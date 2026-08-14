<?php

return [
    'log' => [
        'max_bytes' => env('EMISSION_LOG_MAX_BYTES', 50 * 1024 * 1024),
        'retention_days' => env('EMISSION_LOG_RETENTION_DAYS', 7),
    ],
];
