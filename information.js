// information.js - all JavaScript for the Tree Species page

document.addEventListener("DOMContentLoaded", function () {
  // --- Search and category filters ---
  const searchInput = document.getElementById("searchInput");
  const searchForm = document.getElementById("searchForm");
  const categoryFilter = document.getElementById("categoryFilter");
  const categorySelect = document.getElementById("categorySelect");
  let debounceTimer;

  if (searchInput && searchForm) {
    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        searchForm.submit();
      }, 400);
    });
  }

  if (categoryFilter && categorySelect) {
    categorySelect.addEventListener("change", () => categoryFilter.submit());
  }

  // --- Species status filter (Active / Archived) ---
  function applySpeciesStatusFilter(status, updateUrl = false) {
    const filter = document.querySelector(".species-status-filter");
    const grid = document.querySelector(".species-grid");
    if (!filter || !grid) return;

    filter.dataset.active = status;
    filter.querySelectorAll("a[data-status]").forEach((link) => {
      const isActive = link.dataset.status === status;
      link.classList.toggle("active", isActive);
      link.setAttribute("aria-selected", isActive ? "true" : "false");
      if (isActive) {
        link.setAttribute("aria-current", "page");
      } else {
        link.removeAttribute("aria-current");
      }
    });

    let visibleCards = 0;
    grid.querySelectorAll(".species-card").forEach((card) => {
      const isVisible =
        card.dataset.archived === (status === "archived" ? "1" : "0");
      card.hidden = !isVisible;
      if (isVisible) visibleCards++;
    });

    const addCard = grid.querySelector(".species-add-card");
    const hasSearch =
      document.getElementById("searchInput")?.value.trim() !== "";
    if (addCard)
      addCard.hidden = status === "archived" || hasSearch || visibleCards === 0;

    let emptyState = grid.querySelector("[data-species-empty]");
    if (!emptyState) {
      emptyState = document.createElement("div");
      emptyState.className = "no-results";
      emptyState.dataset.speciesEmpty = "";
      emptyState.innerHTML =
        '<img src="pages/no-results.svg" alt="" class="no-result-img"><h3>No species found</h3><p></p>';
      grid.appendChild(emptyState);
    }
    emptyState.querySelector("p").textContent =
      status === "archived"
        ? "There are no archived species for this category."
        : "There are no active species for this category.";
    emptyState.hidden = visibleCards > 0;

    const pageStatus = document.getElementById("species-page-status");
    if (pageStatus) {
      pageStatus.textContent =
        status === "archived"
          ? "Archived species are hidden from the public catalog and AR selection."
          : "";
    }

    document
      .querySelectorAll('form input[name="archived"]')
      .forEach((input) => {
        input.disabled = status !== "archived";
      });

    if (updateUrl) {
      const url = new URL(
        filter.querySelector(`a[data-status="${status}"]`).href,
        window.location.href,
      );
      window.history.pushState({}, "", url);
    }
  }

  const speciesStatusFilter = document.querySelector(".species-status-filter");
  if (speciesStatusFilter) {
    speciesStatusFilter.querySelectorAll("a[data-status]").forEach((link) => {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        applySpeciesStatusFilter(link.dataset.status, true);
      });
    });
    // Apply initial state
    applySpeciesStatusFilter(speciesStatusFilter.dataset.active);
    // Handle back/forward navigation
    window.addEventListener("popstate", () => {
      const status = new URLSearchParams(window.location.search).has("archived")
        ? "archived"
        : "active";
      applySpeciesStatusFilter(status);
    });
  }

  // --- Species detail view (click on card) ---
  document.querySelectorAll(".species-card").forEach((card) => {
    card.addEventListener("click", (event) => {
      if (event.target.closest(".species-actions")) return;
      const id = card.dataset.id;
      if (id && window.showSpeciesDetail) {
        window.showSpeciesDetail(id);
      }
    });
  });
});

//  Global function for the three‑dot menu toggle
function toggleSpeciesMenu(btn) {
  const menu = btn.querySelector(".species-menu");
  if (!menu) return;

  const isOpen = menu.classList.contains("open");

  // Close all other open menus
  document
    .querySelectorAll(".species-menu")
    .forEach((m) => m.classList.remove("open"));
  document
    .querySelectorAll(".species-menu-trigger")
    .forEach((b) => b.setAttribute("aria-expanded", "false"));

  // Toggle this one
  if (!isOpen) {
    menu.classList.add("open");
    btn.setAttribute("aria-expanded", "true");
  }
}

// Close menus when clicking outside
document.addEventListener("click", function (e) {
  if (!e.target.closest(".species-actions")) {
    document
      .querySelectorAll(".species-menu")
      .forEach((m) => m.classList.remove("open"));
    document
      .querySelectorAll(".species-menu-trigger")
      .forEach((b) => b.setAttribute("aria-expanded", "false"));
  }
});

// Category filter dropdown
const categoryToggle = document.getElementById("categoryFilterToggle");
const categoryDropdown = document.getElementById("categoryFilterDropdown");
const categoryInput = document.getElementById("categoryInput");
const categoryForm = document.getElementById("categoryForm");

function setCategoryDropdownOpen(isOpen) {
  if (!categoryToggle || !categoryDropdown) return;
  categoryDropdown.hidden = !isOpen;
  categoryToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
}

categoryToggle?.addEventListener("click", function (event) {
  event.stopPropagation();
  setCategoryDropdownOpen(categoryDropdown.hidden);
});

// Close dropdown when clicking outside
document.addEventListener("click", function (event) {
  const wrapper = document.querySelector(".category-filter-wrapper");
  if (wrapper && !wrapper.contains(event.target)) {
    setCategoryDropdownOpen(false);
  }
});

// Close on escape key
document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    setCategoryDropdownOpen(false);
    categoryToggle?.focus();
  }
});

// Handle selection
categoryDropdown?.querySelectorAll(".filter-btn").forEach((btn) => {
  btn.addEventListener("click", function () {
    const category = this.dataset.category;
    categoryInput.value = category;
    // Update label
    document.getElementById("categoryFilterLabel").textContent =
      this.textContent.trim();
    // Submit the form
    categoryForm.submit();
  });
});
