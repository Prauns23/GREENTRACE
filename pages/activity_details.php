<?php
require_once '../init_session.php';
require_once '../config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$activity = $result->fetch_assoc();

if (!$activity) {
    echo '<div class="error">Activity not found.</div>';
    exit;
}

$is_full = ($activity['participants_count'] >= $activity['capacity']);

$already_joined = false;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $check_join = $conn->prepare("SELECT id FROM volunteer_participations WHERE user_id = ? AND activity_id = ?");
    $check_join->bind_param("ii", $user_id, $id);
    $check_join->execute();
    $already_joined = ($check_join->get_result()->num_rows > 0);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($activity['title']); ?> - Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="stylesheet" href="../activity_details.css">
</head>

<body>
    <div class="activity-detail-container">
        <!-- Image -->
        <div class="activity-prev">
            <?php if (!empty($activity['image_url'])): ?>
                <img src="../<?php echo htmlspecialchars($activity['image_url']); ?>"
                    alt="<?php echo htmlspecialchars($activity['title']); ?>">
            <?php endif; ?>
        </div>

        <!-- Badges -->
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

        <!-- Description -->
        <p class="description"><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>

        <!-- Details grid -->
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
                            <?php
                            echo date('g:i A', strtotime($activity['time_start']));
                            if (!empty($activity['time_end'])) {
                                echo ' – ' . date('g:i A', strtotime($activity['time_end']));
                            }
                            ?>
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

        <!-- Meet-up point (full width) -->
        <?php if (!empty($activity['meetup_point'])): ?>
            <div class="meetup-item">
                <div class="detail-icon"><i class="fa-solid fa-flag-checkered"></i></div>
                <div class="detail-content">
                    <div class="detail-label">Meet‑up point</div>
                    <div class="detail-value"><?php echo htmlspecialchars($activity['meetup_point']); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Join button -->
        <div class="bottom-button">
            <button class="close-button" onclick="parent.hideFloating()">Close</button>
            <button class="join-btn" id="actionBtn" data-joined="<?php echo $already_joined ? 'true' : 'false'; ?>" <?php echo $is_full && !$already_joined ? 'disabled style="background:#9e9e9e; cursor:not-allowed;"' : ''; ?>>
                <?php
                if ($already_joined) echo 'Leave Activity';
                elseif ($is_full) echo 'Full';
                else echo 'Join Activity';
                ?>
            </button>
        </div>

    </div>

    <script>
        document.getElementById('actionBtn').addEventListener('click', function() {
            const btn = this;
            const activityId = <?php echo $activity['id']; ?>;
            const isJoined = btn.getAttribute('data-joined') === 'true';
            const url = isJoined ? '../unjoin_activity.php' : '../join_activity.php';

            // Disable button during request
            btn.disabled = true;
            btn.textContent = isJoined ? 'Leaving...' : 'Joining...';

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'activity_id=' + activityId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close the modal first
                        if (typeof parent.hideFloating === 'function') {
                            parent.hideFloating();
                        }
                        // Then reload the parent page
                        parent.location.reload();
                    } else {
                        // Show error message (toast or alert)
                        if (typeof parent.showToast === 'function') {
                            parent.showToast(data.error);
                        } else {
                            alert(data.error);
                        }
                        btn.disabled = false;
                        btn.textContent = isJoined ? 'Leave Activity' : 'Join Activity';
                    }
                });
        });
    </script>

</body>

</html>