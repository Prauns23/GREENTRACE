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

  const treeGrowthRoot = document.querySelector("[data-tree-growth-root]");
  if (treeGrowthRoot) {
    const waterButton = treeGrowthRoot.querySelector("[data-tree-water]");
    const illustrationHost = treeGrowthRoot.querySelector("[data-tree-illustration-host]");
    let watering = false;

    const replaceTreeIllustration = (svg, animate = false) => {
      if (!illustrationHost || !svg) return;
      const currentStage = illustrationHost.querySelector("svg")?.dataset.treeStage;
      const nextStage = String(svg.match(/data-tree-stage="(\d+)"/)?.[1] ?? "");
      if (currentStage === nextStage && !animate) return;

      const insertIllustration = () => {
        illustrationHost.innerHTML = svg;
        illustrationHost.classList.remove("is-phase-leaving");
        if (animate) {
          illustrationHost.classList.add("is-phase-entering");
          window.setTimeout(
            () => illustrationHost.classList.remove("is-phase-entering"),
            700,
          );
        }
      };

      if (!animate) {
        insertIllustration();
        return;
      }

      illustrationHost.classList.add("is-phase-leaving");
      window.setTimeout(insertIllustration, 180);
    };

    const applyTreeGrowthState = (state, animate = false) => {
      if (!state) return;
      const dayLabel = `Day ${state.day} of ${state.duration}`;
      const card = treeGrowthRoot.querySelector("[data-tree-card]");
      const name = treeGrowthRoot.querySelector("[data-tree-name]");
      const scientific = treeGrowthRoot.querySelector("[data-tree-scientific]");
      const category = treeGrowthRoot.querySelector("[data-tree-category]");
      const day = treeGrowthRoot.querySelector("[data-tree-day-label]");
      const progress = treeGrowthRoot.querySelector("[data-tree-progress-stat]");
      const matured = treeGrowthRoot.querySelector("[data-tree-matured-stat]");
      const status = treeGrowthRoot.querySelector("[data-tree-status]");

      if (name) name.textContent = state.name;
      if (scientific) scientific.textContent = state.scientificName;
      if (category) category.textContent = state.category;
      if (day) day.textContent = dayLabel;
      if (progress) progress.textContent = state.day;
      if (matured) matured.textContent = state.maturedCount;
      if (status) status.textContent = state.message;
      if (card) card.setAttribute("aria-label", `Your grove: ${state.name} at ${dayLabel.toLowerCase()}`);
      if (groveTrigger) groveTrigger.setAttribute("aria-label", `Open ${state.name} tree tending details`);

      if (waterButton) {
        const label = state.canWater ? `Water ${state.name}` : state.message;
        waterButton.setAttribute("aria-disabled", state.canWater ? "false" : "true");
        waterButton.setAttribute("aria-label", label);
        waterButton.dataset.tooltip = state.wateredToday
          ? "Already watered"
          : state.canWater
            ? "Click to water"
            : state.message;
      }

      replaceTreeIllustration(state.illustrationSvg, animate);
    };

    const showGrowthToast = (message, success) => {
      if (typeof window.showToast === "function") {
        window.showToast(message, 3500, success ? "success" : "error");
      }
    };

    waterButton?.addEventListener("click", async () => {
      if (watering) return;
      if (waterButton.getAttribute("aria-disabled") === "true") {
        showGrowthToast(waterButton.getAttribute("aria-label"), false);
        return;
      }

      watering = true;
      waterButton.classList.add("is-loading");
      waterButton.setAttribute("aria-busy", "true");
      try {
        const response = await fetch(treeGrowthRoot.dataset.waterEndpoint, {
          method: "POST",
          headers: { Accept: "application/json" },
        });
        const payload = await response.json();
        if (payload.state) applyTreeGrowthState(payload.state, payload.advanced === true);
        showGrowthToast(payload.message || "Tree tending status updated.", response.ok && payload.success);

        if (response.ok && payload.advanced) {
          showGroveCard(true);
        }
      } catch (_error) {
        showGrowthToast("The tree could not be watered. Please try again.", false);
      } finally {
        watering = false;
        waterButton.classList.remove("is-loading");
        waterButton.removeAttribute("aria-busy");
      }
    });

    window.addEventListener("message", (event) => {
      if (event.origin !== window.location.origin || event.data?.type !== "tree-growth-updated") return;
      applyTreeGrowthState(event.data.state, event.data.animate === true);
      if (event.data.message) showGrowthToast(event.data.message, event.data.success !== false);
    });
  }

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

  // --- Progressive history timeline ---
  const historyTimeline = document.querySelector(".history-timeline");
  if (historyTimeline) {
    const milestones = Array.from(
      historyTimeline.querySelectorAll(".history-milestone"),
    );
    let latestRevealedIndex = 0;

    historyTimeline.classList.add("history-timeline--interactive");

    const updateHistoryProgress = () => {
      milestones.forEach((milestone, index) => {
        const marker = milestone.querySelector(".history-milestone__marker");
        const year = milestone.querySelector("time")?.textContent.trim() || "History";
        const isRevealed = index <= latestRevealedIndex;
        const isCurrent = index === latestRevealedIndex + 1;

        milestone.classList.toggle("is-revealed", isRevealed);
        milestone.classList.toggle("is-current", isCurrent);
        milestone.classList.toggle("is-locked", !isRevealed && !isCurrent);
        milestone.classList.toggle("is-complete", index < latestRevealedIndex);
        milestone.setAttribute("aria-expanded", isRevealed ? "true" : "false");

        if (!marker) return;
        marker.removeAttribute("aria-hidden");
        marker.setAttribute("role", "button");
        marker.tabIndex = isCurrent ? 0 : -1;
        marker.setAttribute("aria-disabled", isCurrent ? "false" : "true");
        marker.setAttribute(
          "aria-label",
          isCurrent
            ? `Reveal the ${year} milestone`
            : isRevealed
              ? `${year} milestone revealed`
              : `Reveal earlier milestones before ${year}`,
        );
      });
    };

    const revealNextMilestone = (marker, moveFocus = false) => {
      const milestone = marker.closest(".history-milestone");
      const index = milestones.indexOf(milestone);
      if (index !== latestRevealedIndex + 1) return;

      latestRevealedIndex = index;
      updateHistoryProgress();

      if (moveFocus) {
        milestones[index + 1]
          ?.querySelector(".history-milestone__marker")
          ?.focus();
      }
    };

    historyTimeline.addEventListener("click", (event) => {
      const marker = event.target.closest(".history-milestone__marker");
      if (marker) revealNextMilestone(marker);
    });

    historyTimeline.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      const marker = event.target.closest(".history-milestone__marker");
      if (!marker) return;
      event.preventDefault();
      revealNextMilestone(marker, true);
    });

    updateHistoryProgress();
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

function showGroveCard(animatePhase = false) {
  const container = document.getElementById("floatingGroveCardContainer");
  const frame = document.getElementById("groveCardFrame");
  if (!container || !frame) return;

  const modalUrl = new URL(
    (window.basePath || "") + "modals/grove_card.php",
    window.location.href,
  );
  if (animatePhase) modalUrl.searchParams.set("animate", "1");
  frame.src = modalUrl.href;
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
