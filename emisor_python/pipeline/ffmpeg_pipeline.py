import os
import select
import signal
import subprocess
import time
from typing import Any, Dict, Optional

from models.timeline_item import TimelineItem
from models.virtual_screen import VirtualScreen


class FFmpegProcess:
    """Generic FFmpeg subprocess management shared by all emitters.

    Handles spawn, process-group kill, stats approximation and stderr
    draining without knowing anything about timelines or virtual screens.
    The emission pipeline manager and the restream daemon both build on it.
    """

    def __init__(self, fps: float = 30.0, video_bitrate_kbps: int = 0):
        self.proc: Optional[subprocess.Popen] = None
        self.started_at: float = 0.0
        self.last_error: str = ''
        self._fps = fps
        self._video_bitrate_kbps = video_bitrate_kbps

    def spawn(self, args: list, cwd: Optional[str] = None, env: Optional[Dict[str, str]] = None) -> None:
        """Start a new ffmpeg subprocess, killing any previous one first."""
        self._stop_current()
        self.last_error = ''
        self.proc = subprocess.Popen(
            args,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.PIPE,
            stdin=subprocess.DEVNULL,
            start_new_session=True,  # So we can kill the whole process group
            cwd=cwd,
            env=env,
        )
        self.started_at = time.time()

    def is_running(self) -> bool:
        return self.proc is not None and self.proc.poll() is None

    def _stop_current(self) -> None:
        if self.proc and self.proc.poll() is None:
            try:
                # Kill entire process group to avoid zombies
                os.killpg(os.getpgid(self.proc.pid), signal.SIGTERM)
                self.proc.wait(timeout=3)
            except Exception:
                try:
                    os.killpg(os.getpgid(self.proc.pid), signal.SIGKILL)
                    self.proc.wait(timeout=2)
                except Exception:
                    pass
        # Reap any lingering zombies from previous processes
        try:
            while True:
                pid, _ = os.waitpid(-1, os.WNOHANG)
                if pid == 0:
                    break
        except ChildProcessError:
            pass
        self.proc = None

    def stop(self) -> None:
        self._stop_current()

    def shutdown(self) -> None:
        self.stop()

    def get_stream_stats(self) -> Dict[str, Any]:
        stats = {
            'video_bitrate': self._video_bitrate_kbps,
            'fps': float(self._fps),
            'frames_sent': 0,
            'pipeline_state': 'null',
        }
        if not self.proc:
            return stats

        returncode = self.proc.poll()
        if returncode is None:
            stats['pipeline_state'] = 'playing'
            # Approximate frames from elapsed time
            elapsed = time.time() - self.started_at
            stats['frames_sent'] = int(elapsed * self._fps)
        else:
            stats['pipeline_state'] = 'stopped' if returncode == 0 else 'error'
            if self.proc.stderr:
                try:
                    self.last_error = self.proc.stderr.read().decode(errors='replace').strip()
                except Exception:
                    self.last_error = ''
            if self.last_error:
                stats['error_message'] = self.last_error[-1000:]

        return stats

    def drain_stderr(self, max_bytes: int = 65536) -> str:
        """Non-blocking read of whatever stderr has produced so far.

        Used by the restream daemon to forward ffmpeg stderr lines into the
        rotated log. Returns the decoded chunk (may be empty).
        """
        if not self.proc or not self.proc.stderr:
            return ''
        data = b''
        try:
            while True:
                r, _, _ = select.select([self.proc.stderr], [], [], 0)
                if not r:
                    break
                chunk = self.proc.stderr.read(4096)
                if not chunk:
                    break
                data += chunk
                if len(data) >= max_bytes:
                    break
        except Exception:
            pass
        return data.decode(errors='replace')


class FFmpegPipelineManager(FFmpegProcess):
    """Manages FFmpeg subprocess pipeline for a single channel (emission)."""

    def __init__(self, channel_id: str, virtual_screen: VirtualScreen, root_path: str = ""):
        super().__init__(
            fps=float(virtual_screen.fps) if virtual_screen else 30.0,
            video_bitrate_kbps=virtual_screen.video_bitrate_kbps if virtual_screen else 0,
        )
        self.channel_id = channel_id
        self.virtual_screen = virtual_screen
        self.root_path = root_path
        self.current_item: Optional[TimelineItem] = None
        self.item_index = 0
        self.timeline_queue: list[TimelineItem] = []
        self.loops_completed = 0
        self._restarting = False
        self.eos_count = 0

    def _resolve_file_path(self, item: TimelineItem) -> str:
        if item.filename and self.root_path:
            path = os.path.join(self.root_path, item.filename)
            if os.path.exists(path):
                return path
        raise FileNotFoundError(f"Media file not found for item {item.id}: {item.filename}")

    def _build_ffmpeg_args(self, item: TimelineItem, file_path: str, seek_offset_sec: Optional[float] = None) -> list[str]:
        vs = self.virtual_screen
        args = [
            'ffmpeg',
            '-hide_banner', '-loglevel', 'warning',
            '-re', '-fflags', '+genpts',
        ]

        if seek_offset_sec is not None and seek_offset_sec > 0:
            args.extend(['-ss', f"{float(seek_offset_sec):.3f}"])

        args.extend(['-i', file_path])

        duration_limit = None
        if item.duration_sec > 0:
            source_start = float(item.cue_in_sec or 0.0)
            source_seek = float(seek_offset_sec or source_start)
            elapsed_in_item = max(0.0, source_seek - source_start)
            duration_limit = max(0.1, float(item.duration_sec) - elapsed_in_item)

        # Determine if we need video processing (transcode + filters)
        video_copy = vs.codec_video == 'copy'

        # Logo overlay and scaling (only when NOT video copy)
        if not video_copy:
            if vs.logo_enabled:
                logo_path = self._resolve_logo_path()
                if logo_path and os.path.exists(logo_path):
                    args.extend(['-i', logo_path])
                    op = f"{vs.logo_opacity:.2f}"
                    filter_complex = (
                        f"[1:v]scale={vs.logo_w}:{vs.logo_h},format=rgba,"
                        f"colorchannelmixer=aa={op}[lg];"
                        f"[0:v:0]scale={vs.width}:{vs.height}:force_original_aspect_ratio=decrease,"
                        f"pad={vs.width}:{vs.height}:(ow-iw)/2:(oh-ih)/2[scaled];"
                        f"[scaled][lg]overlay={vs.logo_x}:{vs.logo_y}[outv]"
                    )
                    args.extend(['-filter_complex', filter_complex])
                    args.extend(['-map', '[outv]', '-map', '0:a?'])
                else:
                    # Logo configured but file missing; proceed without it
                    args.extend(['-map', '0:v:0', '-map', '0:a:0?'])
                    args.extend([
                        '-vf',
                        f"scale={vs.width}:{vs.height}:force_original_aspect_ratio=decrease,"
                        f"pad={vs.width}:{vs.height}:(ow-iw)/2:(oh-ih)/2"
                    ])
            else:
                args.extend(['-map', '0:v:0', '-map', '0:a:0?'])
                args.extend([
                    '-vf',
                    f"scale={vs.width}:{vs.height}:force_original_aspect_ratio=decrease,"
                    f"pad={vs.width}:{vs.height}:(ow-iw)/2:(oh-ih)/2"
                ])
        else:
            # Video copy: no filters allowed, map directly
            args.extend(['-map', '0:v:0', '-map', '0:a:0?'])

        # Video codec
        if video_copy:
            args.extend(['-c:v', 'copy'])
        else:
            args.extend(['-c:v', vs.codec_video])
            if vs.codec_video in ('libx264', 'libx265'):
                preset = vs.video_preset or 'veryfast'
                args.extend(['-preset', preset])
            args.extend(['-b:v', f"{vs.video_bitrate_kbps}k", '-r', str(vs.fps)])

        # Audio codec
        if vs.codec_audio == 'copy':
            args.extend(['-c:a', 'copy'])
        else:
            args.extend(['-c:a', vs.codec_audio, '-ar', '44100', '-b:a', f"{vs.audio_bitrate_kbps}k"])

        if duration_limit is not None:
            args.extend(['-t', f'{duration_limit:.3f}'])

        # Output format / URL
        if vs.output_protocol == 'rtmp' or vs.output_url.startswith('rtmp://'):
            args.extend(['-f', 'flv', vs.output_url])
        elif vs.output_url.startswith('srt://'):
            args.extend(['-f', 'mpegts', vs.output_url])
        else:
            # HLS fallback
            args.extend(['-f', 'hls', vs.output_url])

        return args

    def _resolve_logo_path(self) -> Optional[str]:
        """Resolve logo file path from logo_media_item_id heuristic."""
        vs = self.virtual_screen
        # Try common logo locations based on channel name / root_path
        candidates = []
        if self.root_path:
            candidates.extend([
                os.path.join(self.root_path, 'logo.png'),
                os.path.join(self.root_path, 'logo.jpg'),
            ])
        if vs.name:
            candidates.extend([
                f"/mnt/multimedia/{vs.name}/logo.png",
                f"/mnt/multimedia/{vs.name}/logo.jpg",
                f"/mnt/multimedia/{vs.name.replace(' ', '')}/logo.png",
                f"/mnt/multimedia/{vs.name.replace(' ', '')}/logo.jpg",
            ])
        for c in candidates:
            if os.path.exists(c):
                return c
        return None

    def play_item(self, item: TimelineItem, seek_offset_sec: Optional[float] = None) -> None:
        """Start FFmpeg playing a specific timeline item.

        If `seek_offset_sec` is provided, fast-seek the input file to that
        offset (in seconds) before starting playback. The seek is performed
        via `-ss` before `-i` which is a fast (keyframe-aligned) seek.
        """
        self.current_item = item
        file_path = self._resolve_file_path(item)
        args = self._build_ffmpeg_args(item, file_path, seek_offset_sec)
        self.spawn(args)

    @property
    def broadcast_clock_sec(self) -> int:
        if not self.started_at:
            return 0
        elapsed = int(time.time() - self.started_at)
        total = elapsed + (self.loops_completed * 86400)
        return total % 86400

    def get_position_sec(self) -> float:
        if not self.started_at:
            return 0.0
        return time.time() - self.started_at
