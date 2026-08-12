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
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit;
}

// Verify user is a member of this conversation
$check = $conn->prepare("SELECT id FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL");
$check->bind_param("ii", $conversation_id, $user_id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$check->close();

// Fetch all members with their details
$query = "
    SELECT 
        cm.user_id,
        cm.member_role,
        cm.added_by,
        CONCAT(u.fname, ' ', u.lname) as full_name,
        u.email,
        u.role as user_role,
        b.name as barangay_name,
        u.last_active_at,
        added_by_user.fname as added_by_fname,
        added_by_user.lname as added_by_lname
    FROM chat_conversation_members cm
    JOIN users_tbl u ON cm.user_id = u.id
    LEFT JOIN barangays b ON u.barangay_id = b.id
    LEFT JOIN users_tbl added_by_user ON cm.added_by = added_by_user.id
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

// Get channel creator info
$creatorQuery = $conn->prepare("
    SELECT CONCAT(u.fname, ' ', u.lname) as creator_name
    FROM chat_conversations c
    JOIN users_tbl u ON c.created_by = u.id
    WHERE c.id = ?
");
$creatorQuery->bind_param("i", $conversation_id);
$creatorQuery->execute();
$creator = $creatorQuery->get_result()->fetch_assoc();
$creatorName = $creator['creator_name'] ?? '';
$creatorQuery->close();


// Get current user's role in this conversation
$roleQuery = $conn->prepare("SELECT member_role FROM chat_conversation_members WHERE conversation_id = ? AND user_id = ?");
$roleQuery->bind_param("ii", $conversation_id, $user_id);
$roleQuery->execute();
$currentUserRole = $roleQuery->get_result()->fetch_assoc()['member_role'] ?? 'member';
$roleQuery->close();

// Format members for frontend
$formattedMembers = [];
foreach ($members as $member) {
    $isCurrentUser = ($member['user_id'] == $user_id);
    $addedByName = '';
    if ($member['added_by']) {
        $addedByName = trim($member['added_by_fname'] . ' ' . $member['added_by_lname']);
    }

    // If member was not added by someone and is not the owner, fall back to channel creator name
    if (!$addedByName && $member['member_role'] !== 'owner') {
        $addedByName = $creatorName;
    }
    
    $formattedMembers[] = [
        'user_id' => (int)$member['user_id'],
        'full_name' => $member['full_name'],
        'email' => $member['email'],
        'barangay' => $member['barangay_name'] ?? '',
        'role' => $member['member_role'],
        'user_role' => $member['user_role'],
        'is_current_user' => $isCurrentUser,
        'added_by' => $member['added_by'] ? (int)$member['added_by'] : null,
        'added_by_name' => $addedByName,
        'last_active_at' => $member['last_active_at']
    ];
}

echo json_encode([
    'success' => true,
    'members' => $formattedMembers,
    'total' => count($formattedMembers),
    'current_user_id' => (int)$user_id,
    'current_user_role' => $currentUserRole,
    'creator_name' => $creatorName
]);
?>