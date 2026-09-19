(() => {
  "use strict";
  const config = window.mapV2UploadPhotoConfig || { maxFiles: 5 };
  const input = document.getElementById("uploadPhotoInput");
  const dropzone = document.getElementById("uploadPhotoDropzone");
  const previews = document.getElementById("uploadPhotoPreviewList");
  const count = document.getElementById("uploadPhotoCount");
  const addButton = document.getElementById("addUploadPhotos");
  let files = [];

  // Keep the accepted image list small and explain invalid files without closing the modal.
  const acceptFiles = (newFiles) => {
    const accepted = [...newFiles].filter(
      (file) =>
        /^(image\/png|image\/jpeg)$/.test(file.type) &&
        file.size <= 10 * 1024 * 1024,
    );
    files = [...files, ...accepted].slice(0, config.maxFiles);
    renderPreviews();
  };
  const renderPreviews = () => {
    previews.innerHTML = "";
    files.forEach((file, index) => {
      const preview = document.createElement("article");
      preview.className = "photo-preview";
      const image = document.createElement("img");
      image.alt = file.name;
      image.src = URL.createObjectURL(file);
      image.onload = () => URL.revokeObjectURL(image.src);
      const remove = document.createElement("button");
      remove.type = "button";
      remove.setAttribute("aria-label", `Remove ${file.name}`);
      remove.innerHTML = '<i class="fa-solid fa-minus" aria-hidden="true"></i>';
      remove.addEventListener("click", () => {
        files.splice(index, 1);
        renderPreviews();
      });
      preview.append(image, remove);
      previews.append(preview);
    });
    count.innerHTML = `<i class="fa-regular fa-images" aria-hidden="true"></i> ${files.length} / ${config.maxFiles}`;
    addButton.disabled = files.length === 0;
    window.requestAnimationFrame(() => {
      const modal = document.querySelector(".photo-modal");
      const content = document.querySelector(".photo-modal__content");
      const height =
        modal.querySelector("header").offsetHeight +
        content.scrollHeight +
        modal.querySelector("footer").offsetHeight;
      parent.postMessage(
        { type: "mapv2:resize-upload-photo-modal", height },
        window.location.origin,
      );
    });
  };
  ["dragenter", "dragover"].forEach((name) =>
    dropzone.addEventListener(name, (event) => {
      event.preventDefault();
      dropzone.classList.add("is-dragging");
    }),
  );
  ["dragleave", "drop"].forEach((name) =>
    dropzone.addEventListener(name, (event) => {
      event.preventDefault();
      dropzone.classList.remove("is-dragging");
    }),
  );
  dropzone.addEventListener("drop", (event) =>
    acceptFiles(event.dataTransfer.files),
  );
  input.addEventListener("change", () => {
    acceptFiles(input.files);
    input.value = "";
  });
  document.getElementById("clearUploadPhotos").addEventListener("click", () => {
    files = [];
    renderPreviews();
  });
  document
    .getElementById("cancelUploadPhotos")
    .addEventListener("click", () => parent.hideFloating());
  document
    .getElementById("uploadPhotosForm")
    .addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!files.length) return;
      addButton.disabled = true;
      addButton.textContent = "Adding…";
      try {
        const payload = new FormData();
        payload.append("compartment_id", config.compartmentId);
        payload.append(
          "category",
          document.getElementById("uploadPhotoCategory").value,
        );
        payload.append(
          "csrf_token",
          document.querySelector('meta[name="csrf-token"]').content,
        );
        files.forEach((file) => payload.append("photos[]", file));
        const response = await fetch(
          "../actions/mapv2/upload_compartment_photos.php",
          {
            method: "POST",
            body: payload,
            headers: { Accept: "application/json" },
          },
        );
        const result = await response.json();
        if (!response.ok || !result.success)
          throw new Error(result.error || "Photos could not be uploaded.");
        parent.postMessage(
          {
            type: "mapv2:photos-uploaded",
            payload: {
              compartmentId: config.compartmentId,
              photos: result.photos || [],
            },
          },
          window.location.origin,
        );
        parent.hideFloating();
      } catch (error) {
        alert(error.message || "Photos could not be uploaded.");
        addButton.disabled = false;
        addButton.textContent = "Add";
      }
    });
  window.addEventListener("message", (event) => {
    if (
      event.origin === window.location.origin &&
      event.data?.type === "mapv2:prefill-photo-files"
    )
      acceptFiles(event.data.files || []);
  });
  renderPreviews();
})();
