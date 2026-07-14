<?php
require_once '../init_session.php';
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$isSuperAdmin = ($_SESSION['role'] ?? '') === 'super_admin';
$isAdmin = in_array($_SESSION['role'] ?? '', ['admin', 'super_admin']);

// Fetch user details including barangay
$stmt = $conn->prepare("
    SELECT u.fname, u.lname, u.email, u.phone_no, u.role, b.id as barangay_id, b.name as barangay_name
    FROM users_tbl u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die('User not found');
}

// Fetch all barangays for dropdown (if editing is allowed)
$barangays = [];
if ($isAdmin) {
    $barangayStmt = $conn->prepare("SELECT id, name FROM barangays ORDER BY name");
    $barangayStmt->execute();
    $barangays = $barangayStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $barangayStmt->close();
}

// Helper: format role for display
function formatRole($role) {
    return $role === 'super_admin' ? 'Super Admin' : ucfirst($role);
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

                <!-- Phone no. + Barangay -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone no.</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone_no'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Barangay</label>
                        <?php if ($isAdmin): ?>
                            <div class="custom-select">
                                <select name="barangay_id">
                                    <option value="">Select Barangay</option>
                                    <?php foreach ($barangays as $b): ?>
                                        <option value="<?= $b['id'] ?>" <?= ($user['barangay_id'] == $b['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        <?php else: ?>
                            <input type="text" value="<?php echo htmlspecialchars($user['barangay_name'] ?? 'Not set'); ?>" disabled>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Email Address + Role -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <?php if ($isSuperAdmin): ?>
                        <!-- Only Super Admins can change roles -->
                        <div class="form-group">
                            <label>Role</label>
                            <div class="custom-select">
                                <select name="role">
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="super_admin" <?php echo $user['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    <?php elseif ($isAdmin): ?>
                        <!-- Regular Admins see role as read-only (cannot change) -->
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo htmlspecialchars(formatRole($user['role'])); ?>" disabled>
                        </div>
                    <?php else: ?>
                        <!-- Regular users see role as read-only -->
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo htmlspecialchars(formatRole($user['role'])); ?>" disabled>
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