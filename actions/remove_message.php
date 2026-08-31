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
    SELECT m.id
    FROM chat_messages m
    JOIN chat_conversation_members cm
      ON cm.conversation_id = m.conversation_id
     AND cm.user_id = ?
     AND cm.left_at IS NULL
    WHERE m.id = ?
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

$archiveStmt = $conn->prepare("
    INSERT IGNORE INTO chat_message_user_archives (message_id, user_id, archived_at)
    VALUES (?, ?, NOW())
");
$archiveStmt->bind_param('ii', $message_id, $user_id);
$archived = $archiveStmt->execute();
$archiveStmt->close();

if (!$archived) {
    echo json_encode(['success' => false, 'error' => 'Failed to remove the message']);
    exit;
}

echo json_encode(['success' => true, 'message_id' => $message_id]);
