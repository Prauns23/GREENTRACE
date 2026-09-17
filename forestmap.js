(() => {
  "use strict";

  const STATUS_STYLES = {
    planned: { label: "Planned", color: "#7b838c", fill: "#d9dde1" },
    planted: { label: "Planted", color: "#23a545", fill: "#9be5ad" },
    monitored: { label: "Monitored", color: "#56bb8b", fill: "#bfe9d5" },
    completed: { label: "Completed", color: "#397d48", fill: "#b9dfbd" },
  };

  // Temporary display data only. It will be replaced by the Map V2 API once the
  // compartment schema and create/edit workflows are ready.
  const compartments = [
    {
      id: "mount-natib-01",
      name: "Mount Natib Forest 01",
      startedAt: "2026-09-03",
      year: "2026",
      status: "planned",
      hectares: 74.53,
      seedlings: 35,
      speciesMix: [
        { name: "Narra", scientificName: "Pterocarpus indicus", quantity: 20 },
        { name: "Lauan", scientificName: "Shorea spp.", quantity: 15 },
      ],
      photos: buildPhotos("1ST-VISIT-PLANTING.jpg", "Menro Admin", "Planned"),
      boundary: [
        [14.6964, 120.3351],
        [14.6995, 120.3427],
        [14.6953, 120.3481],
        [14.6907, 120.3432],
        [14.6919, 120.3372],
      ],
      samplingPoints: [
        [14.694, 120.3394],
        [14.694, 120.3412],
        [14.694, 120.343],
        [14.6953, 120.3397],
        [14.6953, 120.3415],
        [14.6953, 120.3433],
        [14.6966, 120.34],
        [14.6966, 120.3418],
        [14.6966, 120.3436],
      ],
    },
    {
      id: "mount-natib-02",
      name: "Mount Natib Forest 02",
      startedAt: "2026-10-14",
      year: "2026",
      status: "completed",
      hectares: 6.45,
      seedlings: 18,
      speciesMix: [
        { name: "Bagras", scientificName: "Eucalyptus deglupta", quantity: 18 },
      ],
      photos: buildPhotos("COMPLETION-REPORT.jpg", "Menro Admin", "Completed"),
      boundary: [
        [14.7058, 120.3598],
        [14.7082, 120.3656],
        [14.7042, 120.3678],
        [14.7025, 120.3625],
      ],
      samplingPoints: [
        [14.7043, 120.3621],
        [14.7045, 120.3641],
        [14.7058, 120.3624],
        [14.706, 120.3644],
      ],
    },
    {
      id: "mount-natib-03",
      name: "Mount Natib Forest 03",
      startedAt: "2026-09-08",
      year: "2025",
      status: "monitored",
      hectares: 3.22,
      seedlings: 12,
      speciesMix: [
        {
          name: "Mahogany",
          scientificName: "Swietenia macrophylla",
          quantity: 12,
        },
      ],
      photos: buildPhotos("MONITORING-VISIT.jpg", "Field Team", "Monitored"),
      boundary: [
        [14.6816, 120.3531],
        [14.6836, 120.3578],
        [14.6797, 120.3592],
        [14.6789, 120.3544],
      ],
      samplingPoints: [
        [14.6808, 120.355],
        [14.6809, 120.3567],
        [14.682, 120.3553],
        [14.6821, 120.357],
      ],
    },
  ];

  const reportedIssues = [
    {
      id: "reported-issue-01",
      name: "Reported forest issue",
      location: "Morong, Bataan",
      lat: 14.6875,
      lng: 120.3667,
    },
  ];
  const state = {
    scope: "all",
    status: "all",
    year: "all",
    search: "",
    selectedId: null,
  };
  const layers = {
    polygons: new Map(),
    sampling: new Map(),
    reports: null,
    vegetation: null,
    base: null,
  };
  let map;

  function buildPhotos(name, uploadedBy, category) {
    return Array.from({ length: 4 }, () => ({
      name,
      uploadedBy,
      category,
      size: "1.2 MB",
    }));
  }

  function escapeHtml(value) {
    return String(value).replace(
      /[&<>'"]/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#039;",
          '"': "&quot;",
        })[character],
    );
  }

  function formatDate(value) {
    return new Intl.DateTimeFormat("en-PH", {
      year: "numeric",
      month: "short",
      day: "2-digit",
    }).format(new Date(`${value}T00:00:00`));
  }

  function getCompartment(id) {
    return compartments.find((compartment) => compartment.id === id);
  }

  function boundaryCenter(boundary) {
    const totals = boundary.reduce(
      (sum, point) => ({ lat: sum.lat + point[0], lng: sum.lng + point[1] }),
      { lat: 0, lng: 0 },
    );
    return {
      lat: totals.lat / boundary.length,
      lng: totals.lng / boundary.length,
    };
  }

  function coordinateReference(compartment) {
    const center = boundaryCenter(compartment.boundary);
    return `${center.lat.toFixed(5)}, ${center.lng.toFixed(5)}`;
  }

  function locationReference(compartment) {
    return compartment.locationReference || coordinateReference(compartment);
  }

  async function resolveLocationReference(compartment) {
    if (compartment.locationReference || compartment.locationLookupPending)
      return;

    compartment.locationLookupPending = true;
    const center = boundaryCenter(compartment.boundary);

    try {
      const response = await fetch(
        `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(center.lat)}&lon=${encodeURIComponent(center.lng)}&zoom=10&addressdetails=1`,
        {
          headers: { Accept: "application/json" },
        },
      );
      if (!response.ok) throw new Error("Location lookup failed");

      const result = await response.json();
      const address = result.address || {};
      const locality =
        address.village ||
        address.town ||
        address.city ||
        address.municipality ||
        address.county;
      const province = address.state || address.province;
      compartment.locationReference =
        [locality, province, address.country].filter(Boolean).join(", ") ||
        result.display_name ||
        coordinateReference(compartment);
    } catch (error) {
      // Coordinates remain the accurate geographic fallback when a reverse-geocoding service is unavailable.
      compartment.locationReference = coordinateReference(compartment);
    } finally {
      compartment.locationLookupPending = false;
      if (state.selectedId === compartment.id) renderDetail(compartment);
      else renderList();
    }
  }

  function visibleCompartments() {
    const search = state.search.trim().toLowerCase();
    if (state.scope === "reports" || state.scope === "archived") return [];

    return compartments.filter((compartment) => {
      const haystack =
        `${compartment.name} ${locationReference(compartment)}`.toLowerCase();
      return (
        (state.status === "all" || compartment.status === state.status) &&
        (state.year === "all" || compartment.year === state.year) &&
        (!search || haystack.includes(search))
      );
    });
  }

  function initializeMap() {
    map = L.map("forestMap", {
      zoomControl: true,
      attributionControl: true,
    }).setView([14.694, 120.348], 13);

    const osm = L.tileLayer(
      "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
      {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors",
      },
    );
    const satellite = L.tileLayer(
      "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
      {
        maxZoom: 19,
        attribution: "Tiles &copy; Esri",
      },
    );

    layers.base = { osm, satellite };
    osm.addTo(map);
    layers.vegetation = L.tileLayer(
      "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
      {
        opacity: 0.55,
        className: "leaflet-vegetation-proxy",
        attribution: "",
      },
    );

    layers.reports = L.layerGroup(
      reportedIssues.map((issue) =>
        L.circleMarker([issue.lat, issue.lng], {
          radius: 8,
          color: "#b3261e",
          fillColor: "#e9736a",
          fillOpacity: 1,
          weight: 2,
        }).bindTooltip(`${issue.name}: ${issue.location}`, {
          direction: "top",
        }),
      ),
    );
  }

  function createCompartmentLayers() {
    compartments.forEach((compartment) => {
      const style = STATUS_STYLES[compartment.status];
      const polygon = L.polygon(compartment.boundary, {
        color: style.color,
        fillColor: style.fill,
        fillOpacity: 0.45,
        weight: 2,
      }).on("click", () => selectCompartment(compartment.id));

      const sampling = L.layerGroup(
        compartment.samplingPoints.map((point) =>
          L.circleMarker(point, {
            radius: 4,
            color: style.color,
            fillColor: style.color,
            fillOpacity: 1,
            weight: 1,
          }).on("click", () => selectCompartment(compartment.id)),
        ),
      );

      layers.polygons.set(compartment.id, polygon);
      layers.sampling.set(compartment.id, sampling);
    });
  }

  function refreshMapLayers() {
    const visible = new Set(
      visibleCompartments().map((compartment) => compartment.id),
    );
    const samplingVisible = document.getElementById(
      "samplingPlotsToggle",
    ).checked;

    layers.polygons.forEach((polygon, id) => {
      if (visible.has(id)) polygon.addTo(map);
      else map.removeLayer(polygon);
    });
    layers.sampling.forEach((sampling, id) => {
      if (visible.has(id) && samplingVisible) sampling.addTo(map);
      else map.removeLayer(sampling);
    });

    if (state.scope === "all" || state.scope === "reports")
      layers.reports.addTo(map);
    else map.removeLayer(layers.reports);
  }

  function updateMetrics() {
    const visible = visibleCompartments();
    const total = visible.reduce(
      (sum, compartment) => sum + compartment.hectares,
      0,
    );
    document.getElementById("netArea").textContent = total.toFixed(2);
    document.getElementById("forestPlotCount").textContent = visible.length;
  }

  function renderList() {
    const list = document.getElementById("compartmentList");
    const visible = visibleCompartments();

    if (state.scope === "reports") {
      list.innerHTML =
        '<p class="mapv2-empty-state">Reported issues are shown on the map.</p>';
      return;
    }
    if (state.scope === "archived") {
      list.innerHTML =
        '<p class="mapv2-empty-state">Archived compartments will appear here when the archive workflow is added.</p>';
      return;
    }
    if (!visible.length) {
      list.innerHTML =
        '<p class="mapv2-empty-state">No compartments match these filters.</p>';
      return;
    }

    const addCompartmentCard = `
            <button type="button" class="mapv2-add-compartment-card" id="addCompartmentCard" aria-label="Add compartment">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
            </button>`;

    list.innerHTML =
      addCompartmentCard +
      visible
        .map((compartment) => {
          const status = STATUS_STYLES[compartment.status];
          return `
                <article class="mapv2-compartment-card" data-compartment-id="${compartment.id}" tabindex="0">
                    <div class="mapv2-tree-icon"><i class="fa-solid fa-tree" aria-hidden="true"></i></div>
                    <div class="mapv2-card-main">
                        <h3>${escapeHtml(compartment.name)}</h3>
                        <p>${escapeHtml(locationReference(compartment))} · ${compartment.hectares.toFixed(2)} hectares</p>
                        <small>Started: ${formatDate(compartment.startedAt)}</small>
                    </div>
                    <div class="mapv2-card-actions">
                        <button class="mapv2-icon-button mapv2-card-menu" type="button" data-view-id="${compartment.id}" aria-label="View ${escapeHtml(compartment.name)} details"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                        <span class="mapv2-status-badge" style="--badge-color: ${status.color}; --badge-fill: ${status.fill}">${status.label}</span>
                    </div>
                </article>`;
        })
        .join("");
  }

  function renderDetail(compartment) {
    const status = STATUS_STYLES[compartment.status];
    const detail = document.getElementById("compartmentDetailView");
    detail.innerHTML = `
            <header class="mapv2-detail-heading">
                <button type="button" class="mapv2-back-button" id="backToCompartments" aria-label="Back to compartment list"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                <div><h2>${escapeHtml(compartment.name)}</h2><p>${escapeHtml(locationReference(compartment))}</p></div>
            </header>
            <div class="mapv2-detail-stats">
                <div><span>Gross area</span><strong>${compartment.hectares.toFixed(2)} <small>ha</small></strong></div>
                <div><span>Seedlings/saplings</span><strong>${compartment.seedlings}</strong></div>
            </div>
            <div class="mapv2-detail-field"><span>Location</span><p>${escapeHtml(locationReference(compartment))}</p><small>Derived from the compartment boundary.</small></div>
            <label class="mapv2-detail-field"><span>Status</span>
                <select id="detailStatusSelect">
                    ${Object.entries(STATUS_STYLES)
                      .map(
                        ([key, item]) =>
                          `<option value="${key}" ${key === compartment.status ? "selected" : ""}>${item.label}</option>`,
                      )
                      .join("")}
                </select>
            </label>
            <section class="mapv2-species-section">
                <h3>Species mix</h3>
                ${compartment.speciesMix.map((species) => `<article class="mapv2-species-card"><div><strong>${escapeHtml(species.name)}</strong><em>${escapeHtml(species.scientificName)}</em></div><b>×${species.quantity}</b></article>`).join("")}
            </section>
            <section class="mapv2-photos-section">
                <h3>Photos</h3>
                <div class="mapv2-photo-filters"><select aria-label="Filter photo category"><option>Category</option></select><select aria-label="Filter photo date"><option>Date</option></select><button type="button">Clear filters</button></div>
                <div class="mapv2-photo-table" role="table">
                    <div class="mapv2-photo-row mapv2-photo-head" role="row"><span>Name</span><span>Uploaded by</span><span>Category</span><span>Image size</span><span></span></div>
                    <p class="mapv2-photo-date">Today</p>
                    ${compartment.photos.map((photo) => `<div class="mapv2-photo-row" role="row"><span><i class="fa-regular fa-image" aria-hidden="true"></i> ${escapeHtml(photo.name)}</span><span>${escapeHtml(photo.uploadedBy)}</span><span>${escapeHtml(photo.category)}</span><span>${escapeHtml(photo.size)}</span><button class="mapv2-icon-button" type="button" aria-label="Photo options"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button></div>`).join("")}
                </div>
            </section>`;

    document
      .getElementById("detailStatusSelect")
      .addEventListener("change", (event) =>
        updateCompartmentStatus(compartment.id, event.target.value),
      );
    document
      .getElementById("backToCompartments")
      .addEventListener("click", showList);
    document.getElementById("compartmentListView").hidden = true;
    detail.hidden = false;
  }

  function selectCompartment(id) {
    const compartment = getCompartment(id);
    if (!compartment) return;
    state.selectedId = id;
    renderDetail(compartment);
    resolveLocationReference(compartment);
    const polygon = layers.polygons.get(id);
    if (polygon)
      map.fitBounds(polygon.getBounds(), { padding: [40, 40], maxZoom: 15 });
  }

  function showList() {
    state.selectedId = null;
    document.getElementById("compartmentDetailView").hidden = true;
    document.getElementById("compartmentListView").hidden = false;
    renderList();
  }

  function updateCompartmentStatus(id, nextStatus) {
    const compartment = getCompartment(id);
    if (!compartment || !STATUS_STYLES[nextStatus]) return;
    compartment.status = nextStatus;
    const style = STATUS_STYLES[nextStatus];
    layers.polygons
      .get(id)
      .setStyle({ color: style.color, fillColor: style.fill });
    layers.sampling
      .get(id)
      .eachLayer((marker) =>
        marker.setStyle({ color: style.color, fillColor: style.color }),
      );
    renderDetail(compartment);
    refreshMapLayers();
    updateMetrics();
  }

  function refreshUI() {
    refreshMapLayers();
    updateMetrics();
    if (state.selectedId && getCompartment(state.selectedId))
      renderDetail(getCompartment(state.selectedId));
    else renderList();
  }

  function setBasemap(name) {
    Object.values(layers.base).forEach((layer) => map.removeLayer(layer));
    layers.base[name].addTo(map);
    const basemapToggle = document.querySelector(".mapv2-basemap-toggle");
    if (basemapToggle) basemapToggle.dataset.active = name;
    document.querySelectorAll("[data-basemap]").forEach((button) => {
      const isActive = button.dataset.basemap === name;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-selected", isActive ? "true" : "false");
    });
  }

  function showMapNotice(message) {
    const notice = document.getElementById("mapNotice");
    notice.textContent = message;
    notice.hidden = false;
  }

  function bindControls() {
    document
      .getElementById("searchInput")
      .addEventListener("input", (event) => {
        state.search = event.target.value;
        refreshUI();
      });
    document
      .getElementById("mapScopeSelect")
      .addEventListener("change", (event) => {
        state.scope = event.target.value;
        showList();
        refreshUI();
      });
    document
      .getElementById("statusFilter")
      .addEventListener("change", (event) => {
        state.status = event.target.value;
        showList();
        refreshUI();
      });
    document
      .getElementById("yearFilter")
      .addEventListener("change", (event) => {
        state.year = event.target.value;
        showList();
        refreshUI();
      });
    document
      .getElementById("samplingPlotsToggle")
      .addEventListener("change", refreshMapLayers);
    document
      .getElementById("vegetationOverlayToggle")
      .addEventListener("change", (event) => {
        if (event.target.checked) layers.vegetation.addTo(map);
        else map.removeLayer(layers.vegetation);
      });
    document
      .querySelectorAll("[data-basemap]")
      .forEach((button) =>
        button.addEventListener("click", () =>
          setBasemap(button.dataset.basemap),
        ),
      );

    document
      .getElementById("compartmentList")
      .addEventListener("click", (event) => {
        if (event.target.closest("#addCompartmentCard")) {
          window.addNewCompartment();
          return;
        }
        const button = event.target.closest("[data-view-id]");
        const card = event.target.closest("[data-compartment-id]");
        if (button) selectCompartment(button.dataset.viewId);
        else if (card) selectCompartment(card.dataset.compartmentId);
      });
    document
      .getElementById("compartmentList")
      .addEventListener("keydown", (event) => {
        const card = event.target.closest("[data-compartment-id]");
        if (card && (event.key === "Enter" || event.key === " ")) {
          event.preventDefault();
          selectCompartment(card.dataset.compartmentId);
        }
      });

    const menuButton = document.getElementById("mapFilterMenuBtn");
    const menu = document.getElementById("mapFilterMenu");
    menuButton.addEventListener("click", () => {
      const isOpen = !menu.hidden;
      menu.hidden = isOpen;
      menuButton.setAttribute("aria-expanded", String(!isOpen));
    });
    menu.addEventListener("click", (event) => {
      const option = event.target.closest("[data-map-scope]");
      if (!option) return;
      state.scope = option.dataset.mapScope;
      document.getElementById("mapScopeSelect").value = "all";
      menu.hidden = true;
      menuButton.setAttribute("aria-expanded", "false");
      showList();
      refreshUI();
    });
    document.addEventListener("click", (event) => {
      if (!event.target.closest(".mapv2-menu-wrap")) {
        menu.hidden = true;
        menuButton.setAttribute("aria-expanded", "false");
      }
    });
  }

  window.addNewCompartment = () =>
    showMapNotice(
      "The compartment drawing workflow will be connected after the Map V2 schema is ready.",
    );

  document.addEventListener("DOMContentLoaded", () => {
    initializeMap();
    createCompartmentLayers();
    bindControls();
    refreshUI();
  });
})();
