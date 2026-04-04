<?php
require_once 'init_session.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get form data
$full_name     = trim($_POST['fullname'] ?? '');
$date_of_birth = trim($_POST['dob'] ?? '');
$gender        = trim($_POST['gender'] ?? '');
$mobile_number = trim($_POST['mobile'] ?? '');
$email         = trim($_POST['email'] ?? '');
$municipality  = trim($_POST['municipality'] ?? '');
$barangay      = trim($_POST['barangay'] ?? '');
$understand    = isset($_POST['understand']) ? 1 : 0;
$agree         = isset($_POST['agree']) ? 1 : 0;

// Validation
if (empty($full_name) || empty($date_of_birth) || empty($mobile_number) || empty($email) || empty($municipality) || empty($barangay)) {
    echo json_encode(['error' => 'Please fill in all required fields.']);
    exit;
}
if (!$understand || !$agree) {
    echo json_encode(['error' => 'You must agree to the terms and guidelines.']);
    exit;
}

// Get logged-in user ID
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['error' => 'You must be logged in to volunteer.']);
    exit;
}

// Always insert into volunteers (participation log)
$stmtVol = $conn->prepare("INSERT INTO volunteers (user_id, full_name, date_of_birth, gender, mobile_number, email, municipality, barangay) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmtVol->bind_param("isssssss", $user_id, $full_name, $date_of_birth, $gender, $mobile_number, $email, $municipality, $barangay);
if (!$stmtVol->execute()) {
    echo json_encode(['error' => 'Failed to log volunteer participation: ' . $conn->error]);
    exit;
}

// Check if a profile already exists for this user
$check = $conn->prepare("SELECT id FROM volunteer_profiles WHERE user_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$result = $check->get_result();

$is_new_profile = false;
if ($result->num_rows == 0) {
    // First time: insert into volunteer_profiles
    $stmtProf = $conn->prepare("INSERT INTO volunteer_profiles (user_id, full_name, date_of_birth, gender, mobile_number, email, municipality, barangay) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtProf->bind_param("isssssss", $user_id, $full_name, $date_of_birth, $gender, $mobile_number, $email, $municipality, $barangay);
    if (!$stmtProf->execute()) {
        echo json_encode(['error' => 'Failed to create profile: ' . $conn->error]);
        exit;
    }
    $is_new_profile = true;
    $message = 'Volunteer registration successful!';
} else {
    // Profile already exists 
    $message = 'Thank you for volunteering again!';
}

// Store message in session to display on activities.php
$_SESSION['volunteer_success'] = $message;

// Return JSON with redirect flag
echo json_encode([
    'success' => true, 
    'message' => $message,
    'redirect' => true
]);
?>