<?php
require_once 'init_session.php';
require_once 'config.php';

// Check if admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: information.php');
    exit();
}

$species_id = $_POST['species_id'] ?? '';
if (empty($species_id)) {
    $_SESSION['upload_error'] = 'Species not selected.';
    header('Location: information.php');
    exit();
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_error'] = 'No image uploaded or upload error.';
    header('Location: information.php');
    exit();
}

$file = $_FILES['image'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    $_SESSION['upload_error'] = 'Only JPEG, PNG, GIF, or WEBP images are allowed.';
    header('Location: information.php');
    exit();
}

// Check if species exists
$stmt = $conn->prepare("SELECT id FROM tree_species WHERE id = ?");
$stmt->bind_param("i", $species_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['upload_error'] = 'Species not found.';
    header('Location: information.php');
    exit();
}

// Create uploads directory if not exists
$upload_dir = 'uploads/species/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = uniqid() . '.' . $ext;
$destination = $upload_dir . $new_filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Update database
    $stmt = $conn->prepare("UPDATE tree_species SET image_url = ? WHERE id = ?");
    $stmt->bind_param("si", $destination, $species_id);
    if ($stmt->execute()) {
        $_SESSION['upload_success'] = 'Image uploaded successfully.';
    } else {
        $_SESSION['upload_error'] = 'Database error.';
    }
} else {
    $_SESSION['upload_error'] = 'Failed to move uploaded file.';
}

header('Location: information.php');
exit();