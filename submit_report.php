<?php
require_once 'init_session.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$issue_type = trim($_POST['issue_type'] ?? '');
$description = trim($_POST['description'] ?? '');
$location = trim($_POST['location'] ?? '');
$latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
$longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

// Anonymous flag: explicit '1' means anonymous
$anonymous = isset($_POST['anonymous']) && $_POST['anonymous'] === '1' ? 1 : 0;

// Determine user_id and email based on anonymous flag
if ($anonymous) {
    // Anonymous report: ignore user_id and email
    $user_id = null;
    $email = null;
} else {
    // Non-anonymous: use logged-in user's ID (report.php already requires login)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
}

// Validation (unchanged)
if (empty($issue_type) || empty($description) || empty($location)) {
    echo json_encode(['error' => 'Please fill in all required fields.']);
    exit;
}

// Insert Report
$stmt = $conn->prepare("INSERT INTO reports (user_id, issue_type, description, location, latitude, longitude, email, anonymous) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssddss", $user_id, $issue_type, $description, $location, $latitude, $longitude, $email, $anonymous);

if (!$stmt->execute()) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

$report_id = $stmt->insert_id;

// Handle file uploads
$upload_dir = 'uploads/reports/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $files = $_FILES['photos'];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $files['tmp_name'][$i];
            $original_name = $files['name'][$i];
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extension, $allowed)) {
                continue;
            }
            $new_filename = uniqid() . '.' . $extension;
            $destination = $upload_dir . $new_filename;
            if (move_uploaded_file($tmp_name, $destination)) {
                $stmt_photo = $conn->prepare("INSERT INTO report_photos (report_id, file_path, original_name) VALUES (?, ?, ?)");
                $stmt_photo->bind_param("iss", $report_id, $destination, $original_name);
                $stmt_photo->execute();
            }
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);