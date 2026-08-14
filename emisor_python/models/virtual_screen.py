from dataclasses import dataclass
from typing import Optional


@dataclass
class VirtualScreen:
    """Output configuration for a channel."""
    channel_id: str
    name: str
    width: int
    height: int
    output_protocol: str
    output_url: str
    fps: int
    video_bitrate_kbps: int
    audio_bitrate_kbps: int
    codec_video: str
    codec_audio: str
    video_preset: str = 'veryfast'
    logo_media_item_id: Optional[str] = None
    logo_x: int = 0
    logo_y: int = 0
    logo_w: int = 0
    logo_h: int = 0
    logo_opacity: float = 1.0
    fallback_type: str = 'black'
    fallback_media_item_id: Optional[str] = None

    @property
    def is_copy_mode(self) -> bool:
        return self.codec_video == 'copy' and self.codec_audio == 'copy'

    @property
    def logo_enabled(self) -> bool:
        return self.logo_media_item_id is not None and self.logo_media_item_id != ''
