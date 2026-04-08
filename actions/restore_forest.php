<?php
require_once '../init_session.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Invalid forest area ID']);
    exit;
}

// Restore: set status = original_status, then clear original_status and set archived = 0
$stmt = $conn->prepare("UPDATE forest_areas SET status = original_status, original_status = NULL, archived = 0 WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
$conn->close();
?>