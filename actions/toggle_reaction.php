<?php
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$message_id = (int)($_POST['message_id'] ?? 0);
$reaction = trim($_POST['reaction'] ?? '');

if (!$message_id || empty($reaction)) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Check if message exists and user is a member of the conversation
$msgCheck = $conn->prepare("
    SELECT m.id, c.id as conv_id
    FROM chat_messages m
    JOIN chat_conversation_members cm ON m.conversation_id = cm.conversation_id
    WHERE m.id = ? AND cm.user_id = ? AND cm.left_at IS NULL
");
$msgCheck->bind_param("ii", $message_id, $user_id);
$msgCheck->execute();
$msg = $msgCheck->get_result()->fetch_assoc();
$msgCheck->close();

if (!$msg) {
    echo json_encode(['error' => 'Message not found or access denied']);
    exit;
}

// Check if user already reacted with this reaction
$check = $conn->prepare("SELECT id FROM chat_message_reactions WHERE message_id = ? AND user_id = ? AND reaction = ?");
$check->bind_param("iis", $message_id, $user_id, $reaction);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    // Remove reaction
    $delete = $conn->prepare("DELETE FROM chat_message_reactions WHERE id = ?");
    $delete->bind_param("i", $existing['id']);
    $deleted = $delete->execute();
    $delete->close();
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    // Add reaction
    $insert = $conn->prepare("INSERT INTO chat_message_reactions (message_id, user_id, reaction) VALUES (?, ?, ?)");
    $insert->bind_param("iis", $message_id, $user_id, $reaction);
    $inserted = $insert->execute();
    $insert->close();
    echo json_encode(['success' => true, 'action' => 'added']);
}
?>