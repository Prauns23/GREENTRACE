<?php
require_once '../init_session.php';
require_once '../config.php';

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

// Check if a direct conversation already exists between these two users
$checkStmt = $conn->prepare("
    SELECT c.id 
    FROM chat_conversations c
    JOIN chat_conversation_members cm1 ON c.id = cm1.conversation_id
    JOIN chat_conversation_members cm2 ON c.id = cm2.conversation_id
    WHERE c.type = 'direct' 
      AND cm1.user_id = ? 
      AND cm2.user_id = ?
      AND cm1.left_at IS NULL 
      AND cm2.left_at IS NULL
");
$checkStmt->bind_param("ii", $current_user_id, $recipient_id);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();

if ($existing) {
    // Conversation exists
    echo json_encode(['success' => true, 'conversation_id' => $existing['id'], 'message' => 'Conversation already exists']);
    exit;
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
    echo json_encode(['success' => true, 'conversation_id' => $conv_id, 'message' => 'Direct message conversation created']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}