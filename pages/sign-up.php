<?php
require_once '../init_session.php';

// Preserve submitted values from session on error (used only for initial load)
$old_fname = $_SESSION['old_fname'] ?? '';
$old_lname = $_SESSION['old_lname'] ?? '';
$old_email = $_SESSION['old_email'] ?? '';
$old_phone = $_SESSION['old_phone'] ?? '';
unset($_SESSION['old_fname'], $_SESSION['old_lname'], $_SESSION['old_email'], $_SESSION['old_phone']);

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'sign-up';

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}
function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="sign-up.css">
</head>
<body>
    <div class="login-container" <?= isActiveForm('sign-up', $activeForm); ?>>
        <span class="close-btn" onclick="parent.hideLogin && parent.hideLogin()">×</span>
        <div class="login-grid">
            <div class="form-column">
                <h1>Create your account</h1>
                <p class="subtitle">Every tree begins with one step — yours.</p>
                <form id="signupForm">
                    <?php csrf_field(); ?>
                    <div id="signupError" class="error-message" style="display: none; color: #e53935; margin-bottom: 12px;"></div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" placeholder="First Name" value="<?= htmlspecialchars($old_fname) ?>">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" placeholder="Last Name" value="<?= htmlspecialchars($old_lname) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="Email Account" value="<?= htmlspecialchars($old_email) ?>">
                    </div>
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <div class="password-input-wrapper" id="signupPasswordWrapper">
                            <input type="password" name="password" placeholder="Password" class="password-input" id="signupPassword">
                            <button class="toggle-password" type="button" onclick="togglePassword(this)" style="display: none;">
                                <img src="eye-off.svg" alt="Hide" class="eye-icon eye-off">
                                <img src="eye.svg" alt="Show" class="eye-icon eye-on" style="display: none;">
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <div class="phone-input">
                            <span class="country-code">PHIL</span>
                            <input type="tel" name="phone_num" placeholder="09XX-XXX-YYYY" class="phone-field" maxlength="11" pattern="\d{11}" inputmode="numeric" required value="<?= htmlspecialchars($old_phone) ?>">
                        </div>
                    </div>
                    <div class="terms">
                        By signing up, you have agreed to our <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a>.
                    </div>
                    <button type="submit" name="sign-up" class="create-btn">Create Account</button>
                    <div class="signin-link">
                        Already have an account? <a href="#" onclick="parent.switchToSignIn && parent.switchToSignIn()">Sign in</a>
                    </div>
                </form>
            </div>
            <div class="image-column">
                <img src="login-img.svg" alt="Tree planting" class="side-image">
            </div>
        </div>
    </div>

    <div id="signupToast" class="toast hidden">
        <span id="signupToastMessage"></span>
    </div>

    <script src="password-toggle.js"></script>
    <script>
        // Phone number formatting (unchanged)
        document.addEventListener('DOMContentLoaded', function() {
            const phoneField = document.querySelector('.phone-field');
            if (phoneField) {
                phoneField.addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '');
                });
                phoneField.addEventListener('focus', function() {
                    if (this.value === '') {
                        this.value = '09';
                        this.setSelectionRange(this.value.length, this.value.length);
                    }
                });
            }
        });

        // AJAX form submission
        const form = document.getElementById('signupForm');

        function showSignupToast(message, duration = 5000) {
            if (typeof parent !== 'undefined' && parent.showToast) {
                parent.showToast(message, duration, 'error');
                return;
            }

            const toast = document.getElementById('signupToast');
            const toastMessage = document.getElementById('signupToastMessage');
            if (!toast || !toastMessage) {
                alert(message);
                return;
            }

            toastMessage.textContent = message;
            toast.classList.remove('hidden');
            toast.classList.add('show');

            clearTimeout(window.signupToastTimer);
            window.signupToastTimer = setTimeout(hideSignupToast, duration);
        }

        function hideSignupToast() {
            const toast = document.getElementById('signupToast');
            if (toast) {
                toast.classList.remove('show');
                toast.classList.add('hidden');
            }
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const phoneField = this.querySelector('.phone-field');
            const phoneValue = phoneField ? phoneField.value.replace(/\D/g, '') : '';
            if (phoneValue.length !== 11) {
                showSignupToast('Enter a valid phone number');
                return;
            }

            const formData = new FormData(this);
            // Add the sign-up flag (in case the button name is not sent)
            formData.append('sign-up', '1');

            try {
                const response = await fetch('../login_register.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    // Redirect parent page with success toast
                    parent.location.href = '../index.php?toast=' + encodeURIComponent(data.message) + '&type=success';
                } else {
                    showSignupToast(data.error || 'Registration failed.');
                }
            } catch (err) {
                showSignupToast('Network error. Please try again.');
                console.error(err);
            }
        });
    </script>
</body>
</html>