<?php
require_once 'init_session.php';
require_once 'config.php';

if (!isset($_SESSION['first_name'])) {
    $_SESSION['open_signup_modal'] = true;
    header('Location: index.php');
    exit();
}

// Fetch the user's phone number from the database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT phone_no FROM users_tbl WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$phone = $user['phone_no'] ?? '';

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

        <form id="volunteerForm" method="post" action="submit_volunteer.php">
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
    // Pass user data from PHP to JavaScript
    const userData = {
        fullname: "<?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>",
        email: "<?php echo htmlspecialchars($_SESSION['email']); ?>",
        mobile: "<?php echo htmlspecialchars($phone); ?>"
    };

    document.addEventListener('DOMContentLoaded', function() {
        const fullnameInput = document.getElementById('fullname');
        const emailInput = document.getElementById('email');
        const mobileInput = document.getElementById('mobile');

        // Function to fill field on focus if empty
        function fillOnFocus(input, value) {
            if (!input) return;
            input.addEventListener('focus', function() {
                if (this.value === '' && value) {
                    this.value = value;
                }
            });
        }

        fillOnFocus(fullnameInput, userData.fullname);
        fillOnFocus(emailInput, userData.email);
        fillOnFocus(mobileInput, userData.mobile);
    });
</script>

<?php include 'footer.php'; ?>