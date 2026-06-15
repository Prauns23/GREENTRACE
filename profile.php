<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details (unchanged)
$userStmt = $conn->prepare("SELECT fname, lname, email, phone_no, role, created_at FROM users_tbl WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();

$user = [
    'first_name' => $userData['fname'] ?? $_SESSION['first_name'] ?? 'User',
    'last_name'  => $userData['lname'] ?? $_SESSION['last_name'] ?? '',
    'email'      => $userData['email'] ?? $_SESSION['email'] ?? '',
    'phone'      => $userData['phone_no'] ?? 'Not provided',
    'role'       => $userData['role'] ?? $_SESSION['role'] ?? 'user',
    'joined'     => date('F j, Y', strtotime($userData['created_at'] ?? 'now'))
];

// Fetch activity log
$logStmt = $conn->prepare("
    SELECT id, type, title, status, description, created_at 
    FROM user_activity_log 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$logStmt->bind_param("i", $user_id);
$logStmt->execute();
$activities = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper: relative time (displayed on server, but will be updated by JS)
function time_ago($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return round($diff / 60) . ' minutes ago';
    if ($diff < 86400) return round($diff / 3600) . ' hours ago';
    if ($diff < 604800) return round($diff / 86400) . ' days ago';
    if ($diff < 2592000) return round($diff / 604800) . ' weeks ago';
    return date('M j, Y', strtotime($timestamp));
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
            <button type="button" class="user-menu-trigger" aria-label="More options">
                <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
        </div>
    </div>

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
                <label>User Role</label>
                <p><?php echo ucfirst($user['role']); ?></p>
            </div>
        </div>
    </div>

    <h2>Recent Activity</h2>
    <div class="recent-act-container">
        <?php if (empty($activities)): ?>
            <p class="no-activity">No recent activity to show.</p>
        <?php else: ?>
            <div class="activity-list" id="activityList">
                <?php foreach ($activities as $act): ?>
                    <div class="activity-item" data-type="<?php echo $act['type']; ?>" data-timestamp="<?php echo strtotime($act['created_at']); ?>">
                        <div class="activity-main">
                            <div class="activity-title">
                                <h3><?php echo htmlspecialchars($act['title']); ?></h3>
                                <span class="time-ago" data-ts="<?php echo strtotime($act['created_at']); ?>">
                                    <?php echo time_ago($act['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        <p class="activity-date"><?php echo date('F j, Y', strtotime($act['created_at'])); ?></p>
                        <p class="activity-description"><?php echo $act['description']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Update all "time ago" spans every 60 seconds
    function refreshTimeAgo() {
        const spans = document.querySelectorAll('.time-ago');
        const now = Math.floor(Date.now() / 1000);
        spans.forEach(span => {
            const ts = parseInt(span.dataset.ts);
            if (!ts) return;
            const diff = now - ts;
            let text = '';
            if (diff < 60) text = 'Just now';
            else if (diff < 3600) text = Math.floor(diff / 60) + ' minutes ago';
            else if (diff < 86400) text = Math.floor(diff / 3600) + ' hours ago';
            else if (diff < 604800) text = Math.floor(diff / 86400) + ' days ago';
            else if (diff < 2592000) text = Math.floor(diff / 604800) + ' weeks ago';
            else text = new Date(ts * 1000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            span.textContent = text;
        });
    }
    refreshTimeAgo();
    setInterval(refreshTimeAgo, 60000); // every minute
</script>

<?php include 'footer.php'; ?>