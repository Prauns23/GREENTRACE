<?php
require_once 'init_session.php';
require_once 'config.php';

// Check if user is logged in 
if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

// Admin only 
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

include 'header.php';

// Fetch forest areas
$forest_query = "SELECT id, name, location_name, latitude, longitude, date_established, status, description FROM forest_areas ORDER BY created_at DESC";

$forest_result = $conn->query($forest_query);
$forest_areas = [];
while ($row = $forest_result->fetch_assoc()) {
    $forest_areas[] = $row;
}

// Fetch reports (with user info for non-anonymous)
$report_query = "SELECT r.*, u.fname, u.lname, u.phone_no, r.archived FROM reports r LEFT JOIN users_tbl u ON r.user_id = u.id ORDER BY r.created_at DESC";

$reports_result = $conn->query($report_query);

$reports = [];
while ($row = $reports_result->fetch_assoc()) {
    // Format reporter name
    if ($row['anonymous']) {
        $row['reporter_name'] = 'Anonymous';
        $row['report_phone'] = null;
    } else {
        $row['reporter_name'] = $row['fname'] . ' ' . $row['lname']; // Added space between names
        $row['reporter_phone'] = $row['phone_no'] ?? null;
    }
    $reports[] = $row;
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="forestmap.css">

<div class="forestmap-page">
    <!-- Header -->
    <div class="forestmap-header">
        <h1>Forest Map</h1>
        <p>View reforestation areas, reported issues, and forest monitoring data</p>
    </div>

    <!-- Main - Two Grid -->
    <div class="map-layout">
        <!-- Left panel -->
        <div class="info-panel">

            <!-- Search and Filter -->
            <div class="search-filter-section">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search Location">
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="reports">Reports</button>
                    <button class="filter-btn" data-filter="forests">Reforestation Areas</button>
                    <button class="filter-btn" data-filter="archived">Archived</button>
                </div>
            </div>
            <div class="info-header">
                <h3>Forest Overview</h3>
                <p>Forest Monitoring Data</p>
            </div>
            <!-- Stat Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <div class="stat-number" id="totalMarkers">0</div>
                    <div class="stat-label">Total Markers</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-tree"></i>
                    <div class="stat-number" id="activeForests">0</div>
                    <div class="stat-label">Active Forests</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="stat-number" id="activeReports">0</div>
                    <div class="stat-label">Active Reports</div>
                </div>

            </div>

            <!-- Legend -->
            <!-- <div class="legend-section">
                <div class="legend-title">Map Legend</div>
                <div class="legend-item">
                    <div class="legend-color green"><i class="fas fa-tree"></i></div>
                    <span class="legend-text">Reforestation Area</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color red"><i class="fas fa-exclamation-triangle"></i></div>
                    <span class="legend-text">Active Report (Pending/Reviewed)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color gray"><i class="fas fa-check-circle"></i></div>
                    <span class="legend-text">Resolved/Dismissed Report</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color gray"><i class="fas fa-archive"></i></div>
                    <span class="legend-text">Archived Forest Area</span>
                </div>
            </div> -->

            <!-- Recent Activity -->
            <div class="recent-title">Recent Activity</div>
            <div class="recent-activity">
                <div class="activity-list" id="activityList">
                    <!-- Dynamic content from JavaScript -->
                </div>
            </div>
        </div>

        <!-- Right Panel - Map -->
        <div class="map-panel">
            <div id="forestMap"></div>

            <!-- Map Controls -->
            <div class="map-controls">
                <button class="map-control-btn add-marker-btn" onclick="addNewMarker()">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-content">
        <h3>Confirm Action</h3>
        <p id="confirmMessage">Are you sure you want to perform this action?</p>
        <div class="modal-buttons">
            <button class="modal-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-confirm" id="confirmActionBtn">Confirm</button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Pass PHP data to JavaScript (use window object for global access)
    window.forestAreasData = <?php echo json_encode($forest_areas); ?>;
    window.reportsData = <?php echo json_encode($reports); ?>;
</script>
<script src="forestmap.js"></script>

<?php include 'footer.php'; ?>