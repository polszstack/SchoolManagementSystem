<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db.php';
$db = (new Database())->getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS mon_visitor_recovery (
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    visitor_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX visitor_recovery_record (visitor_id)
) ENGINE=InnoDB");
echo "Visitor recovery storage ready. Existing visits and timestamps preserved.\n";
