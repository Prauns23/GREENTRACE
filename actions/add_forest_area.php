<?php
require_once '../init_session.php';
require_once  '../config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}


// Check if user is logged in and is admin

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unathorized access.']);
    exit;
}

// Get and sanitize input
$name = trim($_POST['name'] ?? '');
$location_name = trim($_POST['location_name'] ?? '');
$latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
$date_established = trim($_POST['date_established'] ?? '');
$status = trim($_POST['status'] ?? 'active');
$description = trim($_POST['description'] ?? '');


// Validate requried fields 
if (empty($name)) {
    echo json_encode(['error' => 'Foret name is required.']);
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

// Validate coordinates range
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode(['error' => 'Invalid coordinates range.']);
    exit;
}

// Insert into database 
$stmt = $conn->prepare("INSERT INTO forest_areas (name, location_name, latitude, longitude, date_established, status, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssddsss", $name, $location_name, $latitude, $longitude, $date_established, $status, $description);

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
