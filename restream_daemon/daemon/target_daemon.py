import json
import os
import signal
import sys
import threading
import time
from datetime import datetime
from typing import Any, Dict, Optional

PROJECT_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, PROJECT_ROOT)
sys.path.insert(0, os.path.join(PROJECT_ROOT, 'emisor_python'))

from pipeline.ffmpeg_pipeline import FFmpegProcess
from utils.laravel_client import LaravelClient

CONFIG_DIR = os.path.join(PROJECT_ROOT, 'storage', 'app', 'restream-daemons')


class TargetDaemon:
    """Daemon that restreams one target: source URL -> destination RTMP.

    Follows the emission daemon standard: heartbeat every 5s, [STATS] every
    5s, watchdog, rotated log, registration file, clean shutdown.
    """

    def __init__(self, target_id: str, dry_run: bool = False):
        self.target_id = target_id
        self.dry_run = dry_run
        self.client = LaravelClient()
        self.pipeline: Optional[FFmpegProcess] = None
        self._running = False
        self._stop_event = threading.Event()
        self._heartbeat_thread: Optional[threading.Thread] = None
        self._stats_thread: Optional[threading.Thread] = None
        self._status = 'starting'
        self._error_message: Optional[str] = None
        self._started_at: Optional[datetime] = None
        self._config_path: Optional[str] = None
        self._config: Dict[str, Any] = {}
        self._log_rotator = None  # Injected from main.py

    def _log(self, message: str) -> None:
        """Write to log rotator if available, otherwise print."""
        if self._log_rotator:
            self._log_rotator.write(message)
        else:
            print(message)

    def _load_config(self) -> Dict[str, Any]:
        """Read the config JSON written by Laravel, then delete it so the
        decrypted stream key does not persist on disk."""
        self._config_path = os.path.join(CONFIG_DIR, f"{self.target_id}-config.json")
        if not os.path.exists(self._config_path):
            raise RuntimeError(f"Config file not found: {self._config_path}")

        with open(self._config_path, 'r') as f:
            config = json.load(f)

        # The stream key is sensitive: remove the file right after reading.
        try:
            os.unlink(self._config_path)
        except OSError:
            pass

        return config

    def _build_ffmpeg_args(self) -> list:
        """Build the ffmpeg command for this target.

        Uses the corrected `-rtmp_live live` flag (ffmpeg 4.4 rejects
        `live=1` with "Invalid argument").
        """
        cfg = self._config
        source = cfg.get('source_url')
        if not source:
            raise RuntimeError("No source_url in config")

        destination = cfg.get('destination_url', '').rstrip('/')
        stream_key = cfg.get('stream_key', '')
        if not destination or not stream_key:
            raise RuntimeError("destination_url or stream_key missing in config")

        ffmpeg_bin = cfg.get('ffmpeg_bin', 'ffmpeg')

        return [
            ffmpeg_bin,
            '-hide_banner',
            '-loglevel', 'info',
            '-re',
            '-i', source,
            '-c:v', 'copy',
            '-c:a', 'aac',
            '-ar', '44100',
            '-ac', '2',
            '-b:a', '128k',
            '-f', 'flv',
            '-rtmp_live', 'live',
            f"{destination}/{stream_key}",
        ]

    def _redact_command(self, args: list) -> str:
        """Render the command for logs with the stream key redacted."""
        cfg = self._config
        key = cfg.get('stream_key', '')
        redacted = '***' + key[-4:] if key else '***'
        rendered = []
        for a in args:
            if key and key in a:
                rendered.append(a.replace(key, redacted))
            else:
                rendered.append(a)
        return ' '.join(rendered)

    def start(self) -> None:
        """Start the daemon: read config, spawn ffmpeg, start loops."""
        self._log(f"[DAEMON] Starting restream for target {self.target_id}")

        self._config = self._load_config()

        args = self._build_ffmpeg_args()
        self._log(f"[DAEMON] Command: {self._redact_command(args)}")

        if self.dry_run:
            self._log("[DAEMON] Dry run: not spawning ffmpeg.")
            self._log("=" * 50)
            self._log("DRY RUN COMMAND (stream key redacted):")
            self._log(self._redact_command(args))
            self._log("=" * 50)
            return

        # Write registration so Laravel can discover us
        self.client.write_restream_registration(self.target_id, {
            'pid': os.getpid(),
            'started_at': datetime.utcnow().isoformat(),
        })

        # Spawn the ffmpeg child
        self.pipeline = FFmpegProcess(fps=30.0, video_bitrate_kbps=0)
        self.pipeline.spawn(args)
        if not self.pipeline.is_running():
            raise RuntimeError("FFmpeg failed to spawn")

        self._started_at = datetime.utcnow()
        self._status = 'live'
        self._log(f"[DAEMON] FFmpeg child started (pid={self.pipeline.proc.pid})")

        self._running = True

        # Start heartbeat thread
        self._heartbeat_thread = threading.Thread(target=self._heartbeat_loop, daemon=True)
        self._heartbeat_thread.start()

        # Start stats thread (logs stream metrics every 5s + watchdog)
        self._stats_thread = threading.Thread(target=self._stats_loop, daemon=True)
        self._stats_thread.start()

        # Main loop: wait for stop signal
        try:
            self._log("[DAEMON] Entering main loop...")
            while self._running and not self._stop_event.is_set():
                self._stop_event.wait(1)
        except KeyboardInterrupt:
            self._log("[DAEMON] Interrupted")
        finally:
            self.shutdown()

    def _heartbeat_loop(self) -> None:
        """Background thread: send heartbeat to Laravel every 5s."""
        while not self._stop_event.is_set():
            try:
                pid = self.pipeline.proc.pid if self.pipeline and self.pipeline.proc else None
                self.client.post_restream_heartbeat(self.target_id, {
                    'status': self._status,
                    'pipeline_pid': pid,
                    'error_message': self._error_message,
                    'timestamp': datetime.utcnow().isoformat(),
                })
            except Exception as e:
                self._log(f"[DAEMON] Heartbeat error: {e}")
            self._stop_event.wait(5)

    def _stats_loop(self) -> None:
        """Background thread: log stream stats every 5s + watchdog."""
        last_frames = 0
        stuck_count = 0
        zero_bitrate_since = None
        dead_output_reported = False
        while not self._stop_event.is_set():
            self._stop_event.wait(5)
            if not self.pipeline or not self._running:
                continue
            try:
                # Drain stderr and parse real frame/fps/bitrate from ffmpeg's
                # output. This replaces the previous wall-clock estimate that
                # let dead RTMP sockets slip past the watchdog.
                stderr_chunk = self.pipeline.drain_stderr()
                stats = self.pipeline.get_stream_stats()
                uptime = int(time.time() - self.pipeline.started_at)
                current_frames = stats.get('frames_sent', 0)
                state = stats.get('pipeline_state', '?')
                bitrate_actual = stats.get('video_bitrate_actual', 0)
                rtmp_failure_seen = stats.get('rtmp_failure_seen', False)

                # Auto-restart when the ffmpeg child exits
                if state in ('stopped', 'error'):
                    # Don't respawn during shutdown. The orchestrator just
                    # sent SIGTERM (or the operator hit "Detener"); spawning
                    # a fresh ffmpeg here would leave an orphan that keeps
                    # pushing to YouTube after the daemon itself exits.
                    if self._stop_event.is_set():
                        self._log(f"[DAEMON] FFmpeg child exited during shutdown (state={state}); not respawning.")
                        return
                    self._log(f"[DAEMON] FFmpeg child exited (state={state}). Restarting...")
                    if stats.get('error_message'):
                        self._log(f"[DAEMON] Child error: {stats['error_message']}")
                    self._restart_child()
                    stuck_count = 0
                    last_frames = 0
                    zero_bitrate_since = None
                    dead_output_reported = False
                    continue

                # Dead-output watchdog: YouTube (or any RTMP server) closes
                # the connection without killing ffmpeg. ffmpeg keeps reading
                # source bytes but writes into a dead socket. The next ffmpeg
                # progress line shows bitrate=0 AND a few seconds later the
                # stderr includes RTMP_ReadPacket / Connection reset lines.
                # Detect that combo and restart the child.
                if bitrate_actual == 0 and rtmp_failure_seen:
                    if zero_bitrate_since is None:
                        zero_bitrate_since = time.time()
                    elif not dead_output_reported and (time.time() - zero_bitrate_since) >= 10:
                        self._log("[WATCHDOG] RTMP output socket closed, restarting child…")
                        self._status = 'error'
                        self._error_message = 'rtmp-output-closed'
                        self._post_stall_heartbeat()
                        self._restart_child()
                        zero_bitrate_since = None
                        dead_output_reported = False
                        stuck_count = 0
                        last_frames = 0
                        continue
                else:
                    zero_bitrate_since = None
                    if dead_output_reported and bitrate_actual > 0:
                        # New child is healthy — clear the stall signal.
                        self._post_stall_cleared_heartbeat()
                        dead_output_reported = False

                # Watchdog: detect stuck pipeline (no frame progress)
                if state != 'playing' or current_frames == last_frames:
                    stuck_count += 1
                    if stuck_count >= 3:  # 15s without progress
                        self._log(f"[WATCHDOG] Pipeline stuck! state={state} frames={current_frames} (was {last_frames}). Restarting pipeline...")
                        self._restart_child()
                        stuck_count = 0
                        last_frames = 0
                        zero_bitrate_since = None
                        continue
                else:
                    stuck_count = 0

                last_frames = current_frames

                # Format: [STATS] uptime=120s bitrate=2500kbps fps=30.0 frames_sent=3600 state=playing
                self._log(
                    f"[STATS] uptime={uptime}s "
                    f"bitrate={stats.get('video_bitrate_actual', 0)}kbps "
                    f"fps={stats.get('fps_actual', 0):.1f} "
                    f"frames_sent={current_frames} "
                    f"state={state} "
                    f"rtmp_fail={int(bool(rtmp_failure_seen))}"
                )
            except Exception as e:
                self._log(f"[STATS] Error: {e}")

    def _post_stall_heartbeat(self) -> None:
        """Send a heartbeat carrying daemon_stalled_at = now()."""
        try:
            self.client.post_restream_heartbeat(self.target_id, {
                'status': self._status,
                'pipeline_pid': self.pipeline.proc.pid if self.pipeline and self.pipeline.proc else None,
                'error_message': self._error_message,
                'daemon_stalled_at': datetime.utcnow().isoformat(),
                'timestamp': datetime.utcnow().isoformat(),
            })
        except Exception as e:
            self._log(f"[DAEMON] stall heartbeat error: {e}")

    def _post_stall_cleared_heartbeat(self) -> None:
        """Send a heartbeat carrying daemon_stalled_at = null (recovery)."""
        try:
            self.client.post_restream_heartbeat(self.target_id, {
                'status': self._status,
                'pipeline_pid': self.pipeline.proc.pid if self.pipeline and self.pipeline.proc else None,
                'error_message': None,
                'daemon_stalled_at': None,
                'timestamp': datetime.utcnow().isoformat(),
            })
        except Exception as e:
            self._log(f"[DAEMON] stall-cleared heartbeat error: {e}")

    def _restart_child(self) -> None:
        """Restart the ffmpeg child with the same config."""
        try:
            args = self._build_ffmpeg_args()
            if self.pipeline:
                self.pipeline.spawn(args)
            else:
                self.pipeline = FFmpegProcess(fps=30.0, video_bitrate_kbps=0)
                self.pipeline.spawn(args)
            if self.pipeline.is_running():
                self._status = 'live'
                self._error_message = None
                self._log(f"[DAEMON] FFmpeg child restarted (pid={self.pipeline.proc.pid})")
            else:
                raise RuntimeError("FFmpeg failed to spawn on restart")
        except Exception as e:
            self._log(f"[DAEMON] Restart failed: {e}")
            self._status = 'error'
            self._error_message = str(e)

    def shutdown(self) -> None:
        """Graceful shutdown: stop pipeline, report offline, clean config."""
        self._log("[DAEMON] Shutting down...")
        self._running = False
        self._stop_event.set()

        if self.pipeline:
            self.pipeline.shutdown()

        # Signal Laravel we're offline
        try:
            self.client.post_restream_heartbeat(self.target_id, {
                'status': 'offline',
                'pipeline_pid': None,
                'error_message': None,
                'timestamp': datetime.utcnow().isoformat(),
            })
        except Exception:
            pass

        # Remove the config file if it still exists (defense in depth)
        if self._config_path and os.path.exists(self._config_path):
            try:
                os.unlink(self._config_path)
            except OSError:
                pass


def _handle_signal(signum, frame):
    """Signal handler: request graceful shutdown."""
    daemon = _signal_ctx.get('daemon')
    if daemon:
        daemon._log(f"[DAEMON] Received signal {signum}, shutting down")
        daemon._stop_event.set()


_signal_ctx: Dict[str, Any] = {}


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--target-id', required=True)
    parser.add_argument('--dry-run', action='store_true')
    args = parser.parse_args()

    daemon = TargetDaemon(args.target_id, dry_run=args.dry_run)
    _signal_ctx['daemon'] = daemon
    signal.signal(signal.SIGTERM, _handle_signal)
    signal.signal(signal.SIGINT, _handle_signal)
    daemon.start()
