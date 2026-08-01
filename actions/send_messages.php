<?php
error_reporting(0);
require_once '../config.php';
require_once '../init_session.php';
// require_once '../notifications_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$conversation_id || empty($content)) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Verify user is a member
$check = $conn->prepare("SELECT id FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$check->close();

// Insert message
$stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, content, message_type) VALUES (?, ?, ?, 'text')");
$stmt->bind_param("iis", $conversation_id, $user_id, $content);
if (!$stmt->execute()) {
    echo json_encode(['error' => 'Failed to send message']);
    exit;
}
$message_id = $conn->insert_id;
$stmt->close();

// Update conversation updated_at
$update = $conn->prepare("UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?");
$update->bind_param("i", $conversation_id);
$update->execute();
$update->close();

// Get sender info
$userStmt = $conn->prepare("SELECT fname, lname FROM users_tbl WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$sender_name = $user['fname'] . ' ' . $user['lname'];

// Return success (NO extra output after this)
echo json_encode([
    'success' => true,
    'message' => [
        'id' => $message_id,
        'sender_id' => $user_id,
        'sender_name' => $sender_name,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s'),
        'is_self' => true
    ]
]);
// No closing PHP tag to avoid accidental whitespace