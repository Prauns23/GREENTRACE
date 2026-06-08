<?php
require_once '../init_session.php';

$errors = [
    'login' => $_SESSION['login_error'] ?? $_SESSION['csrf_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'sign-in';

// Clear errors after displaying
unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['csrf_error'], $_SESSION['active_form']);

function showError($error)
{
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}
function isActiveForm($formName, $activeForm)
{
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="sign-in.css">
</head>

<body>
    <div class="signIn-container" <?= isActiveForm('sign-in', $activeForm); ?>>
        <span class="close-btn" onclick="parent.hideLogin && parent.hideLogin()">×</span>
        <div class="signIn-grid">
            <div class="form-column">
                <h1>Welcome Back!</h1>
                <p class="subtitle">Let's keep planting the future, your forest is waiting</p>
                <?php echo showError($errors['login']); ?>
                <form id="signinForm" action="/greentrace/login_register.php" method="post">
                    <?php csrf_field(); ?>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="Email Account">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-input-wrapper" id="signinPasswordWrapper">
                            <input type="password" name="password" placeholder="Password" class="password-input" id="signinPassword">
                            <button class="toggle-password" type="button" onclick="togglePassword(this)" style="display: none;">
                                <img src="eye-off.svg" alt="Hide" class="eye-icon eye-off">
                                <img src="eye.svg" alt="Show" class="eye-icon eye-on" style="display: none;">
                            </button>
                        </div>
                        <div class="forgot-password">
                            <label for="">Forgot Password?</label>
                        </div>
                    </div>
                    <button type="submit" name="sign-in" class="login-btn">Login Account</button>
                    <div class="signin-link">
                        Don't have an account? <a href="#" onclick="parent.switchToSignUp && parent.switchToSignUp()">Sign up</a>
                    </div>
                </form>
            </div>
            <div class="image-column">
                <img src="login03.svg" alt="Tree planting" class="side-image">
            </div>
        </div>
    </div>
    <script src="password-toggle.js"></script>
    <script>
        const signinForm = document.getElementById('signinForm');

        function showSigninToast(message, duration = 5000) {
            if (typeof parent !== 'undefined' && parent.showToast) {
                parent.showToast(message, duration, 'error');
                return;
            }

            let errorDiv = document.querySelector('.error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.color = '#e53935';
                errorDiv.style.marginBottom = '12px';
            }
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';

            const passwordGroup = document.querySelector('.password-input-wrapper')?.closest('.form-group');
            if (passwordGroup) {
                passwordGroup.insertAdjacentElement('afterend', errorDiv);
            } else if (signinForm) {
                signinForm.insertBefore(errorDiv, signinForm.firstChild);
            }
        }

        if (signinForm) {
            signinForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('sign-in', '1');

                try {
                    const response = await fetch('../login_register.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        const redirectUrl = '../index.php?toast=' + encodeURIComponent(data.message) + '&type=success';
                        if (typeof parent !== 'undefined') {
                            parent.location.href = redirectUrl;
                        } else {
                            window.location.href = redirectUrl;
                        }
                    } else {
                        showSigninToast(data.error || 'Incorrect email or password.');
                    }
                } catch (err) {
                    showSigninToast('Network error. Please try again.');
                    console.error(err);
                }
            });
        }
    </script>
    <script>
        if (window.location.hash.startsWith('#error=')) {
            const errorMsg = decodeURIComponent(window.location.hash.substring(7));
            let errorDiv = document.querySelector('.error-message');
            const form = document.querySelector('form');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
            }
            errorDiv.textContent = errorMsg;
            errorDiv.style.display = 'block';

            const passwordGroup = document.querySelector('.password-input-wrapper')?.closest('.form-group');
            if (passwordGroup) {
                passwordGroup.insertAdjacentElement('afterend', errorDiv);
            } else {
                form.appendChild(errorDiv);
            }

            history.replaceState(null, null, window.location.pathname);
        }
    </script>
</body>

</html>