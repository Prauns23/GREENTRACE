<?php
require_once '../init_session.php';
require_once '../config.php';


$headers = getallheaders();

$csrf_token = $_POST['csrf_token'] ?? ($headers['X-CSRF-Token'] ?? '');
if (!verifyCSRFToken($csrf_token)) {
    echo json_encode(['error' => 'Invalid CSRF Token']);
    exit;
}


if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$ids = isset($input['ids']) ? $input['ids'] : (isset($_POST['ids']) ? json_decode($_POST['ids'], true) : []);
if (empty($ids) || !is_array($ids)) exit(json_encode(['error' => 'Invalid IDs']));

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("DELETE FROM activities WHERE id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$success = $stmt->execute();
$stmt->close();
$conn->close();
echo json_encode($success ? ['success' => true, 'message' => count($ids) . ' activities permanently deleted.'] : ['error' => 'Database error']);
