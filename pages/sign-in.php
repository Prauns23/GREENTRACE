<?php 

session_start();

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];

$activeForm = $_SESSION['active_form'] ?? 'sign-in';

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
    <title>Sign-in</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="sign-in.css">
</head>

<body>
    <div class="signIn-container" <?= isActiveForm('sign-in', $activeForm); ?>>

        <span class="close-btn" onclick="parent.hideLogin && parent.hideLogin()">×</span>

        <div class="signIn-grid">
            <!-- LEFT COLUMN - Form -->
            <div class="form-column">


                <h1>Welcome Back!</h1>
                <p class="subtitle">Let's keep planting the future, your forest is waiting</p>


                <form action="../login_register.php" method="post" target="_parent">
                    <?= showError($errors['login']);  ?>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Email Account">
                    </div>


                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-input-wrapper" id="signinPasswordWrapper">
                            <input type="password" name="password" placeholder="Password" class="password-input" id="signinPassword">
                            <button class="toggle-password" type="button" onclick="togglePassword(this)"
                                style="display: none;">
                                <img src="eye-off.svg" alt="Hide" class="eye-icon eye-off">
                                <img src="eye.svg" alt="Show" class="eye-icon eye-on" style="display: none;">
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="sign-in" class="login-btn">Login Account</button>

                    <div class="signin-link">
                        Don't have an account? <a href="#"
                            onclick="parent.switchToSignUp && parent.switchToSignUp()">Sign up</a>
                    </div>
                </form>
            </div>

            <!-- RIGHT COLUMN - Image -->
            <div class="image-column">
                <img src="login03.svg" alt="Tree planting" class="side-image">

            </div>
        </div>
    </div>
    <script src="password-toggle.js"></script>
</body>

</html>