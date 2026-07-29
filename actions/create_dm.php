<?php
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$other_user_id = (int)($_POST['user_id'] ?? 0);

if (!$other_user_id || $other_user_id == $current_user_id) {
    echo json_encode(['error' => 'Invalid user.']);
    exit;
}

// Check if a direct conversation already exists between these two users
$checkStmt = $conn->prepare("
    SELECT c.id 
    FROM chat_conversations c
    JOIN chat_conversation_members cm1 ON c.id = cm1.conversation_id AND cm1.user_id = ?
    JOIN chat_conversation_members cm2 ON c.id = cm2.conversation_id AND cm2.user_id = ?
    WHERE c.type = 'direct'
");
$checkStmt->bind_param("ii", $current_user_id, $other_user_id);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    echo json_encode([
        'success' => true,
        'conversation_id' => $existing['id']
    ]);
    exit;
}

// Create new direct conversation
$conn->begin_transaction();
try {
    // Insert conversation
    $stmt = $conn->prepare("INSERT INTO chat_conversations (type) VALUES ('direct')");
    if (!$stmt->execute()) {
        throw new Exception('Failed to create conversation: ' . $stmt->error);
    }
    $conversation_id = $conn->insert_id;

    // Add both users as members
    $memberStmt = $conn->prepare("INSERT INTO chat_conversation_members (conversation_id, user_id, member_role) VALUES (?, ?, 'member'), (?, ?, 'member')");
    $memberStmt->bind_param("iiii", $conversation_id, $current_user_id, $conversation_id, $other_user_id);
    if (!$memberStmt->execute()) {
        throw new Exception('Failed to add members: ' . $memberStmt->error);
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversation_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}