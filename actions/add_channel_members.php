<?php
require_once '../init_session.php';
require_once '../config.php';
require_once '../notifications_helper.php';
require_once __DIR__ . '/../helpers/chat_system.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$conversation_id = (int)($input['conversation_id'] ?? 0);
$user_ids = $input['user_ids'] ?? [];

if (!$conversation_id || empty($user_ids)) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Verify current user is owner or admin of this channel, or is super_admin
$roleCheck = $conn->prepare("SELECT member_role FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$roleCheck->bind_param("ii", $conversation_id, $current_user_id);
$roleCheck->execute();
$role = $roleCheck->get_result()->fetch_assoc();
$roleCheck->close();

$isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
if (!$isSuperAdmin && (!$role || !in_array($role['member_role'], ['owner', 'admin']))) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$added_count = 0;
$errors = [];
$addedUserIds = [];

foreach ($user_ids as $user_id) {
    $user_id = (int)$user_id;
    if ($user_id == $current_user_id) continue;

    // Check if user is already an active member (left_at IS NULL)
    $check = $conn->prepare("SELECT id, left_at FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ?");
    $check->bind_param("ii", $conversation_id, $user_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        if ($existing['left_at'] !== null) {
            // Re‑join: set left_at = NULL and update added_by
            $update = $conn->prepare("UPDATE chat_conversation_members SET left_at = NULL, added_by = ? WHERE conversation_id = ? AND user_id = ?");
            $update->bind_param("iii", $current_user_id, $conversation_id, $user_id);
            if ($update->execute()) {
                $added_count++;
                $addedUserIds[] = $user_id;
            } else {
                $errors[] = "Failed to re-add user $user_id";
            }
            $update->close();
        }
        // else already active – skip silently
    } else {
        // New member: insert with role 'member' and added_by
        $insert = $conn->prepare("INSERT INTO chat_conversation_members (conversation_id, user_id, member_role, added_by) VALUES (?, ?, 'member', ?)");
        $insert->bind_param("iii", $conversation_id, $user_id, $current_user_id);
        if ($insert->execute()) {
            $added_count++;
            $addedUserIds[] = $user_id;
        } else {
            $errors[] = "Failed to add user $user_id: " . $conn->error;
        }
        $insert->close();
    }
}

// Send notifications and system messages
if ($added_count > 0) {
    // Fetch adder's name
    $adderName = $conn->query("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = $current_user_id")->fetch_assoc()['name'] ?? 'Admin';
    $channelName = $conn->query("SELECT name FROM chat_conversations WHERE id = $conversation_id")->fetch_assoc()['name'] ?? 'channel';

    // System messages in chat
    foreach ($addedUserIds as $uid) {
        $userName = $conn->query("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = $uid")->fetch_assoc()['name'] ?? 'User';
        $content = "$userName was added by $adderName.";
        insertSystemMessage($conn, $conversation_id, $content);
    }

    // Notifications to each added user
    foreach ($addedUserIds as $uid) {
        createNotification($uid, 'system', "Added to Channel", "You have been added to <strong>{$channelName}</strong> by {$adderName}.", 'message.php');
    }

    // Notify all other members about the addition
    $notifTitle = "New Member Added";
    $notifMessage = "$adderName added " . count($addedUserIds) . " member(s) to <strong>{$channelName}</strong>.";
    notifyAllMembers($conn, $conversation_id, $notifTitle, $notifMessage, $current_user_id);
}

echo json_encode([
    'success' => true,
    'message' => "Added $added_count member(s).",
    'errors' => $errors
]);