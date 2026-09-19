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
    .addEventListener("submit", (event) => {
      event.preventDefault();
      if (!photo) return;
      parent.postMessage(
        {
          type: "mapv2:save-photo-edit-draft",
          payload: {
            ...photo,
            name: document.getElementById("editPhotoName").value.trim(),
            category: document.getElementById("editPhotoCategory").value,
          },
        },
        window.location.origin,
      );
      parent.hideFloating();
    });
})();
