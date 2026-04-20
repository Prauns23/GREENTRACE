<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

// Fetch upcoming activities

$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT * FROM activities WHERE date >= ? ORDER BY date ASC");
$stmt->bind_param("s", $today);
$stmt->execute();
$activities_result = $stmt->get_result();
$activities = $activities_result->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>

<div class="activities-page">
    <div class="activities-header">
        <h1>Get Involved — Pick an Activity and Show Up.</h1>
        <span>Browse upcoming planting events, workshops, and restoration projects. Join an activity to make an impact</span>
    </div>

    <div class="activities-grid">
        <?php if (count($activities) === 0): ?>
            <div class="no-results" style="grid-column: 1/-1; text-align: center;">
                <img src="pages/no-results.svg" alt="No activities found" style="max-width: 300px;">
                <h3>No upcoming activities</h3>
                <p>Check back later for new volunteer opportunities!</p>
            </div>
        <?php else: ?>
            <?php foreach ($activities as $activity): ?>
                <div class="activity-card" onclick="showActivityDetails(<?php echo $activity['id']; ?>)">
                    <div class="activity-prev">
                        <?php if (!empty($activity['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($activity['image_url']); ?>" alt="<?php echo htmlspecialchars($activity['title']); ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php endif; ?>
                    </div>
                    <div class="card-content">
                        <div class="badges-row">
                            <?php if (!empty($activity['badge_primary'])): ?>
                                <span class="activity-badge primary"><?php echo htmlspecialchars($activity['badge_primary']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($activity['badge_secondary'])): ?>
                                <span class="activity-badge secondary"><?php echo htmlspecialchars($activity['badge_secondary']); ?></span>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($activity['title']); ?></h3>
                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                        <div class="meta-row">
                            <div class="bottom-content">
                                <i class="fa-regular fa-calendar"></i>
                                <span class="date"><?php echo date('F j, Y', strtotime($activity['date'])); ?></span>
                            </div>
                            <div class="bottom-content">
                                <span class="material-symbols-rounded">group</span>
                                <span class="slots"><?php echo $activity['participants_count']; ?> Participants</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    // Get toast message from URL query parameters
    const urlParams = new URLSearchParams(window.location.search);
    const toastMsg = urlParams.get('toast');
    const toastType = urlParams.get('type') === 'error' ? 'error' : 'success';

    if (toastMsg) {
        // Clean the URL (remove toast parameters) without refreshing
        const cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);

        setTimeout(() => {
            if (typeof showToast === 'function') {
                showToast(decodeURIComponent(toastMsg), 5000, toastType);
            } else {
                alert(decodeURIComponent(toastMsg));
            }
        }, 500);
    }
</script>

<?php include 'footer.php'; ?>