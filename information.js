// information.js - all JavaScript for the Tree Species page

document.addEventListener("DOMContentLoaded", function () {
  // --- Grove tending modal ---
  const groveTrigger = document.querySelector("[data-grove-trigger]");
  groveTrigger?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      showGroveCard();
    }
  });

  // --- Field Notes ---
  const fieldNotesGrid = document.querySelector("[data-field-notes-grid]");
  if (fieldNotesGrid) {
    const fieldNotesStatus = document.querySelector("[data-field-notes-status]");
    const fallbackNotes = Array.from(
      fieldNotesGrid.querySelectorAll(".field-note-card"),
      (card) => ({
        fact: card.querySelector("p")?.textContent.trim() || "",
        tone: card.dataset.noteTone === "accent" ? "accent" : "neutral",
        sourceName: "GreenTrace Field Notes",
        sourceUrl: "",
      }),
    );

    const shuffleNotes = (notes) => {
      const shuffled = [...notes];
      for (let index = shuffled.length - 1; index > 0; index -= 1) {
        const replacementIndex = Math.floor(Math.random() * (index + 1));
        [shuffled[index], shuffled[replacementIndex]] = [
          shuffled[replacementIndex],
          shuffled[index],
        ];
      }
      return shuffled;
    };

    const renderFieldNotes = (notes) => {
      fieldNotesGrid.replaceChildren();
      const columns = Array.from({ length: 2 }, () => {
        const column = document.createElement("div");
        column.className = "field-notes-column";
        fieldNotesGrid.append(column);
        return column;
      });
      const tones = shuffleNotes(
        Array.from({ length: Math.min(notes.length, 6) }, (_, index) =>
          index < Math.ceil(Math.min(notes.length, 6) / 2) ? "accent" : "neutral",
        ),
      );

      notes.slice(0, 6).forEach((note, index) => {
        const card = document.createElement("article");
        const isAccent = tones[index] === "accent";
        card.className = `field-note-card${isAccent ? " field-note-card--accent" : ""}`;
        card.tabIndex = 0;
        card.setAttribute("role", "button");
        card.setAttribute("aria-expanded", "false");

        const title = document.createElement("h4");
        title.textContent = `Note: ${String(index + 1).padStart(2, "0")}`;
        const fact = document.createElement("p");
        fact.textContent = note.fact;

        const citation = document.createElement("div");
        citation.className = "field-note-card__citation";

        const source = document.createElement(note.sourceUrl ? "a" : "span");
        source.className = "field-note-card__source";
        if (note.sourceUrl) {
          source.href = note.sourceUrl;
          source.target = "_blank";
          source.rel = "noopener noreferrer";
        }
        const sourceIcon = document.createElement("i");
        sourceIcon.className = "fa-solid fa-paperclip";
        sourceIcon.setAttribute("aria-hidden", "true");
        const sourceText = document.createElement("span");
        sourceText.textContent = `Source: ${note.sourceUrl || note.sourceName || "GreenTrace"}`;
        source.append(sourceIcon, sourceText);

        const citationPreview = document.createElement("div");
        citationPreview.className = "field-note-card__citation-preview";
        citationPreview.setAttribute("role", "tooltip");
        if (note.imageUrl) {
          const citationImage = document.createElement("img");
          citationImage.src = note.imageUrl;
          citationImage.alt = note.imageAlt || note.sourceName || "Reference image";
          citationImage.loading = "lazy";
          citationPreview.append(citationImage);
        }
        const citationLabel = document.createElement("strong");
        citationLabel.textContent = note.sourceName || "Reference";
        const citationUrl = document.createElement("span");
        citationUrl.textContent = note.sourceUrl || "GreenTrace field notes";
        citationPreview.append(citationLabel, citationUrl);

        citation.append(source, citationPreview);
        card.append(title, fact, citation);
        columns[Math.floor(index / 3)].append(card);
      });
    };

    const toggleFieldNote = (card) => {
      const shouldOpen = !card.classList.contains("field-note-card--active");
      fieldNotesGrid.querySelectorAll(".field-note-card").forEach((note) => {
        note.classList.remove("field-note-card--active");
        note.setAttribute("aria-expanded", "false");
      });

      if (shouldOpen) {
        card.classList.add("field-note-card--active");
        card.setAttribute("aria-expanded", "true");
      }
    };

    fieldNotesGrid.addEventListener("click", (event) => {
      if (event.target.closest(".field-note-card__source")) return;
      const card = event.target.closest(".field-note-card");
      if (card) toggleFieldNote(card);
    });

    fieldNotesGrid.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      const card = event.target.closest(".field-note-card");
      if (!card) return;
      event.preventDefault();
      toggleFieldNote(card);
    });

    // The data endpoint is intentionally optional while the curated API layer is built.
    // Add data-notes-endpoint="actions/field_notes.php?limit=6" to the grid when ready.
    const endpoint = fieldNotesGrid.dataset.notesEndpoint;
    if (!endpoint) {
      renderFieldNotes(shuffleNotes(fallbackNotes));
    } else {
      fetch(endpoint, { headers: { Accept: "application/json" } })
        .then((response) => {
          if (!response.ok) throw new Error("Could not load field notes.");
          return response.json();
        })
        .then((payload) => {
          const notes = Array.isArray(payload.notes) ? payload.notes : [];
          if (notes.length < 6) throw new Error("Not enough field notes returned.");
          renderFieldNotes(shuffleNotes(notes));
          if (fieldNotesStatus) fieldNotesStatus.textContent = "Field notes updated.";
        })
        .catch(() => {
          renderFieldNotes(shuffleNotes(fallbackNotes));
          if (fieldNotesStatus) fieldNotesStatus.textContent = "Showing curated field notes.";
        });
    }
  }

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

function showGroveCard() {
  const container = document.getElementById("floatingGroveCardContainer");
  const frame = document.getElementById("groveCardFrame");
  if (!container || !frame) return;

  frame.src = (window.basePath || "") + "modals/grove_card.php";
  if (typeof window.showFloatingContainer === "function") {
    window.showFloatingContainer(container);
    return;
  }

  container.classList.add("active");
  document.getElementById("overlay")?.classList.add("active");
  document.body.classList.add("login-active");
}

window.showGroveCard = showGroveCard;

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
