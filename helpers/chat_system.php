<?php

require_once __DIR__ . '/notifications_helper.php';
require_once __DIR__ . '/realtime.php';

/**
 * Insert a system message into a conversation
 * 
 * @param mysqli $conn
 * @param int   $conversation_id
 * @param string  string $content       The plain-text system message
 * @return bool
 */

function insertSystemMessage($conn, $conversation_id, $content)
{
    $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message_type, content, created_at) VALUES (?, NULL, 'system', ?, NOW())");
    $stmt->bind_param("is", $conversation_id, $content);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        publishConversationRealtimeEvent((int) $conversation_id, 'system.message');
    }

    return $success;
}

/**
 * Send a notification to all active members of a conversation
 * 
 * @param mysqli $conn
 * @param int    $conversation_id
 * @param string $title
 * @param string $message
 * @param int    $exclude_user_id (optional) user to exclude (e.g., the actor)
 */
function notifyAllMembers($conn, $conversation_id, $title, $message, $exclude_user_id = null) {
    $stmt = $conn->prepare("SELECT user_id FROM chat_conversation_members WHERE conversation_id = ? AND left_at IS NULL");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($members as $member) {
        $uid = $member['user_id'];
        if ($exclude_user_id && $uid == $exclude_user_id) continue;
        createNotification($uid, 'message', $title, $message, 'message.php');
    }
}
