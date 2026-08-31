<?php
require_once '../init_session.php';
$singleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$idsString = isset($_GET['ids']) ? $_GET['ids'] : '';
$isBulk = ($idsString !== '');
$ids = $isBulk ? explode(',', $idsString) : ($singleId ? [$singleId] : []);
$count = count($ids);
$message = $isBulk ? "Archive $count selected activities? They will be hidden from volunteers." : "Archive this activity? It will be hidden from volunteers.";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="../security.js"></script>
    <title>Confirm Archive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="confirm_modal.css">
</head>

<body>
    <div class="confirm-container">
        <div class="confirm-content">
            <!-- <i class="fas fa-archive" style="font-size: 64px; color: #9e9e9e; margin: 20px 0;"></i> -->
            <h1><?= $isBulk ? 'Archive Activities' : 'Archive Activity' ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <div class="button-group">
                <button class="confirm-btn archive-btn" id="confirmAction">Archive</button>
                <button class="cancel-btn" onclick="parent.hideFloating()">Cancel</button>
            </div>
        </div>
    </div>
    <script>
        const ids = <?= json_encode($ids); ?>;
        const isBulk = <?= $isBulk ? 'true' : 'false'; ?>;
        document.getElementById('confirmAction').addEventListener('click', function() {
            const url = isBulk ? '../actions/archive_activities.php' : '../actions/archive_activity.php';
            const body = isBulk ? 'ids=' + JSON.stringify(ids) : 'id=' + ids[0];
            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        parent.location.href = '../admin/activities_manage.php?toast=' + encodeURIComponent(data.message) + '&type=success';
                    } else {
                        if (parent.showToast) parent.showToast(data.error || 'Archive failed', 4000, 'error');
                        parent.hideFloating();
                    }
                })
                .catch(err => {
                    if (parent.showToast) parent.showToast('Network error', 4000, 'error');
                    parent.hideFloating();
                });
        });
    </script>
</body>

</html>
