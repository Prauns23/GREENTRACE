// application_activity.js

// Toast notification function
function showToast(message, type = 'success', duration = 5000) {
    const toast = document.getElementById('toast'); 
    
    // Reset classes
    toast.className = 'toast';
    if (type === 'error') {
        toast.classList.add('error');
    }
    
    // Set message
    toast.textContent = message;
    
    // Show toast
    toast.classList.remove('hidden');
    
    // Hide after duration
    setTimeout(() => {
        toast.classList.add('hidden');
    }, duration);
}

// Search filter
const searchInput = document.getElementById('searchInput');
const tableRows = document.querySelectorAll('.app-table tbody tr');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        tableRows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
}

// Sorting redirect
const sortSelect = document.getElementById('sortSelect');
if (sortSelect) {
    sortSelect.addEventListener('change', function() {
        window.location.href = 'application_activity.php?sort=' + this.value;
    });
}

// Bulk actions (archive / restore)
const selectAll = document.getElementById('selectAll');
const rowCheckboxes = document.querySelectorAll('.rowCheckbox');
const bulkArchiveBtn = document.getElementById('bulkArchiveBtn');
const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
const selectedIdsInput = document.getElementById('selectedIdsInput');
const bulkActionForm = document.getElementById('bulkActionForm');
const bulkActionType = document.getElementById('bulkActionType');

function updateBulkButtons() {
    const anyChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
    if (bulkArchiveBtn) bulkArchiveBtn.disabled = !anyChecked;
    if (bulkRestoreBtn) bulkRestoreBtn.disabled = !anyChecked;
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
        updateBulkButtons();
    });
}

rowCheckboxes.forEach(cb => {
    cb.addEventListener('change', function() {
        if (!this.checked && selectAll) selectAll.checked = false;
        updateBulkButtons();
    });
});

function submitBulkAction(action) {
    const selected = Array.from(rowCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
    if (selected.length === 0) {
        showToast('Please select at least one application.', 'info');
        return;
    }
    if (!confirm(`Are you sure you want to ${action} the selected application(s)?`)) return;
    bulkActionType.value = action;
    selectedIdsInput.value = JSON.stringify(selected);
    bulkActionForm.submit();
}

if (bulkArchiveBtn) {
    bulkArchiveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        submitBulkAction('archive');
    });
}
if (bulkRestoreBtn) {
    bulkRestoreBtn.addEventListener('click', (e) => {
        e.preventDefault();
        submitBulkAction('restore');
    });
}

// Image modal functions
function openImageModal(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modal.style.display = 'flex';
    modalImg.src = src;
}
function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeImageModal();
});