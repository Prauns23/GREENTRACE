<?php
require_once 'init_session.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'You must be logged in to unjoin.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
if (!$activity_id) {
    echo json_encode(['error' => 'Invalid activity ID.']);
    exit;
}

// Check if participation exists
$check = $conn->prepare("SELECT id FROM volunteer_participations WHERE user_id = ? AND activity_id = ?");
$check->bind_param("ii", $user_id, $activity_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'You have not joined this activity.']);
    exit;
}

$conn->begin_transaction();

try {
    // Delete participation
    $delete = $conn->prepare("DELETE FROM volunteer_participations WHERE user_id = ? AND activity_id = ?");
    $delete->bind_param("ii", $user_id, $activity_id);
    $delete->execute();

    // Decrement participants_count
    $update = $conn->prepare("UPDATE activities SET participants_count = participants_count - 1 WHERE id = ? AND participants_count > 0");
    $update->bind_param("i", $activity_id);
    $update->execute();

    // Get new count
    $select = $conn->prepare("SELECT participants_count FROM activities WHERE id = ?");
    $select->bind_param("i", $activity_id);
    $select->execute();
    $row = $select->get_result()->fetch_assoc();
    $new_count = $row['participants_count'];

    $conn->commit();

    $message = 'You have left the activity';
    $_SESSION['activity_message'] = $message;
    $_SESSION['activity_type'] = 'success';

    echo json_encode([
        'success' => true,
        'message' => $message,
        'new_count' => $new_count,
        'reload' => true
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>