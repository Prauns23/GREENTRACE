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
    <link rel="stylesheet" href="../species_modal.css">
</head>

<body>
    <div class="modal-wrapper" style="max-width:500px; max-height:340px;">
        <div class="modal-header">
            <h2><?= $action === 'restore' ? 'Unarchive' : 'Archive' ?> <?= htmlspecialchars($species['name'] ?? 'species') ?>?</h2>
        </div>
        <div class="modal-body confirm-box">
            <p>
                <?= $action === 'restore'
                    ? 'This species will be visible in the catalog and AR selection again.'
                    : 'This species will be hidden from the catalog and AR selection. Its details will be preserved, and you can restore it later.' ?>
            </p>
            <div id="confirm-error" class="form-error"></div>
        </div>
        <div class="modal-footer" style="justify-content:center;">
            <button class="btn-cancel" onclick="parent.hideFloating()">Cancel</button>
            <button class="btn-primary" id="confirmAction" data-id="<?= $id ?>" data-action="<?= $action ?>"><?= $action === 'restore' ? 'Unarchive' : 'Archive' ?></button>
        </div>
    </div>

    <script src="../species_confirm.js"></script>
</body>

</html>