<?php
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$conversation_id = (int)($_POST['conversation_Id'] ?? 0);
$target_user_id = (int)($_POST['user_id'] ?? 0);

if (!$conversation_id || !$target_user_id) {
    echo json_encode(['error' => 'Invalid parameters!']);
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

// Get target's role
$targetCheck = $conn->prepare("
    SELECT member_role, is_muted_by_admin
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

// Cannot mute owner, and admin cannot mute another admin
if ($target['member_role'] === 'owner') {
    echo json_encode(['error' => 'Cannot mute the channel owner']);
    exit;
}
if ($currentRole['member_role'] === 'admin' && $target['member_role'] === 'admin') {
    echo json_encode(['error' => 'Cannot mute another admin']);
    exit;
}

// Toggle mute
$newMuted = $target['is_muted_by_admin'] ? 0 : 1;

$update = $conn->prepare("
    UPDATE chat_conversation_members
    SET is_muted_by_admin = ?
    WHERE conversation_id = ? AND user_id = ?
");
$update->bind_param("iii", $newMuted, $conversation_id, $target_user_id);
$update->execute();
$update->close();

echo json_encode([
    'success' => true,
    'mute' => (bool)$newMuted,
    'message' => $newMuted ? 'User has been muted' : 'User has been unmuted'
]);
?> 