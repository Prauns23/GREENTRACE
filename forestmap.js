(() => {
  "use strict";

  const STATUS_STYLES = {
    planned: { label: "Planned", color: "#7b838c", fill: "#d9dde1" },
    planted: { label: "Planted", color: "#23a545", fill: "#9be5ad" },
    monitored: { label: "Monitored", color: "#56bb8b", fill: "#bfe9d5" },
    completed: { label: "Completed", color: "#397d48", fill: "#b9dfbd" },
  };
  const DEFAULT_PLANTING_SPACING_M = 3;
  const AUTO_PLOT_SPACING_M = 35;
  const TREE_CLUSTER_ZOOM = 14;
  const MAP_MIN_ZOOM = 13;

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
    },
    {
      id: "mount-natib-02",
      name: "Mount Natib Forest 02",
      startedAt: "2026-10-14",
      year: "2026",
      status: "completed",
      hectares: 6.45,
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
    },
    {
      id: "mount-natib-03",
      name: "Mount Natib Forest 03",
      startedAt: "2026-09-08",
      year: "2025",
      status: "monitored",
      hectares: 3.22,
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
    treeSpecies: new Map(),
    treeClusters: new Map(),
    reports: null,
    barangays: null,
    vegetation: null,
    base: null,
  };
  let barangayFeatures = [];
  const treeSpeciesByName = new Map();
  let map;

  function buildPhotos(name, uploadedBy, category) {
    return Array.from({ length: 4 }, () => ({
      name,
      uploadedBy,
      category,
      size: "1.2 MB",
      date: "Today",
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

  function plannedTreeCount(compartment) {
    return compartment.speciesMix.reduce((total, species) => total + species.quantity, 0);
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
    if (compartment.barangay) {
      const { name, municipality, province } = compartment.barangay;
      return [name, municipality, province].filter(Boolean).join(", ");
    }
    return coordinateReference(compartment);
  }

  function pointInRing(point, ring) {
    let inside = false;
    for (let current = 0, previous = ring.length - 1; current < ring.length; previous = current++) {
      const [currentLng, currentLat] = ring[current];
      const [previousLng, previousLat] = ring[previous];
      const intersects = ((currentLat > point[1]) !== (previousLat > point[1]))
        && (point[0] < ((previousLng - currentLng) * (point[1] - currentLat)) / (previousLat - currentLat) + currentLng);
      if (intersects) inside = !inside;
    }
    return inside;
  }

  function pointInGeometry(point, geometry) {
    const polygons = geometry.type === "Polygon" ? [geometry.coordinates] : geometry.coordinates;
    return polygons.some((polygon) => pointInRing(point, polygon[0]) && !polygon.slice(1).some((hole) => pointInRing(point, hole)));
  }

  function matchCompartmentsToBarangays() {
    compartments.forEach((compartment) => {
      const center = boundaryCenter(compartment.boundary);
      const match = barangayFeatures.find((feature) => pointInGeometry([center.lng, center.lat], feature.geometry));
      compartment.barangay = match ? match.properties : null;
    });
  }

  async function loadBarangayBoundaries() {
    try {
      const response = await fetch("actions/mapv2/get_barangay_boundaries.php", { headers: { Accept: "application/geo+json" } });
      if (!response.ok) throw new Error("Barangay boundaries could not be loaded");
      const collection = await response.json();
      barangayFeatures = Array.isArray(collection.features) ? collection.features : [];
      layers.barangays = L.geoJSON(collection, {
        style: { color: "#ffffff", weight: 1.5, dashArray: "5 5", fillOpacity: 0 },
        onEachFeature: (feature, layer) => {
          layer.bindTooltip(feature.properties.name, { sticky: true });
          layer.on("click", () => {
            // Keep the barangay label without leaving a browser focus outline.
            layer.getElement()?.blur();
            layer.openTooltip();
          });
        },
      });
      matchCompartmentsToBarangays();
      refreshMapLayers();
      refreshUI();
    } catch (error) {
      console.warn("Map V2 barangay boundaries are unavailable.", error);
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
      minZoom: MAP_MIN_ZOOM,
    }).setView([14.694, 120.348], MAP_MIN_ZOOM);

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
    map.on("zoomend", () => {
      if (layers.treeSpecies.size) rebuildTreeSpeciesLayers();
    });
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

  function speciesSpacing(species) {
    return treeSpeciesByName.get(species.name.toLowerCase())?.planting_spacing_m || DEFAULT_PLANTING_SPACING_M;
  }

  function generateTreeSpeciesPoints(boundary, speciesMix) {
    const quantity = speciesMix.reduce((total, species) => total + species.quantity, 0);
    const ring = boundary.map(([lat, lng]) => [lng, lat]);
    const latitudes = boundary.map(([lat]) => lat);
    const longitudes = boundary.map(([, lng]) => lng);
    const minLat = Math.min(...latitudes);
    const maxLat = Math.max(...latitudes);
    const minLng = Math.min(...longitudes);
    const maxLng = Math.max(...longitudes);
    const centerLat = (minLat + maxLat) / 2;
    // A mixed plot uses the largest recommended spacing so every species still
    // respects its minimum planting distance in one neat, shared grid.
    const actualGridSpacingM = Math.max(...speciesMix.map(speciesSpacing));
    let displayGridSpacingM = AUTO_PLOT_SPACING_M;

    const getCandidates = (spacingM) => {
      const latitudeStep = spacingM / 111_320;
      const longitudeStep = spacingM / (111_320 * Math.cos((centerLat * Math.PI) / 180));
      const candidates = [];
      const startLat = Math.floor(minLat / latitudeStep) * latitudeStep;
      const startLng = Math.floor(minLng / longitudeStep) * longitudeStep;

      for (let lat = startLat; lat <= maxLat; lat += latitudeStep) {
        for (let lng = startLng; lng <= maxLng; lng += longitudeStep) {
          if (pointInRing([lng, lat], ring)) candidates.push([lat, lng]);
        }
      }
      return candidates;
    };

    let candidates = getCandidates(displayGridSpacingM);
    // A narrow compartment may not fit every tree at the expanded preview scale.
    // Reduce only the visual scale until all species-mix quantities stay visible.
    while (candidates.length < quantity && displayGridSpacingM > actualGridSpacingM) {
      displayGridSpacingM = Math.max(actualGridSpacingM, displayGridSpacingM / 1.5);
      candidates = getCandidates(displayGridSpacingM);
    }

    const selected = candidates
      .sort(([leftLat, leftLng], [rightLat, rightLng]) => {
        const leftDistance = (leftLat - centerLat) ** 2 + (leftLng - ((minLng + maxLng) / 2)) ** 2;
        const rightDistance = (rightLat - centerLat) ** 2 + (rightLng - ((minLng + maxLng) / 2)) ** 2;
        return leftDistance - rightDistance;
      })
      .slice(0, quantity)
      .sort(([leftLat, leftLng], [rightLat, rightLng]) => leftLat - rightLat || leftLng - rightLng);

    return selected.map((point, index) => ({
      point,
      species: speciesMix.find((species, speciesIndex) => index < speciesMix.slice(0, speciesIndex + 1).reduce((total, item) => total + item.quantity, 0)),
      actualGridSpacingM,
      displayGridSpacingM,
    }));
  }

  function treeSpeciesDotStyle(status) {
    const style = STATUS_STYLES[status];
    return {
      radius: 5,
      color: "#ffffff",
      weight: 1.5,
      opacity: 1,
      fillColor: style.color,
      fillOpacity: 1,
    };
  }

  function createTreeCluster(compartment) {
    const style = STATUS_STYLES[compartment.status];
    const treeCount = plannedTreeCount(compartment);
    const cluster = L.marker(boundaryCenter(compartment.boundary), {
      icon: L.divIcon({
        className: "mapv2-tree-cluster-icon",
        html: `<span class="mapv2-tree-cluster" style="--cluster-color: ${style.color}; --cluster-fill: ${style.fill}"><strong>${treeCount}</strong></span>`,
        iconSize: [38, 38],
        iconAnchor: [19, 19],
      }),
      keyboard: true,
      title: String(treeCount),
    }).on("click", () => selectCompartment(compartment.id));

    return cluster;
  }

  function createTreeSpeciesLayer(compartment) {
    const style = STATUS_STYLES[compartment.status];
    return L.layerGroup(
      generateTreeSpeciesPoints(compartment.boundary, compartment.speciesMix).map(({ point, species, actualGridSpacingM, displayGridSpacingM }) =>
        L.circleMarker(point, treeSpeciesDotStyle(compartment.status))
          .bindTooltip(`${species.name} · ${speciesSpacing(species)} m recommended · ${actualGridSpacingM} m actual grid · ${displayGridSpacingM.toFixed(1)} m map preview · ${style.label}`, { direction: "top" })
          .on("click", () => selectCompartment(compartment.id)),
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
        dashArray: "8 6",
      }).on("click", () => selectCompartment(compartment.id));

      layers.polygons.set(compartment.id, polygon);
      layers.treeSpecies.set(compartment.id, createTreeSpeciesLayer(compartment));
      layers.treeClusters.set(compartment.id, createTreeCluster(compartment));
    });
  }

  function rebuildTreeSpeciesLayers() {
    layers.treeSpecies.forEach((layer) => map.removeLayer(layer));
    layers.treeClusters.forEach((layer) => map.removeLayer(layer));
    layers.treeSpecies.clear();
    layers.treeClusters.clear();
    compartments.forEach((compartment) => {
      layers.treeSpecies.set(compartment.id, createTreeSpeciesLayer(compartment));
      layers.treeClusters.set(compartment.id, createTreeCluster(compartment));
    });
    refreshMapLayers();
  }

  async function loadTreeSpeciesSpacing() {
    try {
      const response = await fetch("actions/mapv2/get_tree_species_spacing.php", { headers: { Accept: "application/json" } });
      if (!response.ok) throw new Error("Tree species spacing could not be loaded");
      const payload = await response.json();
      (payload.species || []).forEach((species) => {
        if (species.planting_spacing_m > 0) treeSpeciesByName.set(species.name.toLowerCase(), species);
      });
      rebuildTreeSpeciesLayers();
    } catch (error) {
      console.warn("Tree species spacing is unavailable; using the temporary 3 m grid.", error);
    }
  }

  function refreshMapLayers() {
    const visible = new Set(
      visibleCompartments().map((compartment) => compartment.id),
    );
    const treeSpeciesVisible = document.getElementById(
      "treeSpeciesToggle",
    ).checked;
    const useTreeClusters = map.getZoom() < TREE_CLUSTER_ZOOM;

    layers.polygons.forEach((polygon, id) => {
      if (visible.has(id)) polygon.addTo(map);
      else map.removeLayer(polygon);
    });
    layers.treeSpecies.forEach((treeSpecies, id) => {
      if (visible.has(id) && treeSpeciesVisible && !useTreeClusters) treeSpecies.addTo(map);
      else map.removeLayer(treeSpecies);
    });
    layers.treeClusters.forEach((treeCluster, id) => {
      if (visible.has(id) && treeSpeciesVisible && useTreeClusters) treeCluster.addTo(map);
      else map.removeLayer(treeCluster);
    });

    if (state.scope === "all" || state.scope === "reports")
      layers.reports.addTo(map);
    else map.removeLayer(layers.reports);

    if (layers.barangays && document.getElementById("barangayBoundariesToggle").checked) {
      layers.barangays.addTo(map);
      layers.barangays.bringToBack();
    } else if (layers.barangays) {
      map.removeLayer(layers.barangays);
    }
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
                        <small>Started: <strong>${formatDate(compartment.startedAt)}</strong></small>
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
    const categoryOptions = Object.entries(STATUS_STYLES)
      .map(([key, item]) => `<option value="${key}">${item.label}</option>`)
      .join("");
    const dateOptions = [...new Set(compartment.photos.map((photo) => photo.date))]
      .map((date) => `<option value="${escapeHtml(date)}">${escapeHtml(date)}</option>`)
      .join("");
    detail.innerHTML = `
            <header class="mapv2-detail-heading">
                <button type="button" class="mapv2-back-button" id="backToCompartments" aria-label="Back to compartment list"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                <div><h2>${escapeHtml(compartment.name)}</h2><p>${escapeHtml(locationReference(compartment))}</p></div>
            </header>
              <div class="mapv2-detail-scroll">
            <div class="mapv2-detail-stats">
                <div><span>Gross area</span><strong>${compartment.hectares.toFixed(2)} <small>ha</small></strong></div>
                <div><span>Trees planted</span><strong>${plannedTreeCount(compartment)}</strong></div>
            </div>
            <div class="mapv2-detail-field"><span>Location</span><p>${escapeHtml(locationReference(compartment))}</p><small>Matched from the compartment's barangay boundary.</small></div>
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
                <div class="mapv2-photo-filters"><div class="mapv2-filter-select"><select id="photoCategoryFilter" aria-label="Filter photo category"><option value="all">All categories</option>${categoryOptions}</select><i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i></div><div class="mapv2-filter-select"><select id="photoDateFilter" aria-label="Filter photo date"><option value="all">All dates</option>${dateOptions}</select><i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i></div><button type="button" id="clearPhotoFilters" hidden>Clear filters</button></div>
                <div class="mapv2-photo-table" role="table">
                    <div class="mapv2-photo-row mapv2-photo-head" role="row"><span>Name</span><span>Uploaded by</span><span>Category</span><span>Size</span><span>Date</span><span></span></div>
                    <p class="mapv2-photo-date">Today</p>
                    ${compartment.photos.map((photo) => `<div class="mapv2-photo-row" role="row" data-photo-category="${escapeHtml(photo.category.toLowerCase())}" data-photo-date="${escapeHtml(photo.date)}"><span title="${escapeHtml(photo.name)}"><i class="fa-regular fa-image" aria-hidden="true"></i> ${escapeHtml(photo.name)}</span><span title="${escapeHtml(photo.uploadedBy)}">${escapeHtml(photo.uploadedBy)}</span><span title="${escapeHtml(photo.category)}">${escapeHtml(photo.category)}</span><span title="${escapeHtml(photo.size)}">${escapeHtml(photo.size)}</span><span title="${escapeHtml(photo.date)}">${escapeHtml(photo.date)}</span><button class="mapv2-icon-button" type="button" aria-label="Photo options"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button></div>`).join("")}
                </div>
            </section>
              </div>`;

    const photoRows = detail.querySelectorAll(".mapv2-photo-row[data-photo-category]");
    const categoryFilter = document.getElementById("photoCategoryFilter");
    const dateFilter = document.getElementById("photoDateFilter");
    const clearPhotoFilters = document.getElementById("clearPhotoFilters");
    const updateClearButton = () => {
      clearPhotoFilters.hidden = categoryFilter.value === "all" && dateFilter.value === "all";
    };
    const filterPhotoRows = () => {
      const selectedCategory = categoryFilter.value;
      const selectedDate = dateFilter.value;
      photoRows.forEach((row) => {
        row.hidden = (selectedCategory !== "all" && row.dataset.photoCategory !== selectedCategory)
          || (selectedDate !== "all" && row.dataset.photoDate !== selectedDate);
      });
      updateClearButton();
    };
    categoryFilter.addEventListener("change", filterPhotoRows);
    dateFilter.addEventListener("change", filterPhotoRows);
    clearPhotoFilters.addEventListener("click", () => {
      categoryFilter.value = "all";
      dateFilter.value = "all";
      filterPhotoRows();
    });

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
    layers.treeSpecies
      .get(id)
      .eachLayer((marker) =>
        marker.setStyle(treeSpeciesDotStyle(nextStatus)),
      );
    rebuildTreeSpeciesLayers();
    renderDetail(compartment);
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
      .getElementById("treeSpeciesToggle")
      .addEventListener("change", refreshMapLayers);
    document
      .getElementById("barangayBoundariesToggle")
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
    loadBarangayBoundaries();
    loadTreeSpeciesSpacing();
  });
})();
