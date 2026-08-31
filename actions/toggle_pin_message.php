<?php
error_reporting(0);
require_once '../config.php';
require_once '../init_session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$message_id = (int) ($_POST['message_id'] ?? 0);

if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid message']);
    exit;
}

$messageStmt = $conn->prepare("
    SELECT m.sender_id, m.is_pinned, cm.member_role
    FROM chat_messages m
    JOIN chat_conversation_members cm
      ON cm.conversation_id = m.conversation_id
     AND cm.user_id = ?
     AND cm.left_at IS NULL
    WHERE m.id = ?
      AND m.message_type = 'text'
      AND m.archived = 0
    LIMIT 1
");
$messageStmt->bind_param('ii', $user_id, $message_id);
$messageStmt->execute();
$message = $messageStmt->get_result()->fetch_assoc();
$messageStmt->close();

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

$canPin = (int) $message['sender_id'] === $user_id
    || in_array($message['member_role'], ['owner', 'admin'], true);

if (!$canPin) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to pin this message']);
    exit;
}

$isPinned = (int) $message['is_pinned'] === 1 ? 0 : 1;
$updateStmt = $conn->prepare("UPDATE chat_messages SET is_pinned = ? WHERE id = ?");
$updateStmt->bind_param('ii', $isPinned, $message_id);
$updated = $updateStmt->execute();
$updateStmt->close();

if (!$updated) {
    echo json_encode(['success' => false, 'error' => 'Failed to update the pinned message']);
    exit;
}

echo json_encode([
    'success' => true,
    'message_id' => $message_id,
    'is_pinned' => $isPinned
]);
