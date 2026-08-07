<?php
require_once __DIR__ . '/init_session.php';
require_once __DIR__ . '/config.php';
$basePath = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/pages/') !== false) ? '../' : '';

$unreadCount = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $unreadCount = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    <link rel="stylesheet" href="pagination.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>index.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>volunteer.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>information.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>activities.css">


    <script>
        window.basePath = '<?php echo $basePath; ?>';
    </script>

    <!-- Three.js and OrbitControls -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>

    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Trace</title>
</head>

<body>

    <!-- floating overlays -->
    <div class="overlay" id="overlay"></div>

    <!-- Sign up Popup -->
    <div class="floating-container" id="floatingSignUpContainer">
        <iframe src="<?php echo $basePath; ?>pages/sign-up.php" class="floating-iframe" id="signupFrame"></iframe>
    </div>
    <!-- Login Popup -->
    <div class="floating-container" id="floatingSignInContainer">
        <iframe src="<?php echo $basePath; ?>pages/sign-in.php" class="floating-iframe" id="signInFrame"></iframe>
    </div>
    <!-- Report popup -->
    <div class="floating-container" id="floatingReportContainer">
        <iframe src="<?php echo $basePath; ?>pages/report.php" class="floating-iframe" id="reportFrame"></iframe>
    </div>
    <!-- Logout -->
    <div class="floating-container" id="floatingLogoutContainer">
        <iframe src="<?php echo $basePath; ?>pages/logout.php" class="floating-iframe" id="logoutFrame"></iframe>
    </div>
    <!-- Tree Species Popup -->
    <div class="floating-container" id="floatingSpeciesContainer">
        <iframe src="" class="floating-iframe" id="speciesFrame"></iframe>
    </div>
    <!-- Activity Details Modal -->
    <div class="floating-container" id="floatingActivityContainer">
        <iframe src="<?php echo $basePath; ?>pages/activity_details.php" class="floating-iframe" id="activityFrame"></iframe>
    </div>
    <!-- Add Marker Modal -->
    <div class="floating-container" id="floatingAddMarkerContainer">
        <iframe src="<?php echo $basePath; ?>modals/add_marker_modal.php" class="floating-iframe" id="addMarkerFrame"></iframe>
    </div>
    <!-- Edit Forest Marker -->
    <div class="floating-container" id="floatingEditForestContainer">
        <div class="floating-overlay" onclick="hideFloating()"></div>
        <div class="floating-content">
            <iframe id="editForestFrame" class="floating-iframe" src="" frameborder="0"></iframe>
        </div>
    </div>
    <!-- Report Detail Modal -->
    <div class="floating-container" id="floatingReportDetailsContainer">
        <div class="floating-overlay" onclick="hideFloating()"></div>
        <div class="floating-content">
            <iframe id="reportDetailsFrame" class="floating-iframe" src="" frameborder="0"></iframe>
        </div>
    </div>
    <!-- Forest Detail Modal -->
    <div class="floating-container" id="floatingForestDetailsContainer">
        <div class="floating-overlay" onclick="hideFloating()"></div>
        <div class="floating-content">
            <iframe id="forestDetailsFrame" class="floating-iframe" src="" frameborder="0"></iframe>
        </div>
    </div>
    <!-- Volunteer Modal -->
    <div class="floating-container" id="floatingVolunteerContainer">
        <div class="floating-overlay" onclick="hideFloating()"></div>
        <div class="floating-content">
            <iframe src="" frameborder="0" id="volunteerFrame" class="floating-iframe" frameborder="0"></iframe>
        </div>
    </div>

    <!-- Confirm Archive -->
    <div class="floating-container" id="floatingArchiveContainer">
        <iframe src="<?php echo $basePath; ?>modals/confirm_archive.php" class="floating-iframe" id="confirmArchiveFrame"></iframe>
    </div>

    <!-- Confirm Restore -->
    <div class="floating-container" id="floatingRestoreContainer">
        <iframe src="<?php echo $basePath; ?>modals/confirm_restore.php" class="floating-iframe" id="confirmRestoreFrame"></iframe>
    </div>

    <!-- Confirm Delete -->
    <div class="floating-container" id="floatingDeleteContainer">
        <iframe src="<?php echo $basePath; ?>modals/confirm_delete.php" class="floating-iframe" id="confirmDeleteFrame"></iframe>
    </div>

    <!-- Edit Activity Modal -->
    <div class="floating-container" id="floatingEditActivityContainer">
        <iframe src="<?php echo $basePath; ?>modals/edit_activity.php" class="floating-iframe" id="editActivityFrame"></iframe>
    </div>

    <!-- Add Activity Modal -->

    <div class="floating-container" id="floatingAddActivityContainer">
        <iframe src="<?php echo $basePath; ?>modals/add_activity.php" class="floating-iframe" id="addActivityFrame"></iframe>
    </div>

    <!-- Edit Profile Modal -->
    <div class="floating-container" id="floatingEditProfileContainer">
        <iframe src="<?php echo $basePath; ?>modals/edit_profile.php" class="floating-iframe" id="editProfileFrame"></iframe>
    </div>

    <!-- Add Message Modal -->
    <div class="floating-container" id="floatingAddMessageContainer">
        <iframe src="<?php echo $basePath; ?>modals/add_message.php" class="floating-iframe" id="addMessageFrame"></iframe>
    </div>

    <!-- Create Channel Modal -->
    <div class="floating-container" id="floatingCreateChannelContainer">
        <iframe src="<?php echo $basePath; ?>modals/create_channel.php" frameborder="0" class="floating-iframe" id="createChannelFrame"></iframe>
    </div>

    <!-- Navigation Bar -->
    <div class="navigation">
        <nav class="navbar" aria-label="Main navigation">
            <div class="nav-left">
                <img src="<?php echo $basePath; ?>components/icons/menu.svg" alt="" class="menu" id="menuIcon">
            </div>
            <div class="nav-middle">
                <ul class="nav-links">
                    <li><a href="<?php echo $basePath; ?>index.php#about-section">About</a></li>
                    <li><a href="<?php echo $basePath; ?>index.php#feature-section">Features</a></li>
                    <li><a href="<?php echo $basePath; ?>index.php#volunteer-section">Volunteer</a></li>
                </ul>
            </div>
            <div class="nav-right">
                <?php if (isset($_SESSION['first_name'])): ?>
                    <div class="chat-icon-container">
                        <img src="<?php echo $basePath; ?>components/icons/chat-icon.svg" alt="" class="chat-icon" onclick="window.location.href='<?php echo $basePath; ?>message.php'">
                        <span class="chat-dot" id="chatDot" style="display: none;"></span>
                    </div>
                    <div class="notification-bell-container">
                        <span class="material-symbols-rounded bell-icon" id="fa-bell" onclick="window.location.href='<?php echo $basePath; ?>notifications.php'">
                            notifications
                            <span class="notification-dot" id="notificationDot" style="display: none;"></span>
                        </span>
                    </div>
                <?php endif; ?>
                <img src="<?php echo $basePath; ?>components/icons/person.svg" alt="" class="profile"
                    onclick="<?php echo isset($_SESSION['first_name']) ? 'window.location.href=\'' . $basePath . 'profile.php\'' : 'showLogin()'; ?>">
            </div>
        </nav>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?php echo $basePath; ?>components/icons/menu.svg" alt="Menu" class="sidebar-menu-icon" id="sidebarToggle">
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $basePath; ?>index.php"><i class="fa-solid fa-house"></i><span class="label">Home</span></a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ar-camera.php' || basename($_SERVER['PHP_SELF']) == 'ar-camera.php' ? 'active' : ''; ?>"><a href="<?php echo $basePath; ?>ar-camera.php"><i class="fa-solid fa-camera"></i><span class="label">AR Camera</span></a></li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'information.php' ? 'active' : ''; ?>"><a href="<?php echo $basePath; ?>information.php"> <i class="fa-solid fa-tree"></i><span class="label">Tree Species</span></a></li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'activities.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $basePath; ?>activities.php"><i class="fa-solid fa-hand-holding-heart"></i><span class="label">Volunteer</span></a>
                </li>
            </ul>
        </nav>
        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
            <nav class="sidebar-admin">
                <ul>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin/application_activity.php' || basename($_SERVER['PHP_SELF']) == 'application_activity.php' ? 'active' : ''; ?>">
                        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'application_activity.php' : 'admin/application_activity.php'; ?>">
                            <i class="fa-solid fa-address-book"></i>
                            <span class="label">Volunteer Applicants</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'forestmap.php' ? 'active' : ''; ?>">
                        <a href="<?php echo $basePath; ?>forestmap.php">
                            <i class="fa-solid fa-map"></i><span class="label">Forest Map</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : ''; ?>">
                        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'user_management.php' : 'admin/user_management.php'; ?>">
                            <i class="fa-solid fa-user-group"></i><span class="label">Users</span>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'activities_manage.php' ? 'active' : ''; ?>">
                        <a href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'activities_manage.php' : 'admin/activities_manage.php'; ?>">
                            <i class="fa-solid fa-calendar-days"></i><span>Manage Activities</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif ?>
        <div class="sidebar-report">
            <button class="report-activity"
                onclick="<?php echo isset($_SESSION['first_name']) ? 'showReport()' : 'showSignUp()'; ?>">
                <span class="material-symbols-rounded">release_alert</span>
                <span class="label">Report an activity</span>
            </button>
        </div>
        <div class="sidebar-profile" onclick="<?php echo isset($_SESSION['first_name']) ? 'showLogout()' : 'showLogin()'; ?>">
            <div class="profile-avatar">
                <img src="<?php echo $basePath; ?>components/icons/person.svg" alt="Profile">
            </div>
            <div class="profile-info">
                <h3><?php echo isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : 'Login Account'; ?></h3>
                <span><?php echo isset($_SESSION['first_name']) ? 'Logout Account' : 'Sign in'; ?></span>
            </div>
        </div>
    </div>

    <!-- Toast notification -->
    <div id="toast" class="toast hidden">
        <span id="toast-message"></span>
        <button class="toast-close" onclick="hideToast()">x</button>
    </div>

    <!-- Page content starts here -->
    <div class="page-content">