## Context

The Cloudstream scheduler produces accurate 00:00-24:00 `program_timeline_items` per channel, including content splits and ad cue insertions. The `emission_state` table and `VirtualScreen` model already store all runtime parameters (codecs, bitrates, resolution, RTMP URL, logo). However, the actual playout engine was removed on 2026-07-20 and nothing currently consumes the timeline or writes to `emission_state`.

The new engine must be **gapless** — cuts between content items or mid-content cue insertions are unacceptable for live streaming. It must also be **resilient** — crash recovery must resume at the exact `broadcast_clock_sec` without restarting the playlist from 00:00.

Validated approach: GStreamer 1.20+ with `uridecodebin` (transcode mode) or `filesrc+qtdemux` (copy mode), using `pipeline.seek_simple(0, FLUSH)` to restart the source without stopping the encoder/muxer. Tested successfully with Red Planet channel looping a 2.96s video for 4 minutes without errors.

## Goals / Non-Goals

**Goals:**
- One independent Python daemon process per active channel.
- Daemon fetches today's `program_timeline_items` via Laravel internal API.
- Plays timeline items sequentially, gaplessly, with GStreamer.
- Supports both `copy` passthrough and `transcode` modes based on `VirtualScreen` config.
- Optional logo overlay when `VirtualScreen.logo_media_item_id` is set.
- Reports heartbeat (status, `broadcast_clock_sec`, `current_timeline_item_id`, `pipeline_pid`) to Laravel every 5s.
- On crash, queries Laravel for current position, calculates exact item+offset via `ScheduledPositionCalculator`, and resumes.
- At 00:00, fetches new template/block/timeline and cuts cleanly to the new day.
- Controlled via Laravel REST API: `start`, `stop`, `status`, `now-playing`.

**Non-Goals:**
- Multi-node clustering or horizontal scaling of daemons.
- HLS/DASH output (RTMP only for now; can extend later).
- Real-time timeline mutation without restart (day inserts after 00:00 require manual restart).
- GPU encoding (CPU-only `x264enc` / `avenc_aac`).

## Decisions

**1. Python daemon instead of PHP Artisan worker**
- *Why*: PHP is not a suitable runtime for long-lived signal-handling processes. Python with `GLib.MainLoop` and `GStreamer` bindings is the industry standard for media pipelines.
- *Alternative considered*: PHP `proc_open` wrapping `gst-launch-1.0`. Rejected because it offers no granular control over EOS handling, seek recovery, or heartbeat integration.

**2. One daemon per channel (not one global supervisor)**
- *Why*: Channel isolation prevents a heavy transcode from starving another. Each channel has its own `VirtualScreen` config, timeline, and RTMP destination.
- *Alternative considered*: Single Python process managing multiple pipelines with `Gst.Pipeline` arrays. Rejected because a crash in one pipeline's GStreamer context could destabilize others.

**3. GStreamer `seek_simple(0)` with `FLUSH` for gapless restart**
- *Why*: Tested and validated. The encoder (`x264enc`/`avenc_aac`) and muxer (`flvmux`) stay in `PLAYING` state. Only the source element (`uridecodebin` or `filesrc`) seeks to 0, re-links pads, and continues. The RTMP sink never disconnects.
- *Alternative considered*: FFmpeg concat demuxer with a regenerable playlist file. Rejected because concat demuxer has visible gaps (~0.5–1s) between items when re-initializing headers.
- *Alternative considered*: GStreamer `playbin3` with loop. Rejected because `playbin3` manages sinks internally and does not allow a custom encoder chain to RTMP without complex `interpipe` plumbing.

**4. FastAPI (or Flask) embedded HTTP control server in each daemon**
- *Why*: Laravel needs to signal `start`/`stop` to the daemon. A lightweight HTTP server on `localhost` (or unix socket) is simpler than dbus or ZeroMQ.
- *Port assignment*: Fixed port range per channel (e.g., base 15000 + channel index), or dynamic with PID file registration in Laravel.

**5. Copy vs transcode decided by `VirtualScreen.codec_video`**
- *Why*: The field already stores `libx264`, `libx265`, `libvpx-vp9`, or `copy`. When `copy`, the pipeline uses `h264parse`/`aacparse` passthrough. When anything else, it decodes and re-encodes.
- *Resolution*: For transcode, `VirtualScreen.width x height` is enforced. For copy, output resolution follows the source file (assumes all channel files share the same resolution).

**6. Day rollover: hard cut at 00:00 to new timeline**
- *Why*: The user explicitly requested this behavior. The daemon detects local time crossing 00:00, fetches the new block's timeline, rebuilds the item queue, and performs a `seek_simple(0)` to the first item of the new day.

**7. Missing file = error and stop**
- *Why*: The user stated this is the client's responsibility. The daemon sets `emission_state.status = error`, writes `error_message`, and stops. No auto-fallback except the static `VirtualScreen.fallback_type` when the channel is offline.

## Risks / Trade-offs

| Risk | Mitigation |
|------|------------|
| GStreamer `seek_simple` fails with some file formats (MKV, AVI) | Only MP4/H.264+AAC is guaranteed. Document this limitation. Future work: probe file with `discoverer` before adding to pipeline. |
| Encoder `x264enc` drifts out of sync after many loops (PTS wrap) | Use `h264parse` with `update-timecode=true` and `flvmux` streamable mode. Tested 6+ loops without drift. |
| Logo overlay adds CPU load | Only applied when `logo_media_item_id` is set. Logo is a static image loaded once into an `overlay` element. |
| Daemon port collision if many channels | Use port 0 (OS-assigned) + PID file with registered port, or base port from channel config. |
| Laravel API down when daemon restarts | Daemon caches last known timeline JSON locally (in `/tmp` or its own state dir) and uses it as fallback for one loop while retrying the API. |
| Two daemons start for same channel (race condition) | Laravel `EmissionState.status` guard: refuse `start` if `status ∈ {starting, live}`. Daemon self-checks PID file on startup. |
| Memory growth in GStreamer over days | Schedule nightly daemon restart at low-traffic hour, or monitor RSS and restart if > threshold. |

## Migration Plan

1. **Deploy Python code**: `emisor_python/` directory with `requirements.txt` (PyGObject, FastAPI/Flask, requests).
2. **Deploy Laravel API**: New routes + controllers under feature flag or admin-only initially.
3. **Test on Red Planet** (copy mode, short file) → confirm 24h stability.
4. **Test on CineDios** (transcode mode, logo overlay, longer timeline with cues).
5. **Add systemd units** (one per channel, or a supervisor that spawns them).
6. **Remove feature flag**, document API for frontend team.
7. **Rollback**: Kill daemon processes + set `emission_state.status = offline`. Old scheduler remains untouched.

## Open Questions

1. **Port assignment strategy**: Fixed range vs dynamic? Consensus: dynamic with OS-assigned port + registration file in `storage/app/emission-daemons/{channelId}.json`.
2. **Logo file format**: `VirtualScreen.mediaItem` can be PNG/JPG. GStreamer `gdkpixbufoverlay` or `rsvgoverlay`? Use `gdkpixbufoverlay` for simplicity; require PNG/JPG.
3. **Cue item duration**: If a cue `media_item.duration_sec` differs from the timeline's `effective_duration_sec`, which wins? The timeline is authoritative.
