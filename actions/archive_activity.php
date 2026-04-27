<?php 
require_once '../init_session.php';
require_once '../config.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}


// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unathorized']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Invalid activity ID']);
    exit;
}

// Soft delete: set archived = 1, archive_at = NOW()
$stmt = $conn->prepare("UPDATE activities SET archived = 1, archived_at = NOW() WHERE id = ?");
$stmt->bind_Param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error: '.$conn->error]);
}

$stmt->close();
$conn->close();

?>