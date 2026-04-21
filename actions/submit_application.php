<?php
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$activity_id = (int)($_POST['activity_id'] ?? 0);
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$barangay = trim($_POST['barangay'] ?? '');
$understand = isset($_POST['understand']);
$agree = isset($_POST['agree']);

$birthDate = DateTime::createFromFormat('Y-m-d', $date_of_birth);
if (!$birthDate) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}
$today = new DateTime();
$age = $today->diff($birthDate)->y;
if ($age < 18 || $age > 65) {
    echo json_encode(['error' => 'Age must be between 18 and 65 years old.']);
    exit;
}

if (!$activity_id || !$date_of_birth || !$barangay || !$understand || !$agree) {
    echo json_encode(['error' => 'All required fields must be filled']);
    exit;
}

// Fetch user data from users_tbl (full name, mobile, email)
$userStmt = $conn->prepare("SELECT CONCAT(fname, ' ', lname) as full_name, phone_no as mobile_number, email FROM users_tbl WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
if (!$userData) {
    echo json_encode(['error' => 'User not found']);
    exit;
}
$full_name = $userData['full_name'];
$mobile_number = $userData['mobile_number'];
$email = $userData['email'];


// Application checker
$checkStmt = $conn->prepare("
    SELECT id, status 
    FROM volunteer_applications 
    WHERE user_id = ? 
      AND activity_id = ? 
      AND archived = 0 
      AND status IN ('pending', 'approved')
    LIMIT 1
");
$checkStmt->bind_param("ii", $user_id, $activity_id);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
if ($existing) {
    echo json_encode([
        'error' => 'You already have an active application for this activity (status: ' . $existing['status'] . '). Please wait for admin review.'
    ]);
    exit;
}
$checkStmt->close();


// File upload handling – multiple files
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/GREENTRACE/uploads/applications/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

if (!isset($_FILES['verification_files']) || empty($_FILES['verification_files']['name'][0])) {
    echo json_encode(['error' => 'Please upload at least one verification file']);
    exit;
}

// File uploads
$files = $_FILES['verification_files'];
$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
$uploadedPaths = [];

for ($i = 0; $i < count($files['name']); $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Error uploading file: ' . $files['name'][$i]]);
        exit;
    }
    $type = $files['type'][$i];
    $size = $files['size'][$i];
    if (!in_array($type, $allowed)) {
        echo json_encode(['error' => 'Only JPG, PNG, or PDF files are allowed']);
        exit;
    }
    if ($size > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'File size must be less than 5MB: ' . $files['name'][$i]]);
        exit;
    }
    $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
    $filename = 'app_' . $user_id . '_' . $activity_id . '_' . time() . '_' . $i . '.' . $ext;
    $filepath = $uploadDir . $filename;
    $db_path = 'uploads/applications/' . $filename;
    if (!move_uploaded_file($files['tmp_name'][$i], $filepath)) {
        echo json_encode(['error' => 'Failed to upload file: ' . $files['name'][$i]]);
        exit;
    }
    $uploadedPaths[] = $db_path;
}


// Insert application
$stmt = $conn->prepare("INSERT INTO volunteer_applications 
    (user_id, activity_id, full_name, date_of_birth, gender, mobile_number, email, barangay, status, submitted_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
$stmt->bind_param("iissssss", $user_id, $activity_id, $full_name, $date_of_birth, $gender, $mobile_number, $email, $barangay);

if (!$stmt->execute()) {
    echo json_encode(['error' => 'Database error: ' . $stmt->error]);
    exit;
}
$application_id = $conn->insert_id;
$stmt->close();

// Insert photos
$photoStmt = $conn->prepare("INSERT INTO application_photos (application_id, file_path, original_name) VALUES (?, ?, ?)");
foreach ($uploadedPaths as $idx => $path) {
    $originalName = $files['name'][$idx];
    $photoStmt->bind_param("iss", $application_id, $path, $originalName);
    if (!$photoStmt->execute()) {
        // Rollback? For simplicity, just error and delete uploaded files
        foreach ($uploadedPaths as $p) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/GREENTRACE/' . $p;
            if (file_exists($fullPath)) unlink($fullPath);
        }
        echo json_encode(['error' => 'Failed to save photo: ' . $originalName]);
        exit;
    }
}
$photoStmt->close();

echo json_encode(['success' => true]);
