#!/usr/bin/env python3
"""
GStreamer test pipeline for Red Planet channel — GAPLESS LOOP v4.
Fixed: use pipeline.seek() instead of src.seek(), handle CLOCK_TIME_NONE.
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

# GStreamer CLOCK_TIME_NONE constant (must be int, not GObject constant directly)
CLOCK_TIME_NONE = 18446744073709551615  # Gst.CLOCK_TIME_NONE

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
        self.v_enc.set_property("tune", 0x00000004)
        self.v_enc.set_property("speed-preset", 3)
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
        pad.add_probe(Gst.PadProbeType.EVENT_DOWNSTREAM, self.on_pad_event)
        if name.startswith("video/"):
            sink = self.v_queue1.get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)
                print(f"[LINK] Video pad linked ({time.time()-self.start_time:.2f}s)")
        elif name.startswith("audio/"):
            sink = self.a_queue1.get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)
                print(f"[LINK] Audio pad linked ({time.time()-self.start_time:.2f}s)")

    def on_pad_removed(self, element, pad):
        print(f"[UNLINK] Pad removed: {pad.get_name()} ({time.time()-self.start_time:.2f}s)")

    def on_pad_event(self, pad, info):
        event = info.get_event()
        if event.type == Gst.EventType.EOS:
            pad_name = pad.get_name()
            elapsed = time.time() - self.start_time
            print(f"[PROBE] EOS on {pad_name} at {elapsed:.2f}s — dropping to keep encoder alive")
            GLib.idle_add(self.do_restart)
            return Gst.PadProbeReturn.DROP
        return Gst.PadProbeReturn.OK

    def do_restart(self):
        if self.restarting:
            return False
        self.restarting = True
        self.eos_count += 1
        elapsed = time.time() - self.start_time
        print(f"[RESTART #{self.eos_count}] Starting flush-restart at {elapsed:.2f}s")

        # Flush-start on pipeline
        self.pipeline.send_event(Gst.Event.new_flush_start())

        # Seek to 0 on the pipeline (not on src directly)
        # We use a segment seek to restart from 0
        seek_event = Gst.Event.new_seek(
            1.0,  # rate
            Gst.Format.TIME,
            Gst.SeekFlags.FLUSH | Gst.SeekFlags.ACCURATE,
            Gst.SeekType.SET, 0,  # start at 0
            Gst.SeekType.SET, CLOCK_TIME_NONE  # end at none (play all)
        )
        self.pipeline.send_event(seek_event)

        # Flush-stop (reset base time)
        self.pipeline.send_event(Gst.Event.new_flush_stop(True))

        self.restarting = False
        elapsed2 = time.time() - self.start_time
        print(f"[RESTART #{self.eos_count}] Completed at {elapsed2:.2f}s")
        return False

    def on_bus_message(self, bus, message):
        t = message.type
        if t == Gst.MessageType.EOS:
            elapsed = time.time() - self.start_time
            print(f"[BUS] Pipeline EOS at {elapsed:.2f}s")
            self.shutdown()
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

    def run(self):
        print(f"[START] Red Planet Emitter v4 — Gapless Loop")
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
