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
    $compartments = [];
    $result = $conn->query(
        'SELECT rc.id, rc.name, rc.status, rc.date_started, rc.updated_at, rc.gross_area_ha,
                ST_AsGeoJSON(rc.boundary) AS boundary_geojson,
                b.id AS barangay_id, b.name AS barangay_name,
                b.municipality_name, b.province_name
         FROM reforestation_compartments rc
         LEFT JOIN barangays b ON b.id = rc.barangay_id
         WHERE rc.archived = 0
         ORDER BY rc.created_at DESC, rc.id DESC'
    );

    while ($row = $result->fetch_assoc()) {
        $geometry = json_decode((string) $row['boundary_geojson'], true);
        $coordinates = $geometry['type'] === 'Polygon' ? ($geometry['coordinates'][0] ?? []) : [];
        if (count($coordinates) > 1 && $coordinates[0] === $coordinates[count($coordinates) - 1]) {
            array_pop($coordinates);
        }
        $compartments[(int) $row['id']] = [
            'id' => (string) $row['id'],
            'name' => $row['name'],
            'status' => $row['status'],
            'started_at' => $row['date_started'],
            'updated_at' => $row['updated_at'],
            'hectares' => (float) ($row['gross_area_ha'] ?? 0),
            'boundary' => array_map(static fn(array $point): array => [(float) $point[1], (float) $point[0]], $coordinates),
            'barangay' => $row['barangay_id'] ? [
                'id' => (int) $row['barangay_id'],
                'name' => $row['barangay_name'],
                'municipality' => $row['municipality_name'],
                'province' => $row['province_name'],
            ] : null,
            'species_mix' => [],
        ];
    }

    if ($compartments) {
        $ids = implode(',', array_map('intval', array_keys($compartments)));
        $speciesResult = $conn->query(
            'SELECT cs.compartment_id, cs.planned_quantity, ts.id AS tree_species_id, ts.name, ts.scientific_name
             FROM compartment_species cs
             INNER JOIN tree_species ts ON ts.id = cs.tree_species_id
             WHERE cs.compartment_id IN (' . $ids . ')
             ORDER BY cs.compartment_id, cs.sort_order, cs.id'
        );
        while ($species = $speciesResult->fetch_assoc()) {
            $compartments[(int) $species['compartment_id']]['species_mix'][] = [
                'id' => (int) $species['tree_species_id'],
                'name' => $species['name'],
                'scientific_name' => $species['scientific_name'],
                'quantity' => (int) $species['planned_quantity'],
            ];
        }
    }

    echo json_encode(['success' => true, 'compartments' => array_values($compartments)]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load reforestation compartments.']);
} finally {
    $conn->close();
}
