<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\STMP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/STMP.php';

// Config later with valid creds
define('STMP_HOST', 'stmp.gmail.com');
define('STMP_PORT', 587);
define('STMP_USER', 'explosiveunderarm@gmail.com');


?>