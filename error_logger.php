<?php
// Centralized system error and audit logging.

define('SYSTEM_LOG_FILE', __DIR__ . '/logs/system.log');
define('AUDIT_LOG_FILE', __DIR__ . '/logs/audit.log');
define('LOG_ARCHIVE_DIR', __DIR__ . '/logs/archive');
define('SYSTEM_LOG_MAX_BYTES', 100 * 1024 * 1024);
define('AUDIT_ARCHIVE_RETENTION_MONTHS', 36);

/**
 * Prepare the log directory and rotate application-owned logs when required.
 */
function prepareApplicationLog($file, $level) {
    $directory = dirname($file);
    if (!is_dir($directory)) {
        @mkdir($directory, 0750, true);
    }

    if ($level === 'error') {
        $size = is_file($file) ? @filesize($file) : 0;
        if ($size !== false && $size >= SYSTEM_LOG_MAX_BYTES) {
            $archive = $file . '.' . date('Ymd-His') . '.log';
            @rename($file, $archive);
            if (is_file($archive)) {
                @chmod($archive, 0440);
            }
        }
        return;
    }

    if ($level !== 'audit' || !is_file($file) || @filesize($file) === 0) {
        return;
    }

    $currentMonth = date('Y-m');
    $lastModified = @filemtime($file);
    if ($lastModified === false || date('Y-m', $lastModified) === $currentMonth) {
        return;
    }

    if (!is_dir(LOG_ARCHIVE_DIR)) {
        @mkdir(LOG_ARCHIVE_DIR, 0750, true);
    }

    $archive = LOG_ARCHIVE_DIR . '/audit-' . date('Y-m', $lastModified) . '.log';
    if (!is_file($archive) && @rename($file, $archive)) {
        @chmod($archive, 0440);
    }

    if (!is_file($file)) {
        @touch($file);
    }

    $cutoff = strtotime('-' . AUDIT_ARCHIVE_RETENTION_MONTHS . ' months');
    foreach (glob(LOG_ARCHIVE_DIR . '/audit-????-??.log') ?: [] as $oldArchive) {
        if (@filemtime($oldArchive) !== false && @filemtime($oldArchive) < $cutoff) {
            @unlink($oldArchive);
        }
    }
}

/**
 * Write one structured JSON log entry to an application-owned file.
 */
function writeApplicationLog($file, $level, $message, $context = []) {
    $entry = [
        'timestamp' => date('c'),
        'level' => $level,
        'message' => (string) $message,
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'context' => $context,
    ];

    $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded !== false) {
        prepareApplicationLog($file, $level);
        error_log($encoded . PHP_EOL, 3, $file);
    }
}

/**
 * Log an error with context
 * @param string $message    The error message
 * @param array  $context    Additional data (e.g., user_id, file, line, query)
 */
function logError($message, $context = []) {
    writeApplicationLog(SYSTEM_LOG_FILE, 'error', $message, $context);
}

/**
 * Record a security or user activity event without mixing it with errors.
 */
function logAudit($action, $context = []) {
    writeApplicationLog(AUDIT_LOG_FILE, 'audit', $action, $context);
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
    
    // Always log errors to the application-owned system log.
    ini_set('log_errors', 1);
    ini_set('error_log', SYSTEM_LOG_FILE);
    
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