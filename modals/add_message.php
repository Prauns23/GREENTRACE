<?php
require_once '../init_session.php';
require_once '../config.php';

// Only logged-in users can access
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$current_user_id = $_SESSION['user_id'];

// Fetch current user's barangay_id
$barangayStmt = $conn->prepare("SELECT barangay_id FROM users_tbl WHERE id = ?");
$barangayStmt->bind_param("i", $current_user_id);
$barangayStmt->execute();
$current_barangay_id = $barangayStmt->get_result()->fetch_assoc()['barangay_id'] ?? null;
$barangayStmt->close();

// Fetch users that already have a direct conversation with the current user
$existingDmStmt = $conn->prepare("
    SELECT DISTINCT cm2.user_id
    FROM chat_conversation_members cm1
    JOIN chat_conversation_members cm2 ON cm1.conversation_id = cm2.conversation_id
    JOIN chat_conversations c ON c.id = cm1.conversation_id
    WHERE c.type = 'direct'
      AND c.archived = 0
      AND cm1.user_id = ?
      AND cm2.user_id != ?
      AND cm1.left_at IS NULL
      AND cm2.left_at IS NULL
");
$existingDmStmt->bind_param("ii", $current_user_id, $current_user_id);
$existingDmStmt->execute();
$existingDmUserIds = array_map('intval', array_column($existingDmStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'user_id'));
$existingDmStmt->close();

// Fetch all users except current, sorted by same barangay first
$query = "
    SELECT u.id, u.fname, u.lname, u.email, u.role, b.name as barangay_name,
           (u.barangay_id = ?) AS same_barangay
    FROM users_tbl u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id != ? AND u.archived = 0
    ORDER BY same_barangay DESC, u.fname ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $current_barangay_id, $current_user_id);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Contacts</title>
    <link rel="stylesheet" href="add_message.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="modal-content">
        <!-- Header -->
        <div class="modal-header">
            <h2>Add Contacts</h2>
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
            <?php 
            $sameBarangayShown = false;
            foreach ($users as $user): 
                $dotClass = ($user['role'] === 'admin' || $user['role'] === 'super_admin') ? 'dot-admin' : 'dot-user';
                $displayName = htmlspecialchars($user['fname'] . ' ' . $user['lname']);
                $barangay = htmlspecialchars($user['barangay_name'] ?? 'No barangay');
                $isSame = (bool)$user['same_barangay'];
                $alreadyConnected = in_array((int)$user['id'], $existingDmUserIds, true);
                $inactiveClass = $alreadyConnected ? 'user-item-disabled' : '';
                $disabledAttr = $alreadyConnected ? 'disabled' : '';
                // Barangay divider
                if ($isSame && !$sameBarangayShown) {
                    $sameBarangayShown = true;
                    echo '<div class="barangay-divider">People you may know</div>';
                } elseif (!$isSame && $sameBarangayShown && !isset($differentShown)) {
                    $differentShown = true;
                    echo '<div class="barangay-divider">Other users</div>';
                }
            ?>
                <div class="user-item <?= $inactiveClass ?>" data-user-id="<?= $user['id'] ?>" data-same="<?= $isSame ? '1' : '0' ?>" data-already-connected="<?= $alreadyConnected ? '1' : '0' ?>">
                    <div class="user-info">
                        <div class="user-dot <?= $dotClass ?>"></div>
                        <div class="user-info2">
                            <span class="user-name"><?= $displayName ?></span>
                            <span class="user-barangay"><?= $barangay ?></span>
                            <?php if ($alreadyConnected): ?>
                                <!-- <span class="user-status">Already added</span> -->
                            <?php endif; ?>
                        </div>
                    </div>
                    <input type="checkbox" class="user-checkbox" data-user-id="<?= $user['id'] ?>" <?= $disabledAttr ?>>
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