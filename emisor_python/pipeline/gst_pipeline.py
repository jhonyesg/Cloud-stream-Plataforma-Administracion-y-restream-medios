import os
import sys
import time
from typing import Any, Dict
import gi

gi.require_version("Gst", "1.0")
from gi.repository import Gst, GLib

Gst.init(None)

from models.timeline_item import TimelineItem
from models.virtual_screen import VirtualScreen


class GstPipelineManager:
    """Manages the GStreamer pipeline for a single channel."""

    def __init__(self, channel_id: str, virtual_screen: VirtualScreen, root_path: str = ""):
        self.channel_id = channel_id
        self.virtual_screen = virtual_screen
        self.root_path = root_path
        self.pipeline: Gst.Pipeline | None = None
        self.current_item: TimelineItem | None = None
        self.item_index = 0
        self.timeline_queue: list[TimelineItem] = []
        self.loops_completed = 0
        self.started_at = 0.0
        self._restarting = False
        self.eos_count = 0
        self._dynamic_links = {}
        self._pending_seek_offset_sec: float | None = None

    def _apply_start_position(self, src: Any) -> None:
        """Apply a pending seek offset to the source element if set."""
        if self._pending_seek_offset_sec is None or self._pending_seek_offset_sec <= 0:
            return
        try:
            src.set_property("start-position", int(self._pending_seek_offset_sec * Gst.SECOND))
        except Exception:
            # Source element may not support start-position; degrade silently.
            pass
        finally:
            self._pending_seek_offset_sec = None

    def build_pipeline_for_item(self, item: TimelineItem) -> Gst.Pipeline:
        """Build a new pipeline for the given timeline item."""
        if self.pipeline:
            self.pipeline.set_state(Gst.State.NULL)

        pipeline = Gst.Pipeline.new(f"channel-{self.channel_id}")
        bus = pipeline.get_bus()
        bus.add_signal_watch()
        bus.connect("message", self._on_bus_message)

        # Resolve file path
        file_path = self._resolve_file_path(item)

        if self.virtual_screen.is_copy_mode:
            self._build_copy_pipeline(pipeline, file_path)
        else:
            self._build_transcode_pipeline(pipeline, file_path, item)

        return pipeline

    def _resolve_file_path(self, item: TimelineItem) -> str:
        """Resolve absolute file path from channel root_path + filename."""
        if item.filename and self.root_path:
            path = os.path.join(self.root_path, item.filename)
            if os.path.exists(path):
                return path
        # Fallback: try common paths
        root_candidates = [
            f"/mnt/multimedia/{self.virtual_screen.name}",
            f"/mnt/multimedia/{self.virtual_screen.name.replace(' ', '')}",
        ]
        if item.filename:
            for root in root_candidates:
                path = os.path.join(root, item.filename)
                if os.path.exists(path):
                    return path
        raise FileNotFoundError(f"Media file not found for item {item.id}: {item.filename}")

    def _build_copy_pipeline(self, pipeline: Gst.Pipeline, file_path: str) -> None:
        """Build passthrough H.264/AAC pipeline."""
        filesrc = Gst.ElementFactory.make("filesrc", "filesrc")
        filesrc.set_property("location", file_path)
        self._apply_start_position(filesrc)
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
        sink.set_property("location", f"{self.virtual_screen.output_url} live=1")

        elements = [
            filesrc, qtdemux,
            v_queue1, v_parse, v_queue2,
            a_queue1, a_parse, a_queue2,
            mux, sink,
        ]
        for el in elements:
            if el is None:
                raise RuntimeError("Failed to create GStreamer element (missing plugin?)")
            pipeline.add(el)

        filesrc.link(qtdemux)
        v_queue1.link(v_parse)
        v_parse.link(v_queue2)
        v_queue2.link(mux)
        a_queue1.link(a_parse)
        a_parse.link(a_queue2)
        a_queue2.link(mux)
        mux.link(sink)

        qtdemux.connect("pad-added", self._on_qtdemux_pad_added)
        self._dynamic_links = {
            'v_queue1': v_queue1,
            'a_queue1': a_queue1,
        }

    def _build_transcode_pipeline(self, pipeline: Gst.Pipeline, file_path: str, item: TimelineItem) -> None:
        """Build re-encode pipeline with optional logo overlay."""
        import urllib.parse
        uri = "file://" + urllib.parse.quote(file_path)

        src = Gst.ElementFactory.make("uridecodebin", "src")
        src.set_property("uri", uri)
        self._apply_start_position(src)

        v_queue1 = Gst.ElementFactory.make("queue", "v_queue1")
        v_convert = Gst.ElementFactory.make("videoconvert", "v_convert")
        v_scale = Gst.ElementFactory.make("videoscale", "v_scale")
        v_caps = Gst.ElementFactory.make("capsfilter", "v_caps")
        v_caps.set_property("caps", Gst.Caps.from_string(
            f"video/x-raw,format=I420,width={self.virtual_screen.width},height={self.virtual_screen.height},framerate={self.virtual_screen.fps}/1"
        ))

        v_enc = Gst.ElementFactory.make("x264enc", "v_enc")
        v_enc.set_property("bitrate", self.virtual_screen.video_bitrate_kbps)
        v_enc.set_property("tune", 0x00000004)  # zerolatency
        v_enc.set_property("speed-preset", 3)   # veryfast
        v_enc.set_property("key-int-max", self.virtual_screen.fps * 2)
        v_queue2 = Gst.ElementFactory.make("queue", "v_queue2")

        a_queue1 = Gst.ElementFactory.make("queue", "a_queue1")
        a_convert = Gst.ElementFactory.make("audioconvert", "a_convert")
        a_resample = Gst.ElementFactory.make("audioresample", "a_resample")
        a_caps = Gst.ElementFactory.make("capsfilter", "a_caps")
        a_caps.set_property("caps", Gst.Caps.from_string("audio/x-raw,rate=44100,channels=2"))
        a_enc = Gst.ElementFactory.make("avenc_aac", "a_enc")
        a_enc.set_property("bitrate", self.virtual_screen.audio_bitrate_kbps * 1000)
        a_queue2 = Gst.ElementFactory.make("queue", "a_queue2")

        mux = Gst.ElementFactory.make("flvmux", "mux")
        mux.set_property("streamable", True)
        sink = Gst.ElementFactory.make("rtmpsink", "sink")
        sink.set_property("location", f"{self.virtual_screen.output_url} live=1")

        # Optional logo overlay
        elements = [
            src, v_queue1, v_convert, v_scale, v_caps,
            v_enc, v_queue2,
            a_queue1, a_convert, a_resample, a_caps,
            a_enc, a_queue2,
            mux, sink,
        ]

        if self.virtual_screen.logo_enabled:
            logo_path = self._resolve_logo_path()
            if logo_path and os.path.exists(logo_path):
                overlay = Gst.ElementFactory.make("gdkpixbufoverlay", "logo")
                overlay.set_property("location", logo_path)
                overlay.set_property("offset-x", self.virtual_screen.logo_x)
                overlay.set_property("offset-y", self.virtual_screen.logo_y)
                overlay.set_property("overlay-width", self.virtual_screen.logo_w)
                overlay.set_property("overlay-height", self.virtual_screen.logo_h)
                overlay.set_property("alpha", self.virtual_screen.logo_opacity)
                elements.insert(elements.index(v_enc), overlay)
                v_convert.link(overlay)
                overlay.link(v_scale)
            else:
                print(f"[WARN] Logo file not found: {logo_path}")
                v_convert.link(v_scale)
        else:
            v_convert.link(v_scale)

        for el in elements:
            if el is None:
                raise RuntimeError("Failed to create GStreamer element (missing plugin?)")
            pipeline.add(el)

        v_queue1.link(v_convert)
        v_scale.link(v_caps)
        v_caps.link(v_enc)
        v_enc.link(v_queue2)
        v_queue2.link(mux)

        a_queue1.link(a_convert)
        a_convert.link(a_resample)
        a_resample.link(a_caps)
        a_caps.link(a_enc)
        a_enc.link(a_queue2)
        a_queue2.link(mux)

        mux.link(sink)

        src.connect("pad-added", self._on_uridecodebin_pad_added)
        self._dynamic_links = {
            'v_queue1': v_queue1,
            'a_queue1': a_queue1,
        }

    def _resolve_logo_path(self) -> str | None:
        """Resolve logo file path."""
        # Logo media items are typically in storage/app/public/
        # We use a simple heuristic here.
        candidates = [
            f"/mnt/multimedia/{self.virtual_screen.name}/logo.png",
            f"/mnt/multimedia/{self.virtual_screen.name}/logo.jpg",
        ]
        for c in candidates:
            if os.path.exists(c):
                return c
        return None

    def _on_qtdemux_pad_added(self, element, pad):
        caps = pad.get_current_caps()
        if not caps:
            return
        name = caps.get_structure(0).get_name()
        if name.startswith("video/"):
            sink = self._dynamic_links['v_queue1'].get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)
        elif name.startswith("audio/"):
            sink = self._dynamic_links['a_queue1'].get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)

    def _on_uridecodebin_pad_added(self, element, pad):
        caps = pad.get_current_caps()
        if not caps:
            return
        name = caps.get_structure(0).get_name()
        if name.startswith("video/"):
            sink = self._dynamic_links['v_queue1'].get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)
        elif name.startswith("audio/"):
            sink = self._dynamic_links['a_queue1'].get_static_pad("sink")
            if not pad.is_linked():
                pad.link(sink)

    def _on_bus_message(self, bus, message):
        t = message.type
        if t == Gst.MessageType.EOS:
            print(f"[PIPELINE] Bus EOS received")
            self._on_source_eos()
        elif t == Gst.MessageType.ERROR:
            err, debug = message.parse_error()
            print(f"[PIPELINE] ERROR: {err.message}")
            if debug:
                print(f"  {debug}")
            self._on_pipeline_error()
        elif t == Gst.MessageType.STATE_CHANGED:
            old, new, _ = message.parse_state_changed()
            if message.src == self.pipeline:
                print(f"[PIPELINE] State: {old.value_nick} -> {new.value_nick}")
        elif t == Gst.MessageType.WARNING:
            warn, debug = message.parse_warning()
            print(f"[PIPELINE] WARNING: {warn.message}")

    def _on_source_eos(self):
        """Handle end-of-stream: loop to beginning without rebuilding pipeline."""
        if self._restarting:
            return
        self._restarting = True
        self.eos_count += 1
        elapsed = time.time() - self.started_at
        print(f"[PIPELINE] EOS received (#{self.eos_count}) at t={elapsed:.1f}s — looping...")

        # Use seek_simple to restart from 0 without stopping the encoder/muxer
        ok = self.pipeline.seek_simple(
            Gst.Format.TIME,
            Gst.SeekFlags.FLUSH | Gst.SeekFlags.ACCURATE,
            0
        )
        if not ok:
            print(f"[PIPELINE] seek_simple(0) failed, will rebuild pipeline")
            # Fallback: rebuild pipeline
            try:
                self.pipeline.set_state(Gst.State.NULL)
                self.pipeline = self.build_pipeline_for_item(self.current_item)
                self.pipeline.set_state(Gst.State.PLAYING)
            except Exception as e:
                print(f"[PIPELINE] Failed to rebuild: {e}")
                self._on_pipeline_error()
        else:
            # After FLUSH seek, the pipeline may return to PAUSED.
            # Explicitly set to PLAYING to ensure it resumes.
            state_change = self.pipeline.set_state(Gst.State.PLAYING)
            if state_change == Gst.StateChangeReturn.FAILURE:
                print("[PIPELINE] set_state(PLAYING) after seek failed — pipeline may be stuck")
            else:
                print("[PIPELINE] Seek + PLAYING OK — resumed from 0")

        self._restarting = False

    def _on_pipeline_error(self):
        """Handle fatal pipeline error."""
        # Signal upstream to trigger recovery
        # For now, we stop and expect the daemon to restart us
        print("[PIPELINE] Entering error state")
        if self.pipeline:
            self.pipeline.set_state(Gst.State.NULL)

    def play_item(self, item: TimelineItem, seek_offset_sec: float | None = None) -> None:
        """Start playing a specific timeline item.

        If `seek_offset_sec` is provided, the GStreamer source element will
        be configured with `start-position` so playback begins at the
        requested offset. The seek is consumed by the next pipeline build.
        """
        self.current_item = item
        self._pending_seek_offset_sec = seek_offset_sec
        self.pipeline = self.build_pipeline_for_item(item)
        self.pipeline.set_state(Gst.State.PLAYING)
        self.started_at = time.time()

    def stop(self) -> None:
        """Stop the pipeline."""
        if self.pipeline:
            self.pipeline.set_state(Gst.State.NULL)
            self.pipeline = None

    def shutdown(self) -> None:
        """Alias for stop() — used by ChannelDaemon."""
        self.stop()

    def get_stream_stats(self) -> Dict[str, Any]:
        """Return current stream metrics: bitrate, fps, frames, state."""
        stats: Dict[str, Any] = {
            'video_bitrate': 0,
            'fps': 0.0,
            'frames_sent': 0,
            'pipeline_state': 'null',
        }
        if not self.pipeline:
            return stats

        # Pipeline state
        _, state, _ = self.pipeline.get_state(0)
        stats['pipeline_state'] = state.value_nick if state else 'unknown'

        # If pipeline is not PLAYING, report zeros so the UI shows "—"
        if state != Gst.State.PLAYING:
            return stats

        # Query duration and position
        success_dur, duration = self.pipeline.query_duration(Gst.Format.TIME)
        success_pos, position = self.pipeline.query_position(Gst.Format.TIME)

        if success_dur and success_pos and duration > 0:
            # Approximate frames from position and fps
            pos_sec = position / Gst.SECOND
            # Use virtual screen fps as reference
            fps = self.virtual_screen.fps if self.virtual_screen else 30
            stats['fps'] = float(fps)
            stats['frames_sent'] = int(pos_sec * fps)

            # Bitrate from virtual screen config
            stats['video_bitrate'] = getattr(self.virtual_screen, 'video_bitrate_kbps', 0)

        return stats

    @property
    def broadcast_clock_sec(self) -> int:
        """Current broadcast clock in seconds since midnight."""
        if not self.started_at:
            return 0
        now = time.time()
        # In a real implementation, this uses the scheduled start time
        # For now, approximate with seconds since start
        elapsed = int(now - self.started_at)
        # Add loops
        total = elapsed + (self.loops_completed * 86400)
        return total % 86400

    def get_position_sec(self) -> float:
        """Current playback position within the current item in seconds."""
        if not self.pipeline:
            return 0.0
        
        success, position = self.pipeline.query_position(Gst.Format.TIME)
        if success and position != Gst.CLOCK_TIME_NONE:
            return position / Gst.SECOND
        
        # Fallback: approximate with elapsed time since start
        if self.started_at:
            return time.time() - self.started_at
        return 0.0
