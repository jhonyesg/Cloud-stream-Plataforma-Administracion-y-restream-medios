## ADDED Requirements

### Requirement: Schedule view shows a live countdown to `ends_at` for running and recently-ended schedules

The client and admin schedule index (`resources/views/{client,admin}/restream/target/schedules/index.blade.php`) and the calendar chip component (`resources/views/components/target-schedule-calendar.blade.php`) MUST display, alongside the existing status badge, a per-row countdown label that updates every second on the client. The countdown MUST be the same on both surfaces.

#### Scenario: Running schedule shows "Termina en HH:MM:SS"

- **GIVEN** a schedule with `starts_at < now() < ends_at` and `status='running'`
- **WHEN** the operator opens the schedule page
- **THEN** the row shows `Termina en HH:MM:SS` where the value decreases once per second without server polling
- **WHEN** the countdown reaches zero
- **THEN** the label flips to `Finalizó hace Xm` and stays that way

#### Scenario: Indefinite schedule shows "Sin fin"

- **GIVEN** a schedule with `is_indefinite=true` and `status='running'`
- **WHEN** the operator opens the schedule page
- **THEN** the countdown cell shows `Sin fin` (no numeric counter)

#### Scenario: Pending schedule shows "Inicia en HH:MM:SS"

- **GIVEN** a schedule with `starts_at > now()` and `status='pending'`
- **WHEN** the operator opens the schedule page
- **THEN** the countdown cell shows `Inicia en HH:MM:SS` decreasing once per second

#### Scenario: Ended schedule shows "Finalizó hace Xm"

- **GIVEN** a schedule with `now() > ends_at` and `status='ended'`
- **WHEN** the operator opens the schedule page
- **THEN** the countdown cell shows `Finalizó hace 5m` (or `2h 13m`, whichever is the smallest unit that fits)

#### Scenario: Calendar grid chip includes the countdown

- **WHEN** the operator looks at the calendar grid for a day with a `running` schedule
- **THEN** the chip on that day cell renders the countdown text in addition to the schedule name and start time

#### Scenario: Parity client/admin

- **WHEN** the operator opens `/client/restream/restream-targets/{t}/schedules` and `/admin/channels/{c}/restream-targets/{t}/schedules` for the same target
- **THEN** both pages show the same countdown text for the same schedule