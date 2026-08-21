<?php
require_once '../init_session.php';
require_once '../config.php';
require_once '../notifications_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$recipient_ids = $input['recipient_ids'] ?? [];

if (empty($recipient_ids)) {
    echo json_encode(['error' => 'No recipients selected']);
    exit;
}

// Remove duplicates and self, ensure ints
$recipient_ids = array_unique(array_map('intval', $recipient_ids));
$recipient_ids = array_filter($recipient_ids, function($id) use ($current_user_id) {
    return $id !== $current_user_id;
});

if (empty($recipient_ids)) {
    echo json_encode(['error' => 'You cannot add yourself']);
    exit;
}

// Verify all recipients exist and are not archived
$placeholders = implode(',', array_fill(0, count($recipient_ids), '?'));
$checkUsers = $conn->prepare("SELECT id FROM users_tbl WHERE id IN ($placeholders) AND archived = 0");
$checkUsers->bind_param(str_repeat('i', count($recipient_ids)), ...$recipient_ids);
$checkUsers->execute();
$existingUsers = $checkUsers->get_result()->fetch_all(MYSQLI_ASSOC);
$checkUsers->close();
$existingIds = array_column($existingUsers, 'id');
$missing = array_diff($recipient_ids, $existingIds);
if (!empty($missing)) {
    echo json_encode(['error' => 'Some recipients not found or archived']);
    exit;
}

// Helper function to create or restore a DM with one recipient
function createOrRestoreDM($conn, $current_user_id, $recipient_id) {
    // Check existing conversation (including left members)
    $checkStmt = $conn->prepare("
        SELECT c.id, cm1.left_at AS user1_left, cm2.left_at AS user2_left
        FROM chat_conversations c
        JOIN chat_conversation_members cm1 ON c.id = cm1.conversation_id
        JOIN chat_conversation_members cm2 ON c.id = cm2.conversation_id
                WHERE c.type = 'direct'
                    AND cm1.user_id = ?
          AND cm2.user_id = ?
    ");
    $checkStmt->bind_param("ii", $current_user_id, $recipient_id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        $conv_id = $existing['id'];
        $user1_left = $existing['user1_left'];
        $user2_left = $existing['user2_left'];

        // Check archive status for current user
        $archCheck = $conn->prepare("SELECT is_archived FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ?");
        $archCheck->bind_param("ii", $conv_id, $current_user_id);
        $archCheck->execute();
        $archResult = $archCheck->get_result()->fetch_assoc();
        $archCheck->close();

        if ($archResult && $archResult['is_archived'] == 1) {
            $unarch = $conn->prepare("UPDATE chat_conversation_members SET is_archived = 0 WHERE conversation_id = ? AND left_at IS NULL");
            $unarch->bind_param("i", $conv_id);
            $unarch->execute();
            $unarch->close();
            return ['success' => true, 'conversation_id' => $conv_id, 'message' => 'Conversation restored'];
        }

        if ($user1_left === null && $user2_left === null) {
            return ['success' => true, 'conversation_id' => $conv_id, 'message' => 'Conversation already exists'];
        }

        // Re‑join if one left
        $conn->begin_transaction();
        try {
            if ($user1_left !== null) {
                $rejoin = $conn->prepare("UPDATE chat_conversation_members SET left_at = NULL WHERE conversation_id = ? AND user_id = ?");
                $rejoin->bind_param("ii", $conv_id, $current_user_id);
                $rejoin->execute();
            }
            if ($user2_left !== null) {
                $rejoin = $conn->prepare("UPDATE chat_conversation_members SET left_at = NULL WHERE conversation_id = ? AND user_id = ?");
                $rejoin->bind_param("ii", $conv_id, $recipient_id);
                $rejoin->execute();
            }
            $conn->commit();
            return ['success' => true, 'conversation_id' => $conv_id, 'message' => 'Re‑joined conversation'];
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'error' => 'Failed to re‑join: ' . $e->getMessage()];
        }
    }

    // No existing – create new
    $conn->begin_transaction();
    try {
        $insertConv = $conn->prepare("INSERT INTO chat_conversations (type) VALUES ('direct')");
        if (!$insertConv->execute()) {
            throw new Exception('Failed to create conversation');
        }
        $conv_id = $conn->insert_id;

        $addMember = $conn->prepare("INSERT INTO chat_conversation_members (conversation_id, user_id, member_role) VALUES (?, ?, 'member')");
        $addMember->bind_param("ii", $conv_id, $current_user_id);
        if (!$addMember->execute()) {
            throw new Exception('Failed to add current user');
        }
        $addMember->bind_param("ii", $conv_id, $recipient_id);
        if (!$addMember->execute()) {
            throw new Exception('Failed to add recipient');
        }
        $addMember->close();

        $conn->commit();
        return ['success' => true, 'conversation_id' => $conv_id, 'message' => 'Conversation created'];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Process each recipient individually
$results = [];
$successCount = 0;
$createdConversations = [];

foreach ($recipient_ids as $recipient_id) {
    $result = createOrRestoreDM($conn, $current_user_id, $recipient_id);
    if ($result['success']) {
        $successCount++;
        $createdConversations[] = $result['conversation_id'];
        $results[$recipient_id] = $result['message'];
    } else {
        $results[$recipient_id] = $result['error'];
    }
}

// Send notifications for each successful addition (only once per recipient)
$currentUser = $conn->query("SELECT CONCAT(fname, ' ', lname) as name FROM users_tbl WHERE id = $current_user_id")->fetch_assoc();
$senderName = $currentUser['name'] ?? 'Someone';
$notifTitle = "New Message";
$notifMessage = "$senderName started a conversation with you. Say hello!";
$link = "message.php";

foreach ($recipient_ids as $uid) {
    // Only notify if the conversation was created or restored
    // For simplicity, we'll notify for all successful attempts.
    if (isset($results[$uid]) && strpos($results[$uid], 'already') === false) {
        // Only if it's not "already exists" – but we can still notify if restored.
        if (strpos($results[$uid], 'already') === false) {
            createNotification($uid, 'message', $notifTitle, $notifMessage, $link);
        }
    }
}

echo json_encode([
    'success' => $successCount > 0,
    'message' => "Added $successCount contact(s).",
    'details' => $results
]);