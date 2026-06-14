<?php
require_once 'init_session.php';
require_once 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

$user_ud = $_SESSION['user_id'];
// Placeholder data 

$user = [
    'first_name' => $_SESSION['first_name'] ?? 'James Dean',
    'last_name' => $_SESSION['last_name'] ?? 'Flores',
    'email' => $_SESSION['email'] ?? 'jamesdean@example.com',
    'phone' => $_SESSION['phone_no'] ?? '09196410680',
    'role' => $_SESSION['role'] ?? 'user', // 'Volunteer' for display
    'joined' => $_SESSION['created_at'] ?? 'MM-DD-YYYY',
    'dob' => 'MM-DD-YYYY'
];

// Placeholder recent activities - structure: title, time_ago, status, event_date, description

$recentActivities = [
    [
        'title' => 'Tree Identification Workshop',
        'time_ago' => '14 hours ago',
        'status' => 'approved',
        'event_date' => 'May 28, 2026',
        'description' => 'Your application for the Tree Identificaiton workshop has been Approved. Click for more details.'
    ],
    [
        'title' => 'Tree Identification Workshop',
        'time_ago' => '2 days ago',
        'status' => 'pending',
        'event_date' => 'May 25, 2026',
        'description' => 'Your application for the Tree Identification workshop is being processed, your application will be reviewed soon. Click for more details.'
    ],
    [
        'title' => 'Animal Poaching Report',
        'time_ago' => '7 weeks ago',
        'status' => 'resolved',
        'event_date' => 'Feb 21, 2026',
        'description' => ''
    ]
];

include 'header.php';
?>
<link rel="stylesheet" href="profile.css">

<div class="account-container">
    <!-- Header/Hero -->
    <div class="account-header">
        <div class="user-header-grid">
            <div class="user-img">
                <img src="" alt="user-profile">
            </div>
            <div class="user-details">
                <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <p>Joined <?php $user['joined']; ?></p>
            </div>
            <div class="action-btn">
                <i class="fa-solid fa-ellipsis-vertical"></i>
            </div>
        </div>
    </div>

    <h2>Personal Information</h2>

    <!-- Personal Information Card -->
    <div class="personal-info-container">

        <div class="info-grid">
            <div class="info-item">
                <label for="">First Name</label>
                <p><?php echo htmlspecialchars($user['first_name']); ?></p>
            </div>
            <div class="info-item">
                <label for="">Last Name</label>
                <p><?php echo htmlspecialchars($user['last_name']); ?></p>
            </div>
            <div class="info-item">
                <label for="">Date of Birth</label>
                <p><?php echo htmlspecialchars($user['dob']); ?></p>
            </div>
            <div class="info-item">
                <label for="">Email Address</label>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="info-item">
                <label for="">Phone Number</label>
                <p><?php echo htmlspecialchars($user['phone']); ?></p>
            </div>
            <div class="info-item">
                <label for="">User Role</label>
                <p><?php echo htmlspecialchars($user['role']); ?></p>
            </div>
        </div>
    </div>

    <!-- Recent Activity Card -->
    <h2>Recent Activity</h2>
    <div class="recent-act-container">
        <div class="activity-list">
            <?php foreach ($recentActivities as $activity): ?>
                <div class="activity-item" data-status="<?php echo $activity['status']; ?>">
                    <div class="activity-main">
                        <div class="activity-title">
                            <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                            <span class="time-ago"><?php echo htmlspecialchars($activity['time_ago']); ?></span>
                        </div>
                    </div>
                    <p class="activity-date"><?php echo $activity['event_date']; ?></p>
                    <?php if (!empty($activity['description'])): ?>
                        <p class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>