<?php
require_once 'init_session.php';
require_once 'config.php';

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

// Helper: icon class
function getIconClass($type)
{
    switch ($type) {
        case 'application':
            return 'fa-file-alt';
        case 'activity':
            return 'fa-bell';
        case 'report':
            return 'fa-exclamation-triangle';
        default:
            return 'fa-bell';
    }
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle AJAX: mark notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    header('Content-Type: application/json');
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

// Handle "Mark all as read"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    header('Content-Type: application/json');
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// Fetch notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count unread
$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}

// Group by time
$groups = [
    'Today' => [],
    'This Week' => [],
    'This Month' => [],
    'Older' => []
];

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

    <!-- Filter Buttons -->
    <div class="filter-buttons">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="application">Applications</button>
    </div>

    <!-- Mark all as read (centered) -->
    <!-- <div class="mark-all-wrapper">
        <button class="mark-all-btn" id="markAllBtn"><i class="fa-solid fa-envelope-open"></i></button>
    </div> -->


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
                        <!-- Checkbox -->
                        <input type="checkbox" class="notification-checkbox" data-id="<?= $notif['id'] ?>">
                        <div class="notification-icon icon-<?= $notif['type'] ?>">
                            <i class="fas <?= getIconClass($notif['type']) ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="title-row">
                                <div class="title"><?= htmlspecialchars($notif['title']) ?></div>
                                <div class="time">
                                    <!-- <i class="far fa-clock"></i> -->
                                    <?= time_ago(strtotime($notif['created_at'])) ?>
                                </div>
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
    // Search filter
    document.getElementById('searchInput').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.notification-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(term) ? '' : 'none';
        });
        updateGroupVisibility();
    });

    // Filter buttons (All / Applications)
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            document.querySelectorAll('.notification-item').forEach(item => {
                if (filter === 'all') {
                    item.style.display = '';
                } else {
                    const type = item.dataset.type;
                    item.style.display = (type === filter) ? '' : 'none';
                }
            });
            updateGroupVisibility();
        });
    });

    function updateGroupVisibility() {
        document.querySelectorAll('.notification-group').forEach(group => {
            const visibleItems = group.querySelectorAll('.notification-item[style*="display: none"]');
            const totalItems = group.querySelectorAll('.notification-item').length;
            group.style.display = (visibleItems.length === totalItems) ? 'none' : '';
        });
    }

    // Click notification: mark as read and navigate
    function handleNotificationClick(el) {
        // If the click was on the checkbox, don't mark as read or navigate
        if (event.target.classList.contains('notification-checkbox')) return;

        const id = el.dataset.id;
        const link = el.dataset.link;

        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
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
            setTimeout(() => {
                window.location.href = link;
            }, 200);
        }
    }

    // Mark all as read
    document.getElementById('markAllBtn').addEventListener('click', function() {
        if (!confirm('Mark all notifications as read?')) return;
        fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
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
</script>

<?php include 'footer.php'; ?>