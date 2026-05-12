// Search, Filter, and Sort

const searchInput = document.getElementById("searchInput");
const statusFilter = document.getElementById("statusFilter");
const sortSelect = document.getElementById("sortSelect");

function reloadPage() {
  const search = searchInput ? searchInput.value : "";
  const status = statusFilter ? statusFilter.value : "active";
  const sort = sortSelect ? sortSelect.value : "date_asc";
  window.location.href = `activities_manage.php?searc=${encodeURIComponent(search)}&status=${status}&sort=${sort}`;
}

if (searchInput) {
  let debounceTimer;
  searchInput.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(reloadPage, 400);
  });
}

if (statusFilter) statusFilter.addEventListener("change", reloadPage);
if (sortSelect) sortSelect.addEventListener("change", reloadPage);

// Bulk Actions (Select All + Checkboxes)

const selectAll = document.getElementById("selectAll");
const rowCheckboxes = document.querySelectorAll(".rowCheckbox");
const bulkArchiveBtn = document.getElementById("bulkArchiveBtn");
const bulkRestoreBtn = document.getElementById("bulkRestoreBtn");
const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");

function updateBulkButtons() {
  const anyChecked = Array.from(rowCheckboxes).some((cb) => cb.checked);
  if (bulkArchiveBtn) bulkArchiveBtn.disabled = !anyChecked;
  if (bulkRestoreBtn) bulkRestoreBtn.disabled = !anyChecked;
  if (bulkDeleteBtn) bulkDeleteBtn.disabled = !anyChecked;
}

if (selectAll) {
  selectAll.addEventListener("change", () => {
    rowCheckboxes.forEach((cb) => (cb.checked = selectAll.checked));
    updateBulkButtons;
  });
}
rowCheckboxes.forEach((cb) => {
  cb.addEventListener("change", () => {
    if (!cb.checked && selectAll) selectAll.checked = false;
    updateBulkButtons();
  });
});

// Bulk Action Triggers

if (bulkArchiveBtn) {
  bulkArchiveBtn.addEventListener("click", (e) => {
    e.preventDefault();
    const selected = Array.from(rowCheckboxes)
      .filter((cb) => cb.checked)
      .map((cb) => cb.value);
    if (selected.length === 0) return;
    if (window.parent.showConfirmArchive) {
        window.parent.showConfirmArchive(selected); 
    } else {
        alert("Archive modal not available");
    }
  });
}

if (bulkRestoreBtn) {
    bulkRestoreBtn.addEventListener("click", (e) => {
        e.preventDefault();
        const selected = Array.from(rowCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        if (selected.length === 0) return;
        if (window.parent.showConfirmRestore) {
            window.parent.showConfirmRestore(selected);
        } else {
            alert("Restore modal not available");
        }
    });
}

if (bulkDeleteBtn) {
    bulkDeleteBtn.addEventListener("click", (e) => {
        e.preventDefault();
        const selected = Array.from(rowCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        if (selected.length === 0) return;
        if (window.parent.showConfirmDelete) {
            window.parent.showConfirmDelete(selected);
        } else {
            alert("Delete modal not available");
        }
    });
}

// Single Actions

// Edit button (placeholder)
document.querySelectorAll(".edit-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const id = btn.dataset.id;
        alert("Edit feature coming soon.");
    });
});

// Single archive
document.querySelectorAll(".archive-single-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const id = btn.dataset.id;
        if (window.parent.showConfirmArchive) {
            window.parent.showConfirmArchive(id);  // pass single ID (number)
        } else {
            alert("Archive modal not available");
        }
    });
});

// Single restore
document.querySelectorAll(".restore-single-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const id = btn.dataset.id;
        if (window.parent.showConfirmRestore) {
            window.parent.showConfirmRestore(id);
        } else {
            alert("Restore modal not available");
        }
    });
});

// Single delete
document.querySelectorAll(".delete-single-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const id = btn.dataset.id;
        if (window.parent.showConfirmDelete) {
            window.parent.showConfirmDelete(id);
        } else {
            alert("Delete modal not available");
        }
    });
});