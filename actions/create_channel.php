<?php
require_once '../init_session.php';
require_once '../config.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$user_id = $_SESSION['user_id'];

// Get and sanitize input
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$visibility = $_POST['visibility'] ?? 'public';
$barangay_id = isset($_POST['barangay_id']) && is_numeric($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;
$category = $_POST['category'] ?? null;

// Validate
if (empty($name)) {
    echo json_encode(['error' => 'Channel name is required.']);
    exit;
}
if (!in_array($visibility, ['public', 'private'])) {
    echo json_encode(['error' => 'Invalid visibility.']);
    exit;
}

// Generate slug from name
$slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
// Ensure uniqueness
$slugOriginal = $slug;
$counter = 1;
while (true) {
    $checkStmt = $conn->prepare("SELECT id FROM chat_conversations WHERE slug = ?");
    $checkStmt->bind_param("s", $slug);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows === 0) {
        break;
    }
    $slug = $slugOriginal . '-' . $counter;
    $counter++;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Insert conversation
    $stmt = $conn->prepare("INSERT INTO chat_conversations (type, name, slug, description, visibility, barangay_id, created_by, category) VALUES ('channel', ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssis", $name, $slug, $description, $visibility, $barangay_id, $user_id, $category);
    if (!$stmt->execute()) {
        throw new Exception('Failed to create a channel: ' . $stmt->error);
    }
    $conversation_id = $conn->insert_id;

    // Add creator as owner
    $memberStmt = $conn->prepare("INSERT INTO chat_conversation_members (conversation_id, user_id, member_role) VALUES (?, ?, 'owner')");
    $memberStmt->bind_param("ii", $conversation_id, $user_id);
    if (!$memberStmt->execute()) {
        throw new Exception('Failed to add owner: ' . $memberStmt->error);
    }

    // Visibility public, add all active users as members
    if ($visibility === 'public') {
        // Fetch all active users except the creator
        $usersStmt = $conn->prepare("SELECT id FROM users_tbl WHERE id != ? AND archived = 0");
        $usersStmt->bind_param("i", $user_id);
        $usersStmt->execute();
        $users = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $usersStmt->close();

        if (!empty($users)) {
            // Prepare batch insert members 
            $batchInsert = $conn->prepare("INSERT INTO chat_conversation_members(conversation_id, user_id, member_role) VALUES (?, ?,'member')");
            foreach ($users as $u) {
                $batchInsert->bind_param("ii", $conversation_id, $u['id']);
                $batchInsert->execute();
            }
            $batchInsert->close();
        }
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Channel created successfully!',
        'channel' => [
            'id' => $conversation_id,
            'name' => $name,
            'slug' => $slug,
            'visibility' => $visibility
        ]
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}