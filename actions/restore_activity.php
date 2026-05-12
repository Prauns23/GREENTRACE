<?php
require_once '../init_session.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Invalid activity ID']);
    exit;
}
$stmt = $conn->prepare("UPDATE activities SET archived = 0, archived_at = NULL WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Activity restored.']);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
?>