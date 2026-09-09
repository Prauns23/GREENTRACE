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
$sql = 'SELECT * FROM tree_species WHERE archived = ' . ($showArchived ? '1' : '0');
$params = [];
$types = "";

if ($category !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}
if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR scientific_name LIKE ? OR description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
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
                <form method="get" action="" id="searchForm">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                    <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
                    <input type="text" name="search" id="searchInput" placeholder="Search species" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </form>
            </div>
            <form method="get" action="" class="category-filter" id="categoryFilter">
                <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"><?php endif; ?>
                <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
                <label class="visually-hidden" for="categorySelect">Filter by category</label>
                <select name="category" id="categorySelect" aria-label="Filter tree species by category">
                    <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="native" <?= $category === 'native' ? 'selected' : '' ?>>Native</option>
                    <option value="introduced" <?= $category === 'introduced' ? 'selected' : '' ?>>Introduced</option>
                </select>
            </form>
            <?php if ($canManageSpecies): ?>
                <nav class="species-status-filter" aria-label="Filter species by archive status">
                    <a href="?<?= htmlspecialchars(http_build_query(['category' => $category, 'search' => $search])) ?>" class="<?= !$showArchived ? 'active' : '' ?>" <?= !$showArchived ? 'aria-current="page"' : '' ?>>Active</a>
                    <a href="?<?= htmlspecialchars(http_build_query(['category' => $category, 'search' => $search, 'archived' => '1'])) ?>" class="<?= $showArchived ? 'active' : '' ?>" <?= $showArchived ? 'aria-current="page"' : '' ?>>Archived</a>
                </nav>
            <?php endif; ?>
        </div>
        <p id="species-page-status" class="species-page-status" role="status"><?= $showArchived ? 'Archived species are hidden from the public catalog and AR selection.' : '' ?></p>

        <div class="species-grid">
            <?php if (count($species) === 0): ?>
                <div class="no-results">
                    <img src="pages\no-results.svg" alt="" class="no-result-img">
                    <h3>No species found</h3>
                    <p><?= $search !== '' ? 'Your search “' . htmlspecialchars($search) . '” did not match any tree species.' : ($showArchived ? 'There are no archived species for this category.' : 'There are no active species for this category.') ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($species as $item): ?>
                    <div class="species-card" data-id="<?php echo $item['id']; ?>">
                        <?php if ($canManageSpecies): ?>
                            <div class="species-actions">
                                <button type="button" class="species-menu-trigger" aria-label="Actions for <?= htmlspecialchars($item['name'], ENT_QUOTES) ?>" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                                <div class="species-menu" hidden>
                                    <?php if ($showArchived): ?>
                                        <button type="button" data-species-action="restore" data-id="<?= (int)$item['id'] ?>">Unarchive</button>
                                    <?php else: ?>
                                        <button type="button" data-species-action="edit" data-id="<?= (int)$item['id'] ?>">Edit</button>
                                        <button type="button" data-species-action="archive" data-id="<?= (int)$item['id'] ?>">Archive</button>
                                    <?php endif; ?>
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
            <?php endif; ?>
            <?php if ($canManageSpecies && !$showArchived): ?>
                <button type="button" class="species-add-card" id="species-add"><i class="fa-solid fa-plus" aria-hidden="true"></i><span>Click to add a species</span></button>
            <?php endif; ?>
        </div>

        <?php include __DIR__ . '/modals/species_management.php'; ?>

        <!-- Let's introduce the watering page here; we can reuse the tree species for each trees that need to be watered or to choose from -->

        <!-- Quiz part -->

        

    </div>
    <!-- We shall create separated script file for this page since this will have a larger scope -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchForm = document.getElementById('searchForm');
            const categoryFilter = document.getElementById('categoryFilter');
            const categorySelect = document.getElementById('categorySelect');
            let debounceTimer;

            if (searchInput && searchForm) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        searchForm.submit();
                    }, 400);
                });
            }

            if (categoryFilter && categorySelect) {
                categorySelect.addEventListener('change', () => categoryFilter.submit());
            }
        });
    </script>

    <script>
        document.querySelectorAll('.species-card').forEach(card => {
            card.addEventListener('click', (event) => {
                if (event.target.closest('.species-actions')) return;
                const id = card.dataset.id;
                if (id && window.showSpeciesDetail) showSpeciesDetail(id);
            });
        });
    </script>
    <script src="species-management.js" defer></script>

    <?php include 'footer.php'; ?>
</body>
