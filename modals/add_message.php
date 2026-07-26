<?php
require_once '../init_session.php';
require_once '../config.php';

// Only logged-in users can access
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

// Fetch users (excluding the current user)
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, fname, lname, email, role, barangay_id FROM users_tbl WHERE id != ? AND archived = 0 ORDER BY fname ASC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch barangay names for each user (we'll do it in a loop or join, but we'll join in the query)
// Better: join barangays directly
$stmt = $conn->prepare("
    SELECT u.id, u.fname, u.lname, u.email, u.role, b.name as barangay_name
    FROM users_tbl u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id != ? AND u.archived = 0
    ORDER BY u.fname ASC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Message</title>
    <link rel="stylesheet" href="add_message.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="modal-content">
        <!-- Header -->
        <div class="modal-header">
            <h2>New Message</h2>
        </div>

        <!-- Search bar -->
        <div class="search-container">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="userSearch" placeholder="Search users...">
            </div>
        </div>

        <!-- Users list -->
        <div class="users-container" id="usersContainer">
            <?php foreach ($users as $user): ?>
                <?php
                $dotClass = ($user['role'] === 'admin' || $user['role'] === 'super_admin') ? 'dot-admin' : 'dot-user';
                $displayName = htmlspecialchars($user['fname'] . ' ' . $user['lname']);
                $barangay = htmlspecialchars($user['barangay_name'] ?? 'No barangay');
                ?>
                <div class="user-item" data-user-id="<?= $user['id'] ?>">
                    <div class="user-info">
                        <div class="user-dot <?= $dotClass ?>"></div>
                        <div class="user-info2">
                            <span class="user-name"><?= $displayName ?></span>
                            <span class="user-barangay"><?= $barangay ?></span>
                        </div>

                    </div>
                    <input type="checkbox" class="user-checkbox" data-user-id="<?= $user['id'] ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="modal-actions">
            <button class="btn-cancel" onclick="parent.hideFloating()">Cancel</button>
            <button class="btn-add" id="addRecipientsBtn">Add</button>
        </div>
    </div>

    <script src="add_message.js"></script>
</body>

</html>