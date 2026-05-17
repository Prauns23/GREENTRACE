<?php
require_once '../init_session.php';
require_once '../config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo '<div class="error">Invalid activity ID.</div>';
    exit;
}

$stmt = $conn->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$activity = $stmt->get_result()->fetch_assoc();
if (!$activity) {
    echo '<div class="error">Activity not found.</div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Activity</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="edit_activity.css?v=2">
</head>

<body>
    <div class="edit-modal-container">
        <div class="edit-modal-header">
            <h2>Edit Activity</h2>
            <p>Edit the details of the activity you made such as the title, image, and etc.</p>
        </div>

        <div class="form-container">
            <form id="editActivityForm">
                <input type="hidden" name="activity_id" value="<?= $activity['id'] ?>">

                <div class="form-group">
                    <label>Edit Activity Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($activity['title']) ?>" placeholder="E.g., Urban Tree Planting" required>
                </div>

                <div class="image-upload-area" id="imageUploadArea">
                    <div class="image-preview" id="imagePreview">
                        <?php if (!empty($activity['image_url'])): ?>
                            <img src="../<?= htmlspecialchars($activity['image_url']) ?>" alt="Current image">
                        <?php else: ?>
                            <div class="placeholder-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="upload-text">Click to replace the image or drag and drop<br><small>PNG, JPG up to 10MB</small></div>
                    <input type="file" id="imageFile" accept="image/jpeg,image/png" style="display: none;">
                    <input type="hidden" name="image_url" id="imageUrl" value="<?= htmlspecialchars($activity['image_url'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Edit Activity Description</label>
                    <textarea name="description" rows="4" placeholder="Describe the activity in detail..." required><?= htmlspecialchars($activity['description']) ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Primary Badge</label>
                        <input type="text" name="badge_primary" value="<?= htmlspecialchars($activity['badge_primary'] ?? '') ?>" placeholder="E.g., Beginner-friendly">
                    </div>
                    <div class="form-group">
                        <label>Secondary Badge</label>
                        <input type="text" name="badge_secondary" value="<?= htmlspecialchars($activity['badge_secondary'] ?? '') ?>" placeholder="E.g., Outdoor/Indoor">
                    </div>
                </div>

                <div class="form-group">
                    <label>Edit Activity Date</label>
                    <input type="date" name="date" value="<?= $activity['date'] ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="time_start" value="<?= $activity['time_start'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="time_end" value="<?= $activity['time_end'] ?? '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Edit Activity Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($activity['location']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Edit Activity Meet-Up Point</label>
                    <input type="text" name="meetup_point" value="<?= htmlspecialchars($activity['meetup_point'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Capacity</label>
                    <input type="number" name="capacity" value="<?= $activity['capacity'] ?>">
                </div>


            </form>
        </div>
        <div class="button-group">
            <button type="button" class="cancel-btn" onclick="parent.hideFloating()">Cancel</button>
            <button type="submit" class="submit-btn">Save</button>
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
                    imageUrlHidden.value = '';
                };
                reader.readAsDataURL(file);
            } else {
                alert('Only JPG or PNG files allowed.');
            }
        });

        const form = document.getElementById('editActivityForm');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const submitBtn = form.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';
            try {
                const response = await fetch('../actions/update_activity.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    parent.location.href = '../activities.php?toast=' + encodeURIComponent(data.message) + '&type=success';
                } else {
                    alert(data.error || 'Update failed');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Save';
                }
            } catch (err) {
                alert('Network error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save';
            }
        });
    </script>
</body>

</html>