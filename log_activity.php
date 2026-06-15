<?php


function logActivity($user_id, $type, $item_id, $title, $status, $description) {
    global $conn;
    if (!$conn) return;
    $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, type, item_id, title, status, description, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isisss", $user_id, $type, $item_id, $title, $status, $description);
    $stmt->execute();
    $stmt->close();
}
?>