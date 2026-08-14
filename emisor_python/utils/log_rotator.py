import os
import time
from collections import deque
from typing import Optional


class LogRotator:
    """
    Write daemon logs with auto-rotation:
    - max_size: 1MB (1_048_576 bytes)
    - max_lines: 500
    - When limit reached, keeps only last half (250 lines)
    """

    def __init__(self, path: str, max_size: int = 1_048_576, max_lines: int = 500):
        self.path = path
        self.max_size = max_size
        self.max_lines = max_lines
        self._buffer: deque[str] = deque(maxlen=max_lines)
        self._last_flush = 0.0
        self._dirty = False

    def write(self, message: str) -> None:
        """Queue a log line (timestamped)."""
        ts = time.strftime('%Y-%m-%d %H:%M:%S', time.localtime())
        line = f"[{ts}] {message}\n"
        self._buffer.append(line)
        self._dirty = True

        # Flush every 2 seconds or if buffer is near full
        now = time.time()
        if now - self._last_flush > 2.0 or len(self._buffer) >= self.max_lines - 10:
            self.flush()

    def flush(self) -> None:
        """Write buffer to disk, rotate if oversized."""
        if not self._dirty:
            return
        self._dirty = False
        self._last_flush = time.time()

        # Ensure directory exists
        os.makedirs(os.path.dirname(self.path), exist_ok=True)

        # Read existing content
        existing = ''
        if os.path.exists(self.path):
            try:
                with open(self.path, 'r', encoding='utf-8') as f:
                    existing = f.read()
            except Exception:
                existing = ''

        # Combine with buffer
        new_content = existing + ''.join(self._buffer)

        # Rotate if oversized
        lines = new_content.splitlines(keepends=True)
        if len(lines) > self.max_lines or len(new_content.encode('utf-8')) > self.max_size:
            # Keep last half
            keep = self.max_lines // 2
            lines = lines[-keep:]
            new_content = ''.join(lines)

        # Write atomically via temp file
        tmp = self.path + '.tmp'
        with open(tmp, 'w', encoding='utf-8') as f:
            f.write(new_content)
        os.replace(tmp, self.path)

    def read_tail(self, n: int = 100) -> str:
        """Read last N lines from disk (for API)."""
        if not os.path.exists(self.path):
            return ''
        try:
            with open(self.path, 'r', encoding='utf-8') as f:
                lines = f.readlines()
            return ''.join(lines[-n:])
        except Exception:
            return ''
