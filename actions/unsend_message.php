<?php
error_reporting(0);
require_once '../config.php';
require_once '../init_session.php';
require_once __DIR__ . '/../helpers/realtime.php';

$conn->query("SET time_zone = '+08:00'");
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
    SELECT m.id, m.conversation_id
    FROM chat_messages m
    JOIN chat_conversation_members cm
      ON cm.conversation_id = m.conversation_id
     AND cm.user_id = ?
     AND cm.left_at IS NULL
    WHERE m.id = ?
      AND m.sender_id = ?
      AND m.message_type = 'text'
      AND m.archived = 0
    LIMIT 1
");
$messageStmt->bind_param('iii', $user_id, $message_id, $user_id);
$messageStmt->execute();
$message = $messageStmt->get_result()->fetch_assoc();
$messageStmt->close();

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'This message cannot be unsent']);
    exit;
}

$updateStmt = $conn->prepare("
    UPDATE chat_messages
    SET archived = 1, archived_at = NOW(), is_pinned = 0
    WHERE id = ? AND sender_id = ? AND archived = 0
");
$updateStmt->bind_param('ii', $message_id, $user_id);
$updateStmt->execute();
$updated = $updateStmt->affected_rows > 0;
$updateStmt->close();

if (!$updated) {
    echo json_encode(['success' => false, 'error' => 'The message could not be unsent']);
    exit;
}

$userStmt = $conn->prepare("SELECT fname, lname FROM users_tbl WHERE id = ?");
$userStmt->bind_param('i', $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$senderName = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));

publishConversationRealtimeEvent((int) $message['conversation_id'], 'message.unsent', [
    'message_id' => $message_id,
]);

echo json_encode([
    'success' => true,
    'message' => [
        'id' => $message_id,
        'content' => 'You have unsent a message',
        'sender_name' => $senderName,
        'archived' => 1,
        'archived_at' => date('Y-m-d H:i:s'),
        'is_pinned' => 0,
        'can_edit' => 0,
        'reactions' => []
    ]
]);
