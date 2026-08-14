#!/usr/bin/env python3
"""
Cloudstream Emission Daemon — Entry point with supervisor loop and log rotation.

Usage:
    python main.py --channel-id=<uuid> [--port=0]

Environment:
    Requires GStreamer 1.20+, PyGObject, FastAPI, uvicorn, requests.

Supervisor behavior:
    - If the daemon crashes, it restarts automatically (max 5 restarts in 60s)
    - After max restarts, it enters cooldown and reports error to Laravel

Log behavior:
    - Logs rotated automatically (max 1MB, 500 lines, keeps last 250)
    - Stats written every 5s: bitrate, fps, frames sent, uptime
"""

import argparse
import sys
import os
import time
import traceback
from io import TextIOWrapper

# Add project root to path for imports
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from daemon.channel_daemon import ChannelDaemon
from utils.laravel_client import LaravelClient
from utils.log_rotator import LogRotator


class StderrRedirector(TextIOWrapper):
    """Redirects stderr to LogRotator while preserving original stderr for fatal errors."""

    def __init__(self, rotator: LogRotator):
        self.rotator = rotator
        self._original_stderr = sys.stderr

    def write(self, message: str) -> int:
        if message.strip():
            self.rotator.write(message.rstrip())
        return len(message)

    def flush(self) -> None:
        self.rotator.flush()

    def isatty(self) -> bool:
        return False


def main():
    parser = argparse.ArgumentParser(description="Cloudstream Emission Daemon")
    parser.add_argument("--channel-id", required=True, help="Channel UUID")
    parser.add_argument("--port", type=int, default=0, help="API server port (0=auto)")
    args = parser.parse_args()

    # Setup log rotator
    log_dir = os.path.join(os.path.dirname(__file__), '..', 'storage', 'logs')
    log_path = os.path.join(log_dir, f"emission-{args.channel_id}.log")
    rotator = LogRotator(log_path, max_size=1_048_576, max_lines=500)

    # Redirect stderr to log rotator (so GStreamer/Python errors go to the log)
    redirector = StderrRedirector(rotator)
    sys.stderr = redirector

    rotator.write("=" * 50)
    rotator.write("Daemon starting with supervisor")
    rotator.write(f"Channel: {args.channel_id}")
    rotator.write(f"Log path: {log_path}")
    rotator.write("=" * 50)
    rotator.flush()

    max_restarts = 5
    restart_window = 60
    restart_count = 0
    last_restart = 0

    client = LaravelClient()

    while True:
        daemon = None
        try:
            rotator.write(f"[{time.strftime('%H:%M:%S')}] Initializing ChannelDaemon...")
            rotator.flush()

            daemon = ChannelDaemon(
                channel_id=args.channel_id,
                port=args.port,
            )
            daemon._log_rotator = rotator  # Inject so pipeline can write stats
            daemon.start()
            # If start() returns normally, the daemon shut down cleanly
            break

        except KeyboardInterrupt:
            rotator.write("Interrupted by user")
            if daemon:
                daemon.shutdown()
            break

        except Exception as e:
            rotator.write(f"Fatal error: {e}")
            rotator.write(traceback.format_exc())
            if daemon:
                try:
                    daemon.shutdown()
                except Exception as shutdown_err:
                    rotator.write(f"Shutdown error: {shutdown_err}")

            # Supervisor logic: restart with backoff
            now = time.time()
            if now - last_restart < restart_window:
                restart_count += 1
            else:
                restart_count = 1
            last_restart = now

            if restart_count > max_restarts:
                rotator.write(f"Too many restarts ({restart_count}) in {restart_window}s. Cooldown 30s.")
                rotator.flush()
                try:
                    client.post_heartbeat(args.channel_id, {
                        'status': 'error',
                        'error_message': f'Daemon crashed {restart_count} times in {restart_window}s.',
                        'timestamp': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
                    })
                except Exception:
                    pass
                time.sleep(30)
                restart_count = 0
            else:
                backoff = min(2 ** restart_count, 30)
                rotator.write(f"Restarting in {backoff}s... (attempt {restart_count}/{max_restarts})")
                rotator.flush()
                time.sleep(backoff)

    rotator.write("Daemon exited.")
    rotator.flush()
    print("Daemon exited.")


if __name__ == "__main__":
    main()
