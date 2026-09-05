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

// Check if the conversation is active and the user is still a member.
$check = $conn->prepare("
    SELECT cm.id, cm.is_archived
    FROM chat_conversation_members cm
    JOIN chat_conversations c ON c.id = cm.conversation_id
    WHERE cm.conversation_id = ?
      AND cm.user_id = ?
      AND cm.left_at IS NULL
      AND c.archived = 0
");
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
