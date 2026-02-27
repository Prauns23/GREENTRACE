// Initialize Swiper
const swiper = new Swiper(".mySwiper", {
  // Optional parameters
  loop: true,
  speed: 500,
  spaceBetween: 20,
  slidesPerView: 2,

  // Navigation arrows (you also have custom buttons outside)
  navigation: {
    nextEl: ".swiper-button-next",
    prevEl: ".swiper-button-prev",
  },

  // Enable touch events
  touchRatio: 1,
  grabCursor: true,

  // Auto height
  autoHeight: true,

  // Effects
  effect: "slide",

  // Keyboard control
  keyboard: {
    enabled: true,
    onlyInViewport: true,
  },
});

// descriptions for each feature; we update the paragraph on the left when the slide changes
const descriptions = [
  "Displays GPS-tagged planting sites and reported areas on an interactive map. Allows users to view planting sites, assigned areas, and previously recorded trees through an interactive map interface.",
  "Allows users to create accounts, submit required information, and apply for participation in reforestation activities.",
  "Provides informative content about forest conservation, tree growth cycles, native vs introduced species, and sustainable reforestation practices.",
  "Provides real-time guidance on proper spacing between multiple trees to prevent overcrowding and promote healthy growth. And allows users to visualize a selected tree species in real-world space before planting using augmented reality."
];

// sync feature nav with swiper
const navItems = document.querySelectorAll(".feat-count");
navItems.forEach((item, index) => {
  item.addEventListener("click", () => {
    swiper.slideTo(index);
  });
});

// update active state/description on slide change
swiper.on("slideChange", () => {
  const activeIndex = swiper.realIndex;
  navItems.forEach((item, index) => {
    if (index === activeIndex) item.classList.add("active");
    else item.classList.remove("active");
  });
  document.querySelector(".feat-description").textContent = descriptions[activeIndex];
});

// set initial state
navItems[0].classList.add("active");
document.querySelector(".feat-description").textContent = descriptions[0];

// custom arrows expand swiper control
const leftBtn = document.querySelector('.swipe-left-btn');
const rightBtn = document.querySelector('.swipe-right-btn');
if (leftBtn) leftBtn.addEventListener('click', () => swiper.slidePrev());
if (rightBtn) rightBtn.addEventListener('click', () => swiper.slideNext());


