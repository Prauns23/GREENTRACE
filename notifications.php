<?php
require_once 'init_session.php';
require_once 'config.php';
require_once 'pagination_helper.php';

$conn->query("SET time_zone = '" . date('P') . "'");

// Helper: time ago
function time_ago($unix)
{
    $diff = time() - $unix;
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400)   return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800)  return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    return date('M j, Y', $unix);
}

function getIconClass($type)
{
    switch ($type) {
        case 'application':
            return 'fa-file-alt';
        case 'activity':
            return 'fa-bell';
        case 'report':
            return 'fa-exclamation-triangle';
        case 'message':
            return 'fa-comment-dots';
        default:
            return 'fa-bell';
    }
}

// Notification messages may use simple emphasis, but all data and other markup
// must remain text so saved notifications cannot inject scripts or attributes.
function renderNotificationMessage($message)
{
    $escaped = htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8');
    return str_ireplace(
        ['&lt;strong&gt;', '&lt;/strong&gt;'],
        ['<strong>', '</strong>'],
        $escaped
    );
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$filter = $_GET['filter'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$search = trim((string)($_GET['search'] ?? ''));
$allowedFilters = ['all', 'application', 'message', 'report'];
$allowedSorts = ['newest', 'oldest'];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

// AJAX handlers 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // init_session.php has already validated this state-changing request.

    $action = $_POST['action'] ?? '';

    // Single mark as read
    if ($action === 'mark_read') {
        $notif_id = (int)($_POST['notification_id'] ?? 0);
        if ($notif_id) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $notif_id, $user_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Invalid ID']);
        }
        exit;
    }

    // Mark all as read
    if ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND archived = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    // Bulk actions 
    if (in_array($action, ['bulk_mark_read', 'bulk_archive', 'bulk_delete'])) {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['error' => 'No IDs provided']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        if ($action === 'bulk_mark_read') {
            $sql = "UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?";
        } elseif ($action === 'bulk_archive') {
            $sql = "UPDATE notifications SET archived = 1 WHERE id IN ($placeholders) AND user_id = ?";
        } else { // bulk_delete
            $sql = "DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?";
        }

        $stmt = $conn->prepare($sql);
        $params = array_merge($ids, [$user_id]);
        $stmt->bind_param($types . 'i', ...$params);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success]);
        exit;
    }

    // Invalid action
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

// Main page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;

$baseSql = "SELECT * FROM notifications WHERE user_id = ? AND archived = 0";
$params = [$user_id];
$types = 'i';

if ($filter !== 'all') {
    $baseSql .= " AND type = ?";
    $params[] = $filter;
    $types .= 's';
}

if ($search !== '') {
    $baseSql .= " AND (title LIKE ? OR message LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

$baseSql .= " ORDER BY created_at " . ($sort === 'oldest' ? 'ASC' : 'DESC');

$stmt = $conn->prepare($baseSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$allNotifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total = count($allNotifications);
$totalPages = $total > 0 ? max(1, (int)ceil($total / $limit)) : 1;
$page = min(max(1, $page), $totalPages);

$offset = ($page - 1) * $limit;
$notifications = array_slice($allNotifications, $offset, $limit);

if (count($notifications) < $limit && $total > $limit) {
    $remainingNeeded = $limit - count($notifications);
    $continuation = array_slice($allNotifications, $offset + $limit, $remainingNeeded);
    $notifications = array_merge($notifications, $continuation);
}

$unreadCount = 0;
foreach ($allNotifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}

// Group by time (same as before)
$groups = ['Today' => [], 'This Week' => [], 'This Month' => [], 'Older' => []];
$now = new DateTime();
$today = $now->format('Y-m-d');
$weekStart = (clone $now)->modify('this week')->format('Y-m-d');
$monthStart = (clone $now)->modify('first day of this month')->format('Y-m-d');

foreach ($notifications as $n) {
    $date = (new DateTime($n['created_at']))->format('Y-m-d');
    if ($date === $today) {
        $groups['Today'][] = $n;
    } elseif ($date >= $weekStart) {
        $groups['This Week'][] = $n;
    } elseif ($date >= $monthStart) {
        $groups['This Month'][] = $n;
    } else {
        $groups['Older'][] = $n;
    }
}
$groups = array_filter($groups);

include 'header.php';
?>
<link rel="stylesheet" href="notifications.css">

<div class="notifications-page">
    <!-- Header -->
    <div class="notifications-header">
        <h1>Notifications</h1>
        <p>You have <strong><?= $unreadCount ?></strong> notification<?= $unreadCount !== 1 ? '(s)' : '' ?> to go through — Click a notification to view details.</p>
    </div>

    <!-- Search Bar -->
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search your notifications here" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
    </div>

    <!-- Filter Buttons with dropdown inside wrapper -->
    <div class="filter-buttons">
        <button class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>" data-filter="all">All</button>
        <button class="filter-btn <?= $filter === 'application' ? 'active' : '' ?>" data-filter="application">Applications</button>
        <button class="filter-btn <?= $filter === 'message' ? 'active' : '' ?>" data-filter="message">Messages</button>
        <button class="filter-btn <?= $filter === 'report' ? 'active' : '' ?>" data-filter="report">Reports</button>

        <!-- <label class="sort-wrapper" for="notificationSort">
            <select id="notificationSort" class="sort-select">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            </select>
        </label> -->

        <div class="action-btn-wrapper">
            <button class="action-btn" onclick="toggleBulkDropdown(event)">
                <i class="fas fa-ellipsis-vertical"></i>
            </button>
            <div class="action-dropdown" id="bulkDropdown" style="display: none;">
                <button type="button" id="toggleAllCheckboxesBtn" onclick="event.stopPropagation(); toggleAllCheckboxes()">Select all</button>
                <button onclick="bulkMarkRead(event)">Mark as read</button>
                <button onclick="bulkArchive(event)">Archive</button>
                <button onclick="bulkDelete(event)">Delete</button>
            </div>
        </div>
    </div>

    <!-- Notification Groups -->
    <?php if (empty($groups)): ?>
        <div class="no-notifications">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications yet. You're all caught up!</p>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $label => $items): ?>
            <div class="notification-group" data-group="<?= $label ?>">
                <div class="group-title"><?= $label ?></div>
                <?php foreach ($items as $notif): ?>
                    <?php
                    // Detect negative chat actions (kick, mute, leave)
                    $negativeKeywords = ['Kicked', 'Muted', 'Left'];
                    $isNegative = false;
                    foreach ($negativeKeywords as $keyword) {
                        if (strpos($notif['title'], $keyword) !== false) {
                            $isNegative = true;
                            break;
                        }
                    }
                    $extraClass = $isNegative ? 'negative' : '';
                    ?>
                    <div class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?> <?= $extraClass ?>"
                        data-id="<?= $notif['id'] ?>"
                        data-link="<?= htmlspecialchars($notif['link'] ?? '#') ?>"
                        data-type="<?= $notif['type'] ?>"
                        onclick="handleNotificationClick(this)">

                        <input type="checkbox" class="notification-checkbox" data-id="<?= $notif['id'] ?>">

                        <div class="notification-icon icon-<?= $notif['type'] ?>">
                            <i class="fas <?= getIconClass($notif['type']) ?>"></i>
                        </div>

                        <div class="notification-content">
                            <div class="title-row">
                                <div class="title"><?= htmlspecialchars($notif['title']) ?></div>
                                <div class="time"><?= time_ago(strtotime($notif['created_at'])) ?></div>
                            </div>
                            <div class="message"><?= renderNotificationMessage($notif['message']) ?></div>
                        </div>

                        <?php if (!$notif['is_read']): ?>
                            <div class="unread-dot"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    $queryParams = [];
    if (isset($_GET['filter'])) $queryParams['filter'] = $_GET['filter'];
    if ($search !== '') $queryParams['search'] = $search;
    echo renderPagination($page, $totalPages, 'notifications.php', $queryParams);
    ?>
</div>

<script>
    // UI helpers 
    function getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    }

    function getSelectedIds() {
        const checkboxes = document.querySelectorAll('.notification-checkbox:checked');
        return Array.from(checkboxes).map(cb => cb.dataset.id);
    }

    function updateSelectAllButtonLabel() {
        const button = document.getElementById('toggleAllCheckboxesBtn');
        if (!button) return;
        const checkboxes = document.querySelectorAll('.notification-checkbox');
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        button.textContent = anyChecked ? 'Unselect all' : 'Select all';
    }

    function toggleAllCheckboxes() {
        const button = document.getElementById('toggleAllCheckboxesBtn');
        const checkboxes = document.querySelectorAll('.notification-checkbox');
        const shouldCheck = button.textContent.trim() !== 'Unselect all';
        checkboxes.forEach(cb => cb.checked = shouldCheck);
        updateSelectAllButtonLabel();
    }

    // Dropdown toggle 
    function toggleBulkDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('bulkDropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }

    // Click outside to close
    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.action-btn-wrapper');
        const dropdown = document.getElementById('bulkDropdown');
        if (!wrapper || !dropdown) return;
        if (!wrapper.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    function buildNotificationsUrl(filter, sort, searchTerm = '') {
        const params = new URLSearchParams(window.location.search);

        if (filter === 'all') {
            params.delete('filter');
        } else {
            params.set('filter', filter);
        }

        if (sort === 'newest') {
            params.delete('sort');
        } else {
            params.set('sort', sort);
        }

        const trimmedTerm = (searchTerm || '').trim();
        if (!trimmedTerm) {
            params.delete('search');
        } else {
            params.set('search', trimmedTerm);
        }

        params.set('page', '1');
        return 'notifications.php?' + params.toString();
    }

    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            const term = this.value.trim();
            const activeFilter = document.querySelector('.filter-btn.active')?.dataset.filter || 'all';
            const currentSort = 'newest';

            if (!term) {
                searchTimeout = setTimeout(() => {
                    window.location.href = buildNotificationsUrl(activeFilter, currentSort, '');
                }, 800);
                return;
            }

            searchTimeout = setTimeout(() => {
                const params = new URLSearchParams(window.location.search);
                params.set('search', term);
                params.set('page', '1');
                window.location.href = 'notifications.php?' + params.toString();
            }, 800);
        });
    }

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const filter = this.dataset.filter;
            const sort = document.getElementById('notificationSort')?.value || 'newest';
            window.location.href = buildNotificationsUrl(filter, sort);
        });
    });

    const notificationSortSelect = document.getElementById('notificationSort');
    if (notificationSortSelect) {
        notificationSortSelect.addEventListener('change', function() {
            const activeFilter = document.querySelector('.filter-btn.active')?.dataset.filter || 'all';
            window.location.href = buildNotificationsUrl(activeFilter, this.value);
        });
    }

    function updateGroupVisibility() {
        document.querySelectorAll('.notification-group').forEach(group => {
            const visible = group.querySelectorAll('.notification-item:not([style*="display: none"])');
            group.style.display = visible.length > 0 ? '' : 'none';
        });
    }

    // Single click: mark as read and navigate 
    function handleNotificationClick(el) {
        if (event.target.classList.contains('notification-checkbox')) return;

        const id = el.dataset.id;
        const link = el.dataset.link;

        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: 'action=mark_read&notification_id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    el.classList.remove('unread');
                    el.classList.add('read');
                    const dot = el.querySelector('.unread-dot');
                    if (dot) dot.remove();
                    window.updateBadgeCount();
                }
            });

        if (link && link !== '#') {
            setTimeout(() => window.location.href = link, 200);
        }
    }

    // Bulk actions 
    function sendBulkAction(action, confirmMessage) {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            alert('Please select at least one notification.');
            return;
        }
        if (confirmMessage && !confirm(confirmMessage)) return;

        const token = getCSRFToken();
        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': token
                },
                body: 'action=' + action + '&ids=' + JSON.stringify(ids)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (['bulk_mark_read', 'bulk_archive', 'bulk_delete'].includes(action)) {
                        window.location.reload();
                        return;
                    }

                    // Update UI
                    ids.forEach(id => {
                        const item = document.querySelector(`.notification-item[data-id="${id}"]`);
                        if (item) {
                            if (action === 'bulk_mark_read') {
                                item.classList.remove('unread');
                                item.classList.add('read');
                                const dot = item.querySelector('.unread-dot');
                                if (dot) dot.remove();
                            } else {
                                // archive or delete – remove from DOM
                                item.style.display = 'none';
                            }
                        }
                    });
                    document.getElementById('bulkDropdown').style.display = 'none';
                    if (typeof window.updateBadgeCount === 'function') {
                        window.updateBadgeCount();
                    }
                    updateSelectAllButtonLabel();
                    updateGroupVisibility();
                } else {
                    alert(data.error || 'Action failed.');
                }
            })
            .catch(err => {
                alert('An error occurred.');
                console.error(err);
            });
    }

    function bulkMarkRead(event) {
        event.stopPropagation();
        sendBulkAction('bulk_mark_read', null);
    }

    function bulkArchive(event) {
        event.stopPropagation();
        sendBulkAction('bulk_archive', 'Archive selected notifications?');
    }

    function bulkDelete(event) {
        event.stopPropagation();
        sendBulkAction('bulk_delete', 'Delete selected notifications permanently?');
    }

    // Update label on checkbox change 
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('notification-checkbox')) {
            updateSelectAllButtonLabel();
        }
    });

    // Mark all as read (hidden button)
    document.getElementById('markAllBtn')?.addEventListener('click', function() {
        if (!confirm('Mark all notifications as read?')) return;
        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: 'action=mark_all_read'
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(el => {
                        el.classList.remove('unread');
                        el.classList.add('read');
                        const dot = el.querySelector('.unread-dot');
                        if (dot) dot.remove();
                    });
                    window.updateBadgeCount();
                    document.querySelector('.notifications-header p strong').textContent = '0';
                }
            });
    });

    // Init
    updateSelectAllButtonLabel();
    updateGroupVisibility();
</script>

<?php include 'footer.php'; ?>
