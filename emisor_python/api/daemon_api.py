import socket
import threading
import time
from typing import Any, Dict

from fastapi import FastAPI
from fastapi.responses import JSONResponse

app = FastAPI(title="Cloudstream Emission Daemon API")

# Shared state reference, injected by ChannelDaemon
daemon_state: Dict[str, Any] = {}


@app.post("/stop")
def stop():
    """Signal the daemon to shut down gracefully."""
    daemon_state['should_stop'] = True
    return {"stopping": True}


@app.get("/status")
def status():
    """Return current daemon playback status."""
    return {
        "status": daemon_state.get("status", "unknown"),
        "broadcast_clock_sec": daemon_state.get("broadcast_clock_sec", 0),
        "current_timeline_item_id": daemon_state.get("current_timeline_item_id"),
        "item_index": daemon_state.get("item_index", 0),
        "loops": daemon_state.get("loops", 0),
        "timeline_version": daemon_state.get("timeline_version", 0),
    }


@app.post("/reload-timeline")
def reload_timeline():
    """Request the daemon to re-fetch the timeline."""
    daemon_state['reload_requested'] = True
    return {"reload_requested": True}


class DaemonApiServer:
    """FastAPI server that runs in a background thread."""

    def __init__(self, port: int = 0):
        self.port = port
        self.host = '127.0.0.1'
        self._thread: threading.Thread | None = None
        self._server = None

    def start(self) -> int:
        """Start the API server and return the bound port."""
        import uvicorn

        # If port is 0, bind a socket to get an ephemeral port first
        if self.port == 0:
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.bind((self.host, 0))
            self.port = sock.getsockname()[1]
            sock.close()

        config = uvicorn.Config(app, host=self.host, port=self.port, log_level="warning")
        server = uvicorn.Server(config)
        self._server = server

        def run():
            server.run()

        self._thread = threading.Thread(target=run, daemon=True)
        self._thread.start()

        # Wait for server to start accepting connections
        max_wait = 30
        elapsed = 0
        while not server.started and elapsed < max_wait:
            time.sleep(0.1)
            elapsed += 0.1

        if server.started:
            return self.port
        raise RuntimeError("API server failed to start")

    def stop(self) -> None:
        """Stop the API server."""
        if self._server:
            self._server.should_exit = True
