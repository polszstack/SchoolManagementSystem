<?php
require_once __DIR__ . '/../../../auth/session.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../../monitoring/models/AttendanceReview.php';
$attendanceAccount = (new User())->userSession();
if (!$attendanceAccount || !AttendanceReview::allowed($attendanceAccount)) {
    http_response_code(403); echo '<p>Only an active college coordinator can review attendance.</p>'; return;
}
if (empty($_SESSION['attendance_review_csrf'])) $_SESSION['attendance_review_csrf'] = bin2hex(random_bytes(32));
?>
<section id="attendance-review" data-csrf="<?= htmlspecialchars($_SESSION['attendance_review_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <style>
        #attendance-review { color:#17233b; max-width:1250px; margin:auto; }
        #attendance-review h1 { margin-bottom:10px; }
        #attendance-review p { line-height:1.6; }
        #attendance-review .review-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:12px; margin:24px 0; }
        #attendance-review select, #attendance-review button, #attendance-review textarea { font:inherit; border:1px solid #c8d0dd; border-radius:6px; padding:10px 14px; }
        #attendance-review button { cursor:pointer; background:white; color:#17233b; }
        #attendance-review button:disabled { opacity:.5; cursor:default; }
        #attendance-review .review-card { background:#fff; border:1px solid #dce1e9; border-radius:10px; padding:22px; margin:18px 0; box-shadow:0 2px 6px #18233b08; overflow-wrap:anywhere; }
        #attendance-review .review-card h2 { font-size:1.15rem; margin:0 0 10px; }
        #attendance-review .review-details { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:14px; margin:18px 0; }
        #attendance-review dt { color:#58657b; font-size:.85rem; margin-bottom:5px; }
        #attendance-review dd { margin:0; overflow-wrap:anywhere; }
        #attendance-review .review-state { display:inline-block; border-radius:20px; padding:5px 12px; background:#fff2cc; }
        #attendance-review .review-state.approved { background:#daf2e1; }
        #attendance-review .review-state.rejected { background:#fde1e1; }
        #attendance-review .review-signature { display:block; max-width:100%; width:450px; border:1px solid #dce1e9; background:white; margin:12px 0; }
        #attendance-review .review-photo { display:inline-block; width:200px; max-height:180px; object-fit:contain; margin:10px; }
        #attendance-review textarea { display:block; width:100%; box-sizing:border-box; min-height:70px; margin:8px 0 12px; }
        #attendance-review .review-approve { color:white; background:#234b91; border-color:#234b91; margin-right:12px; }
        #attendance-review .review-reject { color:#a42525; }
        #attendance-review [role=alert] { color:#a42525; margin:12px 0; }
    </style>
    <h1>Attendance Approval</h1>
    <p>Review each monitoring submission and the teacher's e-signature. The recorded mark becomes final only when you approve it. Rejected records remain available with your reason.</p>
    <div class="review-toolbar">
        <label for="attendance-review-filter">Approval status</label>
        <select id="attendance-review-filter"><option value="pending">Pending approval</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="all">All submissions</option></select>
        <button type="button" data-review-refresh>Refresh</button>
        <span>Includes active and archived attendance</span>
    </div>
    <p data-review-message role="status" aria-live="polite"></p>
    <div data-review-records></div>
    <div class="review-toolbar">
        <button type="button" data-review-previous disabled>Previous</button>
        <span data-review-page>Page 1</span>
        <button type="button" data-review-next disabled>Next</button>
    </div>
</section>
