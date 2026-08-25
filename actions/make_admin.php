<?php
require_once '../init_session.php';
require_once '../config.php';
require_once '../notifications_helper.php';
require_once __DIR__ . '/../helpers/chat_system.php';

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
    echo json_encode(['error' => 'You cannot change your own role']);
    exit;
}

// Check if current user is owner
$roleCheck = $conn->prepare("
    SELECT member_role
    FROM chat_conversation_members
    WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL
");
$roleCheck->bind_param("ii", $conversation_id, $current_user_id);
$roleCheck->execute();
$currentRole = $roleCheck->get_result()->fetch_assoc();
$roleCheck->close();

if (!$currentRole || $currentRole['member_role'] !== 'owner') {
    echo json_encode(['error' => 'Only the channel owner can make admins']);
    exit;
}

// Get target's role
$targetCheck = $conn->prepare("
    SELECT member_role
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

if ($target['member_role'] === 'owner') {
    echo json_encode(['error' => 'Cannot change the owner\'s role']);
    exit;
}

// Toggle: if admin -> member, else member -> admin
$newRole = ($target['member_role'] === 'admin') ? 'member' : 'admin';
$action = ($newRole === 'admin') ? 'made admin' : 'removed admin';

// Update role
$update = $conn->prepare("
    UPDATE chat_conversation_members
    SET member_role = ?
    WHERE conversation_id = ? AND user_id = ?
");
$update->bind_param("sii", $newRole, $conversation_id, $target_user_id);
$success = $update->execute();
$update->close();

if (!$success) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

// Get names
$targetName = $conn->query("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = $target_user_id")->fetch_assoc()['name'] ?? 'User';
$ownerName = $conn->query("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = $current_user_id")->fetch_assoc()['name'] ?? 'Owner';
$channelName = $conn->query("SELECT name FROM chat_conversations WHERE id = $conversation_id")->fetch_assoc()['name'] ?? 'channel';

// System message
$content = "$targetName was $action by $ownerName.";
insertSystemMessage($conn, $conversation_id, $content);

// Notification to the target user
$notifTitle = ucfirst($action) . " in Channel";
$notifMessage = "You were $action in <strong>#{$channelName}</strong> by {$ownerName}.";
createNotification($target_user_id, 'system', $notifTitle, $notifMessage, 'message.php');

echo json_encode(['success' => true, 'message' => "User $action successfully"]);
?>