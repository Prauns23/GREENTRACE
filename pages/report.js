document.addEventListener("DOMContentLoaded", function () {
  //  DOM ELEMENTS
  const fileUpload = document.getElementById("fileUpload");
  const uploadArea = document.querySelector(".upload-area");
  const photoPreview = document.getElementById("photoPreview");
  const gpsBtn = document.querySelector(".gps-btn");
  const locationMap = document.getElementById("locationMap");
  const locationHint = document.getElementById("locationHint");
  const locationInput = document.querySelector(".location-search");
  const reportForm = document.getElementById("reportForm");
  const imageModal = document.getElementById("imageModal");
  const modalImage = document.querySelector(".modal-image");
  const modalCounter = document.querySelector(".modal-counter");

  //  STATE VARIABLES
  let gpsActive = false;
  let uploadedFiles = []; // Array to store uploaded files
  let currentImageIndex = 0; // Current index in modal
  const MAX_PHOTOS = 5; // Maximum number of photos allowed
  let map = null; // Leaflet map instance
  let mapMarker = null; // Marker on map
  let mapInitialized = false; // Flag to check if map is initialized

  //  PHOTO UPLOAD & PREVIEW
  if (fileUpload) {
    fileUpload.addEventListener("change", function (e) {
      const newFiles = Array.from(e.target.files);

      // Check if adding new files would exceed the limit
      if (uploadedFiles.length + newFiles.length > MAX_PHOTOS) {
        alert(
          `You can only upload up to ${MAX_PHOTOS} photos. Please remove some photos first.`,
        );
        return;
      }

      // Add new files to the array
      uploadedFiles = [...uploadedFiles, ...newFiles];

      // Refresh the preview
      updatePhotoPreview();

      // Clear the input so the same file can be selected again if removed
      fileUpload.value = "";
    });
  }

  // Update photo preview grid
  function updatePhotoPreview() {
    if (!photoPreview) return;

    // Clear preview grid
    photoPreview.innerHTML = "";

    if (uploadedFiles.length === 0) return;

    // Create preview for each file
    uploadedFiles.forEach((file, index) => {
      if (file.type.startsWith("image/")) {
        const reader = new FileReader();

        reader.onload = function (e) {
          const previewItem = document.createElement("div");
          previewItem.className = "preview-item";
          previewItem.dataset.index = index;

          // Create image element
          const img = document.createElement("img");
          img.src = e.target.result;
          img.className = "preview-image";
          img.alt = `Photo ${index + 1}`;

          // Add click to zoom
          previewItem.addEventListener("click", function (e) {
            // Don't open modal if clicking the remove button
            if (!e.target.classList.contains("remove-photo")) {
              openModal(index);
            }
          });

          // Create remove button
          const removeBtn = document.createElement("button");
          removeBtn.className = "remove-photo";
          removeBtn.innerHTML = "×";
          removeBtn.type = "button";

          // Add remove functionality
          removeBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            removePhoto(index);
          });

          previewItem.appendChild(img);
          previewItem.appendChild(removeBtn);
          photoPreview.appendChild(previewItem);
        };

        reader.readAsDataURL(file);
      }
    });

    // Add photo stats if there are photos
    if (uploadedFiles.length > 0) {
      const statsDiv = document.createElement("div");
      statsDiv.className = "photo-stats";
      statsDiv.innerHTML = `
                <span class="photo-count-text">
                    <i class="fas fa-camera"></i> ${uploadedFiles.length}/${MAX_PHOTOS}
                </span>
                <button type="button" class="clear-all-btn" id="clearAllPhotos">
                    <i class="fas fa-trash-alt"></i> Clear all
                </button>
            `;
      photoPreview.appendChild(statsDiv);

      // Add clear all functionality
      document
        .getElementById("clearAllPhotos")
        .addEventListener("click", function () {
          if (uploadedFiles.length > 0) {
            if (confirm("Remove all photos?")) {
              uploadedFiles = [];
              updatePhotoPreview();
            }
          }
        });
    }
  }

  // Remove a single photo
  function removePhoto(index) {
    uploadedFiles.splice(index, 1);
    updatePhotoPreview();
  }

  //  IMAGE MODAL (ZOOM)
  function openModal(index) {
    if (!imageModal || !modalImage || !modalCounter) return;

    currentImageIndex = index;
    const file = uploadedFiles[index];

    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        modalImage.src = e.target.result;
        modalCounter.textContent = `${index + 1} / ${uploadedFiles.length}`;
        imageModal.classList.add("active");
        document.body.style.overflow = "hidden"; // Prevent scrolling
      };
      reader.readAsDataURL(file);
    }
  }

  function closeModal() {
    if (!imageModal) return;
    imageModal.classList.remove("active");
    document.body.style.overflow = "";
  }

  // Close modal when clicking outside the image
  if (imageModal) {
    imageModal.addEventListener("click", function (e) {
      if (e.target === imageModal) {
        closeModal();
      }
    });
  }

  // Keyboard: Escape to close modal
  document.addEventListener("keydown", function (e) {
    if (
      e.key === "Escape" &&
      imageModal &&
      imageModal.classList.contains("active")
    ) {
      closeModal();
    }
  });

  //  DRAG AND DROP
  if (uploadArea) {
    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      uploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    ["dragenter", "dragover"].forEach((eventName) => {
      uploadArea.addEventListener(eventName, highlight, false);
    });

    ["dragleave", "drop"].forEach((eventName) => {
      uploadArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
      uploadArea.classList.add("dragover");
    }

    function unhighlight() {
      uploadArea.classList.remove("dragover");
    }

    uploadArea.addEventListener("drop", handleDrop, false);

    function handleDrop(e) {
      const dt = e.dataTransfer;
      const droppedFiles = Array.from(dt.files).filter((file) =>
        file.type.startsWith("image/"),
      );

      if (uploadedFiles.length + droppedFiles.length > MAX_PHOTOS) {
        alert(
          `You can only upload up to ${MAX_PHOTOS} photos. Please remove some photos first.`,
        );
        return;
      }

      uploadedFiles = [...uploadedFiles, ...droppedFiles];
      updatePhotoPreview();
    }

    // Click to upload
  }

  //  GPS & MAP FUNCTIONALITY
  if (gpsBtn) {
    gpsBtn.addEventListener("click", function () {
      if (!gpsActive) {
        // Request geolocation
        if (navigator.geolocation) {
          // Show map container
          locationMap.style.display = "block";

          // Update button state
          gpsBtn.classList.add("active");

          // Update hint
          locationHint.innerHTML =
            '<i class="fas fa-info-circle"></i><span>Fetching your location...</span>';

          navigator.geolocation.getCurrentPosition(
            function (position) {
              const lat = position.coords.latitude;
              const lng = position.coords.longitude;

              // Set coordinates in location input
              if (locationInput) {
                locationInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
              }

              // Set hidden fields
              document.getElementById("latitude").value = lat.toFixed(6);
              document.getElementById("longitude").value = lng.toFixed(6);

              // Initialize or update map
              initMap(lat, lng);

              // Update hint
              locationHint.innerHTML =
                '<i class="fas fa-info-circle"></i><span>GPS location detected! You can adjust the marker if needed.</span>';

              gpsActive = true;
            },
            function (error) {
              let errorMsg = "Unable to get your location.";
              switch (error.code) {
                case error.PERMISSION_DENIED:
                  errorMsg = "Location permission denied. Please enable GPS.";
                  break;
                case error.POSITION_UNAVAILABLE:
                  errorMsg = "Location information unavailable.";
                  break;
                case error.TIMEOUT:
                  errorMsg = "Location request timed out.";
                  break;
              }
              alert(errorMsg);

              // Reset
              locationMap.style.display = "none";
              gpsBtn.classList.remove("active");
              locationHint.innerHTML =
                '<i class="fas fa-info-circle"></i><span>Enter a descriptive location or use GPS for coordinates</span>';
            },
          );
        } else {
          alert("Geolocation is not supported by your browser.");
        }
      } else {
        // Toggle off GPS
        locationMap.style.display = "none";
        gpsBtn.classList.remove("active");
        locationHint.innerHTML =
          '<i class="fas fa-info-circle"></i><span>Enter a descriptive location or use GPS for coordinates</span>';

        // Clear map
        if (map) {
          map.remove();
          map = null;
          mapInitialized = false;
        }

        gpsActive = false;
      }
    });
  }

  // Initialize Leaflet map
  function initMap(lat, lng) {
    if (!locationMap) return;

    // If map already exists, just update view and marker
    if (mapInitialized && map) {
      map.setView([lat, lng], 15);
      if (mapMarker) {
        mapMarker.setLatLng([lat, lng]);
      } else {
        mapMarker = L.marker([lat, lng], { draggable: true })
          .addTo(map)
          .bindPopup("Your location")
          .openPopup();
      }
    } else {
      // Create new map
      map = L.map("locationMap").setView([lat, lng], 15);

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors",
      }).addTo(map);

      mapMarker = L.marker([lat, lng], { draggable: true })
        .addTo(map)
        .bindPopup("Your location")
        .openPopup();

      // Update coordinates when marker is dragged
      mapMarker.on("dragend", function (e) {
        const newPos = e.target.getLatLng();
        const newLat = newPos.lat.toFixed(6);
        const newLng = newPos.lng.toFixed(6);
        if (locationInput) {
          locationInput.value = `${newLat}, ${newLng}`;
          document.getElementById("latitude").value = newLat;
          document.getElementById("longitude").value = newLng;
        }
      });

      mapInitialized = true;
    }
  }

  // Handle manual coordinate entry
  if (locationInput) {
    locationInput.addEventListener("change", function () {
      const val = this.value.trim();
      const coordPattern = /^-?\d+\.?\d*,\s*-?\d+\.?\d*$/;
      if (coordPattern.test(val)) {
        const parts = val.split(",").map(Number);
        if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
          document.getElementById("latitude").value = parts[0].toFixed(6);
          document.getElementById("longitude").value = parts[1].toFixed(6);
          // Optionally update map if GPS is active
          if (gpsActive && map) {
            map.setView([parts[0], parts[1]], 15);
            if (mapMarker) {
              mapMarker.setLatLng([parts[0], parts[1]]);
            }
          }
        }
      } else {
        // If not coordinates, clear hidden fields
        document.getElementById("latitude").value = "";
        document.getElementById("longitude").value = "";
      }
    });
  }

  //  FORM SUBMISSION
  if (reportForm) {
    reportForm.addEventListener("submit", function (e) {
      e.preventDefault();

      // Get form values
      const issueType = document.querySelector(".form-select");
      const description = document.getElementById("report-description");
      const location = document.querySelector(".location-search");
      const email = document.querySelector('input[type="email"]');
      const anonymous = document.getElementById("anonymous");
      const latitude = document.getElementById("latitude");
      const longitude = document.getElementById("longitude");

      // Validation
      if (!issueType || !issueType.value) {
        alert("Please select an issue type.");
        return;
      }
      if (!description || !description.value.trim()) {
        alert("Please describe the issue.");
        return;
      }
      if (!location || !location.value.trim()) {
        alert("Please enter a location or use GPS.");
        return;
      }

      // Build FormData object for file upload
      const formData = new FormData();
      formData.append("issue_type", issueType.value);
      formData.append("description", description.value.trim());
      formData.append("location", location.value.trim());
      formData.append("email", email ? email.value.trim() : "");
      formData.append(
        "anonymous",
        anonymous ? (anonymous.checked ? "1" : "0") : "0",
      );
      formData.append("latitude", latitude ? latitude.value : "");
      formData.append("longitude", longitude ? longitude.value : "");

      // Append photos (the uploadedFiles array contains File objects)
      if (uploadedFiles.length > 0) {
        for (let i = 0; i < uploadedFiles.length; i++) {
          formData.append("photos[]", uploadedFiles[i]);
        }
      }

      // Send to server
      fetch("../submit_report.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            alert(data.message);
            // Close the floating window after a short delay
            if (
              window.parent &&
              typeof window.parent.hideFloating === "function"
            ) {
              setTimeout(() => {
                window.parent.hideFloating();
              }, 1500);
            }
            // Reset form and clear uploaded files
            reportForm.reset();
            uploadedFiles = [];
            updatePhotoPreview();
            if (map) {
              map.remove();
              map = null;
              mapInitialized = false;
              locationMap.style.display = "none";
              gpsActive = false;
              gpsBtn.classList.remove("active");
            }
            // Clear hidden coordinates
            document.getElementById("latitude").value = "";
            document.getElementById("longitude").value = "";
          } else {
            alert(data.error || "An error occurred. Please try again.");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("An error occurred. Please try again.");
        });
    });
  }
});


// Fill email field if not anonymous function

document.addEventListener("DOMContentLoaded", function () {
  const anonymousCheckbox = document.getElementById("anonymous");
  const emailField = document.getElementById("reportEmail");

  if (anonymousCheckbox && emailField) {
    // Store the original email value
    emailField.addEventListener("focus", function () {
      if (this.value === "" && typeof userEmail !== "undefined" && userEmail) {
        this.value = userEmail;
      }
    });

    // Clear email when anonymous is checked
        anonymousCheckbox.addEventListener('change', function() {
            if (this.checked) {
                emailField.value = '';
            }
        });
    }
});
