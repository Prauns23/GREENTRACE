<?php
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// CSRF validation
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$role = trim($_POST['role'] ?? '');

if (empty($first_name) || empty($last_name) || empty($email)) {
    echo json_encode(['error' => 'Name and email are required.']);
    exit;
}

// Check email uniqueness
$checkStmt = $conn->prepare("SELECT id FROM users_tbl WHERE email = ? AND id != ?");
$checkStmt->bind_param("si", $email, $user_id);
if ($checkStmt->execute() && $checkStmt->get_result()->num_rows > 0) {
    echo json_encode(['error' => 'Email already taken.']);
    exit;
}

// Only admin can change role
$role_update = '';
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && in_array($role, ['user', 'admin'])) {
    $role_update = ", role = ?";
}

$sql = "UPDATE users_tbl SET fname = ?, lname = ?, email = ?, phone_no = ?" . $role_update . " WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($role_update) {
    $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $role, $user_id);
} else {
    $stmt->bind_param("ssssi", $first_name, $last_name, $email, $phone, $user_id);
}

if ($stmt->execute()) {
    // Update session
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    $_SESSION['email'] = $email;
    if ($role_update) {
        $_SESSION['role'] = $role;
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
$conn->close();
?>