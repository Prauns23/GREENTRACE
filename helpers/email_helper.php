<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once dirname(__DIR__) . '/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/phpmailer/src/PHPMailer.php';
require_once dirname(__DIR__) . '/phpmailer/src/SMTP.php';

// Keep credentials outside Git. Configure these values in Apache or the host OS.
define('SMTP_HOST', getenv('GREENTRACE_SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', (int)(getenv('GREENTRACE_SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('GREENTRACE_SMTP_USER') ?: '');
define('SMTP_PASS', getenv('GREENTRACE_SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('GREENTRACE_SMTP_FROM') ?: SMTP_USER);
define('SMTP_FROM_NAME', getenv('GREENTRACE_SMTP_FROM_NAME') ?: 'GreenTrace');

function sendEmail($to, $subject, $body) {
    if (SMTP_USER === '' || SMTP_PASS === '' || SMTP_FROM === '') {
        error_log('Email was not sent because GreenTrace SMTP credentials are not configured.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host        = SMTP_HOST;
        $mail->SMTPAuth        = true;
        $mail->Username        = SMTP_USER;
        $mail->Password        = SMTP_PASS;
        $mail->SMTPSecure        = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject  = $subject;
        $mail->Body     = $body;
        $mail->AltBody = strip_tags($body); 

        $mail->send();
        return true; 
    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}

?>
