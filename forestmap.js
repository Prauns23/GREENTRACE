// Initialize map
let map;
let markers = [];
let currentFilter = "all";

// Data from PHP
let forestAreas = [];
let reports = [];

// Helper function to escape HTML
function escapeHtml(text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

// Get marker icon based on type and status
function getMarkerIcon(type, status, isArchived = false) {
  let className = "custom-marker";
  let iconHtml = "";

  if (isArchived) {
    className += " archived-marker";
    iconHtml = '<i class="fas fa-archive"></i>';
  } else if (type === "forest") {
    className += " forest-marker";
    iconHtml = '<i class="fas fa-tree"></i>';
  } else if (type === "report") {
    const isActive = status === "pending" || status === "reviewed";
    if (isActive) {
      className += " report-marker";
      iconHtml = '<i class="fas fa-exclamation-triangle"></i>';
    } else {
      className += " archived-marker";
      iconHtml = '<i class="fas fa-check-circle"></i>';
    }
  }

  return L.divIcon({
    className: className,
    html: iconHtml,
    iconSize: [30, 30],
    popupAnchor: [0, -15],
  });
}

// Add markers to map
function addMarkers() {
  if (!map) {
    console.error("Map not initialized");
    return;
  }

  // Clear existing markers
  markers.forEach((marker) => {
    if (map.hasLayer(marker)) {
      map.removeLayer(marker);
    }
  });
  markers = [];

  // Debug: Log data
  console.log("Forest Areas:", forestAreas);
  console.log("Reports:", reports);

  // Process forest areas
  if (forestAreas && forestAreas.length > 0) {
    forestAreas.forEach((area) => {
      const isArchived = area.status === "archived";
      
      let shouldShow = false;
      if (currentFilter === "all") shouldShow = true;
      else if (currentFilter === "forests") shouldShow = true;
      else if (currentFilter === "reports") shouldShow = false;
      else if (currentFilter === "archived") shouldShow = isArchived;

      // Validate coordinates
      const lat = parseFloat(area.latitude);
      const lng = parseFloat(area.longitude);

      if (shouldShow && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
        const icon = getMarkerIcon("forest", area.status, isArchived);
        const marker = L.marker([lat, lng], { icon }).addTo(map);

        // Create unique ID for this marker's popup content
        const markerId = `forest_${area.id}`;
        
        marker.bindPopup(`
          <div class="popup-content" id="popup-${markerId}">
            <h4>${escapeHtml(area.name)}</h4>
            <p>${escapeHtml(area.location_name)}</p>
            <p><strong>Established:</strong> ${area.date_established || "N/A"}</p>
            <div class="popup-details-link">
              <span class="view-details" onclick="showDetails(${area.id}, 'forest')">View Details</span>
            </div>
            <div class="popup-buttons">
              <button class="edit-btn" onclick="editForestArea(${area.id})">
                <i class="fa-regular fa-pen-to-square"></i> Edit
              </button>
              <button class="delete-btn" onclick="deleteForestArea(${area.id})">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </div>
          </div>
        `);

        marker.itemData = { ...area, type: "forest" };
        markers.push(marker);
        console.log(`Added forest marker: ${area.name} at [${lat}, ${lng}]`);
      }
    });
  } else {
    console.warn("No forest areas data available");
  }

  // Process reports
  if (reports && reports.length > 0) {
    reports.forEach((report) => {
      const isResolved = report.status === "resolved" || report.status === "dismissed";
      
      let shouldShow = false;
      if (currentFilter === "all") shouldShow = true;
      else if (currentFilter === "reports") shouldShow = !isResolved;
      else if (currentFilter === "forests") shouldShow = false;
      else if (currentFilter === "archived") shouldShow = isResolved;

      // Validate coordinates
      const lat = parseFloat(report.latitude);
      const lng = parseFloat(report.longitude);

      if (shouldShow && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
        const icon = getMarkerIcon("report", report.status, isResolved);
        const marker = L.marker([lat, lng], { icon }).addTo(map);

        marker.bindPopup(`
          <div class="popup-content">
            <h4>${escapeHtml(report.issue_type)}</h4>
            <p>${escapeHtml(report.location)}</p>
            <p><strong>Status:</strong> ${report.status}</p>
            <p><strong>Reported:</strong> ${report.created_at ? new Date(report.created_at).toLocaleDateString() : "N/A"}</p>
            <div class="popup-details-link">
              <span class="view-details" onclick="showReportDetails(${report.id})">View Details</span>
            </div>
            <div class="popup-buttons">
              <button class="archive-btn" onclick="archiveReport(${report.id})">
                <i class="fas fa-archive"></i> Archive
              </button>
              <button class="delete-btn" onclick="deleteReport(${report.id})">
                <i class="fa-solid fa-trash"></i> Delete
              </button>
            </div>
          </div>
        `);

        marker.itemData = { ...report, type: "report" };
        markers.push(marker);
        console.log(
          `Added report marker: ${report.issue_type} at [${lat}, ${lng}]`,
        );
      }
    });
  } else {
    console.warn("No reports data available");
  }

  console.log(`Total markers added: ${markers.length}`);
  updateStats();
  updateRecentActivity();

  // Auto-fit map bounds to show all markers
  if (markers.length > 0) {
    const group = L.featureGroup(markers);
    map.fitBounds(group.getBounds().pad(0.1));
  }
}

// Update statistics
function updateStats() {
  const activeForests = forestAreas
    ? forestAreas.filter((f) => f.status !== "archived").length
    : 0;
  const activeReports = reports
    ? reports.filter((r) => r.status === "pending" || r.status === "reviewed")
        .length
    : 0;
  const totalMarkers = activeForests + activeReports;

  const totalMarkersEl = document.getElementById("totalMarkers");
  const activeForestsEl = document.getElementById("activeForests");
  const activeReportsEl = document.getElementById("activeReports");

  if (totalMarkersEl) totalMarkersEl.textContent = totalMarkers;
  if (activeForestsEl) activeForestsEl.textContent = activeForests;
  if (activeReportsEl) activeReportsEl.textContent = activeReports;
}

// Update recent activity list
function updateRecentActivity() {
  // All users viewing this page are admins, so show ALL items
  const forestItems = forestAreas ? forestAreas.map((f) => ({
    id: f.id,
    name: f.name,
    locationName: f.location_name,
    date: f.date_established || f.created_at,
    status: f.status === 'archived' ? 'archived' : (f.status || 'active'),
    type: "forest",
    description: f.description,
    isArchived: f.status === 'archived'
  })) : [];
  
  const reportItems = reports ? reports.map((r) => ({
    id: r.id,
    name: r.issue_type,
    locationName: r.location,
    date: r.created_at,
    status: r.status,
    type: "report",
    description: r.description,
    isArchived: (r.status === 'resolved' || r.status === 'dismissed')
  })) : [];

  const allItems = [...forestItems, ...reportItems];
  const recentItems = allItems
    .sort((a, b) => new Date(b.date) - new Date(a.date))
    .slice(0, 10); // Show up to 10 most recent items
  
  const activityList = document.getElementById("activityList");
  if (activityList) {
    if (recentItems.length === 0) {
      activityList.innerHTML = '<div class="no-activity">No recent activity</div>';
      return;
    }
    
    activityList.innerHTML = recentItems
      .map((item) => {
        // Determine status display
        let statusClass = item.status;
        let statusIcon = '';
        let statusText = item.status;
        
        if (item.type === 'forest') {
          if (item.status === 'archived') {
            statusIcon = '<i class="fas fa-archive"></i> ';
            statusClass = 'archived';
          } else {
            statusIcon = '<i class="fas fa-tree"></i> ';
          }
        } else {
          if (item.status === 'dismissed' || item.status === 'resolved') {
            statusIcon = '<i class="fas fa-check-circle"></i> ';
            statusClass = 'resolved';
            statusText = 'Archived';
          } else if (item.status === 'pending') {
            statusIcon = '<i class="fas fa-clock"></i> ';
          } else if (item.status === 'reviewed') {
            statusIcon = '<i class="fas fa-eye"></i> ';
          }
        }
        
        return `
          <div class="activity-item" onclick="showDetails(${item.id}, '${item.type}')">
            <div class="activity-icon ${item.type}">
              <i class="fas ${item.type === "forest" ? "fa-tree" : "fa-exclamation-triangle"}"></i>
            </div>
            <div class="activity-info">
              <div class="activity-name">${escapeHtml(item.name)}</div>
              <div class="activity-location">${escapeHtml(item.locationName)}</div>
            </div>
            <div class="activity-status ${statusClass}">${statusText}</div>
          </div>
        `;
      })
      .join("");
  }
}

// Show forest details
window.showForestDetails = function (id) {
  const forest = forestAreas.find((f) => f.id === id);
  if (forest) {
    alert(
      `🌳 Forest: ${forest.name}\n📍 Location: ${forest.location_name}\n📊 Status: ${forest.status}\n📝 Description: ${forest.description || "No description"}`,
    );
  }
};

// Show report details
window.showReportDetails = function(id) {
  closeAllFloating();
  const container = document.getElementById("floatingReportDetailsContainer");
  const iframe = document.getElementById("reportDetailsFrame");
  if (iframe) {
    iframe.src = "modals/report_details_modal.php?id=" + id;
  }
  if (container) {
    container.classList.add("active");
    if (typeof overlay !== "undefined" && overlay) {
      overlay.classList.add("active");
    }
    if (typeof body !== "undefined" && body) {
      body.classList.add("login-active");
    }
    if (typeof activeContainer !== "undefined") {
      activeContainer = container;
    }
  } 
}

// Show details based on type
window.showDetails = function (id, type) {
  if (type === "forest") {
    showForestDetails(id);
  } else {
    showReportDetails(id);
  }
};

// Edit Forest Area
window.editForestArea = function(id) {
    closeAllFloating();
    const container = document.getElementById("floatingEditForestContainer");
    const iframe = document.getElementById("editForestFrame");
    if (iframe) {
        iframe.src = "modals/edit_forest_modal.php?id=" + id;
    }
    if (container) {
        container.classList.add("active");
        if (typeof overlay !== "undefined" && overlay) {
            overlay.classList.add("active");
        }
        if (typeof body !== "undefined" && body) {
            body.classList.add("login-active");
        }
        if (typeof activeContainer !== "undefined") {
            activeContainer = container;
        }
    }
};

// Delete Forest Area
window.deleteForestArea = function(id) {
    if (confirm("Are you sure you want to permanently delete this forest area? This action cannot be undone.")) {
        // Show loading on button (optional – we'll just use a global indicator)
        fetch('actions/delete_forest_area.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast('Forest area deleted successfully!');
                } else {
                    alert('Forest area deleted successfully!');
                }
                location.reload();
            } else {
                alert(data.error || 'Failed to delete forest area.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
};

// Archive Report
window.archiveReport = function (id) {
  if (confirm("Archive this report? It will no longer appear in active reports.")) {
    // Show loading state
    const btn = event.target.closest('.archive-btn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Archiving...';
    }

    fetch('actions/archive_report.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'report_id=' + id
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (typeof showToast === 'function') {
          showToast('Report archived successfully');
        } else {
          alert('Report archived successfully!');
        }
        // Refresh the map and data
        location.reload();
      } else {
        alert(data.error || 'Failed to archive report.');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-archive"></i> Archive';
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred. Please try again.');
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-archive"></i> Archive';
      }
    });
  }
};

// Delete Report
window.deleteReport = function (id) {
  if (confirm("Are you sure you want to permanently delete this report? This action cannot be undone.")) {
    // Show loading state
    const btn = event.target.closest('.delete-btn');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    }
    
    fetch('actions/delete_report.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'report_id=' + id
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (typeof showToast === 'function') {
          showToast('Report deleted successfully!');
        } else {
          alert('Report deleted successfully!');
        }
        // Refresh the map and data
        location.reload();
      } else {
        alert(data.error || 'Failed to delete report.');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
        }
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('An error occurred. Please try again.');
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
      }
    });
  }
};

// Filter markers
window.filterMarkers = function (filter) {
  currentFilter = filter;

  document.querySelectorAll(".filter-btn").forEach((btn) => {
    btn.classList.remove("active");
  });
  const activeBtn = document.querySelector(
    `.filter-btn[data-filter="${filter}"]`,
  );
  if (activeBtn) activeBtn.classList.add("active");

  addMarkers();
};

// Search functionality
function searchLocations() {
  const searchTerm = document.getElementById("searchInput").value.toLowerCase();

  markers.forEach((marker) => {
    const data = marker.itemData;
    let searchable = "";

    if (data.type === "forest") {
      searchable = (data.name + " " + data.location_name).toLowerCase();
    } else {
      searchable = (data.issue_type + " " + data.location).toLowerCase();
    }

    if (searchTerm === "" || searchable.includes(searchTerm)) {
      if (!map.hasLayer(marker)) {
        marker.addTo(map);
      }
    } else {
      if (map.hasLayer(marker)) {
        map.removeLayer(marker);
      }
    }
  });
}

// Zoom controls
window.zoomIn = function () {
  if (map) map.zoomIn();
};

window.zoomOut = function () {
  if (map) map.zoomOut();
};

// Add new marker (opens modal)
window.addNewMarker = function () {
  closeAllFloating();
  const container = document.getElementById("floatingAddMarkerContainer");
  const iframe = document.getElementById("addMarkerFrame");
  if (iframe) {
    iframe.src = "modals/add_marker_modal.php";
  }
  if (container) {
    container.classList.add("active");
    if (typeof overlay !== "undefined" && overlay) {
      overlay.classList.add("active");
    }
    if (typeof body !== "undefined" && body) {
      body.classList.add("login-active");
    }
    if (typeof activeContainer !== "undefined") {
      activeContainer = container;
    }
  }
};

// Close modal
window.closeModal = function () {
  const modal = document.getElementById("confirmModal");
  if (modal) modal.classList.remove("active");
};

// Initialize map
function initMap() {
  // Check if map container exists
  const mapContainer = document.getElementById("forestMap");
  if (!mapContainer) {
    console.error("Map container not found");
    return;
  }

  // Default center (Philippines)
  map = L.map("forestMap").setView([14.68, 120.35], 12);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(map);

  console.log("Map initialized");
  addMarkers();
}

// Wait for data and DOM to be ready
document.addEventListener("DOMContentLoaded", function () {
  // Get data from PHP
  if (typeof window.forestAreasData !== "undefined") {
    forestAreas = window.forestAreasData;
    console.log("Forest areas loaded:", forestAreas.length);
  } else {
    console.warn("forestAreasData not found");
  }

  if (typeof window.reportsData !== "undefined") {
    reports = window.reportsData;
    console.log("Reports loaded:", reports.length);
  } else {
    console.warn("reportsData not found");
  }

  // Initialize map
  initMap();

  // Add event listeners
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    searchInput.addEventListener("input", searchLocations);
  }

  document.querySelectorAll(".filter-btn").forEach((btn) => {
    btn.addEventListener("click", () => filterMarkers(btn.dataset.filter));
  });
});