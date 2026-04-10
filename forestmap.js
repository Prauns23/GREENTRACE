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
// function addMarkers() {
//   if (!map) {
//     console.error("Map not initialized");
//     return;
//   }

//   // Clear existing markers
//   markers.forEach((marker) => {
//     if (map.hasLayer(marker)) {
//       map.removeLayer(marker);
//     }
//   });
//   markers = [];

//   // Debug: Log data
//   console.log("Forest Areas:", forestAreas);
//   console.log("Reports:", reports);

//   // Process forest areas
//   if (forestAreas && forestAreas.length > 0) {
//     forestAreas.forEach((area) => {
//       const isArchived = area.archived == 1; // Use dedicated archived column

//       let shouldShow = false;
//       if (currentFilter === "all") shouldShow = !isArchived;
//       else if (currentFilter === "forests") shouldShow = !isArchived;
//       else if (currentFilter === "reports") shouldShow = false;
//       else if (currentFilter === "archived") shouldShow = isArchived;

//       // Validate coordinates
//       const lat = parseFloat(area.latitude);
//       const lng = parseFloat(area.longitude);

//       if (shouldShow && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
//         const icon = getMarkerIcon("forest", area.status, isArchived);
//         const marker = L.marker([lat, lng], { icon }).addTo(map);

//         // Create unique ID for this marker's popup content
//         const markerId = `forest_${area.id}`;

//         // Conditional buttons for forest areas
//         let forestButtons = "";
//         if (isArchived) {
//           // Archived forest: Show Restore button (no Edit button)
//           forestButtons = `
//             <button class="restore-btn" onclick="restoreForestArea(${area.id})">
//                 <i class="fas fa-undo-alt"></i> Restore
//             </button>
//             <button class="delete-btn" onclick="deleteForestArea(${area.id})">
//                 <i class="fa-solid fa-trash"></i> Delete
//             </button>
//           `;
//         } else {
//           // Active forest: Show Edit and Archive buttons
//           forestButtons = `
//             <button class="edit-btn" onclick="editForestArea(${area.id})">
//                 <i class="fa-regular fa-pen-to-square"></i> Edit
//             </button>
//             <button class="archive-btn" onclick="archiveForestArea(${area.id})">
//                 <i class="fas fa-archive"></i> Archive
//             </button>
//             <button class="delete-btn" onclick="deleteForestArea(${area.id})">
//                 <i class="fa-solid fa-trash"></i> Delete
//             </button>
//           `;
//         }

//         marker.bindPopup(`
//           <div class="popup-content" id="popup-${markerId}">
//             <h4>${escapeHtml(area.name)}</h4>
//             <p>${escapeHtml(area.location_name)}</p>
//             <p><strong>Established:</strong> ${area.date_established || "N/A"}</p>
//             <p>${area.latitude}, ${area.longitude}</p>
//             <div class="popup-details-link">
//               <span class="view-details" onclick="showDetails(${area.id}, 'forest')">View Details</span>
//             </div>
//             <div class="popup-buttons">
//               ${forestButtons}
//             </div>
//           </div>
//         `);

//         marker.itemData = { ...area, type: "forest" };
//         markers.push(marker);
//         console.log(`Added forest marker: ${area.name} at [${lat}, ${lng}]`);
//       }
//     });
//   } else {
//     console.warn("No forest areas data available");
//   }

//   // Process reports
//   if (reports && reports.length > 0) {
//     reports.forEach((report) => {
//       const isArchived = report.archived == 1;
//       const isResolvedOrDismissed =
//         report.status === "resolved" || report.status === "dismissed";

//       let shouldShow = false;
//       if (currentFilter === "all") {
//         shouldShow = !isArchived;
//       } else if (currentFilter === "reports") {
//         shouldShow = !isArchived;
//       } else if (currentFilter === "forests") {
//         shouldShow = false;
//       } else if (currentFilter === "archived") {
//         shouldShow = isArchived;
//       }

//       // Validate coordinates
//       const lat = parseFloat(report.latitude);
//       const lng = parseFloat(report.longitude);

//       if (shouldShow && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
//         const icon = getMarkerIcon("report", report.status, isArchived);
//         const marker = L.marker([lat, lng], { icon }).addTo(map);

//         // Conditional buttons: Restore for archived, Archive for non-archived
//         const popupButtons = isArchived
//           ? `<button class="restore-btn" onclick="restoreReport(${report.id})">
//                  <i class="fas fa-undo-alt"></i> Restore
//              </button>
//              <button class="delete-btn" onclick="deleteReport(${report.id})">
//                  <i class="fa-solid fa-trash"></i> Delete
//              </button>`
//           : `<button class="archive-btn" onclick="archiveReport(${report.id})">
//                  <i class="fas fa-archive"></i> Archive
//              </button>
//              <button class="delete-btn" onclick="deleteReport(${report.id})">
//                  <i class="fa-solid fa-trash"></i> Delete
//              </button>`;

//         marker.bindPopup(`
//           <div class="popup-content">
//             <h4>${escapeHtml(report.issue_type)}</h4>
//             <p>${escapeHtml(report.location)}</p>
//             <p><strong>Status:</strong> ${report.status}</p>
//             <p><strong>Reported:</strong> ${report.created_at ? new Date(report.created_at).toLocaleDateString() : "N/A"}</p>
//             <div class="popup-details-link">
//               <span class="view-details" onclick="showReportDetails(${report.id})">View Details</span>
//             </div>
//             <div class="popup-buttons">
//               ${popupButtons}
//             </div>
//           </div>
//         `);

//         marker.itemData = { ...report, type: "report" };
//         markers.push(marker);
//         console.log(
//           `Added report marker: ${report.issue_type} at [${lat}, ${lng}]`,
//         );
//       }
//     });
//   } else {
//     console.warn("No reports data available");
//   }

//   console.log(`Total markers added: ${markers.length}`);
//   updateStats();
//   updateRecentActivity();

//   // Auto-fit map bounds to show all markers
//   if (markers.length > 0) {
//     const group = L.featureGroup(markers);
//     map.fitBounds(group.getBounds().pad(0.1));
//   }
// }

// Add markers to map with clustering
function addMarkers() {
    if (!map) {
        console.error("Map not initialized");
        return;
    }

    if (window.markerCluster) {
        map.removeLayer(window.markerCluster);
    }

    // Create a new cluster group (customize options as needed)
    const markerCluster = L.markerClusterGroup({
        maxClusterRadius: 60,        // pixels – smaller = more clusters
        spiderfyOnMaxZoom: true,     // spread markers when clicking a cluster at max zoom
        showCoverageOnHover: false,  // show bounds of cluster on hover? false = less clutter
        zoomToBoundsOnClick: true,   // zoom to cluster bounds when clicked
        disableClusteringAtZoom: 18  // disable clustering when zoomed in very close
    });

    // Debug: Log data
    console.log("Forest Areas:", forestAreas);
    console.log("Reports:", reports);

    // Process forest areas
    if (forestAreas && forestAreas.length > 0) {
        forestAreas.forEach((area) => {
            const isArchived = area.archived == 1;

            let shouldShow = false;
            if (currentFilter === "all") shouldShow = !isArchived;
            else if (currentFilter === "forests") shouldShow = !isArchived;
            else if (currentFilter === "reports") shouldShow = false;
            else if (currentFilter === "archived") shouldShow = isArchived;

            const lat = parseFloat(area.latitude);
            const lng = parseFloat(area.longitude);

            if (shouldShow && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                const icon = getMarkerIcon("forest", area.status, isArchived);
                const marker = L.marker([lat, lng], { icon });
                const markerId = `forest_${area.id}`;

                // Conditional buttons (same as before)
                let forestButtons = "";
                if (isArchived) {
                    forestButtons = `
                        <button class="restore-btn" onclick="restoreForestArea(${area.id})">
                            <i class="fas fa-undo-alt"></i> Restore
                        </button>
                        <button class="delete-btn" onclick="deleteForestArea(${area.id})">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    `;
                } else {
                    forestButtons = `
                        <button class="edit-btn" onclick="editForestArea(${area.id})">
                            <i class="fa-regular fa-pen-to-square"></i> Edit
                        </button>
                        <button class="archive-btn" onclick="archiveForestArea(${area.id})">
                            <i class="fas fa-archive"></i> Archive
                        </button>
                        <button class="delete-btn" onclick="deleteForestArea(${area.id})">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    `;
                }

                marker.bindPopup(`
                    <div class="popup-content" id="popup-${markerId}">
                        <h4>${escapeHtml(area.name)}</h4>
                        <p>${escapeHtml(area.location_name)}</p>
                        <p><strong>Established:</strong> ${area.date_established || "N/A"}</p>
                        <p>${area.latitude}, ${area.longitude}</p>
                        <div class="popup-details-link">
                            <span class="view-details" onclick="showDetails(${area.id}, 'forest')">View Details</span>
                        </div>
                        <div class="popup-buttons">
                            ${forestButtons}
                        </div>
                    </div>
                `);

                marker.itemData = { ...area, type: "forest" };
                markerCluster.addLayer(marker);  // add to cluster group
                console.log(`Added forest marker: ${area.name} at [${lat}, ${lng}]`);
            }
        });
    } else {
        console.warn("No forest areas data available");
    }

    // Process reports (similar changes)
    if (reports && reports.length > 0) {
        reports.forEach((report) => {
            const isArchived = report.archived == 1;

            let shouldShow = false;
            if (currentFilter === "all") shouldShow = !isArchived;
            else if (currentFilter === "reports") shouldShow = !isArchived;
            else if (currentFilter === "forests") shouldShow = false;
            else if (currentFilter === "archived") shouldShow = isArchived;

            const lat = parseFloat(report.latitude);
            const lng = parseFloat(report.longitude);

            if (shouldShow && !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                const icon = getMarkerIcon("report", report.status, isArchived);
                const marker = L.marker([lat, lng], { icon });

                const popupButtons = isArchived
                    ? `<button class="restore-btn" onclick="restoreReport(${report.id})">
                           <i class="fas fa-undo-alt"></i> Restore
                       </button>
                       <button class="delete-btn" onclick="deleteReport(${report.id})">
                           <i class="fa-solid fa-trash"></i> Delete
                       </button>`
                    : `<button class="archive-btn" onclick="archiveReport(${report.id})">
                           <i class="fas fa-archive"></i> Archive
                       </button>
                       <button class="delete-btn" onclick="deleteReport(${report.id})">
                           <i class="fa-solid fa-trash"></i> Delete
                       </button>`;

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
                            ${popupButtons}
                        </div>
                    </div>
                `);

                marker.itemData = { ...report, type: "report" };
                markerCluster.addLayer(marker);
                console.log(`Added report marker: ${report.issue_type} at [${lat}, ${lng}]`);
            }
        });
    } else {
        console.warn("No reports data available");
    }

    // Add the cluster group to the map
    map.addLayer(markerCluster);
    window.markerCluster = markerCluster; // store for later removal

    console.log(`Total markers added to cluster: ${markerCluster.getLayers().length}`);
    updateStats();
    updateRecentActivity();

    // Auto-fit map bounds to show all clustered markers
    if (markerCluster.getLayers().length > 0) {
        map.fitBounds(markerCluster.getBounds().pad(0.1));
    }
}

// Update statistics
function updateStats() {
  const activeForests = forestAreas
    ? forestAreas.filter((f) => f.archived != 1).length
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
  const forestItems = forestAreas
    ? forestAreas.map((f) => ({
        id: f.id,
        name: f.name,
        locationName: f.location_name,
        date: f.date_established || f.created_at,
        status: f.status, // original status (active, ongoing, completed)
        type: "forest",
        description: f.description,
        isArchived: f.archived == 1,
      }))
    : [];

  const reportItems = reports
    ? reports.map((r) => ({
        id: r.id,
        name: r.issue_type,
        locationName: r.location,
        date: r.created_at,
        status: r.status,
        type: "report",
        description: r.description,
        isArchived: r.archived == 1,
      }))
    : [];

  const allItems = [...forestItems, ...reportItems];
  const recentItems = allItems
    .sort((a, b) => new Date(b.date) - new Date(a.date))
    .slice(0, 10); // Show up to 10 most recent items

  const activityList = document.getElementById("activityList");
  if (activityList) {
    if (recentItems.length === 0) {
      activityList.innerHTML =
        '<div class="no-activity">No recent activity</div>';
      return;
    }

    activityList.innerHTML = recentItems
      .map((item) => {
        let statusClass = "";
        let statusIcon = "";
        let statusText = "";

        if (item.type === "forest") {
          if (item.isArchived) {
            statusIcon = '<i class="fas fa-archive"></i> ';
            statusClass = "archived";
            statusText = "Archived";
          } else {
            statusIcon = '<i class="fas fa-tree"></i> ';
            statusClass = item.status;
            statusText =
              item.status.charAt(0).toUpperCase() + item.status.slice(1);
          }
        } else {
          // report
          if (item.isArchived) {
            statusIcon = '<i class="fas fa-archive"></i> ';
            statusClass = "archived";
            statusText = "Archived";
          } else {
            // Not archived – show actual status
            switch (item.status) {
              case "pending":
                statusIcon = '<i class="fas fa-clock"></i> ';
                statusClass = "pending";
                statusText = "Pending";
                break;
              case "reviewed":
                statusIcon = '<i class="fas fa-eye"></i> ';
                statusClass = "reviewed";
                statusText = "Reviewed";
                break;
              case "resolved":
                statusIcon = '<i class="fas fa-check-circle"></i> ';
                statusClass = "resolved";
                statusText = "Resolved";
                break;
              case "dismissed":
                statusIcon = '<i class="fas fa-times-circle"></i> ';
                statusClass = "dismissed";
                statusText = "Dismissed";
                break;
              default:
                statusIcon = "";
                statusClass = "";
                statusText = item.status;
            }
          }
        }

        return `
         <div class="activity-item ${item.type}" onclick="showDetails(${item.id}, '${item.type}')">
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
  closeAllFloating();
  const container = document.getElementById("floatingForestDetailsContainer");
  const iframe = document.getElementById("forestDetailsFrame");
  if (iframe) {
    iframe.src = "modals/forest_details.php?id=" + id;
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

// Show report details
window.showReportDetails = function (id) {
  closeAllFloating();
  const container = document.getElementById("floatingReportDetailsContainer");
  const iframe = document.getElementById("reportDetailsFrame");
  if (iframe) {
    iframe.src = "modals/report_details.php?id=" + id;
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

// Show details based on type
window.showDetails = function (id, type) {
  if (type === "forest") {
    showForestDetails(id);
  } else {
    showReportDetails(id);
  }
};

// Edit Forest Area
window.editForestArea = function (id) {
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

// Archive Forest Area
window.archiveForestArea = function (id) {
  if (confirm("Archive this forest area?")) {
    fetch("actions/archive_forest.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "id=" + id,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (typeof showToast === "function") {
            showToast("Forest area archived successfully");
          } else {
            alert("Forest area archived successfully!");
          }
          location.reload();
        } else {
          alert(data.error || "Failed to archive forest area.");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("An error occurred. Please try again.");
      });
  }
};

// Restore Forest Area
window.restoreForestArea = function (id) {
  if (confirm("Restore this forest area? It will reappear on the main map.")) {
    fetch("actions/restore_forest.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "id=" + id,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (typeof showToast === "function") {
            showToast("Forest area restored successfully");
          } else {
            alert("Forest area restored successfully!");
          }
          location.reload();
        } else {
          alert(data.error || "Failed to restore forest area.");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("An error occurred. Please try again.");
      });
  }
};

// Delete Forest Area
window.deleteForestArea = function (id) {
  if (
    confirm(
      "Are you sure you want to permanently delete this forest area? This action cannot be undone.",
    )
  ) {
    fetch("actions/delete_forest_area.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "id=" + id,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (typeof showToast === "function") {
            showToast("Forest area deleted successfully!");
          } else {
            alert("Forest area deleted successfully!");
          }
          location.reload();
        } else {
          alert(data.error || "Failed to delete forest area.");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("An error occurred. Please try again.");
      });
  }
};

// Archive Report
window.archiveReport = function (reportId) {
  if (
    confirm(
      "Archive this report? It will no longer appear on the map, but will stay in recent activity.",
    )
  ) {
    fetch("actions/archive_report.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "report_id=" + reportId,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (typeof showToast === "function") {
            showToast("Report archived successfully");
          } else {
            alert("Report archived successfully!");
          }
          location.reload();
        } else {
          alert(data.error || "Failed to archive report.");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("An error occurred. Please try again.");
      });
  }
};

// Delete Report
window.deleteReport = function (id) {
  if (
    confirm(
      "Are you sure you want to permanently delete this report? This action cannot be undone.",
    )
  ) {
    const btn = event.target.closest(".delete-btn");
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    }

    fetch("actions/delete_report.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: "report_id=" + id,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (typeof showToast === "function") {
            showToast("Report deleted successfully!");
          } else {
            alert("Report deleted successfully!");
          }
          location.reload();
        } else {
          alert(data.error || "Failed to delete report.");
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
          }
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("An error occurred. Please try again.");
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa-solid fa-trash"></i> Delete';
        }
      });
  }
};

// Restore report
window.restoreReport = function (reportId) {
  if (confirm("Restore this report? It will reappear on the map.")) {
    fetch("actions/restore_report.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "report_id=" + reportId,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (typeof showToast === "function") {
            showToast("Report restored successfully");
          } else {
            alert("Report restored successfully!");
          }
          location.reload();
        } else {
          alert(data.error || "Failed to restore report.");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert("An error occurred. Please try again.");
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
  const mapContainer = document.getElementById("forestMap");
  if (!mapContainer) {
    console.error("Map container not found");
    return;
  }

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

  initMap();

  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    searchInput.addEventListener("input", searchLocations);
  }

  document.querySelectorAll(".filter-btn").forEach((btn) => {
    btn.addEventListener("click", () => filterMarkers(btn.dataset.filter));
  });
});
