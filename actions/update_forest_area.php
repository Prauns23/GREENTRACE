<?php
require_once '../init_session.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Admin check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Read JSON body instead of $_POST
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

// DEBUG: uncomment to inspect exactly what arrived
// error_log('RAW BODY: ' . $raw);
// error_log('DECODED: ' . print_r($input, true));

if ($input === null) {
    echo json_encode(['error' => 'Invalid JSON payload', 'raw' => $raw]);
    exit;
}

$id = (int)($input['id'] ?? 0);
$name = trim($input['name'] ?? '');
$location_name = trim($input['location_name'] ?? '');
$latitude = (float)($input['latitude'] ?? 0);
$longitude = (float)($input['longitude'] ?? 0);
$date_started = trim($input['date_started'] ?? '');
$status = trim($input['status'] ?? 'active');
$description = trim($input['description'] ?? '');

if (!$id || empty($name) || empty($location_name)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}
if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

// Handle empty date -> NULL, otherwise validate format
if ($date_started === '') {
    $date_started = null;
} else {
    $d = DateTime::createFromFormat('Y-m-d', $date_started);
    if (!$d || $d->format('Y-m-d') !== $date_started) {
        echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD.', 'received' => $date_started]);
        exit;
    }
}

$stmt = $conn->prepare("UPDATE forest_areas SET name = ?, location_name = ?, latitude = ?, longitude = ?, date_started = ?, status = ?, description = ? WHERE id = ?");
$stmt->bind_param("ssddsssi", $name, $location_name, $latitude, $longitude, $date_started, $status, $description, $id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Forest area updated successfully.',
        'saved_date' => $date_started, // echoed back so you can confirm in Network tab
    ]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
$conn->close();

?>