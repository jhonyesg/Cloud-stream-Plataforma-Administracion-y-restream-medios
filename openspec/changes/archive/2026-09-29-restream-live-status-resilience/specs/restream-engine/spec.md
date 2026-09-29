## ADDED Requirements

### Requirement: Daemon reports real ffmpeg stats parsed from stderr

The restream daemon's `_stats_loop` MUST drain ffmpeg's stderr every cycle and update the stats from the latest `frame=`, `fps=`, and `bitrate=` lines. The values reported to Laravel via heartbeat and the `[STATS]` log MUST reflect what ffmpeg actually emitted, not a wall-clock estimate.

#### Scenario: Real bitrate appears in stats

- **GIVEN** a live target with ffmpeg encoding at 2500 kbps
- **WHEN** the daemon logs the next `[STATS]` line
- **THEN** the line includes `bitrate=2500kbps` (or `2502kbps`, `2498kbps` — whatever ffmpeg last reported)
- **AND** the heartbeat payload's `status` reflects the same bitrate

#### Scenario: Stderr parsing is robust to format drift

- **GIVEN** a future ffmpeg version prints `frame=  N | fps=  N | bitrate=  Nkbits/s` (extra whitespace, pipe separators)
- **WHEN** the daemon parses the next stats line
- **THEN** the regex tolerates the variation and the stats update normally
- **WHEN** the regex fails to match (wholly new format)
- **THEN** the previous values are kept and a `[STATS] parse failed` warning is logged once per minute (not spammed)

#### Scenario: Emission pipeline regression

- **GIVEN** an emission channel with its own `emisor_python` daemon
- **WHEN** the same change is deployed
- **THEN** the emission daemon's `[STATS]` lines continue to look identical to before (no format change in the log)
- **AND** the operator's emission panel continues to show `bitrate=2500kbps` (which was already correct)

### Requirement: Daemon detects a dead RTMP output and auto-restarts the ffmpeg child

When the daemon observes (a) the ffmpeg output's bitrate is zero for ≥10 s, AND (b) the latest stderr lines include at least one of `RTMP_ReadPacket`, `failed to read RTMP`, `Connection reset`, or `av_interleaved_write_frame`, the daemon MUST log `[WATCHDOG] RTMP output socket closed, restarting child…`, set `_status='error'` and `_error_message='rtmp-output-closed'`, trigger `_restart_child()`, and send a heartbeat with `daemon_stalled_at=now()`.

#### Scenario: Auto-recovery from a dropped YouTube connection

- **GIVEN** a live target whose RTMP output to YouTube is closed by the remote side (FIN / CLOSE-WAIT)
- **WHEN** the daemon's next 5-s stats tick runs
- **THEN** the daemon logs `[WATCHDOG] RTMP output socket closed, restarting child…` within 15 s of the remote close
- **AND** `_restart_child()` respawns ffmpeg with the same config
- **AND** the new ffmpeg establishes a fresh ESTABLISHED connection to YouTube
- **AND** the heartbeat reports `daemon_stalled_at=now()` once, then clears to `null` once the new child is healthy

#### Scenario: Auto-recovery respects existing restart cap

- **GIVEN** a target that has crashed and restarted 5 times in the last 60 s
- **WHEN** the 6th crash is detected
- **THEN** the daemon enters cooldown (per the existing supervisor logic in `main.py`)
- **AND** the heartbeat reports `status='error'`
- **AND** Laravel's `RestreamReapDeadBroadcastsCommand` (next minute cron) handles recreation

#### Scenario: No false positives during normal startup

- **GIVEN** a freshly started ffmpeg child whose first 10 s show `bitrate=0` (no data yet, no RTMP packets)
- **WHEN** the daemon's watchdog runs in the first 15 s
- **THEN** it does NOT trigger a restart (no `RTMP_ReadPacket` / `Connection reset` in stderr yet)
- **WHEN** the daemon sees the first `frame=` line at second 8
- **THEN** `bitrate` updates to a real value and the watchdog stops watching

#### Scenario: Manual reproduction matches the spec

- **GIVEN** a live target
- **WHEN** the operator runs `kill -STOP <ffmpeg-pid>` for 20 s then `kill -CONT`
- **THEN** after the SIGSTOP, the daemon's stats thread continues to run but the ffmpeg child produces no new stderr
- **WHEN** SIGCONT is sent and the child resumes, the daemon's next stats tick sees new `frame=` lines
- **AND** no false restart is triggered during the brief pause