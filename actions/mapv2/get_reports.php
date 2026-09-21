<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../init_session.php';
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access is required.']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT r.id, r.issue_type, r.description, r.location, r.latitude, r.longitude,
               r.anonymous, r.status, r.created_at, r.updated_at, r.archived,
               u.fname, u.lname,
               GROUP_CONCAT(rp.file_path ORDER BY rp.id SEPARATOR '||') AS photo_paths,
               GROUP_CONCAT(rp.original_name ORDER BY rp.id SEPARATOR '||') AS photo_names
        FROM reports r
        LEFT JOIN users_tbl u ON r.user_id = u.id
        LEFT JOIN report_photos rp ON rp.report_id = r.id
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $photos = [];
        if (!empty($row['photo_paths'])) {
            $paths = explode('||', $row['photo_paths']);
            $names = explode('||', $row['photo_names']);
            foreach ($paths as $i => $path) {
                $photos[] = [
                    'path' => $path,
                    'name' => $names[$i] ?? basename($path),
                ];
            }
        }

        $reporterName = $row['anonymous']
            ? 'Anonymous'
            : (trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')) ?: 'Unknown user');

        $reports[] = [
            'id' => (int) $row['id'],
            'issue_type' => $row['issue_type'],
            'description' => $row['description'],
            'location' => $row['location'],
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'anonymous' => (int) $row['anonymous'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'archived' => (int) $row['archived'],
            'reporter' => $reporterName,
            'photos' => $photos,
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'reports' => $reports]);
} catch (Throwable $e) {
    error_log('Map V2 reports fetch failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load reports.']);
}
$conn->close();
