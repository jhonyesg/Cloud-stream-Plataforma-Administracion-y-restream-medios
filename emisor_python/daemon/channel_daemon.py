import os
import sys
import time
import threading
import json
from datetime import datetime
from typing import Any, Dict, List, Optional

sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))

from api.daemon_api import DaemonApiServer, daemon_state
from models.timeline_item import TimelineItem
from models.virtual_screen import VirtualScreen
from pipeline.ffmpeg_pipeline import FFmpegPipelineManager
from utils.laravel_client import LaravelClient


class ChannelDaemon:
    """Main daemon that manages emission for a single channel."""

    def __init__(self, channel_id: str, port: int = 0):
        self.channel_id = channel_id
        self.port = port
        self.client = LaravelClient()
        self.api_server = DaemonApiServer(port=port)
        self.pipeline_manager: Optional[FFmpegPipelineManager] = None
        self.timeline_queue: List[TimelineItem] = []
        self.virtual_screen: Optional[VirtualScreen] = None
        self._running = False
        self._stop_event = threading.Event()
        self._heartbeat_thread: threading.Thread | None = None
        self._stats_thread: threading.Thread | None = None
        self._current_item_index = 0
        self._started_at: Optional[datetime] = None
        self._item_started_at: float = 0.0  # timestamp when current item started
        self._status = 'offline'
        self._error_message: Optional[str] = None
        self._config_path: Optional[str] = None
        self._log_rotator = None  # Injected from main.py

    def _log(self, message: str) -> None:
        """Write to log rotator if available, otherwise print."""
        if self._log_rotator:
            self._log_rotator.write(message)
        else:
            print(message)

    def start(self) -> None:
        """Start the daemon: read config file, build pipeline, start API server."""
        self._log(f"[DAEMON] Starting for channel {self.channel_id}")

        # Read config from file (written by Laravel before spawn)
        self._config_path = os.environ.get('DAEMON_CONFIG_PATH')
        if not self._config_path or not os.path.exists(self._config_path):
            raise RuntimeError(f"Config file not found: {self._config_path}")

        with open(self._config_path, 'r') as f:
            config = json.load(f)

        # Parse VirtualScreen config
        vs_data = config.get('virtual_screen')
        if not vs_data:
            raise RuntimeError("No virtual_screen in config file")

        self.virtual_screen = VirtualScreen(
            channel_id=self.channel_id,
            name=vs_data.get('name', vs_data.get('display_name', '')),
            width=vs_data.get('width', 1920),
            height=vs_data.get('height', 1080),
            output_protocol=vs_data.get('output_protocol', 'rtmp'),
            output_url=vs_data.get('output_url', ''),
            fps=vs_data.get('fps', 30),
            video_bitrate_kbps=vs_data.get('video_bitrate_kbps', 2500),
            audio_bitrate_kbps=vs_data.get('audio_bitrate_kbps', 128),
            codec_video=vs_data.get('codec_video', 'libx264'),
            codec_audio=vs_data.get('codec_audio', 'aac'),
            video_preset=vs_data.get('video_preset', 'veryfast'),
            logo_media_item_id=vs_data.get('logo_media_item_id'),
            logo_x=vs_data.get('logo_x', 0),
            logo_y=vs_data.get('logo_y', 0),
            logo_w=vs_data.get('logo_w', 0),
            logo_h=vs_data.get('logo_h', 0),
            logo_opacity=vs_data.get('logo_opacity', 1.0),
            fallback_type=vs_data.get('fallback_type', 'black'),
            fallback_media_item_id=vs_data.get('fallback_media_item_id'),
        )

        # Parse timeline items
        timeline_data = config.get('timeline', {})
        items = timeline_data.get('items', [])
        self.timeline_queue = [TimelineItem(**item) for item in items]

        if not self.timeline_queue:
            raise RuntimeError("No timeline items in config file")

        # Start API server
        self.port = self.api_server.start()
        self._log(f"[DAEMON] API server on port {self.port}")

        # Write registration so Laravel can discover us
        self.client.write_registration(self.channel_id, {
            'pid': os.getpid(),
            'port': self.port,
            'started_at': datetime.utcnow().isoformat(),
        })

        # Get root_path from config
        channel_data = config.get('channel', {})
        root_path = channel_data.get('root_path', '')

        # Build and start pipeline
        self.pipeline_manager = FFmpegPipelineManager(
            channel_id=self.channel_id,
            virtual_screen=self.virtual_screen,
            root_path=root_path,
        )
        self.pipeline_manager.timeline_queue = self.timeline_queue

        # Resolve "start" hint from the config (set by Laravel's orchestrator).
        # If present and matching a timeline item, start at that item with the
        # given file seek. Otherwise fall back to item 0 with no seek.
        start_block = config.get('start') or {}
        start_metadata = self._resolve_start_hint(start_block)

        first_item = start_metadata['item']
        self._current_item_index = start_metadata['index']
        seek_offset = start_metadata['seek_offset_sec']
        self.pipeline_manager.play_item(first_item, seek_offset_sec=seek_offset)
        self._started_at = datetime.utcnow()
        self._item_started_at = time.time()
        self._status = 'live'
        self._log(
            f"[DAEMON] Playing item {self._current_item_index}: "
            f"{first_item.filename} (seek={seek_offset}s)"
        )

        # Inject state into API
        daemon_state.clear()
        daemon_state.update({
            'should_stop': False,
            'reload_requested': False,
            'status': self._status,
            'broadcast_clock_sec': int(start_block.get('broadcast_clock_sec', 0)) if start_block else 0,
            'current_timeline_item_id': first_item.id,
            'item_index': self._current_item_index,
            'loops': 0,
            'timeline_version': timeline_data.get('timeline_version', 0),
        })

        self._running = True

        # Start heartbeat thread
        self._heartbeat_thread = threading.Thread(target=self._heartbeat_loop, daemon=True)
        self._heartbeat_thread.start()

        # Start stats thread (logs stream metrics every 5s)
        self._stats_thread = threading.Thread(target=self._stats_loop, daemon=True)
        self._stats_thread.start()

        # Main loop: simple sleep loop (no GLib needed for FFmpeg subprocess)
        try:
            self._log("[DAEMON] Entering main loop...")
            while self._running and not self._stop_event.is_set():
                self._check_api_state()
                self._stop_event.wait(1)
        except KeyboardInterrupt:
            self._log("[DAEMON] Interrupted")
        finally:
            self.shutdown()

    def _parse_resolution(self, resolution_str: str, index: int) -> int:
        """Parse '1920x1080' into [1920, 1080]."""
        if not resolution_str:
            return 0
        parts = resolution_str.lower().split('x')
        if len(parts) == 2:
            try:
                return int(parts[index].strip())
            except (ValueError, IndexError):
                pass
        return 0

    def _resolve_start_hint(self, start_block: Dict[str, Any]) -> Dict[str, Any]:
        """Resolve the start hint into a concrete (item, index, seek_offset_sec).

        Falls back to timeline_queue[0] with no seek when the hint is missing
        or references an unknown timeline item.
        """
        default = {
            'item': self.timeline_queue[0],
            'index': 0,
            'seek_offset_sec': None,
        }

        if not start_block:
            return default

        timeline_item_id = start_block.get('timeline_item_id')
        if not timeline_item_id:
            return default

        for idx, item in enumerate(self.timeline_queue):
            if item.id == timeline_item_id:
                seek = start_block.get('file_seek_sec')
                if seek is None:
                    seek = start_block.get('offset_sec')
                try:
                    seek_val = float(seek) if seek is not None else None
                except (TypeError, ValueError):
                    seek_val = None
                return {
                    'item': item,
                    'index': idx,
                    'seek_offset_sec': seek_val,
                }

        self._log(
            f"[DAEMON] start.timeline_item_id={timeline_item_id} not found in "
            f"timeline queue ({len(self.timeline_queue)} items); falling back to item 0"
        )
        return default

    def _check_api_state(self) -> bool:
        """Periodic callback to check API requests (stop/reload)."""
        if not self._running:
            return False
        if daemon_state.get('should_stop'):
            self._log("[DAEMON] Stop requested via API")
            self._stop_event.set()
            return False
        if daemon_state.get('reload_requested'):
            daemon_state['reload_requested'] = False
            self._reload_timeline()
        self._update_state()
        self._sync_playhead()
        return True  # Continue calling

    def _reload_timeline(self) -> None:
        """Re-fetch timeline via API and rebuild queue."""
        self._log("[DAEMON] Reloading timeline via API...")
        timeline_data = self.client.fetch_timeline(self.channel_id)
        if timeline_data and 'items' in timeline_data:
            new_version = int(timeline_data.get('timeline_version', 0))
            current_id = daemon_state.get('current_timeline_item_id')
            old_current = next((item for item in self.timeline_queue if item.id == current_id), None)
            allowed_fields = set(TimelineItem.__dataclass_fields__)
            new_queue = [
                TimelineItem(**{key: value for key, value in item.items() if key in allowed_fields})
                for item in timeline_data['items']
            ]
            if new_version and new_version < int(daemon_state.get('timeline_version', 0)):
                self._log(f"[DAEMON] Ignoring stale timeline version {new_version}")
                return

            self.timeline_queue = new_queue
            self.pipeline_manager.timeline_queue = self.timeline_queue
            daemon_state['timeline_version'] = new_version

            current_index = next(
                (idx for idx, item in enumerate(self.timeline_queue) if item.id == current_id),
                None,
            )
            broadcast_clock = int(daemon_state.get('broadcast_clock_sec', 0))
            target_index = next(
                (
                    idx for idx, item in enumerate(self.timeline_queue)
                    if item.starts_at_sec <= broadcast_clock < item.ends_at_sec
                ),
                current_index if current_index is not None else 0,
            )

            target = self.timeline_queue[target_index]
            offset = max(0.0, broadcast_clock - target.starts_at_sec)
            seek = float(target.cue_in_sec or 0.0) + offset
            self._current_item_index = target_index
            self._item_started_at = time.time()
            self.pipeline_manager.play_item(target, seek_offset_sec=seek)
            daemon_state['item_index'] = target_index
            daemon_state['current_timeline_item_id'] = target.id
            daemon_state['content_position_sec'] = seek
            self._log(f"[DAEMON] Timeline reloaded: {len(self.timeline_queue)} items")

    def shutdown(self) -> None:
        """Graceful shutdown: stop pipeline, stop threads."""
        self._log("[DAEMON] Shutting down...")
        self._running = False
        self._stop_event.set()

        if self.pipeline_manager:
            self.pipeline_manager.shutdown()

        # Signal Laravel we're offline
        try:
            self.client.post_heartbeat(self.channel_id, {
                'status': 'offline',
                'broadcast_clock_sec': 0,
                'content_position_sec': 0,
                'item_index': 0,
                'loops': 0,
                'timestamp': datetime.utcnow().isoformat(),
            })
        except Exception:
            pass

    def _heartbeat_loop(self) -> None:
        """Background thread: send heartbeat to Laravel every 5s."""
        while not self._stop_event.is_set():
            try:
                current = self.timeline_queue[self._current_item_index] if self.timeline_queue else None
                self.client.post_heartbeat(self.channel_id, {
                    'status': self._status,
                    'broadcast_clock_sec': daemon_state.get('broadcast_clock_sec', 0),
                    'content_position_sec': daemon_state.get('content_position_sec', 0),
                    'current_timeline_item_id': daemon_state.get('current_timeline_item_id'),
                    'mode': 'interrupt' if current and current.is_cue else 'content',
                    'item_index': daemon_state.get('item_index', 0),
                    'loops': daemon_state.get('loops', 0),
                    'loops_completed': daemon_state.get('loops', 0),
                    'timeline_version': daemon_state.get('timeline_version', 0),
                    'pipeline_pid': os.getpid(),
                    'timestamp': datetime.utcnow().isoformat(),
                })
            except Exception as e:
                self._log(f"[DAEMON] Heartbeat error: {e}")
            self._stop_event.wait(5)

    def _stats_loop(self) -> None:
        """Background thread: log stream stats every 5s + watchdog + auto-advance."""
        last_frames = 0
        last_active = time.time()
        stuck_count = 0
        while not self._stop_event.is_set():
            self._stop_event.wait(5)
            if not self.pipeline_manager or not self._running:
                continue
            try:
                stats = self.pipeline_manager.get_stream_stats()
                uptime = int(time.time() - self.pipeline_manager.started_at)
                loops_done = daemon_state.get('loops', 0)
                item_idx = daemon_state.get('item_index', 0)
                item = self.timeline_queue[item_idx] if self.timeline_queue else None
                item_name = item.filename if item else 'none'
                current_frames = stats.get('frames_sent', 0)
                state = stats.get('pipeline_state', '?')

                # Auto-advance / loop when FFmpeg subprocess exits
                if state in ('stopped', 'error'):
                    if item:
                        # If the item has been playing long enough (effective_duration),
                        # advance to next. Otherwise loop the same item.
                        total_item_time = time.time() - self._item_started_at
                        effective = getattr(item, 'effective_duration_sec', 0) or 0
                        if total_item_time >= effective and effective > 0:
                            self._log(f"[DAEMON] Item finished (total_time={total_item_time:.1f}s >= duration={effective}s). Advancing...")
                            self.on_eos()
                        else:
                            self._log(f"[DAEMON] Item loop (total_time={total_item_time:.1f}s < duration={effective}s). Restarting same item...")
                            self.pipeline_manager.play_item(item, seek_offset_sec=item.cue_in_sec or 0.0)
                            self._item_started_at = time.time()
                            if stats.get('error_message'):
                                self._log(f"[DAEMON] Pipeline restart reason: {stats['error_message']}")
                    stuck_count = 0
                    last_active = time.time()
                    last_frames = 0
                    continue

                # Watchdog: detect stuck pipeline
                if state != 'playing' or current_frames == last_frames:
                    stuck_count += 1
                    if stuck_count >= 3:  # 15s without progress
                        self._log(f"[WATCHDOG] Pipeline stuck! state={state} frames={current_frames} (was {last_frames}). Restarting pipeline...")
                        try:
                            self.pipeline_manager.stop()
                            first_item = self.timeline_queue[0]
                            self.pipeline_manager.play_item(first_item, seek_offset_sec=first_item.cue_in_sec or 0.0)
                            self._current_item_index = 0
                            daemon_state['item_index'] = 0
                            daemon_state['current_timeline_item_id'] = first_item.id
                            stuck_count = 0
                            last_active = time.time()
                            self._log("[WATCHDOG] Pipeline restarted successfully")
                        except Exception as e:
                            self._log(f"[WATCHDOG] Restart failed: {e}")
                            # Signal error to trigger supervisor restart
                            self._status = 'error'
                            daemon_state['status'] = 'error'
                            daemon_state['error_message'] = f'Pipeline stuck and restart failed: {e}'
                            self._stop_event.set()
                else:
                    stuck_count = 0
                    last_active = time.time()

                last_frames = current_frames

                # Format: [STATS] uptime=120s loops=3 bitrate=2500kbps fps=30.0 frames_sent=3600 item=Inicio.mp4
                self._log(
                    f"[STATS] uptime={uptime}s loops={loops_done} "
                    f"bitrate={stats.get('video_bitrate', 0)}kbps "
                    f"fps={stats.get('fps', 0):.1f} "
                    f"frames_sent={current_frames} "
                    f"item={item_name} "
                    f"state={state}"
                )
            except Exception as e:
                self._log(f"[STATS] Error: {e}")

    def _update_state(self) -> None:
        """Sync pipeline position into daemon_state."""
        if not self.pipeline_manager:
            return
        pos_sec = self.pipeline_manager.get_position_sec()
        now = datetime.now()
        daemon_state['broadcast_clock_sec'] = now.hour * 3600 + now.minute * 60 + now.second
        daemon_state['content_position_sec'] = pos_sec
        if self.timeline_queue:
            current = self.timeline_queue[self._current_item_index]
            daemon_state['item_index'] = self._current_item_index
            daemon_state['current_timeline_item_id'] = current.id

    def _sync_playhead(self) -> None:
        """Resolve the timeline item for the current broadcast clock and switch
        when a boundary is crossed (e.g. content -> cue -> content tail)."""
        if not self.timeline_queue or not self.pipeline_manager:
            return

        now = datetime.now()
        broadcast_clock = now.hour * 3600 + now.minute * 60 + now.second
        daemon_state['broadcast_clock_sec'] = broadcast_clock

        target_index = next(
            (
                idx for idx, item in enumerate(self.timeline_queue)
                if item.starts_at_sec <= broadcast_clock < item.ends_at_sec
            ),
            None,
        )
        if target_index is None:
            return

        current = self.timeline_queue[self._current_item_index]
        target = self.timeline_queue[target_index]
        if target.id == current.id and target_index == self._current_item_index:
            return

        offset = max(0.0, broadcast_clock - target.starts_at_sec)
        seek = float(target.cue_in_sec or 0.0) + offset
        self._log(
            f"[PLAYHEAD] {broadcast_clock}s -> item {target_index} ({target.kind}): "
            f"{target.filename} seek={seek:.1f}s"
        )
        self._current_item_index = target_index
        self._item_started_at = time.time()
        self.pipeline_manager.play_item(target, seek_offset_sec=seek)
        daemon_state['item_index'] = target_index
        daemon_state['current_timeline_item_id'] = target.id
        daemon_state['content_position_sec'] = seek

    def on_eos(self) -> None:
        """Called when current item reaches EOS → advance to next or loop."""
        if not self.timeline_queue:
            return

        self._current_item_index += 1
        if self._current_item_index >= len(self.timeline_queue):
            self._current_item_index = 0
            daemon_state['loops'] = daemon_state.get('loops', 0) + 1
            self._log(f"[DAEMON] Loop #{daemon_state['loops']} completed")

        next_item = self.timeline_queue[self._current_item_index]
        self._log(f"[DAEMON] Advancing to item {self._current_item_index}: {next_item.filename}")

        daemon_state['item_index'] = self._current_item_index
        daemon_state['current_timeline_item_id'] = next_item.id
        daemon_state['content_position_sec'] = 0

        # Restart FFmpeg with next item and reset item timer
        self._item_started_at = time.time()
        self.pipeline_manager.play_item(next_item, seek_offset_sec=next_item.cue_in_sec or 0.0)

    def on_error(self, message: str) -> None:
        """Called on pipeline error → report and stop."""
        self._log(f"[DAEMON] Pipeline error: {message}")
        self._status = 'error'
        self._error_message = message
        daemon_state['status'] = 'error'
        daemon_state['error_message'] = message

        # Try to report to Laravel
        try:
            self.client.post_heartbeat(self.channel_id, {
                'status': 'error',
                'error_message': message,
                'timestamp': datetime.utcnow().isoformat(),
            })
        except Exception:
            pass

        self._stop_event.set()


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--channel-id', required=True)
    parser.add_argument('--port', type=int, default=0)
    args = parser.parse_args()

    daemon = ChannelDaemon(args.channel_id, port=args.port)
    daemon.start()
