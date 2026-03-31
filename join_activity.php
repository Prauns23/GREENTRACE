<?php
require_once 'init_session.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'You must be logged in to join.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
if (!$activity_id) {
    echo json_encode(['error' => 'Invalid activity ID.']);
    exit;
}

// Check if user has a volunteer profile
$check_profile = $conn->prepare("SELECT full_name FROM volunteer_profiles WHERE user_id = ?");
$check_profile->bind_param("i", $user_id);
$check_profile->execute();
$profile_result = $check_profile->get_result();
if ($profile_result->num_rows === 0) {
    echo json_encode(['error' => 'You must complete volunteer registration first.']);
    exit;
}
$profile = $profile_result->fetch_assoc();
$full_name = $profile['full_name'];

// Check if already joined (optional, but prevents duplicate error messages)
$check_join = $conn->prepare("SELECT id FROM volunteer_participations WHERE user_id = ? AND activity_id = ?");
$check_join->bind_param("ii", $user_id, $activity_id);
$check_join->execute();
if ($check_join->get_result()->num_rows > 0) {
    echo json_encode(['error' => 'You have already joined this activity.']);
    exit;
}

$conn->begin_transaction();

try {
    // Atomic update: increment participants_count only if capacity not exceeded
    $update = $conn->prepare("UPDATE activities SET participants_count = participants_count + 1 WHERE id = ? AND participants_count < capacity");
    $update->bind_param("i", $activity_id);
    $update->execute();

    if ($update->affected_rows === 0) {
        // No rows updated → either activity doesn't exist or capacity reached
        $conn->rollback();
        echo json_encode(['error' => 'This activity is full or no longer available.']);
        exit;
    }

    // Insert participation record
    $insert = $conn->prepare("INSERT INTO volunteer_participations (user_id, activity_id, full_name) VALUES (?, ?, ?)");
    $insert->bind_param("iis", $user_id, $activity_id, $full_name);
    $insert->execute();

    // Fetch the new participants count (optional, but used for response)
    $select = $conn->prepare("SELECT participants_count FROM activities WHERE id = ?");
    $select->bind_param("i", $activity_id);
    $select->execute();
    $row = $select->get_result()->fetch_assoc();
    $new_count = $row['participants_count'];

    $conn->commit();

    $message = 'Thanks for participating!';
    $_SESSION['activity_message'] = $message;

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