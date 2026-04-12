</div> <!-- close .page-content -->

<script src="app.js"></script>
<script src="nav.js"></script>

<?php if (isset($_SESSION['login_success'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showToast === 'function') {
                showToast("<?php echo $_SESSION['login_success']; ?>");
            }
        });
    </script>
<?php unset($_SESSION['login_success']);
endif; ?>

<?php if (isset($_SESSION['register_success'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showToast === 'function') {
                showToast("<?php echo $_SESSION['register_success']; ?>");
            }
        });
    </script>
<?php unset($_SESSION['register_success']);
endif; ?>

<?php if (isset($_SESSION['register_error'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showToast === 'function') {
                showToast("<?php echo $_SESSION['register_error']; ?>", 4000, 'error');
            }
        });
    </script>
<?php unset($_SESSION['register_error']);
endif; ?>

<?php if (isset($_SESSION['login_error'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showToast === 'function') {
                showToast("<?php echo $_SESSION['login_error']; ?>", 4000, 'error');
            }
        });
    </script>
<?php unset($_SESSION['login_error']);
endif; ?>

<?php if (isset($_SESSION['active_form'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            <?php if ($_SESSION['active_form'] === 'sign-in'): ?>
                if (typeof showSignIn === 'function') showSignIn();
            <?php elseif ($_SESSION['active_form'] === 'sign-up'): ?>
                if (typeof showSignUp === 'function') showSignUp();
            <?php endif; ?>
        });
    </script>
<?php unset($_SESSION['active_form']);
endif; ?>

<?php if (isset($_SESSION['open_signup_modal'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showSignUp === 'function') showSignUp();
        });
    </script>
<?php unset($_SESSION['open_signup_modal']);
endif; ?>

</body>

</html>