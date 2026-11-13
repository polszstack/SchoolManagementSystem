<?php
require_once __DIR__ . '/../../../auth/session.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../../monitoring/models/AttendanceReview.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $db = (new Database())->getConnection();
    $account = (new User($db))->userSession();
    if (!$account || !AttendanceReview::allowed($account)) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Only an active college coordinator can view or review attendance.']); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $filter = $_GET['filter'] ?? 'pending';
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>100000]]);
        if (!is_string($filter) || $page === false) throw new InvalidArgumentException('Invalid attendance filters.');
        echo json_encode(['success'=>true] + AttendanceReview::records($db, $filter, $page)); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); header('Allow: GET, POST');
        echo json_encode(['success'=>false,'message'=>'Use POST to review attendance.']); exit;
    }
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || empty($_SESSION['attendance_review_csrf']) || !hash_equals($_SESSION['attendance_review_csrf'], $token)) {
        http_response_code(403); echo json_encode(['success'=>false,'message'=>'Refresh the page before reviewing attendance.']); exit;
    }
    foreach (['source','id','version','decision','remarks'] as $field) {
        if (isset($_POST[$field]) && !is_string($_POST[$field])) throw new InvalidArgumentException('Invalid review fields.');
    }
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    $version = filter_var($_POST['version'] ?? '', FILTER_VALIDATE_INT);
    AttendanceReview::decide($db, $account, $_POST['source'] ?? '', (int)$id, (int)$version, $_POST['decision'] ?? '', $_POST['remarks'] ?? '');
    echo json_encode(['success'=>true,'message'=>'Attendance decision saved.']);
} catch (InvalidArgumentException $error) {
    http_response_code(422); echo json_encode(['success'=>false,'message'=>$error->getMessage()]);
} catch (PDOException $error) {
    error_log('Attendance review: '.$error->getMessage());
    http_response_code(503); echo json_encode(['success'=>false,'message'=>'Attendance review storage is unavailable. Please contact the administrator.']);
} catch (RuntimeException $error) {
    http_response_code(409); echo json_encode(['success'=>false,'message'=>$error->getMessage()]);
}
