// Modal handling

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

//  Chat state
let currentConversation = null; // { type: 'channel'|'dm', id: string|number }
let currentPage = 0; // number of loaded chunks from the end
const MESSAGES_PER_PAGE = 6;
let isLoading = false;
let hasMoreMessages = true;
let allMessages = [];

// Dummy data
const dummyMessages = {
  "#concerns": [
    {
      sender: "John Doe",
      text: "Hey fellow greenist! How are you?",
      time: "9:02 AM",
    },
    { sender: "Admin", text: "Please stay on topic.", time: "9:10 AM" },
    { sender: "John Doe", text: "Got it.", time: "9:12 AM" },
    {
      sender: "You",
      text: "What about the reforestation project?",
      time: "9:15 AM",
    },
    { sender: "Admin", text: "We will discuss it tomorrow.", time: "9:20 AM" },
    { sender: "John Doe", text: "Sounds good.", time: "9:22 AM" },
    { sender: "You", text: "I will prepare the documents.", time: "9:25 AM" },
    { sender: "Admin", text: "Great!", time: "9:30 AM" },
    { sender: "John Doe", text: "See you then.", time: "9:32 AM" },
    { sender: "You", text: "See you.", time: "9:33 AM" },
    { sender: "Admin", text: "Bye.", time: "9:34 AM" },
    { sender: "John Doe", text: "Bye!", time: "9:35 AM" },
  ],
  "#activities": [
    {
      sender: "Admin",
      text: "Reminder: Tree planting event on Saturday.",
      time: "10:00 AM",
    },
    { sender: "You", text: "I will be there.", time: "10:05 AM" },
  ],
  "#reports": [
    {
      sender: "John Doe",
      text: "Has anyone seen the illegal logging report?",
      time: "10:25 AM",
    },
  ],
  dm_1: [
    {
      sender: "John Doe",
      text: "Hello, I wanted to ask about the new project.",
      time: "9:02 AM",
    },
    { sender: "You", text: "Sure, what do you need?", time: "9:03 AM" },
    { sender: "John Doe", text: "Can we meet tomorrow?", time: "9:04 AM" },
  ],
  dm_2: [
    { sender: "James Dean", text: "Hi, I have a question.", time: "10:08 PM" },
    { sender: "You", text: "Hello, how can I help?", time: "10:10 PM" },
  ],
};

// Render functions

function renderMessages(messages) {
  const container = document.getElementById("chatMessages");
  container.innerHTML = "";
  messages.forEach((msg) => {
    const isSelf = msg.sender === "You" ? "self" : "";
    const avatar = msg.sender
      .split(" ")
      .map((n) => n[0])
      .join("")
      .toUpperCase();
    const div = document.createElement("div");
    div.className = `message-item ${isSelf}`;
    div.innerHTML = `
            <div class="message-avatar">${avatar}</div>
            <div class="message-content">
                <div class="message-sender">${msg.sender}</div>
                <div class="message-text">${msg.text}</div>
                <div class="message-time">${msg.time}</div>
            </div>
        `;
    container.appendChild(div);
  });
}

function loadMoreMessages() {
  if (isLoading || !hasMoreMessages || !currentConversation) return;
  isLoading = true;

  const key =
    currentConversation.type === "channel"
      ? currentConversation.id
      : "dm_" + currentConversation.id;
  const all = dummyMessages[key] || [];
  const total = all.length;
  if (total === 0) {
    hasMoreMessages = false;
    isLoading = false;
    return;
  }

  // Calculate slice from the end
  let end = total - currentPage * MESSAGES_PER_PAGE;
  let start = end - MESSAGES_PER_PAGE;
  if (start < 0) start = 0;

  const chunk = all.slice(start, end);
  if (chunk.length === 0) {
    hasMoreMessages = false;
    isLoading = false;
    return;
  }

  const container = document.getElementById("chatMessages");
  const oldScrollHeight = container.scrollHeight;
  const oldScrollTop = container.scrollTop;

  // Prepend older messages (chunk is already ascending)
  allMessages = [...chunk, ...allMessages];
  renderMessages(allMessages);

  // Restore scroll position
  const newScrollHeight = container.scrollHeight;
  container.scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);

  currentPage++;
  isLoading = false;
  if (end >= total) {
    hasMoreMessages = false;
  }
}

function loadConversation(type, id) {
  currentConversation = { type, id };
  currentPage = 0;
  hasMoreMessages = true;
  allMessages = [];

  document.getElementById("chatPlaceholder").style.display = "none";
  document.getElementById("chatWindow").style.display = "flex";

  const chatTitle = document.getElementById("chatTitle");
  const chatAddress = document.getElementById("chatAddress");
  const chatRoleBadge = document.getElementById("chatRoleBadge");
  const chatAvatarIcon = document.getElementById("chatAvatarIcon");

  if (type === "channel") {
    const displayName = id.startsWith("#") ? id.substring(1) : id;
    chatTitle.textContent = displayName;
    chatAddress.textContent = "Public";
    chatRoleBadge.style.display = "none";
    chatAvatarIcon.textContent = "group";
  } else {
    // DM: show user name, barangay, role badge, person icon
    const dmItem = document.querySelector(`.dm-item[data-id="${id}"]`);
    if (dmItem) {
      const name = dmItem.querySelector(".dm-name").textContent;
      const address = dmItem.dataset.address || "";
      const role = dmItem.dataset.role || "";
      chatTitle.textContent = name;
      chatAddress.textContent = address;
      if (role) {
        // Set badge text and style based on role
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

  // Load first batch of messages
  loadMoreMessages();
}

// Event listeners

document.addEventListener("DOMContentLoaded", function () {
  // Channel clicks
  document.querySelectorAll(".channel-item").forEach((item) => {
    item.addEventListener("click", function () {
      document
        .querySelectorAll(".channel-item, .dm-item")
        .forEach((el) => el.classList.remove("active"));
      this.classList.add("active");
      const channelId = this.dataset.id;
      loadConversation("channel", channelId);
    });
  });

  // DM clicks
  document.querySelectorAll(".dm-item").forEach((item) => {
    item.addEventListener("click", function () {
      document
        .querySelectorAll(".channel-item, .dm-item")
        .forEach((el) => el.classList.remove("active"));
      this.classList.add("active");
      const dmId = this.dataset.id;
      loadConversation("dm", dmId);
    });
  });

  // Send message
  const sendBtn = document.getElementById("sendMessageBtn");
  const messageInput = document.getElementById("messageInput");
  sendBtn.addEventListener("click", sendMessage);
  messageInput.addEventListener("keypress", function (e) {
    if (e.key === "Enter") sendMessage();
  });

  // Infinite scroll: load more when scrolling to top
  const chatMessages = document.getElementById("chatMessages");
  chatMessages.addEventListener("scroll", function () {
    if (this.scrollTop === 0 && !isLoading && hasMoreMessages) {
      loadMoreMessages();
    }
  });
});

function sendMessage() {
  const input = document.getElementById("messageInput");
  const text = input.value.trim();
  if (!text || !currentConversation) return;

  const container = document.getElementById("chatMessages");
  const div = document.createElement("div");
  div.className = "message-item self";
  div.innerHTML = `
        <div class="message-avatar">${getInitials("You")}</div>
        <div class="message-content">
            <div class="message-sender">You</div>
            <div class="message-text">${text}</div>
            <div class="message-time">Just now</div>
        </div>
    `;
  container.appendChild(div);
  input.value = "";
  container.scrollTop = container.scrollHeight;

  // Add to allMessages (for state consistency)
  allMessages.push({ sender: "You", text: text, time: "Just now" });
}

function getInitials(name) {
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase();
}
