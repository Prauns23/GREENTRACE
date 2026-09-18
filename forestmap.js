(() => {
  "use strict";

  // Keep map colours and status names in one place.
  const STATUS_STYLES = {
    planned: { label: "Planned", color: "#7b838c", fill: "#d9dde1" },
    planted: { label: "Planted", color: "#23a545", fill: "#9be5ad" },
    monitored: { label: "Monitored", color: "#56bb8b", fill: "#bfe9d5" },
    low_survival: { label: "Low survival", color: "#c14b45", fill: "#f3c4c0" },
    completed: { label: "Completed", color: "#397d48", fill: "#b9dfbd" },
  };
  const DEFAULT_PLANTING_SPACING_M = 3;
  const AUTO_PLOT_SPACING_M = 35;
  const TREE_CLUSTER_ZOOM = 14;
  const MAP_MIN_ZOOM = 13;

  // Compartments are loaded from the Map V2 API. Temporary sample plots have
  // been removed now that real compartment boundaries can be created.
  const compartments = [];

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
  const treeSpeciesById = new Map();
  let map;
  let drawingDraft = null;
  let drawingPreview = null;
  let drawingToolbar = null;
  let creatingCompartment = false;

  // Create temporary photo rows until the photo upload feature saves real files.
  function buildPhotos(name, uploadedBy, category) {
    const dateValue = (months = 0, days = 0, years = 0) => {
      const date = new Date();
      date.setFullYear(date.getFullYear() + years);
      date.setMonth(date.getMonth() + months);
      date.setDate(date.getDate() + days);
      return date.toISOString().slice(0, 10);
    };

    return [
      { name, uploadedBy, category, size: "1.2 MB", uploadedAt: dateValue() },
      {
        name,
        uploadedBy,
        category,
        size: "1.5 MB",
        uploadedAt: dateValue(0, -6),
      },
      {
        name,
        uploadedBy,
        category,
        size: "1.4 MB",
        uploadedAt: dateValue(-1, -3),
      },
      {
        name,
        uploadedBy,
        category,
        size: "750 KB",
        uploadedAt: dateValue(0, 0, -1),
      },
    ];
  }

  function formatPhotoDate(value) {
    return new Intl.DateTimeFormat("en-PH", {
      year: "numeric",
      month: "short",
      day: "2-digit",
    }).format(new Date(`${value}T00:00:00`));
  }

  function monthKey(value) {
    const date = value instanceof Date ? value : new Date(`${value}T00:00:00`);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
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

  // Find one compartment so click handlers can use its complete data.
  function getCompartment(id) {
    return compartments.find((compartment) => compartment.id === id);
  }

  function plannedTreeCount(compartment) {
    return compartment.speciesMix.reduce(
      (total, species) => total + species.quantity,
      0,
    );
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

  // Check whether a map point is inside one barangay boundary ring.
  function pointInRing(point, ring) {
    let inside = false;
    for (
      let current = 0, previous = ring.length - 1;
      current < ring.length;
      previous = current++
    ) {
      const [currentLng, currentLat] = ring[current];
      const [previousLng, previousLat] = ring[previous];
      const intersects =
        currentLat > point[1] !== previousLat > point[1] &&
        point[0] <
          ((previousLng - currentLng) * (point[1] - currentLat)) /
            (previousLat - currentLat) +
            currentLng;
      if (intersects) inside = !inside;
    }
    return inside;
  }

  function pointInGeometry(point, geometry) {
    const polygons =
      geometry.type === "Polygon"
        ? [geometry.coordinates]
        : geometry.coordinates;
    return polygons.some(
      (polygon) =>
        pointInRing(point, polygon[0]) &&
        !polygon.slice(1).some((hole) => pointInRing(point, hole)),
    );
  }

  function matchCompartmentsToBarangays() {
    compartments.forEach((compartment) => {
      const center = boundaryCenter(compartment.boundary);
      const match = barangayFeatures.find((feature) =>
        pointInGeometry([center.lng, center.lat], feature.geometry),
      );
      compartment.barangay = match ? match.properties : null;
    });
  }

  // Load barangay shapes for location matching and the optional map layer.
  async function loadBarangayBoundaries() {
    try {
      const response = await fetch(
        "actions/mapv2/get_barangay_boundaries.php",
        { headers: { Accept: "application/geo+json" } },
      );
      if (!response.ok)
        throw new Error("Barangay boundaries could not be loaded");
      const collection = await response.json();
      barangayFeatures = Array.isArray(collection.features)
        ? collection.features
        : [];
      layers.barangays = L.geoJSON(collection, {
        style: {
          color: "#ffffff",
          weight: 1.5,
          dashArray: "5 5",
          fillOpacity: 0,
        },
        onEachFeature: (feature, layer) => {
          layer.bindTooltip(feature.properties.name, { sticky: true });
          layer.on("click", () => {
            // Keep the label without leaving the SVG path keyboard-focused.
            layer.getElement()?.setAttribute("tabindex", "-1");
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

  // Apply the current search, status, year, and scope filters.
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

  // Create Leaflet, add the base maps, and prepare optional map layers.
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

    map.on("click", (event) => {
      if (!drawingDraft) return;
      drawingDraft.points.push(event.latlng);
      refreshDrawingPreview();
    });
  }

  function clearDrawingPreview() {
    if (drawingPreview) {
      map.removeLayer(drawingPreview);
      drawingPreview = null;
    }
  }

  function getDrawingBoundary() {
    return (drawingDraft?.points || []).map((point) => [point.lat, point.lng]);
  }

  function estimateBoundaryHectares(points) {
    if (points.length < 3) return 0;
    const meanLatitude = points.reduce((total, point) => total + point.lat, 0) / points.length;
    const metresPerLongitude = 111320 * Math.cos((meanLatitude * Math.PI) / 180);
    const metresPerLatitude = 110574;
    let twiceArea = 0;
    points.forEach((point, index) => {
      const next = points[(index + 1) % points.length];
      twiceArea += (point.lng * metresPerLongitude) * (next.lat * metresPerLatitude) -
        (next.lng * metresPerLongitude) * (point.lat * metresPerLatitude);
    });
    return Math.abs(twiceArea) / 2 / 10000;
  }

  function matchedBarangayForBoundary(boundary) {
    if (!boundary.length) return null;
    const center = boundaryCenter(boundary);
    return barangayFeatures.find((feature) =>
      pointInGeometry([center.lng, center.lat], feature.geometry),
    )?.properties || null;
  }

  function drawingLocationReference() {
    const matched = matchedBarangayForBoundary(getDrawingBoundary());
    return matched
      ? [matched.name, matched.municipality, matched.province].filter(Boolean).join(", ")
      : "No Morong barangay boundary matched yet";
  }

  // Build the small toolbar used while a user marks a new boundary.
  function createDrawingToolbar() {
    if (drawingToolbar) return drawingToolbar;
    const control = L.control({ position: "topright" });
    control.onAdd = () => {
      const element = L.DomUtil.create("section", "mapv2-drawing-toolbar");
      element.hidden = true;
      element.innerHTML = `<div><strong>Drawing compartment boundary</strong><span id="drawingCompartmentStatus">Add at least three corners.</span></div><div class="mapv2-drawing-actions"><button type="button" id="undoCompartmentCorner">Undo corner</button><button type="button" id="cancelCompartmentBoundary">Cancel</button><button type="button" class="mapv2-drawing-review" id="reviewCompartmentBoundary" disabled>Review</button></div>`;
      L.DomEvent.disableClickPropagation(element);
      L.DomEvent.disableScrollPropagation(element);
      element.querySelector("#undoCompartmentCorner").addEventListener("click", () => {
        if (!drawingDraft?.points.length) return;
        drawingDraft.points.pop();
        refreshDrawingPreview();
      });
      element.querySelector("#cancelCompartmentBoundary").addEventListener("click", cancelCompartmentDrawing);
      element.querySelector("#reviewCompartmentBoundary").addEventListener("click", reviewCompartmentDrawing);
      return element;
    };
    control.addTo(map);
    drawingToolbar = control;
    return control;
  }

  function setDrawingToolbarVisibility(isVisible) {
    if (!drawingToolbar) return;
    const toolbarElement = drawingToolbar.getContainer();
    if (!toolbarElement) return;
    toolbarElement.hidden = !isVisible;
    toolbarElement.setAttribute("aria-hidden", String(!isVisible));
  }

  // Redraw the temporary corners and polygon after every map click or undo.
  function refreshDrawingPreview() {
    if (!drawingDraft) return;
    clearDrawingPreview();
    const points = drawingDraft.points;
    const previewLayers = [];
    if (points.length >= 3) {
      previewLayers.push(L.polygon(points, {
        color: "#377f40",
        fillColor: "#b9dfbd",
        fillOpacity: 0.35,
        weight: 2,
        dashArray: "7 6",
      }));
    } else if (points.length > 1) {
      previewLayers.push(L.polyline(points, { color: "#377f40", weight: 3, dashArray: "7 6" }));
    }
    points.forEach((point, index) => {
      previewLayers.push(L.marker(point, {
        interactive: false,
        icon: L.divIcon({
          className: "mapv2-drawing-corner-icon",
          html: `<span>${index + 1}</span>`,
          iconSize: [24, 24],
          iconAnchor: [12, 12],
        }),
      }));
    });
    drawingPreview = L.layerGroup(previewLayers).addTo(map);

    const toolbarElement = createDrawingToolbar().getContainer();
    const count = points.length;
    setDrawingToolbarVisibility(true);
    toolbarElement.querySelector("#drawingCompartmentStatus").textContent = count < 3
      ? `${count} corner${count === 1 ? "" : "s"} added. Add ${3 - count} more.`
      : `${count} corners added. Review the location and save when ready.`;
    toolbarElement.querySelector("#undoCompartmentCorner").disabled = count === 0;
    toolbarElement.querySelector("#reviewCompartmentBoundary").disabled = count < 3;
  }

  // Start a fresh drawing session from the details entered in the add modal.
  function beginCompartmentBoundaryDrawing(draft) {
    if (!draft?.name || !Array.isArray(draft.species_mix) || !draft.species_mix.length) return;
    clearDrawingPreview();
    drawingDraft = { ...draft, points: [] };
    refreshDrawingPreview();
    showMapNotice("Click the map to mark the compartment corners. Review the location before saving.");
    map.getContainer().focus({ preventScroll: true });
  }

  // Clear every temporary drawing item when the user cancels or saves.
  function cancelCompartmentDrawing() {
    clearDrawingPreview();
    drawingDraft = null;
    setDrawingToolbarVisibility(false);
    const notice = document.getElementById("mapNotice");
    notice.hidden = true;
    window.hideFloating?.();
  }

  function draftSpeciesMix() {
    return (drawingDraft?.species_mix || []).map((item) => {
      const species = treeSpeciesById.get(Number(item.tree_species_id));
      return {
        id: Number(item.tree_species_id),
        name: species?.name || "Tree species",
        scientificName: species?.scientific_name || "Species name pending",
        quantity: Number(item.planned_quantity) || 0,
      };
    });
  }

  // Send the draft to the review modal before it is written to the database.
  function reviewCompartmentDrawing() {
    if (!drawingDraft || drawingDraft.points.length < 3) return;
    const speciesMix = draftSpeciesMix();
    if (typeof window.showCompartmentReviewModal !== "function") {
      showMapNotice("The review dialog is unavailable. Refresh the page and try again.");
      return;
    }
    window.showCompartmentReviewModal({
      name: drawingDraft.name,
      corners: drawingDraft.points.length,
      hectares: estimateBoundaryHectares(drawingDraft.points),
      location: drawingLocationReference(),
      speciesMix,
    });
  }

  function addCompartmentLayers(compartment) {
    const style = STATUS_STYLES[compartment.status] || STATUS_STYLES.planned;
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
  }

  // Save the reviewed boundary and species mix, then show the new compartment.
  async function saveReviewedCompartment() {
    if (!drawingDraft || drawingDraft.points.length < 3 || creatingCompartment) return;
    creatingCompartment = true;
    const boundary = getDrawingBoundary();
    const payload = new FormData();
    payload.append("name", drawingDraft.name);
    payload.append("status", drawingDraft.status);
    payload.append("date_started", drawingDraft.date_started);
    payload.append("species_mix", JSON.stringify(drawingDraft.species_mix));
    const geoJsonRing = boundary.map(([lat, lng]) => [lng, lat]);
    geoJsonRing.push([boundary[0][1], boundary[0][0]]);
    payload.append("boundary_geojson", JSON.stringify({
      type: "Polygon",
      coordinates: [geoJsonRing],
    }));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
    if (csrf) payload.append("csrf_token", csrf);
    try {
      const response = await fetch("actions/mapv2/create_compartment.php", { method: "POST", body: payload, headers: { Accept: "application/json" } });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || "The compartment could not be created.");
      const draft = drawingDraft;
      const newCompartment = {
        id: String(result.compartment_id),
        name: draft.name,
        startedAt: draft.date_started,
        year: draft.date_started.slice(0, 4),
        status: draft.status,
        hectares: Number(result.gross_area_ha) || estimateBoundaryHectares(draft.points),
        speciesMix: draftSpeciesMix(),
        photos: [],
        boundary,
        barangay: result.barangay || matchedBarangayForBoundary(boundary),
      };
      compartments.push(newCompartment);
      addCompartmentLayers(newCompartment);
      cancelCompartmentDrawing();
      refreshUI();
      selectCompartment(newCompartment.id);
      showMapNotice("Compartment created. Its location was matched from the barangay boundary.");
    } catch (error) {
      showMapNotice(error.message || "The compartment could not be created.");
      document.getElementById("compartmentReviewFrame")?.contentWindow?.postMessage(
        { type: "mapv2:review-create-failed", message: error.message || "The compartment could not be created." },
        window.location.origin,
      );
    } finally {
      creatingCompartment = false;
    }
  }

  function speciesSpacing(species) {
    return (
      treeSpeciesByName.get(species.name.toLowerCase())?.planting_spacing_m ||
      DEFAULT_PLANTING_SPACING_M
    );
  }

  // Place neat seedling points inside a compartment using saved species spacing.
  function generateTreeSpeciesPoints(boundary, speciesMix) {
    const quantity = speciesMix.reduce(
      (total, species) => total + species.quantity,
      0,
    );
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
      const longitudeStep =
        spacingM / (111_320 * Math.cos((centerLat * Math.PI) / 180));
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
    while (
      candidates.length < quantity &&
      displayGridSpacingM > actualGridSpacingM
    ) {
      displayGridSpacingM = Math.max(
        actualGridSpacingM,
        displayGridSpacingM / 1.5,
      );
      candidates = getCandidates(displayGridSpacingM);
    }

    const selected = candidates
      .sort(([leftLat, leftLng], [rightLat, rightLng]) => {
        const leftDistance =
          (leftLat - centerLat) ** 2 + (leftLng - (minLng + maxLng) / 2) ** 2;
        const rightDistance =
          (rightLat - centerLat) ** 2 + (rightLng - (minLng + maxLng) / 2) ** 2;
        return leftDistance - rightDistance;
      })
      .slice(0, quantity)
      .sort(
        ([leftLat, leftLng], [rightLat, rightLng]) =>
          leftLat - rightLat || leftLng - rightLng,
      );

    return selected.map((point, index) => ({
      point,
      species: speciesMix.find(
        (species, speciesIndex) =>
          index <
          speciesMix
            .slice(0, speciesIndex + 1)
            .reduce((total, item) => total + item.quantity, 0),
      ),
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
      generateTreeSpeciesPoints(
        compartment.boundary,
        compartment.speciesMix,
      ).map(({ point, species, actualGridSpacingM, displayGridSpacingM }) =>
        L.circleMarker(point, treeSpeciesDotStyle(compartment.status))
          .bindTooltip(
            `${species.name} · ${style.label}`,
            { direction: "top" },
          )
          .on("click", () => selectCompartment(compartment.id)),
      ),
    );
  }

  function createCompartmentLayers() {
    compartments.forEach(addCompartmentLayers);
  }

  function rebuildTreeSpeciesLayers() {
    layers.treeSpecies.forEach((layer) => map.removeLayer(layer));
    layers.treeClusters.forEach((layer) => map.removeLayer(layer));
    layers.treeSpecies.clear();
    layers.treeClusters.clear();
    compartments.forEach((compartment) => {
      layers.treeSpecies.set(
        compartment.id,
        createTreeSpeciesLayer(compartment),
      );
      layers.treeClusters.set(compartment.id, createTreeCluster(compartment));
    });
    refreshMapLayers();
  }

  async function loadTreeSpeciesSpacing() {
    try {
      const response = await fetch(
        "actions/mapv2/get_tree_species_spacing.php",
        { headers: { Accept: "application/json" } },
      );
      if (!response.ok)
        throw new Error("Tree species spacing could not be loaded");
      const payload = await response.json();
      (payload.species || []).forEach((species) => {
        treeSpeciesById.set(Number(species.id), species);
        if (species.planting_spacing_m > 0)
          treeSpeciesByName.set(species.name.toLowerCase(), species);
      });
      rebuildTreeSpeciesLayers();
    } catch (error) {
      console.warn(
        "Tree species spacing is unavailable; using the temporary 3 m grid.",
        error,
      );
    }
  }

  // Load real compartments after the map is ready and turn them into layers.
  async function loadSavedCompartments() {
    try {
      const response = await fetch("actions/mapv2/get_compartments.php", {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) throw new Error("Saved compartments could not be loaded");
      const payload = await response.json();
      const existingIds = new Set(compartments.map((compartment) => String(compartment.id)));
      (payload.compartments || []).forEach((record) => {
        if (existingIds.has(String(record.id)) || !Array.isArray(record.boundary) || record.boundary.length < 3) return;
        const compartment = {
          id: String(record.id),
          name: record.name,
          startedAt: record.started_at,
          year: String(record.started_at || "").slice(0, 4),
          status: STATUS_STYLES[record.status] ? record.status : "planned",
          hectares: Number(record.hectares) || 0,
          boundary: record.boundary,
          barangay: record.barangay || null,
          speciesMix: (record.species_mix || []).map((species) => ({
            id: Number(species.id),
            name: species.name,
            scientificName: species.scientific_name || "",
            quantity: Number(species.quantity) || 0,
          })),
          photos: [],
        };
        compartments.push(compartment);
        addCompartmentLayers(compartment);
      });
      refreshUI();
    } catch (error) {
      console.warn("Saved Map V2 compartments are unavailable.", error);
    }
  }

  // Show only layers that match the filters and active layer switches.
  function refreshMapLayers() {
    const visible = new Set(
      visibleCompartments().map((compartment) => compartment.id),
    );
    const treeSpeciesVisible =
      document.getElementById("treeSpeciesToggle").checked;
    const useTreeClusters = map.getZoom() < TREE_CLUSTER_ZOOM;

    layers.polygons.forEach((polygon, id) => {
      if (visible.has(id)) polygon.addTo(map);
      else map.removeLayer(polygon);
    });
    layers.treeSpecies.forEach((treeSpecies, id) => {
      if (visible.has(id) && treeSpeciesVisible && !useTreeClusters)
        treeSpecies.addTo(map);
      else map.removeLayer(treeSpecies);
    });
    layers.treeClusters.forEach((treeCluster, id) => {
      if (visible.has(id) && treeSpeciesVisible && useTreeClusters)
        treeCluster.addTo(map);
      else map.removeLayer(treeCluster);
    });

    if (layers.reports && (state.scope === "all" || state.scope === "reports"))
      layers.reports.addTo(map);
    else if (layers.reports) map.removeLayer(layers.reports);

    if (
      layers.barangays &&
      document.getElementById("barangayBoundariesToggle").checked
    ) {
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

  // Render the add card, compartment cards, and clean empty states on the right.
  function renderList() {
    const list = document.getElementById("compartmentList");
    const visible = visibleCompartments();

    if (state.scope === "reports") {
      list.innerHTML =
        '<p class="mapv2-empty-state">No reported issues yet.</p>';
      return;
    }
    if (state.scope === "archived") {
      list.innerHTML =
        '<p class="mapv2-empty-state">Archived compartments will appear here when the archive workflow is added.</p>';
      return;
    }
    const addCompartmentCard = `
            <button type="button" class="mapv2-add-compartment-card" id="addCompartmentCard" aria-label="Add compartment">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
            </button>`;

    const emptyState = compartments.length
      ? `<div class="mapv2-empty-state mapv2-empty-state--no-match"><strong>No matching compartments</strong><span>Try clearing your search or filters.</span></div>`
      : `<div class="mapv2-empty-state mapv2-empty-state--initial"><strong>No compartments yet</strong><span>Create the first compartment using the card above.</span></div>`;

    list.innerHTML =
      addCompartmentCard +
      (visible.length
        ? visible
        .map((compartment) => {
          const status = STATUS_STYLES[compartment.status];
          return `
                <article class="mapv2-compartment-card" data-compartment-id="${compartment.id}" tabindex="0" onclick="window.selectMapV2Compartment(this.dataset.compartmentId)">
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
        .join("")
        : emptyState);

    list.querySelector("#addCompartmentCard")?.addEventListener("click", () => {
      window.addNewCompartment();
    });
    list.querySelectorAll("[data-compartment-id]").forEach((card) => {
      card.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;
        event.preventDefault();
        selectCompartment(card.dataset.compartmentId);
      });
    });
  }

  // Replace the list with the selected compartment details and its photo area.
  function renderDetail(compartment) {
    const status = STATUS_STYLES[compartment.status];
    const detail = document.getElementById("compartmentDetailView");
    const photos = Array.isArray(compartment.photos) ? compartment.photos : [];
    const categoryOptions = Object.entries(STATUS_STYLES)
      .map(([key, item]) => `<option value="${key}">${item.label}</option>`)
      .join("");
    const photoYears = [
      ...new Set(
        photos
          .filter((photo) => !photo.archived)
          .map((photo) => photo.uploadedAt.slice(0, 4)),
      ),
    ]
      .sort((left, right) => right.localeCompare(left))
      .map((year) => `<option value="${escapeHtml(year)}">${escapeHtml(year)}</option>`)
      .join("");
    const photoRowsMarkup = photos
      .map((photo, index) => {
        if (photo.archived) return "";
        return `<div class="mapv2-photo-row" role="row" data-photo-index="${index}" data-photo-category="${escapeHtml(photo.category.toLowerCase())}" data-photo-date="${escapeHtml(photo.uploadedAt)}"><span title="${escapeHtml(photo.name)}"><i class="fa-regular fa-image" aria-hidden="true"></i> ${escapeHtml(photo.name)}</span><span title="${escapeHtml(photo.uploadedBy)}">${escapeHtml(photo.uploadedBy)}</span><span title="${escapeHtml(photo.category)}">${escapeHtml(photo.category)}</span><span title="${escapeHtml(photo.size)}">${escapeHtml(photo.size)}</span><span title="${formatPhotoDate(photo.uploadedAt)}">${formatPhotoDate(photo.uploadedAt)}</span><span class="mapv2-photo-menu-wrap"><button class="mapv2-icon-button" type="button" data-photo-menu-toggle="${index}" aria-label="Photo options" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button><span class="mapv2-photo-actions" data-photo-menu="${index}" hidden><button type="button" data-photo-action="edit" data-photo-index="${index}">Edit</button><button type="button" data-photo-action="archive" data-photo-index="${index}">Archive</button></span></span></div>`;
      })
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
                <div class="mapv2-filter-select">
                <select id="detailStatusSelect">
                    ${Object.entries(STATUS_STYLES)
                      .map(
                        ([key, item]) =>
                          `<option value="${key}" ${key === compartment.status ? "selected" : ""}>${item.label}</option>`,
                      )
                      .join("")}
                </select>
                        <i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i>
                        </div>
            </label>
            <section class="mapv2-species-section">
                <h3>Species mix</h3>
                ${compartment.speciesMix.map((species) => `<article class="mapv2-species-card"><div><strong>${escapeHtml(species.name)}</strong><em>${escapeHtml(species.scientificName)}</em></div><b>×${species.quantity}</b></article>`).join("")}
            </section>
            <section class="mapv2-photos-section">
                <h3>Photos</h3>
                <div class="mapv2-photo-filters"><div class="mapv2-filter-select"><select id="photoCategoryFilter" aria-label="Filter photo category"><option value="all">All categories</option>${categoryOptions}</select><i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i></div><div class="mapv2-filter-select"><select id="photoDateFilter" aria-label="Filter photo date"><option value="current-month">Current month</option><option value="last-month">Last month</option><option value="custom-range">Custom range</option><option value="yearly">Yearly view</option></select><i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i></div><div class="mapv2-date-range" id="photoCustomRange" hidden><input id="photoStartDate" type="date" aria-label="Photo date range start"><span>to</span><input id="photoEndDate" type="date" aria-label="Photo date range end"></div><div class="mapv2-filter-select" id="photoYearFilterWrap" hidden><select id="photoYearFilter" aria-label="Photo year"><option value="all">All years</option>${yearOptions}</select><i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i></div><button type="button" id="clearPhotoFilters" hidden>Clear filters</button></div>
                <div class="mapv2-photo-table" role="table">
                    <div class="mapv2-photo-row mapv2-photo-head" role="row"><span>Name</span><span>Uploaded by</span><span>Category</span><span>Size</span><span>Date</span><span></span></div>
                    <p class="mapv2-photo-date" id="photoDateCaption">Current month</p>
                    ${photoRowsMarkup}
                </div>
            </section>
              </div>
            <dialog class="mapv2-photo-editor" id="photoEditorDialog" aria-labelledby="photoEditorTitle">
                <form method="dialog" id="photoEditorForm">
                    <header><h3 id="photoEditorTitle">Edit photo</h3><button type="button" class="mapv2-icon-button" id="closePhotoEditor" aria-label="Close photo editor"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
                    <label>Name<input id="photoEditorName" type="text" required></label>
                    <label>Category<select id="photoEditorCategory">${categoryOptions}</select></label>
                    <label class="image-container mapv2-photo-dropzone" id="photoImageDropzone" for="photoImageInput"><input id="photoImageInput" type="file" accept="image/*" hidden><i class="fa-regular fa-image" aria-hidden="true"></i><strong>Drop an image here</strong><span>or click to choose a replacement</span><img id="photoImagePreview" alt="Selected photo preview" hidden></label>
                    <footer><button type="button" id="cancelPhotoEditor">Cancel</button><button type="submit" class="mapv2-primary-button">Save</button></footer>
                </form>
            </dialog>`;

    document.getElementById("compartmentListView").hidden = true;
    detail.hidden = false;

    try {
    const photoRows = detail.querySelectorAll(
      ".mapv2-photo-row[data-photo-category]",
    );
    const categoryFilter = document.getElementById("photoCategoryFilter");
    const dateFilter = document.getElementById("photoDateFilter");
    const customRange = document.getElementById("photoCustomRange");
    const startDate = document.getElementById("photoStartDate");
    const endDate = document.getElementById("photoEndDate");
    const yearFilterWrap = document.getElementById("photoYearFilterWrap");
    const photoYearFilter = document.getElementById("photoYearFilter");
    const dateCaption = document.getElementById("photoDateCaption");
    const clearPhotoFilters = document.getElementById("clearPhotoFilters");

    const updateDateControls = () => {
      const isCustomRange = dateFilter.value === "custom-range";
      const isYearly = dateFilter.value === "yearly";
      customRange.hidden = !isCustomRange;
      yearFilterWrap.hidden = !isYearly;
      dateCaption.textContent = dateFilter.options[dateFilter.selectedIndex].textContent;
    };
    const updateClearButton = () => {
      clearPhotoFilters.hidden =
        categoryFilter.value === "all" && dateFilter.value === "current-month";
    };
    const filterPhotoRows = () => {
      const selectedCategory = categoryFilter.value;
      const selectedPeriod = dateFilter.value;
      const currentMonth = monthKey(new Date());
      const previousMonthDate = new Date();
      previousMonthDate.setMonth(previousMonthDate.getMonth() - 1);
      const previousMonth = monthKey(previousMonthDate);
      photoRows.forEach((row) => {
        const photoDate = row.dataset.photoDate;
        const periodMatches =
          (selectedPeriod === "current-month" && monthKey(photoDate) === currentMonth) ||
          (selectedPeriod === "last-month" && monthKey(photoDate) === previousMonth) ||
          (selectedPeriod === "custom-range" &&
            (!startDate.value || photoDate >= startDate.value) &&
            (!endDate.value || photoDate <= endDate.value)) ||
          (selectedPeriod === "yearly" &&
            (photoYearFilter.value === "all" || photoDate.startsWith(photoYearFilter.value)));
        row.hidden =
          (selectedCategory !== "all" &&
            row.dataset.photoCategory !== selectedCategory) ||
          !periodMatches;
      });
      updateDateControls();
      updateClearButton();
    };
    categoryFilter.addEventListener("change", filterPhotoRows);
    dateFilter.addEventListener("change", filterPhotoRows);
    startDate.addEventListener("change", filterPhotoRows);
    endDate.addEventListener("change", filterPhotoRows);
    photoYearFilter.addEventListener("change", filterPhotoRows);
    clearPhotoFilters.addEventListener("click", () => {
      categoryFilter.value = "all";
      dateFilter.value = "current-month";
      startDate.value = "";
      endDate.value = "";
      photoYearFilter.value = "all";
      filterPhotoRows();
    });
    filterPhotoRows();

    const closePhotoMenus = () => {
      detail.querySelectorAll("[data-photo-menu]").forEach((menu) => {
        menu.hidden = true;
      });
      detail.querySelectorAll("[data-photo-menu-toggle]").forEach((button) => {
        button.setAttribute("aria-expanded", "false");
      });
    };
    const photoEditor = document.getElementById("photoEditorDialog");
    const photoEditorForm = document.getElementById("photoEditorForm");
    const photoEditorName = document.getElementById("photoEditorName");
    const photoEditorCategory = document.getElementById("photoEditorCategory");
    const photoImageInput = document.getElementById("photoImageInput");
    const photoDropzone = document.getElementById("photoImageDropzone");
    const photoImagePreview = document.getElementById("photoImagePreview");
    let editingPhotoIndex = null;
    let previewUrl = null;

    const showPhotoPreview = (file) => {
      if (!file || !file.type.startsWith("image/")) return;
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = URL.createObjectURL(file);
      photoImagePreview.src = previewUrl;
      photoImagePreview.hidden = false;
      photoDropzone.classList.add("has-preview");
    };
    const openPhotoEditor = (index) => {
      const photo = photos[index];
      if (!photo) return;
      editingPhotoIndex = index;
      photoEditorName.value = photo.name;
      photoEditorCategory.value = photo.category.toLowerCase();
      photoImageInput.value = "";
      photoImagePreview.hidden = true;
      photoDropzone.classList.remove("has-preview", "is-dragging");
      photoEditor.showModal();
    };

    detail.addEventListener("click", (event) => {
      const menuToggle = event.target.closest("[data-photo-menu-toggle]");
      if (menuToggle) {
        const menu = detail.querySelector(`[data-photo-menu="${menuToggle.dataset.photoMenuToggle}"]`);
        const willOpen = menu.hidden;
        closePhotoMenus();
        menu.hidden = !willOpen;
        menuToggle.setAttribute("aria-expanded", String(willOpen));
        return;
      }
      const action = event.target.closest("[data-photo-action]");
      if (action) {
        const photoIndex = Number(action.dataset.photoIndex);
        closePhotoMenus();
        if (action.dataset.photoAction === "edit") openPhotoEditor(photoIndex);
        if (action.dataset.photoAction === "archive") {
          photos[photoIndex].archived = true;
          compartment.photos = photos;
          renderDetail(compartment);
        }
        return;
      }
      if (!event.target.closest(".mapv2-photo-menu-wrap")) closePhotoMenus();
    });

    photoImageInput.addEventListener("change", () => showPhotoPreview(photoImageInput.files[0]));
    ["dragenter", "dragover"].forEach((eventName) => {
      photoDropzone.addEventListener(eventName, (event) => {
        event.preventDefault();
        photoDropzone.classList.add("is-dragging");
      });
    });
    ["dragleave", "drop"].forEach((eventName) => {
      photoDropzone.addEventListener(eventName, (event) => {
        event.preventDefault();
        photoDropzone.classList.remove("is-dragging");
      });
    });
    photoDropzone.addEventListener("drop", (event) => showPhotoPreview(event.dataTransfer.files[0]));
    photoEditorForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const photo = photos[editingPhotoIndex];
      if (!photo) return;
      photo.name = photoEditorName.value.trim() || photo.name;
      photo.category = STATUS_STYLES[photoEditorCategory.value]?.label || photo.category;
      photoEditor.close();
      renderDetail(compartment);
    });
    ["closePhotoEditor", "cancelPhotoEditor"].forEach((id) => {
      document.getElementById(id).addEventListener("click", () => photoEditor.close());
    });
    photoEditor.addEventListener("close", () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = null;
    });
    } catch (error) {
      console.warn("Map V2 photo controls could not be initialized.", error);
    }

    document
      .getElementById("detailStatusSelect")
      .addEventListener("change", (event) =>
        updateCompartmentStatus(compartment.id, event.target.value),
      );
    document
      .getElementById("backToCompartments")
      .addEventListener("click", showList);
  }

  function selectCompartment(id) {
    const compartment = getCompartment(id);
    if (!compartment) return;
    state.selectedId = id;
    try {
      renderDetail(compartment);
    } catch (error) {
      console.error("Map V2 compartment detail could not be rendered.", error);
      renderDetailFallback(compartment);
    }
    if (window.matchMedia("(max-width: 1260px)").matches) {
      document.getElementById("compartmentPanel")?.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }
    const polygon = layers.polygons.get(id);
    if (polygon)
      map.fitBounds(polygon.getBounds(), { padding: [40, 40], maxZoom: 15 });
  }

  function renderDetailFallback(compartment) {
    const detail = document.getElementById("compartmentDetailView");
    const photos = Array.isArray(compartment.photos) ? compartment.photos : [];
    const photoRows = photos.filter((photo) => !photo.archived).map((photo) =>
      `<div class="mapv2-photo-row" role="row"><span><i class="fa-regular fa-image" aria-hidden="true"></i> ${escapeHtml(photo.name)}</span><span>${escapeHtml(photo.uploadedBy)}</span><span>${escapeHtml(photo.category)}</span><span>${escapeHtml(photo.size)}</span><span>${formatPhotoDate(photo.uploadedAt)}</span></div>`,
    ).join("") || '<p class="mapv2-empty-state">No photos have been added yet.</p>';
    detail.innerHTML = `
      <header class="mapv2-detail-heading">
        <button type="button" class="mapv2-back-button" id="backToCompartments" aria-label="Back to compartment list"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
        <div><h2>${escapeHtml(compartment.name)}</h2><p>${escapeHtml(locationReference(compartment))}</p></div>
      </header>
      <div class="mapv2-detail-scroll">
        <div class="mapv2-detail-stats"><div><span>Gross area</span><strong>${compartment.hectares.toFixed(2)} <small>ha</small></strong></div><div><span>Trees planted</span><strong>${plannedTreeCount(compartment)}</strong></div></div>
        <div class="mapv2-detail-field"><span>Location</span><p>${escapeHtml(locationReference(compartment))}</p></div>
        <label class="mapv2-detail-field"><span>Status</span><div class="mapv2-filter-select"><select id="detailStatusSelect">${Object.entries(STATUS_STYLES).map(([key, item]) => `<option value="${key}" ${key === compartment.status ? "selected" : ""}>${item.label}</option>`).join("")}</select><i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i></div></label>
        <section class="mapv2-species-section"><h3>Species mix</h3>${compartment.speciesMix.map((species) => `<article class="mapv2-species-card"><div><strong>${escapeHtml(species.name)}</strong><em>${escapeHtml(species.scientificName)}</em></div><b>×${species.quantity}</b></article>`).join("")}</section>
        <section class="mapv2-photos-section"><h3>Photos</h3><div class="mapv2-photo-table" role="table"><div class="mapv2-photo-row mapv2-photo-head" role="row"><span>Name</span><span>Uploaded by</span><span>Category</span><span>Size</span><span>Date</span></div>${photoRows}</div></section>
      </div>`;
    document.getElementById("compartmentListView").hidden = true;
    detail.hidden = false;
    document.getElementById("backToCompartments").addEventListener("click", showList);
    document.getElementById("detailStatusSelect").addEventListener("change", (event) =>
      updateCompartmentStatus(compartment.id, event.target.value),
    );
  }

  function showList() {
    state.selectedId = null;
    const detail = document.getElementById("compartmentDetailView");
    const list = document.getElementById("compartmentListView");
    if (detail.hidden) {
      list.hidden = false;
      renderList();
      return;
    }
    if (detail.classList.contains("is-leaving")) return;

    // Let the detail panel slide right before the list returns.
    detail.classList.add("is-leaving");
    const finishExit = () => {
      detail.classList.remove("is-leaving");
      detail.hidden = true;
      list.hidden = false;
      renderList();
    };
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      finishExit();
      return;
    }
    detail.addEventListener("animationend", finishExit, { once: true });
  }

  // Change local map colours immediately after a status selection changes.
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
      .eachLayer((marker) => marker.setStyle(treeSpeciesDotStyle(nextStatus)));
    rebuildTreeSpeciesLayers();
    try {
      renderDetail(compartment);
    } catch (error) {
      console.error("Map V2 compartment detail could not be rendered.", error);
      renderDetailFallback(compartment);
    }
    updateMetrics();
  }

  function refreshUI() {
    refreshMapLayers();
    updateMetrics();
    if (state.selectedId && getCompartment(state.selectedId))
      selectCompartment(state.selectedId);
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

  // Connect filters, layer switches, menus, and base map buttons to the map.
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

  // Receive messages from the add and review modal iframes.
  function bindCompartmentCreation() {
    window.addEventListener("message", (event) => {
      if (event.origin !== window.location.origin) return;
      if (event.data?.type === "mapv2:begin-compartment-boundary") {
        beginCompartmentBoundaryDrawing(event.data.payload);
      } else if (event.data?.type === "mapv2:cancel-compartment-drawing") {
        cancelCompartmentDrawing();
      } else if (event.data?.type === "mapv2:resume-compartment-drawing") {
        window.hideFloating?.();
      } else if (event.data?.type === "mapv2:confirm-compartment-create") {
        saveReviewedCompartment();
      }
    });
  }

  window.addNewCompartment = () => {
    if (typeof window.showAddReforestationCompartmentModal === "function") {
      window.showAddReforestationCompartmentModal();
      return;
    }
    showMapNotice("The add-compartment dialog is unavailable. Refresh the page and try again.");
  };
  window.selectMapV2Compartment = selectCompartment;

  // Start the map only after its page elements are available.
  document.addEventListener("DOMContentLoaded", () => {
    initializeMap();
    let resizeTimer;
    window.addEventListener("resize", () => {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(
        () => map?.invalidateSize({ pan: false }),
        120,
      );
    });
    createCompartmentLayers();
    bindControls();
    bindCompartmentCreation();
    refreshUI();
    loadBarangayBoundaries();
    loadTreeSpeciesSpacing();
    loadSavedCompartments();
  });
})();
