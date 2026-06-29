<?php
require_once 'init_session.php';
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['unread' => 0]);
    exit;
}
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
echo json_encode(['unread' => $cnt]);
?>