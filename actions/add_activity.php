<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

require_once '../init_session.php';
require_once '../config.php';

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

$stmt = $conn->prepare("INSERT INTO activities (title, description, date, time_start, time_end, location, meetup_point, capacity, badge_primary, badge_secondary, image_url, created_at, archived) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)");
if (!$stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sssssssisss", $title, $description, $date, $time_start, $time_end, $location, $meetup_point, $capacity, $badge_primary, $badge_secondary, $image_url);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Activity created successfully']);
} else {
    echo json_encode(['error' => 'Database error: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>