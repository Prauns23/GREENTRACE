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
    return is_string($token)
        && $token !== ''
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Retrieve a CSRF token from a form field or request header.
 * @return string
 */
function getRequestCSRFToken() {
    if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
        return $_POST['csrf_token'];
    }

    return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
}

/**
 * Reject a state-changing request that does not contain a valid token.
 */
function requireCSRFToken() {
    $tokens = [getRequestCSRFToken()];

    // A request may contain both a form field and an AJAX header. Accept either
    // when valid so an older cached form field cannot override a current header.
    if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $tokens[] = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    foreach (array_unique($tokens) as $token) {
        if (verifyCSRFToken($token)) {
            return;
        }
    }

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid or missing CSRF token'
    ]);
    exit;
}

/**
 * Output a hidden input field with the CSRF token (for forms).
 */
function csrf_field() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        . '">';
}

/**
 * Get the CSRF token for use in AJAX headers.
 * @return string
 */
function csrf_token() {
    return generateCSRFToken();
}

?>
