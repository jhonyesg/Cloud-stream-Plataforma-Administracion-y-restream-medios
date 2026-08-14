## Why

The emission/playout engine was removed on 2026-07-20 to restructure it from scratch. The scheduler already builds accurate 00:00-24:00 timelines with split content and ad cues, and VirtualScreen configures output parameters per channel, but there is no runtime engine to actually play those timelines and stream them to RTMP. We need a robust, gapless emission engine that consumes the programmed timeline and emits it continuously without cuts between items.

## What Changes

- **New Python emission daemon** (`emisor_python/`): one independent process per channel, supervised by a Python FastAPI control plane. Each daemon fetches the day's timeline from Laravel, builds a GStreamer pipeline, and plays items gaplessly with `seek`-based loop/restart.
- **Laravel emission control API** (`/api/channels/{id}/emission/*`): endpoints for `start`, `stop`, `status`, and an internal `position` endpoint used by the daemon for crash recovery.
- **GStreamer pipeline manager** in the daemon: handles both `copy` mode (H.264/AAC passthrough) and `transcode` mode (libx264/avenc_aac), with optional logo overlay and RTMP output.
- **Heartbeat integration**: daemon reports `broadcast_clock_sec`, `current_timeline_item_id`, `mode`, and `pipeline_pid` back to `emission_state` every 5 seconds.
- **Day rollover**: at 00:00 the daemon fetches the new template/block timeline automatically, cutting cleanly to the new day's playlist.
- **Crash recovery**: on restart, the daemon queries Laravel for the exact `broadcast_clock_sec`, calculates the correct item and offset via `ScheduledPositionCalculator`, and resumes mid-item — never from the start of the playlist.
- **No gapless concatenation artifacts**: validated with `uridecodebin` + `seek_simple(0)` + `FLUSH` for transcode mode, and `filesrc + qtdemux` + `seek_simple(0)` for copy mode. Encoder/muxer stays live; only the source restarts.

## Capabilities

### New Capabilities

- `emission-control-api`: Laravel REST endpoints to start, stop, query status, and resolve playhead position for a channel's emission engine.
- `emission-daemon`: Python FastAPI daemon that manages per-channel GStreamer pipelines, fetches timelines, handles day rollover, crash recovery, and heartbeats.
- `emission-pipeline`: GStreamer pipeline definitions for both copy and transcode modes, logo overlay, RTMP muxing, and gapless source switching.

### Modified Capabilities

- `virtual-screen-codec-config`: The `codec_video` and `codec_audio` fields already support `copy` vs `libx264`/`aac`. The emission engine now consumes these values to decide passthrough vs re-encode. No requirement change, but the capability is now actively used by the emission pipeline.
- `programming-timeline`: The timeline builder and mutator already produce split items and cues. The emission engine is a new consumer. No timeline requirement changes.

## Impact

- **New directory**: `emisor_python/` at project root (Python 3, GStreamer 1.20+, FastAPI/Flask).
- **New Laravel files**: `EmissionController`, `EmissionPositionController`, `EmissionOrchestrator` service, API routes.
- **Database**: `emission_state` table already has all required columns; no migration needed.
- **Dependencies**: GStreamer must remain installed (`gstreamer1.0-libav`, `plugins-bad`, `plugins-good`). FFmpeg remains for media probing; not used for emission.
- **Systemd/Docker**: One daemon process per channel; can be supervised by systemd units or a parent supervisor process.
- **Breaking**: The old `emision:*` Artisan commands and GStreamer script are gone and will not return. The new API is REST-based.
