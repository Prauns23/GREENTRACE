<?php 
$host = "localhost";
$username = "root";
$password = "";
$database = "greentrace";

// Set environment: 'production' or 'development'
define('ENVIRONMENT', 'development'); // change to 'production' when live

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    // Log connection error using the logger (if available)
    if (file_exists(__DIR__ . '/error_logger.php')) {
        require_once __DIR__ . '/error_logger.php';
        logError("Database connection failed: " . $conn->connect_error);
    }
    die("Connection failed: " . $conn->connect_error);
}

// Load error logger and set up handlers
require_once __DIR__ . '/error_logger.php';
setupErrorHandling(ENVIRONMENT);
?>