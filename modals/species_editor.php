<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$species = null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM tree_species WHERE id = ?");
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
    <title><?= $species ? 'Edit' : 'Add' ?> Tree Species</title>
    <link rel="stylesheet" href="species_modal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="modal-wrapper">
        <div class="modal-header">
            <h2><?= $species ? 'Edit Tree Species' : 'Add Tree Species' ?></h2>
            <p><?= $species ? 'Update the details of this species.' : 'Add a new tree species for our community to learn from!' ?></p>
        </div>

        <form id="species-form" class="modal-body" enctype="multipart/form-data" action="../actions/manage_species.php" method="post">
            <?php
            require_once __DIR__ . '/../csrf.php';
            echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
            ?>
            <input type="hidden" name="action" value="<?= $species ? 'edit' : 'add' ?>">
            <input type="hidden" name="id" value="<?= $species['id'] ?? '' ?>">

            <!-- Upload area -->
            <div class="upload-area" id="upload-area">
                <div class="upload-preview">
                    <?php if (!empty($species['image_url'])): ?>
                        <img id="species-image-preview" src="../<?= htmlspecialchars($species['image_url']) ?>" alt="Current image" style="display:block;">
                    <?php else: ?>
                        <img id="species-image-preview" style="display:none;">
                    <?php endif; ?>
                    <i class="fas fa-cloud-upload-alt" id="upload-icon" style="font-size:48px; color:#aaa; <?= !empty($species['image_url']) ? 'display:none;' : '' ?>"></i>
                </div>
                <span class="hint">Click to upload or drag & drop a PNG/JPG (max 10 MB)</span>
                <input type="file" name="image" id="species-image" accept="image/jpeg,image/png">
            </div>

            <!-- Form fields -->
            <div class="form-group">
                <label for="species-name">Tree species name <span class="required">*</span></label>
                <input id="species-name" name="name" maxlength="100" placeholder="E.g., Fire Tree" value="<?= htmlspecialchars($species['name'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="species-scientific">Scientific Name <span class="required">*</span></label>
                    <input id="species-scientific" name="scientific_name" maxlength="150" placeholder="E.g., Delonix regia" value="<?= htmlspecialchars($species['scientific_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="species-category">Category <span class="required">*</span></label>
                    <select id="species-category" name="category" required>
                        <option value="">Select Here</option>
                        <option value="native" <?= ($species['category'] ?? '') === 'native' ? 'selected' : '' ?>>Native</option>
                        <option value="introduced" <?= ($species['category'] ?? '') === 'introduced' ? 'selected' : '' ?>>Introduced</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="species-description">Description <span class="required">*</span></label>
                <textarea id="species-description" name="description" rows="4" maxlength="10000" placeholder="Enter a short description …" required><?= htmlspecialchars($species['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="species-importance">Importance <span class="required">*</span></label>
                <textarea id="species-importance" name="importance" rows="3" maxlength="10000" placeholder="One benefit per line" required><?= htmlspecialchars($species['importance'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="species-fact">Add fun fact</label>
                <textarea id="species-fact" name="fun_fact" rows="2" maxlength="2000" placeholder="Add an interesting fact about this tree"><?= htmlspecialchars($species['fun_fact'] ?? '') ?></textarea>
            </div>

            <div id="form-error" class="form-error"></div>
        </form>

        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="parent.hideFloating()">Cancel</button>
            <button type="submit" form="species-form" class="btn-primary"><?= $species ? 'Save changes' : 'Add' ?></button>
        </div>
    </div>

    <script src="../species_editor.js"></script>
</body>

</html>