<?php
/* Connection check - localhost only. DELETE this file before going online. */
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');
try {
    echo "Connected to MySQL server " . db()->server_info . "\n";
    foreach (['students', 'events', 'registrations', 'remember_tokens'] as $t) {
        echo str_pad($t, 18) . db_value("SELECT COUNT(*) FROM `$t`") . " rows\n";
    }
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n(Did you import database/studenthub.sql?)";
}
