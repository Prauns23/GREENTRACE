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
    $isValid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    
    // Log for debugging
    error_log('CSRF Verification: POST=' . substr($token ?? '', 0, 8) . 
              ', Session=' . substr($_SESSION['csrf_token'] ?? '', 0, 8) . 
              ', Match=' . ($isValid ? 'YES' : 'NO'));
    
    return $isValid;
}

/**
 * Output a hidden input field with the CSRF token (for forms).
 */
function csrf_field() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Get the CSRF token for use in AJAX headers.
 * @return string
 */
function csrf_token() {
    return generateCSRFToken();
}

?>