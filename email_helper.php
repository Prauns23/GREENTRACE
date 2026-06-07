<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

// Config later with valid creds
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'explosiveunderarm@gmail.com');
define('SMTP_PASS', 'ppcjfdktfobytqnm');
define('SMTP_FROM', 'greentrace-noreply@gmail.com');
define('SMTP_FROM_NAME', 'GreenTrace');

function sendEmail($to, $subject, $body) {
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
        error_log("Email cound not be send. Error: {$mail->ErrorInfo}");
        return false;
    }
}

?>