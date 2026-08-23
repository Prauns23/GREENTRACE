<?php
// Suppress all warnings/errors from being output (they will still go to the log)
error_reporting(0);
ini_set('display_errors', 0);

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
    SELECT m.id
    FROM chat_messages m
    JOIN chat_conversation_members cm ON m.conversation_id = cm.conversation_id
    WHERE m.id = ? AND cm.user_id = ? AND cm.left_at IS NULL
");
if (!$msgCheck) {
    echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
    exit;
}
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
if (!$check) {
    echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
    exit;
}
$check->bind_param("iis", $message_id, $user_id, $reaction);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    // Remove reaction
    $delete = $conn->prepare("DELETE FROM chat_message_reactions WHERE id = ?");
    if (!$delete) {
        echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
        exit;
    }
    $delete->bind_param("i", $existing['id']);
    $deleted = $delete->execute();
    $delete->close();
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    // Add reaction
    $insert = $conn->prepare("INSERT INTO chat_message_reactions (message_id, user_id, reaction) VALUES (?, ?, ?)");
    if (!$insert) {
        echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
        exit;
    }
    $insert->bind_param("iis", $message_id, $user_id, $reaction);
    $inserted = $insert->execute();
    $insert->close();
    echo json_encode(['success' => true, 'action' => 'added']);
}
?>