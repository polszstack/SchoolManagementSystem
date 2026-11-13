<?php
/** Resolve review attachments using current account permissions, never a supplied path. */
class SubmissionAttachment {
    public static function resolve(PDO $db, int $employeeId, string $kind, int $id): ?string {
        if (!in_array($kind, ['approvals', 'issues'], true) || $id < 1) return null;
        $stmt = $db->prepare("SELECT e.department_id, r.role_name, d.department_code
            FROM em_employees e LEFT JOIN em_roles r ON r.role_id=e.role_id
            LEFT JOIN em_departments d ON d.department_id=e.department_id
            WHERE e.employee_id=? AND e.employment_status='Active'");
        $stmt->execute([$employeeId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) return null;
        $key = $kind === 'approvals' ? 'approval_id' : 'issue_id';
        $stmt = $db->prepare("SELECT department, status, file_path FROM sd_$kind WHERE $key=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status'] === 'draft') return null;
        $directress = $account['role_name'] === 'School Directress' || $account['department_code'] === 'SMS_SD';
        if (!$directress && (int)$row['department'] !== (int)$account['department_id']) return null;
        $root = dirname(__DIR__, 3);
        $path = realpath($root . '/' . ltrim((string)$row['file_path'], '/'));
        if (!$path || !is_file($path)) return null;
        $allowed = false;
        foreach (['uploads', 'modules'] as $folder) {
            $base = realpath($root . '/' . $folder);
            if ($base && strpos($path, $base . DIRECTORY_SEPARATOR) === 0) $allowed = true;
        }
        if (!$allowed || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['pdf','doc','docx','jpg','jpeg','png'], true)) return null;
        return $path;
    }
}
