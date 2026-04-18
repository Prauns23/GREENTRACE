<?php
require_once '../init_session.php';
require_once '../config.php';

// Get activity ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    echo '<div class="error">No activity specified. Please go back and try again.</div>';
    exit;
}

$stmt = $conn->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$activity = $stmt->get_result()->fetch_assoc();

if (!$activity) {
    echo '<div class="error">Activity not found (ID: ' . $id . ').</div>';
    exit;
}

// Get user's latest application status for this activity
$user_status = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $statusStmt = $conn->prepare("SELECT status FROM volunteer_applications WHERE user_id = ? AND activity_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $statusStmt->bind_param("ii", $user_id, $id);
    $statusStmt->execute();
    $result = $statusStmt->get_result();
    $app = $result->fetch_assoc();
    if ($app) {
        $user_status = $app['status'];
    }
}

$is_full = ($activity['participants_count'] >= $activity['capacity']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($activity['title']); ?> - Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="../activity_details.css">
</head>

<body>
    <div class="activity-detail-container">
        <div class="activity-prev">
            <?php if (!empty($activity['image_url'])): ?>
                <img src="../<?php echo htmlspecialchars($activity['image_url']); ?>" alt="<?php echo htmlspecialchars($activity['title']); ?>">
            <?php endif; ?>
        </div>

        <div class="act-header">
            <h1><?php echo htmlspecialchars($activity['title']); ?></h1>
            <div class="badges-row">
                <?php if (!empty($activity['badge_primary'])): ?>
                    <span class="activity-badge primary"><?php echo htmlspecialchars($activity['badge_primary']); ?></span>
                <?php endif; ?>
                <?php if (!empty($activity['badge_secondary'])): ?>
                    <span class="activity-badge secondary"><?php echo htmlspecialchars($activity['badge_secondary']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <p class="description"><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>

        <div class="details-grid">
            <div class="detail-item">
                <div class="detail-icon"><i class="fa-regular fa-calendar"></i></div>
                <div class="detail-content">
                    <div class="detail-label">Date</div>
                    <div class="detail-value"><?php echo date('F j, Y', strtotime($activity['date'])); ?></div>
                </div>
            </div>
            <?php if (!empty($activity['time_start'])): ?>
                <div class="detail-item">
                    <div class="detail-icon"><i class="fa-regular fa-clock"></i></div>
                    <div class="detail-content">
                        <div class="detail-label">Time</div>
                        <div class="detail-value">
                            <?php echo date('g:i A', strtotime($activity['time_start'])); ?>
                            <?php if (!empty($activity['time_end'])) echo ' – ' . date('g:i A', strtotime($activity['time_end'])); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="detail-item">
                <div class="detail-icon"><i class="fa-solid fa-location-dot"></i></div>
                <div class="detail-content">
                    <div class="detail-label">Location</div>
                    <div class="detail-value"><?php echo htmlspecialchars($activity['location']); ?></div>
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-icon"><span class="material-symbols-rounded">group</span></div>
                <div class="detail-content">
                    <div class="detail-label">Participants</div>
                    <div class="detail-value" id="participantCount"><?php echo $activity['participants_count']; ?> / <?php echo $activity['capacity']; ?> registered</div>
                </div>
            </div>
        </div>

        <?php if (!empty($activity['meetup_point'])): ?>
            <div class="meetup-item">
                <div class="detail-icon"><i class="fa-solid fa-flag-checkered"></i></div>
                <div class="detail-content">
                    <div class="detail-label">Meet‑up point</div>
                    <div class="detail-value"><?php echo htmlspecialchars($activity['meetup_point']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bottom-button">
            <button class="close-button" onclick="parent.hideFloating()">Close</button>
            <button class="join-btn" id="actionBtn"
                data-status="<?php echo $user_status; ?>"
                <?php echo ($user_status === 'pending') ? 'disabled' : ''; ?>>
                <?php
                if ($user_status === 'pending') echo 'Pending';
                elseif ($user_status === 'approved') echo 'Leave Activity';
                else echo 'Join Activity';
                ?>
            </button>
        </div>
    </div>

    <script>
        const actionBtn = document.getElementById('actionBtn');
        const activityId = <?php echo $id; ?>;
        let userStatus = actionBtn.getAttribute('data-status');

        async function handleJoinLeave() {
            if (actionBtn.disabled) return;

            // Leave (approved → cancel)
            if (userStatus === 'approved') {
                if (!confirm('Are you sure you want to leave this activity?')) return;
                actionBtn.disabled = true;
                actionBtn.textContent = 'Leaving...';
                try {
                    const response = await fetch('../actions/update_application_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `activity_id=${activityId}&action=cancel`
                    });
                    const data = await response.json();
                    if (data.success) {
                        if (typeof parent.showToast === 'function') parent.showToast('You have left the activity');
                        parent.hideFloating();
                        parent.location.reload();
                    } else {
                        alert(data.error);
                        actionBtn.disabled = false;
                        actionBtn.textContent = 'Leave Activity';
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                    actionBtn.disabled = false;
                    actionBtn.textContent = 'Leave Activity';
                }
                return;
            }

            // Join (if not pending/approved)
            if (userStatus !== 'pending' && userStatus !== 'approved') {
                if (typeof parent.showVolunteerForm === 'function') {
                    parent.showVolunteerForm(activityId);
                } else {
                    console.error('showVolunteerForm not found in parent window');
                    alert('Application form not available. Please refresh the page and try again.');
                }
            }
        }

        actionBtn.addEventListener('click', handleJoinLeave);
    </script>
</body>

</html>