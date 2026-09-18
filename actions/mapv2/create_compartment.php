<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../init_session.php';
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST is required.']);
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access is required.']);
    exit;
}

function fail(string $message, int $status = 422): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function polygonAreaSquareMetres(array $ring): float {
    $latitude = array_sum(array_column($ring, 1)) / count($ring);
    $longitudeOrigin = $ring[0][0];
    $latitudeOrigin = $ring[0][1];
    $metresPerLongitude = 111320 * cos(deg2rad($latitude));
    $metresPerLatitude = 110574;
    $area = 0.0;
    $last = count($ring) - 1;

    for ($index = 0; $index < $last; $index++) {
        $next = $index + 1;
        $x1 = ($ring[$index][0] - $longitudeOrigin) * $metresPerLongitude;
        $y1 = ($ring[$index][1] - $latitudeOrigin) * $metresPerLatitude;
        $x2 = ($ring[$next][0] - $longitudeOrigin) * $metresPerLongitude;
        $y2 = ($ring[$next][1] - $latitudeOrigin) * $metresPerLatitude;
        $area += ($x1 * $y2) - ($x2 * $y1);
    }

    return abs($area) / 2;
}

$name = trim((string) ($_POST['name'] ?? ''));
$status = (string) ($_POST['status'] ?? 'planned');
$dateStarted = (string) ($_POST['date_started'] ?? '');
$speciesMixJson = (string) ($_POST['species_mix'] ?? '');
$boundaryJson = (string) ($_POST['boundary_geojson'] ?? '');
$allowedStatuses = ['draft', 'planned', 'planted', 'monitored', 'low_survival', 'completed'];

if ($name === '' || mb_strlen($name) > 150) {
    fail('A plot name of up to 150 characters is required.');
}
if (!in_array($status, $allowedStatuses, true)) {
    fail('The selected status is invalid.');
}
if (!$dateStarted || !DateTime::createFromFormat('Y-m-d', $dateStarted)) {
    fail('A valid start date is required.');
}
$speciesMix = json_decode($speciesMixJson, true);
if (!is_array($speciesMix) || count($speciesMix) === 0) {
    fail('Add at least one tree species.');
}

$geometry = json_decode($boundaryJson, true);
$ring = $geometry['type'] === 'Polygon' ? ($geometry['coordinates'][0] ?? null) : null;
if (!is_array($ring) || count($ring) < 4) {
    fail('Draw at least three plot corners before saving.');
}

$normalisedRing = [];
foreach ($ring as $coordinate) {
    if (!is_array($coordinate) || count($coordinate) < 2 || !is_numeric($coordinate[0]) || !is_numeric($coordinate[1])) {
        fail('The plot boundary contains an invalid coordinate.');
    }
    $longitude = (float) $coordinate[0];
    $latitude = (float) $coordinate[1];
    if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
        fail('The plot boundary is outside valid longitude/latitude limits.');
    }
    $normalisedRing[] = [$longitude, $latitude];
}

if ($normalisedRing[0] !== $normalisedRing[count($normalisedRing) - 1]) {
    $normalisedRing[] = $normalisedRing[0];
}

$distinctCorners = array_unique(array_map(static fn(array $point): string => implode(',', $point), array_slice($normalisedRing, 0, -1)));
if (count($distinctCorners) < 3) {
    fail('A compartment needs at least three distinct corners.');
}

$wktPoints = array_map(static fn(array $point): string => sprintf('%.8F %.8F', $point[0], $point[1]), $normalisedRing);
$polygonWkt = 'POLYGON((' . implode(',', $wktPoints) . '))';
$areaSqm = polygonAreaSquareMetres($normalisedRing);
if ($areaSqm <= 1) {
    fail('The drawn plot is too small to save.');
}
$areaHa = $areaSqm / 10000;

$centreLongitude = array_sum(array_column($normalisedRing, 0)) / count($normalisedRing);
$centreLatitude = array_sum(array_column($normalisedRing, 1)) / count($normalisedRing);
$centreWkt = sprintf('POINT(%.8F %.8F)', $centreLongitude, $centreLatitude);

$conn->begin_transaction();
try {
    $barangayId = null;
    $barangay = null;
    $barangayStatement = $conn->prepare(
        'SELECT b.id, b.name, b.municipality_name, b.province_name
         FROM barangay_boundaries bb
         INNER JOIN barangays b ON b.id = bb.barangay_id
         WHERE ST_Contains(bb.boundary, ST_GeomFromText(?))
         LIMIT 1'
    );
    if (!$barangayStatement) {
        throw new RuntimeException($conn->error);
    }
    $barangayStatement->bind_param('s', $centreWkt);
    $barangayStatement->execute();
    $barangay = $barangayStatement->get_result()->fetch_assoc() ?: null;
    $barangayStatement->close();
    if ($barangay) {
        $barangayId = (int) $barangay['id'];
    }

    $speciesStatement = $conn->prepare('SELECT id, planting_spacing FROM tree_species WHERE id = ? AND archived = 0');
    if (!$speciesStatement) {
        throw new RuntimeException($conn->error);
    }
    $validatedSpecies = [];
    foreach ($speciesMix as $position => $item) {
        $speciesId = filter_var($item['tree_species_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($item['planned_quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$speciesId || !$quantity) {
            throw new InvalidArgumentException('Each species needs a valid quantity.');
        }
        $speciesStatement->bind_param('i', $speciesId);
        $speciesStatement->execute();
        $speciesRecord = $speciesStatement->get_result()->fetch_assoc();
        if (!$speciesRecord) {
            throw new InvalidArgumentException('One of the selected tree species is unavailable.');
        }
        $validatedSpecies[] = [
            'id' => (int) $speciesRecord['id'],
            'quantity' => (int) $quantity,
            'spacing' => max(0.01, (float) $speciesRecord['planting_spacing']),
            'sort_order' => $position,
        ];
    }
    $speciesStatement->close();

    $createdBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $compartmentStatement = $conn->prepare(
        'INSERT INTO reforestation_compartments
        (barangay_id, name, status, date_started, gross_area_ha, calculated_area_sqm, boundary, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ST_GeomFromText(?), ?)'
    );
    if (!$compartmentStatement) {
        throw new RuntimeException($conn->error);
    }
    $compartmentStatement->bind_param('isssddsi', $barangayId, $name, $status, $dateStarted, $areaHa, $areaSqm, $polygonWkt, $createdBy);
    if (!$compartmentStatement->execute()) {
        throw new RuntimeException($compartmentStatement->error);
    }
    $compartmentId = (int) $conn->insert_id;
    $compartmentStatement->close();

    $speciesInsert = $conn->prepare(
        'INSERT INTO compartment_species (compartment_id, tree_species_id, planned_quantity, planted_quantity, spacing_m, sort_order)
         VALUES (?, ?, ?, 0, ?, ?)'
    );
    $pointInsert = $conn->prepare(
        'INSERT INTO compartment_boundary_points (compartment_id, point_order, point_label, coordinate, recorded_by)
         VALUES (?, ?, ?, ST_GeomFromText(?), ?)'
    );
    $historyInsert = $conn->prepare(
        'INSERT INTO compartment_status_history (compartment_id, status, notes, recorded_by) VALUES (?, ?, ?, ?)'
    );
    if (!$speciesInsert || !$pointInsert || !$historyInsert) {
        throw new RuntimeException($conn->error);
    }

    foreach ($validatedSpecies as $species) {
        $speciesInsert->bind_param('iiidi', $compartmentId, $species['id'], $species['quantity'], $species['spacing'], $species['sort_order']);
        if (!$speciesInsert->execute()) {
            throw new RuntimeException($speciesInsert->error);
        }
    }
    foreach (array_slice($normalisedRing, 0, -1) as $position => $point) {
        $order = $position + 1;
        $label = 'Corner ' . $order;
        $pointWkt = sprintf('POINT(%.8F %.8F)', $point[0], $point[1]);
        $pointInsert->bind_param('iissi', $compartmentId, $order, $label, $pointWkt, $createdBy);
        if (!$pointInsert->execute()) {
            throw new RuntimeException($pointInsert->error);
        }
    }
    $notes = 'Compartment created from a drawn map boundary.';
    $historyInsert->bind_param('issi', $compartmentId, $status, $notes, $createdBy);
    if (!$historyInsert->execute()) {
        throw new RuntimeException($historyInsert->error);
    }
    $speciesInsert->close();
    $pointInsert->close();
    $historyInsert->close();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'compartment_id' => $compartmentId,
        'gross_area_ha' => round($areaHa, 4),
        'barangay' => $barangay ? [
            'id' => (int) $barangay['id'],
            'name' => $barangay['name'],
            'municipality' => $barangay['municipality_name'],
            'province' => $barangay['province_name'],
        ] : null,
    ]);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['success' => false, 'error' => $exception->getMessage() ?: 'Unable to save the compartment.']);
}

$conn->close();
