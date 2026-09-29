import os
import re
import select
import signal
import subprocess
import time
from typing import Any, Dict, Optional

from models.timeline_item import TimelineItem
from models.virtual_screen import VirtualScreen


# Patterns that ffmpeg writes to stderr every ~1s when `-loglevel info` is set.
# Tolerates whitespace and pipe separators across ffmpeg versions.
_FRAME_RE = re.compile(r'frame=\s*(\d+)')
_FPS_RE = re.compile(r'fps=\s*(\d+(?:\.\d+)?)')
_BITRATE_RE = re.compile(r'bitrate=\s*(\d+(?:\.\d+)?)k?bits/s')

# Patterns that indicate the RTMP output socket has been closed by the remote.
# Captured from real ffmpeg stderr: "RTMP_ReadPacket: failed to read RTMP packet",
# "Connection reset by peer", "av_interleaved_write_frame: Connection reset".
_RTMP_FAILURE_RE = re.compile(
    r'(RTMP_ReadPacket|failed to read RTMP|Connection reset by peer|av_interleaved_write_frame: Connection reset|IO error on send buffer)',
    re.IGNORECASE,
)


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
        # Stats parsed from ffmpeg's stderr. Updated by `drain_stderr`.
        self._frames_actual: int = 0
        self._fps_actual: float = 0.0
        self._bitrate_actual_kbps: int = 0
        self._stderr_tail: str = ''  # last ~64 KB of stderr for failure detection
        self._rtmp_failure_seen: bool = False
        # Cached raw stderr lines so get_stream_stats() can match failure
        # patterns even if drain_stderr was called by something else first.
        self._stderr_window: str = ''

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
        # Reset stderr-derived stats so a fresh child doesn't inherit
        # the previous child's frame count.
        self._frames_actual = 0
        self._fps_actual = 0.0
        self._bitrate_actual_kbps = 0
        self._stderr_tail = ''
        self._stderr_window = ''
        self._rtmp_failure_seen = False

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
            # When the daemon has actually drained stderr and parsed a frame=
            # line, it sets `frames_actual`. Falls back to the wall-clock
            # estimate only if no stderr has arrived yet (first ~1 s of life).
            'video_bitrate_actual': self._bitrate_actual_kbps,
            'fps_actual': self._fps_actual,
            'frames_actual': self._frames_actual,
            'rtmp_failure_seen': self._rtmp_failure_seen,
        }
        if not self.proc:
            return stats

        returncode = self.proc.poll()
        if returncode is None:
            stats['pipeline_state'] = 'playing'
            if self._frames_actual > 0:
                stats['frames_sent'] = self._frames_actual
            else:
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
        rotated log. Also parses `frame=`, `fps=`, `bitrate=` from the most
        recent lines and stores them for `get_stream_stats()`.

        Returns the decoded chunk (may be empty).
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
        decoded = data.decode(errors='replace')

        if decoded:
            self._stderr_tail = (self._stderr_tail + decoded)[-max_bytes:]
            self._stderr_window = self._stderr_tail
            self._parse_stderr_stats(self._stderr_tail)
            if _RTMP_FAILURE_RE.search(self._stderr_tail):
                self._rtmp_failure_seen = True

        return decoded

    def _parse_stderr_stats(self, chunk: str) -> None:
        """Pull the latest `frame=`, `fps=`, `bitrate=` from the stderr tail.

        Uses `findall` on each line so we get the most recent match; ffmpeg
        prints a fresh progress line about once per second.
        """
        if not chunk:
            return
        # ffmpeg prints progress lines like:
        # frame= 1234 fps= 30 q=-1.0 size= 1234kB time=00:01:23.45 bitrate=2500.5kbits/s
        # The block ends with `progress=continue` (or empty line in old versions).
        # We grab the LAST match per pattern to avoid stale values.
        try:
            frames = _FRAME_RE.findall(chunk)
            if frames:
                self._frames_actual = int(frames[-1])
            fps = _FPS_RE.findall(chunk)
            if fps:
                self._fps_actual = float(fps[-1])
            brs = _BITRATE_RE.findall(chunk)
            if brs:
                self._bitrate_actual_kbps = int(round(float(brs[-1])))
        except Exception:
            # Regex failure shouldn't crash the supervisor. Worst case: the
            # next sample will re-parse and (hopefully) succeed.
            pass


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

        # MINIMO FIX Redplanet_TV (2026-09-17): cuando el stream key es
        # Redplanet_TV (un solo item en loop de 120s), usar -stream_loop -1
        # para que ffmpeg loope el input infinitamente sin desconectarse.
        # Esto elimina el gap de ~5s cada 2 min que se producia al matar y
        # respawnear ffmpeg en cada ciclo. Para otros canales el
        # comportamiento se mantiene intacto (-t duration).
        is_redplanet = 'Redplanet_TV' in (vs.output_url or '')

        if is_redplanet:
            args.extend(['-stream_loop', '-1'])
            # En Redplanet forzamos seek=0 para evitar -ss past-EOF que rompe
            # el loop. El item dura 120s y al hacer loop se reinicia solo.
            seek_offset_sec = 0

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
                if vs.codec_video == 'libx264':
                    args.extend([
                        '-tune', 'zerolatency',
                        '-threads', '4',
                        '-x264-params', 'bframes=0:refs=1:rc-lookahead=0:sync-lookahead=0:keyint=60:min-keyint=60:scenecut=0:sliced-threads=0',
                    ])
            elif vs.codec_video == 'libopenh264':
                args.extend([
                    '-threads', '4',
                    '-coder', 'cavlc',
                    '-loopfilter', '0',
                    '-slice_mode', 'fixed',
                    '-slices', '1',
                ])
            args.extend(['-b:v', f"{vs.video_bitrate_kbps}k", '-r', str(vs.fps)])

        # Audio codec
        if vs.codec_audio == 'copy':
            args.extend(['-c:a', 'copy'])
        else:
            args.extend(['-c:a', vs.codec_audio, '-ar', '44100', '-b:a', f"{vs.audio_bitrate_kbps}k"])

        if duration_limit is not None and not is_redplanet:
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
