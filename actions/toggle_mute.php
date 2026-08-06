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
$check = $conn->prepare("SELECT id, is_muted FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
$member = $check->get_result()->fetch_assoc();
$check->close();

if (!$member) {
    echo json_encode(['error' => 'Not a member']);
    exit;
}

$newMuted = $member['is_muted'] ? 0 : 1;

$update = $conn->prepare("UPDATE chat_conversation_members SET is_muted = ? WHERE conversation_id = ? AND user_id = ?");
$update->bind_param("iii", $newMuted, $conversation_id, $user_id);
$update->execute();

echo json_encode([
    'success' => true,
    'muted' => (bool)$newMuted,
    'message' => $newMuted ? 'Conversation muted' : 'Conversation unmuted'
]);
$update->close();
$conn->close();
?>