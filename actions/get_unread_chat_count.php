<?php 
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Count messages that the user hasn't read in conversations they are a member of 

$sql = "SELECT COUNT(DISTINCT m.id) as total
        FROM chat_messages m
        JOIN chat_conversation_members cm ON m.conversation_id = cm.conversation_id 
        AND cm.user_id = ?
        AND cm.left_at IS NULL
        LEFT JOIN chat_message_reads r ON m.id = r.message_id AND r.user_id = ?
        WHERE r.id IS NULL
            AND m.sender_id != ?
            AND m.archived = 0
            AND m.message_type != 'system'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

echo json_encode(['unread' => $result['total'] ?? 0]);
?>