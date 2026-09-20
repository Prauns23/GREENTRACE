(() => {
  "use strict";
  let photo = null;
  window.addEventListener("message", (event) => {
    if (
      event.origin !== window.location.origin ||
      event.data?.type !== "mapv2:edit-photo-data"
    )
      return;
    photo = event.data.payload || null;
    document.getElementById("editPhotoName").value = photo?.name || "";
    document.getElementById("editPhotoCategory").value = String(
      photo?.category || "planned",
    )
      .toLowerCase()
      .replace(/\s+/g, "_");
  });
  document
    .getElementById("cancelEditPhoto")
    .addEventListener("click", () => parent.hideFloating());
  document
    .getElementById("editPhotoForm")
    .addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!photo) return;
      const saveButton = event.submitter;
      saveButton.disabled = true;
      saveButton.textContent = "Saving…";
      try {
        const payload = new FormData();
        payload.append("id", photo.id);
        payload.append("name", document.getElementById("editPhotoName").value.trim());
        payload.append("category", document.getElementById("editPhotoCategory").value);
        payload.append("csrf_token", document.querySelector('meta[name="csrf-token"]').content);
        const response = await fetch("../actions/mapv2/update_compartment_photo.php", { method: "POST", body: payload, headers: { Accept: "application/json" } });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || "Photo details could not be saved.");
        parent.postMessage({ type: "mapv2:photo-updated", payload: { ...photo, ...result.photo } }, window.location.origin);
        parent.hideFloating();
      } catch (error) {
        alert(error.message || "Photo details could not be saved.");
        saveButton.disabled = false;
        saveButton.textContent = "Save";
      }
    });
})();
