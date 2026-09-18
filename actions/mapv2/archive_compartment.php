<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../init_session.php';
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access is required.']);
    exit;
}

// Archiving keeps the compartment data available for a future restore workflow.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid compartment is required.']);
    exit;
}
$statement = $conn->prepare('UPDATE reforestation_compartments SET archived = 1, archived_at = NOW() WHERE id = ? AND archived = 0');
$statement->bind_param('i', $id);
$statement->execute();
if ($statement->affected_rows !== 1) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'This compartment is unavailable.']);
} else {
    echo json_encode(['success' => true]);
}
$statement->close();
$conn->close();
