(() => {
  "use strict";
  // Send a small, trusted message back to the parent map page.
  const send = (type) => parent.postMessage({ type }, window.location.origin);
  const escapeHtml = (value) => String(value ?? "").replace(/[&<>'"]/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#039;", '"': "&quot;" })[character]);

  // Fill the review fields when the map page sends the current drawing draft.
  window.addEventListener("message", (event) => {
    if (event.origin !== window.location.origin) return;
    if (event.data?.type === "mapv2:review-create-failed") {
      const confirmButton = document.getElementById("confirmCreate");
      confirmButton.disabled = false;
      confirmButton.textContent = "Create compartment";
      alert(event.data.message || "The compartment could not be created.");
      return;
    }
    if (event.data?.type !== "mapv2:review-compartment-data") return;
    const review = event.data.payload || {};
    document.getElementById("reviewName").textContent = review.name || "—";
    document.getElementById("reviewCorners").textContent = String(review.corners || 0);
    document.getElementById("reviewArea").textContent = `${Number(review.hectares || 0).toFixed(4)} ha`;
    document.getElementById("reviewLocation").textContent = review.location || "No Morong barangay boundary matched yet";
    document.getElementById("reviewSpecies").innerHTML = (review.speciesMix || []).map((species) => `<article><div><span>${escapeHtml(species.name)}</span><small>${escapeHtml(species.scientificName)}</small></div><b>×${Number(species.quantity) || 0}</b></article>`).join("") || "<p>No species selected.</p>";
  });

  // Keep the three review actions together for easy debugging.
  document.getElementById("resumeDrawingFooter").addEventListener("click", () => send("mapv2:resume-compartment-drawing"));
  document.getElementById("cancelDrawing").addEventListener("click", () => send("mapv2:cancel-compartment-drawing"));
  document.getElementById("confirmCreate").addEventListener("click", (event) => {
    event.currentTarget.disabled = true;
    event.currentTarget.textContent = "Creating…";
    send("mapv2:confirm-compartment-create");
  });
})();
