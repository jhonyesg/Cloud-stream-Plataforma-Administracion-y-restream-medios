#!/usr/bin/env python3
"""
GStreamer test pipeline for Red Planet channel — GAPLESS LOOP v5.
Uses Gst.Element.seek_simple on the pipeline with correct argument handling.
Also adds qtdemux + parser for better short-file handling.
"""

import sys
import gi
import signal
import time

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
        self.start_time = time.time()

        self.pipeline = Gst.Pipeline.new("redplanet-emitter")
        bus = self.pipeline.get_bus()
        bus.add_signal_watch()
        bus.connect("message", self.on_bus_message)

        # ─── Source ───
        # For short files, decodebin can be tricky. We use filesrc + qtdemux directly.
        self.filesrc = Gst.ElementFactory.make("filesrc", "filesrc")
        self.filesrc.set_property("location", VIDEO_FILE)
        self.qtdemux = Gst.ElementFactory.make("qtdemux", "qtdemux")

        # ─── Video parser/decoder ───
        self.v_parser = Gst.ElementFactory.make("h264parse", "v_parser")
        self.v_decode = Gst.ElementFactory.make("avdec_h264", "v_decode")
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
        self.v_enc.set_property("tune", 0x00000004)
        self.v_enc.set_property("speed-preset", 3)
        self.v_enc.set_property("key-int-max", FPS * 2)
        self.v_queue2 = Gst.ElementFactory.make("queue", "v_queue2")

        # ─── Audio parser/decoder ───
        self.a_parser = Gst.ElementFactory.make("aacparse", "a_parser")
        self.a_decode = Gst.ElementFactory.make("avdec_aac", "a_decode")
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

        for el in [
            self.filesrc, self.qtdemux,
            self.v_parser, self.v_decode, self.v_queue1, self.v_convert, self.v_scale,
            self.v_capsfilter, self.v_enc, self.v_queue2,
            self.a_parser, self.a_decode, self.a_queue1, self.a_convert, self.a_resample,
            self.a_capsfilter, self.a_enc, self.a_queue2,
            self.mux, self.sink,
        ]:
            if el is None:
                print("ERROR: Failed to create element.")
                sys.exit(1)
            self.pipeline.add(el)

        # Link source to demuxer
        self.filesrc.link(self.qtdemux)

        # Link static decode -> encode branches
        self.v_parser.link(self.v_decode)
        self.v_decode.link(self.v_queue1)
        self.v_queue1.link(self.v_convert)
        self.v_convert.link(self.v_scale)
        self.v_scale.link(self.v_capsfilter)
        self.v_capsfilter.link(self.v_enc)
        self.v_enc.link(self.v_queue2)
        self.v_queue2.link(self.mux)

        self.a_parser.link(self.a_decode)
        self.a_decode.link(self.a_queue1)
        self.a_queue1.link(self.a_convert)
        self.a_convert.link(self.a_resample)
        self.a_resample.link(self.a_capsfilter)
        self.a_capsfilter.link(self.a_enc)
        self.a_enc.link(self.a_queue2)
        self.a_queue2.link(self.mux)

        # Dynamic pads from qtdemux
        self.qtdemux.connect("pad-added", self.on_pad_added)
        self.qtdemux.connect("pad-removed", self.on_pad_removed)
        self.qtdemux.connect("no-more-pads", self.on_no_more_pads)

    def on_pad_added(self, element, pad):
        caps = pad.get_current_caps()
        if not caps:
            return
        name = caps.get_structure(0).get_name()
        print(f"[PAD] qtdemux added: {pad.get_name()} ({name})")

        if name.startswith("video/"):
            sink = self.v_parser.get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)
                print("[LINK] Video -> parser")
        elif name.startswith("audio/"):
            sink = self.a_parser.get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)
                print("[LINK] Audio -> parser")

    def on_pad_removed(self, element, pad):
        print(f"[UNLINK] qtdemux removed: {pad.get_name()}")

    def on_no_more_pads(self, element):
        print("[PAD] qtdemux: no-more-pads")

    def on_bus_message(self, bus, message):
        t = message.type
        if t == Gst.MessageType.EOS:
            elapsed = time.time() - self.start_time
            print(f"[BUS] Pipeline EOS at {elapsed:.2f}s — triggering loop restart")
            GLib.idle_add(self.do_loop)
        elif t == Gst.MessageType.ERROR:
            err, debug = message.parse_error()
            elapsed = time.time() - self.start_time
            print(f"[BUS] ERROR at {elapsed:.2f}s: {err.message}")
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

    def do_loop(self):
        if self.restarting:
            return False
        self.restarting = True
        self.eos_count += 1
        elapsed = time.time() - self.start_time
        print(f"[LOOP #{self.eos_count}] Restarting source at {elapsed:.2f}s")

        # Method: set filesrc to NULL then PLAYING to re-read file
        # But this disconnects downstream. Better: seek to 0 on pipeline.
        # For a short file, a segment seek from 0 is clean.

        # Block flush on the pipeline, seek to 0
        # We use seek_simple with a plain 0 target
        ok = self.pipeline.seek_simple(
            Gst.Format.TIME,
            Gst.SeekFlags.FLUSH | Gst.SeekFlags.ACCURATE,
            0  # seek to 0 nanoseconds
        )
        print(f"[LOOP #{self.eos_count}] seek_simple(0) returned: {ok}")

        self.restarting = False
        return False

    def run(self):
        print(f"[START] Red Planet Emitter v5 — Gapless Loop (filesrc+qtdemux)")
        print(f"[START] File: {VIDEO_FILE}")
        print(f"[START] RTMP: {RTMP_URL}")
        print(f"[START] Expecting ~2.96s per loop. Running for ~20s...")

        self.pipeline.set_state(Gst.State.PLAYING)

        def on_sig(signum, frame):
            print(f"\n[SIGNAL] {signum}")
            self.shutdown()
        signal.signal(signal.SIGINT, on_sig)
        signal.signal(signal.SIGTERM, on_sig)

        GLib.timeout_add_seconds(20, lambda: self.shutdown() or False)

        try:
            self.loop.run()
        except KeyboardInterrupt:
            pass

    def shutdown(self):
        print("[STOP] Cleaning up...")
        self.pipeline.set_state(Gst.State.NULL)
        self.loop.quit()

if __name__ == "__main__":
    emitter = RedPlanetEmitter()
    emitter.run()
    print("Done.")
