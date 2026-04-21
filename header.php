<?php require_once __DIR__ . '/init_session.php';
$basePath = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
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
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>index.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>volunteer.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>information.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : ''; ?>activities.css">




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

    <!-- Navigation Bar -->
    <div class="navigation">
        <nav class="navbar" aria-label="Main navigation">
            <img src="<?php echo $basePath; ?>components/icons/menu.svg" alt="" class="menu" id="menuIcon">
            <ul class="nav-links">
                <li><a href="<?php echo $basePath; ?>index.php#about-section">About</a></li>
                <li><a href="<?php echo $basePath; ?>index.php#feature-section">Features</a></li>
                <li><a href="<?php echo $basePath; ?>index.php#volunteer-section">Volunteer</a></li>
            </ul>
            <img src="<?php echo $basePath; ?>components/icons/person.svg" alt="" class="profile"
                onclick="<?php echo isset($_SESSION['first_name']) ? 'showLogout()' : 'showLogin()'; ?>">
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
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'pages/ar-camera.php' || basename($_SERVER['PHP_SELF']) == 'ar-camera.php' ? 'active' : ''; ?>"><a href=""><i class="fa-solid fa-camera"></i><span class="label">AR Camera</span></a></li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'information.php' ? 'active' : ''; ?>"><a href="<?php echo $basePath; ?>information.php"> <i class="fa-solid fa-tree"></i><span class="label">Tree Species</span></a></li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'activities.php' ? 'active' : ''; ?>">
                    <a href="<?php echo $basePath; ?>activities.php"><i class="fa-solid fa-hand-holding-heart"></i><span class="label">Volunteer</span></a>
                </li>
            </ul>
        </nav>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
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
        <div class="sidebar-profile">
            <div class="profile-avatar">
                <img src="<?php echo $basePath; ?>components/icons/person.svg" alt="Profile">
            </div>
            <div class="profile-info">
                <h3><?php echo isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : 'Login Account'; ?></h3>
                <span>View profile</span>
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