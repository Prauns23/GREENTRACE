<?php
require_once '../init_session.php';
require_once '../config.php';

// Ensure MySQL returns correct timezone
$conn->query("SET time_zone = '+08:00'");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch updated channels
$channelQuery = "
    SELECT 
        c.id, 
        c.name,
        cm.is_muted,
        (
            SELECT COUNT(*) 
            FROM chat_messages m 
            WHERE m.conversation_id = c.id 
              AND m.sender_id != ? 
              AND NOT EXISTS (
                  SELECT 1 FROM chat_message_reads r 
                  WHERE r.message_id = m.id 
                    AND r.user_id = ?
              )
        ) as unread_count,
        (
            SELECT content 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT created_at 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message_time,
        (
            SELECT CONCAT(u.fname, ' ', u.lname) 
            FROM chat_messages m
            LEFT JOIN users_tbl u ON m.sender_id = u.id
            WHERE m.conversation_id = c.id 
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_sender_name,
        (
            SELECT sender_id 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_sender_id
    FROM chat_conversations c
    JOIN chat_conversation_members cm ON c.id = cm.conversation_id
    WHERE c.type = 'channel' 
      AND c.archived = 0 
      AND cm.user_id = ? 
      AND cm.left_at IS NULL
      AND cm.is_archived = 0
    ORDER BY c.name ASC
";

$stmt = $conn->prepare($channelQuery);
$stmt->bind_param("iii", $user_id, $user_id, $user_id); 
$stmt->execute();
$channels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch updated DMs with unread_count, is_muted, and sender info
$dmQuery = "
    SELECT 
        c.id,
        u.id as user_id,
        CONCAT(u.fname, ' ', u.lname) as name,
        cm.is_muted,
        (
            SELECT COUNT(*) 
            FROM chat_messages m 
            WHERE m.conversation_id = c.id 
              AND m.sender_id != ? 
              AND NOT EXISTS (
                  SELECT 1 FROM chat_message_reads r 
                  WHERE r.message_id = m.id 
                    AND r.user_id = ?
              )
        ) as unread_count,
        (
            SELECT content 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT created_at 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message_time,
        (
            SELECT CONCAT(u2.fname, ' ', u2.lname) 
            FROM chat_messages m
            LEFT JOIN users_tbl u2 ON m.sender_id = u2.id
            WHERE m.conversation_id = c.id 
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_sender_name,
        (
            SELECT sender_id 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_sender_id
    FROM chat_conversations c
    JOIN chat_conversation_members cm ON c.id = cm.conversation_id
    JOIN users_tbl u ON u.id = cm.user_id
    WHERE c.type = 'direct' 
      AND c.archived = 0
      AND cm.user_id != ?
      AND cm.left_at IS NULL
      AND EXISTS (
          SELECT 1 FROM chat_conversation_members cm2 
          WHERE cm2.conversation_id = c.id 
          AND cm2.user_id = ?
          AND cm2.left_at IS NULL
      )
    ORDER BY last_message_time DESC
";

$dmStmt = $conn->prepare($dmQuery);
$dmStmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$dmStmt->execute();
$dms = $dmStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dmStmt->close();

echo json_encode([
    'success' => true,
    'channels' => $channels,
    'dms' => $dms
]);