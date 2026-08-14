#!/usr/bin/env python3
"""
GStreamer test pipeline for Red Planet channel — GAPLESS LOOP v2.
Uses blocking EOS probes to prevent the encoder from receiving EOS.
When the source ends, we flush, seek to 0, and continue.
"""

import sys
import gi
import signal

gi.require_version("Gst", "1.0")
from gi.repository import Gst, GLib

Gst.init(None)

VIDEO_FILE = "/mnt/multimedia/Red Plane/Inicio canal.mp4"
RTMP_URL = "rtmp://127.0.0.1:1935/live/Redplanet_TV"
WIDTH, HEIGHT, FPS = 1280, 720, 30
VIDEO_BITRATE_KBPS = 1000
AUDIO_BITRATE_KBPS = 128

class RedPlanetEmitter:
    def __init__(self):
        self.loop = GLib.MainLoop()
        self.eos_count = 0
        self.restarting = False
        self.source_pads = []  # pads from decodebin that we need to block

        # Pipeline
        self.pipeline = Gst.Pipeline.new("redplanet-emitter")
        bus = self.pipeline.get_bus()
        bus.add_signal_watch()
        bus.connect("message", self.on_bus_message)

        # ─── Source ───
        self.src = Gst.ElementFactory.make("uridecodebin", "src")
        self.src.set_property("uri", f"file://{VIDEO_FILE}")

        # ─── Video branch ───
        self.v_queue1 = Gst.ElementFactory.make("queue", "v_queue1")
        self.v_queue1.set_property("max-size-time", 5 * Gst.SECOND)
        self.v_convert = Gst.ElementFactory.make("videoconvert", "v_convert")
        self.v_scale = Gst.ElementFactory.make("videoscale", "v_scale")
        self.v_capsfilter = Gst.ElementFactory.make("capsfilter", "v_caps")
        v_caps = Gst.Caps.from_string(
            f"video/x-raw,format=I420,width={WIDTH},height={HEIGHT},framerate={FPS}/1"
        )
        self.v_capsfilter.set_property("caps", v_caps)
        self.v_enc = Gst.ElementFactory.make("x264enc", "v_enc")
        self.v_enc.set_property("bitrate", VIDEO_BITRATE_KBPS)
        self.v_enc.set_property("tune", 0x00000004)  # zerolatency
        self.v_enc.set_property("speed-preset", 3)   # veryfast
        self.v_enc.set_property("key-int-max", FPS * 2)
        self.v_queue2 = Gst.ElementFactory.make("queue", "v_queue2")

        # ─── Audio branch ───
        self.a_queue1 = Gst.ElementFactory.make("queue", "a_queue1")
        self.a_queue1.set_property("max-size-time", 5 * Gst.SECOND)
        self.a_convert = Gst.ElementFactory.make("audioconvert", "a_convert")
        self.a_resample = Gst.ElementFactory.make("audioresample", "a_resample")
        self.a_capsfilter = Gst.ElementFactory.make("capsfilter", "a_caps")
        a_caps = Gst.Caps.from_string("audio/x-raw,rate=44100,channels=2")
        self.a_capsfilter.set_property("caps", a_caps)
        self.a_enc = Gst.ElementFactory.make("avenc_aac", "a_enc")
        self.a_enc.set_property("bitrate", AUDIO_BITRATE_KBPS * 1000)
        self.a_queue2 = Gst.ElementFactory.make("queue", "a_queue2")

        # ─── Mux & sink ───
        self.mux = Gst.ElementFactory.make("flvmux", "mux")
        self.mux.set_property("streamable", True)
        self.sink = Gst.ElementFactory.make("rtmpsink", "sink")
        self.sink.set_property("location", f"{RTMP_URL} live=1")

        # Add to pipeline
        for el in [
            self.src,
            self.v_queue1, self.v_convert, self.v_scale, self.v_capsfilter,
            self.v_enc, self.v_queue2,
            self.a_queue1, self.a_convert, self.a_resample, self.a_capsfilter,
            self.a_enc, self.a_queue2,
            self.mux, self.sink,
        ]:
            if el is None:
                print("ERROR: Failed to create element. Missing GStreamer plugin?")
                sys.exit(1)
            self.pipeline.add(el)

        # Link static branches
        self.v_queue1.link(self.v_convert)
        self.v_convert.link(self.v_scale)
        self.v_scale.link(self.v_capsfilter)
        self.v_capsfilter.link(self.v_enc)
        self.v_enc.link(self.v_queue2)
        self.v_queue2.link(self.mux)

        self.a_queue1.link(self.a_convert)
        self.a_convert.link(self.a_resample)
        self.a_resample.link(self.a_capsfilter)
        self.a_capsfilter.link(self.a_enc)
        self.a_enc.link(self.a_queue2)
        self.a_queue2.link(self.mux)

        self.mux.link(self.sink)

        # Dynamic pad linking
        self.src.connect("pad-added", self.on_pad_added)
        self.src.connect("pad-removed", self.on_pad_removed)

    def on_pad_added(self, element, pad):
        caps = pad.get_current_caps()
        if not caps:
            return
        name = caps.get_structure(0).get_name()

        # Add a blocking probe to intercept EOS BEFORE it reaches the encoder
        pad.add_probe(Gst.PadProbeType.EVENT_DOWNSTREAM, self.on_pad_event)

        if name.startswith("video/"):
            sink_pad = self.v_queue1.get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink_pad)
                print(" Video linked + EOS probe installed")
        elif name.startswith("audio/"):
            sink_pad = self.a_queue1.get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink_pad)
                print(" Audio linked + EOS probe installed")

    def on_pad_removed(self, element, pad):
        print(f" Removed: {pad.get_name()}")

    def on_pad_event(self, pad, info):
        event = info.get_event()
        if event.type == Gst.EventType.EOS:
            print(f"[PROBE] EOS intercepted on {pad.get_name()}")
            # Block EOS from propagating. We handle restart ourselves.
            return Gst.PadProbeReturn.DROP
        return Gst.PadProbeReturn.OK

    def on_bus_message(self, bus, message):
        t = message.type
        if t == Gst.MessageType.EOS:
            # This EOS comes from the pipeline level (all elements drained)
            # If we got here, the encoder ran out of data. We need to restart.
            self.eos_count += 1
            print(f"[BUS] Pipeline EOS (#{self.eos_count})")
            self.handle_pipeline_eos()
        elif t == Gst.MessageType.ERROR:
            err, debug = message.parse_error()
            print(f"[BUS] ERROR: {err.message}")
            if debug:
                print(f"  DEBUG: {debug}")
            self.shutdown()
        elif t == Gst.MessageType.WARNING:
            warn, debug = message.parse_warning()
            print(f"[BUS] WARNING: {warn.message}")
        elif t == Gst.MessageType.STATE_CHANGED:
            if message.src == self.pipeline:
                old, new, _ = message.parse_state_changed()
                print(f"[STATE] {old.value_nick} -> {new.value_nick}")

    def handle_pipeline_eos(self):
        if self.restarting:
            return
        self.restarting = True

        print("[LOOP] Performing flush-restart to keep encoder alive...")

        # Flush start: unblock all pads, discard buffers
        self.pipeline.send_event(Gst.Event.new_flush_start())

        # On the source, we want to restart from 0.
        # Easiest method: seek to 0 with flush.
        self.src.seek(
            1.0,                # rate
            Gst.Format.TIME,
            Gst.SeekFlags.FLUSH | Gst.SeekFlags.KEY_UNIT,
            Gst.SeekType.SET,   # start type
            0,                  # start: 0 ns
            Gst.SeekType.NONE,  # end type
            Gst.CLOCK_TIME_NONE # end: no change
        )

        # Flush stop: resume data flow
        self.pipeline.send_event(Gst.Event.new_flush_stop(True))

        self.restarting = False
        print("[LOOP] Flush-restart complete")

    def run(self):
        print(f"[START] Red Planet Emitter (Gapless v2)")
        print(f"[START] Video: {VIDEO_FILE}")
        print(f"[START] RTMP: {RTMP_URL}")

        self.pipeline.set_state(Gst.State.PLAYING)

        def on_sig(signum, frame):
            print(f"\n[SIGNAL] {signum} received, shutting down...")
            self.shutdown()

        signal.signal(signal.SIGINT, on_sig)
        signal.signal(signal.SIGTERM, on_sig)

        try:
            self.loop.run()
        except KeyboardInterrupt:
            pass

    def shutdown(self):
        print("[STOP] Setting NULL...")
        self.pipeline.set_state(Gst.State.NULL)
        self.loop.quit()
        print("[STOP] Done.")

if __name__ == "__main__":
    emitter = RedPlanetEmitter()
    emitter.run()
