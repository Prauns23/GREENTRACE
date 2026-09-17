<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This maintenance importer may only be run from the command line.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/morong_barangay_boundary_importer.php';

try {
    $result = importMorongBarangayBoundaries($conn);
    echo "Imported {$result['imported']} Morong barangay boundaries.\n";
    if ($result['skipped']) {
        echo 'No matching local barangay: ' . implode(', ', $result['skipped']) . "\n";
    }
} finally {
    $conn->close();
}
