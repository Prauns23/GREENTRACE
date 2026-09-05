<?php
require_once '../init_session.php';
require_once '../config.php';
require_once __DIR__ . '/../helpers/notifications_helper.php';
require_once __DIR__ . '/../helpers/realtime.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$conversationId = (int) ($_POST['conversation_id'] ?? 0);

if ($conversationId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid channel']);
    exit;
}

$channelStmt = $conn->prepare("
    SELECT c.name, c.archived, cm.member_role
    FROM chat_conversations c
    JOIN chat_conversation_members cm
      ON cm.conversation_id = c.id
     AND cm.user_id = ?
     AND cm.left_at IS NULL
    WHERE c.id = ?
      AND c.type = 'channel'
    LIMIT 1
");
$channelStmt->bind_param('ii', $userId, $conversationId);
$channelStmt->execute();
$channel = $channelStmt->get_result()->fetch_assoc();
$channelStmt->close();

if (!$channel) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Channel not found']);
    exit;
}

if ($channel['member_role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only the channel owner can delete this channel']);
    exit;
}

if ((int) $channel['archived'] === 1) {
    echo json_encode(['success' => false, 'error' => 'This channel has already been deleted']);
    exit;
}

$memberStmt = $conn->prepare("
    SELECT user_id
    FROM chat_conversation_members
    WHERE conversation_id = ?
      AND user_id != ?
      AND left_at IS NULL
");
$memberStmt->bind_param('ii', $conversationId, $userId);
$memberStmt->execute();
$members = $memberStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$memberStmt->close();

$deleteStmt = $conn->prepare("
    UPDATE chat_conversations
    SET archived = 1, updated_at = NOW()
    WHERE id = ?
      AND type = 'channel'
      AND archived = 0
");
$deleteStmt->bind_param('i', $conversationId);
$deleteStmt->execute();
$deleted = $deleteStmt->affected_rows === 1;
$deleteStmt->close();

if (!$deleted) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'The channel could not be deleted']);
    exit;
}

$safeChannelName = htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8');
foreach ($members as $member) {
    createNotification(
        (int) $member['user_id'],
        'message',
        'Channel Deleted',
        "The channel <strong>#{$safeChannelName}</strong> was deleted by its owner.",
        'message.php'
    );
}

publishConversationRealtimeEvent($conversationId, 'channel_deleted', [
    'deleted' => true,
    'deleted_by' => $userId,
]);

echo json_encode([
    'success' => true,
    'deleted' => true,
    'message' => 'Channel deleted for all members',
]);

$conn->close();
?>
