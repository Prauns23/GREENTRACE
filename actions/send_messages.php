<?php
error_reporting(0);
require_once '../config.php';
require_once '../init_session.php';

// Set PHP timezone (if not already set in config)
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$reply_to_id = isset($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

if (!$conversation_id || empty($content)) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Verify user is a member
$check = $conn->prepare("SELECT id FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$check->close();

// Check if the user is muted by an admin in this conversation
$muteCheck = $conn->prepare("
    SELECT is_muted_by_admin
    FROM chat_conversation_members
    WHERE conversation_id = ? AND user_id = ? AND left_at is NULL
");

$muteCheck->bind_param("ii", $conversation_id, $user_id);
$muteCheck->execute();
$muteResult = $muteCheck->get_result()->fetch_assoc();
$muteCheck->close();

if ($muteResult && $muteResult['is_muted_by_admin'] == 1) {
    echo json_encode([
        'error' => 'You are muted in this channel.'
    ]);
    exit;
}

//  RATE LIMIT 
$limit = 10;
$window = 60;

$stmt = $conn->prepare("
    SELECT COUNT(*) as cnt 
    FROM chat_messages 
    WHERE conversation_id = ? 
      AND sender_id = ? 
      AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
");
$stmt->bind_param("iii", $conversation_id, $user_id, $window);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$count = $result['cnt'] ?? 0;
$stmt->close();

if ($count >= $limit) {
    $oldestStmt = $conn->prepare("
        SELECT created_at 
        FROM chat_messages 
        WHERE conversation_id = ? 
          AND sender_id = ? 
        ORDER BY created_at ASC 
        LIMIT 1 OFFSET ? 
    ");
    $offset = $limit - 1;
    $oldestStmt->bind_param("iii", $conversation_id, $user_id, $offset);
    $oldestStmt->execute();
    $oldestRow = $oldestStmt->get_result()->fetch_assoc();
    $oldestStmt->close();

    if ($oldestRow) {
        $oldestTime = strtotime($oldestRow['created_at']);
        $retryAfter = max(1, $oldestTime + $window - time());
    } else {
        $retryAfter = $window;
    }

    echo json_encode([
        'error' => 'You are sending messages too quickly. Please wait.',
        'retry_after' => $retryAfter,
        'code' => 'rate_limited'
    ]);
    exit;
}
//  END RATE LIMIT 

// Insert message
$stmt = $conn->prepare("INSERT INTO chat_messages (conversation_id, sender_id, content, message_type, reply_to_id, created_at) VALUES (?, ?, ?, 'text', ?, NOW())");
$stmt->bind_param("iisi", $conversation_id, $user_id, $content, $reply_to_id);
if (!$stmt->execute()) {
    echo json_encode(['error' => 'Failed to send message']);
    exit;
}
$message_id = $conn->insert_id;
$stmt->close();

// A new DM message makes an archived conversation visible again to both users.
$unarchive = $conn->prepare("UPDATE chat_conversation_members cm JOIN chat_conversations c ON c.id = cm.conversation_id SET cm.is_archived = 0 WHERE cm.conversation_id = ? AND c.type = 'direct' AND cm.left_at IS NULL");
$unarchive->bind_param("i", $conversation_id);
$unarchive->execute();
$unarchive->close();

// Get the inserted timestamp from MySQL (so we're consistent)
$getTime = $conn->prepare("SELECT created_at FROM chat_messages WHERE id = ?");
$getTime->bind_param("i", $message_id);
$getTime->execute();
$timeResult = $getTime->get_result()->fetch_assoc();
$createdAt = $timeResult['created_at'];
$getTime->close();

// Update conversation updated_at
$update = $conn->prepare("UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?");
$update->bind_param("i", $conversation_id);
$update->execute();
$update->close();

// Get sender info
$userStmt = $conn->prepare("SELECT fname, lname FROM users_tbl WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$sender_name = $user['fname'] . ' ' . $user['lname'];

echo json_encode([
    'success' => true,
    'message' => [
        'id' => $message_id,
        'sender_id' => $user_id,
        'sender_name' => $sender_name,
        'content' => $content,
        'created_at' => $createdAt, // Use the timestamp from MySQL
        'is_self' => true
    ]
]);