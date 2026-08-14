<?php

return [
    'fs_browse_root' => env('FS_BROWSE_ROOT', '/mnt/multimedia'),

    'media' => [
        'disk' => env('MEDIA_DISK', 'local'),
        'thumb_disk' => env('MEDIA_THUMB_DISK', 'public'),
        'max_upload_size' => (int) env('MEDIA_MAX_UPLOAD_SIZE', 5368709120),
        'bulk_max_files' => (int) env('MEDIA_BULK_MAX_FILES', 20),
        'allowed_mimes' => [
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'image/jpeg',
            'image/png',
            'image/webp',
            'audio/mpeg',
            'audio/wav',
        ],
    ],
];