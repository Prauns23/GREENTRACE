<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../init_session.php';
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access is required.']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid photo is required.']);
    exit;
}

try {
    $statement = $conn->prepare('UPDATE compartment_photos SET archived = 1, archived_at = NOW() WHERE id = ? AND archived = 0');
    $statement->bind_param('i', $id);
    $statement->execute();
    if ($statement->affected_rows !== 1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'This photo is unavailable.']);
        exit;
    }
    echo json_encode(['success' => true, 'id' => (string) $id]);
} catch (Throwable $exception) {
    error_log('Map V2 photo archive failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'The photo could not be archived.']);
} finally {
    $conn->close();
}
