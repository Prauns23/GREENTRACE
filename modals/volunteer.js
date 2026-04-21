// Heart icon animation on form interaction
function initHeartAnimation() {
  const heartIcon = document.querySelector(".icon-column i");
  const formInputs = document.querySelectorAll("input, select, textarea");

  let isAnimated = false;

  if (!heartIcon || formInputs.length === 0) {
    console.warn("Heart icon or form inputs not found");
    return;
  }

  formInputs.forEach((input) => {
    input.addEventListener("focus", function () {
      if (!isAnimated && heartIcon) {
        if (heartIcon.classList.contains("fa-regular")) {
          heartIcon.classList.remove("fa-regular");
          heartIcon.classList.add("fa-solid");
        }
        heartIcon.classList.add("pulse");
        setTimeout(() => {
          heartIcon.classList.remove("pulse");
        }, 600);
        isAnimated = true;
      }
    });
  });
}

// Initialize heart animation on DOM ready
document.addEventListener("DOMContentLoaded", function () {
  initHeartAnimation();
  setupAgeValidation();
});

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initHeartAnimation);
} else {
  initHeartAnimation();
}

// Age validation setup
function setupAgeValidation() {
  const dobInput = document.querySelector('input[name="date_of_birth"]');
  if (!dobInput) return;

  const today = new Date();
  const maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
  const minDate = new Date(today.getFullYear() - 65, today.getMonth(), today.getDate());

  const formatDate = (date) => date.toISOString().split('T')[0];
  dobInput.setAttribute('max', formatDate(maxDate));
  dobInput.setAttribute('min', formatDate(minDate));

  const hint = document.createElement('small');
  hint.style.cssText = 'color: #666; font-size: 12px; display: block; margin-top: 4px;';
  hint.textContent = 'Must be between 18 and 65 years old.';
  dobInput.parentNode.appendChild(hint);
}

function calculateAge(birthDateString) {
  const birthDate = new Date(birthDateString);
  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  return age;
}

// --- File upload handling ---
const uploadArea = document.getElementById("uploadArea");
const fileInput = document.getElementById("fileUpload");
const previewContainer = document.getElementById("photoPreview");
let selectedFiles = [];
let currentImageIndex = 0;
let currentImages = [];

function renderPreviews() {
  previewContainer.innerHTML = "";
  currentImages = [];

  if (selectedFiles.length === 0) return;

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
    const clearBtn = statsDiv.querySelector(".clear-all-btn");
    clearBtn.addEventListener("click", () => {
      selectedFiles = [];
      renderPreviews();
      updateFileInput();
      fileInput.value = "";
    });
  }
}

function updateFileInput() {
  const dataTransfer = new DataTransfer();
  selectedFiles.forEach((file) => dataTransfer.items.add(file));
  fileInput.files = dataTransfer.files;
}

function handleFiles(files) {
  const newFiles = Array.from(files);
  if (selectedFiles.length + newFiles.length > 5) {
    if (typeof parent.showToast === "function") {
      parent.showToast("You can only upload up to 5 files.", 4000, "error");
    } else {
      alert("You can only upload up to 5 files.");
    }
    return;
  }
  const validFiles = newFiles.filter((file) => {
    const validType = file.type === "image/jpeg" || file.type === "image/png" || file.type === "application/pdf";
    const validSize = file.size <= 5 * 1024 * 1024;
    if (!validType) {
      if (typeof parent.showToast === "function") {
        parent.showToast(`Invalid type: ${file.name}. Only JPG, PNG, PDF allowed.`, 4000, "error");
      } else {
        alert(`Invalid type: ${file.name}. Only JPG, PNG, PDF allowed.`);
      }
    }
    if (!validSize) {
      if (typeof parent.showToast === "function") {
        parent.showToast(`File too large: ${file.name}. Max 5MB.`, 4000, "error");
      } else {
        alert(`File too large: ${file.name}. Max 5MB.`);
      }
    }
    return validType && validSize;
  });
  selectedFiles = [...selectedFiles, ...validFiles];
  renderPreviews();
  updateFileInput();
}

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

fileInput.addEventListener("change", (e) => {
  handleFiles(e.target.files);
});

previewContainer.addEventListener("click", (e) => {
  const removeBtn = e.target.closest(".remove-photo");
  if (removeBtn) {
    const index = removeBtn.getAttribute("data-index");
    if (index !== null) removeFile(parseInt(index));
  }
});

function removeFile(index) {
  selectedFiles.splice(index, 1);
  renderPreviews();
  updateFileInput();
}

// Image modal
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

previewContainer.addEventListener("click", (e) => {
  const previewItem = e.target.closest(".preview-item");
  if (previewItem && !e.target.closest(".remove-photo")) {
    const index = previewItem.getAttribute("data-index");
    if (index !== null) openImageModal(parseInt(index));
  }
});

if (typeof parent.showToast === "function") {
  parent.showToast("Your error message", 4000, "error");
  console.log("Toast called with type: error");
}

// --- Form submission with toast error handling ---
const form = document.getElementById("applicationForm");
form.addEventListener("submit", async function (e) {
  e.preventDefault();

  // Age validation with parent toast
  const dobInput = document.querySelector('input[name="date_of_birth"]');
  const dobValue = dobInput.value;
  if (!dobValue) {
    if (typeof parent.showToast === "function") {
      parent.showToast("Please enter your date of birth.", 4000, "error");
    } else {
      alert("Please enter your date of birth.");
    }
    return;
  }
  const age = calculateAge(dobValue);
  if (age < 18) {
    if (typeof parent.showToast === "function") {
      parent.showToast("You must be at least 18 years old to volunteer.", 4000, "error");
    } else {
      alert("You must be at least 18 years old to volunteer.");
    }
    return;
  }
  if (age > 65) {
    if (typeof parent.showToast === "function") {
      parent.showToast("Maximum age for volunteering is 65 years old.", 4000, "error");
    } else {
      alert("Maximum age for volunteering is 65 years old.");
    }
    return;
  }

  if (selectedFiles.length === 0) {
    if (typeof parent.showToast === "function") {
      parent.showToast("Please upload at least one verification file.", 4000, "error");
    } else {
      alert("Please upload at least one verification file.");
    }
    return;
  }

  const submitBtn = this.querySelector(".submit-btn");
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

  const formData = new FormData(this);
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
      const message = encodeURIComponent("Application submitted! Awaiting admin approval.");
      parent.location.href = "../activities.php?toast=" + message + "&type=success";
    } else {
      if (typeof parent.showToast === "function") {
        parent.showToast(data.error || "Submission failed.", 5000, "error");
      } else {
        alert(data.error || "Submission failed.");
      }
      submitBtn.disabled = false;
      submitBtn.innerHTML = "Submit Application";
    }
  } catch (error) {
    console.error(error);
    if (typeof parent.showToast === "function") {
      parent.showToast("An error occurred. Please try again.", 5000, "error");
    } else {
      alert("An error occurred. Please try again.");
    }
    submitBtn.disabled = false;
    submitBtn.innerHTML = "Submit Application";
  }
});