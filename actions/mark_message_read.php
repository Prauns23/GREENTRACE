<?php
require_once '../init_session.php';
require_once '../config.php';
require_once __DIR__ . '/../helpers/realtime.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$message_ids = json_decode($_POST['message_ids'] ?? '[]', true);
$mark_all = ($_POST['mark_all'] ?? '') === '1';

if (!$conversation_id || (!$mark_all && empty($message_ids))) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Verify user is a member of this conversation
$check = $conn->prepare("SELECT id FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$check->close();

if ($mark_all) {
    $unreadStmt = $conn->prepare("
        SELECT m.id
        FROM chat_messages m
        WHERE m.conversation_id = ?
          AND m.sender_id != ?
          AND m.message_type != 'system'
          AND m.archived = 0
          AND NOT EXISTS (
              SELECT 1 FROM chat_message_reads r
              WHERE r.message_id = m.id AND r.user_id = ?
          )
    ");
    $unreadStmt->bind_param("iii", $conversation_id, $user_id, $user_id);
    $unreadStmt->execute();
    $message_ids = array_column($unreadStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $unreadStmt->close();
}

if (empty($message_ids)) {
    echo json_encode(['success' => true, 'message' => 'All messages already read']);
    exit;
}

// Build bulk insert for read receipts
$placeholders = implode(',', array_fill(0, count($message_ids), '?'));

// First, check which messages are already read
$checkRead = $conn->prepare("SELECT message_id FROM chat_message_reads WHERE user_id = ? AND message_id IN ($placeholders)");
$types = str_repeat('i', count($message_ids));
$checkRead->bind_param('i' . $types, ...array_merge([$user_id], $message_ids));
$checkRead->execute();
$alreadyRead = $checkRead->get_result()->fetch_all(MYSQLI_ASSOC);
$alreadyReadIds = array_column($alreadyRead, 'message_id');
$checkRead->close();

// Filter out already-read messages
$newMessageIds = array_diff($message_ids, $alreadyReadIds);

if (empty($newMessageIds)) {
    echo json_encode(['success' => true, 'message' => 'All messages already read']);
    exit;
}

// Insert read records for new messages
$values = [];
$bindParams = [];
foreach ($newMessageIds as $id) {
    $values[] = '(?, ?, NOW())';
    $bindParams[] = $id;
    $bindParams[] = $user_id;
}

$sql = "INSERT INTO chat_message_reads (message_id, user_id, read_at) VALUES " . implode(', ', $values);
$stmt = $conn->prepare($sql);

$types = str_repeat('ii', count($newMessageIds));
$stmt->bind_param($types, ...$bindParams);
$stmt->execute();

publishConversationRealtimeEvent($conversation_id, 'receipt.updated');

echo json_encode(['success' => true]);
$stmt->close();
$conn->close();
?>
