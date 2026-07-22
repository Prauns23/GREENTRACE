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

// For now, we'll use dummy data for channels and direct messages.
// In production, these will come from the database.
$channels = [
    ['name' => 'concerns', 'description' => 'Discuss environmental issues'],
    ['name' => 'activities', 'description' => 'Volunteer activities coordination'],
    ['name' => 'reports', 'description' => 'Report submissions and updates'],
];

// Dummy DMs (will be fetched from DB)
$directMessages = [
    ['id' => 1, 'name' => 'John Doe', 'last_message' => 'I think we have to st...', 'time' => '9:02 AM'],
    ['id' => 2, 'name' => 'James Dean', 'last_message' => 'Hello I would like to reque...', 'time' => '10:08 PM'],
];

include 'header.php';
?>
<link rel="stylesheet" href="message.css">

<div class="chat-page">
    <div class="sidebar-header">
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
                    <button class="btn-new-channel" onclick="openModal('createChannelModal')">
                        <i class="fa-solid fa-plus"></i>
                        Add Channels
                    </button>
                    <button class="btn-new-message" onclick="openModal('newMessageModal')">
                        <img src="components\icons\message-circle-more.png" alt="" class="newMessage-icon">
                        New Message
                    </button>
                </div>
            </div>

            <!-- Channels Section -->
            <div class="sidebar-section">
                <div class="section-header">Channels</div>
                <div class="channel-card">
                    <ul class="channel-list">
                        <?php foreach ($channels as $channel): ?>
                            <div class="channels-icon">
                                <!-- <span class="material-symbols-rounded">
                                    diversity_1
                                </span> -->
                            </div>
                            <div class="channel-card-content">
                                <li class="channel-item" data-type="channel" data-id="#<?= strtolower($channel['name']) ?>">
                                    <span class="channel-name">#<?= htmlspecialchars($channel['name']) ?></span>
                                    <span class="channel-time">9:00 AM</span>
                                    <span class="channel-last-msg"><?= htmlspecialchars($channel['description']) ?></span>
                                </li>
                            </div>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Direct Messages Section -->
            <div class="sidebar-section">
                <div class="section-header">Direct Messages</div>
                <ul class="dm-list">
                    <?php foreach ($directMessages as $dm): ?>
                        <li class="dm-item" data-type="dm" data-id="<?= $dm['id'] ?>">
                            <span class="dm-name"><?= htmlspecialchars($dm['name']) ?></span>
                            <span class="dm-time"><?= $dm['time'] ?></span>
                            <span class="dm-last-msg"><?= htmlspecialchars($dm['last_message']) ?></span>
                        </li>
                    <?php endforeach; ?>
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
                    <h3>Start your conversation!</h3>
                    <p>Leaving a digital footprint! This chat is recorded to help us track our progress.</p>
                </div>
            </div>

            <!-- Actual chat window (hidden until a conversation is selected) -->
            <div id="chatWindow" class="chat-window" style="display: none;">
                <div class="chat-header">
                    <span id="chatTitle">#concerns</span>
                    <span id="chatMembers">Members: 12</span>
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

<!-- New Message Modal -->
<div id="newMessageModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('newMessageModal')">&times;</span>
        <h3>New Message</h3>
        <div class="modal-body">
            <div class="form-group">
                <label>To:</label>
                <div class="user-select">
                    <!-- In production, this would be a searchable dropdown -->
                    <input type="text" placeholder="Search users...">
                    <ul class="user-list">
                        <li><input type="checkbox"> Chrisostomo Ibarra ✅</li>
                        <li><input type="checkbox"> George Washington ✅</li>
                        <li><input type="checkbox"> Binaritan Admin ☐</li>
                        <li><input type="checkbox"> Nagbalayong Admin ☐</li>
                        <li><input type="checkbox"> James Kjelberg ☐</li>
                        <li><input type="checkbox"> Bernabe Admin ☐</li>
                        <li><input type="checkbox"> Ashley Peregrino ☐</li>
                        <li><input type="checkbox"> Yuumi Serano ☐</li>
                        <li><input type="checkbox"> Mabayo Admin ☐</li>
                    </ul>
                </div>
            </div>
            <div class="button-group">
                <button class="btn-cancel" onclick="closeModal('newMessageModal')">Cancel</button>
                <button class="btn-submit" onclick="closeModal('newMessageModal')">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Create Channel Modal -->
<div id="createChannelModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('createChannelModal')">&times;</span>
        <h3>Create a channel</h3>
        <div class="modal-body">
            <div class="form-group">
                <label>Channel Name</label>
                <input type="text" placeholder="# concerns">
            </div>
            <div class="form-group">
                <label>Channel Type</label>
                <select>
                    <option>Messaging</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea placeholder="Write your channel description here"></textarea>
            </div>
            <div class="form-group">
                <label>Visibility</label>
                <div class="radio-group">
                    <label><input type="radio" name="visibility" checked> Public – Anyone in #concerns</label>
                    <label><input type="radio" name="visibility"> Private – Only specific users</label>
                </div>
            </div>
            <div class="button-group">
                <button class="btn-cancel" onclick="closeModal('createChannelModal')">Cancel</button>
                <button class="btn-submit" onclick="closeModal('createChannelModal')">Create</button>
            </div>
        </div>
    </div>
</div>

<script src="messages.js"></script>

<?php include 'footer.php'; ?>