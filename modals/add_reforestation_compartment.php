<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('<p style="padding:24px;font-family:Inter,sans-serif">Administrator access is required.</p>');
}

// Load active species so the user can build a valid species mix.
$species = [];
$result = $conn->query("SELECT id, name, scientific_name, planting_spacing FROM tree_species WHERE archived = 0 ORDER BY name ASC");
while ($row = $result->fetch_assoc()) {
    $species[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'scientific_name' => $row['scientific_name'],
        'spacing_m' => (float) $row['planting_spacing'],
    ];
}
$conn->close();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Add Reforestation Compartment</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="add_reforestation_compartment.css?v=7">
</head>

<body>
    <main class="compartment-modal" aria-labelledby="compartmentModalTitle">
        <header class="compartment-modal__header">
            <div>
                <h1 id="compartmentModalTitle">Add Reforestation Compartment</h1>
                <p>Add the details, then mark at least three plot corners on the map.</p>
            </div>
        </header>

        <!-- This form collects details first; boundary drawing starts after validation. -->
        <form id="addCompartmentForm" class="compartment-modal__form">
            <label class="compartment-field"><span class="compartment-field__label">Compartment name <b aria-hidden="true">*</b></span>
                <input name="name" maxlength="150" required placeholder="E.g., Mount Natib Reforestation Area">
            </label>

            <div class="compartment-form-row">
                <label class="compartment-field"><span class="compartment-field__label">Plot status <b aria-hidden="true">*</b></span>
                    <div class="compartment-select">
                    <select name="status" required>
                        <option value="planned" selected>Planned</option>
                        <option value="planted">Planted</option>
                        <option value="monitored">Monitored</option>
                        <option value="low_survival">Low survival</option>
                        <option value="completed">Completed</option>
                    </select>
                    <i class="fas fa-chevron-down compartment-select__chevron" aria-hidden="true"></i>
                    </div>
                </label>
                <label class="compartment-field"><span class="compartment-field__label">Date started <b aria-hidden="true">*</b></span>
                    <!-- The picker blocks future dates before the user starts drawing. -->
                    <input name="date_started" type="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                </label>
            </div>

            <!-- Each species row carries one planned quantity into the drawing draft. -->
            <section class="species-mix" aria-labelledby="speciesMixTitle">
                <div>
                    <h2 id="speciesMixTitle">Species Mix</h2>
                    <p>Allocate planned seedlings. Each species uses its saved planting distance.</p>
                </div>
                <div id="speciesRows" class="species-mix__rows"></div>
                <button type="button" class="species-mix__add" id="addSpeciesRow"> Add species</button>
            </section>
        </form>

        <footer class="compartment-modal__footer">
            <button type="button" class="button button--secondary" id="cancelCompartmentModal">Cancel</button>
            <button type="submit" form="addCompartmentForm" class="button button--primary">Draw boundary</button>
        </footer>
    </main>
    <script>
        window.mapV2Species = <?= json_encode($species, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="add_reforestation_compartment.js?v=7"></script>
</body>

</html>
