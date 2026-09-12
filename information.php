<?php
require_once 'init_session.php';
include 'header.php';
require_once 'config.php';
require_once __DIR__ . '/components/tree_growth.php';

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
$treeGrowthState = treeGrowthState(
    $conn,
    isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null
);
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

        <section class="grow-tree-container" aria-labelledby="grow-tree-title" data-tree-growth-root data-authenticated="<?= $treeGrowthState['authenticated'] ? 'true' : 'false' ?>" data-water-endpoint="actions/water_tree.php">
            <header class="grow-header">
                <h2 id="grow-tree-title">Grow a Tree</h2>
                <p>Choose a tree to tend and see its growing phase. Remember to visit each day to water your sapling! Additionally earn a badge to show off!</p>
            </header>

            <div class="grow-grid">
                <div class="grow-grid-left">
                    <article class="tree-grove-card" data-tree-card aria-label="Your grove: <?= htmlspecialchars($treeGrowthState['name']) ?> at day <?= (int) $treeGrowthState['day'] ?> of <?= (int) $treeGrowthState['duration'] ?>">
                        <header class="tree-grove-card__header">
                            <span>Your Grove</span>
                            <span data-tree-day-label>Day <?= (int) $treeGrowthState['day'] ?> of <?= (int) $treeGrowthState['duration'] ?></span>
                        </header>
                        <div class="tree-grove-card__phase" role="button" tabindex="0" aria-label="Open <?= htmlspecialchars($treeGrowthState['name']) ?> tree tending details" data-grove-trigger onclick="showGroveCard()" data-tree-illustration-host>
                            <?= treeGrowthIllustrationSvg((int) $treeGrowthState['day']) ?>
                        </div>
                        <footer class="tree-grove-card__footer">
                            <div>
                                <h3 data-tree-name><?= htmlspecialchars($treeGrowthState['name']) ?></h3>
                                <p><span data-tree-scientific><?= htmlspecialchars($treeGrowthState['scientificName']) ?></span> · <span data-tree-category><?= htmlspecialchars($treeGrowthState['category']) ?></span></p>
                            </div>
                            <button type="button" class="tree-water-button" data-tree-water data-watered-today="<?= $treeGrowthState['wateredToday'] ? 'true' : 'false' ?>" aria-disabled="<?= $treeGrowthState['canWater'] ? 'false' : 'true' ?>" aria-label="<?= htmlspecialchars($treeGrowthState['canWater'] ? 'Water ' . $treeGrowthState['name'] : $treeGrowthState['message']) ?>" data-tooltip="<?= htmlspecialchars($treeGrowthState['wateredToday'] ? 'Already watered' : ($treeGrowthState['canWater'] ? 'Click to water' : $treeGrowthState['message'])) ?>">
                                <i class="fa-solid fa-heart" aria-hidden="true"></i>
                            </button>
                        </footer>
                    </article>
                </div>

                <div class="grow-grid-right">
                    <div class="tree-stat-grid" aria-label="Tree tending statistics">
                        <article class="tree-stat-card tree-stat-card--bright"><strong data-tree-progress-stat><?= (int) $treeGrowthState['day'] ?></strong><span>Progress</span></article>
                        <article class="tree-stat-card"><strong data-tree-matured-stat><?= (int) $treeGrowthState['maturedCount'] ?></strong><span>Matured trees</span></article>
                    </div>

                    <article class="tree-selection">
                        <h3>Plant a new tree</h3>
                        <p>Available when your current tree finishes growing</p>
                        <div class="tree-selection__choices" aria-label="Tree choices">
                            <button type="button" class="tree-choice tree-choice--current" data-tree-choice <?= $treeGrowthState['authenticated'] ? 'disabled' : 'aria-label="Sign in to select Narra"' ?>><strong>Narra</strong><span>7 days · Native</span></button>
                            <button type="button" class="tree-choice" data-tree-choice <?= $treeGrowthState['authenticated'] ? 'disabled' : 'aria-label="Sign in to select Mahogany"' ?>><strong>Mahogany</strong><span>5 days · Introduced</span></button>
                            <button type="button" class="tree-choice" data-tree-choice <?= $treeGrowthState['authenticated'] ? 'disabled' : 'aria-label="Sign in to select Fire Tree"' ?>><strong>Fire Tree</strong><span>5 days · Introduced</span></button>
                            <button type="button" class="tree-choice" data-tree-choice <?= $treeGrowthState['authenticated'] ? 'disabled' : 'aria-label="Sign in to select Apitong"' ?>><strong>Apitong</strong><span>7 days · Native</span></button>
                            <button type="button" class="tree-choice" data-tree-choice <?= $treeGrowthState['authenticated'] ? 'disabled' : 'aria-label="Sign in to select Lauan"' ?>><strong>Lauan</strong><span>7 days · Native</span></button>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Field Notes -->

        <section class="field-notes-section" aria-labelledby="field-notes-title">
            <div class="notes-header">
                <h3 id="field-notes-title">Field Notes</h3>
                <p>Short facts from foresters and ecologists, plus a timeline of Philippines Reforestation.</p>
            </div>
            <div class="card-notes-grid" data-field-notes-grid data-notes-endpoint="actions/field_notes.php?limit=6">
                <div class="field-notes-column">
                    <article class="field-note-card field-note-card--accent" data-note-tone="accent">
                        <h4>Note: 01</h4>
                        <p>Rainforestation uses only native tree species to restore forest structure and biodiversity.</p>
                    </article>
                    <article class="field-note-card" data-note-tone="neutral">
                        <h4>Note: 02</h4>
                        <p>The cloud rat of Luzon disperses seeds of at least 22 native tree species.</p>
                    </article>
                    <article class="field-note-card field-note-card--accent" data-note-tone="accent">
                        <h4>Note: 03</h4>
                        <p>Tikbalang folklore once protected groves — villages avoided cutting large balete trees.</p>
                    </article>
                </div>
                <div class="field-notes-column">
                    <article class="field-note-card" data-note-tone="neutral">
                        <h4>Note: 04</h4>
                        <p>Endemic Philippine eagles need about 7,000 hectares of forest for each breeding pair.</p>
                    </article>
                    <article class="field-note-card field-note-card--accent" data-note-tone="accent">
                        <h4>Note: 05</h4>
                        <p>The Philippines lost roughly 90% of its primary forest cover during the 20th century.</p>
                    </article>
                    <article class="field-note-card" data-note-tone="neutral">
                        <h4>Note: 06</h4>
                        <p>Bamboo is technically a grass; many Philippine kawayan species can sequester carbon quickly.</p>
                    </article>
                </div>
            </div>
            <p class="field-notes-status visually-hidden" data-field-notes-status aria-live="polite"></p>
        </section>

        <!-- Short history of Philippine reforestation -->
        <section class="history-section" aria-labelledby="history-title">
            <header class="history-header">
                <h2 id="history-title">Short history of Philippine reforestation</h2>
                <p>A few milestones that shaped forest protection, community stewardship, and restoration in the Philippines.</p>
            </header>

            <div class="main-history-content">
                <div class="history-timeline">
                    <ol class="history-timeline__column">
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="1975">1975</time>
                                <p>The Revised Forestry Code placed protection, rehabilitation, and development of forest lands among the State’s forestry policies.</p>
                                <a href="https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/26/20632" target="_blank" rel="noopener noreferrer">P.D. No. 705 <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="1992">1992</time>
                                <p>Visayas State University established a Rainforestation research farm in Leyte, advancing native-tree restoration with local livelihoods.</p>
                                <a href="https://rainforestation.vsu.edu.ph/?page_id=2614" target="_blank" rel="noopener noreferrer">Visayas State University <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="1995">1995</time>
                                <p>Executive Order No. 263 adopted community-based forest management as the national strategy for sustainable forestlands.</p>
                                <a href="https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/5/62977" target="_blank" rel="noopener noreferrer">E.O. No. 263 <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="2011">2011</time>
                                <p>Executive Order No. 26 launched the National Greening Program, targeting 1.5 billion trees across 1.5 million hectares from 2011 to 2016.</p>
                                <a href="https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/5/34112" target="_blank" rel="noopener noreferrer">E.O. No. 26 <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="2015">2015</time>
                                <p>Executive Order No. 193 expanded the program to remaining unproductive, denuded, and degraded forestlands through 2028.</p>
                                <a href="https://fmb.denr.gov.ph/ngp/wp-content/uploads/2022/10/20151112-EO-0193-BSA.pdf" target="_blank" rel="noopener noreferrer">E.O. No. 193 <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                    </ol>

                    <ol class="history-timeline__column" start="6">
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="2016">2016</time>
                                <p>DENR reports that 1.6 million hectares had been planted by the end of the National Greening Program’s initial 2011–2016 phase.</p>
                                <a href="https://fmb.denr.gov.ph/ngp/wp-content/uploads/2023/11/DAO-2023-09.pdf" target="_blank" rel="noopener noreferrer">DENR progress record <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="2020">2020</time>
                                <p>DENR’s Forestland Management and Integrated Natural Resources projects rehabilitated 23,856 hectares of denuded and degraded forestland across seven major river basins.</p>
                                <a href="https://denr.gov.ph/wp-content/uploads/2023/05/Foreign-Assisted_and_Special_Projects_opt.pdf" target="_blank" rel="noopener noreferrer">DENR 2020 Annual Report <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="2021">2021</time>
                                <p>DENR reported planting 95,666 hectares with 70.72 million seedlings under the Enhanced National Greening Program.</p>
                                <a href="https://www.denr.gov.ph/wp-content/uploads/2024/02/DENR-Annual-Report-for-FY2021.pdf" target="_blank" rel="noopener noreferrer">DENR 2021 Annual Report <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="2025">2025</time>
                                <p>DENR launched Forests for Life: 5 Million Trees by 2028, a nationwide reforestation initiative that drew pledges beyond its original target.</p>
                                <a href="https://fmb.denr.gov.ph/ffl/?p=1163" target="_blank" rel="noopener noreferrer">Forests for Life launch <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                        <li class="history-milestone">
                            <span class="history-milestone__marker" aria-hidden="true"></span>
                            <div>
                                <time datetime="2026">2026</time>
                                <p>NGP regional coordinators met to shape the program’s design framework and update the national reforestation policy plan.</p>
                                <a href="https://forestry.denr.gov.ph/fmb_web/news-and-events/national-greening-program-ngp-regional-coordinators-consultation-on-the-formulation-of-ngp-design-framework-and-updating-of-reforestation-policy-plan/" target="_blank" rel="noopener noreferrer">FMB consultation <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </section>
    </div>
    <script src="information.js" defer></script>
    <?php include 'footer.php'; ?>
</body>
