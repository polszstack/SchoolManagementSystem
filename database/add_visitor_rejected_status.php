<?php
// Run once with CLI PHP before deploying the mobile visitor decision endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db.php';
$db = (new Database())->getConnection();
$column = $db->query("SHOW COLUMNS FROM mon_visitors LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
if (!$column) throw new RuntimeException('Visitor status column missing');
$type = $column['Type'];
if (stripos($type, 'enum(') === 0 && strpos($type, "'rejected'") === false) {
    // Preserve all existing enum values and their positions, nullability and default.
    $type = substr($type, 0, -1) . ",'rejected')";
    $nullable = $column['Null'] === 'YES' ? ' NULL' : ' NOT NULL';
    $default = $column['Default'] !== null ? ' DEFAULT ' . $db->quote($column['Default']) : ($column['Null'] === 'YES' ? ' DEFAULT NULL' : '');
    $db->exec('ALTER TABLE mon_visitors MODIFY COLUMN status ' . $type . $nullable . $default);
} elseif (stripos($type, 'enum(') !== 0 && stripos($type, 'varchar(') !== 0) {
    throw new RuntimeException('Unexpected visitor status type: ' . $type);
}
echo "Visitor status supports rejected; existing registrations preserved.\n";
