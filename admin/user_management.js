// Wait for DOM to load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded – initializing user management JS');

    // --- Search and Sort ---
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');

    function reloadWithParams() {
        const search = searchInput ? searchInput.value : '';
        const sort = sortSelect ? sortSelect.value : 'name_asc';
        window.location.href = `user_management.php?search=${encodeURIComponent(search)}&sort=${sort}`;
    }

    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(reloadWithParams, 400);
        });
        console.log('Search input attached');
    }
    if (sortSelect) {
        sortSelect.addEventListener('change', reloadWithParams);
        console.log('Sort select attached');
    }

    // --- Bulk Actions ---
    const selectAll = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.rowCheckbox');
    const bulkArchiveBtn = document.getElementById('bulkArchiveBtn');
    const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
    const selectedIdsInput = document.getElementById('selectedIdsInput');
    const bulkActionForm = document.getElementById('bulkActionForm');
    const bulkActionType = document.getElementById('bulkActionType');

    console.log('Elements found:', {
        selectAll: !!selectAll,
        rowCheckboxes: rowCheckboxes.length,
        bulkArchiveBtn: !!bulkArchiveBtn,
        bulkRestoreBtn: !!bulkRestoreBtn,
        bulkActionForm: !!bulkActionForm,
        bulkActionType: !!bulkActionType,
        selectedIdsInput: !!selectedIdsInput
    });

    function updateBulkButtons() {
        const anyChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
        console.log('Update bulk buttons – anyChecked:', anyChecked);
        if (bulkArchiveBtn) bulkArchiveBtn.disabled = !anyChecked;
        if (bulkRestoreBtn) bulkRestoreBtn.disabled = !anyChecked;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            console.log('Select all clicked, checked:', this.checked);
            rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkButtons();
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            console.log('Row checkbox changed, checked:', this.checked);
            if (!this.checked && selectAll) selectAll.checked = false;
            updateBulkButtons();
        });
    });

    // Initial button state
    updateBulkButtons();

    function submitBulkAction(action) {
        const selected = Array.from(rowCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
        console.log('Submit bulk action:', action, 'selected IDs:', selected);
        if (selected.length === 0) {
            showToast('Please select at least one user.', 4000, 'error');
            return;
        }
        if (!confirm(`Are you sure you want to ${action} the selected user(s)?`)) return;
        bulkActionType.value = action;
        selectedIdsInput.value = JSON.stringify(selected);
        console.log('Submitting form with action:', action, 'IDs:', selectedIdsInput.value);
        bulkActionForm.submit();
    }

    if (bulkArchiveBtn) {
        bulkArchiveBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Bulk archive button clicked');
            submitBulkAction('archive');
        });
    }
    if (bulkRestoreBtn) {
        bulkRestoreBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Bulk restore button clicked');
            submitBulkAction('restore');
        });
    }

    // --- Single Role Update ---
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', async function() {
            const userId = this.dataset.userId;
            const newRole = this.value;
            console.log('Role change:', userId, newRole);
            try {
                const response = await fetch('user_management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=update_role&user_id=${userId}&role=${newRole}`
                });
                const data = await response.json();
                if (data.success) {
                    showToast(data.message, 3000, 'success');
                } else {
                    showToast(data.error, 4000, 'error');
                    location.reload();
                }
            } catch (err) {
                console.error(err);
                showToast('Error updating role', 4000, 'error');
                location.reload();
            }
        });
    });

    // --- Single Archive ---
    document.querySelectorAll('.archive-single-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            if (!confirm('Archive this user? They will no longer be able to log in.')) return;
            console.log('Single archive user:', userId);
            try {
                const response = await fetch('user_management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=archive_user&user_id=${userId}`
                });
                const data = await response.json();
                if (data.success) {
                    showToast(data.message, 3000, 'success');
                    location.reload();
                } else {
                    showToast(data.error, 4000, 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Error archiving user', 4000, 'error');
            }
        });
    });

    // --- Single Restore ---
    document.querySelectorAll('.restore-single-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            if (!confirm('Restore this user? They will be able to log in again.')) return;
            console.log('Single restore user:', userId);
            try {
                const response = await fetch('user_management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=restore_user&user_id=${userId}`
                });
                const data = await response.json();
                if (data.success) {
                    showToast(data.message, 3000, 'success');
                    location.reload();
                } else {
                    showToast(data.error, 4000, 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Error restoring user', 4000, 'error');
            }
        });
    });

    // --- View and Edit Modals (keep your existing code here) ---
    // ... (I'll omit for brevity, but keep your existing modal code)
});

// Toast helper
function showToast(message, duration = 3000, type = 'success') {
    if (typeof window.parent !== 'undefined' && window.parent.showToast) {
        window.parent.showToast(message, duration, type);
    } else if (typeof showToast === 'function') {
        showToast(message, duration, type);
    } else {
        alert(message);
    }
}