import json
import os
import time
from typing import Any, Dict, List, Optional

import requests

from models.timeline_item import TimelineItem
from models.virtual_screen import VirtualScreen


class LaravelClient:
    """Client for the Laravel internal API endpoints.
    
    On startup, reads config from file written by Laravel.
    Uses API only for heartbeat and reload operations.
    """

    def __init__(self, base_url: str = None):
        if base_url is None:
            base_url = os.environ.get('LARAVEL_API_URL', 'http://127.0.0.1:9090')
        self.base_url = base_url.rstrip('/')
        self.session = requests.Session()
        self.session.headers.update({
            'Accept': 'application/json',
            'User-Agent': 'Cloudstream-EmissionDaemon/1.0',
        })

    def read_config_file(self) -> Optional[Dict[str, Any]]:
        """Read the config file written by Laravel before spawn."""
        config_path = os.environ.get('DAEMON_CONFIG_PATH')
        if not config_path or not os.path.exists(config_path):
            return None
        try:
            with open(config_path, 'r') as f:
                return json.load(f)
        except (json.JSONDecodeError, IOError) as e:
            print(f"[LaravelClient] Failed to read config file: {e}")
            return None

    def fetch_timeline(self, channel_id: str) -> Optional[Dict[str, Any]]:
        """Fetch today's timeline for a channel via API."""
        url = f"{self.base_url}/api/internal/channels/{channel_id}/emission/timeline"
        try:
            resp = self.session.get(url, timeout=10)
            resp.raise_for_status()
            return resp.json()
        except requests.RequestException as e:
            print(f"[LaravelClient] fetch_timeline failed: {e}")
            return None

    def fetch_virtual_screen(self, channel_id: str) -> Optional[VirtualScreen]:
        """Fetch VirtualScreen config for a channel via API."""
        url = f"{self.base_url}/api/internal/channels/{channel_id}/emission/virtual-screen"
        try:
            resp = self.session.get(url, timeout=10)
            resp.raise_for_status()
            data = resp.json()
            return VirtualScreen(
                channel_id=channel_id,
                name=data.get('name', ''),
                width=data.get('width', 1280),
                height=data.get('height', 720),
                output_protocol=data.get('output_protocol', 'rtmp'),
                output_url=data.get('output_url', ''),
                fps=data.get('fps', 30),
                video_bitrate_kbps=data.get('video_bitrate_kbps', 1000),
                audio_bitrate_kbps=data.get('audio_bitrate_kbps', 128),
                codec_video=data.get('codec_video', 'libx264'),
                codec_audio=data.get('codec_audio', 'aac'),
                logo_media_item_id=data.get('logo_media_item_id'),
                logo_x=data.get('logo_x', 0),
                logo_y=data.get('logo_y', 0),
                logo_w=data.get('logo_w', 0),
                logo_h=data.get('logo_h', 0),
                logo_opacity=data.get('logo_opacity', 1.0),
                fallback_type=data.get('fallback_type', 'black'),
                fallback_media_item_id=data.get('fallback_media_item_id'),
            )
        except requests.RequestException as e:
            print(f"[LaravelClient] fetch_virtual_screen failed: {e}")
            return None

    def fetch_channel(self, channel_id: str) -> Optional[Dict[str, Any]]:
        """Fetch channel info including root_path via API."""
        url = f"{self.base_url}/api/internal/channels/{channel_id}/emission/channel"
        try:
            resp = self.session.get(url, timeout=10)
            resp.raise_for_status()
            return resp.json()
        except requests.RequestException as e:
            print(f"[LaravelClient] fetch_channel failed: {e}")
            return None

    def fetch_position(self, channel_id: str, broadcast_clock_sec: int) -> Optional[Dict[str, Any]]:
        """Query exact playhead position after crash."""
        url = f"{self.base_url}/api/internal/channels/{channel_id}/emission/position"
        try:
            resp = self.session.get(url, params={'broadcast_clock_sec': broadcast_clock_sec}, timeout=10)
            resp.raise_for_status()
            return resp.json()
        except requests.RequestException as e:
            print(f"[LaravelClient] fetch_position failed: {e}")
            return None

    def post_heartbeat(self, channel_id: str, payload: Dict[str, Any]) -> bool:
        """Report daemon state to Laravel."""
        url = f"{self.base_url}/api/internal/channels/{channel_id}/emission/heartbeat"
        try:
            resp = self.session.post(url, json=payload, timeout=10)
            resp.raise_for_status()
            return True
        except requests.RequestException as e:
            print(f"[LaravelClient] heartbeat failed: {e}")
            return False

    def write_registration(self, channel_id: str, data: Dict[str, Any]) -> None:
        """Write PID/port registration to storage directory."""
        reg_dir = os.path.join(os.path.dirname(__file__), '..', '..', 'storage', 'app', 'emission-daemons')
        os.makedirs(reg_dir, exist_ok=True)
        path = os.path.join(reg_dir, f"{channel_id}.json")
        with open(path, 'w') as f:
            json.dump(data, f, indent=2)

    def read_registration(self, channel_id: str) -> Optional[Dict[str, Any]]:
        """Read PID/port registration from storage directory."""
        reg_dir = os.path.join(os.path.dirname(__file__), '..', '..', 'storage', 'app', 'emission-daemons')
        path = os.path.join(reg_dir, f"{channel_id}.json")
        if not os.path.exists(path):
            return None
        with open(path, 'r') as f:
            return json.load(f)
