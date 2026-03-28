<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

// Fetch user data for pre‑fill on focus
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT phone_no FROM users_tbl WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$phone = $user['phone_no'] ?? '';

$check_profile = $conn->prepare("SELECT id FROM volunteer_profiles WHERE user_id = ?");

$check_profile->bind_param("i", $user_id);
$check_profile->execute();
$result = $check_profile->get_result();

if ($result->num_rows > 0) {
    // Already registered, redirect to activities
    header('Location: activities.php');
    exit();
}

include 'header.php';
?>

<div class="volunteer-page">
    <div class="volunteer-container">
        <div class="volunteer-header">
            <div class="header-row">
                <div class="icon-column">
                    <span class="material-symbols-rounded">volunteer_activism</span>
                </div>
                <div class="header-column">
                    <h3>Volunteer Registration</h3>
                    <p>Your contribution supports organized, sustainable reforestation efforts in our community</p>
                </div>
            </div>
        </div>

        <form id="volunteerForm">
            <!-- Full Name -->
            <div class="form-group">
                <label for="fullname">Full Name <span class="required">*</span></label>
                <input type="text" id="fullname" name="fullname" placeholder="Juan Dela Cruz" required>
            </div>

            <!-- Date of Birth -->
            <div class="form-group">
                <label for="dob">Date of Birth <span class="required">*</span></label>
                <input type="date" id="dob" name="dob" required>
            </div>

            <!-- Gender (optional) -->
            <div class="form-group">
                <label for="gender">Gender (optional)</label>
                <select id="gender" name="gender">
                    <option value="" disabled selected>Select gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                    <option value="prefer-not">Prefer not to say</option>
                </select>
            </div>

            <!-- Mobile Number -->
            <div class="form-group">
                <label for="mobile">Mobile Number <span class="required">*</span></label>
                <input type="tel" id="mobile" name="mobile" placeholder="09XX XXXX XXXX" required>
            </div>

            <!-- Email Address -->
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" placeholder="Johndoe@example.com" required>
            </div>

            <!-- Municipality and Barangay side by side -->
            <div class="form-row">
                <div class="form-group">
                    <label for="municipality">Municipality <span class="required">*</span></label>
                    <input type="text" id="municipality" name="municipality" placeholder="e.g. Morong Bataan" required>
                </div>
                <div class="form-group">
                    <label for="barangay">Barangay <span class="required">*</span></label>
                    <input type="text" id="barangay" name="barangay" placeholder="e.g. Nagbalayong" required>
                </div>
            </div>

            <!-- Checkboxes -->
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="understand" required>
                    <span>I understand the importance of reforestation and will follow planting guidelines.</span>
                </label>
                <label>
                    <input type="checkbox" name="agree" required>
                    <span>I agree to follow DENR environmental and safety protocols.</span>
                </label>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn">Submit Registration</button>
        </form>
    </div>
</div>

<script>
    // User data for auto‑fill on focus
    const userFullName = "<?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>";
    const userEmail = "<?php echo htmlspecialchars($_SESSION['email']); ?>";
    const userPhone = "<?php echo htmlspecialchars($phone); ?>";

    function fillOnFocus(fieldId, value) {
        const field = document.getElementById(fieldId);
        if (field && value) {
            field.addEventListener('focus', function() {
                if (this.value === '') {
                    this.value = value;
                }
            });
        }
    }

    fillOnFocus('fullname', userFullName);
    fillOnFocus('email', userEmail);
    fillOnFocus('mobile', userPhone);

    // AJAX form submission
    const form = document.getElementById('volunteerForm');
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        try {
            const response = await fetch('submit_volunteer.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                // Redirect to activities.php
                window.location.href = 'activities.php';
            } else {
                // Show error toast
                if (typeof showToast === 'function') {
                    showToast(data.error || 'An error occurred.', 4000);
                } else {
                    alert(data.error || 'An error occurred.');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            if (typeof showToast === 'function') {
                showToast('An error occurred. Please try again.', 4000);
            } else {
                alert('An error occurred. Please try again.');
            }
        }
    });
</script>

<?php include 'footer.php'; ?>