// species_confirm.js
(function () {
  "use strict";

  const confirmBtn = document.getElementById("confirmAction");
  const errorEl = document.getElementById("confirm-error");
  const id = parseInt(confirmBtn?.dataset?.id || "0");
  const action = confirmBtn?.dataset?.action || "archive";

  if (!confirmBtn || !id) return;

  confirmBtn.addEventListener("click", async function () {
    errorEl.textContent = "";
    this.disabled = true;

    try {
      const formData = new FormData();
      formData.append("id", id);
      formData.append("action", action);
      formData.append(
        "csrf_token",
        document.querySelector('meta[name="csrf-token"]')?.content || "",
      );

      const response = await fetch("../actions/manage_species.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.error || "Failed to update species.");
      }
      if (typeof parent.queueToast === "function") {
        parent.queueToast(result.message, "success");
      } else if (typeof parent.showToast === "function") {
        parent.showToast(result.message, 3000, "success");
      } else {
        alert(result.message);
      }
      parent.hideFloating();
      parent.location.reload();
    } catch (err) {
      errorEl.textContent = err.message || "Connection failed.";
      this.disabled = false;
    }
  });
})();
