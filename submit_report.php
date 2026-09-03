<?php
require_once 'init_session.php';
require_once 'config.php';
require_once __DIR__ . '/log_activity.php';
require_once __DIR__ . '/helpers/notifications_helper.php';

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

$anonymous = isset($_POST['anonymous']) && $_POST['anonymous'] === '1' ? 1 : 0;

// User is always logged in because the form is only shown when logged in
$user_id = $_SESSION['user_id'] ?? null;
$email = null;

if ($user_id !== null) {
    // Fetch user's email from database 
    $emailStmt = $conn->prepare("SELECT email FROM users_tbl WHERE id = ?");
    $emailStmt->bind_param("i", $user_id);
    $emailStmt->execute();
    $result = $emailStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $email = $row['email'];
    }
    $emailStmt->close();
}


// If anonymous, override email to null
if ($anonymous) {
    $email = null;
} else {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
}

if (empty($issue_type) || empty($description) || empty($location)) {
    echo json_encode(['error' => 'Please fill in all required fields.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO reports (user_id, issue_type, description, location, latitude, longitude, email, anonymous) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssddss", $user_id, $issue_type, $description, $location, $latitude, $longitude, $email, $anonymous);

if (!$stmt->execute()) {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
    exit;
}

$report_id = $stmt->insert_id;

// Handle file uploads (unchanged)
$upload_dir = 'uploads/reports/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $files = $_FILES['photos'];
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $files['tmp_name'][$i];
            $original_name = $files['name'][$i];
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extension, $allowed)) continue;
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

//  Notify the user (if not anonymous) 
if ($user_id !== null) {
    logActivity($user_id, 'report', $report_id, $issue_type, 'pending', "Your report <strong>$issue_type</strong> is pending review.");

    $notifTitle = "Report Submitted";
    $notifMessage = "Your report \"<strong>$issue_type</strong>\" has been submitted and is pending review.";
    $link = "forestmap.php";
    createNotification($user_id, 'report', $notifTitle, $notifMessage, $link);
}

//  Notify all admins 
$adminStmt = $conn->prepare("SELECT id FROM users_tbl WHERE role IN ('admin', 'super_admin') AND archived = 0");
$adminStmt->execute();
$admins = $adminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$adminStmt->close();

$adminNotifTitle = "New Report Submitted";

// Determine reporter name 
if ($anonymous) {
    $adminNotifMessage = "A new report \"<strong>$issue_type</strong>\" has been submitted anonymously.";
} else {
    // Fetch reporter's full name from database 
    $reporterName = 'a user';
    if ($user_id !== null) {
        $nameStmt = $conn->prepare("SELECT CONCAT(fname, ' ', lname) as full_name FROM users_tbl WHERE id = ?");
        $nameStmt->bind_param("i", $user_id);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result()->fetch_assoc();
        if ($nameResult && !empty($nameResult['full_name'])) {
            $reporterName = $nameResult['full_name'];
        }
        $nameStmt->close();
    }
    $adminNotifMessage = "A new report \"<strong>$issue_type</strong>\" has been submitted by <strong>$reporterName</strong>.";
}

$adminLink = "forestmap.php";

foreach ($admins as $admin) {
    createNotification($admin['id'], 'report', $adminNotifTitle, $adminNotifMessage, $adminLink);
}

echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);
?>
