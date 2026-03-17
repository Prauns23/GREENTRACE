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

// Add these missing container variables
const signUpContainer = document.getElementById("floatingLoginContainer"); // For sign up
const signInContainer = document.getElementById("floatingSignInContainer"); // For sign in
const reportContainer = document.getElementById("floatingReportContainer");

let activeContainer = null;

// Update the resetFormFields function in app.js
function resetFormFields(iframeId) {
  const iframe = document.getElementById(iframeId);
  if (iframe && iframe.contentWindow) {
    try {
      // Call the reset function inside the iframe
      if (iframe.contentWindow.resetPasswordToggle) {
        if (iframeId === "loginFrame") {
          iframe.contentWindow.resetPasswordToggle("signupPasswordWrapper");
        } else if (iframeId === "signInFrame") {
          iframe.contentWindow.resetPasswordToggle("signinPasswordWrapper");
        }
      }

      // Reset all input fields
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

// Update showSignUp function
function showSignUp() {
  closeAllFloating();
  signUpContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = signUpContainer;

  // Reset fields in sign up form
  setTimeout(() => {
    resetFormFields("loginFrame");
  }, 200); // Increased timeout to ensure iframe is loaded
}

// Update showSignIn function
function showSignIn() {
  closeAllFloating();
  signInContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = signInContainer;

  // Reset fields in sign in form
  setTimeout(() => {
    resetFormFields("signInFrame");
  }, 200); // Increased timeout to ensure iframe is loaded
}

// SHOW Sign Up
function showSignUp() {
  closeAllFloating();
  signUpContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = signUpContainer;

  // Reset fields in sign up form
  setTimeout(() => {
    resetFormFields("loginFrame");
  }, 100);
}

// SHOW Sign In
function showSignIn() {
  closeAllFloating();
  signInContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = signInContainer;

  // Reset fields in sign in form
  setTimeout(() => {
    resetFormFields("signInFrame");
  }, 100);
}

// SHOW Report
function showReport() {
  closeAllFloating();
  reportContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active");
  activeContainer = reportContainer;

  setTimeout(() => {
    resetFormFields("reportFrame");
  }, 100);
}

// HIDE floating
function hideFloating() {
  if (activeContainer) {
    activeContainer.classList.remove("active");
  }
  overlay.classList.remove("active");
  body.classList.remove("login-active");
  activeContainer = null;
}

// Close all floating containers
function closeAllFloating() {
  if (signUpContainer) signUpContainer.classList.remove("active");
  if (signInContainer) signInContainer.classList.remove("active");
}

// Switch functions
function switchToSignIn() {
  hideFloating();
  showSignIn();
}

function switchToSignUp() {
  hideFloating();
  showSignUp();
}

// Event listeners
if (overlay) {
  overlay.addEventListener("click", hideFloating);
}

// ESC key to close
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape" && activeContainer) {
    hideFloating();
  }
});

// Make functions available globally
window.showSignUp = showSignUp;
window.showSignIn = showSignIn;
window.hideFloating = hideFloating;
window.switchToSignIn = switchToSignIn;
window.switchToSignUp = switchToSignUp;
window.showReport = showReport;

// For backward compatibility
window.showLogin = showSignUp;
window.hideLogin = hideFloating;
