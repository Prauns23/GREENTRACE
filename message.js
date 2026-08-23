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
let currentUserMuted = false;
let isProgrammaticChatScroll = false;

function scrollChatToBottom(container) {
  isProgrammaticChatScroll = true;
  container.scrollTop = container.scrollHeight;
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      isProgrammaticChatScroll = false;
    });
  });
}

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

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => {
    const entities = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" };
    return entities[character];
  });
}

function getSenderName(msg) {
  return msg.sender_name || "Unknown";
}

function renderEmptyState() {
  const container = document.getElementById("chatMessages");
  if (!container) return;

  container.innerHTML = `
    <div class="chat-empty-state">
      <img src="components/icons/in_reallife.png"></img>
      <h3>Start a conversation</h3>
      <p>Plant the first message here and let your ideas grow in a greener community.</p>
    </div>
  `;
}

function formatDisplayTime(timestamp) {
  if (!timestamp) return "";

  const raw = String(timestamp).trim();
  if (!raw) return "";

  const match = raw.match(
    /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/,
  );
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

function toggleReaction(messageId, reaction) {
  const formData = new URLSearchParams();
  formData.append("message_id", messageId);
  formData.append("reaction", reaction);

  fetch("actions/toggle_reaction.php", {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: formData.toString(),
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        // Refresh messages to update UI
        refreshMessages();
      } else {
        showToast(data.error || "Failed to toggle reaction", 3000, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      showToast("Network error", 3000, "error");
    });
}

//
// 4. Render messages
//
function renderMessages(messages) {
  const container = document.getElementById("chatMessages");
  if (!container) return;

  if (!messages || messages.length === 0) {
    renderEmptyState();
    return;
  }

  container.innerHTML = "";

  // Find the latest message sent by the current user
  let latestSelfMessageId = null;
  for (let i = messages.length - 1; i >= 0; i--) {
    if (messages[i].is_self) {
      latestSelfMessageId = messages[i].id;
      break;
    }
  }

  // Track current date to show dividers
  let currentDate = null;

  messages.forEach((msg, index) => {
    // System Message
    if (msg.message_type === "system") {
      const div = document.createElement("div");
      div.className = "message-system";
      div.textContent = msg.content;
      container.appendChild(div);
      return;
    }

    // Regular Message
    const isSelf = msg.is_self ? "self" : "";
    const senderName = getSenderName(msg);
    const avatar = getInitials(senderName);
    const msgDate = new Date(msg.created_at);
    const dateKey = msgDate.toDateString();

    // Check if this is a new day (different from previous message)
    const isNewDay = currentDate !== dateKey;

    // If this is a new day, insert a date divider
    if (isNewDay) {
      const divider = document.createElement("div");
      divider.className = "date-divider";
      const label = document.createElement("span");
      label.textContent = formatDateDivider(msg.created_at);
      divider.appendChild(label);
      container.appendChild(divider);
      currentDate = dateKey;
    }

    // Show read receipt only on the latest self message
    let readReceipt = "";
    if (msg.is_self && msg.id === latestSelfMessageId) {
      const readCount = Number.parseInt(msg.read_count, 10) || 0;
      const isDirectMessage = currentConversation?.type === "dm";
      const readers = (msg.readers || []).map((reader) => reader.user_name);
      const readByTitle = readers.length
        ? `Seen by ${readers.join(", ")}`
        : `Seen by ${readCount} user${readCount === 1 ? "" : "s"}`;
      readReceipt = msg.is_read
        ? `<span class="read-receipt" title="${escapeHtml(readByTitle)}">${isDirectMessage ? "Read" : `Read + ${readCount}`}</span>`
        : `<span class="read-receipt">Sent</span>`;
    }

    const reactions = msg.reactions || [];
    const currentUserReacted = reactions.some(
      (r) => r.user_id === currentUserId && r.reaction === "heart",
    );
    const heartCount = reactions.filter((r) => r.reaction === "heart").length;
    const reactors = reactions
      .filter((reaction) => reaction.reaction === "heart")
      .map((reaction) => reaction.user_name || "Unknown user");
    const reactionTitle = reactors.length
      ? `Reacted by ${reactors.join(", ")}`
      : "Heart reaction";
    const reactionMarkup = heartCount > 0
      ? `<span class="reaction-item ${currentUserReacted ? "user-reacted" : ""}" title="${escapeHtml(reactionTitle)}">
          <img src="components/icons/heart-fill.png" alt="Heart reaction">
          <span class="reaction-count">${heartCount}</span>
        </span>`
      : "";
    const footerMarkup = reactionMarkup || readReceipt
      ? `<div class="message-footer">
          <div class="reactions-display">${reactionMarkup}</div>
          ${readReceipt}
        </div>`
      : "";

    const div = document.createElement("div");
    div.className = `message-item ${isSelf}`;
    div.innerHTML = `
            <div class="message-avatar">${avatar}</div>
        <div class="message-body">
          <div class="message-wrapper">
            <div class="message-content">
              <div class="message-sender">${senderName}</div>
              <div class="message-text">${msg.content}</div>
              <div class="message-time">${new Date(msg.created_at).toLocaleString("en-PH", { hour: "2-digit", minute: "2-digit" })}</div>
            </div>
                </div>
          ${footerMarkup}
            </div>
        `;

    // Reaction trigger (hidden by default, shown when the message is hovered)
    const toolbar = document.createElement("div");
    toolbar.className = "reaction-toolbar";
    toolbar.innerHTML = `
    <button class="reaction-btn reaction-trigger" data-msg-id="${msg.id}" title="React with heart" type="button">
      <img src="components/icons/heart-plus.png" alt="Add heart reaction">
    </button>
`;
    div.appendChild(toolbar);

    container.appendChild(div);
  });

  // If there's an active search, re‑apply it
  if (chatSearchTerm) {
    searchMessages(chatSearchTerm);
  }
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

          if (isInitial || allMessages.length === 0) {
            renderMessages([]);
          } else {
            renderMessages(allMessages);
          }
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
          scrollChatToBottom(container);

          // Mark messages as read after loading
          setTimeout(markMessageAsRead, 500);
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

function updateChatVisibility(isChatVisible) {
  const chatPlaceholder = document.getElementById("chatPlaceholder");
  const chatWindow = document.getElementById("chatWindow");

  if (!chatPlaceholder || !chatWindow) return;

  if (isChatVisible) {
    chatPlaceholder.style.display = "none";
    chatWindow.style.display = "flex";
  } else {
    chatPlaceholder.style.display = "flex";
    chatWindow.style.display = "none";
  }
}

function loadMoreMessages() {
  fetchMessages(false);
}

//
// 6. Load a conversation
//

function loadConversation(type, id) {
  // Hide members panel if open
  hideMembersPanel();

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
  currentUserMuted = false;

  const membersListBtn = document.getElementById("membersListBtn");
  if (membersListBtn) {
    membersListBtn.style.display = type === "channel" ? "block" : "none";
  }

  updateChatVisibility(true);
  document.querySelector(".chat-container")?.classList.add("mobile-chat-open");
  updateInputMuteState();

  // Clear messages
  renderMessages([]);

  // Update header
  const chatTitle = document.getElementById("chatTitle");
  const chatAddress = document.getElementById("chatAddress");
  const chatRoleBadge = document.getElementById("chatRoleBadge");
  const chatAvatarIcon = document.getElementById("chatAvatarIcon");
  const chatMenuBtn = document.getElementById("chatMenuBtn");

  // Get the clicked item to read data attributes
  const selector = type === "channel" ? ".channel-item" : ".dm-item";
  const item = document.querySelector(`${selector}[data-id="${id}"]`);

  if (type === "channel") {
    loadChannelMembers(numericId, true);
    const displayName = item
      ? item.querySelector(".channel-name").textContent
      : "Channel";
    const description = item ? item.dataset.description || "Public" : "Public";
    const category = item ? item.dataset.category || "" : "";

    chatTitle.textContent = displayName;
    chatAddress.textContent = description;
    chatAvatarIcon.textContent = "group";

    if (category) {
      chatRoleBadge.textContent =
        category.charAt(0).toUpperCase() + category.slice(1);
      chatRoleBadge.className = "role-badge badge-category";
      chatRoleBadge.style.display = "inline-block";
    } else {
      chatRoleBadge.style.display = "none";
    }

    // Update archive button text based on current state
    const archived = item ? item.dataset.archived === "1" : false;
    const archiveBtn = document.querySelector(
      '.chat-menu-dropdown button[data-action="archive"]',
    );
    if (archiveBtn) {
      archiveBtn.textContent = archived ? "Unarchive" : "Archive";
    }
  } else {
    // DM
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

      // Update archive button text based on current state
      const archived = item.dataset.archived === "1";
      const archiveBtn = document.querySelector(
        '.chat-menu-dropdown button[data-action="archive"]',
      );
      if (archiveBtn) {
        archiveBtn.textContent = archived ? "Unarchive" : "Archive";
      }
    } else {
      chatTitle.textContent = "Direct Message";
      chatAddress.textContent = "";
      chatRoleBadge.style.display = "none";
    }
    chatAvatarIcon.textContent = "person";
  }

  const leaveBtn = document.querySelector(
    '.chat-menu-dropdown button[data-action="leave"]',
  );

  if (type === "channel") {
    if (membersListBtn) membersListBtn.style.display = "block";
    if (leaveBtn) {
      leaveBtn.textContent = "Leave";
      leaveBtn.dataset.action = "leave";
    }
  } else {
    if (membersListBtn) membersListBtn.style.display = "none";
    if (leaveBtn) {
      leaveBtn.textContent = "Delete";
      leaveBtn.dataset.action = "delete";
    }
  }

  // Load first batch
  fetchMessages(true);

  startMessageRefresh();
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
        scrollChatToBottom(container);
        input.value = "";
        sendBtn.disabled = false;

        // Refresh to update read statuses of other messages
        setTimeout(refreshMessages, 1000);
      } else {
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
  const mobileChatBack = document.getElementById("mobileChatBack");
  mobileChatBack?.addEventListener("click", function () {
    document
      .querySelector(".chat-container")
      ?.classList.remove("mobile-chat-open");
    updateChatVisibility(false);
  });

  // Channel clicks
  document.querySelectorAll(".channel-item").forEach((item) => {
    item.addEventListener("click", function () {
      document
        .querySelectorAll(".channel-item, .dm-item")
        .forEach((el) => el.classList.remove("active"));
      this.classList.add("active");
      const id = this.dataset.id;
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
      const id = this.dataset.id;
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

  // Infinite scroll and temporary search bar visibility
  const chatMessages = document.getElementById("chatMessages");
  const chatSearchBar =
    document.getElementById("chatSearchBar") ||
    document.querySelector(".chat-search-bar");
  let searchBarTimeout = null;
  let searchBarVisible = false;
  let userScrollIntent = false;

  function showChatSearchBar() {
    if (!chatSearchBar) return;
    clearTimeout(searchBarTimeout);
    if (!searchBarVisible) {
      chatSearchBar.style.display = "flex";
      requestAnimationFrame(() => {
        chatSearchBar.classList.add("visible");
        searchBarVisible = true;
      });
    }
    // Auto-hide after 2 seconds only if search input is empty and not focused
    searchBarTimeout = setTimeout(() => {
      const isSearching =
        chatSearchInput &&
        (chatSearchInput.value.trim() !== "" ||
          document.activeElement === chatSearchInput);
      if (!isSearching) {
        chatSearchBar.classList.remove("visible");
        setTimeout(() => {
          if (!chatSearchBar.classList.contains("visible")) {
            chatSearchBar.style.display = "none";
            searchBarVisible = false;
          }
        }, 300);
      }
    }, 2000);
  }

  document.addEventListener("click", function (e) {
    const heartBtn = e.target.closest(".reaction-trigger");
    if (heartBtn) {
      e.preventDefault();
      e.stopPropagation();
      const msgId = heartBtn.dataset.msgId;
      toggleReaction(msgId, "heart");
    }
  });

  if (chatMessages) {
    chatMessages.addEventListener(
      "wheel",
      () => {
        userScrollIntent = true;
      },
      { passive: true },
    );
    chatMessages.addEventListener(
      "touchmove",
      () => {
        userScrollIntent = true;
      },
      { passive: true },
    );
    chatMessages.addEventListener(
      "pointerdown",
      () => {
        userScrollIntent = true;
      },
      { passive: true },
    );
    chatMessages.addEventListener("scroll", function () {
      // Load more messages if at top
      if (this.scrollTop === 0 && !isLoading && hasMoreMessages) {
        loadMoreMessages();
      }
      // Ignore scroll events caused by layout changes or automatic scrolling.
      if (userScrollIntent && !isProgrammaticChatScroll) {
        showChatSearchBar();
        userScrollIntent = false;
      }
    });
  }

  // Start sidebar polling
  startSidebarPolling();

  // Chat menu toggle
  const chatMenuBtn = document.getElementById("chatMenuBtn");
  if (chatMenuBtn) {
    chatMenuBtn.addEventListener("click", toggleChatMenu);
  }

  // Chat search input events

  const chatSearchInput = document.getElementById("chatSearchInput");
  const chatSearchClear = document.getElementById("chatSearchClear");

  if (chatSearchInput) {
    chatSearchInput.addEventListener("input", function () {
      searchMessages(this.value);
    });
  }

  if (chatSearchClear) {
    chatSearchClear.addEventListener("click", function () {
      chatSearchInput.value = "";
      searchMessages("");
      this.style.display = "none";
    });
  }
  // Dropdown action buttons
  document
    .querySelectorAll(".chat-menu-dropdown button[data-action]")
    .forEach((btn) => {
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        const action = this.dataset.action;
        const dropdown = document.getElementById("chatMenuDropdown");
        dropdown.style.display = "none";

        if (!currentConversation) {
          showToast("No conversation selected", 3000, "error");
          return;
        }

        switch (action) {
          case "mute":
            toggleMute();
            break;
          case "leave":
            leaveChannel();
            break;
          case "archive":
            toggleArchive();
            break;
          case "delete":
            deleteConversation();
            break;
          case "add-people":
            if (currentConversation && currentConversation.type === "channel") {
              showMembersPanel();
            } else {
              showToast(
                "Members list is only available for channels",
                3000,
                "error",
              );
            }
            break;
          default:
            showToast(`Action: ${action} (coming soon)`, 3000, "info");
        }
      });
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
  fetch("actions/get_sidebar_updates.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        updateChannelList(data.channels);
        updateDMList(data.dms);
      }
    })
    .catch((err) => console.error("Error fetching sidebar updates:", err));
}

function updateChannelList(channels) {
  const channelItems = document.querySelectorAll(".channel-item");
  channelItems.forEach((item) => {
    const id = item.dataset.id;
    const channelData = channels.find((c) => c.id == id);
    if (channelData) {
      // Update last message
      const lastMsgSpan = item.querySelector(".channel-last-msg");
      const timeSpan = item.querySelector(".channel-time");

      let displayMsg = "No messages yet";
      if (channelData.last_message) {
        if (channelData.last_sender_id == 0) {
          // System message – show only the content
          displayMsg = channelData.last_message;
        } else if (channelData.last_sender_name) {
          const isSelf = channelData.last_sender_id == currentUserId;
          const senderDisplay = isSelf ? "You" : channelData.last_sender_name;
          displayMsg = senderDisplay + ": " + channelData.last_message;
        }
      }
      lastMsgSpan.textContent = displayMsg;

      // Update time
      if (channelData.last_message_time) {
        timeSpan.textContent = formatDisplayTime(channelData.last_message_time);
      }

      // Update unread state and border
      const unreadCount = parseInt(channelData.unread_count) || 0;
      const muted = parseInt(channelData.is_muted) === 1;
      item.dataset.unread = unreadCount;
      item.dataset.muted = muted ? "1" : "0";
      item.classList.toggle("unread", unreadCount > 0 && !muted);

      // Get right section
      const rightSection = item.querySelector(".right-channel-item");
      if (rightSection) {
        //  Unread dot
        const existingDot = rightSection.querySelector(".unread-dot");
        if (existingDot) existingDot.remove();

        if (unreadCount > 0 && !muted) {
          const dot = document.createElement("span");
          dot.className = "unread-dot";
          const muteIcon = rightSection.querySelector(".muted-icon");
          if (muteIcon) {
            rightSection.insertBefore(dot, muteIcon);
          } else {
            rightSection.appendChild(dot);
          }
        }
        // Mute icon
        const existingMuteIcon = rightSection.querySelector(".muted-icon");
        if (muted && !existingMuteIcon) {
          const icon = document.createElement("i");
          icon.className = "fa-regular fa-bell-slash muted-icon";
          const dot = rightSection.querySelector(".unread-dot");
          if (dot) {
            rightSection.insertBefore(icon, dot);
          } else {
            rightSection.prepend(icon);
          }
        } else if (!muted && existingMuteIcon) {
          existingMuteIcon.remove();
        }
      }

      // Toggle muted class on the item itself (for styling)
      item.classList.toggle("muted", muted);
    }

    const channelItems = document.querySelectorAll(".channel-item");
    const emptyChannels = document.getElementById("emptyChannels");
    if (emptyChannels) {
      emptyChannels.style.display =
        channelItems.length === 0 ? "block" : "none";
    }
  });
}

function updateDMList(dms) {
  const dmItems = document.querySelectorAll(".dm-item");
  dmItems.forEach((item) => {
    const id = item.dataset.id;
    const dmData = dms.find((d) => d.id == id);
    if (dmData) {
      // Update last message with sender name
      const lastMsgSpan = item.querySelector(".dm-last-msg");
      const timeSpan = item.querySelector(".dm-time");

      let displayMsg = "No messages yet";
      if (dmData.last_message && dmData.last_sender_name) {
        const isSelf = dmData.last_sender_id == currentUserId;
        const senderDisplay = isSelf ? "You" : dmData.last_sender_name;
        displayMsg = senderDisplay + ": " + dmData.last_message;
      }
      lastMsgSpan.textContent = displayMsg;

      // Update time
      if (dmData.last_message_time) {
        timeSpan.textContent = formatDisplayTime(dmData.last_message_time);
      }

      // Update unread state (but only if not muted)
      const unreadCount = parseInt(dmData.unread_count) || 0;
      const muted = parseInt(dmData.is_muted) === 1;
      item.dataset.unread = unreadCount;
      item.dataset.muted = muted ? "1" : "0";
      item.classList.toggle("unread", unreadCount > 0 && !muted);
      item.classList.toggle("muted", muted);

      const rightSection = item.querySelector(".right-channel-item");
      if (rightSection) {
        // Handle unread dot
        let dot = rightSection.querySelector(".unread-dot");
        if (unreadCount > 0 && !muted) {
          if (!dot) {
            dot = document.createElement("span");
            dot.className = "unread-dot";
            // Insert before mute icon if exists, else append
            const muteIcon = rightSection.querySelector(".muted-icon");
            if (muteIcon) {
              rightSection.insertBefore(dot, muteIcon);
            } else {
              rightSection.appendChild(dot);
            }
          }
        } else {
          if (dot) dot.remove();
        }

        // Handle mute icon
        let muteIcon = rightSection.querySelector(".muted-icon");
        if (muted && !muteIcon) {
          muteIcon = document.createElement("i");
          muteIcon.className = "fa-regular fa-bell-slash muted-icon";
          // Insert before unread dot if exists, else prepend
          const dotEl = rightSection.querySelector(".unread-dot");
          if (dotEl) {
            rightSection.insertBefore(muteIcon, dotEl);
          } else {
            rightSection.prepend(muteIcon);
          }
        } else if (!muted && muteIcon) {
          muteIcon.remove();
        }
      }

      const dmItems = document.querySelectorAll(".dm-item");
      const emptyDms = document.getElementById("emptyDms");
      if (emptyDms) {
        emptyDms.style.display = dmItems.length === 0 ? "block" : "none";
      }
    }
  });
}

function updateSidebarUnread(conversationId) {
  if (!conversationId) return;
  const item = document.querySelector(
    `.channel-item[data-id="${conversationId}"], .dm-item[data-id="${conversationId}"]`,
  );
  if (!item) return;
  // set unread to 0
  item.dataset.unread = 0;
  item.classList.remove("unread");
  const rightSection = item.querySelector(".right-channel-item");
  if (rightSection) {
    const dot = rightSection.querySelector(".unread-dot");
    if (dot) dot.remove();
  }
}

//
// 10. Start polling when page loads
//

// Add to the existing DOMContentLoaded listener
document.addEventListener("DOMContentLoaded", function () {
  // ... existing code ...

  // Start sidebar polling
  startSidebarPolling();
});

// Stop polling when navigating away (optional)
window.addEventListener("beforeunload", function () {
  stopSidebarPolling();
  stopMessageRefresh();
});

//
// 11. Read Receipts
//

let lastReadMessageId = null;

function markMessageAsRead() {
  if (!currentConversation || !allMessages.length) return;

  // Find all unread messages (not from current user, not system)
  const unreadMessages = allMessages.filter(
    (msg) => !msg.is_self && !msg.is_read && msg.message_type !== "system",
  );

  // If none unread, still ensure sidebar is up‑to‑date
  if (!unreadMessages.length) {
    updateSidebarUnread(currentConversation.id);
    // also update global chat badge
    if (typeof window.updateChatBadgeCount === "function") {
      window.updateChatBadgeCount();
    }
    return;
  }

  // Get all unread message IDs
  const unreadIds = unreadMessages.map((msg) => msg.id);

  fetch("actions/mark_message_read.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: `message_ids=${JSON.stringify(unreadIds)}&conversation_id=${currentConversation.id}&mark_all=1`,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Update local state
        unreadMessages.forEach((msg) => {
          const found = allMessages.find((m) => m.id === msg.id);
          if (found) found.is_read = true;
        });
        renderMessages(allMessages);

        // Instantly remove the dot from the sidebar
        updateSidebarUnread(currentConversation.id);

        // Update the global chat badge (header)
        if (typeof window.updateChatBadgeCount === "function") {
          window.updateChatBadgeCount();
        }
      }
    })
    .catch((err) => console.error("Error marking messages as read:", err));
}

//
// Search/Filter Function
//

function filterSidebar(term) {
  const query = term.toLowerCase().trim();
  const channelItems = document.querySelectorAll(".channel-item");
  const dmItems = document.querySelectorAll(".dm-item");

  // Filter channels
  channelItems.forEach((item) => {
    const name =
      item.querySelector(".channel-name")?.textContent.toLowerCase() || "";
    const lastMsg =
      item.querySelector(".channel-last-msg")?.textContent.toLowerCase() || "";
    const matches = name.includes(query) || lastMsg.includes(query);
    item.style.display = matches ? "" : "none";
  });

  // Filter DMs
  dmItems.forEach((item) => {
    const name =
      item.querySelector(".dm-name")?.textContent.toLowerCase() || "";
    const lastMsg =
      item.querySelector(".dm-last-msg")?.textContent.toLowerCase() || "";
    const matches = name.includes(query) || lastMsg.includes(query);
    item.style.display = matches ? "" : "none";
  });

  // Update empty states based on visibility
  const visibleChannels = document.querySelectorAll(
    '.channel-item:not([style*="display: none"])',
  );
  const visibleDms = document.querySelectorAll(
    '.dm-item:not([style*="display: none"])',
  );
  const emptyChannels = document.getElementById("emptyChannels");
  const emptyDms = document.getElementById("emptyDms");

  if (emptyChannels) {
    emptyChannels.style.display =
      visibleChannels.length === 0 ? "block" : "none";
  }
  if (emptyDms) {
    emptyDms.style.display = visibleDms.length === 0 ? "block" : "none";
  }
}

//
// 12. Message refresh for read receipts
//

let messageRefreshInterval = null;

function refreshMessages() {
  if (!currentConversation) return;

  const params = new URLSearchParams();
  params.append("conversation_id", currentConversation.id);
  params.append("limit", MESSAGES_PER_PAGE);

  fetch("actions/get_messages.php?" + params.toString())
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.messages.length > 0) {
        const newMessages = data.messages;

        // Merge and deduplicate messages
        const mergedMessages = mergeMessages(allMessages, newMessages);
        allMessages = mergedMessages;

        // Update oldestTimestamp
        if (newMessages.length > 0) {
          oldestTimestamp = newMessages[0].created_at;
        }

        renderMessages(allMessages);
      }
    })
    .catch((err) => console.error("Error refreshing messages:", err));
}

function mergeMessages(existing, newMessages) {
  const existingIds = new Set(existing.map((m) => m.id));
  const merged = [...existing];

  // Add new messages that don't exist
  for (const msg of newMessages) {
    if (!existingIds.has(msg.id)) {
      merged.push(msg);
    } else {
      // Update existing message (for read status changes)
      const index = merged.findIndex((m) => m.id === msg.id);
      if (index !== -1) {
        merged[index] = msg;
      }
    }
  }

  // Sort by created_at ascending
  merged.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));

  return merged;
}

function startMessageRefresh() {
  if (messageRefreshInterval) {
    clearInterval(messageRefreshInterval);
  }
  // Refresh messages every 10 seconds to update read receipts

  messageRefreshInterval = setInterval(() => {
    if (currentConversation && !isLoading) {
      refreshMessages();
    }
  }, 10000);
}

function stopMessageRefresh() {
  if (messageRefreshInterval) {
    clearInterval(messageRefreshInterval);
    messageRefreshInterval = null;
  }
}

//
// 13. Chat dropdown menu
//

function toggleChatMenu(event) {
  event.stopPropagation();
  const dropdown = document.getElementById("chatMenuDropdown");
  if (!dropdown) return;
  // Toggle visibility
  dropdown.style.display = dropdown.style.display === "none" ? "block" : "none";
}

// Close dropdown when clicking outside
document.addEventListener("click", function (e) {
  const wrapper = document.querySelector(".chat-menu-wrapper");
  const dropdown = document.getElementById("chatMenuDropdown");
  if (!wrapper || !dropdown) return;
  if (!wrapper.contains(e.target)) {
    dropdown.style.display = "none";
  }
});

// Close dropdwon on escape key

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    const dropdown = document.getElementById("chatMenuDropdown");
    if (dropdown) dropdown.style.display = "none";
  }
});

//
// 14. Date Provider
//

function formatDateDivider(dateString) {
  const date = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  // Reset time for comparison
  const todayStr = today.toDateString();
  const yesterdayStr = yesterday.toDateString();
  const dateStr = date.toDateString();

  if (dateStr === todayStr) {
    return "Today";
  } else if (dateStr === yesterdayStr) {
    return "Yesterday";
  } else {
    // Format as "Monday, July 13th"
    const options = { weekday: "long", month: "long", day: "numeric" };
    return date.toLocaleDateString("en-PH", options);
  }
}

//
// .15 Action buttons for dropdown
//

function toggleMute() {
  if (!currentConversation) return;
  const formData = new URLSearchParams();
  formData.append("conversation_id", currentConversation.id);
  fetch("actions/toggle_mute.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        showToast(data.message, 3000, "success");

        // Update dropdown button
        const muteBtn = document.querySelector(
          '.chat-menu-dropdown button[data-action="mute"]',
        );
        if (muteBtn) {
          muteBtn.textContent = data.muted ? "Unmute" : "Mute";
        }

        // Update the sidebar item
        const selector =
          currentConversation.type === "channel" ? ".channel-item" : ".dm-item";
        const item = document.querySelector(
          `${selector}[data-id="${currentConversation.id}"]`,
        );
        if (item) {
          item.classList.toggle("muted", data.muted);
          item.dataset.muted = data.muted ? "1" : "0";

          const rightSection = item.querySelector(".right-channel-item");
          if (rightSection) {
            // Handle mute icon
            let muteIcon = rightSection.querySelector(".muted-icon");
            if (data.muted && !muteIcon) {
              const icon = document.createElement("i");
              icon.className = "fa-regular fa-bell-slash muted-icon";
              // Prepend before the dot if exists
              const dot = rightSection.querySelector(".unread-dot");
              if (dot) {
                rightSection.insertBefore(icon, dot);
              } else {
                rightSection.prepend(icon);
              }
            } else if (!data.muted && muteIcon) {
              muteIcon.remove();
            }

            // Handle unread dot: show if unread AND not muted
            const unreadCount = parseInt(item.dataset.unread) || 0;
            let dot = rightSection.querySelector(".unread-dot");
            if (unreadCount > 0 && !data.muted) {
              if (!dot) {
                dot = document.createElement("span");
                dot.className = "unread-dot";
                rightSection.appendChild(dot);
              }
            } else {
              if (dot) dot.remove();
            }
          }
        }
      } else {
        showToast(data.error || "Error", 3000, "error");
      }
    })
    .catch((err) => console.error(err));
}
function leaveChannel() {
  if (!confirm("Are you sure you want to leave this channel?")) return;
  const formData = new URLSearchParams();
  formData.append("conversation_id", currentConversation.id);
  fetch("actions/leave_conversation.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        showToast(data.message, 3000, "success");
        const item = document.querySelector(
          `.channel-item[data-id="${currentConversation.id}"]`,
        );
        item?.remove();
        document.querySelector(".chat-container")?.classList.remove("mobile-chat-open");
        updateChatVisibility(false);
        currentConversation = null;
        stopMessageRefresh();
        fetchSidebarUpdates();
      } else {
        showToast(data.error || "Error", 3000, "error");
      }
    })
    .catch((err) => console.error(err));
}

function toggleArchive() {
  const formData = new URLSearchParams();
  formData.append("conversation_id", currentConversation.id);
  fetch("actions/toggle_archive.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        showToast(data.message, 3000, "success");
        const archiveBtn = document.querySelector(
          '.chat-menu-dropdown button[data-action="archive"]',
        );
        if (archiveBtn) {
          archiveBtn.textContent = data.archived ? "Unarchive" : "Archive";
        }
        // Reload to refresh sidebar
        location.reload();
      } else {
        showToast(data.error || "Error", 3000, "error");
      }
    })
    .catch((err) => console.error(err));
}

function deleteConversation() {
  if (!currentConversation || currentConversation.type !== "dm") return;
  if (!confirm("Delete this conversation for both users?")) return;

  const formData = new URLSearchParams();
  formData.append("conversation_id", currentConversation.id);
  fetch("actions/delete_conversation.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        showToast(data.message, 3000, "success");
        location.reload();
      } else {
        showToast(data.error || "Error", 3000, "error");
      }
    })
    .catch((err) => console.error(err));
}

//
// 16. Members Panel
//

function showMembersPanel() {
  const chatMessages = document.getElementById("chatMessages");
  const membersPanel = document.getElementById("membersPanel");
  const chatMenuBtn = document.getElementById("chatMenuBtn");
  const chatMenuDropdown = document.getElementById("chatMenuDropdown");
  const chatInput = document.querySelector(".chat-input");

  if (chatMessages && membersPanel) {
    chatMessages.style.display = "none";
    membersPanel.style.display = "flex";
    console.log("Panel displayed, now loading members...");

    // Load members if we have a conversation
    if (currentConversation && currentConversation.id) {
      loadChannelMembers(currentConversation.id);
    } else {
      console.warn("No conversation ID to load members");
    }
  }

  // Hide chat input
  if (chatInput) {
    chatInput.style.display = "none";
  }

  // Change the menu button to a close (X) icon
  if (chatMenuBtn) {
    chatMenuBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    // Remove existing listeners by replacing with new one
    chatMenuBtn.replaceWith(chatMenuBtn.cloneNode(true));
    const newBtn = document.getElementById("chatMenuBtn");
    newBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      hideMembersPanel();
    });
  }

  // Close dropdown if open
  if (chatMenuDropdown) {
    chatMenuDropdown.style.display = "none";
  }
}

function hideMembersPanel() {
  const chatMessages = document.getElementById("chatMessages");
  const membersPanel = document.getElementById("membersPanel");
  const chatMenuBtn = document.getElementById("chatMenuBtn");
  const chatInput = document.querySelector(".chat-input");

  if (chatMessages && membersPanel) {
    membersPanel.style.display = "none";
    chatMessages.style.display = "flex";
  }

  // Show chat input
  if (chatInput) {
    chatInput.style.display = "flex";
  }

  // Restore the menu button to ellipsis
  if (chatMenuBtn) {
    chatMenuBtn.innerHTML = '<i class="fa-solid fa-ellipsis-vertical"></i>';
    // Remove existing listeners by replacing with new one
    chatMenuBtn.replaceWith(chatMenuBtn.cloneNode(true));
    const newBtn = document.getElementById("chatMenuBtn");
    newBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      toggleChatMenu(e);
    });
  }
}

function toggleMembersDropdown(btn) {
  event.stopPropagation();
  const wrapper = btn.closest(".members-menu-wrapper");
  const dropdown = wrapper.querySelector(".members-dropdown");

  // Close other open dropdowns
  document.querySelectorAll(".members-dropdown").forEach((d) => {
    if (d !== dropdown) d.style.display = "none";
  });

  dropdown.style.display = dropdown.style.display === "none" ? "block" : "none";
}

// Close dropdowns when clicking outside
document.addEventListener("click", function (e) {
  document.querySelectorAll(".members-dropdown").forEach((d) => {
    if (!d.closest(".members-menu-wrapper").contains(e.target)) {
      d.style.display = "none";
    }
  });
});

//
// 17. Fetch and render channel members
//

function loadChannelMembers(conversationId) {
  console.log("loadChannelMembers called with ID:", conversationId);
  const membersListContainer = document.getElementById("membersListContainer");
  const membersCount = document.getElementById("membersCount");

  if (!membersListContainer) {
    console.error("membersListContainer element not found!");
    return;
  }

  // Show loading state
  membersListContainer.innerHTML = `
    <div style="text-align: center; padding: 40px; color: #a0aec0;">
      <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
      <p style="margin-top: 12px;">Loading members...</p>
    </div>
  `;

  fetch("actions/get_channel_members.php?conversation_id=" + conversationId)
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        membersListContainer.innerHTML = `
          <div style="text-align: center; padding: 40px; color: #e53e3e;">
            <i class="fas fa-exclamation-circle" style="font-size: 24px;"></i>
            <p style="margin-top: 12px;">${data.error}</p>
          </div>
        `;
        return;
      }

      // Update member count
      if (membersCount) {
        membersCount.textContent = data.total + " USERS";
      }

      // ----- Update current user's mute state -----
      const currentUserData = data.members.find((m) => m.is_current_user);
      if (currentUserData) {
        currentUserMuted = currentUserData.is_muted_by_admin === 1;
        updateInputMuteState();
      }

      // ----- Render members -----
      let html = "";
      data.members.forEach((member) => {
        const isCurrentUser = member.is_current_user;
        const isCreator = member.role === "owner";
        const isMutedByAdmin = member.is_muted_by_admin === 1;
        const muteButtonText = isMutedByAdmin ? "Unmute" : "Mute";
        const mutedClass = isMutedByAdmin ? "muted-member" : "";
        const mutedLabel = isMutedByAdmin
          ? ' <span class="muted-label">(Muted)</span>'
          : "";
        // 👇 Choose avatar icon based on mute status
        const avatarIcon = isMutedByAdmin ? "person_off" : "person";

        // Build "Added by" text
        let addedByText = "";
        if (isCreator) {
          addedByText = `<span class="creator-text">Group creator : ${member.email}</span>`;
        } else {
          const adderName =
            member.added_by_name || data.creator_name || "System";
          addedByText = `Added by ${adderName} : ${member.email}`;
        }

        // Determine dropdown actions based on role and current user
        let dropdownButtons = "";
        if (isCurrentUser) {
          // Current user's own card – only Leave
          dropdownButtons = `
            <button data-action="leave" data-user-id="${member.user_id}">Leave</button>
          `;
        } else if (
          data.current_user_role === "owner" ||
          data.current_user_role === "admin"
        ) {
          // Owner/Admin can manage others
          dropdownButtons = `
            <button data-action="add-contact" data-user-id="${member.user_id}">Add as contact</button>
            <button data-action="kick" data-user-id="${member.user_id}">Kick</button>
            <button data-action="mute-member" data-user-id="${member.user_id}">${muteButtonText}</button>
          `;
          if (data.current_user_role === "owner" && member.role !== "owner") {
            dropdownButtons += `
              <button data-action="make-admin" data-user-id="${member.user_id}">Make Admin</button>
            `;
          }
        } else {
          // Regular member – limited options
          dropdownButtons = `
            <button data-action="add-contact" data-user-id="${member.user_id}">Add as contact</button>
          `;
        }

        html += `
          <div class="member-card ${mutedClass}" data-user-id="${member.user_id}" data-role="${member.role}" data-name="${member.full_name}">
            <div class="member-avatar">
              <span class="material-symbols-rounded">${avatarIcon}</span>
            </div>
            <div class="member-info">
              <div class="member-name-row">
                <span class="member-name">${member.full_name} ${isCurrentUser ? "(You)" : ""}${mutedLabel}</span>
              </div>
              <span class="member-added-by">${addedByText}</span>
            </div>
            <div class="member-actions">
              <div class="members-menu-wrapper">
                <button class="members-menu-btn" onclick="toggleMembersDropdown(this)" data-name="${member.full_name}">
                  <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
                <div class="members-dropdown" style="display: none;">
                  ${dropdownButtons}
                </div>
              </div>
            </div>
          </div>
        `;
      });

      membersListContainer.innerHTML = html;

      // Attach dropdown button click handlers
      document
        .querySelectorAll(".members-dropdown button[data-action]")
        .forEach((btn) => {
          btn.addEventListener("click", function (e) {
            e.stopPropagation();
            const action = this.dataset.action;
            const userId = parseInt(this.dataset.userId);
            const memberCard = this.closest(".member-card");
            const memberName = memberCard ? memberCard.dataset.name || "" : "";
            const dropdown = this.closest(".members-dropdown");
            if (dropdown) dropdown.style.display = "none";

            handleMemberAction(action, userId, conversationId, memberName);
          });
        });
    })
    .catch((err) => {
      console.error("Error loading members:", err);
      membersListContainer.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #e53e3e;">
          <i class="fas fa-exclamation-circle" style="font-size: 24px;"></i>
          <p style="margin-top: 12px;">Failed to load members</p>
        </div>
      `;
    });
}

//
// 18. Handle member actions
//

function handleMemberAction(action, userId, conversationId, memberName) {
  switch (action) {
    case "add-contact":
      // Add as contact
      if (confirm(`Add ${memberName || "this user"} as a contact?`)) {
        addContact(userId);
      }
      break;
    case "kick":
      if (
        confirm(
          `Are you sure want to kick ${memberName || "this user"} from this channel?`,
        )
      ) {
        // Kick user
        kickMember(userId, conversationId, memberName);
      }
      break;
    case "mute-member":
      // Mute user in this channel
      muteMember(userId, conversationId, memberName);
      break;
    case "make-admin":
      if (confirm(`Make ${memberName || "this user"} an admin?`)) {
        showToast("Make admin feature coming soon", 3000, "info");
      }
      break;
    case "leave":
      if (confirm("Are you sure you want to leave this channel?")) {
        leaveChannel();
      }
      break;
    default:
      showToast("Unknown action", 3000, "error");
  }
}

// Add contact function for member dropdown ellipsis

function addContact(userId) {
  if (!userId) return;

  const payload = { recipient_ids: [userId] };

  fetch("actions/create_dm.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
  })
    .then((response) => response.json())
    .then((data) => {
      console.log("create_dm response:", data);

      if (data.success) {
        let msg = "";
        let type = "success";
        if (data.message === "Conversation already exists") {
          msg = "You already have a conversation with this user.";
          type = "info";
        } else if (data.message === "Rejoined conversation") {
          msg = "Conversation restored. You can now message this user.";
        } else {
          msg = "Conversation created! You can now message this user.";
        }
        showToast(msg, 3000, type);
        fetchSidebarUpdates();
      } else {
        showToast(data.error || "Failed to add contact.", 3000, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      showToast("Network error. Please try again.", 3000, "error");
    });
}

// Kick function

function kickMember(userId, conversationId, memberName) {
  const formData = new URLSearchParams();
  formData.append("user_id", userId);
  formData.append("conversation_id", conversationId);

  fetch("actions/kick_member.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: formData.toString(),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showToast(
          `${memberName || "User"} has been kicked from the channel.`,
          3000,
          "success",
        );
        // Refresh the members list
        loadChannelMembers(conversationId);
        fetchSidebarUpdates();
        // Remove the current user's chat immediately after being kicked.
        if (userId == currentUserId) {
          document.querySelector(`.channel-item[data-id="${conversationId}"]`)?.remove();
          document.querySelector(".chat-container")?.classList.remove("mobile-chat-open");
          updateChatVisibility(false);
          currentConversation = null;
          stopMessageRefresh();
        } else {
          refreshMessages();
        }
      } else {
        showToast(data.error || "Failed to kick user.", 3000, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      showToast("Network error. Please try again", 3000, "error");
    });
}

// Mute member function

function muteMember(userId, conversationId, memberName) {
  const formData = new URLSearchParams();
  formData.append("user_id", userId);
  formData.append("conversation_id", conversationId);

  fetch("actions/mute_member.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: formData.toString(),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const action = data.mute ? "muted" : "unmuted";
        showToast(
          `${memberName || "User"} has been ${action}.`,
          3000,
          "success",
        );
        // Refresh the members list to update the dropdown button text
        loadChannelMembers(conversationId);
      } else {
        showToast(data.error || "Failed to mute user.", 3000, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      showToast("Network error. Please try again.", 3000, "error");
    });
}

function updateInputMuteState() {
  const input = document.getElementById("messageInput");
  if (!input) return;

  input.disabled = currentUserMuted;
  input.placeholder = currentUserMuted
    ? "You are muted."
    : "Type your message...";
  input.style.cursor = currentUserMuted ? "not-allowed" : "text";

  // Hide/show the send button
  const sendBtn = document.getElementById("sendMessageBtn");
  if (sendBtn) {
    sendBtn.style.display = currentUserMuted ? "none" : "flex";
  }
}

//
// 19. Add Channel Members Modal
//

function showAddVolunteerModal() {
  if (!currentConversation || currentConversation.type !== "channel") {
    if (typeof showToast === "function") {
      showToast("Please select a channel first.", 3000, "error");
    } else {
      alert("Please select a channel first.");
    }
    return;
  }
  // Call the parent function to show the modal
  if (
    window.parent &&
    typeof window.parent.showAddChannelMembersModal === "function"
  ) {
    window.parent.showAddChannelMembersModal(currentConversation.id);
  } else if (typeof window.showAddChannelMembersModal === "function") {
    // Fallback if called directly in the same window (unlikely)
    window.showAddChannelMembersModal(currentConversation.id);
  } else {
    alert("Modal function not available.");
  }
}

//
// 20. Chat Search Function
//

let chatSearchTerm = "";
let chatSearchMatches = [];

function searchMessages(term) {
  const container = document.getElementById("chatMessages");
  if (!container) return;

  chatSearchTerm = term.trim().toLowerCase();
  chatSearchMatches = [];

  if (!chatSearchTerm) {
    // Remove all highlight spans and show all messages
    document
      .querySelectorAll(".message-item, .message-system")
      .forEach((el) => {
        el.style.display = "";
        // Remove .highlight spans
        el.querySelectorAll(".highlight").forEach((span) => {
          const parent = span.parentNode;
          parent.replaceChild(document.createTextNode(span.textContent), span);
          parent.normalize();
        });
      });
    document.getElementById("chatSearchClear").style.display = "none";
    return;
  }

  const messageItems = container.querySelectorAll(
    ".message-item, .message-system",
  );
  let matchCount = 0;

  messageItems.forEach((el) => {
    const text = el.textContent.toLowerCase();
    if (text.includes(chatSearchTerm)) {
      el.style.display = "";
      highlightMatches(el);
      matchCount++;
    } else {
      el.style.display = "none";
    }
  });

  // Show/hide clear button
  const clearBtn = document.getElementById("chatSearchClear");
  if (clearBtn) {
    clearBtn.style.display = matchCount > 0 ? "block" : "none";
  }

  // If no matches, show a message
  const noResults = container.querySelector(".search-no-results");
  if (matchCount === 0 && chatSearchTerm) {
    if (!noResults) {
      const div = document.createElement("div");
      div.className = "search-no-results";
      div.textContent = 'No messages found for "' + chatSearchTerm + '"';
      div.style.textAlign = "center";
      div.style.padding = "40px 20px";
      div.style.color = "#a0aec0";
      container.appendChild(div);
    }
  } else if (noResults) {
    noResults.remove();
  }
}

function highlightMatches(el) {
  // Remove existing highlights
  el.querySelectorAll(".highlight").forEach((span) => {
    const parent = span.parentNode;
    parent.replaceChild(document.createTextNode(span.textContent), span);
    parent.normalize();
  });

  if (!chatSearchTerm) return;

  const walker = document.createTreeWalker(
    el,
    NodeFilter.SHOW_TEXT,
    null,
    false,
  );
  const nodes = [];
  while (walker.nextNode()) {
    const node = walker.currentNode;
    if (node.parentElement && !node.parentElement.closest(".highlight")) {
      nodes.push(node);
    }
  }

  nodes.forEach((node) => {
    const text = node.textContent;
    const lowerText = text.toLowerCase();
    let index = lowerText.indexOf(chatSearchTerm);
    if (index === -1) return;

    const fragment = document.createDocumentFragment();
    let lastIndex = 0;
    while (index !== -1) {
      // Text before match
      if (index > lastIndex) {
        fragment.appendChild(
          document.createTextNode(text.substring(lastIndex, index)),
        );
      }
      // Highlighted match
      const span = document.createElement("span");
      span.className = "highlight";
      span.textContent = text.substring(index, index + chatSearchTerm.length);
      fragment.appendChild(span);

      lastIndex = index + chatSearchTerm.length;
      index = lowerText.indexOf(chatSearchTerm, lastIndex);
    }
    // Remaining text
    if (lastIndex < text.length) {
      fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
    }
    node.parentNode.replaceChild(fragment, node);
  });
}
