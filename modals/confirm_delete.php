<?php
require_once '../init_session.php';
$singleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$idsString = isset($_GET['ids']) ? $_GET['ids'] : '';
$isBulk = ($idsString !== '');
$ids = $isBulk ? explode(',', $idsString) : ($singleId ? [$singleId] : []);
$count = count($ids);
$message = $isBulk ? "This also removes all related applications and files. This action cannot be undone." : "This will also remove all related applications and photos. This action cannot be undone.";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="../security.js"></script>
    <title>Confirm Delete</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="confirm_modal.css">
</head>

<body>
    <div class="confirm-container">
        <div class="confirm-content">
            <h1><?= $isBulk ? 'Delete Activities Permanently?' : 'Delete Activity Permanently?' ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <div class="button-group">
                <button class="confirm-btn delete-btn" id="confirmAction">Delete</button>
                <button class="cancel-btn" onclick="parent.hideFloating()">Cancel</button>
            </div>
        </div>
    </div>
    <script>
        const ids = <?= json_encode($ids); ?>;
        const isBulk = <?= $isBulk ? 'true' : 'false'; ?>;
        
        document.getElementById('confirmAction').addEventListener('click', function() {
            // Get CSRF token from parent page (since this modal is inside an iframe)
            let token = '';
            try {
                token = parent.document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            } catch(e) {
                // Fallback: try to get from own document (if meta tag is present)
                token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            }
            if (!token) {
                alert('CSRF token not found. Please refresh the page.');
                return;
            }

            const url = isBulk ? '../actions/delete_activities.php' : '../actions/delete_activity.php';
            const body = isBulk ? 'ids=' + JSON.stringify(ids) : 'id=' + ids[0];
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': token
                },
                body: body
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    parent.location.href = '../admin/activities_manage.php?toast=' + encodeURIComponent(data.message) + '&type=success';
                } else {
                    if (parent.showToast) parent.showToast(data.error || 'Delete failed', 4000, 'error');
                    parent.hideFloating();
                }
            })
            .catch(err => {
                console.error(err);
                if (parent.showToast) parent.showToast('An error occurred', 4000, 'error');
                parent.hideFloating();
            });
        });
    </script>
</body>

</html>
