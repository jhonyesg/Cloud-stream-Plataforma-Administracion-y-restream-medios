#!/usr/bin/env python3
"""
GStreamer test pipeline for Red Planet channel.
Loop a short video infinitely and stream to RTMP.
This is a PROOF OF CONCEPT to validate gapless playback with GStreamer.
"""

import sys
import gi
import signal
import os

gi.require_version("Gst", "1.0")
from gi.repository import Gst, GLib

# Initialize GStreamer
Gst.init(None)

# ─── Configuration ────────────────────────────────────────────────────────────
VIDEO_FILE = "/mnt/multimedia/Red Plane/Inicio canal.mp4"
RTMP_URL = "rtmp://127.0.0.1:1935/live/Redplanet_TV"

# VirtualScreen settings
WIDTH = 1280
HEIGHT = 720
FPS = 30
VIDEO_BITRATE_KBPS = 1000
AUDIO_BITRATE_KBPS = 128

# Encoder tune for live streaming
ENCODER_TUNE = "zerolatency"
ENCODER_PRESET = "veryfast"

# ─── Pipeline string ──────────────────────────────────────────────────────────
#
# Strategy for gapless loop with dynamic source changes:
#   uridecodebin (file://) → videoconvert/audioconvert → capsfilter →
#   x264enc/aacenc → flvmux → rtmp2sink
#
# We use playbin3 OR a custom pipeline with appsrc-like feeding.
# For this test, we use a simple decodebin approach with a bus EOS handler
# that seeks back to 0 (loop) WITHOUT stopping the encoder/muxer output.
#
# Actually, the simplest gapless-for-same-file loop is:
#   multifilesrc location=... loop=true ! decodebin ! ...
# But multifilesrc only works for raw/formatted streams, not re-encoded.
#
# Better: decodebin → encoders → muxer, and on EOS from decodebin,
# we flush, seek to 0, and continue. The encoder/muxer stays live.
#
# EVEN SIMPLER for this test: use playbin3 with loop and a custom video-sink
# that feeds into an encoder. But playbin3 loop seeks internally and may
# have a micro-gap.
#
# ROBUST approach for this test: a single pipeline where the source element
# is managed by our Python code. We use decodebin3 or uridecodebin with
# handle-segment-seeks, and when we get EOS, we set a new URI (same file)
# on the source. The downstream encoder/muxer never stops.
#
# For maximum simplicity in a TEST script, we will use:
#   filesrc → qtdemux → h264parse → avdec_h264 → videoconvert → x264enc
#               → aacparse → avdec_aac → audioconvert → faac → flvmux → rtmpsink
# And on EOS, we set the filesrc to restart from 0.
#
# BUT: qtdemux + filesrc EOS means the whole branch ends. We need to
# use a blocking probe to prevent EOS from propagating to the encoder.
#
# CORRECT approach: use decodebin with a custom pad-added handler,
# and block EOS on the source pads so the encoder continues.
#
# SIMPLER CORRECT approach: playbin3 with GST_PLAY_FLAG_LOOP.
# playbin3 handles the internal source switching and keeps the
# sink pads alive. We connect playbin3 → encoder via a tee or
# by setting playbin3's video-sink to an encoder sink.
#
# Actually, the simplest GStreamer gapless loop for a FILE with
# re-encoding to RTMP is:
#
#   gst-launch-1.0 playbin3 uri=file:///path/to/file loop=0 \
#     video-sink="queue ! videoconvert ! x264enc ! flvmux ! rtmpsink" \
#     audio-sink="queue ! audioconvert ! faac ! flvmux ! rtmpsink"
#
# BUT: playbin3's sinks must be complete pipelines ending in a sink.
# The video-sink and audio-sink MUST be sink elements. flvmux+rtmpsink
# is one sink, but playbin3 expects one sink per stream.
#
# WORKAROUND: interpipe, intersink, or compositor to merge.
# Or: use a bin with funnel/tee.
#
# OK, the CLEAN way for this test: build a pipeline manually.
# We will use uridecodebin for the source, and block EOS.
# When EOS arrives, we restart the same URI.
#
# Pipeline architecture:
#   uridecodebin uri=file:///... name=src
#   src.video_0 → queue → videoconvert → videoscale → capsfilter → x264enc → queue → flvmux → rtmpsink
#   src.audio_0 → queue → audioconvert → audioresample → capsfilter → avenc_aac → queue → flvmux
#
# On EOS from src: src.set_state(READY) → src.set_property('uri', same) → src.set_state(PLAYING)
# The downstream encoder/muxer stays PLAYING the whole time.
#
# Let's test this.

PIPELINE_DESC = f"""
    uridecodebin name=src uri=file://{VIDEO_FILE}
    
    src. ! queue ! videoconvert ! videoscale ! 
        video/x-raw,format=I420,width={WIDTH},height={HEIGHT},framerate={FPS}/1 !
        x264enc bitrate={VIDEO_BITRATE_KBPS} tune={ENCODER_TUNE} speed-preset={ENCODER_PRESET} key-int-max={FPS*2} !
        video/x-h264,profile=main ! queue ! flvmux name=mux streamable=true !
        rtmpsink location="{RTMP_URL} live=1"
    
    src. ! queue ! audioconvert ! audioresample !
        audio/x-raw,rate=44100,channels=2 !
        avenc_aac bitrate={AUDIO_BITRATE_KBPS*1000} ! queue ! mux.
"""

# Actually, the dot notation for uridecodebin dynamic pads is tricky.
# Let's build the pipeline programmatically instead of parse_launch.

class RedPlanetEmitter:
    def __init__(self):
        self.loop = GLib.MainLoop()
        self.pipeline = Gst.Pipeline.new("redplanet-emitter")
        self.eos_count = 0
        self.restarting = False

        # Bus
        bus = self.pipeline.get_bus()
        bus.add_signal_watch()
        bus.connect("message", self.on_bus_message)

        # ─── Build elements ───────────────────────────────────────────────────
        self.src = Gst.ElementFactory.make("uridecodebin", "src")
        self.src.set_property("uri", f"file://{VIDEO_FILE}")

        # Video branch
        self.v_queue1 = Gst.ElementFactory.make("queue", "v_queue1")
        self.v_convert = Gst.ElementFactory.make("videoconvert", "v_convert")
        self.v_scale = Gst.ElementFactory.make("videoscale", "v_scale")
        self.v_capsfilter = Gst.ElementFactory.make("capsfilter", "v_caps")
        v_caps = Gst.Caps.from_string(
            f"video/x-raw,format=I420,width={WIDTH},height={HEIGHT},framerate={FPS}/1"
        )
        self.v_capsfilter.set_property("caps", v_caps)
        self.v_enc = Gst.ElementFactory.make("x264enc", "v_enc")
        self.v_enc.set_property("bitrate", VIDEO_BITRATE_KBPS)
        self.v_enc.set_property("tune", 0x00000004)  # GST_X264_ENC_TUNE_ZEROLATENCY
        self.v_enc.set_property("speed-preset", 3)   # veryfast
        self.v_enc.set_property("key-int-max", FPS * 2)
        self.v_queue2 = Gst.ElementFactory.make("queue", "v_queue2")

        # Audio branch
        self.a_queue1 = Gst.ElementFactory.make("queue", "a_queue1")
        self.a_convert = Gst.ElementFactory.make("audioconvert", "a_convert")
        self.a_resample = Gst.ElementFactory.make("audioresample", "a_resample")
        self.a_capsfilter = Gst.ElementFactory.make("capsfilter", "a_caps")
        a_caps = Gst.Caps.from_string("audio/x-raw,rate=44100,channels=2")
        self.a_capsfilter.set_property("caps", a_caps)
        self.a_enc = Gst.ElementFactory.make("avenc_aac", "a_enc")
        self.a_enc.set_property("bitrate", AUDIO_BITRATE_KBPS * 1000)
        self.a_queue2 = Gst.ElementFactory.make("queue", "a_queue2")

        # Mux & sink
        self.mux = Gst.ElementFactory.make("flvmux", "mux")
        self.mux.set_property("streamable", True)
        self.sink = Gst.ElementFactory.make("rtmpsink", "sink")
        self.sink.set_property("location", f"{RTMP_URL} live=1")

        # Add all to pipeline
        for el in [
            self.src,
            self.v_queue1, self.v_convert, self.v_scale, self.v_capsfilter,
            self.v_enc, self.v_queue2,
            self.a_queue1, self.a_convert, self.a_resample, self.a_capsfilter,
            self.a_enc, self.a_queue2,
            self.mux, self.sink,
        ]:
            if el is None:
                print("ERROR: Failed to create a GStreamer element. Check plugins.")
                sys.exit(1)
            self.pipeline.add(el)

        # Link static parts
        def link_ok(a, b):
            if not a.link(b):
                print(f"ERROR: Failed to link {a.get_name()} -> {b.get_name()}")
                sys.exit(1)

        link_ok(self.v_queue1, self.v_convert)
        link_ok(self.v_convert, self.v_scale)
        link_ok(self.v_scale, self.v_capsfilter)
        link_ok(self.v_capsfilter, self.v_enc)
        link_ok(self.v_enc, self.v_queue2)
        link_ok(self.v_queue2, self.mux)

        link_ok(self.a_queue1, self.a_convert)
        link_ok(self.a_convert, self.a_resample)
        link_ok(self.a_resample, self.a_capsfilter)
        link_ok(self.a_capsfilter, self.a_enc)
        link_ok(self.a_enc, self.a_queue2)
        link_ok(self.a_queue2, self.mux)

        link_ok(self.mux, self.sink)

        # Dynamic pad linking for decodebin
        self.src.connect("pad-added", self.on_pad_added)
        self.src.connect("pad-removed", self.on_pad_removed)

    def on_pad_added(self, element, pad):
        caps = pad.get_current_caps()
        if caps is None:
            return
        structure = caps.get_structure(0)
        name = structure.get_name()

        if name.startswith("video/"):
            sink_pad = self.v_queue1.get_static_pad("sink")
            if pad.is_linked():
                print("[PAD] Video pad already linked, skipping")
                return
            ret = pad.link(sink_pad)
            if ret == Gst.PadLinkReturn.OK:
                print("[PAD] Video linked successfully")
            else:
                print(f"[PAD] Video link failed: {ret}")

        elif name.startswith("audio/"):
            sink_pad = self.a_queue1.get_static_pad("sink")
            if pad.is_linked():
                print("[PAD] Audio pad already linked, skipping")
                return
            ret = pad.link(sink_pad)
            if ret == Gst.PadLinkReturn.OK:
                print("[PAD] Audio linked successfully")
            else:
                print(f"[PAD] Audio link failed: {ret}")

    def on_pad_removed(self, element, pad):
        print(f"[PAD] Removed: {pad.get_name()}")

    def on_bus_message(self, bus, message):
        t = message.type
        if t == Gst.MessageType.EOS:
            self.eos_count += 1
            print(f"[BUS] EOS received (#{self.eos_count})")
            self.handle_source_eos()
        elif t == Gst.MessageType.ERROR:
            err, debug = message.parse_error()
            print(f"[BUS] ERROR: {err.message}")
            if debug:
                print(f"[BUS] DEBUG: {debug}")
            self.shutdown()
        elif t == Gst.MessageType.WARNING:
            warn, debug = message.parse_warning()
            print(f"[BUS] WARNING: {warn.message}")
        elif t == Gst.MessageType.STATE_CHANGED:
            old, new, pending = message.parse_state_changed()
            if message.src == self.pipeline:
                print(f"[STATE] Pipeline: {old.value_nick} -> {new.value_nick}")
        elif t == Gst.MessageType.BUFFERING:
            percent = message.parse_buffering()
            print(f"[BUS] Buffering: {percent}%")

    def handle_source_eos(self):
        """
        Restart the source (same file) without stopping the encoder/muxer.
        This keeps the RTMP stream alive = gapless loop.
        """
        if self.restarting:
            return
        self.restarting = True

        print("[LOOP] Restarting source (seek to 0)...")

        # Method 1: seek on the source element
        # This works if the source supports seek. uridecodebin does.
        # We do a flushing seek to 0.
        # Actually, after EOS the element may not accept seeks.
        # We may need to set it to PAUSED/READY and back.

        # Method 2: set READY -> set URI -> PLAYING
        # This disconnects pads, so we must handle pad-removed/added.
        self.src.set_state(Gst.State.READY)
        # Same URI
        self.src.set_property("uri", f"file://{VIDEO_FILE}")
        self.src.set_state(Gst.State.PLAYING)

        self.restarting = False
        print("[LOOP] Source restarted")

    def run(self):
        print(f"[START] Red Planet Emitter")
        print(f"[START] Video: {VIDEO_FILE}")
        print(f"[START] RTMP: {RTMP_URL}")
        print(f"[START] Output: {WIDTH}x{HEIGHT} @ {VIDEO_BITRATE_KBPS}kbps")

        self.pipeline.set_state(Gst.State.PLAYING)

        # Graceful shutdown on SIGINT/SIGTERM
        def on_sig(signum, frame):
            print(f"\n[SIGNAL] Received {signum}, shutting down...")
            self.shutdown()

        signal.signal(signal.SIGINT, on_sig)
        signal.signal(signal.SIGTERM, on_sig)

        try:
            self.loop.run()
        except KeyboardInterrupt:
            pass

    def shutdown(self):
        print("[STOP] Setting pipeline to NULL...")
        self.pipeline.set_state(Gst.State.NULL)
        self.loop.quit()
        print("[STOP] Done.")


if __name__ == "__main__":
    emitter = RedPlanetEmitter()
    emitter.run()
