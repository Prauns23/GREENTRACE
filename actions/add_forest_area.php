<?php
require_once '../init_session.php';
require_once '../config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in and is admin/super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get and sanitize input
$name = trim($_POST['name'] ?? '');
$location_name = trim($_POST['location_name'] ?? '');
$latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
$date_started = trim($_POST['date_started'] ?? '');
$status = trim($_POST['status'] ?? 'active');
$description = trim($_POST['description'] ?? '');

// Validate required fields
if (empty($name)) {
    echo json_encode(['error' => 'Forest name is required.']);
    exit;
}
if (empty($location_name)) {
    echo json_encode(['error' => 'Location name is required.']);
    exit;
}
if ($latitude === null || $longitude === null || $latitude === 0 || $longitude === 0) {
    echo json_encode(['error' => 'Valid coordinates are required. Please select a location on the map.']);
    exit;
}
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode(['error' => 'Invalid coordinates range.']);
    exit;
}

// Handle empty date: set to NULL
if (empty($date_started)) {
    $date_started = null;
} else {
    // Validate date format (YYYY-MM-DD)
    $d = DateTime::createFromFormat('Y-m-d', $date_started);
    if (!$d || $d->format('Y-m-d') !== $date_started) {
        echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD.']);
        exit;
    }
}

// Insert into database (date_started can be NULL)
$stmt = $conn->prepare("INSERT INTO forest_areas (name, location_name, latitude, longitude, date_started, status, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssddsss", $name, $location_name, $latitude, $longitude, $date_started, $status, $description);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Forest area added successfully.',
        'id' => $stmt->insert_id
    ]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>