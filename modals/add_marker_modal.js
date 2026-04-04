// Initialize Map
let map;
let marker;
let currentLat = null;
let currentLng = null;

// Initialize the map when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  initMap();
  setupFormSubmit();
});

function initMap() {
  // Default center (Philippines)
  const defaultLat = 14.68;
  const defaultLng = 120.35;

  map = L.map("locationPickerMap").setView([defaultLat, defaultLng], 12);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(map);

  // Add Click hanlder to set marker
  map.on("click", function (e) {
    setMarker(e.latlng.lat, e.latlng.lng);
  });
}

function setMarker(lat, lng) {
  // remove existing marker if any
  if (marker) {
    map.removeLayer(marker);
  }

  // Add new marker
  marker = L.marker([lat, lng], { draggable: true }).addTo(map);

  // update coordinates
  currentLat = lat;
  currentLng = lng;

  // Update hidden fields
  document.getElementById("latitude").value = lat.toFixed(6);
  document.getElementById("longitude").value = lng.toFixed(6);

  // Update display
  document.getElementById("coordDisplay").innerHTML =
    `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

  // Center map on marker
  map.setView([lat, lng], 15);

  // Make marker draggable
  marker.on("dragend", function (e) {
    const newPos = e.target.getLatLng();
    setMarker(newPos.lat, newPos.lng);
  });
}

function setupFormSubmit() {
  const form = document.getElementById("addForestForm");

  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    // Get form values
    const name = document.getElementById("forestName").value.trim();
    const locationName = document.getElementById("locationName").value.trim();
    const latitude = document.getElementById("latitude").value;
    const longitude = document.getElementById("longitude").value;
    const dateEstablished = document.getElementById("dateEstablished").value;
    const status = document.getElementById("status").value;
    const description = document.getElementById("description").value.trim();

    // Validation
    if (!name) {
      alert("Please enter a forest name.");
      return;
    }

    if (!locationName) {
      alert("Please enter a locaiton name.");
      return;
    }

    if (!locationName) {
      alert("Please enter a location name.");
      return;
    }

    if (!latitude || !longitude) {
      alert("Please select a location on the map.");
      return;
    }

    // Disable submit button
    const submitBtn = form.querySelector(".btn-submit");
    submitBtn.disabled = true;
    submitBtn.textContent = "Adding...";

    // Send data to server
    try {
      const response = await fetch("../actions/add_forest_area.php", {
        method: "POST",
        headers: {
          "Content-type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          name: name,
          location_name: locationName,
          latitude: latitude,
          longitude: longitude,
          date_established: dateEstablished,
          status: status,
          description: description,
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Shpw success message via parent toast
        if (typeof parent.showToast === "function") {
          parent.showToast("Forest area added successfully!");
        } else {
          alert("Forest area added successfully");
        }

        // Close modal
        if (typeof parent.hideFloating === "function") {
          parent.hideFloating();
        }

        // Reload parent page to show new marker
        setTimeout(() => {
          parent.location.reload();
        }, 500);
      } else {
        alert(data.error || "Failed to add forest area.");
        submitBtn.disabled = false;
        submitBtn.textContent = "Add Forest Area";
      }
    } catch (error) {
      console.error("Error: ", error);
      alert("An error occured. Please try again.");
      submitBtn.disabled = false;
      submitBtn.textContent = "Add Forest Area";
    }
  });
}
