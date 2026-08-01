<?php
require_once 'init_session.php';
require_once 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit;
}

// Get current user info
$user_id = $_SESSION['user_id'];
$userName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Fetch user's barangay
$userStmt = $conn->prepare("SELECT b.name as barangay_name FROM users_tbl u LEFT JOIN barangays b ON u.barangay_id = b.id WHERE u.id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userBarangay = $userStmt->get_result()->fetch_assoc()['barangay_name'] ?? 'Unknown Barangay';
$userStmt->close();

// Fetch real channels from database
$channels = [];
$channelStmt = $conn->prepare("
    SELECT 
        c.id, 
        c.name, 
        c.slug, 
        c.description,
        (
            SELECT content 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT created_at 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message_time,
        (
            SELECT CONCAT(u.fname, ' ', u.lname) 
            FROM chat_messages m
            LEFT JOIN users_tbl u ON m.sender_id = u.id
            WHERE m.conversation_id = c.id 
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_sender_name,
        (
            SELECT sender_id 
            FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_sender_id
    FROM chat_conversations c
    JOIN chat_conversation_members cm ON c.id = cm.conversation_id
    WHERE c.type = 'channel' 
      AND c.archived = 0 
      AND cm.user_id = ? 
      AND cm.left_at IS NULL
    ORDER BY c.name ASC
");
$channelStmt->bind_param("i", $user_id);
$channelStmt->execute();
$channels = $channelStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// For now, keep dummy DMs – we'll replace later with real DMs
// $directMessages = [
//     ['id' => 1, 'name' => 'John Doe', 'email' => 'john.doe@gmail.com', 'address' => 'Nagbalayong, Morong Bataan', 'role' => 'Volunteer', 'last_message' => 'I think we have to st...', 'time' => '9:02 AM'],
//     ['id' => 2, 'name' => 'James Dean', 'email' => 'james.dean@gmail.com', 'address' => 'Poblacion, Morong Bataan', 'role' => 'Volunteer', 'last_message' => 'Hello I would like to reque...', 'time' => '10:08 PM'],
// ];

// $directMessages = [
//     ['id' => 1, 'name' => 'John Doe', 'email' => 'john.doe@gmail.com', 'address' => 'Nagbalayong, Morong Bataan', 'role' => 'Volunteer', 'last_message' => 'I think we have to st...', 'time' => '9:02 AM'],
//     ['id' => 2, 'name' => 'James Dean', 'email' => 'james.dean@gmail.com', 'address' => 'Poblacion, Morong Bataan', 'role' => 'Volunteer', 'last_message' => 'Hello I would like to reque...', 'time' => '10:08 PM'],
// ];

// Fetch direct message conversations
$dmQuery = "
    SELECT 
        c.id,
        u.id as user_id,
        u.email,
        CONCAT(u.fname, ' ', u.lname) as name,
        b.name as address,
        u.role,
        (SELECT content FROM chat_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM chat_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
    FROM chat_conversations c
    JOIN chat_conversation_members cm ON c.id = cm.conversation_id
    JOIN users_tbl u ON u.id = cm.user_id
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE c.type = 'direct' 
      AND c.archived = 0
      AND cm.user_id != ?
      AND cm.left_at IS NULL
      AND EXISTS (
          SELECT 1 FROM chat_conversation_members cm2 
          WHERE cm2.conversation_id = c.id 
          AND cm2.user_id = ?
          AND cm2.left_at IS NULL
      )
    ORDER BY last_message_time DESC
";

$dmStmt = $conn->prepare($dmQuery);
$dmStmt->bind_param("ii", $user_id, $user_id);
$dmStmt->execute();
$directMessages = $dmStmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'header.php';
?>
<link rel="stylesheet" href="message.css">

<div class="chat-page">
    <div class="chat-sidebar-header">
        <h2>Messages</h2>
        <p>Collaborate and communicate with the users across the platform!</p>
    </div>
    <div class="chat-container">
        <!-- Left Sidebar -->
        <div class="chat-sidebar">
            <!-- Search Bar -->
            <div class="sidebar-search-container">
                <h3>Messages</h3>
                <div class="sidebar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search Chats, Channels">
                </div>
                <!-- sidebar actions -->
                <div class="sidebar-actions">
                    <button class="btn-new-channel" onclick="showCreateChannelModal()">
                        <i class="fa-solid fa-plus"></i>
                        Add Channels
                    </button>
                    <button class="btn-new-message" onclick="showAddMessageModal()">
                        <img src="components\icons\message-circle-more.png" alt="" class="newMessage-icon">
                        New Message
                    </button>
                </div>
            </div>

            <!-- Channels Section -->
            <div class="sidebar-section channel">
                <div class="section-header">Channels</div>
                <div class="channel-card">
                    <ul class="channel-list">
                        <?php foreach ($channels as $channel):
                            $lastSender = $channel['last_sender_name'] ?? '';
                            $lastMsg = $channel['last_message'] ?? 'No messages yet';

                            // Format the last message with sender name
                            if ($lastMsg && $lastSender) {
                                $isSelf = ($channel['last_sender_id'] == $user_id);
                                $senderDisplay = $isSelf ? 'You' : $lastSender;
                                $lastMsgDisplay = $senderDisplay . ': ' . $lastMsg;
                            } else {
                                $lastMsgDisplay = 'No messages yet';
                            }

                            // 🔧 Fix: Use DateTime with correct timezone
                            $lastTime = '';
                            if ($channel['last_message_time']) {
                                try {
                                    $dt = new DateTime($channel['last_message_time'], new DateTimeZone('Asia/Manila'));
                                    $lastTime = $dt->format('g:i A');
                                } catch (Exception $e) {
                                    $lastTime = date('g:i A', strtotime($channel['last_message_time']));
                                }
                            }
                        ?>
                            <li class="channel-item" data-type="channel" data-id="<?= $channel['id'] ?>">
                                <span class="channel-name"># <?= htmlspecialchars($channel['name']) ?></span>
                                <span class="channel-time"><?= $lastTime ?></span>
                                <span class="channel-last-msg"><?= htmlspecialchars($lastMsgDisplay) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Direct Messages Section -->
            <div class="sidebar-section directMessages">
                <div class="section-header">Direct Messages</div>
                <ul class="dm-list">
                    <?php foreach ($directMessages as $dm):
                        // 🔧 Fix: Use DateTime with correct timezone
                        $dmLastTime = '';
                        if ($dm['last_message_time']) {
                            try {
                                $dt = new DateTime($dm['last_message_time'], new DateTimeZone('Asia/Manila'));
                                $dmLastTime = $dt->format('g:i A');
                            } catch (Exception $e) {
                                $dmLastTime = date('g:i A', strtotime($dm['last_message_time']));
                            }
                        }
                    ?>
                        <li class="dm-item" data-type="dm" data-id="<?= $dm['id'] ?>"
                            data-email="<?= htmlspecialchars($dm['email'] ?? '') ?>"
                            data-address="<?= htmlspecialchars($dm['address'] ?? '') ?>"
                            data-role="<?= htmlspecialchars($dm['role'] ?? '') ?>">
                            <span class="dm-name"><?= htmlspecialchars($dm['name'] ?? 'Unknown') ?></span>
                            <span class="dm-time"><?= $dmLastTime ?></span>
                            <span class="dm-last-msg"><?= htmlspecialchars($dm['last_message'] ?? 'No messages yet') ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($directMessages)): ?>
                        <li class="dm-item" style="text-align: center; color: #a0aec0; padding: 12px;">
                            No direct messages yet.
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- User Info -->
            <!-- <div class="sidebar-user">
                <div class="user-avatar">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($userName) ?></span>
                    <span class="user-barangay"><?= htmlspecialchars($userBarangay) ?></span>
                </div>
            </div> -->
        </div>

        <!-- Main Chat Area -->
        <div class="chat-main">
            <!-- Placeholder when no conversation is selected -->
            <div id="chatPlaceholder" class="chat-placeholder">
                <div class="placeholder-content">
                    <img src="components\icons\empty-chat-img.svg" alt="">
                    <h3>Start your conversation!</h3>
                    <p>Click the <strong><i>New Message</i></strong> and search for someone you may know! Or just click the <strong><i>Channels</i></strong>!</p>
                </div>
            </div>

            <!-- Actual chat window (hidden until a conversation is selected) -->
            <div id="chatWindow" class="chat-window" style="display: none;">
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-header-top">
                        <button class="chat-menu-btn" id="chatMenuBtn" title="More options">
                            <i class="fa-solid fa-ellipsis-vertical"></i>
                        </button>
                    </div>
                    <div class="chat-header-main">
                        <div class="chat-header-left">
                            <div class="chat-avatar">
                                <!-- <i class="fa-solid fa-user"></i> -->
                                <span class="material-symbols-rounded" id="chatAvatarIcon">
                                    person
                                </span>
                            </div>
                            <div class="chat-user-info">
                                <span id="chatTitle">#concerns</span>
                                <span id="chatAddress" class="chat-address">Nagbalayong, Morong Bataan</span>
                            </div>
                        </div>
                        <span id="chatRoleBadge" class="role-badge" style="display: none;">Volunteer</span>
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <!-- Messages will be dynamically loaded here -->
                </div>
                <div class="chat-input">
                    <input type="text" placeholder="Type your message..." id="messageInput">
                    <button id="sendMessageBtn"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="message.js"></script>
<script>
    // Pass current user ID to JavaScript for "You" detection
    const currentUserId = <?= json_encode($user_id) ?>;
</script>

<?php include 'footer.php'; ?>