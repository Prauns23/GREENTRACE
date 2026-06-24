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

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
    // Log activity only if the report belongs to a logged‑in user (not anonymous)
    if ($user_id !== null) {
        logActivity($user_id, 'report', $report_id, $issue_type, $new_status, "Your report <strong>$issue_type</strong> has been <strong>$new_status</strong>.");

        // Send notification (report status updated)
        $notifTitle = "Report Update";
        $notifMessage = "Your report \"<strong>$issue_type</strong>\" has is <strong>$new_status</strong>.";
        $link = "forestmap.php";
        createNotification($user_id, 'report', $notifTitle, $notifMessage, $link);
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
$conn->close();
?>