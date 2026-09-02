<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Sync MySQL timezone with PHP to prevent timestamp mismatch
$conn->query("SET time_zone = '" . date('P') . "'");

function time_ago(int $unix): string
{
    $diff = time() - $unix;
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    return floor($diff / 60)    . ' minutes ago';
    if ($diff < 86400)   return floor($diff / 3600)  . ' hours ago';
    if ($diff < 604800)  return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    return date('M j, Y', $unix);
}

function renderActivityDescription(string $description): string
{
    $escaped = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    return str_ireplace(
        ['&lt;strong&gt;', '&lt;/strong&gt;'],
        ['<strong>', '</strong>'],
        $escaped
    );
}

// Load four activity rows at a time. The fifth row only determines whether
// another batch exists, avoiding a separate COUNT query.
$activityLimit = 4;
$activityPage = max(1, (int)($_GET['activity_page'] ?? 1));
$activityOffset = ($activityPage - 1) * $activityLimit;
$activityFetchLimit = $activityLimit + 1;

$logStmt = $conn->prepare("
    SELECT id, type, title, status, description, created_at,
           UNIX_TIMESTAMP(created_at) AS created_unix
    FROM user_activity_log
    WHERE user_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT ?, ?
");
$logStmt->bind_param("iii", $user_id, $activityOffset, $activityFetchLimit);
$logStmt->execute();
$activityRows = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$logStmt->close();

$hasMoreActivities = count($activityRows) > $activityLimit;
$activities = array_slice($activityRows, 0, $activityLimit);

if (($_GET['activities_only'] ?? '') === '1') {
    header('Content-Type: application/json');

    $payload = array_map(static function (array $activity): array {
        $timestamp = (int)$activity['created_unix'];
        return [
            'id' => (int)$activity['id'],
            'type' => (string)$activity['type'],
            'title' => (string)$activity['title'],
            'timestamp' => $timestamp,
            'date' => date('F j, Y', $timestamp),
            'description_html' => renderActivityDescription((string)$activity['description'])
        ];
    }, $activities);

    echo json_encode([
        'success' => true,
        'activities' => $payload,
        'has_more' => $hasMoreActivities,
        'next_page' => $activityPage + 1
    ]);
    exit;
}

// Fetch user details (including barangay)
$userStmt = $conn->prepare("
    SELECT u.fname, u.lname, u.email, u.phone_no, u.role, u.created_at, b.name as barangay_name
    FROM users_tbl u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id = ?
");

$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();

$user = [
    'first_name' => $userData['fname']     ?? $_SESSION['first_name'] ?? 'User',
    'last_name'  => $userData['lname']     ?? $_SESSION['last_name']  ?? '',
    'email'      => $userData['email']     ?? $_SESSION['email']      ?? '',
    'phone'      => $userData['phone_no']  ?? 'Not provided',
    'role'       => $userData['role']      ?? $_SESSION['role']       ?? 'user',
    'barangay'   => $userData['barangay_name'] ?? 'Not set',
    'joined'     => date('F j, Y', strtotime($userData['created_at'] ?? 'now'))
];

// Helper: format role name
function formatRole($role) {
    return $role === 'super_admin' ? 'Super Admin' : ucfirst($role);
}

include 'header.php';
?>
<link rel="stylesheet" href="profile.css">

<div class="account-container">
    <div class="account-header">
        <div class="user-header-grid">
            <div class="user-profile-main">
                <div class="user-avatar">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="user-details">
                    <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p>Joined <?php echo $user['joined']; ?></p>
                </div>
            </div>
            <button type="button" class="user-menu-trigger" aria-label="More options" id="userMenuTrigger">
                <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
            <div class="user-menu-dropdown" id="userMenuDropdown" style="display: none;">
                <button onclick="window.parent.showEditProfileModal()"> <i class="fa-solid fa-pen"></i> Edit Profile</button>
                <button onclick="<?php echo isset($_SESSION['first_name']) ? 'showLogout()' : 'showLogin()'; ?>"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</button>
            </div>
        </div>
    </div>

    <button id="editProfileBtn" style="display: none;"></button>

    <h2>Personal Information</h2>
    <div class="personal-info-container">
        <div class="info-grid">
            <div class="info-item">
                <label>First Name</label>
                <p><?php echo htmlspecialchars($user['first_name']); ?></p>
            </div>
            <div class="info-item">
                <label>Last Name</label>
                <p><?php echo htmlspecialchars($user['last_name']); ?></p>
            </div>
            <div class="info-item">
                <label>Email Address</label>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="info-item">
                <label>Phone Number</label>
                <p><?php echo htmlspecialchars($user['phone']); ?></p>
            </div>
            <div class="info-item">
                <label>Barangay</label>
                <p><?php echo htmlspecialchars($user['barangay']); ?></p>
            </div>
            <div class="info-item">
                <label>User Role</label>
                <p><?php echo htmlspecialchars(formatRole($user['role'])); ?></p>
            </div>
        </div>
    </div>

    <h2>Recent Activity</h2>
    <div class="recent-act-container">
        <?php if (empty($activities)): ?>
            <p class="no-activity">No recent activity to show.</p>
        <?php else: ?>
            <div class="activity-list"
                id="activityList"
                data-next-page="2"
                data-has-more="<?= $hasMoreActivities ? '1' : '0' ?>">
                <?php foreach ($activities as $act):
                    $ts = (int) $act['created_unix'];
                ?>
                    <div class="activity-item"
                        data-type="<?php echo htmlspecialchars($act['type']); ?>"
                        data-timestamp="<?php echo $ts; ?>">
                        <div class="activity-main">
                            <div class="activity-title">
                                <h3><?php echo htmlspecialchars($act['title']); ?></h3>
                                <span class="time-ago" data-ts="<?php echo $ts; ?>">
                                    <?php echo time_ago($ts); ?>
                                </span>
                            </div>
                        </div>
                        <p class="activity-date"><?php echo date('F j, Y', $ts); ?></p>
                        <p class="activity-description"><?php echo renderActivityDescription((string)$act['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateTimeAgo() {
        const nowSeconds = Math.floor(Date.now() / 1000);

        document.querySelectorAll('.time-ago').forEach(el => {
            const ts = parseInt(el.getAttribute('data-ts'), 10);
            if (!ts || Number.isNaN(ts)) return;

            const diff = Math.max(0, nowSeconds - ts);
            let text;

            if (diff < 60) text = 'Just now';
            else if (diff < 3600) text = Math.floor(diff / 60) + ' minutes ago';
            else if (diff < 86400) text = Math.floor(diff / 3600) + ' hours ago';
            else if (diff < 604800) text = Math.floor(diff / 86400) + ' days ago';
            else if (diff < 2592000) text = Math.floor(diff / 604800) + ' weeks ago';
            else text = new Date(ts * 1000).toLocaleDateString('en-PH');

            if (el.textContent.trim() !== text) el.textContent = text;
        });
    }

    updateTimeAgo();
    setInterval(updateTimeAgo, 30000); // re-check every 30 seconds

    const activityScrollContainer = document.querySelector('.recent-act-container');
    const activityList = document.getElementById('activityList');
    let isLoadingActivities = false;
    const activitySkeletonMinimumMs = 700;

    async function keepSkeletonVisible(startedAt) {
        const elapsed = performance.now() - startedAt;
        const remaining = Math.max(0, activitySkeletonMinimumMs - elapsed);

        if (remaining > 0) {
            await new Promise(resolve => window.setTimeout(resolve, remaining));
        }
    }

    function showActivitySkeleton() {
        if (!activityList || document.getElementById('activityLoadingSkeleton')) return;

        const skeleton = document.createElement('div');
        skeleton.id = 'activityLoadingSkeleton';
        skeleton.className = 'activity-item activity-skeleton';
        skeleton.setAttribute('aria-hidden', 'true');
        skeleton.innerHTML = `
            <div class="skeleton-title-row">
                <span class="skeleton-line skeleton-title"></span>
                <span class="skeleton-line skeleton-time"></span>
            </div>
            <span class="skeleton-line skeleton-date"></span>
            <span class="skeleton-line skeleton-description"></span>
            <span class="skeleton-line skeleton-description-short"></span>
        `;

        activityList.setAttribute('aria-busy', 'true');
        activityList.appendChild(skeleton);
    }

    function hideActivitySkeleton() {
        document.getElementById('activityLoadingSkeleton')?.remove();
        activityList?.removeAttribute('aria-busy');
    }

    function createActivityElement(activity) {
        const item = document.createElement('div');
        item.className = 'activity-item';
        item.dataset.type = String(activity.type || '');
        item.dataset.timestamp = String(activity.timestamp || '');

        const main = document.createElement('div');
        main.className = 'activity-main';

        const titleRow = document.createElement('div');
        titleRow.className = 'activity-title';

        const title = document.createElement('h3');
        title.textContent = String(activity.title || 'Activity');

        const time = document.createElement('span');
        time.className = 'time-ago';
        time.dataset.ts = String(activity.timestamp || '');

        const date = document.createElement('p');
        date.className = 'activity-date';
        date.textContent = String(activity.date || '');

        const description = document.createElement('p');
        description.className = 'activity-description';
        // The server escapes all markup except the existing <strong> styling.
        description.innerHTML = String(activity.description_html || '');

        titleRow.append(title, time);
        main.appendChild(titleRow);
        item.append(main, date, description);
        return item;
    }

    async function loadMoreActivities() {
        if (!activityList || isLoadingActivities || activityList.dataset.hasMore !== '1') return;

        isLoadingActivities = true;
        const nextPage = Math.max(2, parseInt(activityList.dataset.nextPage || '2', 10));
        const skeletonStartedAt = performance.now();
        showActivitySkeleton();

        try {
            const response = await fetch(`profile.php?activities_only=1&activity_page=${nextPage}`, {
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (!response.ok || !data.success || !Array.isArray(data.activities)) {
                throw new Error(data.error || 'Unable to load activity history.');
            }

            await keepSkeletonVisible(skeletonStartedAt);

            const fragment = document.createDocumentFragment();
            data.activities.forEach(activity => fragment.appendChild(createActivityElement(activity)));
            activityList.appendChild(fragment);
            activityList.dataset.nextPage = String(data.next_page || nextPage + 1);
            activityList.dataset.hasMore = data.has_more ? '1' : '0';

            updateTimeAgo();
        } catch (error) {
            await keepSkeletonVisible(skeletonStartedAt);
            console.error('Failed to load profile activities:', error);
        } finally {
            isLoadingActivities = false;
            hideActivitySkeleton();
        }
    }

    if (activityScrollContainer && activityList) {
        activityScrollContainer.addEventListener('scroll', () => {
            const remaining = activityScrollContainer.scrollHeight
                - activityScrollContainer.scrollTop
                - activityScrollContainer.clientHeight;

            if (remaining <= 64) {
                loadMoreActivities();
            }
        }, { passive: true });
    }

    // Dropdown toggle
    const trigger = document.getElementById('userMenuTrigger');
    const dropdown = document.getElementById('userMenuDropdown');

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
</script>

<?php include 'footer.php'; ?>
