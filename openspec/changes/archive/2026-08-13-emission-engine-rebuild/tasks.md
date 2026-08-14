## 1. Laravel Control API

- [x] 1.1 Create `EmissionController` with `start()`, `stop()`, `status()` methods
- [x] 1.2 Create `EmissionPositionController` with internal `position()` method (IP-gated)
- [x] 1.3 Create `EmissionOrchestrator` service: resolve timeline, spawn daemon, signal daemon
- [x] 1.4 Add routes in `routes/api.php`: `POST /channels/{id}/emission/{action}`
- [x] 1.5 Implement `EmissionState` update helpers (heartbeat write, status transitions)
- [x] 1.6 Add authorization: channel owner or admin only

## 2. Python Daemon Skeleton

- [x] 2.1 Create `emisor_python/` directory structure (daemon, api, pipeline, models)
- [x] 2.2 Add `requirements.txt` (PyGObject, fastapi/uvicorn, requests, pydantic)
- [x] 2.3 Implement `ChannelDaemon` class: lifecycle (start, stop, pipeline build)
- [x] 2.4 Implement `DaemonApiServer` (FastAPI): `/stop`, `/status`, `/reload-timeline`
- [x] 2.5 Implement PID + port registration file writer (`storage/app/emission-daemons/`)
- [x] 2.6 Implement `LaravelClient`: fetch timeline, virtual screen, post heartbeat

## 3. GStreamer Pipeline Manager

- [x] 3.1 Implement `build_transcode_pipeline()` (uridecodebin → x264enc/avenc_aac → flvmux → rtmpsink)
- [x] 3.2 Implement `build_copy_pipeline()` (filesrc → qtdemux → h264parse/aacparse → flvmux → rtmpsink)
- [x] 3.3 Implement `TimelineQueue`: holds items, advances on EOS, handles loop
- [x] 3.4 Implement gapless restart: `seek_simple(0, FLUSH)` on source EOS
- [x] 3.5 Implement `on_bus_message`: EOS → next item/loop, ERROR → report + stop + recover
- [x] 3.6 Implement logo overlay: conditional `gdkpixbufoverlay` with x/y/w/h/opacity

## 4. Timeline & Playback Logic

- [x] 4.1 Fetch timeline on startup and cache in `TimelineQueue`
- [x] 4.2 Map timeline item to source file path (`channel.root_path + filename`)
- [x] 4.3 Handle `cue_in_sec` / `cue_out_sec`: seek to offset, limit duration
- [x] 4.4 Handle split content items: parent/child rows play sequentially
- [x] 4.5 Implement day rollover: cron-like 00:00 check, fetch new timeline, reset queue
- [x] 4.6 Handle short playlists (<24h): loop indefinitely, increment `loops_completed`

## 5. Heartbeat & State Reporting

- [x] 5.1 Thread/timer that POSTs heartbeat every 5s to Laravel internal endpoint
- [x] 5.2 Calculate `broadcast_clock_sec` from system time + `started_at`
- [x] 5.3 Report `current_timeline_item_id`, `content_position_sec`, `mode`, `pipeline_pid`
- [x] 5.4 Laravel endpoint: receive heartbeat, update `emission_state` row
- [x] 5.5 Stale heartbeat detection: mark `error` if >15s without heartbeat

## 6. Crash Recovery

- [x] 6.1 On GStreamer ERROR, capture current time and compute `broadcast_clock_sec`
- [x] 6.2 Query Laravel `/api/internal/.../position` with `broadcast_clock_sec`
- [x] 6.3 Receive `current_timeline_item_id` + `offset_in_item_sec`
- [x] 6.4 Rebuild pipeline starting at that item with correct seek offset
- [x] 6.5 Fallback: if API unreachable, use local `TimelineQueue` + system time
- [x] 6.6 Max retry limit: after N failed restarts, enter permanent `error` state

## 7. Control Integration & Testing

- [x] 7.1 Wire Laravel `start` to spawn daemon process (`python main.py --channel-id=X`)
- [x] 7.2 Wire Laravel `stop` to POST `/stop` to daemon API and wait for exit
- [x] 7.3 End-to-end test: Red Planet (copy mode, 2.96s loop) — verify 10 min stability
- [x] 7.4 End-to-end test: CineDios (transcode mode, logo overlay, multi-item timeline)
- [x] 7.5 Simulate crash: kill daemon mid-playback, verify auto-restart resumes correctly
- [x] 7.6 Simulate missing file: remove media, verify `error` state and readable message

## 8. Deployment & Ops

- [x] 8.1 Create systemd unit template: `cloudstream-emission@.service`
- [x] 8.2 Add daemon auto-start on Laravel `start` if not already running
- [x] 8.3 Ensure `emisor_python/` is deployed with git (add to `.gitignore` if needed for venv)
- [x] 8.4 Document environment requirements: GStreamer plugins, Python 3.10+
- [x] 8.5 Add log rotation for `/tmp/` or `storage/logs/emission/` daemon logs
- [x] 8.6 Rollback procedure: kill all `emisor_python` processes, set statuses to `offline`
