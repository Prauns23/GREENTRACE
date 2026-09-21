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
$name = trim((string) ($_POST['name'] ?? ''));
$category = (string) ($_POST['category'] ?? 'other');
$allowedCategories = ['planned', 'planted', 'monitored', 'low_survival', 'completed', 'other'];

if (!$id || $name === '' || mb_strlen($name) > 255 || !in_array($category, $allowedCategories, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Provide a photo name and valid category.']);
    exit;
}

try {
    $statement = $conn->prepare(
        'UPDATE compartment_photos SET display_name = ?, category = ? WHERE id = ? AND archived = 0'
    );
    $statement->bind_param('ssi', $name, $category, $id);
    $statement->execute();

    if ($statement->affected_rows < 1) {
        $check = $conn->prepare('SELECT id FROM compartment_photos WHERE id = ? AND archived = 0');
        $check->bind_param('i', $id);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'This photo is unavailable.']);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'photo' => ['id' => (string) $id, 'name' => $name, 'category' => ucwords(str_replace('_', ' ', $category))],
    ]);
} catch (Throwable $exception) {
    error_log('Map V2 photo update failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'The photo details could not be saved.']);
} finally {
    $conn->close();
}
