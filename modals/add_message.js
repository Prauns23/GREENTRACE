document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const userItems = document.querySelectorAll('.user-item');

    // Search filter
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        userItems.forEach(item => {
            const name = item.querySelector('.user-name').textContent.toLowerCase();
            const barangay = item.querySelector('.user-barangay').textContent.toLowerCase();
            const matches = name.includes(term) || barangay.includes(term);
            item.style.display = matches ? '' : 'none';
        });
    });

    // Add button
    document.getElementById('addRecipientsBtn').addEventListener('click', function() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one user.');
            return;
        }

        const recipient_ids = Array.from(checked).map(cb => parseInt(cb.dataset.userId));

        // Disable button to prevent double submission
        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Adding...';

        fetch('../actions/create_dm.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ recipient_ids: recipient_ids })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof parent.showToast === 'function') {
                    parent.showToast('Conversation created!', 3000, 'success');
                }
                if (typeof parent.hideFloating === 'function') {
                    parent.hideFloating();
                }
                // Reload to show the new DM
                parent.location.reload();
            } else {
                alert(data.error || 'Failed to create conversation.');
                btn.disabled = false;
                btn.textContent = 'Add';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Add';
        });
    });
});