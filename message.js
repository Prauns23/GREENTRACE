//
// 1. Modal handling
//
function openModal(modalId) {
  document.getElementById(modalId).style.display = "flex";
}
function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none";
}
document.addEventListener("click", function (e) {
  if (e.target.classList.contains("modal-overlay")) {
    e.target.style.display = "none";
  }
});

//
// 2. Chat state
//
let currentConversation = null; // { type: 'channel'|'dm', id: number }
let currentPage = 0; // not used anymore, we use oldestTimestamp
const MESSAGES_PER_PAGE = 6;
let isLoading = false;
let hasMoreMessages = true;
let allMessages = [];
let oldestTimestamp = null;

//
// 3. Helper functions
//
function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase();
}

function getSenderName(msg) {
  return msg.sender_name || "Unknown";
}

function formatDisplayTime(timestamp) {
  if (!timestamp) return "";

  const raw = String(timestamp).trim();
  if (!raw) return "";

  const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/);
  if (match) {
    const hourValue = parseInt(match[4], 10);
    const minuteValue = parseInt(match[5], 10);
    const suffix = hourValue >= 12 ? "PM" : "AM";
    const displayHour = hourValue % 12 || 12;
    return `${String(displayHour).padStart(2, "0")}:${String(minuteValue).padStart(2, "0")} ${suffix}`;
  }

  const parsed = new Date(raw);
  if (!Number.isNaN(parsed.getTime())) {
    return parsed.toLocaleTimeString("en-PH", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: true,
      timeZone: "Asia/Manila",
    });
  }

  return raw;
}

//
// 4. Render messages
//
function renderMessages(messages) {
  const container = document.getElementById("chatMessages");
  container.innerHTML = "";
  messages.forEach((msg) => {
    const isSelf = msg.is_self ? "self" : "";
    const senderName = getSenderName(msg);
    const avatar = getInitials(senderName);
    const div = document.createElement("div");
    div.className = `message-item ${isSelf}`;
    div.innerHTML = `
            <div class="message-avatar">${avatar}</div>
            <div class="message-content">
                <div class="message-sender">${senderName}</div>
                <div class="message-text">${msg.content}</div>
                <div class="message-time">${formatDisplayTime(msg.created_at)}</div>
            </div>
        `;
    container.appendChild(div);
  });
}

//
// 5. Fetch messages from server
//
function fetchMessages(isInitial = false) {
  if (isLoading || !hasMoreMessages || !currentConversation) return;
  isLoading = true;

  const params = new URLSearchParams();
  params.append("conversation_id", currentConversation.id);
  params.append("limit", MESSAGES_PER_PAGE);
  if (!isInitial && oldestTimestamp) {
    params.append("before", oldestTimestamp);
  }

  fetch("actions/get_messages.php?" + params.toString())
    .then((response) => response.json())
    .then((data) => {
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
          const container = document.getElementById("chatMessages");
          container.scrollTop = container.scrollHeight;
        }
        isLoading = false;
      } else {
        alert(data.error || "Failed to load messages");
        isLoading = false;
      }
    })
    .catch((err) => {
      console.error(err);
      alert("Network error");
      isLoading = false;
    });
}

function loadMoreMessages() {
  fetchMessages(false);
}

//
// 6. Load a conversation
//
function loadConversation(type, id) {
  // Convert id to integer (for channels, id is numeric from DB)
  const numericId = parseInt(id, 10);
  if (isNaN(numericId)) {
    alert("Invalid conversation ID");
    return;
  }

  currentConversation = { type, id: numericId };
  currentPage = 0;
  hasMoreMessages = true;
  allMessages = [];
  oldestTimestamp = null;

  document.getElementById("chatPlaceholder").style.display = "none";
  document.getElementById("chatWindow").style.display = "flex";

  // Clear messages
  document.getElementById("chatMessages").innerHTML = "";

  // Update header
  const chatTitle = document.getElementById("chatTitle");
  const chatAddress = document.getElementById("chatAddress");
  const chatRoleBadge = document.getElementById("chatRoleBadge");
  const chatAvatarIcon = document.getElementById("chatAvatarIcon");

  if (type === "channel") {
    // Get channel name from the clicked item
    const item = document.querySelector(`.channel-item[data-id="${id}"]`);
    const displayName = item
      ? item.querySelector(".channel-name").textContent
      : "Channel";
    chatTitle.textContent = displayName;
    chatAddress.textContent = "Public";
    chatRoleBadge.style.display = "none";
    chatAvatarIcon.textContent = "group";
  } else {
    // DM
    const item = document.querySelector(`.dm-item[data-id="${id}"]`);
    if (item) {
      const name = item.querySelector(".dm-name").textContent;
      const address = item.dataset.address || "";
      const role = item.dataset.role || "";
      chatTitle.textContent = name;
      chatAddress.textContent = address;
      if (role) {
        let badgeText = "";
        let badgeClass = "";
        switch (role) {
          case "admin":
            badgeText = "Admin";
            badgeClass = "badge-admin";
            break;
          case "super_admin":
            badgeText = "Super Admin";
            badgeClass = "badge-super-admin";
            break;
          default:
            badgeText = "Volunteer";
            badgeClass = "badge-volunteer";
        }
        chatRoleBadge.textContent = badgeText;
        chatRoleBadge.className = "role-badge " + badgeClass;
        chatRoleBadge.style.display = "inline-block";
      } else {
        chatRoleBadge.style.display = "none";
      }
    } else {
      chatTitle.textContent = "Direct Message";
      chatAddress.textContent = "";
      chatRoleBadge.style.display = "none";
    }
    chatAvatarIcon.textContent = "person";
  }

  // Load first batch
  fetchMessages(true);
}

//
// 7. Send a message
//
function sendMessage() {
  const input = document.getElementById("messageInput");
  const text = input.value.trim();
  const sendBtn = document.getElementById("sendMessageBtn");

  if (!text || !currentConversation || sendBtn.disabled) return;

  sendBtn.disabled = true;

  const formData = new URLSearchParams();
  formData.append("conversation_id", currentConversation.id);
  formData.append("content", text);

  fetch("actions/send_messages.php", {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: formData.toString(),
  })
    .then((response) => response.text())
    .then((text) => {
      let data;
      try {
        data = JSON.parse(text);
      } catch (err) {
        console.error("Invalid JSON response from send_messages.php:", text);
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
          is_self: true,
        });
        renderMessages(allMessages);
        const container = document.getElementById("chatMessages");
        container.scrollTop = container.scrollHeight;
        input.value = "";
        sendBtn.disabled = false;
      } else {
        // Check for rate limiting
        // Check for rate limiting
        if (data.code === "rate_limited" && data.retry_after) {
          const waitSeconds = data.retry_after;
          const toastMsg = `Too many messages. Please wait ${waitSeconds} seconds.`;

          // Show toast
          if (typeof showToast === "function") {
            showToast(toastMsg, 5000, "error");
          } else {
            alert(toastMsg);
          }

          // Disable button (CSS will grey it out and show not-allowed cursor)
          sendBtn.disabled = true;

          // Re-enable after the wait duration
          setTimeout(() => {
            sendBtn.disabled = false;
          }, waitSeconds * 1000);
        } else {
          alert(data.error || "Failed to send message");
          sendBtn.disabled = false;
        }
      }
    })
    .catch((err) => {
      console.error(err);
      alert("Network error");
      sendBtn.disabled = false;
    });
}
//
// 8. Event listeners
//
document.addEventListener("DOMContentLoaded", function () {
  // Channel clicks
  document.querySelectorAll(".channel-item").forEach((item) => {
    item.addEventListener("click", function () {
      document
        .querySelectorAll(".channel-item, .dm-item")
        .forEach((el) => el.classList.remove("active"));
      this.classList.add("active");
      const id = this.dataset.id; // numeric ID from DB
      loadConversation("channel", id);
    });
  });

  // DM clicks
  document.querySelectorAll(".dm-item").forEach((item) => {
    item.addEventListener("click", function () {
      document
        .querySelectorAll(".channel-item, .dm-item")
        .forEach((el) => el.classList.remove("active"));
      this.classList.add("active");
      const id = this.dataset.id; // numeric ID
      loadConversation("dm", id);
    });
  });

  // Send message
  const sendBtn = document.getElementById("sendMessageBtn");
  const messageInput = document.getElementById("messageInput");
  sendBtn.addEventListener("click", sendMessage);
  messageInput.addEventListener("keypress", function (e) {
    if (e.key === "Enter") sendMessage();
  });

  // Infinite scroll
  const chatMessages = document.getElementById("chatMessages");
  chatMessages.addEventListener("scroll", function () {
    if (this.scrollTop === 0 && !isLoading && hasMoreMessages) {
      loadMoreMessages();
    }
  });
});

// 
// 9. Live updates for sidebar
// 

let sidebarPollingInterval = null;

function startSidebarPolling() {
    if (sidebarPollingInterval) {
        clearInterval(sidebarPollingInterval);
    }
    // Poll every 5 seconds
    sidebarPollingInterval = setInterval(fetchSidebarUpdates, 5000);
}

function stopSidebarPolling() {
    if (sidebarPollingInterval) {
        clearInterval(sidebarPollingInterval);
        sidebarPollingInterval = null;
    }
}

function fetchSidebarUpdates() {
    fetch('actions/get_sidebar_updates.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateChannelList(data.channels);
                updateDMList(data.dms);
            }
        })
        .catch(err => console.error('Error fetching sidebar updates:', err));
}

function updateChannelList(channels) {
    const channelItems = document.querySelectorAll('.channel-item');
    channelItems.forEach(item => {
        const id = item.dataset.id;
        const channelData = channels.find(c => c.id == id);
        if (channelData) {
            // Update last message
            const lastMsgSpan = item.querySelector('.channel-last-msg');
            const timeSpan = item.querySelector('.channel-time');
            
            let displayMsg = 'No messages yet';
            if (channelData.last_message && channelData.last_sender_name) {
                const isSelf = (channelData.last_sender_id == currentUserId);
                const senderDisplay = isSelf ? 'You' : channelData.last_sender_name;
                displayMsg = senderDisplay + ': ' + channelData.last_message;
            }
            lastMsgSpan.textContent = displayMsg;
            
            // Update time
            if (channelData.last_message_time) {
                timeSpan.textContent = formatDisplayTime(channelData.last_message_time);
            }
        }
    });
}

function updateDMList(dms) {
    const dmItems = document.querySelectorAll('.dm-item');
    dmItems.forEach(item => {
        const id = item.dataset.id;
        const dmData = dms.find(d => d.id == id);
        if (dmData) {
            // Update last message
            const lastMsgSpan = item.querySelector('.dm-last-msg');
            const timeSpan = item.querySelector('.dm-time');
            
            let displayMsg = 'No messages yet';
            if (dmData.last_message && dmData.last_sender_name) {
                const isSelf = (dmData.last_sender_id == currentUserId);
                const senderDisplay = isSelf ? 'You' : dmData.last_sender_name;
                displayMsg = senderDisplay + ': ' + dmData.last_message;
            }
            lastMsgSpan.textContent = displayMsg;
            
            // Update time
            if (dmData.last_message_time) {
                timeSpan.textContent = formatDisplayTime(dmData.last_message_time);
            }
        }
    });
}

// 
// 10. Start polling when page loads
// 

// Add to the existing DOMContentLoaded listener
document.addEventListener('DOMContentLoaded', function() {
    // ... existing code ...
    
    // Start sidebar polling
    startSidebarPolling();
});

// Stop polling when navigating away (optional)
window.addEventListener('beforeunload', function() {
    stopSidebarPolling();
});