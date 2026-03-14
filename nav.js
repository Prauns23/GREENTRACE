// Navigation Sidebar functionality
document.addEventListener("DOMContentLoaded", function () {
  const menuIcon = document.querySelector(".menu");
  const sidebar = document.querySelector(".sidebar");
  const body = document.body;

  // Create overlay if it doesn't exist
  let sidebarOverlay = document.querySelector(".sidebar-overlay");
  if (!sidebarOverlay) {
    sidebarOverlay = document.createElement("div");
    sidebarOverlay.className = "sidebar-overlay";
    body.appendChild(sidebarOverlay);
  }

  // Toggle sidebar function
  function toggleSidebar() {
    sidebar.classList.toggle("active");
    sidebarOverlay.classList.toggle("active");
    body.classList.toggle("sidebar-open");
  }

  // Open/close with menu icon click
  menuIcon.addEventListener("click", toggleSidebar);

  // Close sidebar when clicking overlay
  sidebarOverlay.addEventListener("click", toggleSidebar);

  // Close sidebar when clicking a link (for smooth navigation)
  const sidebarLinks = document.querySelectorAll(".sidebar-nav a");
  sidebarLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      // Don't close if it's a login/signup link (if they have onclick)
      if (!this.hasAttribute("onclick")) {
        toggleSidebar();
      }
    });
  });

  // Handle escape key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && sidebar.classList.contains("active")) {
      toggleSidebar();
    }
  });

  // Handle "View profile" click
  const viewProfile = document.querySelector(".sidebar-profile");
  if (viewProfile) {
    viewProfile.addEventListener("click", function () {
      console.log("View profile clicked");
      // You can close sidebar or navigate to profile page
      // toggleSidebar(); // Uncomment if you want sidebar to close when clicking profile
    });
  }
});