<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db.php';
$db = (new Database())->getConnection();
foreach (['mon_visitors', 'mon_visitors_archive'] as $table) {
    foreach (['departure_confirmed_at' => 'DATETIME NULL', 'departure_confirmed_by' => 'INT NULL', 'departure_reason' => 'VARCHAR(500) NULL'] as $column => $type) {
        if (!$db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetch()) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $type");
        }
    }
}
echo "Unrecorded visitor departure fields ready; existing records preserved.\n";
