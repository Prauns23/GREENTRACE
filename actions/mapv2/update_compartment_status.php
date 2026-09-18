<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../init_session.php';
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST is required.']);
    exit;
}
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access is required.']);
    exit;
}

// This small action supports a quick status change without reopening the edit form.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$status = (string) ($_POST['status'] ?? '');
$allowedStatuses = ['planned', 'planted', 'monitored', 'low_survival', 'completed'];
if (!$id || !in_array($status, $allowedStatuses, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid compartment and status are required.']);
    exit;
}

$conn->begin_transaction();
try {
    $currentStatement = $conn->prepare('SELECT status FROM reforestation_compartments WHERE id = ? AND archived = 0 FOR UPDATE');
    $currentStatement->bind_param('i', $id);
    $currentStatement->execute();
    $current = $currentStatement->get_result()->fetch_assoc();
    $currentStatement->close();
    if (!$current) throw new InvalidArgumentException('This compartment is unavailable.');

    if ($current['status'] !== $status) {
        // updated_at changes automatically because it is an ON UPDATE timestamp column.
        $updateStatement = $conn->prepare('UPDATE reforestation_compartments SET status = ? WHERE id = ?');
        $updateStatement->bind_param('si', $status, $id);
        if (!$updateStatement->execute()) throw new RuntimeException($updateStatement->error);
        $updateStatement->close();

        $recordedBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $historyStatement = $conn->prepare('INSERT INTO compartment_status_history (compartment_id, status, notes, recorded_by) VALUES (?, ?, ?, ?)');
        $note = 'Status updated from the compartment details panel.';
        $historyStatement->bind_param('issi', $id, $status, $note, $recordedBy);
        if (!$historyStatement->execute()) throw new RuntimeException($historyStatement->error);
        $historyStatement->close();
    }

    $timestampStatement = $conn->prepare('SELECT updated_at FROM reforestation_compartments WHERE id = ?');
    $timestampStatement->bind_param('i', $id);
    $timestampStatement->execute();
    $timestamp = $timestampStatement->get_result()->fetch_assoc();
    $timestampStatement->close();
    $conn->commit();
    echo json_encode(['success' => true, 'updated_at' => $timestamp['updated_at'] ?? null]);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code($exception instanceof InvalidArgumentException ? 404 : 500);
    echo json_encode(['success' => false, 'error' => $exception->getMessage() ?: 'Unable to update the status.']);
}
$conn->close();
