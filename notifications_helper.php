<?php
/**
 * Insert a notification for a user
 * @param int    $user_id  Recipient user ID
 * @param string $type     'application', 'activity', 'report', 'system'
 * @param string $title    Short headline
 * @param string $message  Detailed message (can contain HTML)
 * @param string $link     Optional URL to click
 * @return bool
 */
function createNotification($user_id, $type, $title, $message, $link = null) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    return $stmt->execute();
}
?>