<?php
require_once '../init_session.php';
require_once '../config.php';
require_once __DIR__ . '/../log_activity.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
$action = $_POST['action'] ?? '';

if (!$activity_id || !in_array($action, ['cancel', 'approve', 'reject'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// For approve/reject, only admin can do it
if (($action === 'approve' || $action === 'reject') && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Fetch activity title (needed for logging)
$actStmt = $conn->prepare("SELECT title FROM activities WHERE id = ?");
$actStmt->bind_param("i", $activity_id);
$actStmt->execute();
$actTitle = $actStmt->get_result()->fetch_assoc()['title'] ?? 'the activity';
$actStmt->close();

// Get the latest application for this user+activity
$stmt = $conn->prepare("SELECT id, status FROM volunteer_applications WHERE user_id = ? AND activity_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->bind_param("ii", $user_id, $activity_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
if (!$app) {
    echo json_encode(['error' => 'No application found']);
    exit;
}
$app_id = $app['id'];
$current_status = $app['status'];

$conn->begin_transaction();
try {
    $new_status = '';
    if ($action === 'cancel') {
        if ($current_status !== 'approved') {
            throw new Exception('Only approved applications can be cancelled.');
        }
        $update = $conn->prepare("UPDATE volunteer_applications SET status = 'cancelled' WHERE id = ?");
        $update->bind_param("i", $app_id);
        $update->execute();
        // Decrement participants count
        $dec = $conn->prepare("UPDATE activities SET participants_count = participants_count - 1 WHERE id = ? AND participants_count > 0");
        $dec->bind_param("i", $activity_id);
        $dec->execute();
        $new_status = 'cancelled';
    } elseif ($action === 'approve') {
        if ($current_status !== 'pending') {
            throw new Exception('Only pending applications can be approved.');
        }
        // Check capacity before approving
        $capStmt = $conn->prepare("SELECT capacity, participants_count FROM activities WHERE id = ?");
        $capStmt->bind_param("i", $activity_id);
        $capStmt->execute();
        $activity = $capStmt->get_result()->fetch_assoc();
        if ($activity && $activity['participants_count'] >= $activity['capacity']) {
            throw new Exception('Activity is already full. Cannot approve more volunteers.');
        }
        $capStmt->close();

        $update = $conn->prepare("UPDATE volunteer_applications SET status = 'approved' WHERE id = ?");
        $update->bind_param("i", $app_id);
        $update->execute();
        // Increment participants count
        $inc = $conn->prepare("UPDATE activities SET participants_count = participants_count + 1 WHERE id = ?");
        $inc->bind_param("i", $activity_id);
        $inc->execute();
        $new_status = 'approved';
    } elseif ($action === 'reject') {
        if ($current_status !== 'pending') {
            throw new Exception('Only pending applications can be rejected.');
        }
        $update = $conn->prepare("UPDATE volunteer_applications SET status = 'rejected' WHERE id = ?");
        $update->bind_param("i", $app_id);
        $update->execute();
        // No count change
        $new_status = 'rejected';
    }
    $conn->commit();

    // Log activity after successful commit
    logActivity($user_id, 'application', $app_id, $actTitle, $new_status, "Your application for <strong>$actTitle</strong> has been <strong>$new_status.</strong>");

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}
?>