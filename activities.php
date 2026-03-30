<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

// Check if volunteer profile exists
$user_id = $_SESSION['user_id'];
$check = $conn->prepare("SELECT id FROM volunteer_profiles WHERE user_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$result = $check->get_result();
if ($result->num_rows === 0) {
    header('Location: volunteer.php');
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
        <h1>Get Involved — Pick an Activity and Show Up</h1>
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
    // Toast for volunteer success (same as before)
    <?php if (isset($_SESSION['volunteer_success'])): ?>
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showToast === 'function') {
                showToast("<?php echo addslashes($_SESSION['volunteer_success']); ?>");
            }
        });
    <?php unset($_SESSION['volunteer_success']);
    endif; ?>
</script>

<?php include 'footer.php'; ?>