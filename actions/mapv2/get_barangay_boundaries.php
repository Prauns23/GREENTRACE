<?php
declare(strict_types=1);

header('Content-Type: application/geo+json; charset=utf-8');
require_once __DIR__ . '/../../init_session.php';
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Administrator access is required.']);
    exit;
}

$result = $conn->query(
    'SELECT b.id, b.name, b.psgc_code, b.municipality_name, b.province_name, ST_AsGeoJSON(bb.boundary) AS geometry_json
     FROM barangay_boundaries bb
     INNER JOIN barangays b ON b.id = bb.barangay_id
     ORDER BY b.name ASC'
);

$features = [];
while ($row = $result->fetch_assoc()) {
    $geometry = json_decode((string) $row['geometry_json'], true);
    if (!$geometry) {
        continue;
    }
    $features[] = [
        'type' => 'Feature',
        'properties' => [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'psgc_code' => $row['psgc_code'],
            'municipality' => $row['municipality_name'],
            'province' => $row['province_name'],
        ],
        'geometry' => $geometry,
    ];
}

echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_SLASHES);
$conn->close();
