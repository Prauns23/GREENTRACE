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

const floatingContainer = document.getElementById("floatingContainer");
const overlay = document.getElementById("overlay");
const body = document.body;

// SHOW login - makes background unclickable
function showLogin() {
  floatingContainer.classList.add("active");
  overlay.classList.add("active");
  body.classList.add("login-active"); 
  
}

// HIDE login - makes background clickable again
function hideLogin() {
  floatingContainer.classList.remove("active");
  overlay.classList.remove("active");
  body.classList.remove("login-active"); 
  // This restores background clicks
}

// Make sure elements exist before adding event listeners
if (overlay) {
  overlay.addEventListener("click", hideLogin);
}

// ESC key to close
document.addEventListener("keydown", function (e) {
  if (
    e.key === "Escape" &&
    floatingContainer &&
    floatingContainer.classList.contains("active")
  ) {
    hideLogin();
  }
});

// Make showLogin available globally (for onclick in HTML)
window.showLogin = showLogin;
window.hideLogin = hideLogin;

// Debug - check if functions are working
console.log("Login functions loaded:", { showLogin, hideLogin });
