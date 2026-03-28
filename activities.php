<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

// Check if volunteer profile exists
$user_id = $_SESSION['user_id'];
$check = $conn->prepare("SELECT id FROM volunteer_profiles WHERE user_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$result = $check->get_result();
if ($result->num_rows === 0) {
    // No profile, redirect to registration
    header('Location: volunteer.php');
    exit();
}

include 'header.php';
?>

<div class="activities-page">
    <h1>Upcoming Activities</h1>
    <p>This is where activities will be listed.</p>
</div>

<script>
    // Check for volunteer success message and show toast
    <?php if (isset($_SESSION['volunteer_success'])): ?>
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showToast === 'function') {
                showToast("<?php echo addslashes($_SESSION['volunteer_success']); ?>");
            }
        });
    <?php unset($_SESSION['volunteer_success']); endif; ?>
</script>

<?php include 'footer.php'; ?>