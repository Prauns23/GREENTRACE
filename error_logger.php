<?php
// error_logger.php – Centralized error logging

/**
 * Log an error with context
 * @param string $message    The error message
 * @param array  $context    Additional data (e.g., user_id, file, line, query)
 */
function logError($message, $context = []) {
    // Build log entry
    $log = '[' . date('Y-m-d H:i:s') . '] ';
    
    // Add user info if logged in
    if (isset($_SESSION['user_id'])) {
        $log .= '[User: ' . $_SESSION['user_id'] . '] ';
    } else {
        $log .= '[User: guest] ';
    }
    
    // Add IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log .= '[IP: ' . $ip . '] ';
    
    // Add request URI
    $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $log .= '[URI: ' . $uri . '] ';
    
    // Add main message
    $log .= $message;
    
    // Add context details (if any)
    if (!empty($context)) {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $log .= " [$key: $value]";
        }
    }
    
    // Send to server's error log
    error_log($log);
}

/**
 * Log a database error
 * @param mysqli $conn        The database connection
 * @param string $query       The SQL query that failed (optional)
 * @param string $additional  Extra info
 */
function logDbError($conn, $query = null, $additional = '') {
    $message = 'Database error: ' . $conn->error;
    $context = ['errno' => $conn->errno];
    if ($query) {
        $context['query'] = $query;
    }
    if ($additional) {
        $context['additional'] = $additional;
    }
    logError($message, $context);
}

/**
 * Set up error handlers
 */
function setupErrorHandling($environment = 'production') {
    // Turn off display errors for production
    if ($environment === 'production') {
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
    } else {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
    }
    
    // Always log errors
    ini_set('log_errors', 1);
    // Optional: set a custom error log file (uncomment if needed)
    // ini_set('error_log', __DIR__ . '/logs/php_errors.log');
    
    // Set error reporting level (log everything)
    error_reporting(E_ALL);
    
    // Custom error handler for PHP errors
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        $message = "PHP Error [$errno]: $errstr in $errfile on line $errline";
        logError($message, ['type' => 'error_handler']);
        // Don't halt execution (return false to let PHP handle it)
        return false;
    }, E_ALL);
    
    // Custom exception handler for uncaught exceptions
    set_exception_handler(function($exception) {
        $message = "Uncaught Exception: " . $exception->getMessage() . 
                   " in " . $exception->getFile() . " on line " . $exception->getLine();
        logError($message, ['trace' => $exception->getTraceAsString()]);
        // Show a generic error page if in production
        if (ini_get('display_errors') == 0) {
            http_response_code(500);
            echo '<h1>500 Internal Server Error</h1>';
            echo '<p>An error occurred. Please try again later.</p>';
        } else {
            throw $exception; // Let it display for development
        }
        exit;
    });
    
    // Register shutdown function to catch fatal errors
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = "Fatal Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
            logError($message, ['type' => 'shutdown']);
        }
    });
}
?>