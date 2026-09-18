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

function updateFail(string $message, int $status = 422): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function editPolygonAreaSquareMetres(array $ring): float {
    $latitude = array_sum(array_column($ring, 1)) / count($ring);
    $longitudeOrigin = $ring[0][0];
    $latitudeOrigin = $ring[0][1];
    $metresPerLongitude = 111320 * cos(deg2rad($latitude));
    $area = 0.0;
    for ($index = 0, $last = count($ring) - 1; $index < $last; $index++) {
        $next = $index + 1;
        $x1 = ($ring[$index][0] - $longitudeOrigin) * $metresPerLongitude;
        $y1 = ($ring[$index][1] - $latitudeOrigin) * 110574;
        $x2 = ($ring[$next][0] - $longitudeOrigin) * $metresPerLongitude;
        $y2 = ($ring[$next][1] - $latitudeOrigin) * 110574;
        $area += ($x1 * $y2) - ($x2 * $y1);
    }
    return abs($area) / 2;
}

// Validate the details before changing the stored geometry.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$name = trim((string) ($_POST['name'] ?? ''));
$status = (string) ($_POST['status'] ?? '');
$dateStarted = (string) ($_POST['date_started'] ?? '');
$speciesMix = json_decode((string) ($_POST['species_mix'] ?? ''), true);
$geometry = json_decode((string) ($_POST['boundary_geojson'] ?? ''), true);
$allowedStatuses = ['draft', 'planned', 'planted', 'monitored', 'low_survival', 'completed'];
if (!$id || $name === '' || mb_strlen($name) > 150) updateFail('A compartment name of up to 150 characters is required.');
if (!in_array($status, $allowedStatuses, true)) updateFail('The selected status is invalid.');
$startedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStarted);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$startedDate || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count'])) || $startedDate->format('Y-m-d') !== $dateStarted) updateFail('A valid start date is required.');
if ($startedDate > new DateTimeImmutable('today')) updateFail('The start date cannot be later than today.');
if (!is_array($speciesMix) || !$speciesMix) updateFail('Add at least one tree species.');
$ring = ($geometry['type'] ?? '') === 'Polygon' ? ($geometry['coordinates'][0] ?? null) : null;
if (!is_array($ring) || count($ring) < 4) updateFail('The compartment boundary needs at least three corners.');

$normalisedRing = [];
foreach ($ring as $coordinate) {
    if (!is_array($coordinate) || count($coordinate) < 2 || !is_numeric($coordinate[0]) || !is_numeric($coordinate[1])) updateFail('The boundary contains an invalid coordinate.');
    $longitude = (float) $coordinate[0];
    $latitude = (float) $coordinate[1];
    if ($longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) updateFail('The boundary is outside valid longitude/latitude limits.');
    $normalisedRing[] = [$longitude, $latitude];
}
if ($normalisedRing[0] !== $normalisedRing[count($normalisedRing) - 1]) $normalisedRing[] = $normalisedRing[0];
$corners = array_slice($normalisedRing, 0, -1);
if (count(array_unique(array_map(static fn(array $point): string => implode(',', $point), $corners))) < 3) updateFail('A compartment needs at least three distinct corners.');

$conn->begin_transaction();
try {
    // Repositioning keeps the same number of corners. Adding a corner is not allowed here.
    $existingStatement = $conn->prepare('SELECT status, (SELECT COUNT(*) FROM compartment_boundary_points WHERE compartment_id = rc.id) AS corner_count FROM reforestation_compartments rc WHERE id = ? AND archived = 0 FOR UPDATE');
    $existingStatement->bind_param('i', $id);
    $existingStatement->execute();
    $existing = $existingStatement->get_result()->fetch_assoc();
    $existingStatement->close();
    if (!$existing) throw new InvalidArgumentException('This compartment is unavailable.');
    if ((int) $existing['corner_count'] !== count($corners)) throw new InvalidArgumentException('Only existing compartment corners can be repositioned.');

    $speciesIds = [];
    foreach ($speciesMix as $item) {
        $speciesId = filter_var($item['tree_species_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($item['planned_quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$speciesId || !$quantity || isset($speciesIds[$speciesId])) throw new InvalidArgumentException('Each species must be selected once with a valid quantity.');
        $speciesIds[$speciesId] = (int) $quantity;
    }
    $speciesStatement = $conn->prepare('SELECT id, planting_spacing FROM tree_species WHERE id = ? AND archived = 0');
    $speciesRows = [];
    foreach ($speciesIds as $speciesId => $quantity) {
        $speciesStatement->bind_param('i', $speciesId);
        $speciesStatement->execute();
        $tree = $speciesStatement->get_result()->fetch_assoc();
        if (!$tree) throw new InvalidArgumentException('One of the selected tree species is unavailable.');
        $speciesRows[] = ['id' => (int) $tree['id'], 'quantity' => $quantity, 'spacing' => max(0.01, (float) $tree['planting_spacing'])];
    }
    $speciesStatement->close();

    $centreLongitude = array_sum(array_column($normalisedRing, 0)) / count($normalisedRing);
    $centreLatitude = array_sum(array_column($normalisedRing, 1)) / count($normalisedRing);
    $centreWkt = sprintf('POINT(%.8F %.8F)', $centreLongitude, $centreLatitude);
    $barangayStatement = $conn->prepare('SELECT b.id, b.name, b.municipality_name, b.province_name FROM barangay_boundaries bb INNER JOIN barangays b ON b.id = bb.barangay_id WHERE ST_Contains(bb.boundary, ST_GeomFromText(?)) LIMIT 1');
    $barangayStatement->bind_param('s', $centreWkt);
    $barangayStatement->execute();
    $barangay = $barangayStatement->get_result()->fetch_assoc() ?: null;
    $barangayStatement->close();
    $barangayId = $barangay ? (int) $barangay['id'] : null;
    $areaSqm = editPolygonAreaSquareMetres($normalisedRing);
    if ($areaSqm <= 1) throw new InvalidArgumentException('The edited plot is too small to save.');
    $areaHa = $areaSqm / 10000;
    $polygonWkt = 'POLYGON((' . implode(',', array_map(static fn(array $point): string => sprintf('%.8F %.8F', $point[0], $point[1]), $normalisedRing)) . '))';

    $updateStatement = $conn->prepare('UPDATE reforestation_compartments SET barangay_id = ?, name = ?, status = ?, date_started = ?, gross_area_ha = ?, calculated_area_sqm = ?, boundary = ST_GeomFromText(?) WHERE id = ?');
    $updateStatement->bind_param('isssddsi', $barangayId, $name, $status, $dateStarted, $areaHa, $areaSqm, $polygonWkt, $id);
    if (!$updateStatement->execute()) throw new RuntimeException($conn->error);
    $updateStatement->close();

    $conn->query('DELETE FROM compartment_species WHERE compartment_id = ' . (int) $id);
    $conn->query('DELETE FROM compartment_boundary_points WHERE compartment_id = ' . (int) $id);
    $createdBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $speciesInsert = $conn->prepare('INSERT INTO compartment_species (compartment_id, tree_species_id, planned_quantity, planted_quantity, spacing_m, sort_order) VALUES (?, ?, ?, 0, ?, ?)');
    foreach ($speciesRows as $position => $speciesRow) {
        $speciesInsert->bind_param('iiidi', $id, $speciesRow['id'], $speciesRow['quantity'], $speciesRow['spacing'], $position);
        if (!$speciesInsert->execute()) throw new RuntimeException($conn->error);
    }
    $speciesInsert->close();
    $pointInsert = $conn->prepare('INSERT INTO compartment_boundary_points (compartment_id, point_order, point_label, coordinate, recorded_by) VALUES (?, ?, ?, ST_GeomFromText(?), ?)');
    foreach ($corners as $position => $point) {
        $order = $position + 1;
        $label = 'Corner ' . $order;
        $pointWkt = sprintf('POINT(%.8F %.8F)', $point[0], $point[1]);
        $pointInsert->bind_param('iissi', $id, $order, $label, $pointWkt, $createdBy);
        if (!$pointInsert->execute()) throw new RuntimeException($conn->error);
    }
    $pointInsert->close();
    if ($existing['status'] !== $status) {
        $history = $conn->prepare('INSERT INTO compartment_status_history (compartment_id, status, notes, recorded_by) VALUES (?, ?, ?, ?)');
        $note = 'Status changed while editing the compartment.';
        $history->bind_param('issi', $id, $status, $note, $createdBy);
        $history->execute();
        $history->close();
    }
    // Read the database timestamp after the update so the details panel stays accurate.
    $updatedAtStatement = $conn->prepare('SELECT updated_at FROM reforestation_compartments WHERE id = ?');
    $updatedAtStatement->bind_param('i', $id);
    $updatedAtStatement->execute();
    $updatedAt = $updatedAtStatement->get_result()->fetch_assoc();
    $updatedAtStatement->close();
    $conn->commit();
    echo json_encode(['success' => true, 'gross_area_ha' => round($areaHa, 4), 'updated_at' => $updatedAt['updated_at'] ?? null, 'barangay' => $barangay ? ['id' => (int) $barangay['id'], 'name' => $barangay['name'], 'municipality' => $barangay['municipality_name'], 'province' => $barangay['province_name']] : null]);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['success' => false, 'error' => $exception->getMessage() ?: 'Unable to update the compartment.']);
}
$conn->close();
