#!/usr/bin/env python3
"""
Production test for Red Planet — standalone mode.
Does NOT require Laravel API. Uses hardcoded config from VirtualScreen.
Emits for ~3 minutes to verify gapless loop stability.
"""

import sys
import os
import time
import signal
import gi

gi.require_version("Gst", "1.0")
from gi.repository import Gst, GLib

Gst.init(None)

VIDEO_FILE = "/mnt/multimedia/Red Plane/Inicio canal.mp4"
RTMP_URL = "rtmp://127.0.0.1:1935/live/Redplanet_TV"
WIDTH, HEIGHT, FPS = 1920, 1080, 30
RUN_SECONDS = 180  # 3 minutes

class ProductionTest:
    def __init__(self):
        self.pipeline = None
        self.loop = GLib.MainLoop()
        self.eos_count = 0
        self.restarting = False
        self.start_time = time.time()

    def build_pipeline(self):
        pipeline = Gst.Pipeline.new("production-test")
        bus = pipeline.get_bus()
        bus.add_signal_watch()
        bus.connect("message", self.on_bus_message)

        filesrc = Gst.ElementFactory.make("filesrc", "filesrc")
        filesrc.set_property("location", VIDEO_FILE)
        qtdemux = Gst.ElementFactory.make("qtdemux", "qtdemux")

        v_queue1 = Gst.ElementFactory.make("queue", "v_queue1")
        v_parse = Gst.ElementFactory.make("h264parse", "v_parse")
        v_parse.set_property("config-interval", -1)
        v_queue2 = Gst.ElementFactory.make("queue", "v_queue2")

        a_queue1 = Gst.ElementFactory.make("queue", "a_queue1")
        a_parse = Gst.ElementFactory.make("aacparse", "a_parse")
        a_queue2 = Gst.ElementFactory.make("queue", "a_queue2")

        mux = Gst.ElementFactory.make("flvmux", "mux")
        mux.set_property("streamable", True)
        sink = Gst.ElementFactory.make("rtmpsink", "sink")
        sink.set_property("location", f"{RTMP_URL} live=1")

        elements = [filesrc, qtdemux, v_queue1, v_parse, v_queue2,
                    a_queue1, a_parse, a_queue2, mux, sink]
        for el in elements:
            if el is None:
                raise RuntimeError("Failed to create element")
            pipeline.add(el)

        filesrc.link(qtdemux)
        v_queue1.link(v_parse)
        v_parse.link(v_queue2)
        v_queue2.link(mux)
        a_queue1.link(a_parse)
        a_parse.link(a_queue2)
        a_queue2.link(mux)
        mux.link(sink)

        qtdemux.connect("pad-added", self.on_pad_added)
        self._links = {'v': v_queue1, 'a': a_queue1}

        return pipeline

    def on_pad_added(self, element, pad):
        caps = pad.get_current_caps()
        if not caps:
            return
        name = caps.get_structure(0).get_name()
        if name.startswith("video/"):
            sink = self._links['v'].get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)
        elif name.startswith("audio/"):
            sink = self._links['a'].get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)

    def on_bus_message(self, bus, message):
        t = message.type
        if t == Gst.MessageType.EOS:
            self.eos_count += 1
            GLib.idle_add(self.do_loop)
        elif t == Gst.MessageType.ERROR:
            err, debug = message.parse_error()
            elapsed = time.time() - self.start_time
            print(f"[ERROR] at {elapsed:.1f}s: {err.message}")
            if debug:
                print(f"  {debug}")
            self.shutdown()

    def do_loop(self):
        if self.restarting:
            return False
        self.restarting = True
        self.eos_count += 1
        elapsed = time.time() - self.start_time
        print(f"[LOOP #{self.eos_count}] t={elapsed:.1f}s | mode=copy | file={os.path.basename(VIDEO_FILE)}")

        ok = self.pipeline.seek_simple(
            Gst.Format.TIME,
            Gst.SeekFlags.FLUSH | Gst.SeekFlags.ACCURATE,
            0
        )
        if not ok:
            print(f"[LOOP #{self.eos_count}] seek failed!")

        self.restarting = False
        return False

    def run(self):
        print(f"[START] Red Planet Production Test")
        print(f"[START] File: {VIDEO_FILE}")
        print(f"[START] RTMP: {RTMP_URL}")
        print(f"[START] Mode: H.264 copy + AAC copy (1920x1080 passthrough)")
        print(f"[START] Duration: {RUN_SECONDS}s")
        print("")

        self.pipeline = self.build_pipeline()
        self.pipeline.set_state(Gst.State.PLAYING)

        def on_sig(signum, frame):
            print(f"\n[SIGNAL] Stopping...")
            self.shutdown()
        signal.signal(signal.SIGINT, on_sig)
        signal.signal(signal.SIGTERM, on_sig)

        GLib.timeout_add_seconds(RUN_SECONDS, lambda: self.shutdown() or False)

        try:
            self.loop.run()
        except KeyboardInterrupt:
            pass

    def shutdown(self):
        elapsed = time.time() - self.start_time
        print("")
        print("=" * 50)
        print("PRODUCTION TEST COMPLETE")
        print(f"Duration: {elapsed:.1f}s")
        print(f"Loops: {self.eos_count}")
        print("=" * 50)
        if self.pipeline:
            self.pipeline.set_state(Gst.State.NULL)
        self.loop.quit()

if __name__ == "__main__":
    test = ProductionTest()
    test.run()
    print("Done.")
