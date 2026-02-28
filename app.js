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
