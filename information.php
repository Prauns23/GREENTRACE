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
                                    <div class="species-menu" style="display: none;">
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

            function applySpeciesStatusFilter(status, updateUrl = false) {
                const filter = document.querySelector('.species-status-filter');
                const grid = document.querySelector('.species-grid');
                if (!filter || !grid) return;

                filter.dataset.active = status;
                filter.querySelectorAll('a[data-status]').forEach(link => {
                    const isActive = link.dataset.status === status;
                    link.classList.toggle('active', isActive);
                    link.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    if (isActive) {
                        link.setAttribute('aria-current', 'page');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                });

                let visibleCards = 0;
                grid.querySelectorAll('.species-card').forEach(card => {
                    const isVisible = card.dataset.archived === (status === 'archived' ? '1' : '0');
                    card.hidden = !isVisible;
                    if (isVisible) visibleCards++;
                });

                const addCard = grid.querySelector('.species-add-card');
                const hasSearch = document.getElementById('searchInput')?.value.trim() !== '';
                if (addCard) addCard.hidden = status === 'archived' || hasSearch || visibleCards === 0;

                let emptyState = grid.querySelector('[data-species-empty]');
                if (!emptyState) {
                    emptyState = document.createElement('div');
                    emptyState.className = 'no-results';
                    emptyState.dataset.speciesEmpty = '';
                    emptyState.innerHTML = '<img src="pages/no-results.svg" alt="" class="no-result-img"><h3>No species found</h3><p></p>';
                    grid.appendChild(emptyState);
                }
                emptyState.querySelector('p').textContent = status === 'archived'
                    ? 'There are no archived species for this category.'
                    : 'There are no active species for this category.';
                emptyState.hidden = visibleCards > 0;

                const pageStatus = document.getElementById('species-page-status');
                if (pageStatus) {
                    pageStatus.textContent = status === 'archived'
                        ? 'Archived species are hidden from the public catalog and AR selection.'
                        : '';
                }

                document.querySelectorAll('form input[name="archived"]').forEach(input => {
                    input.disabled = status !== 'archived';
                });

                if (updateUrl) {
                    const url = new URL(filter.querySelector(`a[data-status="${status}"]`).href, window.location.href);
                    window.history.pushState({}, '', url);
                }
            }

            const speciesStatusFilter = document.querySelector('.species-status-filter');
            if (speciesStatusFilter) {
                speciesStatusFilter.querySelectorAll('a[data-status]').forEach(link => {
                    link.addEventListener('click', event => {
                        event.preventDefault();
                        applySpeciesStatusFilter(link.dataset.status, true);
                    });
                });
                applySpeciesStatusFilter(speciesStatusFilter.dataset.active);
                window.addEventListener('popstate', () => {
                    const status = new URLSearchParams(window.location.search).has('archived') ? 'archived' : 'active';
                    applySpeciesStatusFilter(status);
                });
            }

        function toggleSpeciesMenu(btn) {
            const menu = btn.nextElementSibling;
            const isOpen = menu.style.display === 'block';
            // close all others
            document.querySelectorAll('.species-menu').forEach(m => m.style.display = 'none');
            document.querySelectorAll('.species-menu-trigger').forEach(b => b.setAttribute('aria-expanded', 'false'));
            if (!isOpen) {
                menu.style.display = 'block';
                btn.setAttribute('aria-expanded', 'true');
            }
        }
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
    <?php include 'footer.php'; ?>
</body>