// Drag & drop and preview functionality (mirrors report.js)
const uploadArea = document.getElementById("uploadArea");
const fileInput = document.getElementById("fileUpload");
const previewContainer = document.getElementById("photoPreview");
let selectedFiles = [];
let currentImageIndex = 0;
let currentImages = [];

// Format file size
function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}

// Render previews as a grid (same as report.php)
function renderPreviews() {
  previewContainer.innerHTML = "";
  currentImages = [];

  if (selectedFiles.length === 0) {
    // Optionally show empty state
    return;
  }

  selectedFiles.forEach((file, index) => {
    if (!file.type.startsWith("image/")) return;
    const reader = new FileReader();
    reader.onload = function (e) {
      const previewDiv = document.createElement("div");
      previewDiv.className = "preview-item";
      previewDiv.setAttribute("data-index", index);
      previewDiv.innerHTML = `
                <img src="${e.target.result}" class="preview-image">
                <button type="button" class="remove-photo" data-index="${index}">&times;</button>
            `;
      previewContainer.appendChild(previewDiv);
      currentImages.push(e.target.result);
    };
    reader.readAsDataURL(file);
  });

  // Add photo stats and clear all button
  if (selectedFiles.length > 0) {
    const statsDiv = document.createElement("div");
    statsDiv.className = "photo-stats";
    statsDiv.innerHTML = `
            <div class="photo-count-text">
                <i class="fas fa-images"></i>
                <span>${selectedFiles.length} file(s) selected</span>
            </div>
            <button type="button" class="clear-all-btn">Clear all</button>
        `;
    previewContainer.appendChild(statsDiv);

    // Clear all button
    const clearBtn = statsDiv.querySelector(".clear-all-btn");
    clearBtn.addEventListener("click", () => {
      selectedFiles = [];
      renderPreviews();
      updateFileInput();
      fileInput.value = "";
    });
  }
}

// Update hidden file input with selectedFiles
function updateFileInput() {
  const dataTransfer = new DataTransfer();
  selectedFiles.forEach((file) => dataTransfer.items.add(file));
  fileInput.files = dataTransfer.files;
}

// Handle file selection
function handleFiles(files) {
  const newFiles = Array.from(files);
  if (selectedFiles.length + newFiles.length > 5) {
    alert("You can only upload up to 5 files.");
    return;
  }
  const validFiles = newFiles.filter((file) => {
    const validType =
      file.type === "image/jpeg" ||
      file.type === "image/png" ||
      file.type === "application/pdf";
    const validSize = file.size <= 5 * 1024 * 1024;
    if (!validType)
      alert(`Invalid type: ${file.name}. Only JPG, PNG, PDF allowed.`);
    if (!validSize) alert(`File too large: ${file.name}. Max 5MB.`);
    return validType && validSize;
  });
  selectedFiles = [...selectedFiles, ...validFiles];
  renderPreviews();
  updateFileInput();
}

// Remove a single file
function removeFile(index) {
  selectedFiles.splice(index, 1);
  renderPreviews();
  updateFileInput();
}

// Drag & drop events
if (uploadArea) {
  uploadArea.addEventListener("dragover", (e) => {
    e.preventDefault();
    uploadArea.classList.add("dragover");
  });
  uploadArea.addEventListener("dragleave", () => {
    uploadArea.classList.remove("dragover");
  });
  uploadArea.addEventListener("drop", (e) => {
    e.preventDefault();
    uploadArea.classList.remove("dragover");
    const files = e.dataTransfer.files;
    if (files.length) handleFiles(files);
  });
}

// File input change
fileInput.addEventListener("change", (e) => {
  handleFiles(e.target.files);
});

// Remove photo via event delegation
previewContainer.addEventListener("click", (e) => {
  const removeBtn = e.target.closest(".remove-photo");
  if (removeBtn) {
    const index = removeBtn.getAttribute("data-index");
    if (index !== null) removeFile(parseInt(index));
  }
});

// Image modal functionality
const imageModal = document.getElementById("imageModal");
const modalImage = document.querySelector(".modal-image");
const modalCounter = document.querySelector(".modal-counter");

function openImageModal(index) {
  if (currentImages.length === 0) return;
  currentImageIndex = index;
  modalImage.src = currentImages[currentImageIndex];
  modalCounter.textContent = `${currentImageIndex + 1} / ${currentImages.length}`;
  imageModal.classList.add("active");
}

function closeImageModal() {
  imageModal.classList.remove("active");
}

imageModal.addEventListener("click", (e) => {
  if (e.target === imageModal) closeImageModal();
});

// Preview click to open modal
previewContainer.addEventListener("click", (e) => {
  const previewItem = e.target.closest(".preview-item");
  if (previewItem && !e.target.closest(".remove-photo")) {
    const index = previewItem.getAttribute("data-index");
    if (index !== null) openImageModal(parseInt(index));
  }
});

// Form submission
const form = document.getElementById("applicationForm");
form.addEventListener("submit", async function (e) {
  e.preventDefault();
  if (selectedFiles.length === 0) {
    alert("Please upload at least one verification file.");
    return;
  }
  const submitBtn = this.querySelector(".submit-btn");
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

  const formData = new FormData(this);
  // Replace file input with selectedFiles
  formData.delete("verification_files[]");
  selectedFiles.forEach((file) => {
    formData.append("verification_files[]", file);
  });

  try {
    const response = await fetch("../actions/submit_application.php", {
      method: "POST",
      body: formData,
    });
    const data = await response.json();
    if (data.success) {
      if (typeof parent.showToast === "function") {
        parent.showToast("Application submitted! Awaiting admin approval.");
      }
      parent.hideFloating();
      parent.location.reload();
    } else {
      if (typeof parent.showToast === "function") {
        parent.showToast(data.error || "Submission failed.");
      } else {
        alert(data.error || "Submission failed.");
      }
      submitBtn.disabled = false;
      submitBtn.innerHTML = "Submit Application";
    }
  } catch (error) {
    console.error(error);
    alert("An error occurred. Please try again.");
    submitBtn.disabled = false;
    submitBtn.innerHTML = "Submit Application";
  }
});
