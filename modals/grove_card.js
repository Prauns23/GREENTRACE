document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("[data-grove-root]");
  if (!root) return;

  const waterButton = root.querySelector("[data-grove-water]");
  const illustrationHost = root.querySelector("[data-grove-illustration-host]");
  let watering = false;

  if (root.dataset.initialAnimation === "true" && illustrationHost) {
    illustrationHost.classList.add("is-phase-entering");
    window.setTimeout(() => illustrationHost.classList.remove("is-phase-entering"), 700);
  }

  const notifyParent = (message, success = true) => {
    try {
      if (typeof parent.showToast === "function") {
        parent.showToast(message, 3500, success ? "success" : "error");
      }
    } catch (_error) {
      // The live region below still reports the result if the modal is opened directly.
    }
  };

  const replaceIllustration = (svg, animate = false) => {
    if (!illustrationHost || !svg) return;
    const currentStage = illustrationHost.querySelector("svg")?.dataset.treeStage;
    const nextStage = String(svg.match(/data-tree-stage="(\d+)"/)?.[1] ?? "");
    if (currentStage === nextStage && !animate) return;

    const insertIllustration = () => {
      illustrationHost.innerHTML = svg;
      const illustration = illustrationHost.querySelector("svg");
      illustration?.classList.remove("tree-growth-illustration");
      illustration?.classList.add("grove-tree-illustration");
      illustrationHost.classList.remove("is-phase-leaving");
      if (animate) {
        illustrationHost.classList.add("is-phase-entering");
        window.setTimeout(() => illustrationHost.classList.remove("is-phase-entering"), 700);
      }
    };

    if (!animate) {
      insertIllustration();
      return;
    }

    illustrationHost.classList.add("is-phase-leaving");
    window.setTimeout(insertIllustration, 180);
  };

  const applyState = (state, animate = false) => {
    if (!state) return;
    const dayLabel = `Day ${state.day} of ${state.duration}`;
    const values = {
      "[data-grove-name]": state.name,
      "[data-grove-scientific]": state.scientificName,
      "[data-grove-category]": state.category,
      "[data-grove-day]": dayLabel,
      "[data-grove-progress-label]": dayLabel,
      "[data-grove-status]": state.message,
      "[data-grove-fact]": state.funFact,
    };

    Object.entries(values).forEach(([selector, value]) => {
      const element = root.querySelector(selector);
      if (element) element.textContent = value || "";
    });

    const content = root.querySelector("[data-grove-content]");
    const progress = root.querySelector("[data-grove-progress]");
    const progressBar = root.querySelector("[data-grove-progress-bar]");
    content?.setAttribute("aria-label", `${state.name} tending progress`);
    progress?.setAttribute("aria-label", `${dayLabel} growth progress`);
    if (progressBar) progressBar.style.width = `${state.progressPercent}%`;

    if (waterButton) {
      const label = state.canWater ? `Water ${state.name}` : state.message;
      waterButton.setAttribute("aria-disabled", state.canWater ? "false" : "true");
      waterButton.setAttribute("aria-label", label);
      waterButton.dataset.wateredToday = state.wateredToday ? "true" : "false";
      waterButton.dataset.tooltip = state.wateredToday
        ? "Already watered"
        : state.canWater
          ? "Click me!"
          : state.message;
    }

    replaceIllustration(state.illustrationSvg, animate);
  };

  waterButton?.addEventListener("click", async () => {
    if (watering) return;
    if (root.dataset.authenticated !== "true") {
      if (parent !== window && typeof parent.showSignIn === "function") {
        parent.showSignIn();
      }
      return;
    }
    if (waterButton.getAttribute("aria-disabled") === "true") {
      if (waterButton.dataset.wateredToday === "true") {
        const status = root.querySelector("[data-grove-status]");
        status?.classList.remove("is-shaking");
        void status?.offsetWidth;
        status?.classList.add("is-shaking");
        return;
      }
      notifyParent(waterButton.getAttribute("aria-label"), false);
      return;
    }

    watering = true;
    waterButton.classList.add("is-loading");
    waterButton.setAttribute("aria-busy", "true");
    try {
      const response = await fetch(root.dataset.waterEndpoint, {
        method: "POST",
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (payload.state) applyState(payload.state, payload.advanced === true);
      if (parent === window) {
        notifyParent(payload.message || "Tree tending status updated.", response.ok && payload.success);
      }
      parent.postMessage(
        {
          type: "tree-growth-updated",
          state: payload.state,
          animate: payload.advanced === true,
          message: payload.message,
          success: response.ok && payload.success,
        },
        window.location.origin,
      );
    } catch (_error) {
      notifyParent("The tree could not be watered. Please try again.", false);
    } finally {
      watering = false;
      waterButton.classList.remove("is-loading");
      waterButton.removeAttribute("aria-busy");
    }
  });

  window.addEventListener("message", (event) => {
    if (event.origin !== window.location.origin || event.data?.type !== "tree-growth-updated") return;
    applyState(event.data.state, event.data.animate === true);
  });

  root.querySelector("[data-grove-close]")?.addEventListener("click", () => parent.hideFloating());
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") parent.hideFloating();
  });
});
