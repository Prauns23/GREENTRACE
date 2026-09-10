<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'archive';
$species = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT name FROM tree_species WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $species = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm <?= $action === 'restore' ? 'Restore' : 'Archive' ?></title>
    <!-- Add CSRF meta tag for the iframe -->
    <?php
    require_once __DIR__ . '/../csrf.php';
    echo '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    ?>
    <link rel="stylesheet" href="species_modal.css?v=<?= filemtime(__DIR__ . '/species_modal.css') ?>">
</head>

<body>
    <div class="modal-wrapper species-confirm-modal">
        <div class="modal-header">
            <h2><?= $action === 'restore' ? 'Unarchive' : 'Archive' ?> <?= htmlspecialchars($species['name'] ?? 'species') ?>?</h2>
        </div>
        <div class="modal-body confirm-box">
            <p>
                <?php if ($action === 'restore'): ?>
                    This species will be <strong>visible</strong> in the <strong>catalog</strong> and AR selection again.
                <?php else: ?>
                    This species will be <strong>hidden</strong> from the catalog and AR selection. Its details will be preserved, and you can <strong>restore</strong> it later.
                <?php endif; ?>
            </p>
            <div id="confirm-error" class="form-error"></div>
        </div>
        <div class="modal-footer species-confirm-footer">
            <button class="btn-cancel" onclick="parent.hideFloating()">Cancel</button>
            <button class="btn-primary" id="confirmAction" data-id="<?= $id ?>" data-action="<?= $action ?>"><?= $action === 'restore' ? 'Unarchive' : 'Archive' ?></button>
        </div>
    </div>

    <script src="species_confirm.js?v=<?= filemtime(__DIR__ . '/species_confirm.js') ?>"></script>
</body>

</html>
