<?php
require_once '../init_session.php';
require_once '../config.php';
require_once '../notifications_helper.php';
require_once '../log_activity.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$target_user_id = (int)($_POST['user_id'] ?? 0);

if (!$conversation_id || !$target_user_id) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

if ($target_user_id == $current_user_id) {
    echo json_encode(['error' => 'You cannot kick yourself']);
    exit;
}

// Check if current user is owner or admin
$roleCheck = $conn->prepare("
    SELECT member_role
    FROM chat_conversation_members
    WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
");

$roleCheck->bind_param("ii", $conversation_id, $current_user_id);
$roleCheck->execute();
$currentRole = $roleCheck->get_result()->fetch_assoc();
$roleCheck->close();

if (!$currentRole || !in_array($currentRole['member_role'], ['owner', 'admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get target user's role (can't kick owner or admins if you're admin)
$targetCheck = $conn->prepare("
    SELECT member_role, user_id
    FROM chat_conversation_members
    WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
");
$targetCheck->bind_param("ii", $conversation_id, $target_user_id);
$targetCheck->execute();
$target = $targetCheck->get_result()->fetch_assoc();
$targetCheck->close();

if (!$target) {
    echo json_encode(['error' => 'User not found in this channel']);
    exit;
}

// Cannot kick owner, and admin cannot kick another admin
if ($target['member_role'] === 'owner') {
    echo json_encode(['error' => 'Cannot kick the channel owner']);
    exit;
}
if ($currentRole['member_role'] === 'admin' && $target['member_role'] === 'admin') {
    echo json_encode(['error' => 'Cannot kick another admin']);
    exit;
}

// Kick user
$kick = $conn->prepare("
    UPDATE chat_conversation_members
    SET left_at = NOW(), kicked_by = ?
    WHERE conversation_id = ? AND user_id = ?
");
$kick->bind_param("iii", $current_user_id, $conversation_id, $target_user_id);
$kick->execute();
$kick->close();

// Get channel name for notification
$channelName = $conn->query("SELECT name FROM chat_conversations WHERE id = $conversation_id")->fetch_assoc()['name'] ?? 'channel';

// Get kicker name
$kickerName  = $conn->query("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = $current_user_id")->fetch_assoc()['name'] ?? 'Admin';

// Send notification to kicked user
$notifTitle = "Kicked from Channel";
$notifMessage = "You have been kicked from <strong#{$channelName}</strong> by <strong>{$kickerName}</strong>.";
createNotification($target_user_id, 'system', $notifTitle, $notifMessage, null);

// Log activity
$currentUser = $conn->query("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = $current_user_id")->fetch_assoc();
logActivity($target_user_id, 'system', $conversation_id, "Kicked from#{$channelName}", 'kicked', "Kicked by {$currentUser['name']}");

echo json_encode(['success' => true, 'message' => 'User kicked successfully']);