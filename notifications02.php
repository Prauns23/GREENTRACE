<?php
require_once 'init_session.php';
require_once 'config.php';
require_once 'pagination_helper.php';
require_once 'helpers/badge_helpers.php';
require_once 'helpers/admin_authorization.php';

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
            return 'fa-comments';
        default:
            return 'fa-bell';
    }
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$notificationRole = currentDatabaseRole();

//  AJAX handlers 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // CSRF validation for all POST actions
    $headers = getallheaders();
    $csrf_token = $_POST['csrf_token'] ?? ($headers['X-CSRF-Token'] ?? '');
    if (!verifyCSRFToken($csrf_token) && !verifySameOriginRequest()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Single mark as read
    if ($action === 'mark_read') {
        $notif_id = (int)($_POST['notification_id'] ?? 0);
        if ($notif_id) {
            supabaseUpdate('notifications', ['id' => 'eq.' . $notif_id, 'user_id' => 'eq.' . $user_id], ['is_read' => true]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Invalid ID']);
        }
        exit;
    }

    // Mark all as read
    if ($action === 'mark_all_read') {
        supabaseUpdate('notifications', ['user_id' => 'eq.' . $user_id, 'archived' => 'eq.false'], ['is_read' => true]);
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

        $ids = array_values(array_unique(array_filter(array_map(
            static fn($id) => filter_var($id, FILTER_VALIDATE_INT),
            $ids
        ))));
        if (!$ids) {
            http_response_code(422);
            echo json_encode(['error' => 'No valid notification IDs provided']);
            exit;
        }
        $filters = [
            'id' => 'in.(' . implode(',', $ids) . ')',
            'user_id' => 'eq.' . $user_id,
        ];
        if ($action === 'bulk_mark_read') {
            supabaseUpdate('notifications', $filters, ['is_read' => true]);
        } elseif ($action === 'bulk_archive') {
            supabaseUpdate('notifications', $filters, ['archived' => true]);
        } else {
            supabaseDelete('notifications', $filters);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // Invalid action
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

//  Main page 
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$allowedFilters = ['all', 'application', 'report', 'message'];
$filter = strtolower(trim($_GET['filter'] ?? 'all'));
if (!in_array($filter, $allowedFilters, true)) $filter = 'all';
$search = trim($_GET['search'] ?? '');
$filterLabels = [
    'all' => 'All',
    'message' => 'Messages',
    'application' => 'Applications',
    'report' => 'Reports',
];
$activeFilterLabel = $filterLabels[$filter];
$notificationsPage = basename($_SERVER['PHP_SELF']);

// Count total non-archived notifications
$allNotifications = supabaseSelect('notifications', ['user_id' => 'eq.' . $user_id, 'archived' => 'eq.false'], '*', ['order' => 'created_at.desc']);
$allUnreadCount = count(array_filter($allNotifications, static fn($n) => empty($n['is_read'])));
$allNotifications = array_values(array_filter($allNotifications, static function ($notification) use ($filter, $search) {
    if ($filter !== 'all' && strtolower((string)($notification['type'] ?? '')) !== $filter) return false;
    if ($search === '') return true;
    $haystack = strtolower(strip_tags(($notification['title'] ?? '') . ' ' . ($notification['message'] ?? '')));
    return str_contains($haystack, strtolower($search));
}));
$total = count($allNotifications);
$totalPages = max(1, (int)ceil($total / $limit));

// Fetch only non‑archived notifications
$notifications = array_slice($allNotifications, $offset, $limit);

$unreadCount = $allUnreadCount;
$badgeNotifications = [];
try {
    $badgeNotifications = array_values(array_filter(supabaseRpc('list_android_badge_notifications'), static fn(array $row): bool => empty($row['is_read']) && empty($row['archived'])));
} catch (Throwable $error) {
    error_log('Badge notifications load failed: ' . $error->getMessage());
}
$unreadCount += count($badgeNotifications);

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
<link rel="stylesheet" href="notifications.css?v=<?= filemtime(__DIR__ . '/notifications.css') ?>">

<div class="notifications-page">
    <!-- Header -->
    <div class="notifications-header">
        <h1>Notifications</h1>
        <p>You have <strong><?= $unreadCount ?></strong> notification<?= $unreadCount !== 1 ? '(s)' : '' ?> to go through — Click a notification to view details.</p>
    </div>

    <!-- Search Bar -->
    <form class="notification-controls" method="get" action="<?= htmlspecialchars($notificationsPage, ENT_QUOTES, 'UTF-8') ?>">
        <div class="search-bar">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" id="searchInput" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>" placeholder="Search your notifications here">
        </div>
        <div class="filter-buttons">
            <div class="filter-dropdown-wrapper">
                <button type="button" class="filter-dropdown-toggle" id="filterDropdownToggle" aria-haspopup="menu" aria-controls="filterDropdown" aria-expanded="false">
                    <span>Filter: <?= htmlspecialchars($activeFilterLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <i class="fas fa-chevron-down filter-chevron" aria-hidden="true"></i>
                </button>
                <div class="filter-dropdown" id="filterDropdown" role="menu" hidden>
                    <?php foreach ($filterLabels as $filterValue => $filterLabel): ?>
                        <button type="button"
                            class="filter-btn <?= $filter === $filterValue ? 'active' : '' ?>"
                            data-filter="<?= htmlspecialchars($filterValue, ENT_QUOTES, 'UTF-8') ?>"
                            role="menuitem"
                            aria-current="<?= $filter === $filterValue ? 'true' : 'false' ?>">
                            <?= htmlspecialchars($filterLabel, ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="notificationFilter" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="action-btn-wrapper">
                <button type="button" class="action-btn" id="notificationActionsToggle" onclick="toggleBulkDropdown(event)" aria-label="Notification actions" aria-haspopup="menu" aria-controls="bulkDropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-vertical" aria-hidden="true"></i>
                </button>
                <div class="action-dropdown" id="bulkDropdown" role="menu" hidden>
                    <button type="button" id="toggleAllCheckboxesBtn" onclick="event.stopPropagation(); toggleAllCheckboxes()">Select all</button>
                    <button type="button" onclick="bulkMarkRead(event)">Mark as read</button>
                    <button type="button" onclick="bulkArchive(event)">Archive</button>
                    <button type="button" onclick="bulkDelete(event)">Delete</button>
                </div>
            </div>
        </div>
    </form>

    <?php if ($badgeNotifications): ?>
        <section class="notification-group badge-notification-group" aria-label="Badge notifications">
            <div class="group-title">Badge achievements</div>
            <?php foreach ($badgeNotifications as $badgeNotification): ?>
                <a class="notification-item unread" href="badges.php" data-link="badges.php">
                    <div class="notification-icon icon-badge"><i class="fas fa-award"></i></div>
                    <div class="notification-content">
                        <div class="title-row">
                            <div class="title"><?= htmlspecialchars((string)($badgeNotification['title'] ?? 'You earned a new badge!')) ?></div>
                            <div class="time"><?= time_ago(strtotime($badgeNotification['created_at'] ?? 'now')) ?></div>
                        </div>
                        <div class="message"><?= htmlspecialchars((string)($badgeNotification['message'] ?? 'Visit Badges to view and equip it.')) ?></div>
                    </div>
                    <div class="unread-dot"></div>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

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
                    <?php $isNegative = stripos(($notif['title'] ?? '') . ' ' . ($notif['message'] ?? ''), 'reject') !== false; ?>
                    <div class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?> <?= $isNegative ? 'notification-negative' : '' ?>"
                        data-id="<?= $notif['id'] ?>"
                        data-link="<?= htmlspecialchars($notif['link'] ?? '#') ?>"
                        data-type="<?= $notif['type'] ?>"
                        data-destination="<?= htmlspecialchars((string)($notif['destination'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-entity-id="<?= htmlspecialchars((string)($notif['entity_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-role="<?= htmlspecialchars($notificationRole, ENT_QUOTES, 'UTF-8') ?>"
                        onclick="handleNotificationClick(event, this)">

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
    <?php
    $queryParams = [];
    if (isset($_GET['filter'])) $queryParams['filter'] = $_GET['filter'];
    if (isset($_GET['search'])) $queryParams['search'] = $_GET['search'];
    echo renderPagination($page, $totalPages, $notificationsPage, $queryParams);
    ?>
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

    function setBulkDropdownOpen(isOpen) {
        const toggle = document.getElementById('notificationActionsToggle');
        const dropdown = document.getElementById('bulkDropdown');
        if (!toggle || !dropdown) return;
        dropdown.hidden = !isOpen;
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function setFilterDropdownOpen(isOpen) {
        const toggle = document.getElementById('filterDropdownToggle');
        const dropdown = document.getElementById('filterDropdown');
        if (!toggle || !dropdown) return;
        dropdown.hidden = !isOpen;
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    // Both menus use click-only disclosure and close each other when opened.
    function toggleBulkDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('bulkDropdown');
        if (!dropdown) return;
        setFilterDropdownOpen(false);
        setBulkDropdownOpen(dropdown.hidden);
    }

    // Click outside to close
    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.action-btn-wrapper');
        const dropdown = document.getElementById('bulkDropdown');
        if (!wrapper || !dropdown) return;
        if (!wrapper.contains(e.target)) {
            setBulkDropdownOpen(false);
        }
    });

    const filterDropdownToggle = document.getElementById('filterDropdownToggle');
    const filterDropdown = document.getElementById('filterDropdown');
    const filterDropdownWrapper = document.querySelector('.filter-dropdown-wrapper');

    filterDropdownToggle?.addEventListener('click', function(event) {
        event.stopPropagation();
        setBulkDropdownOpen(false);
        setFilterDropdownOpen(filterDropdown.hidden);
    });

    document.addEventListener('click', function(event) {
        if (filterDropdownWrapper && !filterDropdownWrapper.contains(event.target)) {
            setFilterDropdownOpen(false);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key !== 'Escape') return;
        const filterWasOpen = filterDropdownToggle?.getAttribute('aria-expanded') === 'true';
        const actionToggle = document.getElementById('notificationActionsToggle');
        const actionsWereOpen = actionToggle?.getAttribute('aria-expanded') === 'true';
        setFilterDropdownOpen(false);
        setBulkDropdownOpen(false);
        if (filterWasOpen) filterDropdownToggle?.focus();
        else if (actionsWereOpen) actionToggle?.focus();
    });

    document.querySelectorAll('.filter-btn[data-filter]').forEach(button => {
        button.addEventListener('click', function() {
            const filterInput = document.getElementById('notificationFilter');
            if (!filterInput || this.classList.contains('active')) return;
            filterInput.value = this.dataset.filter;
            this.closest('form')?.requestSubmit();
        });
    });

    function updateGroupVisibility() {
        document.querySelectorAll('.notification-group').forEach(group => {
            const visible = group.querySelectorAll('.notification-item:not([style*="display: none"])');
            group.style.display = visible.length > 0 ? '' : 'none';
        });
    }

    // Resolve shared notification destinations to the correct web screen.
    function resolveNotificationLink(el) {
        const type = (el.dataset.type || '').toLowerCase();
        const destination = (el.dataset.destination || '').toLowerCase();
        const entityId = el.dataset.entityId || '';
        const role = (el.dataset.role || '').toLowerCase();
        const isAdmin = role === 'admin' || role === 'super_admin';
        const notificationText = el.textContent || '';
        const isParticipationUpdate = /(?:pending approval|activity (?:confirmed|joined)|application (?:submitted|rejected|approved|cancelled|canceled)|rejected application|removed from activity|rejoin)/i.test(notificationText);
        if ((type === 'application' && destination !== 'applications' && !isAdmin) || (destination === 'activity' && isParticipationUpdate)) {
            return 'my_applications.php';
        }

        if (destination === 'conversation' && entityId) {
            return `message.php?conversation_id=${encodeURIComponent(entityId)}`;
        }
        if (destination === 'user_moderation' || type === 'user_report') {
            return isAdmin ? 'admin/user_moderation.php' : 'moderation_notices.php';
        }
        if (type === 'application' || destination === 'applications') {
            return isAdmin ? 'admin/application_activity.php' : 'my_applications.php';
        }
        if (type === 'report' || destination === 'report') {
            return isAdmin ? 'forestmap.php' : 'my_reports.php';
        }
        if (destination === 'badges') return 'badges.php';

        let link = el.dataset.link || '';
        const activityDetailsMatch = link.match(/^activity_details\.php\?activity_id=(\d+)$/i);
        if (activityDetailsMatch) link = 'pages/activity_details.php?id=' + activityDetailsMatch[1];
        return link && link !== '#' ?
            link :
            (type === 'activity' ? 'activities.php' : (window.location.pathname.split('/').pop() || 'notifications.php'));
    }

    // Single click: mark as read and navigate.
    function handleNotificationClick(event, el) {
        if (event.target.closest('.notification-checkbox')) return;

        const id = el.dataset.id;
        const link = resolveNotificationLink(el);

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

        if (el.dataset.destination === 'profile_verification') {
            setTimeout(() => {
                if (typeof window.showEditProfileModal === 'function') window.showEditProfileModal();
            }, 200);
            return;
        }

        if (link && link !== '#') {
            setTimeout(() => window.location.href = link, 200);
        }
    }

    //  Bulk actions 
    async function sendBulkAction(action, confirmMessage) {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            alert('Please select at least one notification.');
            return;
        }
        if (confirmMessage && !await greenTraceConfirm({
                title: action === 'bulk_delete' ? 'Delete notifications?' : 'Archive notifications?',
                message: confirmMessage,
                confirmLabel: action === 'bulk_delete' ? 'Delete' : 'Archive',
                tone: action === 'bulk_delete' ? 'danger' : 'default'
            })) return;

        const token = getCSRFToken();
        const loadingToken = greenTraceLoading.show({
            title: action === 'bulk_delete' ? 'Deleting notifications' : 'Archiving notifications',
            message: 'Updating your notification list…'
        });
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
                    setBulkDropdownOpen(false);
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
            })
            .finally(() => greenTraceLoading.hide(loadingToken));
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
    document.getElementById('markAllBtn')?.addEventListener('click', async function() {
        if (!await greenTraceConfirm({
                title: 'Mark all as read?',
                message: 'All notifications will be marked as read.',
                confirmLabel: 'Mark as read'
            })) return;
        const loadingToken = greenTraceLoading.show({
            title: 'Updating notifications',
            message: 'Marking all notifications as read…'
        });
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
            })
            .finally(() => greenTraceLoading.hide(loadingToken));
    });

    // Init
    updateSelectAllButtonLabel();
    updateGroupVisibility();
</script>

<?php include 'footer.php'; ?>