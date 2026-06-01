<?php
require_once '../init_session.php';
require_once '../config.php';

// Only admin can access this modal directly
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div style="padding: 20px; text-align: center;">Access denied. You must be an administrator.</div>';
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Activity</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="add_activity.css?v=1">
</head>

<body>
    <div class="add-modal-container">
        <div class="add-modal-header">
            <h2>Add Activity</h2>
            <p>Insert a new activity to help bloom and connect our local community!</p>
        </div>

        <div class="form-scrollable">
            <form id="addActivityForm" enctype="multipart/form-data">

                <div class="form-group">
                    <label>Activity Title <span class="required">*</span></label>
                    <input type="text" name="title" placeholder="E.g., Urban Tree Planting" required>
                </div>

                <div class="image-upload-area" id="imageUploadArea">
                    <div class="image-preview" id="imagePreview">
                        <div class="placeholder-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    </div>
                    <div class="upload-text">Click to upload the image or drag and drop<br><small>PNG, JPG up to 10MB</small></div>
                    <input type="file" id="imageFile" accept="image/jpeg,image/png" style="display: none;" name="image">
                    <input type="hidden" name="image_url" id="imageUrl">
                </div>

                <div class="form-group">
                    <label>Activity Description <span class="required">*</span></label>
                    <textarea name="description" rows="4" placeholder="Describe the activity in detail..." required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Primary Badge <span class="required">*</span></label>
                        <input type="text" name="badge_primary" placeholder="E.g., Beginner-friendly" required>
                    </div>
                    <div class="form-group">
                        <label>Secondary Badge <span class="required">*</span></label>
                        <input type="text" name="badge_secondary" placeholder="E.g., Outdoor/Indoor" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Activity Date <span class="required">*</span></label>
                    <input type="date" name="date" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="time_start" placeholder="07:00">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="time_end" placeholder="12:00">
                    </div>
                </div>

                <div class="form-group">
                    <label>Activity Location <span class="required">*</span></label>
                    <input type="text" name="location" placeholder="E.g., Poblacion, Morong, Bataan" required>
                </div>

                <div class="form-group">
                    <label>Activity Meet-Up Point <span class="required">*</span></label>
                    <input type="text" name="meetup_point" placeholder="E.g., Plaza of Morong" required>
                </div>

                <div class="form-group">
                    <label>Set Max Capacity</label>
                    <input type="number" name="capacity" value="50">
                </div>
            </form>
        </div>

        <div class="button-group">
            <button type="button" class="cancel-btn" onclick="parent.hideFloating()">Cancel</button>
            <button type="submit" form="addActivityForm" class="submit-btn">Add Activity</button>
        </div>
    </div>

    <script>
        const imageUploadArea = document.getElementById('imageUploadArea');
        const imageFileInput = document.getElementById('imageFile');
        const imagePreview = document.getElementById('imagePreview');
        const imageUrlHidden = document.getElementById('imageUrl');

        imageUploadArea.addEventListener('click', () => imageFileInput.click());
        imageFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && (file.type === 'image/jpeg' || file.type === 'image/png')) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    imagePreview.innerHTML = `<img src="${ev.target.result}" alt="Preview">`;
                    imageUrlHidden.value = ''; // will be handled by backend later
                };
                reader.readAsDataURL(file);
            } else {
                alert('Only JPG or PNG files allowed.');
            }
        });

        const form = document.getElementById('addActivityForm');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Creating...';
            const formData = new FormData(form);
            try {
                const response = await fetch('../actions/add_activity.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    parent.location.href = '../activities.php?toast=' + encodeURIComponent(data.message) + '&type=success';
                } else {
                    alert(data.error || 'Failed to create activity');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Add Activity';
                }
            } catch (err) {
                alert('Network error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Add Activity';
            }
        });
    </script>
</body>

</html>