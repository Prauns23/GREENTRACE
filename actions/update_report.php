<?php
require_once '../init_session.php';
require_once '../config.php';
require_once __DIR__ . '/../log_activity.php';
require_once '../notifications_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$report_id = (int)($_POST['report_id'] ?? 0);
$new_status = $_POST['status'] ?? '';

if (!$report_id || !in_array($new_status, ['pending', 'reviewed', 'resolved', 'dismissed'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Fetch the report's user_id and issue_type for logging
$infoStmt = $conn->prepare("SELECT user_id, issue_type FROM reports WHERE id = ?");
$infoStmt->bind_param("i", $report_id);
$infoStmt->execute();
$reportInfo = $infoStmt->get_result()->fetch_assoc();
$infoStmt->close();

if (!$reportInfo) {
    echo json_encode(['error' => 'Report not found']);
    exit;
}

$user_id = $reportInfo['user_id'];
$issue_type = $reportInfo['issue_type'];

$stmt = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $report_id);

if ($stmt->execute()) {
    $recipientUserId = !empty($user_id) ? (int)$user_id : null;

    // Log activity and send a notification only if the report is linked to a real user account.
    if ($recipientUserId !== null) {
        logActivity($recipientUserId, 'report', $report_id, $issue_type, $new_status, "Your report <strong>$issue_type</strong> has been <strong>$new_status</strong>.");

        $notifTitle = "Report Update";
        $notifMessage = "Your report \"<strong>$issue_type</strong>\" has been <strong>$new_status</strong>.";
        $link = "forestmap.php";

        if (!createNotification($recipientUserId, 'report', $notifTitle, $notifMessage, $link)) {
            error_log("Failed to create report notification for report_id=$report_id user_id=$recipientUserId");
        }
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
$conn->close();
