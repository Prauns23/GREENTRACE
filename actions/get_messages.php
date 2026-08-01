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
$before = $_GET['before'] ?? null; //Time Stamp (2026-06-29 10:00:00)

if (!$conversation_id) {
    echo json_encode(['error' => 'Invalid conversation']);
    exit;
}

// Verify user is a member of this conversation
$check = $conn->prepare("SELECT id FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii",  $conversation_id, $user_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$check->close();

// Build quert

$sql = "SELECT m.*, u.fname, u.lname
        FROM chat_messages m
        LEFT JOIN users_tbl u ON m.sender_id = u.id
        WHERE m.conversation_id = ? AND m.archived = 0
        ";
$params = [$conversation_id];
$types = "i";

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

echo json_encode(['success' => true, 'messages' => $messages]);
?>