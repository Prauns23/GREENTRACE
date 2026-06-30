<?php
require_once 'config.php';
require_once 'init_session.php';

/**
 * Check if a login/register attempt is allowed based on IP and optionally email.
 * @param string $ip
 * @param string|null $email
 * @param int $maxAttempts
 * @param int $timeWindow minutes
 * @return bool
 */
function checkRateLimit($ip, $email = null, $maxAttempts = 5, $timeWindow = 15) {
    global $conn;
    $cutoff = date('Y-m-d H:i:s', strtotime("-$timeWindow minutes"));
    $sql = "SELECT COUNT(*) as cnt FROM login_attempts WHERE ip = ? AND attempt_time > ?";
    $params = [$ip, $cutoff];
    $types = "ss";

    // If email is provided, also count by email (to block per-account brute force)
    if ($email) {
        $sql .= " OR email = ?";
        $params[] = $email;
        $types .= "s";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $count = $result['cnt'] ?? 0;
    $stmt->close();

    return $count < $maxAttempts;
}

/**
 * Log an attempt (success or failure).
 */
function logLoginAttempt($ip, $email = null, $userId = null, $success = 0) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip, email, user_id, success, attempt_time) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssii", $ip, $email, $userId, $success);
    $stmt->execute();
    $stmt->close();
}

/**
 * Clear attempts for an IP (after successful login) – optional.
 */
function clearLoginAttempts($ip) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}