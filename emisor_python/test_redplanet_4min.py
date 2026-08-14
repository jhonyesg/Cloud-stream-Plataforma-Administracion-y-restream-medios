#!/usr/bin/env python3
"""
GStreamer production-test pipeline for Red Planet channel.
Emits continuously for ~4 minutes with loop logging.
Can be started as a background process.
"""

import sys
import gi
import signal
import time
import threading
import json
import argparse

# Force unbuffered output
sys.stdout.reconfigure(line_buffering=True)

gi.require_version("Gst", "1.0")
from gi.repository import Gst, GLib

Gst.init(None)

VIDEO_FILE = "/mnt/multimedia/Red Plane/Inicio canal.mp4"
RTMP_URL = "rtmp://127.0.0.1:1935/live/Redplanet_TV"
WIDTH, HEIGHT, FPS = 1280, 720, 30
VIDEO_BITRATE_KBPS = 1000
AUDIO_BITRATE_KBPS = 128
RUN_SECONDS = 240  # 4 minutes

class RedPlanetEmitter:
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

        self.pipeline = Gst.Pipeline.new("redplanet-emitter")
        bus = self.pipeline.get_bus()
        bus.add_signal_watch()
        bus.connect("message", self.on_bus_message)

        # Source
        self.src = Gst.ElementFactory.make("uridecodebin", "src")
        self.src.set_property("uri", f"file://{VIDEO_FILE}")
        self.src.set_property("use-buffering", True)

        # Video branch
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

        # Audio branch
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

        # Mux & sink
        self.mux = Gst.ElementFactory.make("flvmux", "mux")
        self.mux.set_property("streamable", True)
        self.sink = Gst.ElementFactory.make("rtmpsink", "sink")
        self.sink.set_property("location", f"{RTMP_URL} live=1")

        for el in [
            self.src,
            self.v_queue1, self.v_convert, self.v_scale, self.v_capsfilter,
            self.v_enc, self.v_queue2,
            self.a_queue1, self.a_convert, self.a_resample, self.a_capsfilter,
            self.a_enc, self.a_queue2,
            self.mux, self.sink,
        ]:
            if el is None:
                print("ERROR: Failed to create element.")
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

        self.src.connect("pad-added", self.on_pad_added)
        self.src.connect("pad-removed", self.on_pad_removed)

    def on_pad_added(self, element, pad):
        caps = pad.get_current_caps()
        if not caps:
            return
        name = caps.get_structure(0).get_name()

        if name.startswith("video/"):
            sink = self.v_queue1.get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)
        elif name.startswith("audio/"):
            sink = self.a_queue1.get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)

    def on_pad_removed(self, element, pad):
        pass

    def on_bus_message(self, bus, message):
        t = message.type
        if t == Gst.MessageType.EOS:
            elapsed = time.time() - self.start_time
            GLib.idle_add(self.do_loop)
        elif t == Gst.MessageType.ERROR:
            err, debug = message.parse_error()
            elapsed = time.time() - self.start_time
            print(f"[ERROR] at {elapsed:.1f}s: {err.message}")
            if debug:
                print(f"  {debug}")
            self.stats["errors"] += 1
            # Try to restart after 3s
            GLib.timeout_add_seconds(3, lambda: self.do_loop() or False)
        elif t == Gst.MessageType.WARNING:
            warn, debug = message.parse_warning()
            print(f"[WARN] {warn.message}")
        elif t == Gst.MessageType.STATE_CHANGED:
            if message.src == self.pipeline:
                old, new, _ = message.parse_state_changed()
                if new == Gst.State.PLAYING:
                    self.stats["status"] = "live"
                elif new == Gst.State.PAUSED:
                    self.stats["status"] = "paused"
                elif new == Gst.State.NULL:
                    self.stats["status"] = "offline"

    def do_loop(self):
        if self.restarting:
            return False
        self.restarting = True
        self.eos_count += 1
        self.stats["loops"] = self.eos_count
        elapsed = time.time() - self.start_time
        print(f"[LOOP #{self.eos_count}] t={elapsed:.1f}s | loops={self.eos_count} | status=live")

        ok = self.pipeline.seek_simple(
            Gst.Format.TIME,
            Gst.SeekFlags.FLUSH | Gst.SeekFlags.ACCURATE,
            0
        )
        if not ok:
            print(f"[LOOP #{self.eos_count}] seek failed!")

        self.restarting = False
        return False

    def log_status(self):
        while self.stats["status"] != "offline":
            time.sleep(10)
            elapsed = time.time() - self.start_time
            loops = self.stats["loops"]
            errors = self.stats["errors"]
            print(f"[STATUS] t={elapsed:.0f}s | loops={loops} | errors={errors} | status={self.stats['status']}")

    def run(self):
        print(f"[START] Red Planet 4-Minute Emission Test")
        print(f"[START] File: {VIDEO_FILE}")
        print(f"[START] RTMP: {RTMP_URL}")
        print(f"[START] Output: {WIDTH}x{HEIGHT} @ {VIDEO_BITRATE_KBPS}kbps video / {AUDIO_BITRATE_KBPS}kbps audio")
        print(f"[START] Duration: {self.run_seconds}s (press Ctrl+C to stop early)")
        print("")

        self.stats["start_time"] = time.time()
        self.pipeline.set_state(Gst.State.PLAYING)

        # Status logger thread
        logger = threading.Thread(target=self.log_status, daemon=True)
        logger.start()

        def on_sig(signum, frame):
            print(f"\n[SIGNAL] Stopping...")
            self.shutdown()
        signal.signal(signal.SIGINT, on_sig)
        signal.signal(signal.SIGTERM, on_sig)

        # Auto-stop
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
        print("EMISSION TEST COMPLETE")
        print(f"Duration: {duration:.1f}s")
        print(f"Loops completed: {self.stats['loops']}")
        print(f"Errors: {self.stats['errors']}")
        print(f"Status: {self.stats['status']}")
        print("=" * 50)
        self.pipeline.set_state(Gst.State.NULL)
        self.loop.quit()

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--seconds", type=int, default=RUN_SECONDS, help="Run duration in seconds")
    args = parser.parse_args()

    emitter = RedPlanetEmitter(run_seconds=args.seconds)
    emitter.run()
    print("Done.")
