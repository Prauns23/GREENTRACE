function joinActivity(activityId) {
    fetch('check_volunteer_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.hasApprovedProfile) {
                window.location.href = 'join_activity.php?activity_id=' + activityId;
            } else {
                if (confirm('You need to be a verified volunteer. Would you like to register now?')) {
                    showVolunteerForm();
                }
            }
        });
}