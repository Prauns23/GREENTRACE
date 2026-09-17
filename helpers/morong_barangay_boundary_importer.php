<?php
declare(strict_types=1);

/**
 * Imports the five Morong, Bataan barangay polygons from the PSA/GeoRisk service.
 *
 * The remote data is stored locally; Forest Map never needs to request this
 * third-party service during normal page loads.
 *
 * @return array{imported:int, skipped:array<int,string>}
 */
function importMorongBarangayBoundaries(mysqli $conn): array
{
    $sourceUrl = 'https://portal.georisk.gov.ph/arcgis/rest/services/PSA/Barangay/MapServer/4/query?'
        . http_build_query([
            'where' => "city_name = 'Morong' AND prov_name = 'Bataan'",
            'outFields' => 'brgy_name,brgy_code,city_name,prov_name',
            'returnGeometry' => 'true',
            'f' => 'geojson',
            'outSR' => '4326',
        ]);

    $curl = curl_init($sourceUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => ['Accept: application/geo+json, application/json'],
    ]);
    $payload = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($payload) || $payload === '' || $status < 200 || $status >= 300) {
        throw new RuntimeException('Could not download Morong barangay boundaries' . ($error ? ': ' . $error : '.'));
    }

    $collection = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    $features = $collection['features'] ?? null;
    if (!is_array($features) || count($features) !== 5) {
        throw new RuntimeException('Expected five Morong barangay boundaries from the source.');
    }

    $findBarangay = $conn->prepare('SELECT id FROM barangays WHERE name = ? LIMIT 1');
    $updateBarangay = $conn->prepare(
        'UPDATE barangays SET psgc_code = ?, municipality_name = ?, province_name = ? WHERE id = ?'
    );
    $upsertBoundary = $conn->prepare(
        "INSERT INTO barangay_boundaries (barangay_id, boundary, source_name, source_version, source_url)
         VALUES (?, ST_GeomFromGeoJSON(?), 'PSA GeoRisk Barangay Boundary', '2016 indicative boundary layer', ?)
         ON DUPLICATE KEY UPDATE
            boundary = VALUES(boundary),
            source_name = VALUES(source_name),
            source_version = VALUES(source_version),
            source_url = VALUES(source_url),
            updated_at = CURRENT_TIMESTAMP"
    );

    $imported = 0;
    $skipped = [];
    $conn->begin_transaction();

    try {
        foreach ($features as $feature) {
            $properties = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? null;
            $name = trim((string) ($properties['brgy_name'] ?? ''));
            $psgcCode = trim((string) ($properties['brgy_code'] ?? ''));
            $municipality = trim((string) ($properties['city_name'] ?? 'Morong'));
            $province = trim((string) ($properties['prov_name'] ?? 'Bataan'));

            if ($name === '' || $psgcCode === '' || !is_array($geometry)) {
                throw new RuntimeException('The boundary source returned an incomplete feature.');
            }

            // The database column is MULTIPOLYGON; wrap normal Polygon geometry.
            if (($geometry['type'] ?? '') === 'Polygon') {
                $geometry = ['type' => 'MultiPolygon', 'coordinates' => [$geometry['coordinates']]];
            }
            if (($geometry['type'] ?? '') !== 'MultiPolygon') {
                throw new RuntimeException('Unsupported geometry returned for ' . $name . '.');
            }

            $findBarangay->bind_param('s', $name);
            $findBarangay->execute();
            $barangay = $findBarangay->get_result()->fetch_assoc();
            if (!$barangay) {
                $skipped[] = $name;
                continue;
            }

            $barangayId = (int) $barangay['id'];
            $geometryJson = json_encode($geometry, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            $updateBarangay->bind_param('sssi', $psgcCode, $municipality, $province, $barangayId);
            $updateBarangay->execute();
            $upsertBoundary->bind_param('iss', $barangayId, $geometryJson, $sourceUrl);
            $upsertBoundary->execute();
            $imported++;
        }
        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    } finally {
        $findBarangay->close();
        $updateBarangay->close();
        $upsertBoundary->close();
    }

    return ['imported' => $imported, 'skipped' => $skipped];
}
