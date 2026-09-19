<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('<p style="padding:24px;font-family:Inter,sans-serif">Administrator access is required.</p>');
}

// Confirm that photos are always attached to an active reforestation compartment.
$compartmentId = filter_input(INPUT_GET, 'compartment_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$compartmentId) {
    http_response_code(404);
    exit('<p style="padding:24px;font-family:Inter,sans-serif">Compartment not found.</p>');
}
$statement = $conn->prepare('SELECT id, status FROM reforestation_compartments WHERE id = ? AND archived = 0 LIMIT 1');
$statement->bind_param('i', $compartmentId);
$statement->execute();
$compartment = $statement->get_result()->fetch_assoc();
$statement->close();
$conn->close();
if (!$compartment) {
    http_response_code(404);
    exit('<p style="padding:24px;font-family:Inter,sans-serif">Compartment not found.</p>');
}

$categories = [
    'planned' => 'Planned',
    'planted' => 'Planted',
    'monitored' => 'Monitored',
    'low_survival' => 'Low survival',
    'completed' => 'Completed',
    'other' => 'Other',
];
$requestedCategory = $_GET['category'] ?? $compartment['status'];
$requestedCategory = $requestedCategory === 'draft' ? 'other' : $requestedCategory;
$selectedCategory = array_key_exists($requestedCategory, $categories) ? $requestedCategory : $compartment['status'];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Upload reforestation photos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="upload_reforestation_photos.css?v=1">
</head>

<body>
    <main class="photo-modal" aria-labelledby="uploadPhotoTitle">
        <header class="photo-modal__header">
            <h1 id="uploadPhotoTitle">Upload photo</h1>
        </header>

        <!-- Files are validated here, then sent to the Map V2 storage action. -->
        <form class="photo-modal__content" id="uploadPhotosForm">
            <label class="photo-field">
                <span>Category</span>
                <span class="photo-select">
                    <select id="uploadPhotoCategory" name="category">
                        <?php foreach ($categories as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $value === $selectedCategory ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </span>
            </label>

            <section class="photo-field" aria-labelledby="photoDocumentationLabel">
                <span id="photoDocumentationLabel">Photo documentation</span>
                <label class="photo-dropzone" id="uploadPhotoDropzone" for="uploadPhotoInput">
                    <input id="uploadPhotoInput" type="file" accept="image/png,image/jpeg" multiple hidden>
                    <strong>Upload photos or drag and drop</strong>
                    <small>PNG, JPG up to 10 MB each</small>
                </label>
            </section>

            <div class="photo-selection-actions">
                <span id="uploadPhotoCount"><i class="fa-regular fa-images" aria-hidden="true"></i> 0 / 5</span>
                <button type="button" id="clearUploadPhotos"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Clear all</button>
            </div>
            <div class="photo-preview-list" id="uploadPhotoPreviewList" aria-live="polite"></div>
        </form>

        <footer class="photo-modal__footer">
            <button type="button" class="photo-button photo-button--secondary" id="cancelUploadPhotos">Cancel</button>
            <button type="submit" form="uploadPhotosForm" class="photo-button photo-button--primary" id="addUploadPhotos">Add</button>
        </footer>
    </main>
    <script>
        window.mapV2UploadPhotoConfig = {
            compartmentId: <?= (int) $compartmentId ?>,
            maxFiles: 5
        };
    </script>
    <script src="upload_reforestation_photos.js?v=1"></script>
</body>

</html>