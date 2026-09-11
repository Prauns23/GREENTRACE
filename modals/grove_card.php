<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../components/tree_growth.php';

$treeGrowthState = treeGrowthState(
    $conn,
    isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
);
$dayLabel = 'Day ' . (int) $treeGrowthState['day'] . ' of ' . (int) $treeGrowthState['duration'];
$animateInitialPhase = ($_GET['animate'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($treeGrowthState['name']) ?> Grove</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="grove_card.css?v=<?= filemtime(__DIR__ . '/grove_card.css') ?>">
</head>
<body>
    <main class="grove-modal" role="dialog" aria-modal="true" aria-labelledby="grove-modal-title" data-grove-root data-authenticated="<?= $treeGrowthState['authenticated'] ? 'true' : 'false' ?>" data-water-endpoint="../actions/water_tree.php" data-initial-animation="<?= $animateInitialPhase ? 'true' : 'false' ?>">
        <header class="grove-modal__header">
            <div>
                <h1 id="grove-modal-title" data-grove-name><?= htmlspecialchars($treeGrowthState['name']) ?></h1>
                <p><span data-grove-scientific><?= htmlspecialchars($treeGrowthState['scientificName']) ?></span> <span aria-hidden="true">·</span> <span data-grove-category><?= htmlspecialchars($treeGrowthState['category']) ?></span></p>
            </div>
            <span class="grove-modal__day" data-grove-day><?= htmlspecialchars($dayLabel) ?></span>
            <button type="button" class="grove-modal__close" data-grove-close aria-label="Close grove details">&times;</button>
        </header>

        <section class="grove-modal__content" data-grove-content aria-label="<?= htmlspecialchars($treeGrowthState['name']) ?> tending progress">
            <button type="button" class="grove-tree-action" data-grove-water aria-disabled="<?= $treeGrowthState['canWater'] ? 'false' : 'true' ?>" data-tooltip="<?= htmlspecialchars($treeGrowthState['wateredToday'] ? 'Already watered' : ($treeGrowthState['canWater'] ? 'Click me!' : $treeGrowthState['message'])) ?>" aria-label="<?= htmlspecialchars($treeGrowthState['canWater'] ? 'Water ' . $treeGrowthState['name'] : $treeGrowthState['message']) ?>">
                <span class="grove-tree-action__illustration" data-grove-illustration-host>
                    <?= treeGrowthIllustrationSvg((int) $treeGrowthState['day'], 'grove-tree-illustration') ?>
                </span>
            </button>

            <div class="grove-progress" data-grove-progress aria-label="<?= htmlspecialchars($dayLabel) ?> growth progress">
                <div class="grove-progress__track"><span data-grove-progress-bar style="width: <?= (float) $treeGrowthState['progressPercent'] ?>%"></span></div>
                <strong data-grove-progress-label><?= htmlspecialchars($dayLabel) ?></strong>
                <p data-grove-status aria-live="polite"><?= htmlspecialchars($treeGrowthState['message']) ?></p>
            </div>
        </section>

        <footer class="grove-modal__footer">
            <p class="fun-fact-note" data-grove-fact><?= htmlspecialchars($treeGrowthState['funFact']) ?></p>
        </footer>
    </main>

    <script src="../security.js"></script>
    <script src="grove_card.js?v=<?= filemtime(__DIR__ . '/grove_card.js') ?>"></script>
</body>
</html>
