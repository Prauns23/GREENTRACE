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
$content = trim($_POST['content'] ?? '');

if (!$message_id || $content === '') {
    echo json_encode(['success' => false, 'error' => 'Message content cannot be empty']);
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
      AND m.created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)
    LIMIT 1
");
$messageStmt->bind_param('iii', $user_id, $message_id, $user_id);
$messageStmt->execute();
$message = $messageStmt->get_result()->fetch_assoc();
$messageStmt->close();

if (!$message) {
    echo json_encode([
        'success' => false,
        'error' => 'This message cannot be edited. It may be older than 60 minutes, unsent, or not yours.'
    ]);
    exit;
}

$updateStmt = $conn->prepare("
    UPDATE chat_messages
    SET content = ?, edited_at = NOW()
    WHERE id = ?
      AND sender_id = ?
      AND archived = 0
      AND created_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)
");
$updateStmt->bind_param('sii', $content, $message_id, $user_id);
$updateStmt->execute();
$updated = $updateStmt->affected_rows > 0;
$updateStmt->close();

if (!$updated) {
    echo json_encode(['success' => false, 'error' => 'The message was not changed']);
    exit;
}

$resultStmt = $conn->prepare("SELECT content, edited_at FROM chat_messages WHERE id = ?");
$resultStmt->bind_param('i', $message_id);
$resultStmt->execute();
$result = $resultStmt->get_result()->fetch_assoc();
$resultStmt->close();

publishConversationRealtimeEvent((int) $message['conversation_id'], 'message.edited', [
    'message_id' => $message_id,
]);

echo json_encode([
    'success' => true,
    'message' => [
        'id' => $message_id,
        'content' => $result['content'],
        'edited_at' => $result['edited_at'],
        'can_edit' => 1,
        'archived' => 0
    ]
]);
