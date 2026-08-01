<?php 
$host = "localhost";
$username = "root";
$password = "";
$database = "greentrace";

// Set environment
define('ENVIRONMENT', 'development');

// Set PHP timezone
date_default_timezone_set('Asia/Manila');

$conn = new mysqli($host, $username, $password, $database);

// Set MySQL timezone to match PHP
$conn->query("SET time_zone = '+08:00'");

if ($conn->connect_error) {
    if (file_exists(__DIR__ . '/error_logger.php')) {
        require_once __DIR__ . '/error_logger.php';
        logError("Database connection failed: " . $conn->connect_error);
    }
    die("Connection failed: " . $conn->connect_error);
}

require_once __DIR__ . '/error_logger.php';
setupErrorHandling(ENVIRONMENT);
?>