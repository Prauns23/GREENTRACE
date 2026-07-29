document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const userItems = document.querySelectorAll('.user-item');

    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        userItems.forEach(item => {
            const name = item.querySelector('.user-name').textContent.toLowerCase();
            const barangay = item.querySelector('.user-barangay').textContent.toLowerCase();
            const matches = name.includes(term) || barangay.includes(term);
            item.style.display = matches ? '' : 'none';
        });
    });

    // Optional: Handle "Add" button – you can implement later
    document.getElementById('addRecipientsBtn').addEventListener('click', function() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one user.');
            return;
        }
        // Here you would create a DM conversation and redirect
        // For now, just close the modal
        parent.hideFloating();
    });
});