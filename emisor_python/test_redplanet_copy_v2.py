#!/usr/bin/env python3
"""
GStreamer test pipeline for Red Planet channel — CODEC COPY MODE v2.
Uses filesrc + qtdemux for direct H.264/AAC passthrough (no decode).
Emits continuously for 3 minutes.
"""

import sys
import gi
import signal
import time
import threading

gi.require_version("Gst", "1.0")
from gi.repository import Gst, GLib

Gst.init(None)

VIDEO_FILE = "/mnt/multimedia/Red Plane/Inicio canal.mp4"
RTMP_URL = "rtmp://127.0.0.1:1935/live/Redplanet_TV"
RUN_SECONDS = 180  # 3 minutes

class RedPlanetEmitterCopy:
    def __init__(self, run_seconds=RUN_SECONDS):
        self.loop = GLib.MainLoop()
        self.eos_count = 0
        self.restarting = False
        self.start_time = time.time()
        self.run_seconds = run_seconds
        self.stats = {
            "loops": 0,
            "errors": 0,
            "start_time": None,
            "end_time": None,
            "status": "starting"
        }

        self.pipeline = Gst.Pipeline.new("redplanet-copy")
        bus = self.pipeline.get_bus()
        bus.add_signal_watch()
        bus.connect("message", self.on_bus_message)

        # ─── Source: filesrc + qtdemux (raw container demux, no decode) ───
        self.filesrc = Gst.ElementFactory.make("filesrc", "filesrc")
        self.filesrc.set_property("location", VIDEO_FILE)
        self.qtdemux = Gst.ElementFactory.make("qtdemux", "qtdemux")

        # ─── Video branch: H.264 passthrough ───
        self.v_queue1 = Gst.ElementFactory.make("queue", "v_queue1")
        self.v_queue1.set_property("max-size-time", 5 * Gst.SECOND)
        self.v_parse = Gst.ElementFactory.make("h264parse", "v_parse")
        self.v_parse.set_property("config-interval", -1)
        self.v_queue2 = Gst.ElementFactory.make("queue", "v_queue2")

        # ─── Audio branch: AAC passthrough ───
        self.a_queue1 = Gst.ElementFactory.make("queue", "a_queue1")
        self.a_queue1.set_property("max-size-time", 5 * Gst.SECOND)
        self.a_parse = Gst.ElementFactory.make("aacparse", "a_parse")
        self.a_queue2 = Gst.ElementFactory.make("queue", "a_queue2")

        # ─── Mux & sink ───
        self.mux = Gst.ElementFactory.make("flvmux", "mux")
        self.mux.set_property("streamable", True)
        self.sink = Gst.ElementFactory.make("rtmpsink", "sink")
        self.sink.set_property("location", f"{RTMP_URL} live=1")

        for el in [
            self.filesrc, self.qtdemux,
            self.v_queue1, self.v_parse, self.v_queue2,
            self.a_queue1, self.a_parse, self.a_queue2,
            self.mux, self.sink,
        ]:
            if el is None:
                print("ERROR: Failed to create element.")
                sys.exit(1)
            self.pipeline.add(el)

        # Link source to demuxer
        self.filesrc.link(self.qtdemux)

        # Link static passthrough branches
        self.v_queue1.link(self.v_parse)
        self.v_parse.link(self.v_queue2)
        self.v_queue2.link(self.mux)

        self.a_queue1.link(self.a_parse)
        self.a_parse.link(self.a_queue2)
        self.a_queue2.link(self.mux)

        self.mux.link(self.sink)

        # Dynamic pads from qtdemux
        self.qtdemux.connect("pad-added", self.on_pad_added)
        self.qtdemux.connect("pad-removed", self.on_pad_removed)
        self.qtdemux.connect("no-more-pads", self.on_no_more_pads)

    def on_pad_added(self, element, pad):
        caps = pad.get_current_caps()
        if not caps:
            return
        name = caps.get_structure(0).get_name()
        print(f" qtdemux pad: {pad.get_name()} ({name})")

        if name.startswith("video/"):
            sink = self.v_queue1.get_static_pad("sink")
            if not pad.is_linked():
                ret = pad.link(sink)
                print(f"[LINK] Video -> h264parse: {ret}")
        elif name.startswith("audio/"):
            sink = self.a_queue1.get_static_pad("sink")
            if not pad.is_linked():
                ret = pad.link(sink)
                print(f"[LINK] Audio -> aacparse: {ret}")

    def on_pad_removed(self, element, pad):
        print(f"[UNLINK] qtdemux removed: {pad.get_name()}")

    def on_no_more_pads(self, element):
        print(" qtdemux: no-more-pads")

    def on_bus_message(self, bus, message):
        t = message.type
        if t == Gst.MessageType.EOS:
            elapsed = time.time() - self.start_time
            print(f"[BUS] EOS at {elapsed:.1f}s — looping...")
            GLib.idle_add(self.do_loop)
        elif t == Gst.MessageType.ERROR:
            err, debug = message.parse_error()
            elapsed = time.time() - self.start_time
            print(f"[ERROR] at {elapsed:.1f}s: {err.message}")
            if debug:
                print(f"  {debug}")
            self.stats["errors"] += 1
            GLib.timeout_add_seconds(3, lambda: self.do_loop() or False)
        elif t == Gst.MessageType.WARNING:
            warn, debug = message.parse_warning()
            print(f"[WARN] {warn.message}")
        elif t == Gst.MessageType.STATE_CHANGED:
            if message.src == self.pipeline:
                old, new, _ = message.parse_state_changed()
                if new == Gst.State.PLAYING:
                    self.stats["status"] = "live"
                elif new == Gst.State.NULL:
                    self.stats["status"] = "offline"

    def do_loop(self):
        if self.restarting:
            return False
        self.restarting = True
        self.eos_count += 1
        self.stats["loops"] = self.eos_count
        elapsed = time.time() - self.start_time
        print(f"[LOOP #{self.eos_count}] t={elapsed:.1f}s | loops={self.eos_count} | mode=copy")

        ok = self.pipeline.seek_simple(
            Gst.Format.TIME,
            Gst.SeekFlags.FLUSH | Gst.SeekFlags.ACCURATE,
            0
        )
        print(f"[LOOP #{self.eos_count}] seek={ok}")

        self.restarting = False
        return False

    def log_status(self):
        while self.stats["status"] != "offline":
            time.sleep(10)
            elapsed = time.time() - self.start_time
            loops = self.stats["loops"]
            errors = self.stats["errors"]
            print(f"[STATUS] t={elapsed:.0f}s | loops={loops} | errors={errors} | mode=copy | status={self.stats['status']}")

    def run(self):
        print(f"[START] Red Planet 3-Minute Test — CODEC COPY (filesrc+qtdemux)")
        print(f"[START] File: {VIDEO_FILE}")
        print(f"[START] RTMP: {RTMP_URL}")
        print(f"[START] Mode: H.264 passthrough + AAC passthrough")
        print(f"[START] Duration: {self.run_seconds}s")
        print("")

        self.stats["start_time"] = time.time()
        self.pipeline.set_state(Gst.State.PLAYING)

        logger = threading.Thread(target=self.log_status, daemon=True)
        logger.start()

        def on_sig(signum, frame):
            print(f"\n[SIGNAL] Stopping...")
            self.shutdown()
        signal.signal(signal.SIGINT, on_sig)
        signal.signal(signal.SIGTERM, on_sig)

        GLib.timeout_add_seconds(self.run_seconds, lambda: self.shutdown() or False)

        try:
            self.loop.run()
        except KeyboardInterrupt:
            pass

    def shutdown(self):
        self.stats["status"] = "offline"
        self.stats["end_time"] = time.time()
        duration = self.stats["end_time"] - (self.stats["start_time"] or self.stats["end_time"])
        print("")
        print("=" * 50)
        print("EMISSION TEST COMPLETE — COPY MODE")
        print(f"Duration: {duration:.1f}s")
        print(f"Loops: {self.stats['loops']}")
        print(f"Errors: {self.stats['errors']}")
        print("=" * 50)
        self.pipeline.set_state(Gst.State.NULL)
        self.loop.quit()

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument("--seconds", type=int, default=RUN_SECONDS)
    args = parser.parse_args()

    emitter = RedPlanetEmitterCopy(run_seconds=args.seconds)
    emitter.run()
    print("Done.")
