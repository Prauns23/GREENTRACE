(() => {
  "use strict";
  const species = Array.isArray(window.mapV2Species) ? window.mapV2Species : [];
  const rows = document.getElementById("speciesRows");
  const form = document.getElementById("addCompartmentForm");

  // Keep each selected species unique across all rows.
  const selectedSpeciesIds = () =>
    new Set(
      [...rows.querySelectorAll(".species-row__select")]
        .map((select) => select.value)
        .filter(Boolean),
    );

  const speciesOptions = (selectedId = "", unavailableIds = new Set()) =>
    species
      .map((item) => {
        const id = String(item.id);
        const isSelected = id === String(selectedId);
        const isUnavailable = unavailableIds.has(id) && !isSelected;
        return `<option value="${item.id}"${isSelected ? " selected" : ""}${isUnavailable ? " disabled" : ""}>${item.name}</option>`;
      })
      .join("");

  // Grey out species already used in another row and stop extra rows at the limit.
  const updateSpeciesChoices = () => {
    const unavailableIds = selectedSpeciesIds();
    [...rows.querySelectorAll(".species-row__select")].forEach((select) => {
      select.innerHTML = speciesOptions(select.value, unavailableIds);
    });
    document.getElementById("addSpeciesRow").disabled =
      unavailableIds.size >= species.length;
  };
  // Show a short number animation after the plus or minus button is used.
  const animateQuantity = (row, nextValue, direction) => {
    const input = row.querySelector(".species-row__input");
    const quantity = row.querySelector(".species-row__quantity");
    const output = row.querySelector(".species-row__quantity-value output");
    input.value = String(nextValue);
    output.textContent = String(nextValue);
    quantity.classList.remove("is-animating", "is-increasing", "is-decreasing");
    void quantity.offsetWidth;
    quantity.classList.add(
      "is-animating",
      direction > 0 ? "is-increasing" : "is-decreasing",
    );
    window.setTimeout(
      () =>
        quantity.classList.remove(
          "is-animating",
          "is-increasing",
          "is-decreasing",
        ),
      320,
    );
  };
  // Add one species and quantity row with the next available species selected.
  const createRow = () => {
    const unavailableIds = selectedSpeciesIds();
    const firstAvailableSpecies = species.find(
      (item) => !unavailableIds.has(String(item.id)),
    );
    if (!firstAvailableSpecies) return;

    const row = document.createElement("div");
    row.className = "species-row";
    row.innerHTML = `<div class="species-row__select-wrap"><select class="species-row__select" aria-label="Tree species">${speciesOptions(firstAvailableSpecies.id, unavailableIds)}</select><i class="fas fa-chevron-down species-row__chevron" aria-hidden="true"></i></div><div class="species-row__quantity"><button type="button" data-step="-1" aria-label="Decrease quantity"><i class="fa-solid fa-minus" aria-hidden="true"></i></button><label class="species-row__quantity-value"><input class="species-row__input" type="number" min="1" max="1000000" value="25" aria-label="Planned seedlings"><output aria-hidden="true">25</output></label><button type="button" data-step="1" aria-label="Increase quantity"><i class="fa-solid fa-plus" aria-hidden="true"></i></button></div><button type="button" class="species-row__remove" aria-label="Remove species"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>`;
    row.addEventListener("click", (event) => {
      const step = event.target.closest("[data-step]");
      if (step) {
        const input = row.querySelector(".species-row__input");
        const nextValue = Math.min(
          1000000,
          Math.max(1, Number(input.value || 1) + Number(step.dataset.step)),
        );
        animateQuantity(row, nextValue, Number(step.dataset.step));
      }
      if (
        event.target.closest(".species-row__remove") &&
        rows.children.length > 1
      ) {
        row.remove();
        updateSpeciesChoices();
      }
    });
    row
      .querySelector(".species-row__input")
      .addEventListener("input", (event) => {
        const value = Math.min(
          1000000,
          Math.max(1, Number(event.target.value || 1)),
        );
        row.querySelector(".species-row__quantity-value output").textContent =
          String(value);
      });
    row
      .querySelector(".species-row__select")
      .addEventListener("change", updateSpeciesChoices);
    rows.append(row);
    updateSpeciesChoices();
  };
  createRow();

  // Let the user add another species row or leave the add flow safely.
  document.getElementById("addSpeciesRow").addEventListener("click", createRow);
  document
    .getElementById("cancelCompartmentModal")
    .addEventListener("click", () => {
      parent.postMessage(
        { type: "mapv2:cancel-compartment-drawing" },
        window.location.origin,
      );
      parent.hideFloating();
    });

  // Validate the form, then ask the parent map page to begin boundary drawing.
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const formData = new FormData(form);
    const speciesMix = [...rows.children].map((row) => ({
      tree_species_id: Number(row.querySelector(".species-row__select").value),
      planned_quantity: Number(row.querySelector(".species-row__input").value),
    }));
    if (
      !speciesMix.length ||
      speciesMix.some(
        (item) => !item.tree_species_id || item.planned_quantity < 1,
      )
    ) {
      alert("Add at least one species with a planned quantity.");
      return;
    }
    if (new Set(speciesMix.map((item) => item.tree_species_id)).size !== speciesMix.length) {
      alert("Each tree species can only be selected once.");
      return;
    }
    parent.postMessage(
      {
        type: "mapv2:begin-compartment-boundary",
        payload: {
          name: formData.get("name").trim(),
          status: formData.get("status"),
          date_started: formData.get("date_started"),
          species_mix: speciesMix,
        },
      },
      window.location.origin,
    );
    parent.hideFloating();
  });
})();
