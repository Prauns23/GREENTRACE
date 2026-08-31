<?php
require_once '../init_session.php';
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$current_user_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
if (!$conversation_id) {
    die('Invalid conversation');
}

// Check permission
$roleCheck = $conn->prepare("SELECT member_role FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$roleCheck->bind_param("ii", $conversation_id, $current_user_id);
$roleCheck->execute();
$role = $roleCheck->get_result()->fetch_assoc();
$roleCheck->close();

if (!$role || !in_array($role['member_role'], ['owner', 'admin'])) {
    die('You do not have permission to add members.');
}

// Fetch all active users except current, with membership status
$query = "
    SELECT 
        u.id, 
        u.fname, 
        u.lname, 
        u.email, 
        b.name as barangay_name,
        CASE WHEN cm.user_id IS NOT NULL AND cm.left_at IS NULL THEN 1 ELSE 0 END AS is_active_member
    FROM users_tbl u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    LEFT JOIN chat_conversation_members cm ON cm.conversation_id = ? AND cm.user_id = u.id AND cm.left_at IS NULL
    WHERE u.id != ? AND u.archived = 0
    ORDER BY is_active_member ASC, u.fname ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $conversation_id, $current_user_id);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="../security.js"></script>
    <title>Add Volunteers</title>
    <link rel="stylesheet" href="add_message.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .user-item-disabled {
            opacity: 0.6;
            background: #f8fafc;
            cursor: not-allowed;
        }
        .user-item-disabled .user-checkbox {
            cursor: not-allowed;
        }
        .already-member-label {
            font-size: 0.75rem;
            color: #a0aec0;
            font-weight: 500;
            margin-left: 8px;
        }
    </style>
</head>
<body>
<div class="modal-content">
    <input type="hidden" id="conversationIdInput" value="<?=  $conversation_id ?>">
    <div class="modal-header">
        <h2>Add Volunteers</h2>
    </div>
    <div class="search-container">
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="userSearch" placeholder="Search users...">
        </div>
    </div>
    <div class="users-container" id="usersContainer">
        <?php if (empty($users)): ?>
            <div style="text-align: center; padding: 40px; color: #a0aec0;">
                <i class="fas fa-users" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                <p>No users available.</p>
            </div>
        <?php else: ?>
            <?php foreach ($users as $user):
                $isMember = (bool)$user['is_active_member'];
                $inactiveClass = $isMember ? 'user-item-disabled' : '';
                $disabledAttr = $isMember ? 'disabled' : '';
                $label = $isMember ? '<span class="already-member-label"></span>' : '';
            ?>
                <div class="user-item <?= $inactiveClass ?>" data-user-id="<?= $user['id'] ?>" data-is-member="<?= $isMember ? '1' : '0' ?>">
                    <div class="user-info">
                        <div class="user-dot dot-user"></div>
                        <div class="user-info2">
                            <span class="user-name"><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></span>
                            <span class="user-barangay"><?= htmlspecialchars($user['barangay_name'] ?? 'No barangay') ?></span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <?= $label ?>
                        <input type="checkbox" class="user-checkbox" data-user-id="<?= $user['id'] ?>" <?= $disabledAttr ?>>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="modal-actions">
        <button class="btn-cancel" onclick="parent.hideFloating()">Cancel</button>
        <button class="btn-add" id="addChannelMembersBtn">Add Selected</button>
    </div>
</div>
<script src="add_channel_members.js"></script>
</body>
</html>
