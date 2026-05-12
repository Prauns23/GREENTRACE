<?php
require_once '../init_session.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') exit(json_encode(['error' => 'Unauthorized']));
$input = json_decode(file_get_contents('php://input'), true);
$ids = isset($input['ids']) ? $input['ids'] : (isset($_POST['ids']) ? json_decode($_POST['ids'], true) : []);
if (empty($ids) || !is_array($ids)) exit(json_encode(['error' => 'Invalid IDs']));

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $conn->prepare("UPDATE activities SET archived = 1, archived_at = NOW() WHERE id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
$success = $stmt->execute();
$stmt->close();
$conn->close();
echo json_encode($success ? ['success' => true, 'message' => count($ids) . ' activities archived.'] : ['error' => 'Database error']);
?>