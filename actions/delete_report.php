<?php
require_once '../init_session.php';
require_once '../config.php';

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
if (!$report_id) {
    echo json_encode(['error' => 'Invalid report ID']);
    exit;
}

// First, fetch all photo file paths to delete them from the server
$photo_stmt = $conn->prepare("SELECT file_path FROM report_photos WHERE report_id = ?");
$photo_stmt->bind_param("i", $report_id);
$photo_stmt->execute();
$photos = $photo_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$photo_stmt->close();

// Delete physical files
foreach ($photos as $photo) {
    $file_path = '../' . $photo['file_path']; // adjust path relative to document root
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Delete photo records from database
$delete_photos = $conn->prepare("DELETE FROM report_photos WHERE report_id = ?");
$delete_photos->bind_param("i", $report_id);
$delete_photos->execute();
$delete_photos->close();

// Finally, delete the report itself
$stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
$stmt->bind_param("i", $report_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>