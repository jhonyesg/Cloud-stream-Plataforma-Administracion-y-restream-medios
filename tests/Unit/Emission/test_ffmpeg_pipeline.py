import os
import sys
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..', 'emisor_python'))

from models.timeline_item import TimelineItem
from models.virtual_screen import VirtualScreen
from pipeline.ffmpeg_pipeline import FFmpegPipelineManager


def make_screen() -> VirtualScreen:
    return VirtualScreen(
        channel_id='ch-1',
        name='Test',
        width=1280,
        height=720,
        output_protocol='rtmp',
        output_url='rtmp://127.0.0.1/live/test',
        fps=30,
        video_bitrate_kbps=1000,
        audio_bitrate_kbps=128,
        codec_video='libx264',
        codec_audio='aac',
    )


def make_item(**overrides) -> TimelineItem:
    base = dict(
        id='item-1',
        kind='content',
        starts_at_sec=0,
        ends_at_sec=1000,
        effective_duration_sec=1000,
        media_item_id='media-1',
        filename='movie.mp4',
        cue_in_sec=None,
        cue_out_sec=None,
    )
    base.update(overrides)
    return TimelineItem(**base)


class TimelineItemTest(unittest.TestCase):
    def test_cue_kind_is_detected(self):
        self.assertTrue(make_item(kind='cue').is_cue)
        self.assertFalse(make_item(kind='content').is_cue)

    def test_duration_uses_effective_duration(self):
        item = make_item(effective_duration_sec=45.0)
        self.assertEqual(item.duration_sec, 45.0)


class FFmpegArgsTest(unittest.TestCase):
    def setUp(self):
        self.manager = FFmpegPipelineManager('ch-1', make_screen(), '/media')

    def test_tail_seeks_to_cue_in_and_limits_duration(self):
        item = make_item(
            id='tail',
            starts_at_sec=1045,
            ends_at_sec=9500,
            effective_duration_sec=8455,
            cue_in_sec=1000,
        )
        args = self.manager._build_ffmpeg_args(item, '/media/movie.mp4', seek_offset_sec=1000)

        self.assertEqual(args[args.index('-ss') + 1], '1000.000')
        self.assertEqual(args[args.index('-t') + 1], '8455.000')
        self.assertGreater(args.index('-t'), args.index('-map'))

    def test_cue_plays_full_duration(self):
        item = make_item(
            id='cue',
            kind='cue',
            starts_at_sec=1000,
            ends_at_sec=1045,
            effective_duration_sec=45,
            filename='ad.mp4',
        )
        args = self.manager._build_ffmpeg_args(item, '/media/ad.mp4', seek_offset_sec=0)

        self.assertNotIn('-ss', args)
        self.assertEqual(args[args.index('-t') + 1], '45.000')

    def test_missing_file_raises(self):
        item = make_item(filename='missing.mp4')
        with self.assertRaises(FileNotFoundError):
            self.manager._resolve_file_path(item)


class ReloadFilterTest(unittest.TestCase):
    def test_reload_ignores_unknown_fields(self):
        from daemon.channel_daemon import ChannelDaemon

        allowed = set(TimelineItem.__dataclass_fields__)
        payload = {
            'id': 'x',
            'kind': 'content',
            'starts_at_sec': 0,
            'ends_at_sec': 10,
            'effective_duration_sec': 10,
            'status': 'playing',
            'timeline_version': 3,
        }
        filtered = {k: v for k, v in payload.items() if k in allowed}
        item = TimelineItem(**filtered)
        self.assertEqual(item.id, 'x')
        self.assertEqual(item.timeline_version, 3)


if __name__ == '__main__':
    unittest.main()
