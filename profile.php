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

// Fetch user details
$userStmt = $conn->prepare("SELECT fname, lname, email, phone_no, role, created_at FROM users_tbl WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();

$user = [
    'first_name' => $userData['fname']     ?? $_SESSION['first_name'] ?? 'User',
    'last_name'  => $userData['lname']     ?? $_SESSION['last_name']  ?? '',
    'email'      => $userData['email']     ?? $_SESSION['email']      ?? '',
    'phone'      => $userData['phone_no']  ?? 'Not provided',
    'role'       => $userData['role']      ?? $_SESSION['role']       ?? 'user',
    'joined'     => date('F j, Y', strtotime($userData['created_at'] ?? 'now'))
];

// Fetch activity log — UNIX_TIMESTAMP() returns a timezone-safe integer
$logStmt = $conn->prepare("
    SELECT id, type, title, status, description, created_at,
           UNIX_TIMESTAMP(created_at) AS created_unix
    FROM user_activity_log
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$logStmt->bind_param("i", $user_id);
$logStmt->execute();
$activities = $logStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper: server-side relative time for initial render
// Accepts a Unix timestamp integer
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
                        <p class="activity-description"><?php echo $act['description']; ?></p>
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

            if      (diff < 60)      text = 'Just now';
            else if (diff < 3600)    text = Math.floor(diff / 60)    + ' minutes ago';
            else if (diff < 86400)   text = Math.floor(diff / 3600)  + ' hours ago';
            else if (diff < 604800)  text = Math.floor(diff / 86400) + ' days ago';
            else if (diff < 2592000) text = Math.floor(diff / 604800) + ' weeks ago';
            else                     text = new Date(ts * 1000).toLocaleDateString('en-PH');

            if (el.textContent.trim() !== text) el.textContent = text;
        });
    }

    updateTimeAgo();
    setInterval(updateTimeAgo, 30000); // re-check every 30 seconds
</script>

<?php include 'footer.php'; ?>