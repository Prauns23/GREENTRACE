<?php
require_once '../init_session.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$location_name = trim($_POST['location_name'] ?? '');
$latitude = (float)($_POST['latitude'] ?? 0);
$longitude = (float)($_POST['longitude'] ?? 0);
$date_started = trim($_POST['date_started'] ?? '');
$status = trim($_POST['status'] ?? 'active');
$description = trim($_POST['description'] ?? '');

if (!$id || empty($name) || empty($location_name)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

$stmt = $conn->prepare("UPDATE forest_areas SET name = ?, location_name = ?, latitude = ?, longitude = ?, date_started = ?, status = ?, description = ? WHERE id = ?");
$stmt->bind_param("ssddsssi", $name, $location_name, $latitude, $longitude, $date_started, $status, $description, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
$conn->close();
?>