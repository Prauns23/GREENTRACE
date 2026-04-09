// Open full-size image modal
function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modal.style.display = 'block';
    modalImg.src = imageSrc;
}

// Close image modal
function closeImageModal() {
    const modal = document.getElementById('imageModal');
    modal.style.display = 'none';
}

// Save status change
document.addEventListener('DOMContentLoaded', function() {
    const saveBtn = document.getElementById('saveStatusBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            const statusSelect = document.getElementById('status');
            const reportId = getReportIdFromUrl();
            const newStatus = statusSelect.value;

            // Disable button to prevent double submission
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('../actions/update_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'report_id=' + reportId + '&status=' + newStatus
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success toast if parent has function
                    if (typeof parent.showToast === 'function') {
                        parent.showToast('Status updated successfully');
                    } else {
                        alert('Status updated successfully');
                    }
                    // Reload the parent page to refresh map markers and activity list
                    parent.location.reload();
                } else {
                    alert(data.error || 'Failed to update status');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = 'Save Changes';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                saveBtn.disabled = false;
                saveBtn.innerHTML = 'Save Changes';
            });
        });
    }
});

// Helper: extract report ID from URL query string
function getReportIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('id');
}