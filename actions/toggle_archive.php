<?php
require_once '../init_session.php';
require_once '../config.php';
require_once '../notifications_helper.php'; // <-- ADD THIS

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
$check = $conn->prepare("SELECT id, is_archived FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
$member = $check->get_result()->fetch_assoc();
$check->close();

if (!$member) {
    echo json_encode(['error' => 'Not a member']);
    exit;
}

// DMs are deleted for both participants; channel archives remain per-user.
$typeCheck = $conn->prepare("SELECT type FROM chat_conversations WHERE id = ?");
$typeCheck->bind_param("i", $conversation_id);
$typeCheck->execute();
$convType = $typeCheck->get_result()->fetch_assoc()['type'] ?? '';
$typeCheck->close();

$newArchived = $member['is_archived'] ? 0 : 1;

if ($convType === 'direct') {
    $newArchived = 1;
    $update = $conn->prepare("UPDATE chat_conversation_members SET is_archived = 1 WHERE conversation_id = ? AND left_at IS NULL");
    $update->bind_param("i", $conversation_id);
    $update->execute();
    $update->close();
} else {
    $update = $conn->prepare("UPDATE chat_conversation_members SET is_archived = ? WHERE conversation_id = ? AND user_id = ?");
    $update->bind_param("iii", $newArchived, $conversation_id, $user_id);
    $update->execute();
    $update->close();
}

// Notify the other participants when a DM is deleted.
if ($newArchived == 1 && $convType === 'direct') {
    // Get the other user(s) in this DM (exclude current user)
    $otherStmt = $conn->prepare("SELECT user_id FROM chat_conversation_members WHERE conversation_id = ? AND user_id != ? AND left_at IS NULL");
    $otherStmt->bind_param("ii", $conversation_id, $user_id);
    $otherStmt->execute();
    $others = $otherStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $otherStmt->close();

    // Get current user's name
    $nameStmt = $conn->prepare("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = ?");
    $nameStmt->bind_param("i", $user_id);
    $nameStmt->execute();
    $userName = $nameStmt->get_result()->fetch_assoc()['name'] ?? 'Someone';
    $nameStmt->close();

    foreach ($others as $other) {
        createNotification(
            $other['user_id'],
            'message',
            'Conversation Deleted',
            "{$userName} has deleted the conversation.",
            'message.php'
        );
    }
}

echo json_encode([
    'success' => true,
    'archived' => (bool)$newArchived,
    'message' => $convType === 'direct'
        ? 'Conversation deleted for all participants'
        : ($newArchived ? 'Conversation archived' : 'Conversation unarchived')
]);
$conn->close();
?>