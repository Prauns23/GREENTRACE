<?php require_once 'init_session.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,300,0,0&icon_names=release_alert,volunteer_activism" />
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="volunteer.css">
    <link rel="stylesheet" href="forest_map.css">

    <!-- if you have page-specific CSS like volunteer.css, include it here -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Trace</title>
</head>

<body>

    <!-- floating overlays -->
    <div class="overlay" id="overlay"></div>

    <div class="floating-container" id="floatingSignUpContainer">
        <iframe src="pages/sign-up.php" class="floating-iframe" id="signupFrame"></iframe>
    </div>
    <div class="floating-container" id="floatingSignInContainer">
        <iframe src="pages/sign-in.php" class="floating-iframe" id="signInFrame"></iframe>
    </div>
    <div class="floating-container" id="floatingReportContainer">
        <iframe src="pages/report.php" class="floating-iframe" id="reportFrame"></iframe>
    </div>
    <div class="floating-container" id="floatingLogoutContainer">
        <iframe src="pages/logout.php" class="floating-iframe" id="logoutFrame"></iframe>
    </div>

    <!-- Navigation Bar -->
    <div class="navigation">
        <nav class="navbar" aria-label="Main navigation">
            <img src="components/icons/menu.svg" alt="" class="menu" id="menuIcon">
            <ul class="nav-links">
                <li><a href="index.php#about-section">About</a></li>
                <li><a href="index.php#feature-section">Features</a></li>
                <li><a href="index.php#volunteer-section">Volunteer</a></li>
            </ul>
            <img src="components/icons/person.svg" alt="" class="profile"
                onclick="<?php echo isset($_SESSION['first_name']) ? 'showLogout()' : 'showLogin()'; ?>">
        </nav>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="components/icons/menu.svg" alt="Menu" class="sidebar-menu-icon" id="sidebarToggle">
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <a href="index.php"><i class="fa-solid fa-house"></i><span class="label">Home</span></a>
                </li>
                <li><a href="#"><i class="fa-solid fa-map"></i><span class="label">Forest Map</span></a></li>
                <li><a href="#"><i class="fa-solid fa-camera"></i><span class="label">AR Camera</span></a></li>
                <li><a href="#"><i class="fa-solid fa-tree"></i><span class="label">Tree Species</span></a></li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'volunteer.php' ? 'active' : ''; ?>">
                    <a href="volunteer.php"><i class="fa-solid fa-hand-holding-heart"></i><span class="label">Volunteer</span></a>
                </li>
            </ul>
        </nav>
        <div class="sidebar-report">
            <button class="report-activity"
                onclick="<?php echo isset($_SESSION['first_name']) ? 'showReport()' : 'showSignUp()'; ?>">
                <span class="material-symbols-rounded">release_alert</span>
                <span class="label">Report an activity</span>
            </button>
        </div>
        <div class="sidebar-profile">
            <div class="profile-avatar">
                <img src="components/icons/person.svg" alt="Profile">
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