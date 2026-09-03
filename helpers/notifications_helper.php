<?php
require_once __DIR__ . '/email_helper.php';

/**
 * Build the temporary generic notification email. Design-specific templates
 * can replace this later without changing notification callers.
 */
function buildNotificationEmail($recipientName, $title, $message, $link = null)
{
    $safeName = htmlspecialchars($recipientName ?: 'GreenTrace user', ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
    $plainMessage = trim(strip_tags((string)$message));
    $safeMessage = nl2br(htmlspecialchars($plainMessage, ENT_QUOTES, 'UTF-8'));

    $linkMarkup = '';
    $notificationUrl = resolveNotificationUrl($link);
    if ($notificationUrl !== null) {
        $safeUrl = htmlspecialchars($notificationUrl, ENT_QUOTES, 'UTF-8');
        $linkMarkup = '<p style="margin:24px 0 0;"><a href="' . $safeUrl . '" style="display:inline-block;padding:10px 18px;border-radius:999px;background:#2e7d32;color:#ffffff;text-decoration:none;font-weight:600;">View notification</a></p>';
    }

    return '<div style="max-width:600px;margin:0 auto;padding:24px;font-family:Arial,sans-serif;color:#1f2937;line-height:1.6;">'
        . '<p style="margin:0 0 12px;">Hello ' . $safeName . ',</p>'
        . '<h2 style="margin:0 0 12px;color:#1f3a2c;">' . $safeTitle . '</h2>'
        . '<p style="margin:0;">' . $safeMessage . '</p>'
        . $linkMarkup
        . '<p style="margin:28px 0 0;color:#64748b;font-size:13px;">This is an automated update from GreenTrace.</p>'
        . '</div>';
}

function resolveNotificationUrl($link)
{
    $link = trim((string)$link);
    if ($link === '') return null;

    if (filter_var($link, FILTER_VALIDATE_URL)) {
        return $link;
    }

    if (empty($_SERVER['HTTP_HOST'])) return null;

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptDirectory = preg_replace('#/(actions|admin|modals)$#', '', $scriptDirectory);

    return $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim($scriptDirectory, '/') . '/' . ltrim($link, '/');
}

function emailNotificationToUser($user_id, $title, $message, $link = null)
{
    global $conn;

    $userStmt = $conn->prepare("SELECT email, CONCAT(fname, ' ', lname) AS name FROM users_tbl WHERE id = ? AND archived = 0 LIMIT 1");
    if (!$userStmt) return false;

    $userStmt->bind_param('i', $user_id);
    $userStmt->execute();
    $recipient = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$recipient || !filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = 'GreenTrace: ' . str_replace(["\r", "\n"], '', (string)$title);
    $body = buildNotificationEmail($recipient['name'], $title, $message, $link);

    return sendEmail($recipient['email'], $subject, $body);
}

/**
 * Insert an in-app notification and send a best-effort email copy.
 *
 * @param int         $user_id Recipient user ID
 * @param string      $type    'application', 'activity', 'report', 'message', 'system'
 * @param string      $title   Short headline
 * @param string      $message Detailed message (can contain simple HTML)
 * @param string|null $link    Optional URL to click
 * @return bool Whether the in-app notification was created
 */
function createNotification($user_id, $type, $title, $message, $link = null)
{
    global $conn;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    if (!$stmt) return false;

    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    $created = $stmt->execute();
    $stmt->close();

    if (!$created) return false;

    // Email delivery must not prevent the in-app notification from succeeding.
    emailNotificationToUser($user_id, $title, $message, $link);

    return true;
}
?>
