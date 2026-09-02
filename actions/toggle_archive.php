<?php
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = (int)($_POST['conversation_id'] ?? 0);

if (!$conversation_id) {
    echo json_encode(['error' => 'Invalid conversation']);
    exit;
}

// Check if user is a member
$check = $conn->prepare("SELECT id, is_archived FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
$member = $check->get_result()->fetch_assoc();
$check->close();

if (!$member) {
    echo json_encode(['error' => 'Not a member']);
    exit;
}

// Archive state is per-user for both channels and direct messages.
$newArchived = $member['is_archived'] ? 0 : 1;

$update = $conn->prepare("UPDATE chat_conversation_members SET is_archived = ? WHERE conversation_id = ? AND user_id = ?");
$update->bind_param("iii", $newArchived, $conversation_id, $user_id);
$update->execute();
$update->close();

echo json_encode([
    'success' => true,
    'archived' => (bool)$newArchived,
    'message' => $newArchived ? 'Conversation archived' : 'Conversation unarchived'
]);
$conn->close();
?>
