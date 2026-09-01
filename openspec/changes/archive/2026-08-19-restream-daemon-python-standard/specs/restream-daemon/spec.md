# restream-daemon Specification

## Purpose

Daemon Python independiente por target de restream, con el mismo estándar que el emisor de programación (`emisor_python/`): supervisor loop con restart y backoff, heartbeat hacia Laravel cada 5s, línea `[STATS]` cada 5s, watchdog de pipeline atascado, log rotado y unit systemd por target. Cada target corre su propio daemon y su propio ffmpeg hijo; un fallo en un target SHALL NOT afectar a los demás (los N cupos habilitados operan aislados).

## ADDED Requirements

### Requirement: One independent daemon per target

The system SHALL run one Python daemon process per restream target. Each daemon SHALL be completely isolated: its own process tree (daemon + ffmpeg child), its own log file, its own heartbeat, and its own restart loop. A crash, restart, or cooldown of one target SHALL NOT affect other targets of the same channel or user.

#### Scenario: Daemon startup
- **WHEN** the daemon is launched with `--target-id={uuid}`
- **THEN** it reads its config JSON (written by Laravel before spawn) containing `source_url`, `destination_url`, `stream_key`, `channel_id`, `platform`
- **AND** spawns the ffmpeg child with the built command
- **AND** writes a registration file to `storage/app/restream-daemons/{targetId}.json` with `{"pid":N,"started_at":"ISO8601"}`
- **AND** starts the heartbeat loop and the stats loop

#### Scenario: Target isolation
- **WHEN** target `t1` crashes and enters its restart backoff
- **THEN** target `t2` (same channel) keeps streaming without interruption and its heartbeat continues

### Requirement: Daemon builds the FFmpeg command

The daemon SHALL build the ffmpeg command from the target config: input `-i {source_url}` (or the channel `public_hls_url` fallback resolved by Laravel and written into the config), `-c:v copy`, `-c:a aac -ar 44100 -ac 2 -b:a 128k`, `-f flv`, and the destination `{destination_url}/{stream_key}`. The RTMP live flag SHALL be passed as `-rtmp_live live` (no `=1`), which is the only form accepted by ffmpeg 4.4.

#### Scenario: Command uses corrected rtmp_live flag
- **WHEN** the daemon builds the command for any target
- **THEN** the argument list contains `-rtmp_live` followed by the value `live` (never `live=1`)

#### Scenario: Command consumes the resolved source
- **WHEN** the daemon builds the command for a target whose config has `source_url = "rtmp://emision.local/app/CHAN"`
- **THEN** the `-i` argument equals `"rtmp://emision.local/app/CHAN"`
- **AND** when the config has `source_url = null` and `fallback_source = "https://canal.example/live/chan.m3u8"`, the `-i` argument equals the fallback

#### Scenario: Stream key is never logged
- **WHEN** the daemon logs the command or any error
- **THEN** the plaintext `stream_key` SHALL NOT appear in the log file

### Requirement: Heartbeat reporting

The daemon SHALL report its state to Laravel every 5 seconds via the internal endpoint `POST /api/internal/restream/{target}/heartbeat`, with body containing `status` (`starting`/`live`/`error`/`offline`), `pipeline_pid` (the ffmpeg child PID), `error_message` (nullable), and `timestamp`. Laravel SHALL update the `restream_targets` row (`status`, `last_heartbeat_at`, `pipeline_pid`, `last_error`).

#### Scenario: Successful heartbeat
- **WHEN** 5 seconds have elapsed since the last heartbeat and ffmpeg is running
- **THEN** the daemon POSTs a heartbeat with `status='live'` and the ffmpeg child PID
- **AND** Laravel updates `restream_targets.last_heartbeat_at = now()` and `status = 'live'`

#### Scenario: Heartbeat reports error
- **WHEN** the ffmpeg child exits with a non-zero code
- **THEN** the daemon POSTs a heartbeat with `status='error'` and the last stderr line in `error_message`
- **AND** Laravel sets `restream_targets.status = 'error'` and `last_error` to that line

#### Scenario: Heartbeat on shutdown
- **WHEN** the daemon shuts down cleanly (stop requested)
- **THEN** it POSTs a final heartbeat with `status='offline'` and Laravel clears `pipeline_pid` and sets `status = 'idle'`

### Requirement: Supervisor loop with restart and cooldown

The daemon SHALL supervise its own ffmpeg child: if the child dies, it SHALL restart it with exponential backoff (max 5 restarts in 60s, then a 30s cooldown). After exceeding the restart budget, it SHALL report `status='error'` via heartbeat and enter cooldown, mirroring the emisor's `main.py` supervisor behavior.

#### Scenario: Restart after crash
- **WHEN** the ffmpeg child exits unexpectedly
- **THEN** the daemon logs the failure, waits the backoff interval, and spawns a new ffmpeg child with the same config
- **AND** the target's heartbeat continues reporting `status='live'` once the new child is up

#### Scenario: Cooldown after repeated crashes
- **WHEN** the ffmpeg child crashes 6 times within 60 seconds
- **THEN** the daemon POSTs a heartbeat with `status='error'` and `error_message` describing the repeated crashes
- **AND** it sleeps 30 seconds before attempting another restart

### Requirement: Stats and watchdog

The daemon SHALL write a `[STATS]` line to its log every 5 seconds with `uptime`, `bitrate`, `fps`, `frames_sent`, and `state`, and SHALL run a watchdog: if the ffmpeg child is alive but produces no progress for 15 seconds, the daemon SHALL restart the child.

#### Scenario: Stats line written
- **WHEN** the daemon has been streaming for 10 seconds
- **THEN** the log contains at least one line matching `[STATS] uptime=… bitrate=… fps=… frames_sent=… state=playing`

#### Scenario: Watchdog restarts a stuck child
- **WHEN** the ffmpeg child is alive but `frames_sent` does not advance for 15 seconds
- **THEN** the daemon kills the child, spawns a new one, and logs `[WATCHDOG]` with the reason

### Requirement: Log rotation

The daemon SHALL write its log to `storage/logs/restream/{target_id}.log` using the shared `LogRotator` (max 1MB / 500 lines, keeps last half, atomic writes, flush every 2s). The log SHALL be readable by the Laravel log endpoint.

#### Scenario: Log is rotated
- **WHEN** the log exceeds 500 lines
- **THEN** the file is rewritten keeping only the last 250 lines

### Requirement: Systemd unit per target

The system SHALL ship a systemd template unit `cloudstream-restream@.service` (same style as `cloudstream-emission@.service`) that runs `restream_daemon/main.py --target-id=%i` under the `www` user, with `Restart=on-failure` and `RestartSec=5`.

#### Scenario: Unit starts a target
- **WHEN** `systemctl start cloudstream-restream@{targetId}` is invoked
- **THEN** the daemon starts, reads the target config, and spawns ffmpeg
