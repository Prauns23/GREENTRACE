<?php
require_once '../init_session.php';
require_once '../config.php';
require_once __DIR__ . '/../helpers/notifications_helper.php';

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

$check = $conn->prepare("
    SELECT c.type
    FROM chat_conversations c
    JOIN chat_conversation_members cm ON cm.conversation_id = c.id
    WHERE c.id = ? AND cm.user_id = ? AND cm.left_at IS NULL
");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
$conversation = $check->get_result()->fetch_assoc();
$check->close();

if (!$conversation) {
    echo json_encode(['error' => 'Conversation not found']);
    exit;
}

if ($conversation['type'] !== 'direct') {
    echo json_encode(['error' => 'Only direct conversations can be deleted']);
    exit;
}

$otherStmt = $conn->prepare("SELECT user_id FROM chat_conversation_members WHERE conversation_id = ? AND user_id != ?");
$otherStmt->bind_param("ii", $conversation_id, $user_id);
$otherStmt->execute();
$others = $otherStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$otherStmt->close();

$nameStmt = $conn->prepare("SELECT CONCAT(fname, ' ', lname) AS name FROM users_tbl WHERE id = ?");
$nameStmt->bind_param("i", $user_id);
$nameStmt->execute();
$userName = $nameStmt->get_result()->fetch_assoc()['name'] ?? 'Someone';
$nameStmt->close();

$delete = $conn->prepare("DELETE FROM chat_conversations WHERE id = ?");
$delete->bind_param("i", $conversation_id);
$success = $delete->execute();
$delete->close();

if (!$success) {
    echo json_encode(['error' => 'Failed to delete conversation']);
    exit;
}

foreach ($others as $other) {
    createNotification(
        $other['user_id'],
        'message',
        'Conversation Deleted',
        "{$userName} has deleted the conversation.",
        'message.php'
    );
}

echo json_encode([
    'success' => true,
    'message' => 'Conversation deleted for all participants'
]);
$conn->close();
?>
