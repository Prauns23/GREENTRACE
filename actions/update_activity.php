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
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = (int)($_POST['activity_id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'Invalid activity ID']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$date = trim($_POST['date'] ?? '');
$time_start = trim($_POST['time_start'] ?? '');
$time_end = trim($_POST['time_end'] ?? '');
$location = trim($_POST['location'] ?? '');
$meetup_point = trim($_POST['meetup_point'] ?? '');
$capacity = (int)($_POST['capacity'] ?? 0);
$badge_primary = trim($_POST['badge_primary'] ?? '');
$badge_secondary = trim($_POST['badge_secondary'] ?? '');

// Handle image upload
$image_url = trim($_POST['image_url'] ?? '');
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/greentrace/uploads/activities/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed)) {
        $filename = 'activity_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destination = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
            // Optionally delete the old image file if it exists and is not the default placeholder
            if (!empty($image_url) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/greentrace/' . $image_url)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/greentrace/' . $image_url);
            }
            $image_url = 'uploads/activities/' . $filename;
        }
    }
} // else keep existing $image_url

if (empty($title) || empty($description) || empty($date) || empty($location)) {
    echo json_encode(['error' => 'Title, description, date, and location are required.']);
    exit;
}

$sql = "UPDATE activities SET title = ?, description = ?, date = ?, time_start = ?, time_end = ?, location = ?, meetup_point = ?, capacity = ?, badge_primary = ?, badge_secondary = ?, image_url = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    logError("Prepare failed: " . $conn->error, ['sql' => $sql]);
    echo json_encode(['error' => 'Database error']);
    exit;
}

$params = [
    $title,
    $description,
    $date,
    $time_start,
    $time_end,
    $location,
    $meetup_point,
    $capacity,
    $badge_primary,
    $badge_secondary,
    $image_url,
    $id
];
$types = str_repeat('s', 11) . 'i';
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Activity updated successfully!']);
} else {
    echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
}
$stmt->close();
$conn->close();

?>