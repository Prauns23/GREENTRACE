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

// For now, only handle single recipient (we'll later support groups)
$recipient_id = (int)$recipient_ids[0];

if ($recipient_id === $current_user_id) {
    echo json_encode(['error' => 'You cannot send a message to yourself']);
    exit;
}

// Check if the recipient exists and is not archived
$checkUser = $conn->prepare("SELECT id, fname, lname FROM users_tbl WHERE id = ? AND archived = 0");
$checkUser->bind_param("i", $recipient_id);
$checkUser->execute();
$recipient = $checkUser->get_result()->fetch_assoc();
if (!$recipient) {
    echo json_encode(['error' => 'Recipient not found or archived']);
    exit;
}

// Check if a direct conversation already exists between these two users (including left members)
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

if ($existing) {
    $conv_id = $existing['id'];
    $user1_left = $existing['user1_left'];
    $user2_left = $existing['user2_left'];

    // If either user left, re-add them (set left_at = NULL)
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
        echo json_encode(['success' => true, 'conversation_id' => $conv_id, 'message' => 'Re‑joined conversation']);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => 'Failed to re‑join: ' . $e->getMessage()]);
        exit;
    }
}

// Create new direct conversation
$conn->begin_transaction();
try {
    // Insert conversation (type = direct, name/slug/visibility NULL)
    $insertConv = $conn->prepare("INSERT INTO chat_conversations (type) VALUES ('direct')");
    if (!$insertConv->execute()) {
        throw new Exception('Failed to create conversation');
    }
    $conv_id = $conn->insert_id;

    // Add current user
    $addMember = $conn->prepare("INSERT INTO chat_conversation_members (conversation_id, user_id, member_role) VALUES (?, ?, 'member')");
    $addMember->bind_param("ii", $conv_id, $current_user_id);
    if (!$addMember->execute()) {
        throw new Exception('Failed to add current user');
    }

    // Add recipient
    $addMember->bind_param("ii", $conv_id, $recipient_id);
    if (!$addMember->execute()) {
        throw new Exception('Failed to add recipient');
    }

    $conn->commit();

    // Send notification to the recipient
    $currentUser = $conn->query("SELECT fname, lname FROM users_tbl WHERE id = $current_user_id")->fetch_assoc();
    $senderName = $currentUser['fname'] . ' ' . $currentUser['lname'];
    $notifTitle = "New Message";
    $notifMessage = "$senderName started a conversation with you. Say hello!";
    $link = "message.php";
    createNotification($recipient_id, 'message', $notifTitle, $notifMessage, $link);

    echo json_encode(['success' => true, 'conversation_id' => $conv_id, 'message' => 'Conversation created']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}