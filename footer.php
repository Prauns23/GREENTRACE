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
<?php unset($_SESSION['login_success']); endif; ?>

<?php if (isset($_SESSION['active_form'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            <?php if ($_SESSION['active_form'] === 'sign-in'): ?>
                var errorMsg = "<?php echo addslashes($_SESSION['login_error'] ?? ''); ?>";
                if (typeof showSignIn === 'function') showSignIn(errorMsg);
            <?php elseif ($_SESSION['active_form'] === 'sign-up'): ?>
                if (typeof showSignUp === 'function') showSignUp();
            <?php endif; ?>
        });
    </script>
<?php unset($_SESSION['active_form'], $_SESSION['login_error']); endif; ?>

<?php if (isset($_SESSION['open_signup_modal'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showSignUp === 'function') showSignUp();
        });
    </script>
<?php unset($_SESSION['open_signup_modal']); endif; ?>

</body>
</html>