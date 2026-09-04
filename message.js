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
const CHAT_SKELETON_MIN_DURATION_MS = 900; // Increase this to inspect the skeleton longer.
let isLoading = false;
let hasMoreMessages = true;
let allMessages = [];
let oldestTimestamp = null;
let currentUserMuted = false;
let isProgrammaticChatScroll = false;
// Global message actions dropdown
let currentMessageActionTarget = null;
// Current user's role in the conversation
let currentUserRoleInConversation = null;
let editingMessageId = null;
let activeConversationFilter = "chats";
let pinnedMessagesRequestController = null;
let pinnedModalPreviousFocus = null;
let chatSocket = null;
let chatSocketConnected = false;
let chatSocketShouldReconnect = true;
let chatSocketReconnectTimer = null;
let chatSocketReconnectAttempt = 0;
let chatSocketHeartbeat = null;
let realtimeSidebarRefreshTimer = null;
let realtimeMessageRefreshTimer = null;
let chatSkeletonShownAt = 0;
let chatSkeletonLoadId = 0;

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
    const entities = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
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

//
// Message Actions (Pin, Edit, Unsend)
//

function setMessageToolbarState(messageItem) {
  document.querySelectorAll(".message-item.dropdown-open").forEach((item) => {
    if (item !== messageItem) item.classList.remove("dropdown-open");
  });

  if (messageItem) {
    messageItem.classList.add("dropdown-open");
  }
}

function clearMessageToolbarState() {
  document.querySelectorAll(".message-item.dropdown-open").forEach((item) => {
    item.classList.remove("dropdown-open");
  });
}

function isMessageInChatViewport(messageItem) {
  const chatMessages = document.getElementById("chatMessages");
  if (!messageItem || !chatMessages) return false;

  const messageRect = messageItem.getBoundingClientRect();
  const chatRect = chatMessages.getBoundingClientRect();

  return (
    messageRect.bottom > chatRect.top &&
    messageRect.top < chatRect.bottom &&
    messageRect.right > chatRect.left &&
    messageRect.left < chatRect.right
  );
}

function syncDropdownToButton() {
  const dropdown = document.getElementById("globalMessageActionsDropdown");
  if (!dropdown || dropdown.style.display !== "block") return;

  const activeId = dropdown.dataset.msgId;
  if (!activeId) return;

  const button = document.querySelector(
    `.more-tool[data-msg-id="${CSS.escape(activeId)}"]`,
  );
  if (!button) {
    hideMessageActions();
    return;
  }

  const messageItem = button.closest(".message-item");
  if (!messageItem || !isMessageInChatViewport(messageItem)) {
    hideMessageActions();
    return;
  }

  const rect = button.getBoundingClientRect();
  dropdown.style.left = rect.left + rect.width / 2 - dropdown.offsetWidth / 2 + "px";
  dropdown.style.top = rect.bottom + 4 + "px";
}

function shouldKeepToolbarVisible(target, relatedTarget) {
  if (!target) return false;

  const dropdown = document.getElementById("globalMessageActionsDropdown");
  if (!dropdown || dropdown.style.display !== "block") return false;

  if (relatedTarget && (dropdown.contains(relatedTarget) || target.contains(relatedTarget))) {
    return true;
  }

  if (relatedTarget && relatedTarget.closest(".more-tool")) {
    return true;
  }

  return false;
}

function showMessageActions(button, msgId) {
  const dropdown = document.getElementById("globalMessageActionsDropdown");
  if (!dropdown) return;

  // Find the message object
  const msg = allMessages.find((m) => String(m.id) === String(msgId));
  if (!msg) return;

  // Determine which actions to show
  const isOwn = msg.is_self;
  const canEdit = isOwn && Number(msg.can_edit) === 1;
  const canUnsend =
    isOwn && Number(msg.archived) === 0 && msg.message_type === "text";
  const canPin = Number(msg.can_pin) === 1;
  const canRemove = true;

  let buttonsHtml = "";

  if (canEdit) {
    buttonsHtml += `
      <button data-action="edit" data-msg-id="${msgId}">
        Edit
      </button>
    `;
  }

  if (canUnsend) {
    buttonsHtml += `
      <button data-action="unsend" data-msg-id="${msgId}">
        Unsend
      </button>
    `;
  }

  if (canPin) {
    buttonsHtml += `
      <button data-action="pin" data-msg-id="${msgId}">
        ${Number(msg.is_pinned) === 1 ? "Unpin" : "Pin"}
      </button>
    `;
  }

  if (canRemove) {
    buttonsHtml += `
      <button data-action="remove" data-msg-id="${msgId}">
        Remove
      </button>
    `;
  }

  if (!buttonsHtml) {
    dropdown.style.display = "none";
    clearMessageToolbarState();
    return;
  }

  dropdown.innerHTML = buttonsHtml;
  dropdown.dataset.msgId = String(msgId);

  // Position dropdown near the clicked button (use button, not event)
  const rect = button.getBoundingClientRect();
  dropdown.style.left =
    rect.left + rect.width / 2 - dropdown.offsetWidth / 2 + "px";
  dropdown.style.top = rect.bottom + 4 + "px";
  dropdown.style.display = "block";

  const messageItem = button.closest(".message-item");
  setMessageToolbarState(messageItem);
  if (messageItem && !isMessageInChatViewport(messageItem)) {
    hideMessageActions();
    return;
  }
  currentMessageActionTarget = msgId;

  dropdown.querySelectorAll("button[data-action]").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      const action = this.dataset.action;
      const msgId = this.dataset.msgId;
      dropdown.style.display = "none";
      handleMessageAction(action, msgId);
    });
  });
}

function hideMessageActions() {
  const dropdown = document.getElementById("globalMessageActionsDropdown");
  if (dropdown) dropdown.style.display = "none";
  clearMessageToolbarState();
  currentMessageActionTarget = null;
}

function handleMessageAction(action, msgId) {
  switch (action) {
    case "edit":
      startEditMessage(msgId);
      break;
    case "unsend":
      unsendMessage(msgId);
      break;
    case "pin":
      togglePinMessage(msgId);
      break;
    case "remove":
      removeMessage(msgId);
      break;
    default:
      showToast("Unknown action", 3000, "error");
  }
}

function startEditMessage(msgId) {
  const message = allMessages.find(
    (item) => String(item.id) === String(msgId),
  );

  if (!message || Number(message.can_edit) !== 1) {
    showToast("This message can no longer be edited.", 3000, "error");
    return;
  }

  cancelReply();
  editingMessageId = message.id;

  const preview = document.getElementById("replyPreview");
  const label = document.getElementById("composerPreviewLabel");
  const previewName = document.getElementById("replyPreviewName");
  const previewMessage = document.getElementById("replyPreviewMessage");
  const input = document.getElementById("messageInput");

  label.textContent = "Editing message";
  previewName.textContent = "";
  previewMessage.textContent = message.content;
  preview.style.display = "flex";

  input.value = message.content;
  input.focus();
  input.setSelectionRange(input.value.length, input.value.length);
}

function unsendMessage(msgId) {
  if (!confirm("Unsend this message for everyone?")) return;

  const formData = new URLSearchParams();
  formData.append("message_id", msgId);

  fetch("actions/unsend_message.php", {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: formData.toString(),
  })
    .then((response) => response.json())
    .then((data) => {
      if (!data.success) {
        showToast(data.error || "Failed to unsend message", 3000, "error");
        return;
      }

      allMessages = allMessages.map((message) =>
        String(message.id) === String(msgId)
          ? { ...message, ...data.message }
          : message,
      );

      if (String(editingMessageId) === String(msgId)) {
        cancelReply();
      }

      renderMessages(allMessages);
      fetchSidebarUpdates();
      showToast("Message unsent", 3000, "success");
    })
    .catch((error) => {
      console.error(error);
      showToast("Network error", 3000, "error");
    });
}

function removeMessage(msgId) {
  if (!confirm("Remove this message from your view?")) return;

  const formData = new URLSearchParams();
  formData.append("message_id", msgId);

  fetch("actions/remove_message.php", {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: formData.toString(),
  })
    .then((response) => response.json())
    .then((data) => {
      if (!data.success) {
        showToast(data.error || "Failed to remove message", 3000, "error");
        return;
      }

      allMessages = allMessages.filter(
        (message) => String(message.id) !== String(msgId),
      );

      if (String(editingMessageId) === String(msgId)) {
        cancelReply();
      }

      renderMessages(allMessages);
      fetchSidebarUpdates();
      showToast("Message removed from your view", 3000, "success");
    })
    .catch((error) => {
      console.error(error);
      showToast("Network error", 3000, "error");
    });
}

function togglePinMessage(msgId) {
  const formData = new URLSearchParams();
  formData.append("message_id", msgId);

  return fetch("actions/toggle_pin_message.php", {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: formData.toString(),
  })
    .then((response) => response.json())
    .then((data) => {
      if (!data.success) {
        showToast(data.error || "Failed to update pinned message", 3000, "error");
        return false;
      }

      allMessages = allMessages.map((message) =>
        String(message.id) === String(msgId)
          ? { ...message, is_pinned: Number(data.is_pinned) }
          : message,
      );
      renderMessages(allMessages);

      if (isPinnedMessagesModalOpen()) {
        loadPinnedMessages();
      }

      showToast(
        Number(data.is_pinned) === 1 ? "Message pinned" : "Message unpinned",
        3000,
        "success",
      );
      return true;
    })
    .catch((error) => {
      console.error(error);
      showToast("Network error", 3000, "error");
      return false;
    });
}

//
// Reply and Reaction functions
//

function toggleReaction(messageId, reaction) {
  const message = allMessages.find(
    (msg) => String(msg.id) === String(messageId),
  );
  if (!message) return;

  message.reactions = message.reactions || [];
  const previousReactions = [...message.reactions];
  const existingIndex = message.reactions.findIndex(
    (item) => item.user_id === currentUserId && item.reaction === reaction,
  );

  if (existingIndex === -1) {
    message.reactions.push({
      user_id: currentUserId,
      reaction,
      user_name: "You",
    });
  } else {
    message.reactions.splice(existingIndex, 1);
  }
  renderMessages(allMessages);

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
        // Confirm the optimistic update with the server state.
        refreshMessages();
      } else {
        message.reactions = previousReactions;
        renderMessages(allMessages);
        showToast(data.error || "Failed to toggle reaction", 3000, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      message.reactions = previousReactions;
      renderMessages(allMessages);
      showToast("Network error", 3000, "error");
    });
}

function startReply(messageId, senderName) {
  if (editingMessageId !== null) {
    cancelReply();
  }

  const replyPreview = document.getElementById("replyPreview");
  const composerPreviewLabel = document.getElementById(
    "composerPreviewLabel",
  );
  const replyPreviewName = document.getElementById("replyPreviewName");
  const replyPreviewMessage = document.getElementById("replyPreviewMessage");
  if (!replyPreview) return;

  // Find the original message content
  const msg = allMessages.find((m) => String(m.id) === String(messageId));
  const content = msg ? msg.content : "";

  composerPreviewLabel.textContent = "Replying to";
  replyPreviewName.textContent = senderName || "User";
  const truncated =
    content.length > 80 ? content.substring(0, 80) + "…" : content;
  replyPreviewMessage.textContent = truncated || "(no content)";

  replyPreview.style.display = "flex";
  document.getElementById("messageInput").focus();

  window._replyTo = { id: messageId, sender: senderName };
}

function cancelReply() {
  const wasEditing = editingMessageId !== null;
  const replyPreview = document.getElementById("replyPreview");
  const composerPreviewLabel = document.getElementById(
    "composerPreviewLabel",
  );
  const replyPreviewName = document.getElementById("replyPreviewName");
  const replyPreviewMessage = document.getElementById("replyPreviewMessage");
  const input = document.getElementById("messageInput");

  if (replyPreview) {
    replyPreview.style.display = "none";
  }

  if (composerPreviewLabel) composerPreviewLabel.textContent = "Replying to";
  if (replyPreviewName) replyPreviewName.textContent = "";
  if (replyPreviewMessage) replyPreviewMessage.textContent = "";
  if (wasEditing && input) input.value = "";

  window._replyTo = null;
  editingMessageId = null;
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
    const isUnsent = Number(msg.archived) === 1;
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
    if (!isUnsent && msg.is_self && msg.id === latestSelfMessageId) {
      const readCount = Number.parseInt(msg.read_count, 10) || 0;
      const isDirectMessage = currentConversation?.type === "dm";
      const readers = (msg.readers || []).map((reader) => reader.user_name);
      const readByTitle = readers.length
        ? `Seen by ${readers.join(", ")}`
        : `Seen by ${readCount} user${readCount === 1 ? "" : "s"}`;
      readReceipt = msg.is_read
        ? `<span class="read-receipt" data-tooltip="${escapeHtml(readByTitle)}">${isDirectMessage ? "Read" : `Read + ${readCount}`}</span>`
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
    const reactionMarkup =
      !isUnsent && heartCount > 0
        ? `<span class="reaction-item ${currentUserReacted ? "user-reacted" : ""}" data-tooltip="${escapeHtml(reactionTitle)}">
          <img src="components/icons/heart-fill.png" alt="Heart reaction">
          <span class="reaction-count">${heartCount}</span>
        </span>`
        : "";
    const footerMarkup =
      reactionMarkup || readReceipt
        ? `<div class="message-footer">
          <div class="reactions-display">${reactionMarkup}</div>
          ${readReceipt}
        </div>`
        : "";

    let replyQuote = "";
    if (
      !isUnsent &&
      msg.reply_to_id &&
      msg.parent_sender_name &&
      msg.parent_content
    ) {
      const parentContent =
        msg.parent_content.length > 100
          ? msg.parent_content.substring(0, 100) + "…"
          : msg.parent_content;
      const replyHeader = msg.is_self
        ? "You replied to yourself"
        : `In reply to <strong>${escapeHtml(msg.parent_sender_name)}</strong>`;

      replyQuote = `
        <div class="reply-quote">
            <div class="reply-quote-header">${replyHeader}</div>
            <div class="reply-quote-content">${escapeHtml(parentContent)}</div>
        </div>
    `;
    }

    const messageStatuses = [];
    if (Number(msg.is_pinned) === 1 && !isUnsent) {
      messageStatuses.push(
        '<span class="message-pinned"><img src="components/icons/pin.png" alt="" aria-hidden="true">Pinned</span>',
      );
    }
    if (msg.edited_at && !isUnsent) {
      messageStatuses.push('<span class="message-edited">Edited</span>');
    }
    const messageStatusMarkup = messageStatuses.length
      ? `<div class="message-statuses">${messageStatuses.join("")}</div>`
      : "";
    const div = document.createElement("div");
    div.className = `message-item ${isSelf}${isUnsent ? " unsent" : ""}`;
    div.dataset.messageId = String(msg.id);
    div.tabIndex = -1;
    div.innerHTML = `
            <div class="message-avatar">${escapeHtml(avatar)}</div>
        <div class="message-body">
          ${messageStatusMarkup}
          <div class="message-wrapper">
          ${replyQuote}
            <div class="message-content">
              <div class="message-sender">${escapeHtml(senderName)}</div>
              <div class="message-text">${escapeHtml(msg.content)}</div>
              <div class="message-time">${new Date(msg.created_at).toLocaleString("en-PH", { hour: "2-digit", minute: "2-digit" })}</div>
            </div>
                </div>
          ${footerMarkup}
            </div>
        `;

    // Unsent messages keep the More menu so they can be removed from this user's view.
    const toolbar = document.createElement("div");
    toolbar.className = "reaction-toolbar";
    toolbar.innerHTML = isUnsent
      ? `
    <button class="reaction-btn more-tool" data-msg-id="${Number.parseInt(msg.id, 10) || 0}" data-tooltip="More" type="button">
        <span class="material-symbols-rounded">more_vert</span>
    </button>
`
      : `
    <button class="reaction-btn more-tool" data-msg-id="${Number.parseInt(msg.id, 10) || 0}" data-tooltip="More" type="button">
        <span class="material-symbols-rounded">more_vert</span>
    </button>
    <button class="reaction-btn reply-btn" data-msg-id="${Number.parseInt(msg.id, 10) || 0}" data-sender="${escapeHtml(senderName)}" data-tooltip="Reply">
        <span class="material-symbols-rounded" id="replyIcon">reply</span>
    </button>
    <button class="reaction-btn reaction-trigger" data-msg-id="${Number.parseInt(msg.id, 10) || 0}" data-tooltip="React with heart" type="button">
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

function fetchMessages(isInitial = false, isInitialLoad = isInitial) {
  if (isLoading || !hasMoreMessages || !currentConversation) {
    return Promise.resolve(false);
  }
  isLoading = true;

  const params = new URLSearchParams();
  params.append("conversation_id", currentConversation.id);
  params.append("limit", MESSAGES_PER_PAGE);
  if (!isInitial && oldestTimestamp) {
    params.append("before", oldestTimestamp);
  }

  return fetch("actions/get_messages.php?" + params.toString())
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
          if (isInitialLoad) {
            scrollChatToBottom(document.getElementById("chatMessages"));
            setTimeout(markMessageAsRead, 500);
          }
          return false;
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

        if (isInitialLoad) {
          const container = document.getElementById("chatMessages");
          isLoading = false;

          if (
            container &&
            container.scrollHeight <= container.clientHeight &&
            messages.length === MESSAGES_PER_PAGE
          ) {
            return fetchMessages(false, true);
          } else {
            scrollChatToBottom(container);

            // Mark messages as read after loading
            setTimeout(markMessageAsRead, 500);
          }
          return true;
        }
        isLoading = false;
        return true;
      } else {
        alert(data.error || "Failed to load messages");
        isLoading = false;
        return false;
      }
    })
    .catch((err) => {
      console.error(err);
      alert("Network error");
      isLoading = false;
      return false;
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

function showChatLoadingSkeleton() {
  const skeleton = document.getElementById("chatLoadingSkeleton");
  const chatWindow = document.getElementById("chatWindow");
  const loadId = ++chatSkeletonLoadId;

  chatSkeletonShownAt = performance.now();
  if (skeleton) {
    skeleton.hidden = false;
    skeleton.setAttribute("aria-hidden", "false");
  }
  chatWindow?.setAttribute("aria-busy", "true");

  return loadId;
}

function hideChatLoadingSkeleton(loadId) {
  const elapsed = performance.now() - chatSkeletonShownAt;
  const remainingDelay = Math.max(0, CHAT_SKELETON_MIN_DURATION_MS - elapsed);

  setTimeout(() => {
    if (loadId !== chatSkeletonLoadId) return;

    const skeleton = document.getElementById("chatLoadingSkeleton");
    const chatWindow = document.getElementById("chatWindow");
    if (skeleton) {
      skeleton.hidden = true;
      skeleton.setAttribute("aria-hidden", "true");
    }
    chatWindow?.removeAttribute("aria-busy");
  }, remainingDelay);
}

function startSidebarLoadingSkeleton() {
  const skeleton = document.getElementById("sidebarLoadingSkeleton");
  const sidebar = document.querySelector(".chat-sidebar");

  if (!skeleton) return;

  sidebar?.setAttribute("aria-busy", "true");
  setTimeout(() => {
    skeleton.hidden = true;
    skeleton.setAttribute("aria-hidden", "true");
    sidebar?.removeAttribute("aria-busy");
  }, CHAT_SKELETON_MIN_DURATION_MS);
}

function loadMoreMessages() {
  return fetchMessages(false);
}

function isPinnedMessagesModalOpen() {
  return document
    .getElementById("pinnedMessagesModal")
    ?.classList.contains("is-open");
}

function formatPinnedDate(timestamp) {
  const date = new Date(timestamp);
  if (Number.isNaN(date.getTime())) return "";

  const options = { month: "long", day: "numeric" };
  if (date.getFullYear() !== new Date().getFullYear()) {
    options.year = "numeric";
  }
  return date.toLocaleDateString("en-PH", options);
}

function closePinnedMessageMenus(exceptItem = null) {
  document.querySelectorAll(".pinned-message-item.menu-open").forEach((item) => {
    if (item === exceptItem) return;
    item.classList.remove("menu-open");
    item
      .querySelector(".pinned-message-more")
      ?.setAttribute("aria-expanded", "false");
  });
}

function renderPinnedMessages(messages) {
  const list = document.getElementById("pinnedMessagesList");
  if (!list) return;

  if (!Array.isArray(messages) || messages.length === 0) {
    list.innerHTML = `
      <div class="pinned-messages-empty">
        <img src="components/icons/pin.png" alt="" aria-hidden="true">
        <h3>No pinned messages</h3>
        <p>Pin an important message to find it quickly later.</p>
      </div>
    `;
    return;
  }

  list.innerHTML = messages
    .map((message) => {
      const messageId = Number.parseInt(message.id, 10) || 0;
      const senderName = message.sender_name || "Unknown user";
      const senderLabel = message.is_self ? `${senderName} (You)` : senderName;
      const editedLabel = message.edited_at
        ? '<span class="pinned-message-edited">Edited</span>'
        : "";
      const unpinAction = message.can_unpin
        ? `<button type="button" role="menuitem" data-pinned-action="unpin" data-msg-id="${messageId}">Unpin</button>`
        : "";

      return `
        <article class="pinned-message-item" data-msg-id="${messageId}">
          <time datetime="${escapeHtml(message.created_at)}">${escapeHtml(formatPinnedDate(message.created_at))}</time>
          <div class="pinned-message-row">
            <div class="pinned-message-avatar" aria-hidden="true">${escapeHtml(getInitials(senderName))}</div>
            <div class="pinned-message-bubble">
              <div class="pinned-message-sender">${escapeHtml(senderLabel)}</div>
              <div class="pinned-message-text">${escapeHtml(message.content)}</div>
              ${editedLabel}
            </div>
            <div class="pinned-message-action">
              <button class="pinned-message-more" type="button" data-msg-id="${messageId}" aria-label="More actions for this pinned message" aria-haspopup="menu" aria-expanded="false">
                <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
              </button>
              <div class="pinned-message-actions" role="menu">
                <button type="button" role="menuitem" data-pinned-action="see" data-msg-id="${messageId}">See in chat</button>
                ${unpinAction}
              </div>
            </div>
          </div>
        </article>
      `;
    })
    .join("");
}

async function loadPinnedMessages() {
  const list = document.getElementById("pinnedMessagesList");
  if (!list || !currentConversation) return;

  if (pinnedMessagesRequestController) {
    pinnedMessagesRequestController.abort();
  }
  pinnedMessagesRequestController = new AbortController();
  const requestedConversationId = currentConversation.id;

  list.innerHTML = '<div class="pinned-messages-loading" role="status">Loading pinned messages…</div>';

  try {
    const params = new URLSearchParams({
      conversation_id: String(requestedConversationId),
    });
    const response = await fetch(
      "actions/get_pinned_messages.php?" + params.toString(),
      {
        credentials: "same-origin",
        signal: pinnedMessagesRequestController.signal,
      },
    );
    const data = await response.json();

    if (
      !isPinnedMessagesModalOpen() ||
      String(currentConversation?.id) !== String(requestedConversationId)
    ) {
      return;
    }

    if (!data.success) {
      throw new Error(data.error || "Unable to load pinned messages");
    }
    renderPinnedMessages(data.messages);
  } catch (error) {
    if (error.name === "AbortError") return;
    console.error(error);
    list.innerHTML = `
      <div class="pinned-messages-empty is-error">
        <h3>Pinned messages could not be loaded</h3>
        <p>Please close this window and try again.</p>
      </div>
    `;
  }
}

function openPinnedMessagesModal() {
  if (!currentConversation) {
    showToast("Select a conversation first", 3000, "error");
    return;
  }

  const modal = document.getElementById("pinnedMessagesModal");
  if (!modal) return;

  pinnedModalPreviousFocus = document.activeElement;
  hideMessageActions();
  const chatDropdown = document.getElementById("chatMenuDropdown");
  if (chatDropdown) chatDropdown.style.display = "none";

  modal.classList.add("is-open");
  modal.setAttribute("aria-hidden", "false");
  document
    .getElementById("pinnedMessagesBtn")
    ?.setAttribute("aria-expanded", "true");
  document.body.classList.add("pinned-modal-open");
  document.getElementById("closePinnedMessagesBtn")?.focus();
  loadPinnedMessages();
}

function closePinnedMessagesModal() {
  const modal = document.getElementById("pinnedMessagesModal");
  if (!modal || !modal.classList.contains("is-open")) return;

  pinnedMessagesRequestController?.abort();
  pinnedMessagesRequestController = null;
  closePinnedMessageMenus();
  modal.classList.remove("is-open");
  modal.setAttribute("aria-hidden", "true");
  document
    .getElementById("pinnedMessagesBtn")
    ?.setAttribute("aria-expanded", "false");
  document.body.classList.remove("pinned-modal-open");
  pinnedModalPreviousFocus?.focus?.();
  pinnedModalPreviousFocus = null;
}

async function seePinnedMessageInChat(messageId) {
  closePinnedMessagesModal();
  hideMembersPanel();

  const searchInput = document.getElementById("chatSearchInput");
  if (searchInput && searchInput.value) {
    searchInput.value = "";
    searchMessages("");
  }

  let target = document.querySelector(
    `.message-item[data-message-id="${CSS.escape(String(messageId))}"]`,
  );
  let attempts = 0;
  stopMessageRefresh();

  try {
    while (!target && hasMoreMessages && attempts < 50) {
      const loaded = await loadMoreMessages();
      if (!loaded) break;
      attempts += 1;
      target = document.querySelector(
        `.message-item[data-message-id="${CSS.escape(String(messageId))}"]`,
      );
    }
  } finally {
    startMessageRefresh();
  }

  if (!target) {
    showToast("This message is no longer available in your chat", 3000, "error");
    return;
  }

  target.scrollIntoView({ behavior: "smooth", block: "center" });
  target.classList.remove("message-focus-highlight");
  requestAnimationFrame(() => {
    target.classList.add("message-focus-highlight");
    target.focus({ preventScroll: true });
  });
  setTimeout(() => target.classList.remove("message-focus-highlight"), 1600);
}

//
// 6. Load a conversation
//

function syncConversationMenuState(item) {
  const archiveBtn = document.querySelector(
    '.chat-menu-dropdown button[data-action="archive"]',
  );

  const muted = item?.dataset.muted === "1";
  const archived = item?.dataset.archived === "1";

  syncMuteButton(muted);
  if (archiveBtn) archiveBtn.textContent = archived ? "Unarchive" : "Archive";
}

function syncMuteButton(muted) {
  const muteBtn = document.getElementById("chatMuteBtn");
  if (!muteBtn) return;

  const label = muted ? "Unmute conversation" : "Mute conversation";
  const icon = muteBtn.querySelector("img");
  if (icon) {
    icon.src = muted
      ? "components/icons/bell-off.png"
      : "components/icons/bell.png";
  }
  muteBtn.classList.toggle("is-muted", muted);
  muteBtn.setAttribute("aria-pressed", muted ? "true" : "false");
  muteBtn.setAttribute("aria-label", label);
  muteBtn.setAttribute("title", label);

  const accessibleLabel = muteBtn.querySelector(".sr-only");
  if (accessibleLabel) accessibleLabel.textContent = label;
}

function loadConversation(type, id) {
  // Hide members panel if open
  closePinnedMessagesModal();
  hideMembersPanel();
  cancelReply();

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
  currentUserRoleInConversation = null; // Reset role

  const membersListBtn = document.getElementById("membersListBtn");
  if (membersListBtn) {
    membersListBtn.style.display = type === "channel" ? "block" : "none";
  }

  updateChatVisibility(true);
  const skeletonLoadId = showChatLoadingSkeleton();
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
  syncConversationMenuState(item);

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
  fetchMessages(true).finally(() => hideChatLoadingSkeleton(skeletonLoadId));

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

  let replyToId = null;
  if (window._replyTo) {
    replyToId = window._replyTo.id;
  }

  const formData = new URLSearchParams();
  formData.append("content", text);
  const isEditing = editingMessageId !== null;
  const endpoint = isEditing
    ? "actions/edit_message.php"
    : "actions/send_messages.php";

  if (isEditing) {
    formData.append("message_id", editingMessageId);
  } else {
    formData.append("conversation_id", currentConversation.id);
    if (replyToId) {
      formData.append("reply_to_id", replyToId);
    }
  }

  fetch(endpoint, {
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
        console.error(`Invalid JSON response from ${endpoint}:`, text);
        throw err;
      }

      if (data.success) {
        const msg = data.message;
        if (isEditing) {
          allMessages = allMessages.map((message) =>
            String(message.id) === String(msg.id)
              ? { ...message, ...msg }
              : message,
          );
          showToast("Message edited", 3000, "success");
        } else {
          allMessages.push({
            id: msg.id,
            sender_id: msg.sender_id,
            sender_name: msg.sender_name,
            content: msg.content,
            created_at: msg.created_at,
            message_type: "text",
            archived: 0,
            is_pinned: 0,
            edited_at: null,
            can_edit: 1,
            can_pin: 1,
            is_self: true,
          });
        }

        renderMessages(allMessages);
        if (!isEditing) {
          const container = document.getElementById("chatMessages");
          scrollChatToBottom(container);
        }

        cancelReply();
        input.value = "";
        sendBtn.disabled = false;
        fetchSidebarUpdates();

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
  startSidebarLoadingSkeleton();

  document.querySelectorAll(".conversation-filter-btn").forEach((button) => {
    button.addEventListener("click", function () {
      setConversationFilter(this.dataset.filter);
    });
  });
  filterSidebar(document.getElementById("sidebarSearchInput")?.value || "");

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

  document
    .getElementById("cancelReplyBtn")
    ?.addEventListener("click", cancelReply);

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
      if (this.scrollTop === 0 && !isLoading && hasMoreMessages) {
        loadMoreMessages();
      }

      syncDropdownToButton();

      if (userScrollIntent && !isProgrammaticChatScroll) {
        showChatSearchBar();
        userScrollIntent = false;
      }
    });
  }

  // Start sidebar polling
  startSidebarPolling();
  connectChatWebSocket();

  // Chat menu toggle
  const chatMenuBtn = document.getElementById("chatMenuBtn");
  if (chatMenuBtn) {
    chatMenuBtn.addEventListener("click", toggleChatMenu);
  }

  document.getElementById("chatMuteBtn")?.addEventListener("click", toggleMute);
  document
    .getElementById("pinnedMessagesBtn")
    ?.addEventListener("click", openPinnedMessagesModal);
  document
    .getElementById("closePinnedMessagesBtn")
    ?.addEventListener("click", closePinnedMessagesModal);

  const pinnedModal = document.getElementById("pinnedMessagesModal");
  pinnedModal?.addEventListener("click", function (event) {
    if (event.target === pinnedModal) closePinnedMessagesModal();
  });

  document
    .getElementById("pinnedMessagesList")
    ?.addEventListener("click", function (event) {
      const moreButton = event.target.closest(".pinned-message-more");
      if (moreButton) {
        event.stopPropagation();
        const item = moreButton.closest(".pinned-message-item");
        const willOpen = !item.classList.contains("menu-open");
        closePinnedMessageMenus(item);
        item.classList.toggle("menu-open", willOpen);
        moreButton.setAttribute("aria-expanded", willOpen ? "true" : "false");
        return;
      }

      const actionButton = event.target.closest("[data-pinned-action]");
      if (!actionButton) return;

      event.stopPropagation();
      const messageId = actionButton.dataset.msgId;
      if (actionButton.dataset.pinnedAction === "see") {
        seePinnedMessageInChat(messageId);
      } else if (actionButton.dataset.pinnedAction === "unpin") {
        togglePinMessage(messageId);
      }
    });

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

function getChatWebSocketUrl() {
  if (window.GREEN_TRACE_WEBSOCKET_URL) {
    return window.GREEN_TRACE_WEBSOCKET_URL;
  }

  const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
  return `${protocol}//${window.location.hostname}:8080/greentrace`;
}

function sendChatSocketMessage(message) {
  if (!chatSocket || chatSocket.readyState !== WebSocket.OPEN) return false;
  chatSocket.send(JSON.stringify(message));
  return true;
}

function subscribeToCurrentConversation() {
  if (!currentConversation) return;
  sendChatSocketMessage({
    type: "subscribe",
    conversation_id: Number(currentConversation.id),
  });
}

function scheduleRealtimeSidebarRefresh() {
  clearTimeout(realtimeSidebarRefreshTimer);
  realtimeSidebarRefreshTimer = setTimeout(() => {
    fetchSidebarUpdates();
    if (typeof window.updateChatBadgeCount === "function") {
      window.updateChatBadgeCount();
    }
  }, 80);
}

function scheduleRealtimeMessageRefresh(event) {
  const eventConversationId = String(event.conversation_id || "");
  if (
    !currentConversation ||
    String(currentConversation.id) !== eventConversationId
  ) {
    return;
  }

  clearTimeout(realtimeMessageRefreshTimer);
  realtimeMessageRefreshTimer = setTimeout(() => {
    refreshMessages();
    if (isPinnedMessagesModalOpen()) {
      loadPinnedMessages();
    }
  }, 60);
}

function handleChatSocketEvent(event) {
  if (!event || typeof event !== "object") return;

  if (event.type === "ready") {
    subscribeToCurrentConversation();
    return;
  }

  if (event.type === "conversation.updated") {
    scheduleRealtimeSidebarRefresh();
    scheduleRealtimeMessageRefresh(event);
    return;
  }

  if (event.type === "sidebar.updated") {
    scheduleRealtimeSidebarRefresh();
  }
}

function scheduleChatSocketReconnect() {
  if (!chatSocketShouldReconnect || chatSocketReconnectTimer) return;

  const delay = Math.min(30000, 1000 * 2 ** chatSocketReconnectAttempt);
  chatSocketReconnectAttempt += 1;
  chatSocketReconnectTimer = setTimeout(() => {
    chatSocketReconnectTimer = null;
    connectChatWebSocket();
  }, delay);
}

function connectChatWebSocket() {
  if (!("WebSocket" in window) || !chatSocketShouldReconnect) return;
  if (
    chatSocket &&
    (chatSocket.readyState === WebSocket.OPEN ||
      chatSocket.readyState === WebSocket.CONNECTING)
  ) {
    return;
  }

  try {
    chatSocket = new WebSocket(getChatWebSocketUrl());
  } catch (error) {
    console.warn("Unable to start the chat WebSocket:", error);
    scheduleChatSocketReconnect();
    return;
  }

  chatSocket.addEventListener("open", () => {
    chatSocketConnected = true;
    chatSocketReconnectAttempt = 0;
    startSidebarPolling();
    startMessageRefresh();
    subscribeToCurrentConversation();

    clearInterval(chatSocketHeartbeat);
    chatSocketHeartbeat = setInterval(() => {
      sendChatSocketMessage({ type: "ping" });
    }, 25000);
  });

  chatSocket.addEventListener("message", (messageEvent) => {
    try {
      handleChatSocketEvent(JSON.parse(messageEvent.data));
    } catch (error) {
      console.warn("Ignored an invalid chat WebSocket event", error);
    }
  });

  chatSocket.addEventListener("close", () => {
    chatSocketConnected = false;
    chatSocket = null;
    clearInterval(chatSocketHeartbeat);
    chatSocketHeartbeat = null;
    startSidebarPolling();
    startMessageRefresh();
    scheduleChatSocketReconnect();
  });

  chatSocket.addEventListener("error", () => {
    chatSocket?.close();
  });
}

let sidebarPollingInterval = null;

function startSidebarPolling() {
  if (sidebarPollingInterval) {
    clearInterval(sidebarPollingInterval);
  }
  // WebSockets provide the fast path; a slower poll protects against missed events.
  const refreshDelay = chatSocketConnected ? 30000 : 5000;
  sidebarPollingInterval = setInterval(fetchSidebarUpdates, refreshDelay);
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
      if (
        Number(channelData.last_message_unsent) === 1 &&
        channelData.last_sender_name
      ) {
        const isSelf = channelData.last_sender_id == currentUserId;
        const senderDisplay = isSelf ? "You" : channelData.last_sender_name;
        displayMsg = isSelf
          ? "You have unsent a message"
          : `${senderDisplay} has unsent a message`;
      } else if (channelData.last_message) {
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
      const archived = parseInt(channelData.is_archived) === 1;
      item.dataset.unread = unreadCount;
      item.dataset.muted = muted ? "1" : "0";
      item.dataset.archived = archived ? "1" : "0";
      item.classList.toggle("unread", unreadCount > 0 && !muted && !archived);

      // Get right section
      const rightSection = item.querySelector(".right-channel-item");
      if (rightSection) {
        //  Unread dot
        const existingDot = rightSection.querySelector(".unread-dot");
        if (existingDot) existingDot.remove();

        if (unreadCount > 0 && !muted && !archived) {
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

      if (
        currentConversation?.type === "channel" &&
        String(currentConversation.id) === String(id)
      ) {
        syncConversationMenuState(item);
      }
    }
  });
  filterSidebar(document.getElementById("sidebarSearchInput")?.value || "");
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
      if (
        Number(dmData.last_message_unsent) === 1 &&
        dmData.last_sender_name
      ) {
        const isSelf = dmData.last_sender_id == currentUserId;
        const senderDisplay = isSelf ? "You" : dmData.last_sender_name;
        displayMsg = isSelf
          ? "You have unsent a message"
          : `${senderDisplay} has unsent a message`;
      } else if (dmData.last_message && dmData.last_sender_name) {
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
      const archived = parseInt(dmData.is_archived) === 1;
      item.dataset.unread = unreadCount;
      item.dataset.muted = muted ? "1" : "0";
      item.dataset.archived = archived ? "1" : "0";
      item.classList.toggle("unread", unreadCount > 0 && !muted && !archived);
      item.classList.toggle("muted", muted);

      const rightSection = item.querySelector(".right-channel-item");
      if (rightSection) {
        // Handle unread dot
        let dot = rightSection.querySelector(".unread-dot");
        if (unreadCount > 0 && !muted && !archived) {
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

      if (
        currentConversation?.type === "dm" &&
        String(currentConversation.id) === String(id)
      ) {
        syncConversationMenuState(item);
      }

    }
  });
  filterSidebar(document.getElementById("sidebarSearchInput")?.value || "");
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

// Stop polling when navigating away (optional)
window.addEventListener("beforeunload", function () {
  chatSocketShouldReconnect = false;
  clearTimeout(chatSocketReconnectTimer);
  clearTimeout(realtimeSidebarRefreshTimer);
  clearTimeout(realtimeMessageRefreshTimer);
  clearInterval(chatSocketHeartbeat);
  chatSocket?.close();
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

function setConversationFilter(filter) {
  activeConversationFilter = filter === "archived" ? "archived" : "chats";

  document.querySelectorAll(".conversation-filter-btn").forEach((button) => {
    const isActive = button.dataset.filter === activeConversationFilter;
    button.classList.toggle("active", isActive);
    button.setAttribute("aria-selected", isActive ? "true" : "false");
  });

  filterSidebar(document.getElementById("sidebarSearchInput")?.value || "");
}

function filterSidebar(term) {
  const query = term.toLowerCase().trim();
  const channelItems = document.querySelectorAll(".channel-item");
  const dmItems = document.querySelectorAll(".dm-item");
  const showArchived = activeConversationFilter === "archived";
  let visibleChannelCount = 0;
  let visibleDmCount = 0;

  channelItems.forEach((item) => {
    const name =
      item.querySelector(".channel-name")?.textContent.toLowerCase() || "";
    const lastMsg =
      item.querySelector(".channel-last-msg")?.textContent.toLowerCase() || "";
    const isArchived = item.dataset.archived === "1";
    const matchesSearch = name.includes(query) || lastMsg.includes(query);
    const matchesFilter = showArchived ? isArchived : !isArchived;
    const isVisible = matchesSearch && matchesFilter;
    item.hidden = !isVisible;
    if (isVisible) visibleChannelCount += 1;
  });

  dmItems.forEach((item) => {
    const name =
      item.querySelector(".dm-name")?.textContent.toLowerCase() || "";
    const lastMsg =
      item.querySelector(".dm-last-msg")?.textContent.toLowerCase() || "";
    const isArchived = item.dataset.archived === "1";
    const matchesSearch = name.includes(query) || lastMsg.includes(query);
    const matchesFilter = showArchived ? isArchived : !isArchived;
    const isVisible = matchesSearch && matchesFilter;
    item.hidden = !isVisible;
    if (isVisible) visibleDmCount += 1;
  });

  const emptyChannels = document.getElementById("emptyChannels");
  const emptyDms = document.getElementById("emptyDms");

  if (emptyChannels) {
    emptyChannels.hidden = visibleChannelCount > 0;
    const message = emptyChannels.querySelector("p");
    if (message) {
      message.textContent = query
        ? "No matching channels."
        : showArchived
          ? "No archived channels."
          : "No channels yet. Create one to start chatting!";
    }
  }
  if (emptyDms) {
    emptyDms.hidden = visibleDmCount > 0;
    emptyDms.textContent = query
      ? "No matching direct messages."
      : showArchived
        ? "No archived direct messages."
        : "No direct messages yet.";
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
      if (!data.success || !Array.isArray(data.messages)) return;

      const hasChanges = data.messages.some((incoming) => {
        const existing = allMessages.find(
          (message) => String(message.id) === String(incoming.id),
        );
        return (
          !existing ||
          existing.content !== incoming.content ||
          existing.edited_at !== incoming.edited_at ||
          Number(existing.is_pinned) !== Number(incoming.is_pinned) ||
          Number(existing.can_edit) !== Number(incoming.can_edit) ||
          Number(existing.can_pin) !== Number(incoming.can_pin) ||
          Number(existing.archived) !== Number(incoming.archived) ||
          Number(existing.is_read) !== Number(incoming.is_read) ||
          Number(existing.read_count) !== Number(incoming.read_count) ||
          JSON.stringify(existing.reactions || []) !==
            JSON.stringify(incoming.reactions || [])
        );
      });

      if (!hasChanges) return;

      allMessages = mergeMessages(allMessages, data.messages);
      renderMessages(allMessages);
    })
    .catch((err) => console.error("Error refreshing messages:", err));
}

function mergeMessages(existing, newMessages) {
  const existingIds = new Set(existing.map((m) => String(m.id)));
  const merged = [...existing];

  // Add new messages that don't exist
  for (const msg of newMessages) {
    const messageId = String(msg.id);
    if (!existingIds.has(messageId)) {
      merged.push(msg);
      existingIds.add(messageId);
    } else {
      // Update existing message (for read status changes)
      const index = merged.findIndex((m) => String(m.id) === messageId);
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

  subscribeToCurrentConversation();
  const refreshDelay = chatSocketConnected ? 30000 : 2000;
  messageRefreshInterval = setInterval(() => {
    if (currentConversation && !isLoading) {
      refreshMessages();
    }
  }, refreshDelay);
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
  if (!e.target.closest(".pinned-message-action")) {
    closePinnedMessageMenus();
  }

  const wrapper = document.querySelector(".chat-menu-wrapper");
  const dropdown = document.getElementById("chatMenuDropdown");
  if (!wrapper || !dropdown) return;
  if (!wrapper.contains(e.target)) {
    dropdown.style.display = "none";
  }
});

// Close dropdown on escape key
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    if (isPinnedMessagesModalOpen()) {
      closePinnedMessagesModal();
      return;
    }
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
// Global click handlers for message actions (Heart, More, Reply)
//

document.addEventListener("click", function (e) {
  // Heart reaction
  const heartBtn = e.target.closest(".reaction-trigger");
  if (heartBtn) {
    e.preventDefault();
    e.stopPropagation();
    const msgId = heartBtn.dataset.msgId;
    toggleReaction(msgId, "heart");
  }

  // More (ellipsis) button – pass the button element
  const moreBtn = e.target.closest(".more-tool");
  if (moreBtn) {
    e.preventDefault();
    e.stopPropagation();

    const dropdown = document.getElementById("globalMessageActionsDropdown");
    const msgId = moreBtn.dataset.msgId;
    const isSameMessageOpen =
      dropdown &&
      dropdown.style.display === "block" &&
      dropdown.dataset.msgId === String(msgId);

    if (isSameMessageOpen) {
      hideMessageActions();
      return;
    }

    showMessageActions(moreBtn, msgId);
    return;
  }

  // Close global dropdown on outside click
  const dropdown = document.getElementById("globalMessageActionsDropdown");
  if (dropdown && dropdown.style.display === "block") {
    if (!dropdown.contains(e.target) && !e.target.closest(".more-tool")) {
      hideMessageActions();
    }
  }

  // Keep the dropdown tied to the active message when the user is interacting nearby
  syncDropdownToButton();

  // Reply button
  const replyBtn = e.target.closest(".reply-btn");
  if (replyBtn) {
    e.preventDefault();
    e.stopPropagation();
    const msgId = replyBtn.dataset.msgId;
    const senderName = replyBtn.dataset.sender;
    startReply(msgId, senderName);
  }
});

document.addEventListener("mouseover", function (e) {
  const dropdown = document.getElementById("globalMessageActionsDropdown");
  if (!dropdown || dropdown.style.display !== "block") return;

  const activeId = dropdown.dataset.msgId;
  const hoveredItem = e.target.closest(".message-item");
  const hoveredToolbar = e.target.closest(".reaction-toolbar");
  const hoveredDropdown = e.target.closest(".message-actions-dropdown");

  if (!activeId) return;

  if (hoveredItem || hoveredToolbar || hoveredDropdown) {
    const matchingButton = document.querySelector(
      `.more-tool[data-msg-id="${CSS.escape(activeId)}"]`,
    );
    if (matchingButton) {
      const messageItem = matchingButton.closest(".message-item");
      if (messageItem) setMessageToolbarState(messageItem);
    }
  }
});

document.addEventListener("mouseout", function (e) {
  const dropdown = document.getElementById("globalMessageActionsDropdown");
  if (!dropdown || dropdown.style.display !== "block") return;

  const messageItem = e.target.closest(".message-item");
  const relatedTarget = e.relatedTarget;
  if (!messageItem) return;

  const shouldKeepVisible =
    (relatedTarget && relatedTarget.closest(".reaction-toolbar")) ||
    (relatedTarget && relatedTarget.closest(".message-actions-dropdown")) ||
    (relatedTarget && relatedTarget.closest(".more-tool"));

  if (shouldKeepVisible) {
    setMessageToolbarState(messageItem);
    return;
  }

  const moreBtn = messageItem.querySelector(".more-tool");
  const isSameOpenMessage = moreBtn && dropdown.dataset.msgId === String(moreBtn.dataset.msgId);

  if (!isSameOpenMessage && (!relatedTarget || !relatedTarget.closest(".message-item"))) {
    hideMessageActions();
  }
});

window.addEventListener("resize", syncDropdownToButton);
window.addEventListener("scroll", syncDropdownToButton, true);

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
        syncMuteButton(Boolean(data.muted));

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

            // Archived conversations never surface unread indicators.
            const unreadCount = parseInt(item.dataset.unread) || 0;
            let dot = rightSection.querySelector(".unread-dot");
            if (
              unreadCount > 0 &&
              !data.muted &&
              item.dataset.archived !== "1"
            ) {
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
        document
          .querySelector(".chat-container")
          ?.classList.remove("mobile-chat-open");
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
  if (!currentConversation) return;

  const conversation = { ...currentConversation };
  const formData = new URLSearchParams();
  formData.append("conversation_id", conversation.id);
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

        const itemSelector =
          conversation.type === "channel" ? ".channel-item" : ".dm-item";
        const item = document.querySelector(
          `${itemSelector}[data-id="${conversation.id}"]`,
        );
        if (item) {
          item.dataset.archived = data.archived ? "1" : "0";
          item.classList.remove("active", "unread");
          item.querySelector(".unread-dot")?.remove();
        }

        document
          .querySelector(".chat-container")
          ?.classList.remove("mobile-chat-open");
        updateChatVisibility(false);
        currentConversation = null;
        stopMessageRefresh();
        filterSidebar(document.getElementById("sidebarSearchInput")?.value || "");
        fetchSidebarUpdates();
        if (typeof window.updateChatBadgeCount === "function") {
          window.updateChatBadgeCount();
        }
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
            <p style="margin-top: 12px;">${escapeHtml(data.error)}</p>
          </div>
        `;
        return;
      }

      // Update member count
      if (membersCount) {
        membersCount.textContent = data.total + " USERS";
      }

      // ----- Update current user's mute state and role -----
      const currentUserData = data.members.find((m) => m.is_current_user);
      if (currentUserData) {
        currentUserMuted = currentUserData.is_muted_by_admin === 1;
        updateInputMuteState();
      }

      // Set the global role for permission checks
      if (data.current_user_role) {
        currentUserRoleInConversation = data.current_user_role;
      }

      // ----- Render members -----
      let html = "";
      data.members.forEach((member) => {
        const memberId = Number.parseInt(member.user_id, 10) || 0;
        const safeFullName = escapeHtml(member.full_name || "Unknown user");
        const safeEmail = escapeHtml(member.email || "");
        const safeRole = escapeHtml(member.role || "");
        const safeAdderName = escapeHtml(
          member.added_by_name || data.creator_name || "System",
        );
        const isCurrentUser = member.is_current_user;
        const isCreator = member.role === "owner";
        const isModerator = member.role === "admin";
        const isMutedByAdmin = member.is_muted_by_admin === 1;
        const muteButtonText = isMutedByAdmin ? "Unmute" : "Mute";
        const mutedClass = isMutedByAdmin ? "muted-member" : "";
        const mutedLabel = isMutedByAdmin
          ? ' <span class="muted-label">(Muted)</span>'
          : "";
        // Choose avatar icon based on mute status
        const avatarIcon = isMutedByAdmin ? "person_off" : "person";

        // Build "Added by" text
        let addedByText = "";
        if (isCreator) {
          addedByText = `<span class="creator-text">Group creator • ${safeEmail}</span>`;
        } else if (isModerator) {
          const moderatorEmail = safeEmail
            ? `@${safeEmail.replace(/^@/, "")}`
            : "";
          addedByText = `Moderator • ${moderatorEmail}`;
        } else {
          addedByText = `Added by ${safeAdderName} • ${safeEmail}`;
        }

        // Determine dropdown actions based on role and current user
        let dropdownButtons = "";
        if (isCurrentUser) {
          // Current user's own card – only Leave
          dropdownButtons = `
            <button data-action="leave" data-user-id="${memberId}">Leave</button>
          `;
        } else if (
          data.current_user_role === "owner" ||
          data.current_user_role === "admin"
        ) {
          // Owner/Admin can manage others
          dropdownButtons = `
            <button data-action="add-contact" data-user-id="${memberId}">Add as contact</button>
            <button data-action="kick" data-user-id="${memberId}">Kick</button>
            <button data-action="mute-member" data-user-id="${memberId}">${muteButtonText}</button>
          `;
          if (data.current_user_role === "owner" && member.role !== "owner") {
            const adminButtonText =
              member.role === "admin" ? "Remove Moderator" : "Make Moderator";
            dropdownButtons += `
        <button data-action="make-admin" data-user-id="${memberId}">${adminButtonText}</button>
    `;
          }
        } else {
          // Regular member – limited options
          dropdownButtons = `
            <button data-action="add-contact" data-user-id="${memberId}">Add as contact</button>
          `;
        }

        html += `
          <div class="member-card ${mutedClass}" data-user-id="${memberId}" data-role="${safeRole}" data-name="${safeFullName}">
            <div class="member-avatar">
              <span class="material-symbols-rounded">${avatarIcon}</span>
            </div>
            <div class="member-info">
              <div class="member-name-row">
                <span class="member-name">${safeFullName} ${isCurrentUser ? "(You)" : ""}${mutedLabel}</span>
              </div>
              <span class="member-added-by">${addedByText}</span>
            </div>
            <div class="member-actions">
              <div class="members-menu-wrapper">
                <button class="members-menu-btn" onclick="toggleMembersDropdown(this)" data-name="${safeFullName}">
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
      if (
        confirm(
          `Are you sure you want to ${memberName ? "change role for " + memberName : "change this user's role"}?`,
        )
      ) {
        makeAdmin(userId, conversationId, memberName);
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
          document
            .querySelector(`.channel-item[data-id="${conversationId}"]`)
            ?.remove();
          document
            .querySelector(".chat-container")
            ?.classList.remove("mobile-chat-open");
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

// Make Admin Function
function makeAdmin(userId, conversationId, memberName) {
  const formData = new URLSearchParams();
  formData.append("user_id", userId);
  formData.append("conversation_id", conversationId);

  fetch("actions/make_admin.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: formData.toString(),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showToast(`${memberName || "User"} ${data.message}`, 3000, "success");
        // Refresh members list
        loadChannelMembers(conversationId);
      } else {
        showToast(data.error || "Failed to update role.", 3000, "error");
      }
    })
    .catch((err) => {
      console.error(err);
      showToast("Network error. Please try again.", 3000, "error");
    });
}
