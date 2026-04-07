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
    // Get initial coordinates from hidden fields
    const initialLat = parseFloat(document.getElementById("latitude").value) || 14.68;
    const initialLng = parseFloat(document.getElementById("longitude").value) || 120.35;

    map = L.map("locationPickerMap").setView([initialLat, initialLng], 12);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    // Set initial marker if coordinates exist
    if (initialLat && initialLng) {
        setMarker(initialLat, initialLng);
    }

    // Add click handler to set marker
    map.on("click", function (e) {
        setMarker(e.latlng.lat, e.latlng.lng);
    });
}

function setMarker(lat, lng) {
    // Remove existing marker if any
    if (marker) {
        map.removeLayer(marker);
    }

    // Add new marker
    marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    // Update coordinates
    currentLat = lat;
    currentLng = lng;

    // Update hidden fields
    document.getElementById("latitude").value = lat.toFixed(6);
    document.getElementById("longitude").value = lng.toFixed(6);

    // Update display
    document.getElementById("coordDisplay").innerHTML = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

    // Center map on marker
    map.setView([lat, lng], 15);

    // Make marker draggable
    marker.on("dragend", function (e) {
        const newPos = e.target.getLatLng();
        setMarker(newPos.lat, newPos.lng);
    });
}

function setupFormSubmit() {
    const form = document.getElementById("editForestForm");

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
        const id = document.querySelector("input[name='id']").value;

        // Validation
        if (!name) {
            alert("Please enter a forest name.");
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
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.textContent = "Saving...";

        // Send data to server
        try {
            const response = await fetch("../actions/update_forest_area.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams({
                    id: id,
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
                // Show success message via parent toast
                if (typeof parent.showToast === "function") {
                    parent.showToast("Forest area updated successfully!");
                } else {
                    alert("Forest area updated successfully");
                }

                // Close modal
                if (typeof parent.hideFloating === "function") {
                    parent.hideFloating();
                }

                // Reload parent page to show updated marker
                setTimeout(() => {
                    parent.location.reload();
                }, 500);
            } else {
                alert(data.error || "Failed to update forest area.");
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (error) {
            console.error("Error: ", error);
            alert("An error occurred. Please try again.");
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}