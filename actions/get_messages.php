<?php
require_once '../init_session.php';
require_once '../config.php';

// Ensure MySQL returns correct timezone
$conn->query("SET time_zone = '+08:00'");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$limit = (int)($_GET['limit'] ?? 6);
$before = $_GET['before'] ?? null;

if (!$conversation_id) {
    echo json_encode(['error' => 'Invalid conversation']);
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

// Build query with correct read status logic
$sql = "SELECT 
    m.*, 
    u.fname, 
    u.lname,
    CASE 
        WHEN m.sender_id is NULL THEN 1 -- system messages are always marked as read
        WHEN m.sender_id = ? THEN
            -- For messages I sent: check if any OTHER user has read it
            (SELECT COUNT(*) > 0 FROM chat_message_reads 
             WHERE message_id = m.id AND user_id != ?)
        ELSE
            -- For messages from others: check if I have read it
            (SELECT COUNT(*) > 0 FROM chat_message_reads 
             WHERE message_id = m.id AND user_id = ?)
    END as is_read,
    (SELECT COUNT(*) FROM chat_message_reads WHERE message_id = m.id) as read_count
FROM chat_messages m
LEFT JOIN users_tbl u ON m.sender_id = u.id
WHERE m.conversation_id = ? AND m.archived = 0
";
$params = [$user_id, $user_id, $user_id, $conversation_id];
$types = "iiii";

if ($before) {
    $sql .= " AND m.created_at < ?";
    $params[] = $before;
    $types .= "s";
}

$sql .= " ORDER BY m.created_at DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $row['sender_name'] = $row['sender_id'] ? ($row['fname'] . ' ' . $row['lname']) : 'System';
    $row['is_self'] = ($row['sender_id'] == $user_id);
    $messages[] = $row;
}
$stmt->close();

// Reverse order to display oldest first
$messages = array_reverse($messages);


// Get reactions for these messages
$messageIds = array_column($messages, 'id');
if (!empty($messageIds)) {
    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
    $reactStmt = $conn->prepare("SELECT message_id, user_id, reaction FROM chat_message_reactions WHERE message_id IN ($placeholders)");
    $reactStmt->bind_param(str_repeat('i', count($messageIds)), ...$messageIds);
    $reactStmt->execute();
    $reactResult = $reactStmt->get_result();
    $reactionsByMessage = [];
    while ($row = $reactResult->fetch_assoc()) {
        $reactionsByMessage[$row['message_id']][] = ['user_id' => (int)$row['user_id'], 'reaction' => $row['reaction']];
    }
    $reactStmt->close();
    // Attach to messages
    foreach ($messages as &$msg) {
        $msg['reactions'] = $reactionsByMessage[$msg['id']] ?? [];
    }
    unset($msg);
} else {
    foreach ($messages as &$msg) {
        $msg['reactions'] = [];
    }
    unset($msg);
}


echo json_encode(['success' => true, 'messages' => $messages]);