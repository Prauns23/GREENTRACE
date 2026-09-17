<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../init_session.php';
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Administrator access is required.']);
    exit;
}

$result = $conn->query(
    'SELECT id, name, scientific_name, planting_spacing
     FROM tree_species
     WHERE archived = 0
     ORDER BY name ASC'
);

$species = [];
while ($row = $result->fetch_assoc()) {
    $species[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'scientific_name' => $row['scientific_name'],
        'planting_spacing_m' => (float) $row['planting_spacing'],
    ];
}

echo json_encode(['species' => $species], JSON_UNESCAPED_SLASHES);
$conn->close();
