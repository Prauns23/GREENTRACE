<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('<p style="padding:24px;font-family:Inter,sans-serif">Administrator access is required.</p>');
}

// Load the saved details once so the edit form starts with the current values.
$compartmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$compartmentId) {
    http_response_code(404);
    exit('<p style="padding:24px;font-family:Inter,sans-serif">Compartment not found.</p>');
}

$compartmentStatement = $conn->prepare(
    'SELECT id, name, status, date_started FROM reforestation_compartments WHERE id = ? AND archived = 0 LIMIT 1'
);
$compartmentStatement->bind_param('i', $compartmentId);
$compartmentStatement->execute();
$compartment = $compartmentStatement->get_result()->fetch_assoc();
$compartmentStatement->close();
if (!$compartment) {
    http_response_code(404);
    exit('<p style="padding:24px;font-family:Inter,sans-serif">Compartment not found.</p>');
}

$species = [];
$speciesResult = $conn->query("SELECT id, name, scientific_name, planting_spacing FROM tree_species WHERE archived = 0 ORDER BY name ASC");
while ($row = $speciesResult->fetch_assoc()) {
    $species[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'scientific_name' => $row['scientific_name'],
        'spacing_m' => (float) $row['planting_spacing'],
    ];
}

$mix = [];
$mixStatement = $conn->prepare(
    'SELECT tree_species_id, planned_quantity FROM compartment_species WHERE compartment_id = ? ORDER BY sort_order, id'
);
$mixStatement->bind_param('i', $compartmentId);
$mixStatement->execute();
$mixResult = $mixStatement->get_result();
while ($row = $mixResult->fetch_assoc()) {
    $mix[] = ['tree_species_id' => (int) $row['tree_species_id'], 'planned_quantity' => (int) $row['planned_quantity']];
}
$mixStatement->close();
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Edit Reforestation Compartment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="edit_reforestation_compartment.css?v=1">
</head>
<body>
    <main class="compartment-modal" aria-labelledby="editCompartmentModalTitle">
        <header class="compartment-modal__header">
            <div>
                <h1 id="editCompartmentModalTitle">Edit Reforestation Compartment</h1>
                <p>Update the details, then reposition the existing plot corners on the map.</p>
            </div>
        </header>

        <!-- These values are saved only after the existing corners are repositioned. -->
        <form id="editCompartmentForm" class="compartment-modal__form">
            <label class="compartment-field"><span class="compartment-field__label">Compartment name <b aria-hidden="true">*</b></span>
                <input name="name" maxlength="150" required value="<?= htmlspecialchars($compartment['name'], ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <div class="compartment-form-row">
                <label class="compartment-field"><span class="compartment-field__label">Plot status <b aria-hidden="true">*</b></span>
                    <div class="compartment-select">
                        <select name="status" required>
                            <?php foreach (['planned' => 'Planned', 'planted' => 'Planted', 'monitored' => 'Monitored', 'low_survival' => 'Low survival', 'completed' => 'Completed'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $compartment['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down compartment-select__chevron" aria-hidden="true"></i>
                    </div>
                </label>
                <label class="compartment-field"><span class="compartment-field__label">Date started <b aria-hidden="true">*</b></span>
                    <input name="date_started" type="date" value="<?= htmlspecialchars((string) $compartment['date_started'], ENT_QUOTES, 'UTF-8') ?>" min="<?= date('Y-m-d') ?>" required>
                </label>
            </div>
            <section class="species-mix" aria-labelledby="editSpeciesMixTitle">
                <div>
                    <h2 id="editSpeciesMixTitle">Species Mix</h2>
                    <p>Update the planned seedlings. Each species uses its saved planting distance.</p>
                </div>
                <div id="speciesRows" class="species-mix__rows"></div>
                <button type="button" class="species-mix__add" id="addSpeciesRow">Add species</button>
            </section>
        </form>
        <footer class="compartment-modal__footer">
            <button type="button" class="button button--secondary" id="cancelEditCompartmentModal">Cancel</button>
            <button type="submit" form="editCompartmentForm" class="button button--primary">Save</button>
        </footer>
    </main>
    <script>
        window.mapV2Species = <?= json_encode($species, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.mapV2EditCompartment = <?= json_encode([
            'id' => (int) $compartment['id'],
            'species_mix' => $mix,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="edit_reforestation_compartment.js?v=1"></script>
</body>
</html>
