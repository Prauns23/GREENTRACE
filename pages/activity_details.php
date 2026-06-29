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
$isFull = ($activity['participants_count'] >= $activity['capacity']);
$isPast = (strtotime($activity['date']) < strtotime(date('Y-m-d')));
$application_id = null; 

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $statusStmt = $conn->prepare("SELECT id, status FROM volunteer_applications WHERE user_id = ? AND activity_id = ? ORDER BY submitted_at DESC LIMIT 1");
    $statusStmt->bind_param("ii", $user_id, $id);
    $statusStmt->execute();
    $result = $statusStmt->get_result();
    $app = $result->fetch_assoc();
    if ($app) {
        $user_status = $app['status'];
        $application_id = $app['id']; 
    }
}
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
                    <div class="detail-value"><?php echo $activity['participants_count']; ?> / <?php echo $activity['capacity']; ?> registered</div>
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
            <button class="join-btn" id="actionBtn" <?php if ($isPast || $isFull) echo 'disabled'; ?>>
                <?php
                if ($isPast) {
                    echo 'Activity Ended';
                } elseif ($isFull && $user_status !== 'approved') {
                    echo 'Full';
                } elseif ($user_status === 'pending') {
                    echo 'Cancel Application';
                } elseif ($user_status === 'approved') {
                    echo 'Leave Activity';
                } else {
                    echo 'Join Activity';
                }
                ?>
            </button>
        </div>
    </div>

    <!-- <script>
        const actionBtn = document.getElementById('actionBtn');
        const activityId = <?php echo $id; ?>;
        const userStatus = <?php echo json_encode($user_status); ?>;
        const isPast = <?php echo json_encode($isPast); ?>;
        const isFull = <?php echo json_encode($isFull); ?>;

        function getCSRFToken() {
            try {
                return parent.document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            } catch (e) {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            }
        }

        async function handleAction(action) {
            const token = getCSRFToken();
            if (!token) {
                alert('CSRF token missing. Please refresh.');
                return;
            }

            const url = '../actions/update_application_status.php';
            const body = `application_id=${activityId}&action=${action}&csrf_token=${encodeURIComponent(token)}`;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body
                });
                const data = await response.json();
                if (data.success) {
                    parent.location.href = '../activities.php?toast=' + encodeURIComponent(data.message || 'Action completed') + '&type=success';
                } else {
                    alert(data.error || 'Action failed');
                }
            } catch (e) {
                alert('An error occurred. Please try again.');
            }
        }

        // Join (open volunteer form)
        function handleJoin() {
            if (typeof parent.showVolunteerForm === 'function') {
                parent.showVolunteerForm(activityId);
            } else {
                alert('Application form not available. Please refresh.');
            }
        }

        // Event listener for the main button
        actionBtn.addEventListener('click', function() {
            if (this.disabled) return;

            // If button says "Leave Activity" → cancel (approved)
            if (this.textContent.trim() === 'Leave Activity') {
                if (confirm('Are you sure you want to leave this activity?')) {
                    handleAction('cancel');
                }
                return;
            }

            // If button says "Join Activity"
            if (this.textContent.trim() === 'Join Activity') {
                handleJoin();
                return;
            }

            // Other cases (should not happen)
        });

        // Separate "Cancel Application" button for pending status (added dynamically)
        function addCancelButton() {
            const bottomDiv = document.querySelector('.bottom-button');
            if (!bottomDiv) return;

            // Remove existing cancel button if any
            const existingCancel = document.getElementById('cancelBtn');
            if (existingCancel) existingCancel.remove();

            const cancelBtn = document.createElement('button');
            cancelBtn.id = 'cancelBtn';
            cancelBtn.className = 'cancel-btn';
            cancelBtn.textContent = 'Cancel Application';
            cancelBtn.onclick = function() {
                if (confirm('Cancel your pending application? This cannot be undone.')) {
                    handleAction('cancel');
                }
            };
            bottomDiv.insertBefore(cancelBtn, bottomDiv.lastElementChild);
        }

        // On page load, decide button state
        document.addEventListener('DOMContentLoaded', function() {
            if (userStatus === 'pending') {
                actionBtn.textContent = 'Pending';
                actionBtn.disabled = true;
                addCancelButton();
            } else if (userStatus === 'approved') {
                actionBtn.textContent = 'Leave Activity';
                actionBtn.disabled = false;
            } else if (isPast) {
                actionBtn.textContent = 'Activity Ended';
                actionBtn.disabled = true;
            } else if (isFull) {
                actionBtn.textContent = 'Full';
                actionBtn.disabled = true;
            } else {
                actionBtn.textContent = 'Join Activity';
                actionBtn.disabled = false;
            }
        });
    </script> -->
    <script>
    const actionBtn = document.getElementById('actionBtn');
    const activityId = <?php echo $id; ?>;
    const applicationId = <?php echo json_encode($application_id); ?>;
    const userStatus = <?php echo json_encode($user_status); ?>;
    const isPast = <?php echo json_encode($isPast); ?>;
    const isFull = <?php echo json_encode($isFull); ?>;

    function getCSRFToken() {
        try {
            return parent.document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        } catch(e) {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
    }

    async function handleAction(action) {
        if (!applicationId) {
            alert('No application found. Please refresh.');
            return;
        }
        const token = getCSRFToken();
        if (!token) {
            alert('CSRF token missing. Please refresh.');
            return;
        }

        const url = '../actions/update_application_status.php';
        const body = `application_id=${applicationId}&action=${action}&csrf_token=${encodeURIComponent(token)}`;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body
            });
            const data = await response.json();
            if (data.success) {
                parent.location.href = '../activities.php?toast=' + encodeURIComponent(data.message || 'Action completed') + '&type=success';
            } else {
                alert(data.error || 'Action failed');
            }
        } catch (e) {
            alert('An error occurred. Please try again.');
        }
    }

    function handleJoin() {
        if (typeof parent.showVolunteerForm === 'function') {
            parent.showVolunteerForm(activityId);
        } else {
            alert('Application form not available. Please refresh.');
        }
    }

    actionBtn.addEventListener('click', function() {
        if (this.disabled) return;
        const text = this.textContent.trim();
        if (text === 'Leave Activity' || text === 'Cancel Application') {
            const confirmMessage = text === 'Leave Activity'
                ? 'Are you sure you want to leave this activity?'
                : 'Cancel your pending application? This cannot be undone.';
            if (confirm(confirmMessage)) {
                handleAction('cancel');
            }
            return;
        }
        if (text === 'Join Activity') {
            handleJoin();
            return;
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        if (userStatus === 'pending') {
            actionBtn.textContent = 'Cancel Application';
            actionBtn.disabled = false;
        } else if (userStatus === 'approved') {
            actionBtn.textContent = 'Leave Activity';
            actionBtn.disabled = false;
        } else if (isPast) {
            actionBtn.textContent = 'Activity Ended';
            actionBtn.disabled = true;
        } else if (isFull) {
            actionBtn.textContent = 'Full';
            actionBtn.disabled = true;
        } else {
            actionBtn.textContent = 'Join Activity';
            actionBtn.disabled = false;
        }
    });
</script>

</body>

</html>