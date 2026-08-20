<?php
require_once '../init_session.php';
require_once '../config.php';
// require_once '../csrf.php';

// CSRF Validation

$headers = getallheaders();
$csrf_token = $_POST['csrf_token'] ?? ($headers['X-CSRF-Token'] ?? '');
if (!verifyCSRFToken($csrf_token)) {
    echo json_encode(['error' => 'Invalid CSRF Token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Invalid activity ID']);
    exit;
}
$stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Activity permanently deleted.']);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
?>
