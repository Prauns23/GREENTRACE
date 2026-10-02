<?php
// Field Notes are intentionally refreshed by the server reset window.
// Prevent browser/proxy caching from showing the previous collection.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'init_session.php';
include 'header.php';
require_once 'config.php';
require_once __DIR__ . '/helpers/tree_growth_helpers.php';
require_once __DIR__ . '/helpers/admin_authorization.php';
$canManageSpecies = hasAdminPermission('tree_species_management');

// Get filters from URL
$category = $_GET['category'] ?? 'all';
$speciesStatus = $_GET['status'] ?? 'active';
$speciesStatus = $canManageSpecies && in_array($speciesStatus, ['active', 'archived'], true)
    ? $speciesStatus
    : 'active';
$search = trim($_GET['search'] ?? '');

// Fetch only the fields used by cards. Filtering in PHP preserves the current
// multi-field search while avoiding the legacy SQL compatibility layer.
$speciesFilters = ['archived' => $speciesStatus === 'archived' ? 'eq.true' : 'eq.false'];
if ($category !== 'all') $speciesFilters['category'] = 'eq.' . $category;
$species = supabaseSelect('tree_species', $speciesFilters, 'id,name,scientific_name,description,category,image_url,tree_species_images(image_url,sort_order,id)', ['order' => 'name.asc']);
foreach ($species as &$speciesItem) {
    $gallery = array_values(array_filter($speciesItem['tree_species_images'] ?? [], static fn($image): bool => is_array($image) && !empty($image['image_url'])));
    usort($gallery, static fn(array $left, array $right): int => ((int)($left['sort_order'] ?? 0) <=> (int)($right['sort_order'] ?? 0)) ?: ((int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0)));
    if (!empty($gallery[0]['image_url'])) $speciesItem['image_url'] = $gallery[0]['image_url'];
    unset($speciesItem['tree_species_images']);
}
unset($speciesItem);
if ($search !== '') {
    $needle = mb_strtolower($search);
    $species = array_values(array_filter(
        $species,
        static fn(array $item): bool =>
        str_contains(mb_strtolower(($item['name'] ?? '') . ' ' . ($item['scientific_name'] ?? '') . ' ' . ($item['description'] ?? '')), $needle)
    ));
}
$treeGrowth = ['available' => false, 'authenticated' => !empty($_SESSION['supabase_access_token']), 'message' => 'Tree growth is being prepared. Check back soon.'];
try {
    $treeGrowth = treeGrowthState();
} catch (Throwable $treeGrowthError) {
    error_log('Tree growth state unavailable: ' . $treeGrowthError->getMessage());
}
$fieldNotes = [];
try {
    $fieldNotes = supabaseSelect('field_notes', [
        'published_for' => 'eq.' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d'),
        'status' => 'eq.published',
    ], 'id,title,summary,image_url,image_credit,source_name,source_url', ['order' => 'created_at.desc', 'limit' => 12]);
} catch (Throwable $fieldNotesError) {
    error_log('Field notes unavailable: ' . $fieldNotesError->getMessage());
}
$displayFieldNotes = array_slice($fieldNotes, 0, 6);
?>

<link rel="stylesheet" href="information02.css?v=<?= filemtime(__DIR__ . '/information02.css') ?>">

<body>
    <div class="information-page">
        <div class="species-header">
            <h1>Tree Species</h1>
            <p>Explore the tree species used in our reforestation efforts, including their characteristics, ecological benefits, and planting requirements.</p>
        </div>

        <div class="filters">
            <div class="species-filter-left">
                <div class="search-bar">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <form method="get" action="" id="searchForm">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($speciesStatus); ?>">
                        <input type="text" name="search" id="searchInput" placeholder="Search species" value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                    </form>
                </div>
                <div class="species-filter-controls">
                    <div class="category-filter-wrapper species-category-filter">
                        <button type="button" class="category-filter-toggle" id="categoryFilterToggle" aria-haspopup="true" aria-expanded="false">
                            <span><?= ucfirst(htmlspecialchars($category)); ?></span><i class="fas fa-chevron-down filter-chevron" aria-hidden="true"></i>
                        </button>
                        <div class="category-filter-dropdown" id="categoryFilterDropdown" role="menu" hidden>
                            <?php foreach (['all' => 'All', 'native' => 'Native', 'introduced' => 'Introduced'] as $value => $label): ?>
                                <a class="filter-btn <?= $category === $value ? 'active' : '' ?>" role="menuitem" aria-current="<?= $category === $value ? 'true' : 'false' ?>" href="?category=<?= $value ?>&status=<?= $speciesStatus ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $label ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($canManageSpecies): ?>
                <div class="species-status-toggle" role="tablist" aria-label="Tree species status">
                    <?php foreach (['active' => 'Active', 'archived' => 'Archived'] as $value => $label): ?>
                        <a role="tab" aria-selected="<?= $speciesStatus === $value ? 'true' : 'false' ?>" class="<?= $speciesStatus === $value ? 'active' : '' ?>" href="?category=<?= urlencode($category) ?>&status=<?= $value ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="species-grid">
            <?php if (count($species) === 0): ?>
                <?php if ($canManageSpecies && $speciesStatus !== 'archived'): ?><div class="activity-card add-activity-card species-add-card" onclick="showAddSpeciesModal()" role="button" tabindex="0" aria-label="Add tree species">
                        <div class="add-activity-inner"><i class="fa-solid fa-plus add-activity-icon"></i>
                            <p class="add-activity-guidetext">Click to add Species</p>
                        </div>
                    </div><?php endif; ?>
                <div class="no-results">
                    <img src="pages\no-results.svg" alt="" class="no-result-img">
                    <h3>No species found</h3>
                    <p>Your search "<?php echo htmlspecialchars($search); ?>" did not match any tree species.</p>
                </div>
            <?php else: ?>
                <?php foreach ($species as $item): ?>
                    <div class="species-card" data-id="<?php echo $item['id']; ?>" data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>" data-scientific="<?= htmlspecialchars($item['scientific_name'], ENT_QUOTES) ?>" data-category="<?= htmlspecialchars($item['category'], ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($item['description'], ENT_QUOTES) ?>">
                        <?php if (hasAdminPermission('tree_species_management')): ?><div class="activity-menu-trigger" onclick="event.stopPropagation(); toggleSpeciesMenu(this)" aria-label="Species options"><i class="fa-solid fa-ellipsis-vertical"></i>
                                <div class="activity-menu-dropdown" style="display: none;"><button type="button" onclick="event.stopPropagation(); showAddSpeciesModal(<?= (int)$item['id'] ?>)">Edit</button><button type="button" data-species-archive onclick="event.stopPropagation(); archiveSpecies(<?= (int)$item['id'] ?>, '<?php echo $speciesStatus === 'archived' ? 'restore' : 'archive'; ?>')"><?php echo $speciesStatus === 'archived' ? 'Restore' : 'Archive'; ?></button></div>
                            </div><?php endif; ?>
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" loading="lazy" decoding="async">
                        <?php else: ?>
                            <div class="card-image-placeholder"></div>
                        <?php endif; ?>
                        <div class="card-content">
                            <div class="card-header">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <span class="category-badge <?php echo $item['category']; ?>">
                                    <?php echo ucfirst($item['category']); ?>
                                </span>
                            </div>
                            <p class="scientific"><?php echo htmlspecialchars($item['scientific_name']); ?></p>
                            <p class="description"><?php echo htmlspecialchars($item['description']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($canManageSpecies && $speciesStatus !== 'archived'): ?><div class="activity-card add-activity-card species-add-card" onclick="showAddSpeciesModal()" role="button" tabindex="0" aria-label="Add tree species">
                        <div class="add-activity-inner"><i class="fa-solid fa-plus add-activity-icon"></i>
                            <p class="add-activity-guidetext">Click to add Species</p>
                        </div>
                    </div><?php endif; ?>
            <?php endif; ?>
        </div>

        <section class="grow-tree-container" aria-labelledby="grow-tree-title" data-tree-growth-root data-tree-empty="<?= !empty($treeGrowth['empty']) ? 'true' : 'false' ?>" data-tree-status="<?= htmlspecialchars((string)($treeGrowth['status'] ?? '')) ?>" data-tree-day="<?= (int)($treeGrowth['day'] ?? 0) ?>" data-tree-duration="<?= max(1, (int)($treeGrowth['duration'] ?? 1)) ?>" data-tree-category="<?= htmlspecialchars((string)($treeGrowth['category'] ?? '')) ?>" data-next-water-at="<?= htmlspecialchars((string)($treeGrowth['nextWaterAt'] ?? ''), ENT_QUOTES) ?>" data-tree-fact="<?= htmlspecialchars((string)($treeGrowth['funFact'] ?? 'Each watering day helps your tree reach maturity.')) ?>" data-authenticated="<?= !empty($treeGrowth['authenticated']) ? 'true' : 'false' ?>" data-water-endpoint="actions/water_tree.php" data-start-endpoint="actions/start_tree.php">
            <header class="grow-header">
                <div>
                    <h2 id="grow-tree-title">Grow a Tree</h2>
                    <p>Choose a tree to tend and see its growing phase. Remember to visit each day to water your sapling! Additionally earn a badge to show off!</p>
                </div>
            </header>
            <?php if (!empty($treeGrowth['available'])): ?>
                <div class="grow-grid">
                    <div class="grow-grid-left">
                        <article class="tree-grove-card" data-tree-card>
                            <header class="tree-grove-card__header"><span>Your Grove</span><span data-tree-day-label><?= !empty($treeGrowth['empty']) ? '' : 'Day ' . (int)$treeGrowth['day'] . ' of ' . (int)$treeGrowth['duration'] ?></span></header>
                            <div class="tree-grove-card__phase <?= !empty($treeGrowth['empty']) ? 'tree-grove-card__phase--empty' : '' ?>" data-tree-phase-action data-tree-illustration-host role="button" tabindex="0" aria-label="View <?= htmlspecialchars((string)($treeGrowth['name'] ?? 'tree')) ?> details"><span class="tree-ambient-glow" aria-hidden="true"></span><span class="tree-leaf-layer" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span><span class="tree-soil-motes" aria-hidden="true"><i></i><i></i><i></i></span><span class="tree-root-rise" aria-hidden="true"><i></i><i></i><i></i></span><span class="tree-waterfall" aria-hidden="true"><i></i><i></i><i></i></span><span class="tree-growth-flash" aria-hidden="true"></span><?php if (empty($treeGrowth['empty'])): ?><?= treeGrowthIllustrationSvg((int)$treeGrowth['day'], (string)($treeGrowth['assetKey'] ?? 'narra-v1'), 'tree-growth-illustration', (int)($treeGrowth['duration'] ?? 7)) ?><?php endif; ?></div>
                            <footer class="tree-grove-card__footer">
                                <div>
                                    <h3 data-tree-name><?= !empty($treeGrowth['empty']) ? '' : htmlspecialchars((string)$treeGrowth['name']) ?></h3>
                                    <p data-tree-species-meta><span data-tree-scientific><?= !empty($treeGrowth['empty']) ? '' : htmlspecialchars((string)$treeGrowth['scientificName']) ?></span><?= !empty($treeGrowth['empty']) ? '' : ' · ' ?><span data-tree-category><?= !empty($treeGrowth['empty']) ? '' : htmlspecialchars((string)$treeGrowth['category']) ?></span></p>
                                </div>
                                <button type="button" class="tree-water-button" data-tree-water <?= !empty($treeGrowth['empty']) ? 'hidden' : '' ?> data-watered-today="<?= !empty($treeGrowth['wateredToday']) ? 'true' : 'false' ?>" aria-disabled="<?= !empty($treeGrowth['canWater']) ? 'false' : 'true' ?>" aria-label="<?= htmlspecialchars(!empty($treeGrowth['canWater']) ? 'Water ' . (string)($treeGrowth['name'] ?? 'tree') : (string)($treeGrowth['message'] ?? 'This tree is unavailable.')) ?>" data-tooltip="<?= htmlspecialchars(!empty($treeGrowth['canWater']) ? 'Click to water' : (string)($treeGrowth['message'] ?? 'This tree is unavailable.')) ?>">
                                    <i class="fa-solid fa-heart" aria-hidden="true"></i>
                                </button>
                            </footer>
                        </article>
                    </div>
                    <div class="grow-grid-right">
                        <div class="tree-stat-grid">
                            <article class="tree-stat-card tree-stat-card--bright"><strong data-tree-progress-stat><?= (int)$treeGrowth['day'] ?></strong><span>Progress</span></article>
                            <article class="tree-stat-card"><strong data-tree-matured-stat><?= (int)($treeGrowth['maturedCount'] ?? 0) ?></strong><span>Matured trees</span></article>
                        </div>
                        <?php $speciesPickerLocked = !$treeGrowth['authenticated'] || ($treeGrowth['status'] ?? '') === 'growing'; ?>
                        <article class="tree-selection tree-species-picker" data-tree-species-picker>
                            <div class="tree-selection__heading">
                                <h3>Plant a new tree</h3>
                                <p>Available when your current tree finishes growing.</p>
                            </div>
                            <div class="tree-selection__choices" data-tree-species-choices aria-label="Choose a tree species">
                                <?php foreach (($treeGrowth['speciesOptions'] ?? []) as $option): $isCurrentTree = ($treeGrowth['status'] ?? '') === 'growing' && strcasecmp((string)($treeGrowth['name'] ?? ''), (string)($option['name'] ?? '')) === 0; ?>
                                    <button type="button" class="tree-choice<?= $isCurrentTree ? ' tree-choice--current' : '' ?>" data-tree-species-choice data-tree-species-id="<?= (int)$option['id'] ?>" aria-pressed="<?= $isCurrentTree ? 'true' : 'false' ?>" <?= $speciesPickerLocked ? 'disabled' : '' ?>>
                                        <strong><?= htmlspecialchars((string)$option['name']) ?></strong>
                                        <span><?= (int)$option['duration'] ?> days · <?= htmlspecialchars(ucfirst((string)$option['category'])) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <select data-tree-species-select hidden aria-hidden="true" <?= $speciesPickerLocked ? 'disabled' : '' ?>>
                                <option value="">Choose species</option><?php foreach (($treeGrowth['speciesOptions'] ?? []) as $option): ?><option value="<?= (int)$option['id'] ?>" data-tree-name="<?= htmlspecialchars((string)$option['name']) ?>" data-tree-scientific-name="<?= htmlspecialchars((string)$option['scientificName']) ?>" data-tree-category="<?= htmlspecialchars((string)$option['category']) ?>" data-tree-duration="<?= (int)$option['duration'] ?>" data-tree-asset-key="<?= htmlspecialchars((string)$option['assetKey']) ?>"><?= htmlspecialchars($option['name']) ?></option><?php endforeach; ?>
                            </select>
                            <div class="tree-selection__actions">
                                <button type="button" class="tree-species-start" data-tree-species-start <?= $speciesPickerLocked ? 'disabled' : '' ?>>Start selected tree</button>
                                <button type="button" class="tree-history-open tree-history-open--panel" data-tree-history-open>View grown trees</button>
                            </div>
                        </article>
                    </div>
                </div>
            <?php else: ?>
                <div class="tree-growth-unavailable"><?= htmlspecialchars((string)$treeGrowth['message']) ?></div>
            <?php endif; ?>
        </section>

        <div class="tree-detail-modal" data-tree-detail-modal hidden aria-hidden="true">
            <div class="tree-detail-modal__backdrop" data-tree-detail-close></div>
            <section class="tree-detail-modal__card" role="dialog" aria-modal="true" aria-labelledby="tree-detail-title">
                <header class="tree-detail-modal__header">
                    <div>
                        <h2 id="tree-detail-title" data-tree-detail-name></h2>
                        <p class="tree-detail-scientific" data-tree-detail-scientific></p>
                    </div>
                    <span class="tree-detail-modal__day" data-tree-detail-day></span>
                    <button type="button" class="tree-detail-modal__close" data-tree-detail-close aria-label="Close tree details"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </header>
                <section class="tree-detail-modal__content" data-tree-detail-content aria-label="Tree tending progress">
                    <button type="button" class="tree-detail-art tree-grove-card__phase" data-tree-detail-art data-tree-detail-water aria-label="Water this tree">
                        <span class="tree-ambient-glow" aria-hidden="true"></span>
                        <span class="tree-leaf-layer" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                        <span class="tree-soil-motes" aria-hidden="true"><i></i><i></i><i></i></span>
                    </button>
                    <div class="tree-detail-progress" data-tree-detail-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="tree-detail-progress__track"><span data-tree-detail-progress-bar></span></div>
                        <strong data-tree-detail-progress-label></strong>
                        <p data-tree-detail-status aria-live="polite"></p>
                    </div>
                </section>
                <footer class="tree-detail-modal__footer">
                    <p data-tree-detail-fact>Each watering day helps your tree reach maturity.</p>
                </footer>
            </section>
        </div>

        <section class="field-notes-section" aria-labelledby="field-notes-title">
            <div class="notes-header">
                <h3 id="field-notes-title">Field Notes</h3>
                <p>Short facts from foresters and ecologists, plus a timeline of Philippines Reforestation.</p>
            </div>
            <div class="card-notes-grid" data-field-notes-grid>
                <?php if (!$displayFieldNotes): ?><p class="field-notes-empty">Today’s field notes are not available yet. Please check again soon.</p><?php endif; ?>
                <?php for ($column = 0; $column < 2; $column++): ?><div class="field-notes-column">
                        <?php foreach (array_slice($displayFieldNotes, $column * 3, 3, true) as $index => $note): ?>
                            <article class="field-note-card <?= $index % 2 === 0 ? 'field-note-card--accent' : '' ?>" data-note-tone="<?= $index % 2 === 0 ? 'accent' : 'neutral' ?>">
                                <h4>Note: <?= str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) ?></h4>
                                <h3><?= htmlspecialchars((string)$note['title']) ?></h3>
                                <?php if (!empty($note['image_url'])): ?><figure class="field-note-photo"><img src="<?= htmlspecialchars((string)$note['image_url']) ?>" alt="<?= htmlspecialchars((string)$note['title']) ?>" loading="lazy">
                                        <figcaption>Photo: <?= htmlspecialchars((string)($note['image_credit'] ?: $note['source_name'])) ?></figcaption>
                                    </figure><?php endif; ?>
                                <p><?= htmlspecialchars((string)$note['summary']) ?></p>
                                <?php if (!empty($note['source_url'])): ?><div class="field-note-card__citation"><a class="field-note-card__source" href="<?= htmlspecialchars((string)$note['source_url']) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-paperclip" aria-hidden="true"></i><span>Source: <?= htmlspecialchars((string)($note['source_name'] ?: $note['source_url'])) ?></span></a></div><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div><?php endfor; ?>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let debounceTimer;
            const loadSpeciesSection = async (targetUrl, updateHistory = true) => {
                const currentGrid = document.querySelector('.species-grid');
                const currentFilters = document.querySelector('.filters');
                if (!currentGrid || !currentFilters) return;
                currentGrid.classList.add('is-loading');
                currentGrid.innerHTML = Array.from({
                    length: 3
                }, () => `
                    <div class="species-skeleton-card" aria-hidden="true">
                        <div class="species-skeleton-image"></div>
                        <div class="species-skeleton-content">
                            <span class="species-skeleton-line species-skeleton-line--short"></span>
                            <span class="species-skeleton-line"></span>
                            <span class="species-skeleton-line species-skeleton-line--tiny"></span>
                        </div>
                    </div>`).join('');
                try {
                    const response = await fetch(targetUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!response.ok) throw new Error('Tree species could not be loaded.');
                    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const nextGrid = page.querySelector('.species-grid');
                    const nextFilters = page.querySelector('.filters');
                    if (!nextGrid || !nextFilters) throw new Error('Tree species response was incomplete.');
                    currentGrid.replaceWith(nextGrid);
                    currentFilters.replaceWith(nextFilters);
                    if (updateHistory) window.history.pushState({}, '', targetUrl);
                    bindFilters();
                } catch (error) {
                    currentGrid.classList.remove('is-loading');
                    console.error('Tree species filter request failed.', error);
                }
            };

            const bindFilters = () => {
                const searchInput = document.getElementById('searchInput');
                const searchForm = document.getElementById('searchForm');
                const categoryToggle = document.getElementById('categoryFilterToggle');
                const categoryDropdown = document.getElementById('categoryFilterDropdown');
                categoryToggle?.addEventListener('click', () => {
                    const isOpen = categoryToggle.getAttribute('aria-expanded') === 'true';
                    categoryToggle.setAttribute('aria-expanded', String(!isOpen));
                    if (categoryDropdown) categoryDropdown.hidden = isOpen;
                });
                searchInput?.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        const params = new URLSearchParams(new FormData(searchForm));
                        loadSpeciesSection(`${window.location.pathname}?${params.toString()}`);
                    }, 400);
                });
                searchForm?.addEventListener('submit', event => {
                    event.preventDefault();
                    const params = new URLSearchParams(new FormData(searchForm));
                    loadSpeciesSection(`${window.location.pathname}?${params.toString()}`);
                });
            };

            document.addEventListener('click', event => {
                const filterLink = event.target.closest('.category-filter-dropdown a, .species-status-toggle a');
                if (filterLink) {
                    event.preventDefault();
                    loadSpeciesSection(filterLink.href);
                    return;
                }
                if (!event.target.closest('.category-filter-wrapper')) {
                    document.getElementById('categoryFilterToggle')?.setAttribute('aria-expanded', 'false');
                    const dropdown = document.getElementById('categoryFilterDropdown');
                    if (dropdown) dropdown.hidden = true;
                }
            });
            window.addEventListener('popstate', () => loadSpeciesSection(window.location.href, false));
            bindFilters();
        });
    </script>

    <script>
        document.addEventListener('click', event => {
            const card = event.target.closest('.species-card');
            if (!card || event.target.closest('.activity-menu-trigger, .activity-menu-dropdown')) return;
            const id = card.dataset.id;
            if (id && window.showSpeciesDetail) window.showSpeciesDetail(id);
        });
    </script>

    <script>
        document.querySelectorAll('.field-note-card').forEach(card => {
            card.tabIndex = 0;
            card.setAttribute('role', 'button');
            card.setAttribute('aria-expanded', 'false');
            if (!card.querySelector('.field-note-card__citation')) {
                const citation = document.createElement('div');
                citation.className = 'field-note-card__citation';
                citation.innerHTML = '<span class="field-note-card__source"><i class="fa-solid fa-paperclip" aria-hidden="true"></i><span>Source: GreenTrace Field Notes</span></span>';
                card.append(citation);
            }
            const toggle = () => {
                const active = card.classList.contains('field-note-card--active');
                document.querySelectorAll('.field-note-card').forEach(item => {
                    item.classList.remove('field-note-card--active');
                    item.setAttribute('aria-expanded', 'false');
                });
                if (!active) {
                    card.classList.add('field-note-card--active');
                    card.setAttribute('aria-expanded', 'true');
                }
            };
            card.addEventListener('click', toggle);
            card.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });
        });
        const historyTimeline = document.querySelector('.history-timeline');
        if (historyTimeline) {
            const milestones = Array.from(historyTimeline.querySelectorAll('.history-milestone'));
            let latestRevealedIndex = 0;
            historyTimeline.classList.add('history-timeline--interactive');
            const updateHistoryProgress = () => {
                milestones.forEach((milestone, index) => {
                    const marker = milestone.querySelector('.history-milestone__marker');
                    const year = milestone.querySelector('time')?.textContent.trim() || 'History';
                    const isRevealed = index <= latestRevealedIndex;
                    const isCurrent = index === latestRevealedIndex + 1;
                    milestone.classList.toggle('is-revealed', isRevealed);
                    milestone.classList.toggle('is-current', isCurrent);
                    milestone.classList.toggle('is-locked', !isRevealed && !isCurrent);
                    milestone.classList.toggle('is-complete', index < latestRevealedIndex);
                    milestone.setAttribute('aria-expanded', isRevealed ? 'true' : 'false');
                    if (!marker) return;
                    marker.removeAttribute('aria-hidden');
                    marker.setAttribute('role', 'button');
                    marker.tabIndex = isCurrent ? 0 : -1;
                    marker.setAttribute('aria-disabled', isCurrent ? 'false' : 'true');
                    marker.setAttribute('aria-label', isCurrent ? `Reveal the ${year} milestone` : (isRevealed ? `${year} milestone revealed` : `Reveal earlier milestones before ${year}`));
                });
            };
            const revealNextMilestone = (marker, moveFocus = false) => {
                const milestone = marker.closest('.history-milestone');
                const index = milestones.indexOf(milestone);
                if (index !== latestRevealedIndex + 1) return;
                latestRevealedIndex = index;
                updateHistoryProgress();
                if (moveFocus) milestones[index + 1]?.querySelector('.history-milestone__marker')?.focus();
            };
            historyTimeline.addEventListener('click', event => {
                const marker = event.target.closest('.history-milestone__marker');
                if (marker) revealNextMilestone(marker);
            });
            historyTimeline.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                const marker = event.target.closest('.history-milestone__marker');
                if (!marker) return;
                event.preventDefault();
                revealNextMilestone(marker, true);
            });
            updateHistoryProgress();
        }

        const treeDetailModal = document.querySelector('[data-tree-detail-modal]');
        const treePhaseAction = document.querySelector('[data-tree-phase-action]');
        const treeHistoryOpen = document.querySelector('[data-tree-history-open]');
        const closeTreeDetail = () => {
            if (treeDetailModal) {
                treeDetailModal.hidden = true;
                treeDetailModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('tree-detail-modal-open');
            }
        };
        // The same detail view is used from the grove card and the secondary record button.
        const openCurrentTreeDetail = () => {
            const root = document.querySelector('[data-tree-growth-root]');
            if (!treeDetailModal || !root) return;
            if (root.dataset.treeEmpty === 'true') {
                window.showToast?.('Choose a tree before opening its details.', 3000, 'error');
                return;
            }
            const name = root.querySelector('[data-tree-name]')?.textContent?.trim() || 'Tree';
            const scientific = root.querySelector('[data-tree-scientific]')?.textContent?.trim() ||
                root.querySelector('[data-tree-species-meta]')?.textContent?.split(' · ')[0]?.trim() || '';
            const category = (root.dataset.treeCategory || '').trim().toLowerCase();
            const categoryLabel = ['native', 'introduced'].includes(category)
                ? `${category[0].toUpperCase()}${category.slice(1)}`
                : '';
            const status = root.dataset.treeStatus === 'matured' ? 'Matured' : 'Growing';
            const day = Math.max(0, Number(root.dataset.treeDay || 0));
            const duration = Math.max(1, Number(root.dataset.treeDuration || 1));
            const progress = Math.min(100, (day / duration) * 100);
            treeDetailModal.querySelector('[data-tree-detail-name]').textContent = name;
            treeDetailModal.querySelector('[data-tree-detail-scientific]').textContent =
                `${scientific}${categoryLabel ? ` · ${categoryLabel}` : ''}`;
            const dayLabel = `Day ${day} of ${duration}`;
            const waterButton = root.querySelector('[data-tree-water]');
            const statusCopy = status === 'Matured' ?
                'This tree is fully mature. Choose a new tree to continue.' :
                (waterButton?.dataset.wateredToday === 'true' ? 'You already watered. Come back tomorrow.' : 'Tap the tree to water it and help it grow.');
            treeDetailModal.querySelector('[data-tree-detail-day]').textContent = dayLabel;
            treeDetailModal.querySelector('[data-tree-detail-progress-label]').textContent = dayLabel;
            treeDetailModal.querySelector('[data-tree-detail-status]').textContent = statusCopy;
            treeDetailModal.querySelector('[data-tree-detail-fact]').textContent = root.dataset.treeFact || 'Each watering day helps your tree reach maturity.';
            const detailProgress = treeDetailModal.querySelector('[data-tree-detail-progress]');
            detailProgress?.setAttribute('aria-valuenow', progress.toFixed(1));
            detailProgress?.setAttribute('aria-label', `${dayLabel} growth progress`);
            treeDetailModal.querySelector('[data-tree-detail-progress-bar]').style.width = `${progress.toFixed(3)}%`;
            const detailArt = treeDetailModal.querySelector('[data-tree-detail-art]');
            const artwork = root.querySelector('[data-tree-illustration-host] .tree-growth-illustration')?.cloneNode(true);
            detailArt?.querySelectorAll('.tree-growth-illustration').forEach((item) => item.remove());
            if (artwork) detailArt?.append(artwork);
            detailArt?.setAttribute('aria-label', root.querySelector('[data-tree-water]')?.getAttribute('aria-label') || `Water ${name}`);
            detailArt?.classList.remove('is-detail-entering');
            treeDetailModal.hidden = false;
            treeDetailModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('tree-detail-modal-open');
            window.requestAnimationFrame(() => detailArt?.classList.add('is-detail-entering'));
            window.setTimeout(() => detailArt?.classList.remove('is-detail-entering'), 520);
        };
        window.openCurrentTreeDetail = openCurrentTreeDetail;
        treePhaseAction?.addEventListener('click', openCurrentTreeDetail);
        treePhaseAction?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            openCurrentTreeDetail();
        });
        treeHistoryOpen?.addEventListener('click', openCurrentTreeDetail);
        treeDetailModal?.querySelectorAll('[data-tree-detail-close]').forEach((element) => element.addEventListener('click', closeTreeDetail));
        treeDetailModal?.querySelector('[data-tree-detail-water]')?.addEventListener('click', () => {
            document.querySelector('[data-tree-water]')?.click();
        });
        document.addEventListener('tree-growth-updated', () => {
            if (!treeDetailModal?.hidden) openCurrentTreeDetail();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !treeDetailModal?.hidden) closeTreeDetail();
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const root = document.querySelector('[data-tree-growth-root]');
            const button = root?.querySelector('[data-tree-water]');
            if (!root || !button) return;
            const illustrationHost = root.querySelector('[data-tree-illustration-host]');
            let cooldownTitleTimer;
            let cooldownExpiryTimer;
            const cooldownRemaining = () => {
                const nextWaterAt = Date.parse(root.dataset.nextWaterAt || '');
                if (!Number.isFinite(nextWaterAt)) return '';
                const remainingMs = nextWaterAt - Date.now();
                if (remainingMs <= 0) return '';
                const totalMinutes = Math.ceil(remainingMs / 60000);
                const hours = Math.floor(totalMinutes / 60);
                const minutes = totalMinutes % 60;
                const parts = [];
                if (hours) parts.push(`${hours} hour${hours === 1 ? '' : 's'}`);
                if (minutes || !hours) parts.push(`${minutes} minute${minutes === 1 ? '' : 's'}`);
                return parts.join(' ');
            };
            // Keep the heart available for a click so unavailable watering can be explained with a toast.
            const setWaterButtonState = (canWater, unavailableMessage = '') => {
                const treeName = root.querySelector('[data-tree-name]')?.textContent?.trim() || 'tree';
                const label = canWater ? `Water ${treeName}` : unavailableMessage;
                button.setAttribute('aria-disabled', canWater ? 'false' : 'true');
                button.setAttribute('aria-label', label);
                button.dataset.tooltip = canWater ? 'Click to water' : label;
                button.title = canWater ? `Water ${treeName}` : label;
                button.dataset.wateredToday = canWater ? 'false' : 'true';
            };
            const updateCooldownTitle = () => {
                if (button.dataset.wateredToday !== 'true' || root.dataset.treeStatus !== 'growing') return false;
                const remaining = cooldownRemaining();
                if (!remaining) {
                    root.dataset.nextWaterAt = '';
                    window.clearInterval(cooldownTitleTimer);
                    window.clearTimeout(cooldownExpiryTimer);
                    setWaterButtonState(true);
                    return false;
                }
                const title = `Tree already watered. Available in ${remaining}.`;
                button.title = title;
                button.dataset.tooltip = title;
                return true;
            };
            const startCooldownTitleUpdates = () => {
                window.clearInterval(cooldownTitleTimer);
                window.clearTimeout(cooldownExpiryTimer);
                if (!updateCooldownTitle()) return;
                cooldownTitleTimer = window.setInterval(updateCooldownTitle, 30000);
                const untilAvailable = Date.parse(root.dataset.nextWaterAt) - Date.now();
                cooldownExpiryTimer = window.setTimeout(updateCooldownTitle, Math.max(0, untilAvailable));
            };
            const showWaterUnavailableToast = () => {
                const remaining = cooldownRemaining();
                const isWateringCooldown = button.dataset.wateredToday === 'true' && remaining;
                if (typeof window.showToast !== 'function') return;
                if (isWateringCooldown) {
                    const message = `Tree already watered. Come back in ${remaining}.`;
                    window.showToast(message, 5000, 'success');
                    return;
                }
                window.showToast(button.getAttribute('aria-label') || 'This tree cannot be watered yet.', 3500, 'error');
            };
            startCooldownTitleUpdates();
            const preloadTreeSvg = (svgMarkup) => {
                if (!svgMarkup) return Promise.resolve();
                const wrapper = document.createElement('div');
                wrapper.innerHTML = String(svgMarkup).trim();
                const source = wrapper.querySelector('image')?.getAttribute('href');
                if (!source) return Promise.resolve();
                return new Promise((resolve) => {
                    const image = new Image();
                    let settled = false;
                    const finish = () => {
                        if (!settled) {
                            settled = true;
                            resolve();
                        }
                    };
                    image.onload = finish;
                    image.onerror = finish;
                    image.src = source;
                    if (typeof image.decode === 'function') image.decode().then(finish).catch(() => {});
                    window.setTimeout(finish, 2500);
                });
            };
            const replaceTreeSvg = async (svgMarkup, animate = false, alreadyPreloaded = false) => {
                if (!illustrationHost || !svgMarkup) return;
                const current = illustrationHost.querySelector('.tree-growth-illustration');
                const mount = () => {
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = String(svgMarkup).trim();
                    const next = wrapper.firstElementChild;
                    if (!next) return;
                    current?.replaceWith(next);
                    illustrationHost.classList.remove('is-phase-leaving');
                    if (animate) {
                        illustrationHost.classList.add('is-phase-entering');
                        window.setTimeout(() => illustrationHost.classList.remove('is-phase-entering'), 700);
                    }
                };
                if (!animate) {
                    mount();
                    return;
                }
                if (!alreadyPreloaded) await preloadTreeSvg(svgMarkup);
                illustrationHost.classList.add('is-phase-leaving');
                await new Promise((resolve) => window.setTimeout(resolve, 180));
                mount();
            };
            // Keep the current day available to the detail modal after watering or planting.
            const updateGrowthState = (day, duration) => {
                const safeDuration = Math.max(1, Number(duration || 1));
                const safeDay = Math.max(0, Math.min(safeDuration, Number(day || 0)));
                root.dataset.treeDay = String(safeDay);
                root.dataset.treeDuration = String(safeDuration);
            };
            updateGrowthState(root.dataset.treeDay, root.dataset.treeDuration);
            button.addEventListener('click', async () => {
                if (button.getAttribute('aria-disabled') === 'true') {
                    showWaterUnavailableToast();
                    return;
                }
                setWaterButtonState(false, 'Watering your tree...');
                button.classList.add('is-loading');
                const phase = root.querySelector('[data-tree-illustration-host]');
                const waitForGrowthMoment = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));
                const stageForDuration = (day, duration) => duration === 5 ? [1, 2, 3, 4, 6, 8][Math.max(0, Math.min(5, day))] :
                    Math.min(8, Math.max(1, day + 1));
                let pulseCancelled = false;
                let pulseTimer;
                let brightStartedAt = 0;
                let wateringCompleted = false;
                phase?.classList.remove('is-revealing', 'is-transforming', 'is-pulsing', 'is-watering');
                void phase?.offsetWidth;
                phase?.classList.add('is-watering');
                void phase?.offsetWidth;
                phase?.classList.add('is-pulsing');
                const pulseFinished = new Promise((resolve) => {
                    pulseTimer = window.setTimeout(() => {
                        if (!pulseCancelled) {
                            phase?.classList.remove('is-pulsing', 'is-watering');
                            phase?.classList.remove('is-pulse-complete');
                            phase?.classList.add('is-transforming', 'is-awaiting-artwork');
                            brightStartedAt = performance.now();
                        }
                        resolve();
                    }, 1100);
                });
                try {
                    const response = await fetch(root.dataset.waterEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        }
                    });
                    const responseText = await response.text();
                    let payload = {};
                    try {
                        payload = responseText ? JSON.parse(responseText) : {};
                    } catch (parseError) {
                        console.error('Tree watering returned an invalid response.', {
                            status: response.status,
                            body: responseText
                        }, parseError);
                        throw new Error(`Watering request failed (${response.status}).`);
                    }
                    wateringCompleted = response.ok;
                    if (response.ok && !payload.matured) {
                        root.dataset.nextWaterAt = payload.state?.nextWaterAt || '';
                        setWaterButtonState(false, 'Tree already watered.');
                        startCooldownTitleUpdates();
                    }
                    if (response.ok && payload.state?.day != null) {
                        const day = Number(payload.state.day);
                        const duration = Number(payload.state.duration || 7);
                        updateGrowthState(day, duration);
                        const illustration = root.querySelector('.tree-growth-illustration');
                        const nextArtwork = payload.tree?.illustrationSvg || '';
                        const artworkReady = nextArtwork ? preloadTreeSvg(nextArtwork) : Promise.resolve();
                        await pulseFinished;
                        await artworkReady;
                        await waitForGrowthMoment(Math.max(0, 650 - (performance.now() - brightStartedAt)));
                        const stat = root.querySelector('[data-tree-progress-stat]');
                        if (stat) stat.textContent = day;
                        const height = Number(payload.state.current_height || 0);
                        const targetHeight = Number(payload.state.target_height || 15);
                        if (nextArtwork) {
                            phase?.classList.add('is-phase-leaving');
                            await waitForGrowthMoment(120);
                            await replaceTreeSvg(nextArtwork, false, true);
                            void phase?.offsetWidth;
                            phase?.classList.remove('is-transforming', 'is-watering', 'is-awaiting-artwork', 'is-phase-leaving');
                            phase?.classList.add('is-revealing');
                        } else if (illustration) {
                            illustration.style.setProperty('--tree-height-scale', (0.88 + (0.12 * Math.min(1, Math.max(0, height / targetHeight)))).toFixed(4));
                            const folder = illustration.dataset.treeAssetFolder || 'narra';
                            const stage = String(stageForDuration(day, duration)).padStart(2, '0');
                            illustration.dataset.treeStage = String(Number(stage) - 1);
                            illustration.style.setProperty('--tree-sprite-image', `url('assets/tree_growth/${folder}/${folder}_stage_${stage}.png')`);
                            void phase?.offsetWidth;
                            phase?.classList.remove('is-transforming', 'is-watering', 'is-awaiting-artwork');
                            phase?.classList.add('is-revealing');
                        }
                        const matured = Boolean(payload.matured);
                        if (matured) {
                            root.dataset.treeStatus = 'matured';
                            root.dataset.nextWaterAt = '';
                            window.clearInterval(cooldownTitleTimer);
                            window.clearTimeout(cooldownExpiryTimer);
                            setWaterButtonState(false, 'This tree is fully mature. Choose a new tree to continue.');
                            speciesSelect.disabled = false;
                            speciesStart.disabled = false;
                            speciesChoices?.querySelectorAll('[data-tree-species-choice]').forEach((choice) => {
                                choice.disabled = false;
                                choice.classList.remove('tree-choice--current');
                                choice.setAttribute('aria-pressed', 'false');
                            });
                        }
                        await waitForGrowthMoment(420);
                        phase?.classList.remove('is-revealing');
                        document.dispatchEvent(new Event('tree-growth-updated'));
                    }
                    if (!response.ok) {
                        phase?.classList.remove('is-transforming', 'is-pulsing', 'is-watering', 'is-pulse-complete', 'is-awaiting-artwork', 'is-revealing');
                        const alreadyWatered = response.status === 409 && payload.state?.wateredToday === true;
                        if (alreadyWatered) {
                            root.dataset.nextWaterAt = payload.state.nextWaterAt || '';
                            wateringCompleted = true;
                            setWaterButtonState(false, payload.message || 'Tree already watered.');
                            startCooldownTitleUpdates();
                            showWaterUnavailableToast();
                        } else {
                            const message = response.status === 401 ?
                                'Your session expired. Please sign in again.' :
                                (payload.message || 'Unable to water the tree.');
                            if (typeof window.showToast === 'function') window.showToast(message, 3500, 'error');
                        }
                    }
                    if (response.ok && Number(payload.state?.current_height || 0) > Number(payload.state?.target_height || 0) && typeof window.showToast === 'function') window.showToast(`Your tree grew over ${(Number(payload.state.current_height) - Number(payload.state.target_height)).toFixed(2)} meters than its average size!`, 5000, 'success');
                } catch (error) {
                    console.error('Tree watering failed.', error);
                    pulseCancelled = true;
                    window.clearTimeout(pulseTimer);
                    phase?.classList.remove('is-transforming', 'is-pulsing', 'is-watering', 'is-pulse-complete', 'is-awaiting-artwork', 'is-revealing');
                    const message = error?.message && !error.message.startsWith('Watering request failed') ?
                        error.message :
                        'The tree could not be watered right now.';
                    if (typeof window.showToast === 'function') window.showToast(message, 3500, 'error');
                } finally {
                    pulseCancelled = true;
                    window.clearTimeout(pulseTimer);
                    button.classList.remove('is-loading');
                    if (!wateringCompleted && root.dataset.treeStatus !== 'matured') {
                        window.setTimeout(() => setWaterButtonState(true), 250);
                    }
                }
            });

            const speciesSelect = root.querySelector('[data-tree-species-select]');
            const speciesStart = root.querySelector('[data-tree-species-start]');
            const speciesChoices = root.querySelector('[data-tree-species-choices]');
            // The visible species cards update the hidden select so the existing start flow stays unchanged.
            speciesChoices?.addEventListener('click', (event) => {
                const choice = event.target.closest('[data-tree-species-choice]');
                if (!choice || choice.disabled || !speciesSelect) return;
                speciesSelect.value = choice.dataset.treeSpeciesId || '';
                speciesChoices.querySelectorAll('[data-tree-species-choice]').forEach((item) => {
                    const isSelected = item === choice;
                    item.classList.toggle('tree-choice--current', isSelected);
                    item.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                });
                speciesSelect.dispatchEvent(new Event('change'));
            });
            const createSpeciesPreview = (folder, name) => {
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.className.baseVal = `tree-growth-illustration tree-growth-illustration--svg tree-growth-illustration--${folder}`;
                svg.setAttribute('viewBox', '0 0 512 512');
                svg.setAttribute('data-tree-stage', '0');
                svg.setAttribute('data-tree-species', folder);
                svg.setAttribute('role', 'img');
                svg.setAttribute('aria-label', `${name || 'Tree'} seed artwork`);
                const image = document.createElementNS('http://www.w3.org/2000/svg', 'image');
                image.setAttribute('href', `assets/tree_growth/${folder}/${folder}_stage_01.png`);
                image.setAttribute('x', '0');
                image.setAttribute('y', '0');
                image.setAttribute('width', '512');
                image.setAttribute('height', '512');
                image.setAttribute('preserveAspectRatio', 'xMidYMid meet');
                svg.append(image);
                return svg;
            };
            speciesSelect?.addEventListener('change', () => {
                if (root.dataset.treeEmpty !== 'true' && root.dataset.treeStatus !== 'matured') return;
                const option = speciesSelect.options[speciesSelect.selectedIndex];
                if (!option?.value) return;
                const phase = root.querySelector('[data-tree-illustration-host]');
                const folderByAssetKey = {
                    'narra-v1': 'narra',
                    'mahogany-v1': 'mahogany',
                    'apitong-v1': 'apitong',
                    'lauan-v1': 'lauan',
                    'fire-tree-v1': 'fire_tree'
                };
                const folder = folderByAssetKey[option.dataset.treeAssetKey] || 'narra';
                phase?.querySelectorAll('.tree-growth-illustration').forEach((artwork) => artwork.remove());
                let preview = null;
                if (phase) {
                    preview = createSpeciesPreview(folder, option.dataset.treeName || 'Tree');
                    phase.append(preview);
                }
                root.querySelector('[data-tree-name]')?.replaceChildren(document.createTextNode(option.dataset.treeName || 'Tree'));
                phase?.setAttribute('aria-label', `View ${option.dataset.treeName || 'tree'} details`);
                root.dataset.treeCategory = option.dataset.treeCategory || '';
                const meta = root.querySelector('[data-tree-species-meta]');
                if (meta) meta.textContent = `${option.dataset.treeScientificName || ''}${option.dataset.treeCategory ? ` · ${option.dataset.treeCategory}` : ''}`;
                const phaseCard = phase;
                phaseCard?.classList.remove('tree-grove-card__phase--empty', 'is-revealing');
                void phaseCard?.offsetWidth;
                phaseCard?.classList.add('is-revealing');
            });
            speciesStart?.addEventListener('click', async () => {
                const speciesId = speciesSelect?.value;
                if (!speciesId || speciesStart.disabled) return;
                speciesStart.disabled = true;
                const phase = root.querySelector('[data-tree-illustration-host]');
                const waitForStartMoment = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));
                phase?.classList.remove('is-revealing', 'is-transforming', 'is-planting', 'is-watering');
                void phase?.offsetWidth;
                phase?.classList.add('is-planting');
                const plantingStartedAt = performance.now();
                const body = new URLSearchParams({
                    species_id: speciesId,
                    csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
                });
                try {
                    const response = await fetch(root.dataset.startEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                        },
                        body
                    });
                    const payload = await response.json();
                    if (typeof window.showToast === 'function') window.showToast(payload.message || 'Tree selection updated.', 3500, response.ok ? 'success' : 'error');
                    if (response.ok && payload.tree) {
                        const tree = payload.tree;
                        await waitForStartMoment(Math.max(0, 420 - (performance.now() - plantingStartedAt)));
                        root.dataset.treeEmpty = 'false';
                        const illustration = root.querySelector('.tree-growth-illustration');
                        const folderByAssetKey = {
                            'narra-v1': 'narra',
                            'mahogany-v1': 'mahogany',
                            'apitong-v1': 'apitong',
                            'lauan-v1': 'lauan',
                            'fire-tree-v1': 'fire_tree'
                        };
                        const folder = folderByAssetKey[tree.assetKey] || 'narra';
                        const day = Number(tree.day || 0);
                        const duration = Number(tree.duration || 7);
                        const targetHeight = Number(tree.targetHeight || 15);
                        root.querySelector('[data-tree-name]')?.replaceChildren(document.createTextNode(tree.name || 'Tree'));
                        phase?.setAttribute('aria-label', `View ${tree.name || 'tree'} details`);
                        root.dataset.treeCategory = tree.category || '';
                        const meta = root.querySelector('[data-tree-species-meta]');
                        if (meta) meta.textContent = `${tree.scientificName || ''}${tree.category ? ` · ${tree.category}` : ''}`;
                        const stat = root.querySelector('[data-tree-progress-stat]');
                        if (stat) stat.textContent = day;
                        let activeIllustration = illustration;
                        if (!activeIllustration && phase) {
                            activeIllustration = document.createElement('div');
                            activeIllustration.className = 'tree-growth-illustration tree-growth-illustration--sprite tree-growth-illustration--individual';
                            activeIllustration.setAttribute('role', 'img');
                            activeIllustration.setAttribute('aria-label', 'Tree growth artwork');
                            phase.append(activeIllustration);
                        }
                        if (activeIllustration) {
                            const stageNumber = duration === 5 ? [1, 2, 3, 4, 6, 8][Math.max(0, Math.min(5, day))] :
                                Math.min(8, Math.max(1, day + 1));
                            const stage = String(stageNumber).padStart(2, '0');
                            activeIllustration.dataset.treeAssetFolder = folder;
                            activeIllustration.dataset.treeStage = String(Number(stage) - 1);
                            activeIllustration.style.setProperty('--tree-height-scale', (0.88 + (0.12 * Math.min(1, Number(tree.currentHeight || 0) / targetHeight))).toFixed(4));
                            activeIllustration.style.setProperty('--tree-sprite-image', `url('assets/tree_growth/${folder}/${folder}_stage_${stage}.png')`);
                        }
                        if (tree.illustrationSvg) replaceTreeSvg(tree.illustrationSvg, false);
                        root.dataset.treeStatus = 'growing';
                        updateGrowthState(day, duration);
                        button.hidden = false;
                        setWaterButtonState(true);
                        speciesSelect.disabled = true;
                        speciesStart.disabled = true;
                        speciesChoices?.querySelectorAll('[data-tree-species-choice]').forEach((choice) => {
                            choice.disabled = true;
                        });
                        void phase?.offsetWidth;
                        phase?.classList.remove('is-planting');
                        phase?.classList.add('is-revealing');
                        await waitForStartMoment(420);
                        phase?.classList.remove('is-revealing');
                    } else if (!response.ok) {
                        phase?.classList.remove('is-planting', 'is-revealing');
                        speciesStart.disabled = false;
                    }
                } catch (_error) {
                    phase?.classList.remove('is-planting', 'is-revealing');
                    if (typeof window.showToast === 'function') window.showToast('Unable to start this tree right now.', 3500, 'error');
                    speciesStart.disabled = false;
                }
            });
        });
    </script>

    <?php include 'footer.php'; ?>
    <?php if (hasAdminPermission('tree_species_management')): ?><script>
            function toggleSpeciesMenu(trigger) {
                const dropdown = trigger.querySelector('.activity-menu-dropdown');
                document.querySelectorAll('.activity-menu-dropdown').forEach(menu => {
                    if (menu !== dropdown) menu.style.display = 'none';
                });
                dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            }

            function archiveSpecies(id, action = 'archive') {
                const body = new URLSearchParams({
                    action,
                    id: String(id),
                    csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
                });
                fetch('actions/manage_species.php', {
                    method: 'POST',
                    body
                }).then(() => location.reload());
            }
            document.addEventListener('click', () => document.querySelectorAll('.activity-menu-dropdown').forEach(menu => {
                menu.style.display = 'none';
            }));
        </script><?php endif; ?>
</body>