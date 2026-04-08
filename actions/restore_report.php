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

$report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
if (!$report_id) {
    echo json_encode(['error' => 'Invalid report ID']);
    exit;
}

// Restore: set archived = 0
$stmt = $conn->prepare("UPDATE reports SET archived = 0 WHERE id = ?");
$stmt->bind_param("i", $report_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
$conn->close();
?>