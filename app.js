// Function to update active feat-count based on visible card
function updateActiveFeat(containerId, featRowId) {
  const container = document.getElementById(containerId);
  const featCounts = document.querySelectorAll(`#${featRowId} .feat-count`);
  if (!container || !featCounts.length) return;
  featCounts.forEach((count) => count.classList.remove("active"));
  const scrollLeft = container.scrollLeft;
  const cardWidth = container.querySelector(".feature-card").offsetWidth + 32;
  const visibleIndex = Math.round(scrollLeft / cardWidth);
  const cards = container.querySelectorAll(".feature-card");
  cards.forEach((card, index) => {
    const cardRect = card.getBoundingClientRect();
    const containerRect = container.getBoundingClientRect();
    const visibleWidth =
      Math.min(cardRect.right, containerRect.right) -
      Math.max(cardRect.left, containerRect.left);
    const visiblePercentage = visibleWidth / cardRect.width;
    if (visiblePercentage > 0.3) {
      const cardNumber = card.getAttribute("data-card");
      featCounts.forEach((count) => {
        if (count.getAttribute("data-feat") === cardNumber) {
          count.classList.add("active");
        }
      });
    }
  });
}

// Scroll functionality with highlight update for main layout
window.addEventListener("DOMContentLoaded", function () {
  const container = document.getElementById("cardsContainer");
  const scrollLeftBtn = document.getElementById("scrollLeft");
  const scrollRightBtn = document.getElementById("scrollRight");
  if (container && scrollLeftBtn && scrollRightBtn) {
    setTimeout(() => updateActiveFeat("cardsContainer", "featRow"), 100);
    scrollLeftBtn.addEventListener("click", () => {
      container.scrollBy({ left: -400, behavior: "smooth" });
      setTimeout(() => updateActiveFeat("cardsContainer", "featRow"), 300);
    });
    scrollRightBtn.addEventListener("click", () => {
      container.scrollBy({ left: 400, behavior: "smooth" });
      setTimeout(() => updateActiveFeat("cardsContainer", "featRow"), 300);
    });
    container.addEventListener("scroll", () => {
      updateActiveFeat("cardsContainer", "featRow");
    });
  }
  window.addEventListener("resize", () => {
    updateActiveFeat("cardsContainer", "featRow");
  });
});

// Floating Login Script
const overlay = document.getElementById("overlay");
const body = document.body;

const signUpContainer = document.getElementById("floatingSignUpContainer");
const signInContainer = document.getElementById("floatingSignInContainer");
const reportContainer = document.getElementById("floatingReportContainer");
const logoutContainer = document.getElementById("floatingLogoutContainer");

let activeContainer = null;

function resetFormFields(iframeId) {
  const iframe = document.getElementById(iframeId);
  if (iframe && iframe.contentWindow) {
    try {
      if (iframe.contentWindow.resetPasswordToggle) {
        if (iframeId === "signupFrame") {
          iframe.contentWindow.resetPasswordToggle("signupPasswordWrapper");
        } else if (iframeId === "signInFrame") {
          iframe.contentWindow.resetPasswordToggle("signinPasswordWrapper");
        }
      }
      const inputs = iframe.contentWindow.document.querySelectorAll("input");
      inputs.forEach((input) => {
        if (input.type !== "submit" && input.type !== "button") {
          input.value = "";
        }
      });
    } catch (e) {
      console.log("Could not reset iframe fields:", e);
    }
  }
}

function showSignUp() {
  closeAllFloating();
  signUpContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = signUpContainer;
  setTimeout(() => resetFormFields("signupFrame"), 200);
}

function showSignIn(errorMsg = "") {
  closeAllFloating();
  const iframe = document.getElementById("signInFrame");
  if (errorMsg) {
    iframe.src = "pages/sign-in.php#error=" + encodeURIComponent(errorMsg);
  }
  signInContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = signInContainer;
  setTimeout(() => resetFormFields("signInFrame"), 200);
}

function showLogout() {
  closeAllFloating();
  logoutContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = logoutContainer;
}

function showReport() {
  closeAllFloating();
  reportContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = reportContainer;
  setTimeout(() => resetFormFields("reportFrame"), 100);
}

function hideFloating() {
  if (activeContainer) {
    activeContainer.classList.remove("active");
    // Clear iframe src if this is the volunteer container to prevent state persistence
    if (activeContainer.id === "floatingVolunteerContainer") {
      const volunteerFrame = document.getElementById("volunteerFrame");
      if (volunteerFrame) volunteerFrame.src = "";
    }
  }
  overlay.classList.remove("active");
  body.classList.remove("login-active");
  activeContainer = null;
}

function closeAllFloating() {
  if (signUpContainer) signUpContainer.classList.remove("active");
  if (signInContainer) signInContainer.classList.remove("active");
  if (logoutContainer) logoutContainer.classList.remove("active");

  // Close all other floating containers to prevent duplication
  const allContainers = document.querySelectorAll(".floating-container");
  allContainers.forEach((container) => {
    container.classList.remove("active");
  });
}

function switchToSignIn() {
  hideFloating();
  showSignIn();
}

function switchToSignUp() {
  hideFloating();
  showSignUp();
}

if (overlay) {
  overlay.addEventListener("click", hideFloating);
}

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape" && activeContainer) {
    hideFloating();
  }
});

function showToast(message, duration = 3000, type = "success") {
  const toast = document.getElementById("toast");
  const toastMessage = document.getElementById("toast-message");
  if (!toast || !toastMessage) return;

  toastMessage.textContent = message;

  // Reset classes
  toast.classList.remove("hidden", "error", "success");

  if (type === "error") {
    toast.classList.add("error");
    // Force inline style with !important to override CSS
    toast.style.setProperty("background-color", "#d32f2f", "important");
  } else {
    toast.classList.add("success");
    toast.style.removeProperty("background-color");
  }

  setTimeout(() => hideToast(), duration);
}

function hideToast() {
  const toast = document.getElementById("toast");
  if (toast) {
    toast.classList.add("hidden");
    toast.style.removeProperty("background-color");
  }
}

function showSpeciesDetail(id) {
  closeAllFloating();
  const speciesContainer = document.getElementById("floatingSpeciesContainer");
  const iframe = document.getElementById("speciesFrame");
  iframe.src = "species_detail.php?id=" + id;
  speciesContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = speciesContainer;
}

function showActivityDetails(activityId) {
  closeAllFloating();
  const container = document.getElementById("floatingActivityContainer");
  const iframe = document.getElementById("activityFrame");
  iframe.src = "pages/activity_details.php?id=" + activityId;
  container.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = container;
}

// Volunteer Registration Modal
function showVolunteerForm(activityId) {
  closeAllFloating();
  const container = document.getElementById("floatingVolunteerContainer");
  const iframe = document.getElementById("volunteerFrame");
  if (iframe) {
    iframe.src = "modals/volunteer.php?activity_id=" + activityId;
  }
  if (container) {
    container.classList.add("active");
    if (overlay) overlay.classList.add("active");
    document.body.classList.add("login-active");
    activeContainer = container;
  }
}

function showConfirmArchive(idOrArray) {
  closeAllFloating();
  const container = document.getElementById("floatingArchiveContainer");
  const iframe = document.getElementById("confirmArchiveFrame");
  let param = "";
  if (Array.isArray(idOrArray)) {
    param = "ids=" + idOrArray.join(",");
  } else {
    param = "id=" + idOrArray;
  }
  const baseUrl = iframe.src.split("?")[0];
  iframe.src = `${baseUrl}?${param}`;
  container.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = container;
}

function showConfirmRestore(idOrArray) {
  closeAllFloating();
  const container = document.getElementById("floatingRestoreContainer");
  const iframe = document.getElementById("confirmRestoreFrame");
  let param = "";
  if (Array.isArray(idOrArray)) {
    param = "ids=" + idOrArray.join(",");
  } else {
    param = "id=" + idOrArray;
  }
  const baseUrl = iframe.src.split("?")[0];
  iframe.src = `${baseUrl}?${param}`;
  container.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = container;
}

function showConfirmDelete(idOrArray) {
  closeAllFloating();
  const container = document.getElementById("floatingDeleteContainer");
  const iframe = document.getElementById("confirmDeleteFrame");
  let param = "";
  if (Array.isArray(idOrArray)) {
    param = "ids=" + idOrArray.join(",");
  } else {
    param = "id=" + idOrArray;
  }
  const baseUrl = iframe.src.split("?")[0];
  iframe.src = `${baseUrl}?${param}`;
  container.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = container;
}

// Edit Activity Modal
function showEditActivityModal(activityId) {
  closeAllFloating();
  const container = document.getElementById("floatingEditActivityContainer");
  const iframe = document.getElementById("editActivityFrame");
  iframe.src =
    (window.basePath || "") + "modals/edit_activity.php?id=" + activityId;
  container.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = container;
}

// Add Activity Modal
function showAddActivityModal() {
  closeAllFloating();
  const container = document.getElementById("floatingAddActivityContainer");
  const iframe = document.getElementById("addActivityFrame");
  iframe.src = (window.basePath || "") + "modals/add_activity.php";
  console.log("Loading iframe:", iframe.src);
  container.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = container;
}

function getCSRFToken() {
  return document
    .querySelector('meta[name="csrf-token"]')
    .getAttribute("content");
}

function showEditProfileModal() {
  closeAllFloating();
  const container = document.getElementById("floatingEditProfileContainer");
  const iframe = document.getElementById("editProfileFrame");
  iframe.src = (window.basePath || "") + "modals/edit_profile.php";
  container.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = container;
}

function updateBadgeCount() {
  fetch("get_unread_count.php")
    .then((response) => response.json())
    .then((data) => {
      const dot = document.getElementById("notificationDot");
      if (dot) {
        if (data.unread > 0) {
          dot.style.display = "block";
        } else {
          dot.style.display = "none";
        }
      }
    })
    .catch((err) => console.error("Error fetching unread count:", err));
}

// Add Message Modal

function showAddMessageModal() {
  closeAllFloating();
  const container = document.getElementById("floatingAddMessageContainer");
  const iframe = document.getElementById("addMessageFrame");
  if (iframe) {
    iframe.src = (window.basePath || "") + "modals/add_message.php";
  }
  if (container) {
    container.classList.add("active");
    if (overlay) overlay.classList.add("active");
    document.body.classList.add("login-active");
    activeContainer = container;
  }
}

// Create Channel Modal
function showCreateChannelModal() {
  closeAllFloating();
  const container = document.getElementById("floatingCreateChannelContainer");
  const iframe = document.getElementById("createChannelFrame");
  if (iframe) {
    iframe.src = (window.basePath || "") + "modals/create_channel.php";
  }
  if (container) {
    container.classList.add("active");
    if (overlay) overlay.classList.add("active");
    document.body.classList.add("login-active");
    activeContainer = container;
  }
}

function updateChatBadgeCount() {
  fetch('actions/get_unread_chat_count.php')
      .then(response => response.json())
      .then(data => {
        const dot = document.getElementById('chatDot');
        if (dot) {
          if (data.unread > 0) {
            dot.style.display = 'block';
          } else {
            dot.style.display = 'none';
          }
        }
      })
      .catch(err => console.error('Error fetching unread count:', err));
}

// Call on load

document.addEventListener('DOMContentLoaded', function() {
  updateBadgeCount();
  updateChatBadgeCount();
});

// Periodic update every 3 seconds
setInterval(() => {
  updateBadgeCount();
  updateChatBadgeCount();
}, 3000);

// Call it on page load
// document.addEventListener("DOMContentLoaded", updateBadgeCount);

function showAddChannelMembersModal(conversationId) {
    closeAllFloating();
    const container = document.getElementById("floatingAddChannelMembersContainer");
    const iframe = document.getElementById("addChannelMembersFrame");
    if (iframe) {
        iframe.src = (window.basePath || "") + "modals/add_channel_members.php?conversation_id=" + conversationId;
    }
    if (container) {
        container.classList.add("active");
        if (overlay) overlay.classList.add("active");
        document.body.classList.add("login-active");
        activeContainer = container;
    }
}

window.showAddChannelMembersModal = showAddChannelMembersModal;
window.showCreateChannelModal = showCreateChannelModal;
window.showAddMessageModal = showAddMessageModal;
window.showEditProfileModal = showEditProfileModal;
window.showAddActivityModal = showAddActivityModal;
window.showEditActivityModal = showEditActivityModal;
window.showActivityDetails = showActivityDetails;
window.showSignUp = showSignUp;
window.showSignIn = showSignIn;
window.hideFloating = hideFloating;
window.switchToSignIn = switchToSignIn;
window.switchToSignUp = switchToSignUp;
window.showLogout = showLogout;
window.showReport = showReport;
window.showLogin = showSignUp;
window.hideLogin = hideFloating;
window.showSpeciesDetail = showSpeciesDetail;
window.showVolunteerForm = showVolunteerForm;
window.showConfirmArchive = showConfirmArchive;
window.showConfirmRestore = showConfirmRestore;
window.showConfirmDelete = showConfirmDelete;