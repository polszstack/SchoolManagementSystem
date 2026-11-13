<?php
// Additive, repeatable migration. Existing records require review; no approval is invented.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../modules/monitoring/models/AttendanceReview.php';
$db = (new Database())->getConnection();
foreach (['mon_attendance_records', 'mon_attendance_archive'] as $table) {
    $fields = AttendanceReview::SCHEMA + ['faculty_signature'=>'MEDIUMTEXT NULL', 'signature_signed_at'=>'DATETIME NULL', 'signature_captured_by'=>'VARCHAR(100) NULL'];
    foreach ($fields as $name => $type) {
        if (!$db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($name))->fetch()) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$name` $type");
        }
    }
}
echo "Attendance approval fields are ready. Existing records remain pending review.\n";
