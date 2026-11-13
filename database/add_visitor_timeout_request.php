<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db.php';
$db = (new Database())->getConnection();
foreach (['mon_visitors', 'mon_visitors_archive'] as $table) {
    if (!$db->query("SHOW COLUMNS FROM $table LIKE 'timeout_requested_at'")->fetch()) {
        $db->exec("ALTER TABLE $table ADD COLUMN timeout_requested_at DATETIME NULL");
    }
}
echo "Visitor time-out request fields ready; existing records preserved.\n";
