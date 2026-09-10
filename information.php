<?php
require_once 'init_session.php';
include 'header.php';
require_once 'config.php';

// Get filters from URL
$category = is_string($_GET['category'] ?? null) ? $_GET['category'] : 'all';
if (!in_array($category, ['all', 'native', 'introduced'], true)) $category = 'all';
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$canManageSpecies = isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true);
$showArchived = $canManageSpecies && ($_GET['archived'] ?? '') === '1';

// Build query
$conditions = [];
$sql = 'SELECT * FROM tree_species';
$params = [];
$types = "";

if (!$canManageSpecies) {
    $conditions[] = 'archived = 0';
}

if ($category !== 'all') {
    $conditions[] = 'category = ?';
    $params[] = $category;
    $types .= "s";
}
if (!empty($search)) {
    $conditions[] = '(name LIKE ? OR scientific_name LIKE ? OR description LIKE ?)';
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= " ORDER BY name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$species = $result->fetch_all(MYSQLI_ASSOC);
?>

<link rel="stylesheet" href="information.css?v=<?= filemtime(__DIR__ . '/information.css') ?>">

<body>
    <div class="information-page">
        <div class="species-header">
            <h1>Tree Species</h1>
            <p>Explore the tree species used in our reforestation efforts, including their characteristics, ecological benefits, and planting requirements.</p>
        </div>

        <div class="filters">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <form method="get" action="" id="searchForm">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                    <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
                    <input type="text" name="search" id="searchInput" placeholder="Search species" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </form>
            </div>
            <!-- Custom category filter dropdown -->
            <div class="category-filter-wrapper">
                <button type="button" class="category-filter-toggle" id="categoryFilterToggle" aria-haspopup="true" aria-expanded="false">
                    <span id="categoryFilterLabel"><?= ucfirst($category) ?></span>
                    <i class="fas fa-chevron-down filter-chevron" aria-hidden="true"></i>
                </button>
                <div class="category-filter-dropdown" id="categoryFilterDropdown" role="menu" hidden>
                    <button type="button" class="filter-btn <?= $category === 'all' ? 'active' : '' ?>" data-category="all" role="menuitem" aria-current="<?= $category === 'all' ? 'true' : 'false' ?>">All</button>
                    <button type="button" class="filter-btn <?= $category === 'native' ? 'active' : '' ?>" data-category="native" role="menuitem" aria-current="<?= $category === 'native' ? 'true' : 'false' ?>">Native</button>
                    <button type="button" class="filter-btn <?= $category === 'introduced' ? 'active' : '' ?>" data-category="introduced" role="menuitem" aria-current="<?= $category === 'introduced' ? 'true' : 'false' ?>">Introduced</button>
                </div>
                <form method="get" action="" id="categoryForm">
                    <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"><?php endif; ?>
                    <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
                    <input type="hidden" name="category" id="categoryInput" value="<?= $category ?>">
                </form>
            </div>
            <?php if ($canManageSpecies): ?>
                <nav class="species-status-filter" role="tablist" aria-label="Filter species by archive status" data-active="<?= $showArchived ? 'archived' : 'active' ?>">
                    <a href="?<?= htmlspecialchars(http_build_query(['category' => $category, 'search' => $search])) ?>" class="<?= !$showArchived ? 'active' : '' ?>" role="tab" aria-selected="<?= !$showArchived ? 'true' : 'false' ?>" data-status="active" <?= !$showArchived ? 'aria-current="page"' : '' ?>>Active</a>
                    <a href="?<?= htmlspecialchars(http_build_query(['category' => $category, 'search' => $search, 'archived' => '1'])) ?>" class="<?= $showArchived ? 'active' : '' ?>" role="tab" aria-selected="<?= $showArchived ? 'true' : 'false' ?>" data-status="archived" <?= $showArchived ? 'aria-current="page"' : '' ?>>Archived</a>
                </nav>
            <?php endif; ?>
        </div>

        <div class="species-grid">
            <?php foreach ($species as $item): ?>
                <div class="species-card" data-id="<?php echo $item['id']; ?>" data-archived="<?= (int)$item['archived'] ?>">
                    <?php if ($canManageSpecies): ?>
                        <div class="species-actions">
                            <div class="species-menu-trigger" onclick="event.stopPropagation(); toggleSpeciesMenu(this)" role="button" tabindex="0" aria-expanded="false">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                <div class="species-menu">
                                    <?php if ((int)$item['archived'] === 1): ?>
                                        <button type="button" onclick="event.stopPropagation(); showSpeciesConfirm(<?= $item['id'] ?>, 'restore')">Unarchive</button>
                                    <?php else: ?>
                                        <button type="button" onclick="event.stopPropagation(); showSpeciesEditor(<?= $item['id'] ?>)">Edit</button>
                                        <button type="button" onclick="event.stopPropagation(); showSpeciesConfirm(<?= $item['id'] ?>, 'archive')">Archive</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($item['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <?php else: ?>
                        <div class="card-image-placeholder"></div>
                    <?php endif; ?>
                    <div class="card-content">
                        <div class="card-header">
                            <h3><button type="button" class="species-detail-trigger" data-id="<?= (int)$item['id'] ?>"><?php echo htmlspecialchars($item['name']); ?></button></h3>
                            <span class="category-badge <?php echo $item['category']; ?>">
                                <?php echo ucfirst($item['category']); ?>
                            </span>
                        </div>
                        <p class="scientific"><?php echo htmlspecialchars($item['scientific_name']); ?></p>
                        <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="no-results" data-species-empty <?= count($species) > 0 ? 'hidden' : '' ?>>
                <img src="pages\no-results.svg" alt="" class="no-result-img">
                <h3>No species found</h3>
                <p><?= $search !== '' ? 'Your search “' . htmlspecialchars($search) . '” did not match any tree species.' : 'There are no species for this category.' ?></p>
            </div>
            <?php if ($canManageSpecies): ?>
                <button type="button" class="species-add-card" id="species-add" onclick="showSpeciesEditor()" <?= $showArchived ? 'hidden' : '' ?>><i class="fa-solid fa-plus" aria-hidden="true"></i><span>Click to add a species</span></button>
            <?php endif; ?>
        </div>

        <section class="grow-tree-container" aria-labelledby="grow-tree-title" data-preview="true">
            <header class="grow-header">
                <h2 id="grow-tree-title">Grow a Tree</h2>
                <p>Choose a tree to tend and see its growing phase. Remember to visit each day to water your sapling! Additionally earn a badge to show off!</p>
            </header>

            <div class="grow-grid">
                <div class="grow-grid-left">
                    <article class="tree-grove-card" aria-label="Your grove preview: Narra at day 4 of 7">
                        <header class="tree-grove-card__header">
                            <span>Your Grove</span>
                            <span>Day 4 of 7</span>
                        </header>
                        <div class="tree-grove-card__phase tree-phase--day-four" role="img" aria-label="Narra at its fourth placeholder growth phase">
                            <svg class="tree-growth-illustration" viewBox="0 0 320 320" aria-hidden="true" focusable="false">
                                <g class="tree-growth-illustration__soil">
                                    <ellipse cx="160" cy="276" rx="120" ry="10" fill="oklch(0.78 0.04 90)" />
                                    <ellipse cx="160" cy="272" rx="80" ry="6" fill="oklch(0.55 0.05 80)" opacity=".5" />
                                </g>
                                <g class="tree-growth-illustration__trunk">
                                    <rect x="152.6" y="168" width="14.8" height="108" rx="7.4" fill="oklch(0.34 0.04 50)" />
                                    <path d="M145.2 276q-10-2-16 2m29.6-2q10-2 16 2" fill="none" stroke="oklch(0.30 0.04 50)" stroke-linecap="round" stroke-width="3" />
                                </g>
                                <g class="tree-growth-illustration__canopy">
                                    <circle cx="160" cy="115" r="74" fill="oklch(0.45 0.09 150)" />
                                    <circle cx="115.6" cy="129.8" r="51.8" fill="oklch(0.5 0.1 148)" />
                                    <circle cx="204.4" cy="129.8" r="51.8" fill="oklch(0.55 0.1 145)" />
                                    <circle cx="160" cy="78" r="40.7" fill="oklch(0.6 0.11 142)" />
                                </g>
                            </svg>
                        </div>
                        <footer class="tree-grove-card__footer">
                            <div>
                                <h3>Narra</h3>
                                <p>Pterocarpus indicus · Native</p>
                            </div>
                            <button type="button" class="tree-water-button" aria-disabled="true" aria-label="Watering is not available in this preview" data-tooltip="Click to water">
                                <i class="fa-solid fa-heart" aria-hidden="true"></i>
                            </button>
                        </footer>
                    </article>
                </div>

                <div class="grow-grid-right">
                    <div class="tree-stat-grid" aria-label="Tree tending preview statistics">
                        <article class="tree-stat-card tree-stat-card--green"><strong>77</strong><span>Exp point</span></article>
                        <article class="tree-stat-card tree-stat-card--bright"><strong>4</strong><span>Progress</span></article>
                        <article class="tree-stat-card"><strong>0</strong><span>Matured trees</span></article>
                    </div>

                    <article class="tree-selection">
                        <h3>Plant a new tree</h3>
                        <p>Available when your current tree finishes growing</p>
                        <div class="tree-selection__choices" aria-label="Placeholder tree choices">
                            <button type="button" class="tree-choice tree-choice--current" disabled><strong>Narra</strong><span>7 days · Native</span></button>
                            <button type="button" class="tree-choice" disabled><strong>Mahogany</strong><span>5 days · Introduced</span></button>
                            <button type="button" class="tree-choice" disabled><strong>Fire Tree</strong><span>5 days · Introduced</span></button>
                            <button type="button" class="tree-choice" disabled><strong>Apitong</strong><span>7 days · Native</span></button>
                            <button type="button" class="tree-choice" disabled><strong>Lauan</strong><span>7 days · Native</span></button>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Field Notes -->

        <!-- Short history of PH Reforestation -->

    </div>
    <script src="information.js" defer></script>
    <?php include 'footer.php'; ?>
</body>