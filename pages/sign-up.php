<?php 

session_start();

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'sign-up';

session_unset();

function showError($error) {
    return !empty($error) ? "<p class= 'error-message'>$error</p>" : '';
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
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="sign-up.css">
</head>

<body>
    <div class="login-container" <?= isActiveForm('sign-up', $activeForm); ?>>
        <!-- Two-column layout -->
        <span class="close-btn" onclick="parent.hideLogin && parent.hideLogin()">×</span>

        <div class="login-grid">
            <!-- LEFT COLUMN - Form -->
            <div class="form-column">

                <!-- Header -->
                <h1>Create your account</h1>
                <p class="subtitle">Every tree begins with one step — yours.</p>

                <!-- Form -->
                <form action="../login_register.php" method="post" target="_parent">
                    <?= showError($errors['login']);  ?>
                    <!-- First Name & Last Name row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" placeholder="First Name">
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" placeholder="Last Name">
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" placeholder="Email Account">
                    </div>

                    <!-- Password section -->
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <div class="password-input-wrapper" id="signupPasswordWrapper">
                            <input type="password" name="password" placeholder="Password" class="password-input" id="signupPassword">
                            <button class="toggle-password" type="button" onclick="togglePassword(this)"
                                style="display: none;">
                                <img src="eye-off.svg" alt="Hide" class="eye-icon eye-off">
                                <img src="eye.svg" alt="Show" class="eye-icon eye-on" style="display: none;">
                            </button>
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <div class="phone-input">
                            <span class="country-code">PHIL</span>
                            <input type="tel" name="phone_num" placeholder="09XX-XXX-YYYY" class="phone-field">
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="terms">
                        By signing up, you have agreed to our <a href="#">Terms & Conditions</a> and <a href="#">Privacy
                            Policy</a>.
                    </div>

                    <!-- Create Account Button -->
                    <button type="submit" name="sign-up" class="create-btn">Create Account</button>

                    <!-- Sign In Link -->
                    <div class="signin-link">
                        Already have an account? <a href="#"
                            onclick="parent.switchToSignIn && parent.switchToSignIn()">Sign in</a>
                    </div>
                </form>
            </div>

            <!-- RIGHT COLUMN - Image -->
            <div class="image-column">
                <img src="login-img.svg" alt="Tree planting" class="side-image">
                <!-- Optional overlay text -->
            </div>
        </div>
    </div>
    <script src="password-toggle.js"></script>
</body>

</html>