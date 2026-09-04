<?php
error_reporting(0);
require_once '../init_session.php';
require_once '../config.php';

$conn->query("SET time_zone = '+08:00'");
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$conversationId = (int) ($_GET['conversation_id'] ?? 0);

if (!$conversationId) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation']);
    exit;
}

$memberStmt = $conn->prepare("
    SELECT member_role
    FROM chat_conversation_members
    WHERE conversation_id = ?
      AND user_id = ?
      AND left_at IS NULL
    LIMIT 1
");
$memberStmt->bind_param('ii', $conversationId, $userId);
$memberStmt->execute();
$member = $memberStmt->get_result()->fetch_assoc();
$memberStmt->close();

if (!$member) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$pinnedStmt = $conn->prepare("
    SELECT
        m.id,
        m.sender_id,
        m.content,
        m.created_at,
        m.edited_at,
        CONCAT(u.fname, ' ', u.lname) AS sender_name
    FROM chat_messages m
    LEFT JOIN users_tbl u ON u.id = m.sender_id
    WHERE m.conversation_id = ?
      AND m.is_pinned = 1
      AND m.archived = 0
      AND m.message_type = 'text'
      AND NOT EXISTS (
          SELECT 1
          FROM chat_message_user_archives message_archive
          WHERE message_archive.message_id = m.id
            AND message_archive.user_id = ?
      )
    ORDER BY m.created_at DESC, m.id DESC
");
$pinnedStmt->bind_param('ii', $conversationId, $userId);
$pinnedStmt->execute();
$result = $pinnedStmt->get_result();

$canModeratePins = in_array($member['member_role'], ['owner', 'admin'], true);
$messages = [];
while ($row = $result->fetch_assoc()) {
    $isSelf = (int) $row['sender_id'] === $userId;
    $messages[] = [
        'id' => (int) $row['id'],
        'sender_id' => (int) $row['sender_id'],
        'sender_name' => trim($row['sender_name'] ?? '') ?: 'Unknown user',
        'content' => $row['content'] ?? '',
        'created_at' => $row['created_at'],
        'edited_at' => $row['edited_at'],
        'is_self' => $isSelf,
        'can_unpin' => $isSelf || $canModeratePins,
    ];
}
$pinnedStmt->close();

echo json_encode(['success' => true, 'messages' => $messages]);
