<?php
require_once '../init_session.php';
require_once '../config.php';
require_once __DIR__ . '/../helpers/chat_system.php';

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

// Check if it's a channel (not DM)
$convCheck = $conn->prepare("SELECT type FROM chat_conversations WHERE id = ?");
$convCheck->bind_param("i", $conversation_id);
$convCheck->execute();
$conv = $convCheck->get_result()->fetch_assoc();
$convCheck->close();

if (!$conv || $conv['type'] !== 'channel') {
    echo json_encode(['error' => 'Cannot leave a direct message']);
    exit;
}

// Check if user is a member and not the only owner? We allow anyone to leave.
$check = $conn->prepare("SELECT id, member_role FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
$member = $check->get_result()->fetch_assoc();
$check->close();

if (!$member) {
    echo json_encode(['error' => 'Not a member']);
    exit;
}

// Set left_at
$update = $conn->prepare("UPDATE chat_conversation_members SET left_at = NOW() WHERE conversation_id = ? AND user_id = ?");
$update->bind_param("ii", $conversation_id, $user_id);
$update->execute();
$update->close();

$userName = $conn->query("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = $user_id")->fetch_assoc()['name'] ?? 'User';
$content = "$userName has left the channel.";
insertSystemMessage($conn, $conversation_id, $content);

echo json_encode([
    'success' => true,
    'message' => 'You have left the channel'
]);
$conn->close();
?>