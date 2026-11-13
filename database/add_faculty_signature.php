<?php
// Additive and repeatable; run using CLI before deploying signature capture.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db.php';
$db = (new Database())->getConnection();
foreach (['mon_attendance_records', 'mon_attendance_archive'] as $table) {
    foreach (['faculty_signature' => 'MEDIUMTEXT NULL', 'signature_signed_at' => 'DATETIME NULL', 'signature_captured_by' => 'VARCHAR(100) NULL'] as $name => $type) {
        if (!$db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($name))->fetch()) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$name` $type");
        }
    }
}
foreach (['time_out' => 'DATETIME NULL', 'visit_duration' => 'VARCHAR(32) NULL'] as $name => $type) {
    if (!$db->query('SHOW COLUMNS FROM mon_visitors_archive LIKE ' . $db->quote($name))->fetch()) {
        $db->exec("ALTER TABLE mon_visitors_archive ADD COLUMN `$name` $type");
    }
}
echo "Faculty signatures and visitor departure archive fields are ready.\n";
