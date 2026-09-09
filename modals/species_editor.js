// species_editor.js
(function () {
  "use strict";

  const form = document.getElementById("species-form");
  const fileInput = document.getElementById("species-image");
  const preview = document.getElementById("species-image-preview");
  const uploadIcon = document.getElementById("upload-icon");
  const uploadArea = document.getElementById("upload-area");
  const errorEl = document.getElementById("form-error");
  const importanceInput = document.getElementById("species-importance");

  if (!form) return;

  function withoutBullets(value) {
    return value
      .split(/\r?\n/)
      .map((line) => line.replace(/^\s*[•*-]\s?/, "").trim())
      .join("\n")
      .trim();
  }

  function withBullets(value) {
    return withoutBullets(value)
      .split("\n")
      .map((line) => (line ? `• ${line}` : ""))
      .join("\n");
  }

  if (importanceInput) {
    if (importanceInput.value.trim() !== "") {
      importanceInput.value = withBullets(importanceInput.value);
    }

    importanceInput.addEventListener("focus", function () {
      if (this.value.trim() === "") {
        this.value = "• ";
        this.setSelectionRange(this.value.length, this.value.length);
      }
    });

    importanceInput.addEventListener("blur", function () {
      if (withoutBullets(this.value) === "") {
        this.value = "";
      }
    });

    importanceInput.addEventListener("keydown", function (event) {
      if (event.key !== "Enter") return;

      const start = this.selectionStart;
      const lineStart = this.value.lastIndexOf("\n", start - 1) + 1;
      const lineEnd = this.value.indexOf("\n", start);
      const currentLine = this.value.slice(
        lineStart,
        lineEnd === -1 ? this.value.length : lineEnd,
      );

      if (currentLine.trim() === "•") {
        event.preventDefault();
        this.setRangeText("", lineStart, lineEnd === -1 ? this.value.length : lineEnd + 1, "start");
        return;
      }

      event.preventDefault();
      this.setRangeText("\n• ", start, this.selectionEnd, "end");
    });
  }

  // ----- File selection & preview -----
  function selectFile(file) {
    errorEl.textContent = "";
    if (!file) {
      preview.src = "";
      preview.style.display = "none";
      uploadIcon.style.display = "block";
      return;
    }
    if (
      !["image/jpeg", "image/png"].includes(file.type) ||
      file.size > 10 * 1024 * 1024
    ) {
      fileInput.value = "";
      errorEl.textContent = "Choose a PNG or JPG image up to 10 MB.";
      return;
    }
    const reader = new FileReader();
    reader.onload = function (e) {
      preview.src = e.target.result;
      preview.style.display = "block";
      uploadIcon.style.display = "none";
    };
    reader.readAsDataURL(file);
  }

  fileInput.addEventListener("change", function () {
    if (this.files.length) selectFile(this.files[0]);
  });

  // Drag and drop
  uploadArea.addEventListener("dragover", function (e) {
    e.preventDefault();
    this.classList.add("dragover");
  });
  uploadArea.addEventListener("dragleave", function (e) {
    e.preventDefault();
    this.classList.remove("dragover");
  });
  uploadArea.addEventListener("drop", function (e) {
    e.preventDefault();
    this.classList.remove("dragover");
    if (e.dataTransfer.files.length !== 1) {
      errorEl.textContent = "Please drop exactly one image.";
      return;
    }
    fileInput.files = e.dataTransfer.files;
    selectFile(fileInput.files[0]);
  });

  // Click to open file picker
  uploadArea.addEventListener("click", function () {
    fileInput.click();
  });

  // ----- Form submission -----
  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    if (!this.reportValidity()) return;

    const submitBtn = this.querySelector(".btn-primary");
    submitBtn.disabled = true;
    errorEl.textContent = "";

    try {
      const data = new FormData(this);
      if (importanceInput) {
        data.set("importance", withoutBullets(importanceInput.value));
      }
      const response = await fetch(this.action, {
        method: "POST",
        body: data,
        credentials: "same-origin",
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.error || "Unable to save the species.");
      }
      // Notify parent (main page)
      if (typeof parent.showToast === "function") {
        parent.showToast(result.message, 3000, "success");
      } else {
        alert(result.message);
      }
      parent.hideFloating();
      parent.location.reload();
    } catch (err) {
      errorEl.textContent =
        err.message || "Connection failed. Please try again.";
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
