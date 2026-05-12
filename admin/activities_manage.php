<?php
require_once __DIR__ . '/../init_session.php';
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Get filters from URL
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'active'; // active, archived, all
$sort = $_GET['sort'] ?? 'date_asc';

// Build WHERE clause
$where = "1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = "sss";
}

if ($statusFilter === 'active') {
    $where .= " AND archived = 0";
} elseif ($statusFilter === 'archived') {
    $where .= " AND archived = 1";
}
// 'all' shows both

// Build ORDER BY
switch ($sort) {
    case 'title_asc':
        $orderBy = "title ASC";
        break;
    case 'title_desc':
        $orderBy = "title DESC";
        break;
    case 'date_asc':
        $orderBy = "date ASC";
        break;
    case 'date_desc':
        $orderBy = "date DESC";
        break;
    case 'capacity_asc':
        $orderBy = "capacity ASC";
        break;
    case 'capacity_desc':
        $orderBy = "capacity DESC";
        break;
    default:
        $orderBy = "date ASC";
}

$sql = "SELECT * FROM activities WHERE $where ORDER BY $orderBy";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$totalActive = $conn->query("SELECT COUNT(*) as cnt FROM activities WHERE archived = 0")->fetch_assoc()['cnt'];
$totalArchived = $conn->query("SELECT COUNT(*) as cnt FROM activities WHERE archived = 1")->fetch_assoc()['cnt'];
$totalAll = $totalActive + $totalArchived;

include __DIR__ . '/../header.php';

?>

<link rel="stylesheet" href="activities_manage.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<div class="activities-manage-container">
    <div class="manage-header">
        <h2>Activity Management</h2>
        <p>Manage the activities for our volunteers here</p>
    </div>

    <!-- Filter and Search -->
    <div class="search-filter">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by title, description, or location" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-actions">
            <div class="sort-bar">
                <label>Status:</label>
                <div class="custom-select">
                    <select id="statusFilter">
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    </select>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
            <div class="sort-bar">
                <label>Sort by:</label>
                <div class="custom-select">
                    <select id="sortSelect">
                        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Date (earliest first)</option>
                        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Date (latest first)</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title (A–Z)</option>
                        <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Title (Z–A)</option>
                        <option value="capacity_asc" <?= $sort === 'capacity_asc' ? 'selected' : '' ?>>Capacity (low to high)</option>
                        <option value="capacity_desc" <?= $sort === 'capacity_desc' ? 'selected' : '' ?>>Capacity (high to low)</option>
                    </select>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <h3>Total Activities</h3>
            <p><?= $totalAll ?></p>
        </div>
        <div class="stat-card">
            <h3>Active</h3>
            <p><?= $totalActive ?></p>
        </div>
        <div class="stat-card">
            <h3>Archived</h3>
            <p><?= $totalArchived ?></p>
        </div>
    </div>

    <!-- Bulk Actions Bar -->
    <div class="bulk-actions-bar">
        <form method="POST" id="bulkActionForm" class="bulk-action-form">
            <input type="hidden" name="bulk_action" id="bulkActionType" value="">
            <input type="hidden" name="selected_ids" id="selectedIdsInput" value="">
            <button type="button" class="bulk-archive-btn" id="bulkArchiveBtn" disabled><i class="fas fa-archive"></i> </button>
            <button type="button" class="bulk-restore-btn" id="bulkRestoreBtn" disabled><i class="fas fa-undo-alt"></i> </button>
            <button type="button" class="bulk-delete-btn" id="bulkDeleteBtn" disabled><i class="fas fa-trash-alt"></i> </button>
        </form>
    </div>


    <!-- Activities Table -->
    <div class="activities-table">
        <div style="margin:0;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Location</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="9" class="no-data">No activities found.<?= !empty($search) ? ' Try a different search.' : '' ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $act): ?>
                            <tr data-activity-id="<?= $act['id'] ?>">
                                <td><input type="checkbox" class="rowCheckbox" value="<?= $act['id'] ?>"></td>
                                <td><?= $act['id'] ?></td>
                                <td><?= htmlspecialchars($act['title']) ?></td>
                                <td><?= date('M d, Y', strtotime($act['date'])) ?></td>
                                <td>
                                    <?php if (!empty($act['time_start'])): ?>
                                        <?= date('g:i A', strtotime($act['time_start'])) ?>
                                        <?php if (!empty($act['time_end'])): ?> – <?= date('g:i A', strtotime($act['time_end'])) ?><?php endif; ?>
                                            <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($act['location']) ?></td>
                                <td><?= $act['participants_count'] ?> / <?= $act['capacity'] ?></td>
                                <td>
                                    <?php if ($act['archived']): ?>
                                        <span class="status-badge archived">Archived</span>
                                    <?php else: ?>
                                        <span class="status-badge active">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="action-btn edit-btn" title="Edit" data-id="<?= $act['id'] ?>"><i class="fas fa-pen"></i></button>
                                    <?php if ($act['archived']): ?>
                                        <button type="button" class="action-btn restore-single-btn" title="Restore" data-id="<?= $act['id'] ?>"><i class="fas fa-undo-alt"></i></button>
                                    <?php else: ?>
                                        <button type="button" class="action-btn archive-single-btn" title="Archive" data-id="<?= $act['id'] ?>"><i class="fas fa-archive"></i></button>
                                    <?php endif; ?>
                                    <button type="button" class="action-btn delete-single-btn" title="Delete Permanently" data-id="<?= $act['id'] ?>"><i class="fas fa-trash-alt"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="activities_manage.js"></script>
<script>
    // Simple toast from URL (will be replaced with proper toast later)
    const urlParams = new URLSearchParams(window.location.search);
    const toastMsg = urlParams.get('toast');
    if (toastMsg) {
        alert(decodeURIComponent(toastMsg));
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
    }
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>