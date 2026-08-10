<?php
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = (int)($_GET['conversation_id'] ?? 0);

if (!$conversation_id) {
    echo json_encode(['error' => 'Invalid conversation']);
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

// Fetch all members
$query = "
    SELECT 
        u.id as user_id,
        CONCAT(u.fname, ' ', u.lname) as name,
        u.email,
        u.role,
        cm.member_role,
        cm.joined_at
    FROM chat_conversation_members cm
    JOIN users_tbl u ON cm.user_id = u.id
    WHERE cm.conversation_id = ? 
      AND cm.left_at IS NULL
      AND u.archived = 0
    ORDER BY 
        FIELD(cm.member_role, 'owner', 'admin', 'member'),
        u.fname ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'success' => true,
    'members' => $members
]);