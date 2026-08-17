document.addEventListener('DOMContentLoaded', function() {
    // Add button handler
    document.getElementById('addChannelMembersBtn').addEventListener('click', function() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one user.');
            return;
        }
        const userIds = Array.from(checked).map(cb => parseInt(cb.dataset.userId));
        const conversationId = parseInt(document.querySelector('.modal-content').dataset.conversationId || 0);
        // fallback: get from a hidden input (we'll add one in PHP)
        // Actually we can pass via PHP variable – we'll add a hidden input
        const convIdInput = document.getElementById('conversationIdInput');
        const convId = convIdInput ? parseInt(convIdInput.value) : 0;

        const btn = this;
        btn.disabled = true;
        btn.textContent = 'Adding...';

        fetch('../actions/add_channel_members.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                conversation_id: convId,
                user_ids: userIds
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (typeof parent.showToast === 'function') {
                    parent.showToast(data.message || 'Members added successfully.', 3000, 'success');
                }
                // Refresh members list in parent
                if (typeof parent.loadChannelMembers === 'function') {
                    parent.loadChannelMembers(convId);
                }
                parent.hideFloating();
            } else {
                alert(data.error || 'Failed to add members.');
                btn.disabled = false;
                btn.textContent = 'Add Selected';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Network error.');
            btn.disabled = false;
            btn.textContent = 'Add Selected';
        });
    });

    // Search filter
    document.getElementById('userSearch').addEventListener('input', function() {
        const term = this.value.toLowerCase().trim();
        document.querySelectorAll('.user-item').forEach(item => {
            const name = item.querySelector('.user-name').textContent.toLowerCase();
            const barangay = item.querySelector('.user-barangay').textContent.toLowerCase();
            const matches = name.includes(term) || barangay.includes(term);
            item.style.display = matches ? '' : 'none';
        });
    });
});