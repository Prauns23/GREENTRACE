<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,300,0,0&icon_names=release_alert" />
    <link rel="stylesheet" href="index.css">

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
                <li><a href="#about-section">About</a></li>
                <li><a href="#feature-section">Features</a></li>
                <li><a href="#volunteer-section">Volunteer</a></li>
            </ul>
            <img src="components/icons/person.svg" alt="" class="profile"
                onclick="<?php echo isset($_SESSION['first_name']) ? 'showLogout()' : 'showLogin()'; ?>">
        </nav>
    </div>

    <!-- SideBar Nav - Collapsible with menu icon -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="components/icons/menu.svg" alt="Menu" class="sidebar-menu-icon" id="sidebarToggle">
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="active">
                    <a href="#">
                        <i class="fa-solid fa-house"></i>
                        <span class="label">Home</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fa-solid fa-map"></i>
                        <span class="label">Forest Map</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fa-solid fa-camera"></i>
                        <span class="label">AR Camera</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fa-solid fa-tree"></i>
                        <span class="label">Tree Species</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                        <span class="label">Volunteer</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-report">
            <button class="report-activity" onclick="showReport()">
                <span class="material-symbols-rounded">release_alert</span>
                <span class="label">Report an activity</span>
            </button>
        </div>

        <!-- Profile Section at BOTTOM -->
        <div class="sidebar-profile">
            <div class="profile-avatar" id="userProfile">
                <img src="components/icons/person.svg" alt="Profile">
            </div>
            <div class="profile-info">
                <h3 id="userName">
                    <?php echo isset($_SESSION['first_name']) ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : 'Franz Harvey Bautista'; ?>
                </h3>
                <span>View profile</span>
            </div>
        </div>
    </div>

    <!-- Landing Page -->
    <div class="FirstPage">
        <h2 id="header01">Empowering Communities</h2>
        <h2 id="header02">To Restore Nature</h2>
        <p>Our platform connects people, businesses, and governments to greening programs that reduce carbon footprints,
            restore biodiversity, and create healthier cities. </p>

        <button class="start-planting">
            Start Planting
            <img src="components/icons/arrow-forward-white.svg" alt="">
        </button>
    </div>

    <!-- 2nd Page -->
    <div class="SecondPage" id="about-section">
        <h3 class="about-title">ABOUT US</h3>

        <div class="top">
            <div class="feature">
                <h2>01.</h2>
                <p>Promotes environmental awareness through forest information</p>
            </div>
            <div class="feature">
                <h2>02.</h2>
                <p>Improves data management and support better environmental decision-making</p>
            </div>
            <div class="feature">
                <h2>03.</h2>
                <p>Increases community engagement by allowing users to volunteer and partnerships with local barangays
                </p>
            </div>
        </div>

        <div class="middle">
            <h3>
                <span class="green">GREENTRACE</span> is an Online Reforestation Management System designed to support
                our environment, using web and mobile platforms for <em>effective management</em>, <em>monitoring</em>,
                and <em>participation</em>
                in reforestation activities.
            </h3>
        </div>

        <div class="bottom">
            <p>TECHNOLOGY AND <span class="green">NATURE</span> COLLABORATION</p>
            <button onclick="showLogin()" class="joinBtn">
                Join us
                <img src="components/icons/double-arrow.svg" alt="">
            </button>
        </div>
    </div>

    <!-- Third Page -->
    <div class="ThirdPage" id="feature-section">
        <h3 class="features-title">FEATURES</h3>
        <!-- Left features -->
        <div class="left-features">
            <h3>Technology Meets <br><span class="green02">Nature restoration</span></h3>
            <div class="feat-row" id="featRow">
                <div class="feat-count" data-feat="1">
                    <h2>01</h2>
                    <h2>//</h2>
                </div>
                <div class="feat-count" data-feat="2">
                    <h2>02</h2>
                    <h2>//</h2>
                </div>
                <div class="feat-count" data-feat="3">
                    <h2>03</h2>
                    <h2>//</h2>
                </div>
                <div class="feat-count" data-feat="4">
                    <h2>04</h2>
                </div>
            </div>
            <p>Our comprehensive platform combines modern technology with environmental management to support
                sustainable reforestation practices.</p>
        </div>

        <!-- RIGHT FEATURES - with horizontal cards -->
        <div class="right-features">
            <div class="cards-container" id="cardsContainer">
                <!-- Feature Card 01 -->
                <div class="feature-card" data-card="1">
                    <img src="components/treeroad.jpg">
                    <div class="feature-badge">
                        <span>FEATURE 01</span>
                    </div>
                    <div class="card-content">
                        <h3>2D Mapping and GPS</h3>
                        <p>Displays GPS-tagged planting sites and reported areas on an interactive map. Allows filtering
                            by species, project, or planting date for monitoring and planning.</p>
                        <button class="explore">
                            Explore
                            <img src="components/icons/double-arrow.svg" alt="">
                        </button>
                    </div>
                </div>

                <!-- Feature Card 02 -->
                <div class="feature-card" data-card="2">
                    <img src="components/ar-tree.jpg" alt="Forest path">
                    <div class="feature-badge">
                        <span>FEATURE 02</span>
                    </div>
                    <div class="card-content">
                        <h3>Tree Space Estimation</h3>
                        <p>Estimates the mature height and canopy spread of a tree and visually projects the space it
                            will occupy.</p>
                        <button class="explore">
                            Explore
                            <img src="components/icons/double-arrow.svg" alt="">
                        </button>
                    </div>
                </div>

                <!-- Feature Card 03 -->
                <div class="feature-card" data-card="3">
                    <img src="components/down-tree.webp" alt="Misty forest">
                    <div class="feature-badge">
                        <span>FEATURE 03</span>
                    </div>
                    <div class="card-content">
                        <h3>Community Reports</h3>
                        <p>Enables administrators to review environmental reports (e.g., illegal logging, forest damage)
                            submitted by users, including GPS location and photo evidence.</p>
                        <button class="explore">
                            Explore
                            <img src="components/icons/double-arrow.svg" alt="">
                        </button>
                    </div>
                </div>

                <!-- Feature Card 04 -->
                <div class="feature-card" data-card="4">
                    <img src="components/books-nature.jpg" alt="Tree planting">
                    <div class="feature-badge">
                        <span>FEATURE 04</span>
                    </div>
                    <div class="card-content">
                        <h3>Educational Information Pages</h3>
                        <p>Provides informative content about forest conservation, tree growth cycles, native vs
                            introduced species, and sustainable reforestation practices.</p>
                        <button class="explore">
                            Explore
                            <img src="components/icons/double-arrow.svg" alt="">
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Swiper nav -->
        <div class="ThirdPage-nav">
            <button class="nav-btn" id="scrollLeft">
                <img src="components/icons/arrow-back.svg" alt="">
            </button>
            <button class="nav-btn" id="scrollRight">
                <img src="components/icons/arrow-forward-white (2).svg" alt="">
            </button>
        </div>
    </div>

    <!-- FourthPage -->
    <div class="FourthPage" id="volunteer-section">
        <div class="fourth-background">
            <div class="fourth-content">
                <div class="fourth-content-wrapper">
                    <p class="join-tag">JOIN US BY SIGNING UP</p>
                    <div class="center-content">
                        <h2 class="fourth-title">Be Part of the Greening Activities</h2>
                        <p class="fourth-description">Join thousands of volunteers and organizations working together to
                            restore the Philippines' forests and create a sustainable future for generations to come.
                        </p>
                        <div class="fourth-buttons">
                            <button class="btn-primary">
                                Start Volunteering
                                <img src="components/icons/arrow-forward-white (2).svg" alt="">
                            </button>
                            <button class="btn-secondary">
                                Learn More
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-content">
            <!-- Left Section -->
            <div class="footer-left">
                <div class="footer-brand">
                    <h3 class="footer-title">GreenTrace</h3>
                </div>
                <p class="footer-description">Empowering communities to restore nature through technology, transparency,
                    and collective action.</p>

                <div class="contact-info">
                    <div class="contact-item">
                        <img src="components/icons/location-white.svg" alt="Location">
                        <span>Morong, Bataan, Philippines</span>
                    </div>
                    <div class="contact-item">
                        <img src="components/icons/mail.svg" alt="Email">
                        <span>greentraceph@gmail.com</span>
                    </div>
                    <div class="contact-item">
                        <img src="components/icons/phone.svg" alt="Phone">
                        <span>+6391 763 67803</span>
                    </div>
                </div>
            </div>

            <!-- Right Section -->
            <div class="footer-right">
                <div class="footer-column">
                    <h4>PROGRAMS</h4>
                    <ul>
                        <li><a href="#">Reforestation</a></li>
                        <li><a href="#">Information</a></li>
                        <li><a href="#">Carbon Offsetting</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>PLATFORM</h4>
                    <ul>
                        <li><a href="#">AR Planning</a></li>
                        <li><a href="#">2D Mapping</a></li>
                        <li><a href="#">Volunteering Platform</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>ABOUT</h4>
                    <ul>
                        <li><a href="#">News</a></li>
                        <li><a href="#">Partners</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p>&copy; 2026 GreenTrace. All rights reserved.</p>
        </div>
    </div>

    <script src="app.js"></script>
    <script src="nav.js"></script>
</body>

</html>