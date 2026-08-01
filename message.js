// ============================
// 1. Modal handling
// ============================
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});

// ============================
// 2. Chat state
// ============================
let currentConversation = null; // { type: 'channel'|'dm', id: number }
let currentPage = 0; // not used anymore, we use oldestTimestamp
const MESSAGES_PER_PAGE = 6;
let isLoading = false;
let hasMoreMessages = true;
let allMessages = [];
let oldestTimestamp = null;

// ============================
// 3. Helper functions
// ============================
function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase();
}

function getSenderName(msg) {
    return msg.sender_name || 'Unknown';
}

// ============================
// 4. Render messages
// ============================
function renderMessages(messages) {
    const container = document.getElementById('chatMessages');
    container.innerHTML = '';
    messages.forEach(msg => {
        const isSelf = msg.is_self ? 'self' : '';
        const senderName = getSenderName(msg);
        const avatar = getInitials(senderName);
        const div = document.createElement('div');
        div.className = `message-item ${isSelf}`;
        div.innerHTML = `
            <div class="message-avatar">${avatar}</div>
            <div class="message-content">
                <div class="message-sender">${senderName}</div>
                <div class="message-text">${msg.content}</div>
                <div class="message-time">${new Date(msg.created_at).toLocaleString('en-PH', { hour: '2-digit', minute: '2-digit' })}</div>
            </div>
        `;
        container.appendChild(div);
    });
}

// ============================
// 5. Fetch messages from server
// ============================
function fetchMessages(isInitial = false) {
    if (isLoading || !hasMoreMessages || !currentConversation) return;
    isLoading = true;

    const params = new URLSearchParams();
    params.append('conversation_id', currentConversation.id);
    params.append('limit', MESSAGES_PER_PAGE);
    if (!isInitial && oldestTimestamp) {
        params.append('before', oldestTimestamp);
    }

    fetch('actions/get_messages.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const messages = data.messages;
                if (messages.length === 0) {
                    hasMoreMessages = false;
                    isLoading = false;
                    return;
                }

                // Update oldestTimestamp for next load
                if (messages.length > 0) {
                    oldestTimestamp = messages[0].created_at;
                }

                if (isInitial) {
                    allMessages = messages;
                } else {
                    allMessages = [...messages, ...allMessages];
                }

                renderMessages(allMessages);

                if (isInitial) {
                    const container = document.getElementById('chatMessages');
                    container.scrollTop = container.scrollHeight;
                }
                isLoading = false;
            } else {
                alert(data.error || 'Failed to load messages');
                isLoading = false;
            }
        })
        .catch(err => {
            console.error(err);
            alert('Network error');
            isLoading = false;
        });
}

function loadMoreMessages() {
    fetchMessages(false);
}

// ============================
// 6. Load a conversation
// ============================
function loadConversation(type, id) {
    // Convert id to integer (for channels, id is numeric from DB)
    const numericId = parseInt(id, 10);
    if (isNaN(numericId)) {
        alert('Invalid conversation ID');
        return;
    }

    currentConversation = { type, id: numericId };
    currentPage = 0;
    hasMoreMessages = true;
    allMessages = [];
    oldestTimestamp = null;

    document.getElementById('chatPlaceholder').style.display = 'none';
    document.getElementById('chatWindow').style.display = 'flex';

    // Clear messages
    document.getElementById('chatMessages').innerHTML = '';

    // Update header
    const chatTitle = document.getElementById('chatTitle');
    const chatAddress = document.getElementById('chatAddress');
    const chatRoleBadge = document.getElementById('chatRoleBadge');
    const chatAvatarIcon = document.getElementById('chatAvatarIcon');

    if (type === 'channel') {
        // Get channel name from the clicked item
        const item = document.querySelector(`.channel-item[data-id="${id}"]`);
        const displayName = item ? item.querySelector('.channel-name').textContent : 'Channel';
        chatTitle.textContent = displayName;
        chatAddress.textContent = 'Public';
        chatRoleBadge.style.display = 'none';
        chatAvatarIcon.textContent = 'group';
    } else {
        // DM
        const item = document.querySelector(`.dm-item[data-id="${id}"]`);
        if (item) {
            const name = item.querySelector('.dm-name').textContent;
            const address = item.dataset.address || '';
            const role = item.dataset.role || '';
            chatTitle.textContent = name;
            chatAddress.textContent = address;
            if (role) {
                let badgeText = '';
                let badgeClass = '';
                switch (role) {
                    case 'admin':
                        badgeText = 'Admin';
                        badgeClass = 'badge-admin';
                        break;
                    case 'super_admin':
                        badgeText = 'Super Admin';
                        badgeClass = 'badge-super-admin';
                        break;
                    default:
                        badgeText = 'Volunteer';
                        badgeClass = 'badge-volunteer';
                }
                chatRoleBadge.textContent = badgeText;
                chatRoleBadge.className = 'role-badge ' + badgeClass;
                chatRoleBadge.style.display = 'inline-block';
            } else {
                chatRoleBadge.style.display = 'none';
            }
        } else {
            chatTitle.textContent = 'Direct Message';
            chatAddress.textContent = '';
            chatRoleBadge.style.display = 'none';
        }
        chatAvatarIcon.textContent = 'person';
    }

    // Load first batch
    fetchMessages(true);
}

// ============================
// 7. Send a message
// ============================
function sendMessage() {
    const input = document.getElementById('messageInput');
    const text = input.value.trim();
    if (!text || !currentConversation) return;

    const sendBtn = document.getElementById('sendMessageBtn');
    sendBtn.disabled = true;

    const formData = new URLSearchParams();
    formData.append('conversation_id', currentConversation.id);
    formData.append('content', text);

    fetch('actions/send_messages.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(response => response.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            console.error('Invalid JSON response from send_messages.php:', text);
            throw err;
        }

        if (data.success) {
            const msg = data.message;
            allMessages.push({
                id: msg.id,
                sender_id: msg.sender_id,
                sender_name: msg.sender_name,
                content: msg.content,
                created_at: msg.created_at,
                is_self: true
            });
            renderMessages(allMessages);
            const container = document.getElementById('chatMessages');
            container.scrollTop = container.scrollHeight;
            input.value = '';
        } else {
            alert(data.error || 'Failed to send message');
        }
        sendBtn.disabled = false;
    })
    .catch(err => {
        console.error(err);
        alert('Network error');
        sendBtn.disabled = false;
    });
}

// 
// 8. Event listeners
// 
document.addEventListener('DOMContentLoaded', function() {
    // Channel clicks
    document.querySelectorAll('.channel-item').forEach(item => {
        item.addEventListener('click', function() {
            document.querySelectorAll('.channel-item, .dm-item').forEach(el => el.classList.remove('active'));
            this.classList.add('active');
            const id = this.dataset.id; // numeric ID from DB
            loadConversation('channel', id);
        });
    });

    // DM clicks
    document.querySelectorAll('.dm-item').forEach(item => {
        item.addEventListener('click', function() {
            document.querySelectorAll('.channel-item, .dm-item').forEach(el => el.classList.remove('active'));
            this.classList.add('active');
            const id = this.dataset.id; // numeric ID
            loadConversation('dm', id);
        });
    });

    // Send message
    const sendBtn = document.getElementById('sendMessageBtn');
    const messageInput = document.getElementById('messageInput');
    sendBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMessage();
    });

    // Infinite scroll
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.addEventListener('scroll', function() {
        if (this.scrollTop === 0 && !isLoading && hasMoreMessages) {
            loadMoreMessages();
        }
    });
});