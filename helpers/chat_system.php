<?php

/**
 * Insert a system message into a conversation
 * 
 * @param mysqli $conn
 * @param int   $conversation_id
 * @param string  string $content       The plain-text system message
 * @return bool
 */

function insertSystemMessage($conn, $conversation_id, $content) {
    $stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, message_type, content, created_at) VALUES (?, NULL, 'system', ?, NOW())");
    $stmt->bind_param("is", $conversation_id, $content);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
?>