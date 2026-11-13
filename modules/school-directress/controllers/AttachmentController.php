<?php
require_once __DIR__ . '/../../../auth/session.php';
require_once __DIR__ . '/../../../database/db.php';
require_once __DIR__ . '/../classes/SubmissionAttachment.php';
try {
    $kind = is_string($_GET['kind'] ?? null) ? $_GET['kind'] : '';
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
    $path = SubmissionAttachment::resolve((new Database())->getConnection(), (int)($_SESSION['employee_id'] ?? 0), $kind, $id);
    if (!$path) { http_response_code(404); exit('Attachment not found.'); }
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="document.' . strtolower(pathinfo($path, PATHINFO_EXTENSION)) . '"');
    header('Content-Length: ' . filesize($path));
    session_write_close();
    readfile($path);
} catch (Throwable $error) {
    error_log('Review attachment download failed: ' . $error->getMessage());
    http_response_code(500);
    echo 'The attachment could not be downloaded. Please try again.';
}
