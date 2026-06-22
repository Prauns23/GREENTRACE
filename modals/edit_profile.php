<?php
require_once '../init_session.php';
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$stmt = $conn->prepare("SELECT fname, lname, email, phone_no, role FROM users_tbl WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die('User not found');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="edit_profile.css">
</head>

<body>
    <div class="edit-profile">
        <div class="edit-profile-header">
            <h2>Edit profile information</h2>
            <p>Customize your profile, update your info, and manage how you appear to others.</p>
        </div>
        <div class="edit-profile-container">
            <form id="editProfileForm">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <?php csrf_field(); ?>

                <!-- First Name + Last Name -->
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['fname']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['lname']); ?>" required>
                    </div>
                </div>

                <!-- Date of Birth (disabled) + Phone no. -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="text" value="MM-DD-YYYY" disabled>
                    </div>
                    <div class="form-group">
                        <label>Phone no.</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone_no'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Email Address + Role (admin only) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <?php if ($isAdmin): ?>
                        <div class="form-group">
                            <label>Role</label>
                            <div class="custom-select">
                                <select name="role">
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Volunteer</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled>
                        </div>
                    <?php endif; ?>
                </div>


            </form>
        </div>
        <div class="button-group">
            <button type="button" class="cancel-btn" onclick="parent.hideFloating()">Cancel</button>
            <button type="submit" class="submit-btn">Save</button>
        </div>
    </div>

    <script>
        const form = document.getElementById('editProfileForm');
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('.submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';

            const formData = new FormData(this);
            try {
                const response = await fetch('../actions/update_profile.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    parent.location.href = '../profile.php?toast=' + encodeURIComponent('Profile updated successfully!') + '&type=success';
                } else {
                    if (parent.showToast) parent.showToast(data.error || 'Update failed', 4000, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Save';
                }
            } catch (err) {
                if (parent.showToast) parent.showToast('Network error', 4000, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save';
            }
        });
    </script>
</body>

</html>