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
  }
  overlay.classList.remove("active");
  body.classList.remove("login-active");
  activeContainer = null;
}

function closeAllFloating() {
  if (signUpContainer) signUpContainer.classList.remove("active");
  if (signInContainer) signInContainer.classList.remove("active");
  if (logoutContainer) logoutContainer.classList.remove("active");
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

function showToast(message, duration = 3000) {
  const toast = document.getElementById("toast");
  const toastMessage = document.getElementById("toast-message");
  if (!toast || !toastMessage) return;
  toastMessage.textContent = message;
  toast.classList.remove("hidden");
  setTimeout(() => hideToast(), duration);
}

function hideToast() {
  const toast = document.getElementById("toast");
  if (toast) toast.classList.add("hidden");
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
