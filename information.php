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

        <!-- Let's introduce the watering page here; we can reuse the tree species for each trees that need to be watered or to choose from -->

        <!-- Field Notes -->

        <!-- Short history of PH Reforestation -->

    </div>
    <script src="information.js" defer></script>
    <?php include 'footer.php'; ?>
</body>