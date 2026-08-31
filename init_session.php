<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/error_logger.php';
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (in_array($requestMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    requireCSRFToken();
}
?>
