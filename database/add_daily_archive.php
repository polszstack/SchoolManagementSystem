<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/db.php';
$db=(new Database())->getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS mon_daily_archive_runs (
    run_date DATE PRIMARY KEY, run_kind VARCHAR(20) NOT NULL,
    completed_at DATETIME NOT NULL, counts_json TEXT NULL
) ENGINE=InnoDB");
// First automatic move is after the next Manila date change, not during deployment.
// This also leaves restored old records available for the remainder of their restore day.
$stmt=$db->prepare("INSERT IGNORE INTO mon_daily_archive_runs (run_date,run_kind,completed_at,counts_json) VALUES (?,'enabled',?,NULL)");
$stmt->execute([date('Y-m-d'),date('Y-m-d H:i:s')]);
echo "Daily archiving enabled; first scheduled move is after the next Philippine midnight.\n";
