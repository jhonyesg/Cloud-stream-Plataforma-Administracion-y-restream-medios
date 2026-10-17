<?php

namespace App\Enums;

use InvalidArgumentException;

/**
 * Closed whitelist of `restream_target_events.kind` values, classified by
 * causal source. The engine (and any future code that writes events) MUST
 * route through this enum so a stray string is rejected at the boundary
 * instead of slipping into the append-only history.
 */
final class RestreamTargetEventKind
{
    public const MANUAL_STARTED = 'manual_started';
    public const MANUAL_STOPPED = 'manual_stopped';
    public const ADMIN_STARTED = 'admin_started';
    public const ADMIN_STOPPED = 'admin_stopped';
    public const AUTO_STARTED = 'auto_started';
    public const AUTO_STOPPED = 'auto_stopped';
    public const YOUTUBE_REVOKED = 'youtube_revoked';
    public const YOUTUBE_CONSUMED_REMOTELY = 'youtube_consumed_remotely';
    public const YOUTUBE_HEALTH_LOST = 'youtube_health_lost';
    public const FFMPEG_DIED = 'ffmpeg_died';
    public const NETWORK_UNREACHABLE = 'network_unreachable';
    public const OAUTH_EXPIRED = 'oauth_expired';
    public const QUOTA_EXCEEDED = 'quota_exceeded';
    public const WATCHDOG_RECOVERY_SUCCEEDED = 'watchdog_recovery_succeeded';
    public const WATCHDOG_RECOVERY_EXHAUSTED = 'watchdog_recovery_exhausted';
    public const LEGACY_FAILURE_ARCHIVED = 'legacy_failure_archived';
    public const YOUTUBE_TRANSITION_SUCCEEDED = 'youtube_transition_succeeded';
    public const YOUTUBE_TRANSITION_FAILED = 'youtube_transition_failed';
    public const YOUTUBE_ZOMBIE_DETECTED = 'youtube_zombie_detected';
    public const YOUTUBE_ZOMBIE_RECOVERED = 'youtube_zombie_recovered';
    public const YOUTUBE_ZOMBIE_RECOVERY_FAILED = 'youtube_zombie_recovery_failed';

    /**
     * @var array<int, string>
     */
    public const ALL = [
        self::MANUAL_STARTED,
        self::MANUAL_STOPPED,
        self::ADMIN_STARTED,
        self::ADMIN_STOPPED,
        self::AUTO_STARTED,
        self::AUTO_STOPPED,
        self::YOUTUBE_REVOKED,
        self::YOUTUBE_CONSUMED_REMOTELY,
        self::YOUTUBE_HEALTH_LOST,
        self::FFMPEG_DIED,
        self::NETWORK_UNREACHABLE,
        self::OAUTH_EXPIRED,
        self::QUOTA_EXCEEDED,
        self::WATCHDOG_RECOVERY_SUCCEEDED,
        self::WATCHDOG_RECOVERY_EXHAUSTED,
        self::LEGACY_FAILURE_ARCHIVED,
        self::YOUTUBE_TRANSITION_SUCCEEDED,
        self::YOUTUBE_TRANSITION_FAILED,
        self::YOUTUBE_ZOMBIE_DETECTED,
        self::YOUTUBE_ZOMBIE_RECOVERED,
        self::YOUTUBE_ZOMBIE_RECOVERY_FAILED,
    ];

    public static function tryFrom(string $value): string
    {
        if (! in_array($value, self::ALL, true)) {
            throw new InvalidArgumentException(
                "Unknown RestreamTargetEventKind '{$value}'. Allowed: ".implode(', ', self::ALL)
            );
        }

        return $value;
    }
}
