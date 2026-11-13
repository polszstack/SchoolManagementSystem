<?php
// CLI-only, additive migration. Backups must be outside the web root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$appRoot = realpath($argv[1] ?? dirname(__DIR__));
$backupRoot = realpath($argv[2] ?? dirname($appRoot ?: __DIR__));
if (!$appRoot || !$backupRoot || $backupRoot === $appRoot || str_starts_with($backupRoot . DIRECTORY_SEPARATOR, $appRoot . DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Provide an application root and an existing backup directory outside the web root.');
}
require_once $appRoot . '/database/db.php';
$db = (new Database())->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$changes = [
    'mon_facility_reports_archive' => ['archived_by' => 'varchar(100)'],
    'mon_visitors_archive' => ['time_out' => 'datetime', 'visit_duration' => 'varchar(20)', 'archived_by' => 'varchar(100)'],
];
$lock = $db->query("SELECT GET_LOCK('monitoring_archive_columns_migration', 10)")->fetchColumn();
if ((int)$lock !== 1) throw new RuntimeException('Another archive migration is running.');
try {
    $pending = [];
    foreach ($changes as $table => $columns) {
        $existing = array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
        foreach ($columns as $column => $type) {
            if (!isset($existing[$column])) $pending[$table][$column] = $type;
            elseif (strtolower($existing[$column]['Type']) !== $type || $existing[$column]['Null'] !== 'YES') {
                throw new RuntimeException("Unexpected definition for $table.$column; review before migrating.");
            }
        }
    }
    if (!$pending) { echo "PASS: archive columns already present; no changes needed.\n"; }
    else {
        $backup = $backupRoot . '/archive-schema-backup-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        if (!mkdir($backup, 0700)) throw new RuntimeException('Could not create private backup directory.');
        $saved = [];
        $db->beginTransaction();
        try {
            foreach (array_keys($changes) as $table) {
                $ddl = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
                $rows = $db->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
                $saved[$table] = $rows;
                $path = $backup . '/' . $table . '.json';
                $bytes = json_encode(['schema' => $ddl, 'rows' => $rows], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Backup write failed.');
                chmod($path, 0600);
                if (file_get_contents($path) !== $bytes) throw new RuntimeException('Backup verification failed.');
            }
            $db->commit();
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
        echo "Verified private backup: $backup\n";
        foreach ($pending as $table => $columns) {
            $clauses = [];
            foreach ($columns as $column => $type) $clauses[] = "ADD COLUMN `$column` $type NULL DEFAULT NULL";
            $db->exec("ALTER TABLE `$table` " . implode(', ', $clauses));
            echo "Added " . implode(', ', array_keys($columns)) . " to $table.\n";
        }
        foreach ($changes as $table => $columns) {
            $actual = array_column($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC), null, 'Field');
            foreach ($columns as $column => $type) {
                if (!isset($actual[$column]) || strtolower($actual[$column]['Type']) !== $type || $actual[$column]['Null'] !== 'YES') {
                    throw new RuntimeException("Column verification failed: $table.$column");
                }
            }
            $lookup = $db->prepare("SELECT * FROM `$table` WHERE id = ?");
            foreach ($saved[$table] as $before) {
                $lookup->execute([$before['id']]); $after = $lookup->fetch(PDO::FETCH_ASSOC);
                foreach ($before as $field => $value) {
                    if (!$after || $after[$field] !== $value) throw new RuntimeException("Existing archive row changed during verification in $table; inspect backup.");
                }
            }
            echo "PASS: $table schema verified; " . count($saved[$table]) . " existing rows preserved.\n";
        }
    }
} finally { $db->query("SELECT RELEASE_LOCK('monitoring_archive_columns_migration')"); }
