<?php
// CSRF Protection Helper Functions

/**
 * Generate or retrieve the CSRF token for the current session.
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify that a given token matches the session token.
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output a hidden input field with the CSRF token (for forms).
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Get the CSRF token for use in AJAX headers.
 * @return string
 */
function csrf_token() {
    return generateCSRFToken();
}

?>