<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

require_once '../init_session.php';
require_once '../config.php';
require_once '../notifications_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$date = trim($_POST['date'] ?? '');
$time_start = trim($_POST['time_start'] ?? '');
$time_end = trim($_POST['time_end'] ?? '');
$location = trim($_POST['location'] ?? '');
$meetup_point = trim($_POST['meetup_point'] ?? '');
$capacity = (int)($_POST['capacity'] ?? 50);
$badge_primary = trim($_POST['badge_primary'] ?? '');
$badge_secondary = trim($_POST['badge_secondary'] ?? '');
$image_url = trim($_POST['image_url'] ?? '');

if (empty($title) || empty($description) || empty($date) || empty($location) || empty($meetup_point) || empty($badge_primary) || empty($badge_secondary)) {
    echo json_encode(['error' => 'All required fields must be filled']);
    exit;
}

// Handles image upload 
$image_url = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/greentrace/uploads/activities/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed)) {
        $filename = 'activity_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destination = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            $image_url = 'uploads/activities/' . $filename;
        }
    }
} else {
    // Keep existing image url if provided via text field
    $image_url = trim($_POST['image_url'] ?? '');
}

$stmt = $conn->prepare("INSERT INTO activities (title, description, date, time_start, time_end, location, meetup_point, capacity, badge_primary, badge_secondary, image_url, created_at, archived) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)");
if (!$stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sssssssisss", $title, $description, $date, $time_start, $time_end, $location, $meetup_point, $capacity, $badge_primary, $badge_secondary, $image_url);

if ($stmt->execute()) {
    // Store the activity title for notification BEFORE overwriting the variable
    $activityTitle = $title; 
    $activityId = $conn->insert_id;

    // Send notifications to all active users (except admins)
    $userStmt = $conn->prepare("SELECT id FROM users_tbl WHERE archived = 0 AND role = 'user'");
    $userStmt->execute();
    $users = $userStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $userStmt->close();

    // Notification details (using the actual activity title)
    $notifTitle = "New Activity!";
    $notifMessage = "A new activity \"<strong>$activityTitle</strong>\" has been added. Come join and apply!";
    $link = "activities.php?open_activity={$activityId}";

    foreach ($users as $user) {
        createNotification($user['id'], 'activity', $notifTitle, $notifMessage, $link);
    }

    echo json_encode(['success' => true, 'message' => 'Activity created successfully']);
} else {
    echo json_encode(['error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>