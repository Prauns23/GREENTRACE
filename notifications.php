<?php
require_once 'init_session.php';
require_once 'config.php';
require_once 'pagination_helper.php';

$conn->query("SET time_zone = '" . date('P') . "'");

// Helper: time ago
function time_ago($unix) {
    $diff = time() - $unix;
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400)   return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800)  return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    return date('M j, Y', $unix);
}

function getIconClass($type) {
    switch ($type) {
        case 'application': return 'fa-file-alt';
        case 'activity':    return 'fa-bell';
        case 'report':      return 'fa-exclamation-triangle';
        default:            return 'fa-bell';
    }
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

//  AJAX handlers 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // CSRF validation for all POST actions
    $headers = getallheaders();
    $csrf_token = $_POST['csrf_token'] ?? ($headers['X-CSRF-Token'] ?? '');
    if (!verifyCSRFToken($csrf_token)) {
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

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

    //  Bulk actions 
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

//  Main page 
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Count total non-archived notifications
$countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND archived = 0";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($total / $limit);

// Fetch only non‑archived notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND archived = 0 ORDER BY created_at DESC");
$stmt->bind_param("iii", $user_id, $offset, $limit);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unreadCount = 0;
foreach ($notifications as $n) {
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
        <input type="text" id="searchInput" placeholder="Search your notifications here">
    </div>

    <!-- Filter Buttons with dropdown inside wrapper -->
    <div class="filter-buttons">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="application">Applications</button>

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
                    <div class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>"
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
                            <div class="message"><?= $notif['message'] ?></div>
                        </div>

                        <?php if (!$notif['is_read']): ?>
                            <div class="unread-dot"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    //  UI helpers 
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

    //  Dropdown toggle 
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

    // Search & Filter 
    document.getElementById('searchInput').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.notification-item').forEach(item => {
            item.style.display = item.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
        updateGroupVisibility();
    });

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            document.querySelectorAll('.notification-item').forEach(item => {
                if (filter === 'all' || item.dataset.type === filter) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
            updateGroupVisibility();
        });
    });

    function updateGroupVisibility() {
        document.querySelectorAll('.notification-group').forEach(group => {
            const visible = group.querySelectorAll('.notification-item:not([style*="display: none"])');
            group.style.display = visible.length > 0 ? '' : 'none';
        });
    }

    //  Single click: mark as read and navigate 
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

    //  Bulk actions 
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

    //  Update label on checkbox change 
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