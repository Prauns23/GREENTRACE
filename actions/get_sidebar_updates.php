<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
              AND (m.message_type != 'system' OR (m.message_type = 'system' AND m.content NOT LIKE '% was muted by %' AND m.content NOT LIKE '% was unmuted by %'))
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
              AND (message_type != 'system' OR (message_type = 'system' AND content NOT LIKE '% was muted by %' AND content NOT LIKE '% was unmuted by %'))
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT created_at 
            FROM chat_messages 
            WHERE conversation_id = c.id 
              AND (message_type != 'system' OR (message_type = 'system' AND content NOT LIKE '% was muted by %' AND content NOT LIKE '% was unmuted by %'))
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message_time,
        (
            SELECT COALESCE(CONCAT(u.fname, ' ', u.lname), 'System')
            FROM chat_messages m
            LEFT JOIN users_tbl u ON m.sender_id = u.id
            WHERE m.conversation_id = c.id 
              AND (m.message_type != 'system' OR (m.message_type = 'system' AND m.content NOT LIKE '% was muted by %' AND m.content NOT LIKE '% was unmuted by %'))
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_sender_name,
        (
            SELECT COALESCE(m.sender_id, 0) 
            FROM chat_messages m
            WHERE conversation_id = c.id 
              AND (message_type != 'system' OR (message_type = 'system' AND content NOT LIKE '% was muted by %' AND content NOT LIKE '% was unmuted by %'))
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

// Fetch updated DMs with unread_count, is_muted (current user), and sender info
$dmQuery = "
    SELECT 
        c.id,
        u_other.id as user_id,
        CONCAT(u_other.fname, ' ', u_other.lname) as name,
        cm_current.is_muted,
        (
            SELECT COUNT(*) 
            FROM chat_messages m 
            WHERE m.conversation_id = c.id 
              AND m.sender_id != ? 
              AND (m.message_type != 'system' OR (m.message_type = 'system' AND m.content NOT LIKE '% was muted by %' AND m.content NOT LIKE '% was unmuted by %'))
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
              AND (message_type != 'system' OR (message_type = 'system' AND content NOT LIKE '% was muted by %' AND content NOT LIKE '% was unmuted by %'))
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT created_at 
            FROM chat_messages 
            WHERE conversation_id = c.id 
              AND (message_type != 'system' OR (message_type = 'system' AND content NOT LIKE '% was muted by %' AND content NOT LIKE '% was unmuted by %'))
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message_time,
        (
            SELECT COALESCE(CONCAT(u_sender.fname, ' ', u_sender.lname), 'System')
            FROM chat_messages m
            LEFT JOIN users_tbl u_sender ON m.sender_id = u_sender.id
            WHERE m.conversation_id = c.id 
              AND (m.message_type != 'system' OR (m.message_type = 'system' AND m.content NOT LIKE '% was muted by %' AND m.content NOT LIKE '% was unmuted by %'))
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_sender_name,
        (
            SELECT COALESCE(m.sender_id, 0)
            FROM chat_messages m
            WHERE m.conversation_id = c.id 
              AND (m.message_type != 'system' OR (m.message_type = 'system' AND m.content NOT LIKE '% was muted by %' AND m.content NOT LIKE '% was unmuted by %'))
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_sender_id
    FROM chat_conversations c
    JOIN chat_conversation_members cm_current ON c.id = cm_current.conversation_id AND cm_current.user_id = ?
    JOIN chat_conversation_members cm_other ON c.id = cm_other.conversation_id AND cm_other.user_id != ?
    JOIN users_tbl u_other ON u_other.id = cm_other.user_id
    WHERE c.type = 'direct'
      AND c.archived = 0
      AND cm_current.left_at IS NULL
      AND cm_other.left_at IS NULL
      AND cm_current.is_archived = 0
    ORDER BY last_message_time DESC
";

$dmStmt = $conn->prepare($dmQuery);
$dmStmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$dmStmt->execute();
$dms = $dmStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dmStmt->close();

echo json_encode([
    'success' => true,
    'channels' => $channels,
    'dms' => $dms
]);
