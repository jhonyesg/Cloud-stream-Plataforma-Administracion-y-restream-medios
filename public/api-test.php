<?php
echo json_encode([
    'uri' => $_SERVER['REQUEST_URI'] ?? 'none',
    'script' => $_SERVER['SCRIPT_NAME'] ?? 'none',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'none',
]);
