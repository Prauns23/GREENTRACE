/**
 * reports-map.js
 * Handles the "Reported" scope on the Forest Map page.
 * Exposes: window.ReportMap
 */
(() => {
  "use strict";

  const REPORT_STATUS = {
    pending:   { label: "Pending",   color: "#e53935", fill: "#ffcdd2" },
    reviewed:  { label: "Reviewed",  color: "#fb8c00", fill: "#ffe0b2" },
    resolved:  { label: "Resolved",  color: "#43a047", fill: "#c8e6c9" },
    dismissed: { label: "Dismissed", color: "#757575", fill: "#e0e0e0" },
  };

  const reports = [];
  const state = {
    loaded: false,
    scopeActive: false,
    archivedOnly: false,
    status: "all",
    search: "",
    selectedId: null,
  };

  let map = null;
  let markersLayer = null;
  const markers = new Map();
  let barangayFeatures = [];
  let galleryState = null;
  let toastTimer = null;

  //  
  // Utility helpers
  //  
  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>'"]/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#039;", '"': "&quot;" })[c]);
  }

  function formatReportDate(value) {
    if (!value) return "—";
    const date = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return "—";
    return new Intl.DateTimeFormat("en-PH", {
      year: "numeric", month: "short", day: "2-digit",
    }).format(date);
  }

  function formatReportTime(value) {
    if (!value) return "";
    const date = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return "";
    return new Intl.DateTimeFormat("en-PH", {
      hour: "2-digit", minute: "2-digit", hour12: true,
    }).format(date);
  }

  function formatUpdatedAt(value) {
    if (!value) return "Not updated yet";
    const ts = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(ts.getTime())) return "Not available";
    return new Intl.DateTimeFormat("en-PH", {
      year: "numeric", month: "long", day: "numeric",
    }).format(ts);
  }

  function showToast(message) {
    const toast = document.getElementById("mapToast");
    if (!toast) return;
    window.clearTimeout(toastTimer);
    toast.textContent = message;
    toast.hidden = false;
    window.requestAnimationFrame(() => toast.classList.add("is-visible"));
    toastTimer = window.setTimeout(() => {
      toast.classList.remove("is-visible");
      window.setTimeout(() => {
        if (!toast.classList.contains("is-visible")) toast.hidden = true;
      }, 180);
    }, 4600);
  }

  //  
  // Barangay boundary matching (independent copy)
  //  
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
      geometry.type === "Polygon" ? [geometry.coordinates] : geometry.coordinates;
    return polygons.some(
      (polygon) =>
        pointInRing(point, polygon[0]) &&
        !polygon.slice(1).some((hole) => pointInRing(point, hole)),
    );
  }

  function matchedBarangay(lat, lng) {
    if (lat == null || lng == null) return null;
    const feature = barangayFeatures.find((f) =>
      pointInGeometry([lng, lat], f.geometry),
    );
    return feature ? feature.properties : null;
  }

  async function loadBarangayBoundaries() {
    try {
      const response = await fetch("actions/mapv2/get_barangay_boundaries.php", {
        headers: { Accept: "application/geo+json" },
      });
      if (!response.ok) throw new Error("Barangay boundaries unavailable");
      const collection = await response.json();
      barangayFeatures = Array.isArray(collection.features) ? collection.features : [];
      // Re-render the open detail view so the barangay card fills in.
      if (state.selectedId) {
        const report = reports.find((r) => String(r.id) === state.selectedId);
        if (report) renderDetail(report);
      }
    } catch (error) {
      console.warn("ReportMap: barangay boundaries unavailable.", error);
    }
  }

  //  
  // Map marker helpers
  //  
  function getMarkerIcon(status, isArchived) {
    const style = REPORT_STATUS[status] || REPORT_STATUS.pending;
    const color = isArchived ? "#9e9e9e" : style.color;
    return L.divIcon({
      className: "mapv2-report-marker-icon",
      html: `<span class="mapv2-report-marker" style="--report-color: ${color};"><i class="fa-solid fa-triangle-exclamation"></i></span>`,
      iconSize: [32, 32],
      iconAnchor: [16, 16],
    });
  }

  function visibleReports() {
    const term = state.search.trim().toLowerCase();
    return reports.filter((report) => {
      if (Boolean(report.archived) !== state.archivedOnly) return false;
      if (state.status !== "all" && report.status !== state.status) return false;
      if (!term) return true;
      const haystack = `${report.issue_type} ${report.location}`.toLowerCase();
      return haystack.includes(term);
    });
  }

  function renderMarkers() {
    if (!map) return;
    if (!markersLayer) markersLayer = L.layerGroup();
    markersLayer.clearLayers();
    markers.clear();

    visibleReports().forEach((report) => {
      if (report.latitude == null || report.longitude == null) return;
      const marker = L.marker([report.latitude, report.longitude], {
        icon: getMarkerIcon(report.status, report.archived),
      });
      marker.bindTooltip(report.issue_type, { direction: "top" });
      marker.on("click", () => selectReport(report.id));
      markersLayer.addLayer(marker);
      markers.set(String(report.id), marker);
    });

    if (state.scopeActive && !map.hasLayer(markersLayer)) {
      markersLayer.addTo(map);
    }
    updateMetrics();
  }

  function updateMetrics() {
    const all = reports.filter(
      (report) => Boolean(report.archived) === state.archivedOnly,
    );
    const active = all.filter((r) => r.status === "pending" || r.status === "reviewed");
    const totalEl = document.getElementById("totalReports");
    const activeEl = document.getElementById("activeReports");
    if (totalEl) totalEl.textContent = String(all.length);
    if (activeEl) activeEl.textContent = String(active.length);
  }

  //  
  // List rendering
  //  
  function showReportList() {
    state.selectedId = null;
    const list = document.getElementById("reportListView");
    const detail = document.getElementById("reportDetailView");
    if (detail) detail.hidden = true;
    if (list) list.hidden = false;
    renderList();
  }

  function renderList() {
    const list = document.getElementById("reportList");
    if (!list) return;
    const visible = visibleReports();

    if (!visible.length) {
      list.innerHTML = `<p class="mapv2-empty-state"><span>No reports match your filter.</span></p>`;
      return;
    }

    list.innerHTML = visible
      .map((report) => {
        const status = REPORT_STATUS[report.status] || REPORT_STATUS.pending;
        const time = formatReportTime(report.created_at);
        const date = formatReportDate(report.created_at);
        const locationLine = time
          ? `${escapeHtml(report.location)} · ${time}`
          : escapeHtml(report.location);
        const isActive = String(report.id) === String(state.selectedId);
        return `
          <article class="mapv2-report-card ${isActive ? "is-active" : ""}"
                   data-report-id="${report.id}" tabindex="0">
            <div class="mapv2-report-icon">
              <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            </div>
            <div class="mapv2-report-main">
              <h3>${escapeHtml(report.issue_type)}</h3>
              <p>${locationLine}</p>
              <small>Reported: <strong>${date}</strong></small>
            </div>
            <div class="mapv2-report-actions">
              <span class="mapv2-report-status"
                    style="--status-color: ${status.color}; --status-fill: ${status.fill};">
                ${status.label}
              </span>
              <div class="mapv2-card-menu-wrap">
                <button class="mapv2-icon-button mapv2-card-menu" type="button"
                        data-report-menu-toggle="${report.id}"
                        aria-label="Report options" aria-expanded="false">
                  <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                </button>
                <div class="mapv2-card-menu-options" data-report-menu="${report.id}" role="menu" hidden>
                  <button type="button" data-report-action="${state.archivedOnly ? "restore" : "archive"}" data-report-id="${report.id}">${state.archivedOnly ? "Restore" : "Archive"}</button>
                </div>
              </div>
            </div>
          </article>`;
      })
      .join("");

    list.querySelectorAll("[data-report-menu-toggle]").forEach((button) => {
      button.addEventListener("click", (event) => {
        event.stopPropagation();
        const menu = list.querySelector(
          `[data-report-menu="${button.dataset.reportMenuToggle}"]`,
        );
        if (!menu) return;
        const isOpen = menu.classList.toggle("is-open");
        menu.hidden = !isOpen;
        button.setAttribute("aria-expanded", String(isOpen));
      });
    });

    list.querySelectorAll("[data-report-action]").forEach((button) => {
      button.addEventListener("click", (event) => {
        event.stopPropagation();
        updateReportArchive(
          Number(button.dataset.reportId),
          button.dataset.reportAction === "restore",
        );
      });
    });

    list.querySelectorAll("[data-report-id]").forEach((card) => {
      card.addEventListener("click", () =>
        selectReport(Number(card.dataset.reportId)),
      );
      card.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;
        event.preventDefault();
        selectReport(Number(card.dataset.reportId));
      });
    });
  }

  //  
  // Detail rendering
  //  
  function renderDetail(report) {
    const list = document.getElementById("reportListView");
    const detail = document.getElementById("reportDetailView");
    if (!detail) return;

    const status = REPORT_STATUS[report.status] || REPORT_STATUS.pending;
    const photos = Array.isArray(report.photos) ? report.photos : [];
    const barangay = matchedBarangay(report.latitude, report.longitude);
    const barangayLabel = barangay
      ? [barangay.name, barangay.municipality, barangay.province]
          .filter(Boolean)
          .join(", ")
      : "Unmatched barangay";
    const coordinateText =
      report.latitude != null && report.longitude != null
        ? `${report.latitude.toFixed(5)}, ${report.longitude.toFixed(5)}`
        : "No GPS coordinates recorded";

    // Status select options.
    const statusOptions = Object.entries(REPORT_STATUS)
      .map(
        ([key, item]) =>
          `<option value="${key}" ${key === report.status ? "selected" : ""}>${item.label}</option>`,
      )
      .join("");

    // Evidence photos section.
    let photosMarkup;
    if (photos.length) {
      const rows = photos
        .map(
          (photo, index) => `
            <div class="mapv2-photo-row report-photo"
                 role="row" tabindex="0"
                 data-report-photo-index="${index}"
                 aria-label="Preview ${escapeHtml(photo.name)}">
              <span title="${escapeHtml(photo.name)}">
                <i class="fa-regular fa-image" aria-hidden="true"></i>
                ${escapeHtml(photo.name)}
              </span>
              <span class="mapv2-report-photo-preview-icon" aria-hidden="true">
                <i class="fa-solid fa-expand"></i>
              </span>
            </div>`,
        )
        .join("");
      photosMarkup = `
        <div class="mapv2-photo-table" role="table">
          <div class="mapv2-photo-row mapv2-photo-head report-photo" role="row">
            <span>Photo</span><span></span>
          </div>
          ${rows}
        </div>`;
    } else {
      photosMarkup = `
        <div class="mapv2-photo-empty-state">
          <img src="components/icons/photos.svg" alt="" aria-hidden="true" class="mapv2-photo-empty-illustration">
          <strong>No evidence photos</strong>
          <span>This report has no attached photos.</span>
        </div>`;
    }

    detail.innerHTML = `
      <header class="mapv2-detail-heading">
        <button type="button" class="mapv2-back-button" id="backToReports"
                aria-label="Back to report list">
          <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </button>
        <div>
          <h2>${escapeHtml(report.issue_type)}</h2>
          <p>${escapeHtml(barangayLabel)}</p>
        </div>
      </header>

      <div class="mapv2-detail-scroll">

        <div class="mapv2-detail-stats">
          <div>
            <span>Status</span>
            <strong>${status.label}</strong>
          </div>
          <div>
            <span>Reported</span>
            <strong>${formatReportDate(report.created_at)}</strong>
          </div>
        </div>

        <div class="mapv2-detail-field">
          <span>Location</span>
          <p>${escapeHtml(report.location)}</p>
          <small>${escapeHtml(coordinateText)}</small>
        </div>

        <div class="mapv2-detail-field">
          <span>Barangay</span>
          <p>${escapeHtml(barangayLabel)}</p>
          <small>${
            barangay
              ? "Matched from the report's coordinates."
              : "No matching barangay boundary for this report's coordinates."
          }</small>
        </div>

        <div class="mapv2-detail-field">
          <span>Reporter</span>
          <p>${escapeHtml(report.reporter)}</p>
          ${
            report.anonymous
              ? `<small>This report was submitted anonymously.</small>`
              : `<small>Signed report visible to MENRO.</small>`
          }
        </div>

        <label class="mapv2-detail-field">
          <span>Update Status</span>
          <div class="mapv2-filter-select">
            <select id="reportDetailStatusSelect">${statusOptions}</select>
            <i class="fas fa-chevron-down mapv2-scope-chevron" aria-hidden="true"></i>
          </div>
        </label>

        <section class="mapv2-report-description-section">
          <h3>Description</h3>
          <p class="mapv2-report-description">${escapeHtml(report.description || "")}</p>
        </section>

        <div class="mapv2-detail-updated">
          <span>Last updated</span>
          <p>${escapeHtml(formatUpdatedAt(report.updated_at))}</p>
        </div>

        <section class="mapv2-photos-section">
          <h3>Evidence photos</h3>
          ${photosMarkup}
        </section>

      </div>
    `;

    if (list) list.hidden = true;
    detail.hidden = false;

    // Back button
    document.getElementById("backToReports")?.addEventListener("click", showReportList);

    // Status select
    const statusSelect = document.getElementById("reportDetailStatusSelect");
    statusSelect?.addEventListener("change", (event) => {
      updateReportStatus(report.id, event.target.value);
    });

    // Photo rows → open gallery
    detail.querySelectorAll("[data-report-photo-index]").forEach((row) => {
      const open = () =>
        openGallery(photos, Number(row.dataset.reportPhotoIndex));
      row.addEventListener("click", open);
      row.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;
        event.preventDefault();
        open();
      });
    });
  }

  //  
  // Select a report (from list or marker)
  //  
  function selectReport(id) {
    const report = reports.find((r) => String(r.id) === String(id));
    if (!report) return;
    state.selectedId = String(id);

    const marker = markers.get(String(id));
    if (marker && map) {
      map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15), { animate: true });
      marker.openTooltip();
    }

    renderDetail(report);
    // Refresh the list so the active card highlight follows the selection.
    renderList();
  }

  //  
  // Status update
  //  
  async function updateReportStatus(id, newStatus) {
    const report = reports.find((r) => String(r.id) === String(id));
    if (!report || !REPORT_STATUS[newStatus]) return;
    const statusSelect = document.getElementById("reportDetailStatusSelect");
    if (statusSelect) statusSelect.disabled = true;

    try {
      const payload = new FormData();
      payload.append("report_id", id);
      payload.append("status", newStatus);
      payload.append(
        "csrf_token",
        document.querySelector('meta[name="csrf-token"]')?.content || "",
      );

      const response = await fetch("actions/update_report.php", {
        method: "POST",
        body: payload,
        headers: { Accept: "application/json" },
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.error || "Failed to update report.");
      }

      report.status = newStatus;
      report.updated_at = new Date()
        .toISOString()
        .slice(0, 19)
        .replace("T", " ");

      renderMarkers();
      renderList();
      renderDetail(report);
      showToast("Report status updated.");
    } catch (error) {
      showToast(error.message || "Failed to update report.");
      if (statusSelect && report) {
        statusSelect.value = report.status;
        statusSelect.disabled = false;
      }
    }
  }

  async function updateReportArchive(id, restore) {
    const report = reports.find((item) => String(item.id) === String(id));
    if (!report) return;
    if (!window.confirm(`${restore ? "Restore" : "Archive"} this report?`)) return;

    try {
      const payload = new FormData();
      payload.append("report_id", id);
      const csrf = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
      if (csrf) payload.append("csrf_token", csrf);
      const response = await fetch(
        restore ? "actions/restore_report.php" : "actions/archive_report.php",
        {
        method: "POST",
        body: payload,
        headers: { Accept: "application/json" },
        },
      );
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(
          result.error || `The report could not be ${restore ? "restored" : "archived"}.`,
        );
      }

      report.archived = restore ? 0 : 1;
      if (state.selectedId === String(id)) showReportList();
      renderMarkers();
      renderList();
      showToast(`Report ${restore ? "restored" : "archived"}.`);
    } catch (error) {
      showToast(error.message || `The report could not be ${restore ? "restored" : "archived"}.`);
    }
  }

  // 
  // Lightweight photo gallery (self-contained)
  // 
  function ensureGalleryElement() {
    let gallery = document.getElementById("reportGallery");
    if (gallery) return gallery;
    gallery = document.createElement("section");
    gallery.id = "reportGallery";
    gallery.className = "mapv2-photo-gallery";
    gallery.setAttribute("role", "dialog");
    gallery.setAttribute("aria-modal", "true");
    gallery.setAttribute("aria-hidden", "true");
    gallery.hidden = true;
    gallery.innerHTML = `
      <div class="mapv2-photo-gallery__dialog">
        <header class="mapv2-photo-gallery__header">
          <p id="reportGalleryName">Evidence photo</p>
          <span id="reportGalleryCounter" aria-live="polite"></span>
          <button type="button" id="reportGalleryClose">Close</button>
        </header>
        <div class="mapv2-photo-gallery__content">
          <button type="button" class="mapv2-photo-gallery__nav" id="reportGalleryPrevious" aria-label="Previous photo"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
          <img id="reportGalleryImage" alt="">
          <button type="button" class="mapv2-photo-gallery__nav" id="reportGalleryNext" aria-label="Next photo"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        </div>
      </div>
    `;
    document.body.appendChild(gallery);

    gallery.addEventListener("click", (event) => {
      if (event.target === gallery) closeGallery();
    });
    gallery.querySelector("#reportGalleryClose").addEventListener("click", closeGallery);
    gallery.querySelector("#reportGalleryPrevious").addEventListener("click", () => moveGallery(-1));
    gallery.querySelector("#reportGalleryNext").addEventListener("click", () => moveGallery(1));

    document.addEventListener("keydown", (event) => {
      if (gallery.hidden) return;
      if (event.key === "Escape") closeGallery();
      if (event.key === "ArrowLeft") moveGallery(-1);
      if (event.key === "ArrowRight") moveGallery(1);
    });

    return gallery;
  }

  function updateGalleryContent() {
    if (!galleryState) return;
    const { photos, index } = galleryState;
    const photo = photos[index];
    if (!photo) return;
    const gallery = document.getElementById("reportGallery");
    if (!gallery) return;
    const image = document.getElementById("reportGalleryImage");
    const name = document.getElementById("reportGalleryName");
    const counter = document.getElementById("reportGalleryCounter");
    const prev = document.getElementById("reportGalleryPrevious");
    const next = document.getElementById("reportGalleryNext");
    if (image) {
      image.src = photo.path;
      image.alt = photo.name || "Evidence photo";
    }
    if (name) name.textContent = photo.name || "Evidence photo";
    if (counter) counter.textContent = `${index + 1} / ${photos.length}`;
    if (prev) prev.disabled = index === 0;
    if (next) next.disabled = index === photos.length - 1;
  }

  function openGallery(photos, index) {
    if (!Array.isArray(photos) || !photos.length) return;
    const gallery = ensureGalleryElement();
    galleryState = { photos, index };
    gallery.hidden = false;
    gallery.setAttribute("aria-hidden", "false");
    updateGalleryContent();
    document.getElementById("reportGalleryClose")?.focus();
  }

  function closeGallery() {
    const gallery = document.getElementById("reportGallery");
    if (!gallery) return;
    gallery.hidden = true;
    gallery.setAttribute("aria-hidden", "true");
    galleryState = null;
  }

  function moveGallery(step) {
    if (!galleryState) return;
    const nextIndex = galleryState.index + step;
    if (nextIndex < 0 || nextIndex >= galleryState.photos.length) return;
    galleryState.index = nextIndex;
    updateGalleryContent();
  }

  //
  // Filters / search / visibility
  //

  function applyFilters() {
    renderMarkers();
    if (state.selectedId) {
      const report = reports.find((r) => String(r.id) === state.selectedId);
      if (report) {
        renderDetail(report);
        return;
      }
      state.selectedId = null;
    }
    renderList();
  }

  function setSearch(value) {
    state.search = String(value || "");
    applyFilters();
  }

  function setStatus(value) {
    state.status = String(value || "all");
    applyFilters();
  }

  function setArchived(value) {
    state.archivedOnly = Boolean(value);
    if (state.selectedId) showReportList();
    renderMarkers();
    renderList();
    updateMetrics();
  }

  function setVisible(isVisible) {
    state.scopeActive = isVisible;
    if (isVisible) {
      if (!state.loaded) loadReports();
      if (markersLayer && map && !map.hasLayer(markersLayer)) {
        markersLayer.addTo(map);
      }
      renderMarkers();
      renderList();
      updateMetrics();
    } else if (markersLayer && map && map.hasLayer(markersLayer)) {
      map.removeLayer(markersLayer);
    }
  }

  // 
  // Data loading
  // 

  async function loadReports() {
    if (state.loaded) return;
    try {
      const response = await fetch("actions/mapv2/get_reports.php", {
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) {
        throw new Error(payload.error || "Failed to load reports.");
      }
      reports.length = 0;
      (payload.reports || []).forEach((r) => reports.push(r));
      state.loaded = true;
      renderMarkers();
      renderList();
      updateMetrics();
    } catch (error) {
      console.warn("ReportMap: load failed", error);
      const list = document.getElementById("reportList");
      if (list) {
        list.innerHTML = `<p class="mapv2-empty-state"><span>Reports could not be loaded.</span></p>`;
      }
    }
  }

  // 
  // Init
  // 
  function init(mapInstance) {
    map = mapInstance;
    if (!markersLayer) markersLayer = L.layerGroup();
    loadBarangayBoundaries();
  }

  window.ReportMap = {
    init,
    setVisible,
    setSearch,
    setStatus,
    setArchived,
    refresh: loadReports,
  };
})();