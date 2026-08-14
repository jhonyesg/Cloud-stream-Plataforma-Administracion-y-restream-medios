from dataclasses import dataclass
from typing import Optional


@dataclass
class TimelineItem:
    """Represents a single item in the day's program timeline."""
    id: str
    kind: str  # 'content' | 'cue' | 'fallback'
    starts_at_sec: int
    ends_at_sec: int
    effective_duration_sec: float
    media_item_id: Optional[str] = None
    filename: Optional[str] = None
    cue_in_sec: Optional[float] = None
    cue_out_sec: Optional[float] = None
    resume_offset_sec: float = 0.0
    parent_content_id: Optional[str] = None
    is_interruptible: bool = False
    timeline_version: int = 0
    file_path: Optional[str] = None  # Full resolved file path (channel.root_path + filename)

    @property
    def duration_sec(self) -> float:
        """Effective playback duration in seconds."""
        return max(0.0, float(self.effective_duration_sec))

    @property
    def is_cue(self) -> bool:
        return self.kind == 'cue'

    @property
    def is_content(self) -> bool:
        return self.kind == 'content'
