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
        c.category,
        c.visibility,
        cm.member_role,
        cm.is_archived,
        cm.is_muted,
        (
            SELECT COUNT(*) 
            FROM chat_messages m 
            WHERE m.conversation_id = c.id 
              AND m.sender_id != ? 
              AND m.archived = 0
              AND m.message_type != 'system'
              AND NOT EXISTS (
                  SELECT 1 FROM chat_message_reads r 
                  WHERE r.message_id = m.id 
                    AND r.user_id = ?
              )
              AND NOT EXISTS (
                  SELECT 1 FROM chat_message_user_archives a
                  WHERE a.message_id = m.id AND a.user_id = ?
              )
        ) as unread_count,
        (
            SELECT content 
            FROM chat_messages latest
            WHERE latest.conversation_id = c.id
              AND latest.message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = latest.id AND a.user_id = ?)
            ORDER BY latest.created_at DESC
            LIMIT 1
        ) as last_message,
        (
            SELECT archived
            FROM chat_messages latest
            WHERE latest.conversation_id = c.id
              AND latest.message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = latest.id AND a.user_id = ?)
            ORDER BY latest.created_at DESC
            LIMIT 1
        ) as last_message_unsent,
        (
            SELECT created_at 
            FROM chat_messages latest
            WHERE latest.conversation_id = c.id
              AND latest.message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = latest.id AND a.user_id = ?)
            ORDER BY latest.created_at DESC
            LIMIT 1
        ) as last_message_time,
        (
            SELECT COALESCE(CONCAT(u.fname, ' ', u.lname), 'System')
            FROM chat_messages m
            LEFT JOIN users_tbl u ON m.sender_id = u.id
            WHERE m.conversation_id = c.id 
              AND m.message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = m.id AND a.user_id = ?)
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_sender_name,
        (
            SELECT COALESCE(m.sender_id, 0)
            FROM chat_messages m
            WHERE conversation_id = c.id 
              AND message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = m.id AND a.user_id = ?)
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

$channelStmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$channelStmt->execute();
$channels = $channelStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch direct message conversations
$dmQuery = "
    SELECT 
        c.id,
        u.id as user_id,
        u.email,
        CONCAT(u.fname, ' ', u.lname) as name,
        b.name as address,
        u.role,
        cm2.is_archived,
        cm2.is_muted,
        (
            SELECT COUNT(*)
            FROM chat_messages m
            WHERE m.conversation_id = c.id
              AND m.sender_id != ?
              AND m.archived = 0
              AND m.message_type != 'system'
              AND NOT EXISTS (
                  SELECT 1 FROM chat_message_reads r
                  WHERE r.message_id = m.id AND r.user_id = ?
              )
              AND NOT EXISTS (
                  SELECT 1 FROM chat_message_user_archives a
                  WHERE a.message_id = m.id AND a.user_id = ?
              )
        ) as unread_count,
        (
            SELECT content 
            FROM chat_messages latest
            WHERE latest.conversation_id = c.id
              AND latest.message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = latest.id AND a.user_id = ?)
            ORDER BY latest.created_at DESC
            LIMIT 1
        ) as last_message,
        (
            SELECT archived
            FROM chat_messages latest
            WHERE latest.conversation_id = c.id
              AND latest.message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = latest.id AND a.user_id = ?)
            ORDER BY latest.created_at DESC
            LIMIT 1
        ) as last_message_unsent,
        (
            SELECT created_at 
            FROM chat_messages latest
            WHERE latest.conversation_id = c.id
              AND latest.message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = latest.id AND a.user_id = ?)
            ORDER BY latest.created_at DESC
            LIMIT 1
        ) as last_message_time,
        (
            SELECT COALESCE(CONCAT(u_sender.fname, ' ', u_sender.lname), 'System')
            FROM chat_messages m
            LEFT JOIN users_tbl u_sender ON m.sender_id = u_sender.id
            WHERE m.conversation_id = c.id 
              AND m.message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = m.id AND a.user_id = ?)
            ORDER BY m.created_at DESC 
            LIMIT 1
        ) as last_sender_name,
        (
            SELECT COALESCE(m.sender_id, 0)
            FROM chat_messages m
            WHERE conversation_id = c.id 
              AND message_type != 'system'
              AND NOT EXISTS (SELECT 1 FROM chat_message_user_archives a WHERE a.message_id = m.id AND a.user_id = ?)
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_sender_id
    FROM chat_conversations c
    JOIN chat_conversation_members cm ON c.id = cm.conversation_id
    JOIN users_tbl u ON u.id = cm.user_id
    LEFT JOIN barangays b ON u.barangay_id = b.id
    JOIN chat_conversation_members cm2 ON c.id = cm2.conversation_id AND cm2.user_id = ?
    WHERE c.type = 'direct' 
      AND c.archived = 0
      AND cm.user_id != ?
      AND cm.left_at IS NULL
      AND EXISTS (
          SELECT 1 FROM chat_conversation_members cm3
          WHERE cm3.conversation_id = c.id 
          AND cm3.user_id = ?
          AND cm3.left_at IS NULL
      )
    ORDER BY last_message_time DESC
";

$dmStmt = $conn->prepare($dmQuery);
$dmStmt->bind_param("iiiiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
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
            <div class="sidebar-loading-skeleton" id="sidebarLoadingSkeleton" aria-hidden="false">
                <div class="sidebar-skeleton-card sidebar-skeleton-search-card">
                    <div class="sidebar-skeleton-filter-row">
                        <span class="skeleton-shape sidebar-skeleton-filter"></span>
                        <span class="skeleton-shape sidebar-skeleton-filter"></span>
                    </div>
                    <span class="skeleton-shape sidebar-skeleton-search"></span>
                    <div class="sidebar-skeleton-action-row">
                        <span class="skeleton-shape sidebar-skeleton-action"></span>
                        <span class="skeleton-shape sidebar-skeleton-action"></span>
                    </div>
                </div>

                <div class="sidebar-skeleton-card sidebar-skeleton-channel-card">
                    <span class="skeleton-shape sidebar-skeleton-heading"></span>
                    <div class="sidebar-skeleton-list">
                        <?php for ($sidebarSkeletonItem = 0; $sidebarSkeletonItem < 3; $sidebarSkeletonItem++): ?>
                            <div class="sidebar-skeleton-item">
                                <div class="sidebar-skeleton-item-copy">
                                    <span class="skeleton-shape sidebar-skeleton-name"></span>
                                    <span class="skeleton-shape sidebar-skeleton-preview"></span>
                                </div>
                                <span class="skeleton-shape sidebar-skeleton-time"></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="sidebar-skeleton-card sidebar-skeleton-dm-card">
                    <span class="skeleton-shape sidebar-skeleton-heading sidebar-skeleton-heading-wide"></span>
                    <div class="sidebar-skeleton-list">
                        <?php for ($sidebarSkeletonItem = 0; $sidebarSkeletonItem < 4; $sidebarSkeletonItem++): ?>
                            <div class="sidebar-skeleton-item">
                                <div class="sidebar-skeleton-item-copy">
                                    <span class="skeleton-shape sidebar-skeleton-name"></span>
                                    <span class="skeleton-shape sidebar-skeleton-preview"></span>
                                </div>
                                <span class="skeleton-shape sidebar-skeleton-time"></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <span class="sr-only" role="status">Loading conversations…</span>
            </div>

            <!-- Search Bar -->
            <div class="sidebar-search-container">
                <div class="conversation-filter" role="tablist" aria-label="Conversation filter" data-active="chats">
                    <button type="button" class="conversation-filter-btn active" data-filter="chats" role="tab" aria-selected="true" aria-label="Show chats" title="Chats">
                        <span class="filter-label">Chats</span>
                    </button>
                    <button type="button" class="conversation-filter-btn" data-filter="archived" role="tab" aria-selected="false" aria-label="Show archived conversations" title="Archived">
                        <span class="filter-label">Archived</span>
                    </button>
                </div>
                <!-- Search -->
                <div class="sidebar-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="sidebarSearchInput" placeholder="Search Chats, Channels" oninput="filterSidebar(this.value)">
                </div>
                <!-- sidebar actions -->
                <div class="sidebar-actions">
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                        <button class="btn-new-channel" onclick="showCreateChannelModal()">
                            <!-- <i class="fa-solid fa-plus"></i> -->
                            Add Channels
                        </button>
                        <button class="btn-new-message" onclick="showAddMessageModal()">
                            <!-- <img src="components\icons\message-circle-more.png" alt="" class="newMessage-icon"> -->
                            Add Contacts
                        </button>
                    <?php else: ?>
                        <button class="btn-new-message full-width" onclick="showAddMessageModal()">
                            <!-- <img src="components\icons\message-circle-more.png" alt="" class="newMessage-icon"> -->
                            Add Contacts
                        </button>
                    <?php endif; ?>
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
                            $unreadCount = (int)$channel['unread_count'];
                            $isMuted = (bool)$channel['is_muted'];
                            $isArchived = (bool)$channel['is_archived'];

                            if ((int)($channel['last_message_unsent'] ?? 0) === 1 && $lastSender) {
                                $isSelf = ($channel['last_sender_id'] == $user_id);
                                $senderDisplay = $isSelf ? 'You' : $lastSender;
                                $lastMsgDisplay = $isSelf
                                    ? 'You have unsent a message'
                                    : $senderDisplay . ' has unsent a message';
                            } elseif ($lastMsg && $lastSender) {
                                $isSelf = ($channel['last_sender_id'] == $user_id);
                                $senderDisplay = $isSelf ? 'You' : $lastSender;
                                $lastMsgDisplay = $senderDisplay . ': ' . $lastMsg;
                            } else {
                                $lastMsgDisplay = 'No messages yet';
                            }

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
                            <li class="channel-item <?= $isMuted ? 'muted' : '' ?> <?= $unreadCount > 0 && !$isMuted && !$isArchived ? 'unread' : '' ?>"
                                <?= $isArchived ? 'hidden' : '' ?>
                                data-type="channel"
                                data-id="<?= $channel['id'] ?>"
                                data-description="<?= htmlspecialchars($channel['description'] ?? '') ?>"
                                data-category="<?= htmlspecialchars($channel['category'] ?? 'general') ?>"
                                data-visibility="<?= htmlspecialchars($channel['visibility'] ?? 'public') ?>"
                                data-member-role="<?= htmlspecialchars($channel['member_role'] ?? 'member') ?>"
                                data-archived="<?= $channel['is_archived'] ?>"
                                data-muted="<?= $isMuted ? '1' : '0' ?>"
                                data-unread="<?= $unreadCount ?>">
                                <div class="left-channel-item">
                                    <div class="leftItem2">
                                        <span class="channel-name"># <?= htmlspecialchars($channel['name']) ?></span>
                                        <span class="channel-time"><?= $lastTime ?></span>
                                    </div>
                                    <span class="channel-last-msg"><?= htmlspecialchars($lastMsgDisplay) ?></span>
                                </div>
                                <div class="right-channel-item">
                                    <?php if ($isMuted): ?>
                                        <i class="fa-regular fa-bell-slash muted-icon"></i>
                                    <?php endif; ?>
                                    <?php if ($unreadCount > 0 && !$isMuted && !$isArchived): ?>
                                        <span class="unread-dot"></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <li id="emptyChannels" class="conversation-empty-state" hidden>
                            <i class="fas fa-hashtag" style="font-size: 24px; display: block; margin-bottom: 8px;"></i>
                            <p>No channels yet. Create one to start chatting!</p>
                        </li>
                    </ul>
                </div>
            </div>
            <!-- Direct Messages Section -->
            <div class="sidebar-section directMessages">
                <div class="section-header">Direct Messages</div>
                <ul class="dm-list">
                    <?php foreach ($directMessages as $dm):
                        $lastSender = $dm['last_sender_name'] ?? '';
                        $lastMsg = $dm['last_message'] ?? 'No messages yet';
                        $unreadCount = (int)($dm['unread_count'] ?? 0);
                        $isMuted = (bool)$dm['is_muted'];
                        $isArchived = (bool)$dm['is_archived'];

                        if ((int)($dm['last_message_unsent'] ?? 0) === 1 && $lastSender) {
                            $isSelf = ($dm['last_sender_id'] == $user_id);
                            $senderDisplay = $isSelf ? 'You' : $lastSender;
                            $lastMsgDisplay = $isSelf
                                ? 'You have unsent a message'
                                : $senderDisplay . ' has unsent a message';
                        } elseif ($lastMsg && $lastSender) {
                            $isSelf = ($dm['last_sender_id'] == $user_id);
                            $senderDisplay = $isSelf ? 'You' : $lastSender;
                            $lastMsgDisplay = $senderDisplay . ': ' . $lastMsg;
                        } else {
                            $lastMsgDisplay = 'No messages yet';
                        }

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
                        <li class="dm-item <?= $isMuted ? 'muted' : '' ?> <?= $unreadCount > 0 && !$isMuted && !$isArchived ? 'unread' : '' ?>"
                            <?= $isArchived ? 'hidden' : '' ?>
                            data-type="dm"
                            data-id="<?= $dm['id'] ?>"
                            data-email="<?= htmlspecialchars($dm['email'] ?? '') ?>"
                            data-address="<?= htmlspecialchars($dm['address'] ?? '') ?>"
                            data-role="<?= htmlspecialchars($dm['role'] ?? '') ?>"
                            data-archived="<?= $dm['is_archived'] ?>"
                            data-muted="<?= $isMuted ? '1' : '0' ?>"
                            data-unread="<?= $unreadCount ?>">
                            <div class="left-channel-item">
                                <div class="leftItem2">
                                    <span class="dm-name"><?= htmlspecialchars($dm['name'] ?? 'Unknown') ?></span>
                                    <span class="dm-time"><?= $dmLastTime ?></span>
                                </div>
                                <span class="dm-last-msg"><?= htmlspecialchars($lastMsgDisplay) ?></span>
                            </div>
                            <div class="right-channel-item">
                                <?php if ($isMuted): ?>
                                    <i class="fa-regular fa-bell-slash muted-icon"></i>
                                <?php endif; ?>
                                <?php if ($unreadCount > 0 && !$isMuted && !$isArchived): ?>
                                    <span class="unread-dot"></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <li id="emptyDms" class="conversation-empty-state" hidden>
                        No direct messages yet.
                    </li>
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
                    <p>Click the <strong><i>Add Contacts</i></strong> and search for someone you may know! Or just click the <strong><i>Channels</i></strong>!</p>
                </div>
            </div>

            <!-- Actual chat window (hidden until a conversation is selected) -->
            <div id="chatWindow" class="chat-window" style="display: none;">
                <div class="chat-loading-skeleton" id="chatLoadingSkeleton" aria-hidden="true" hidden>
                    <div class="chat-skeleton-header">
                        <div class="chat-skeleton-actions">
                            <span class="skeleton-shape skeleton-circle"></span>
                            <span class="skeleton-shape skeleton-circle"></span>
                            <span class="skeleton-shape skeleton-circle"></span>
                        </div>
                        <div class="chat-skeleton-profile">
                            <span class="skeleton-shape skeleton-avatar"></span>
                            <span class="chat-skeleton-profile-lines">
                                <span class="skeleton-shape skeleton-line skeleton-title"></span>
                                <span class="skeleton-shape skeleton-line skeleton-subtitle"></span>
                            </span>
                            <span class="skeleton-shape skeleton-badge"></span>
                        </div>
                    </div>
                    <div class="chat-skeleton-messages" aria-label="Loading conversation">
                        <div class="chat-skeleton-message">
                            <span class="skeleton-shape skeleton-avatar skeleton-avatar-small"></span>
                            <span class="chat-skeleton-bubble">
                                <span class="skeleton-shape skeleton-line skeleton-name"></span>
                                <span class="skeleton-shape skeleton-line skeleton-message-line"></span>
                                <span class="skeleton-shape skeleton-line skeleton-message-line short"></span>
                            </span>
                        </div>
                        <div class="chat-skeleton-message self">
                            <span class="skeleton-shape skeleton-avatar skeleton-avatar-small"></span>
                            <span class="chat-skeleton-bubble">
                                <span class="skeleton-shape skeleton-line skeleton-name"></span>
                                <span class="skeleton-shape skeleton-line skeleton-message-line"></span>
                            </span>
                        </div>
                        <div class="chat-skeleton-message">
                            <span class="skeleton-shape skeleton-avatar skeleton-avatar-small"></span>
                            <span class="chat-skeleton-bubble">
                                <span class="skeleton-shape skeleton-line skeleton-name"></span>
                                <span class="skeleton-shape skeleton-line skeleton-message-line"></span>
                            </span>
                        </div>
                    </div>
                    <div class="chat-skeleton-input">
                        <span class="skeleton-shape skeleton-input-bar"></span>
                        <span class="skeleton-shape skeleton-send"></span>
                    </div>
                    <span class="sr-only" role="status">Loading conversation…</span>
                </div>
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-header-top">
                        <button class="chat-header-icon-btn" id="chatMuteBtn" type="button" title="Mute conversation" aria-label="Mute conversation" aria-pressed="false">
                            <img src="components/icons/bell.png" alt="" aria-hidden="true">
                            <span class="sr-only">Mute conversation</span>
                        </button>
                        <button class="chat-header-icon-btn" id="pinnedMessagesBtn" type="button" title="View pinned messages" aria-label="View pinned messages" aria-haspopup="dialog" aria-controls="pinnedMessagesModal" aria-expanded="false">
                            <img src="components/icons/pin.png" alt="" aria-hidden="true">
                        </button>
                        <div class="chat-menu-wrapper">
                            <button class="chat-menu-btn" id="chatMenuBtn" type="button" title="More options">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="chat-menu-dropdown" id="chatMenuDropdown" style="display: none;">
                                <!-- Only show Members List for channels -->
                                <button data-action="add-people" id="membersListBtn" style="display: none;">Members List</button>
                                <button data-action="archive">Archive</button>
                                <button data-action="leave" id="leaveChannelBtn" style="display: none;">Leave</button>
                                <button data-action="delete-dm" id="deleteConversationBtn" style="display: none;">Delete</button>
                                <button data-action="delete-channel" id="deleteChannelBtn" style="display: none;">Delete Channel</button>
                            </div>
                        </div>
                    </div>
                    <div class="chat-header-main">
                        <button class="mobile-chat-back" id="mobileChatBack" type="button" aria-label="Back to conversations">
                            <i class="fa-solid fa-arrow-left"></i>
                        </button>
                        <div class="chat-header-left">
                            <div class="chat-avatar">
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

                <div class="chat-search-bar" style="display: flex; padding: 8px 20px; border-bottom: 1px solid #edf2f7; gap: 8px; align-items: center;">
                    <i class="fas fa-search" style="color: #a0aec0;"></i>
                    <input type="text" id="chatSearchInput" placeholder="Search in this conversation..." style="flex: 1; border: none; outline: none; padding: 8px 0; font-size: 0.9rem; background: transparent;">
                    <button id="chatSearchClear" style="background: none; border: none; color: #a0aec0; cursor: pointer; display: none; font-size: 1.2rem;">&times;</button>
                </div>

                <!-- Chat Messages -->
                <div class="chat-messages" id="chatMessages">
                    <!-- Messages will be dynamically loaded here -->
                </div>

                <!-- Members Panel (replaces chat messages) -->
                <div id="membersPanel" class="members-panel" style="display: none;">
                    <!-- Panel Header -->
                    <div class="members-panel-header">
                        <div class="members-header-left">
                            <div class="members-header-info">
                                <h3>CHAT MEMBERS LIST</h3>
                            </div>
                        </div>
                        <span class="members-count" id="membersCount">0 USERS</span>
                    </div>

                    <!-- Members List -->
                    <div class="members-list-container" id="membersListContainer">
                        <!-- Dynamic content will be loaded here -->
                    </div>
                    <div class="add-members-btn">
                        <button class="add-volunteer-btn" onclick="showAddVolunteerModal()">
                            <span class="material-symbols-rounded">
                                person_add
                            </span>
                            <p>Add volunteers</p>
                        </button>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="chat-input">
                    <div class="reply-preview-wrapper">
                        <div id="replyPreview" class="reply-preview" style="display: none;">
                            <div class="reply-preview-content">
                                <span><span id="composerPreviewLabel">Replying to</span> <strong id="replyPreviewName"></strong></span>
                                <span id="replyPreviewMessage"></span>
                            </div>
                            <button id="cancelReplyBtn" class="cancel-reply-btn" type="button" aria-label="Cancel reply" title="Cancel reply">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chat-input-row">
                        <input type="text" placeholder="Type your message..." id="messageInput">
                        <button id="sendMessageBtn"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
            <div id="globalMessageActionsDropdown" class="message-actions-dropdown" style="display: none;
                position: fixed; z-index: 10000; background: white; border: 1px solid #e2e8f0; border-radius: 12px; min-width: 180px; padding: 4px 0;">
                <!-- Buttons will be dynamically inserted -->
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/modals/pinned_messages.php'; ?>

    <script src="message.js"></script>
    <script>
        // Pass current user ID to JavaScript for "You" detection
        const currentUserId = <?= json_encode($user_id) ?>;
    </script>

    <?php include 'footer.php'; ?>
