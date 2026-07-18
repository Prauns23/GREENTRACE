<?php
require_once 'init_session.php';
require_once 'config.php';
require_once 'pagination_helper.php'; // <-- NEW

if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

// Get search, filter, and page from URL
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'upcoming';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build base WHERE clause (only non‑archived activities)
$where = "archived = 0";
$params = [];
$types = "";

// Add date condition based on filter
$today = date('Y-m-d');
if ($filter === 'upcoming') {
    $where .= " AND date >= ?";
    $params[] = $today;
    $types .= "s";
} elseif ($filter === 'past') {
    $where .= " AND date < ?";
    $params[] = $today;
    $types .= "s";
}

// Add search condition
if (!empty($search)) {
    $where .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

// Count total rows (for pagination)
$countSql = "SELECT COUNT(*) as total FROM activities WHERE $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($total / $limit);

// Fetch activities for current page
$sql = "SELECT * FROM activities WHERE $where ORDER BY date ASC LIMIT $offset, $limit";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$allActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user's application statuses for each activity (if logged in)
$userStatuses = [];
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $statusStmt = $conn->prepare("SELECT activity_id, status FROM volunteer_applications WHERE user_id = ?");
    $statusStmt->bind_param("i", $userId);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    while ($row = $statusResult->fetch_assoc()) {
        $userStatuses[$row['activity_id']] = $row['status'];
    }
    $statusStmt->close();
}

// Add user status to activities and split (though all are already filtered by date)
$upcoming = [];
$past = [];
foreach ($allActivities as $activity) {
    $activity['user_status'] = $userStatuses[$activity['id']] ?? null;
    if ($activity['date'] >= $today) {
        $upcoming[] = $activity;
    } else {
        $past[] = $activity;
    }
}

// Which section to show?
$showUpcoming = ($filter === 'upcoming');
$showPast = ($filter === 'past');

include 'header.php';
?>

<link rel="stylesheet" href="activities.css">

<div class="activities-page">
    <div class="activities-header">
        <h1>Get Involved — Pick an Activity and Show Up.</h1>
        <span>Browse upcoming planting events, workshops, and restoration projects. Join an activity to make an impact</span>
    </div>

    <!-- Search and Filter Bar -->
    <div class="search-filter">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search activities" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-actions">
            <div class="sort-bar">
                <label>Show:</label>
                <div class="custom-select">
                    <select id="filterSelect">
                        <option value="upcoming" <?= $filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="past" <?= $filter === 'past' ? 'selected' : '' ?>>Past</option>
                    </select>
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if ($showUpcoming): ?>
        <div class="section-header">
            <h2>Upcoming Activities</h2>
        </div>
        <div class="activities-grid">
            <?php if (count($upcoming) === 0): ?>
                <div class="no-results" style="grid-column: 1/-1; text-align: center;">
                    <img src="pages/no-results.svg" alt="No activities found" style="max-width: 300px;">
                    <h3>No upcoming activities</h3>
                    <p>Check back later for new volunteer opportunities!</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming as $activity): ?>
                    <?php include 'activity_card.php'; ?>
                <?php endforeach; ?>
                <!-- Add Activity (Admin/Super Admin) -->
                <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                    <div class="activity-card add-activity-card" onclick="showAddActivityModal()" role="button" aria-label="Add activity">
                        <div class="add-activity-inner">
                            <i class="fa-solid fa-plus add-activity-icon"></i>
                            <p class="add-activity-guidetext">Click to add Activity</p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($showPast): ?>
        <div class="section-header">
            <h2>Past Activities</h2>
        </div>
        <div class="activities-grid">
            <?php if (count($past) === 0): ?>
                <div class="no-results" style="grid-column: 1/-1; text-align: center;">
                    <img src="pages/no-results.svg" alt="No activities found" style="max-width: 300px;">
                    <h3>No past activities</h3>
                    <p>Past activities will appear here once events have ended.</p>
                </div>
            <?php else: ?>
                <?php foreach ($past as $activity): ?>
                    <?php include 'activity_card.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php
    $queryParams = ['filter' => $filter];
    if (!empty($search)) {
        $queryParams['search'] = $search;
    }
    echo renderPagination($page, $totalPages, 'activities.php', $queryParams);
    ?>
</div>

<script>
    // Debounced search
    const searchInput = document.getElementById('searchInput');
    let debounceTimer;
    const filterSelect = document.getElementById('filterSelect');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const filter = filterSelect ? filterSelect.value : 'upcoming';
                const search = encodeURIComponent(this.value);
                // Reset page to 1 when searching
                window.location.href = `activities.php?filter=${filter}&search=${search}&page=1`;
            }, 400);
        });
    }

    // Filter dropdown change
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            const search = document.getElementById('searchInput')?.value || '';
            window.location.href = `activities.php?filter=${this.value}&search=${encodeURIComponent(search)}&page=1`;
        });
    }

    // Toast handling (unchanged)
    const urlParams = new URLSearchParams(window.location.search);
    const toastMsg = urlParams.get('toast');
    const toastType = urlParams.get('type') === 'error' ? 'error' : 'success';
    if (toastMsg) {
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
        setTimeout(() => {
            if (typeof showToast === 'function') {
                showToast(decodeURIComponent(toastMsg), 5000, toastType);
            } else {
                alert(decodeURIComponent(toastMsg));
            }
        }, 500);
    }

    const openActivityParam = new URLSearchParams(window.location.search).get('open_activity');
    if (openActivityParam) {
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
        setTimeout(() => {
            if (typeof showActivityDetails === 'function') {
                showActivityDetails(parseInt(openActivityParam, 10));
            }
        }, 300);
    }

    // Activity menu functions (unchanged)
    function toggleActivityMenu(trigger) {
        event.stopPropagation();
        const dropdown = trigger.querySelector('.activity-menu-dropdown');
        document.querySelectorAll('.activity-menu-dropdown').forEach(menu => {
            if (menu !== dropdown) menu.style.display = 'none';
        });
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
    document.addEventListener('click', () => {
        document.querySelectorAll('.activity-menu-dropdown').forEach(menu => {
            menu.style.display = 'none';
        });
    });

    function archiveActivity(activityId) {
        if (!confirm('Archive this activity? It will no longer appear on the volunteer page.')) return;
        fetch('actions/archive_activity.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + activityId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Activity archived successfully', 3000, 'success');
                    const card = document.querySelector(`.activity-card[data-activity-id="${activityId}"]`);
                    if (card) card.remove();
                } else {
                    showToast(data.error || 'Archive failed', 4000, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('An error occurred', 4000, 'error');
            });
    }

    function editActivity(activityId) {
        if (window.parent.showEditActivityModal) {
            window.parent.showEditActivityModal(activityId);
        } else {
            alert('Edit modal not available');
        }
    }
</script>

<?php include 'footer.php'; ?>