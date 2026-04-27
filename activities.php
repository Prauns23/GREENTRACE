<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

// Get search and filter from URL
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'upcoming';

// Build query (only non‑archived activities)
$sql = "SELECT * FROM activities WHERE archived = 0";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = "sss";
}

$sql .= " ORDER BY date ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$allActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

// Split into upcoming and past based on today's date
$today = date('Y-m-d');
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

// Apply filter selection
$showUpcoming = false;
$showPast = false;
if ($filter === 'upcoming') {
    $showUpcoming = true;
} elseif ($filter === 'past') {
    $showPast = true;
}

include 'header.php';
?>

<link rel="stylesheet" href="activities.css">

<div class="activities-page">
    <div class="activities-header">
        <h1>Get Involved — Pick an Activity and Show Up.</h1>
        <span>Browse upcoming planting events, workshops, and restoration projects. Join an activity to make an impact</span>
    </div>

    <!-- Search and Filter Bar  -->
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

    <!-- Upcoming Activities Section -->
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
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Past Activities Section -->
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
</div>

<script>
    // Debounced search
    const searchInput = document.getElementById('searchInput');
    const searchForm = document.getElementById('searchForm');
    let debounceTimer;
    // Search with filter preservation
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const filter = filterSelect ? filterSelect.value : 'upcoming';
                window.location.href = `activities.php?filter=${filter}&search=${encodeURIComponent(this.value)}`;
            }, 400);
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

    // Menu functions (admin only)
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
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
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
        alert('Edit functionality will be added later. Activity ID: ' + activityId);
    }

    // Filter dropdown change
    const filterSelect = document.getElementById('filterSelect');
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            const search = document.getElementById('searchInput')?.value || '';
            window.location.href = `activities.php?filter=${this.value}&search=${encodeURIComponent(search)}`;
        });
    }
</script>

<?php include 'footer.php'; ?>