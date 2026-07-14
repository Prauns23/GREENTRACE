<?php
require_once '../init_session.php';
require_once '../config.php';
require_once __DIR__ . '/../log_activity.php';
require_once __DIR__ . '/../notifications_helper.php';

header('Content-Type: application/json');

// CSRF Validation 
$headers = getallheaders();
$csrf_token = $_POST['csrf_token'] ?? ($headers['X-CSRF-Token'] ?? '');
if (!verifyCSRFToken($csrf_token)) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';
$application_id = (int)($_POST['application_id'] ?? 0);

if (!$application_id || !in_array($action, ['approve', 'reject', 'cancel'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Fetch application details
$appStmt = $conn->prepare("SELECT user_id, activity_id, status FROM volunteer_applications WHERE id = ?");
$appStmt->bind_param("i", $application_id);
$appStmt->execute();
$app = $appStmt->get_result()->fetch_assoc();
$appStmt->close();

if (!$app) {
    echo json_encode(['error' => 'Application not found']);
    exit;
}

$applicant_id = $app['user_id'];
$activity_id = $app['activity_id'];
$current_status = $app['status'];

// Check permissions
if ($action === 'approve' || $action === 'reject') {
    // Admin only
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    if ($current_status !== 'pending') {
        echo json_encode(['error' => 'Application is no longer pending']);
        exit;
    }
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
} elseif ($action === 'cancel') {
    // User can cancel their own pending or approved application
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $applicant_id) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    if (!in_array($current_status, ['pending', 'approved'])) {
        echo json_encode(['error' => 'Cannot cancel this application']);
        exit;
    }
    $new_status = 'cancelled';
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update status
    $update = $conn->prepare("UPDATE volunteer_applications SET status = ? WHERE id = ?");
    $update->bind_param("si", $new_status, $application_id);
    $update->execute();
    $update->close();

    // If cancelling an approved application, decrement participants count
    if ($action === 'cancel' && $current_status === 'approved') {
        $dec = $conn->prepare("UPDATE activities SET participants_count = participants_count - 1 WHERE id = ? AND participants_count > 0");
        $dec->bind_param("i", $activity_id);
        $dec->execute();
        $dec->close();
    }

    // If approving, increment participants
    if ($action === 'approve') {
        $inc = $conn->prepare("UPDATE activities SET participants_count = participants_count + 1 WHERE id = ?");
        $inc->bind_param("i", $activity_id);
        $inc->execute();
        $inc->close();
    }

    $conn->commit();

    // Fetch activity title for notifications
    $actStmt = $conn->prepare("SELECT title FROM activities WHERE id = ?");
    $actStmt->bind_param("i", $activity_id);
    $actStmt->execute();
    $actTitle = $actStmt->get_result()->fetch_assoc()['title'] ?? 'the activity';
    $actStmt->close();

    // Log activity for the applicant
    logActivity(
        $applicant_id,
        'application',
        $application_id,
        $actTitle,
        $new_status,
        "Your application for <strong>$actTitle</strong> has been <strong>$new_status</strong>."
    );

    // Send notification to the user
    $userNotifTitle = ucfirst($new_status) . ' Application';
    $userNotifMessage = "Your application for <strong>$actTitle</strong> has been <strong>$new_status</strong>.";
    $userLink = "activities.php?open_activity={$activity_id}";
    createNotification($applicant_id, 'application', $userNotifTitle, $userNotifMessage, $userLink);

    // If admin action, notify all admins as well
    if ($action === 'approve' || $action === 'reject') {
        $adminStmt = $conn->prepare("SELECT id FROM users_tbl WHERE role = 'admin' AND archived = 0");
        $adminStmt->execute();
        $admins = $adminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $adminStmt->close();

        $adminNotifTitle = "Application $new_status";
        $adminNotifMessage = "An application for <strong>$actTitle</strong> by user (ID: $applicant_id) has been <strong>$new_status</strong>.";
        $adminLink = "admin/application_activity.php";
        foreach ($admins as $admin) {
            createNotification($admin['id'], 'application', $adminNotifTitle, $adminNotifMessage, $adminLink);
        }
    }

    // Prepare success message for redirect
    $message = ($action === 'cancel') ? 'Application cancelled successfully.' : "Application $new_status.";
    echo json_encode(['success' => true, 'message' => $message]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}
