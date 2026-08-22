<?php
require_once 'init_session.php';
require_once 'config.php';
require_once 'rate_limit.php';

// Ensure no extra output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // don't output errors to browser
ini_set('log_errors', 1);

// Get client IP
$ip = $_SERVER['REMOTE_ADDR'];

// Helper: send JSON error and log
function sendJsonError($message, $logMessage = null) {
    if ($logMessage) {
        logError("login_register.php error: $logMessage");
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

//  SIGN UP (AJAX) 
if (isset($_POST['sign-up'])) {
    try {
        $email = trim($_POST['email'] ?? '');

        // Rate limit: 3 attempts per hour for registration
        if (!checkRateLimit($ip, $email, 3, 60)) {
            logLoginAttempt($ip, $email, null, 0);
            sendJsonError('Too many registration attempts. Please try again later.');
        }

        $fname   = trim($_POST['first_name'] ?? '');
        $lname   = trim($_POST['last_name'] ?? '');
        $phone   = trim($_POST['phone_num'] ?? '');
        $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
        $role    = $_POST['role'] ?? 'user';
        $barangay_id = isset($_POST['barangay_id']) && is_numeric($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;

        // Basic validation
        if (empty($fname) || empty($lname) || empty($email) || empty($_POST['password']) || empty($phone)) {
            sendJsonError('All fields are required.');
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users_tbl WHERE email = ?");
        if (!$stmt) {
            sendJsonError('Database error: prepare failed', $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            sendJsonError('Email is already registered!');
        }
        $stmt->close();

        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users_tbl (fname, lname, email, phone_no, password, role, barangay_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            sendJsonError('Database error: prepare insert failed', $conn->error);
        }
        $stmt->bind_param("ssssssi", $fname, $lname, $email, $phone, $password, $role, $barangay_id);
        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;
            logLoginAttempt($ip, $email, $newUserId, 1); // log success

            // Auto-login
            $_SESSION['first_name'] = $fname;
            $_SESSION['last_name']  = $lname;
            $_SESSION['email']      = $email;
            $_SESSION['role']       = $role;
            $_SESSION['user_id']    = $newUserId;
            logAudit('user_registered', ['user_id' => $newUserId]);

            // Send welcome email (best effort)
            require_once __DIR__ . '/email_helper.php';
            $templatePath = __DIR__ . '/welcome_template.html';
            if (file_exists($templatePath)) {
                $emailBody = file_get_contents($templatePath);
                $fullName = $fname . ' ' . $lname;
                $accountUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/greentrace/profile.php';
                $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/greentrace/';
                $emailBody = str_replace('Hello, <span class="highlight">Franz Harvey Bautista</span>!', 'Hello, <span class="highlight">' . htmlspecialchars($fullName) . '</span>!', $emailBody);
                $emailBody = str_replace('[[ACCOUNT_URL]]', $accountUrl, $emailBody);
                $emailBody = str_replace('[[BASE_URL]]', $baseUrl, $emailBody);
                $emailBody = str_replace('[[IMAGE_URL]]', 'https://i.postimg.cc/90NN9vxj/undraw-celebration-wtm8.png', $emailBody);
                sendEmail($email, 'Welcome to GreenTrace!', $emailBody);
            }

            echo json_encode(['success' => true, 'message' => "Account created successfully! Welcome, $fname!"]);
        } else {
            sendJsonError('Database error: ' . $stmt->error);
        }
        $stmt->close();
        exit;
    } catch (Exception $e) {
        sendJsonError('Server error. Please try again.', $e->getMessage());
    }
}

//  SIGN IN (AJAX) 
if (isset($_POST['sign-in'])) {
    try {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            sendJsonError('Please enter email and password.');
        }

        // Rate limit: 5 attempts per 15 minutes
        if (!checkRateLimit($ip, $email, 5, 15)) {
            logLoginAttempt($ip, $email, null, 0);
            sendJsonError('Too many login attempts. Please try again later.');
        }

        // Check user
        $stmt = $conn->prepare("SELECT id, fname, lname, email, password, role FROM users_tbl WHERE email = ? AND archived = 0");
        if (!$stmt) {
            sendJsonError('Database error: prepare failed', $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                logLoginAttempt($ip, $email, $user['id'], 1);
                clearLoginAttempts($ip);

                $_SESSION['first_name'] = $user['fname'];
                $_SESSION['last_name']  = $user['lname'];
                $_SESSION['email']      = $user['email'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['user_id']    = $user['id'];
                logAudit('user_logged_in', ['user_id' => $user['id']]);
                echo json_encode(['success' => true, 'message' => "Welcome back, " . $user['fname'] . "!"]);
                exit;
            }
        }

        // Login failed
        logLoginAttempt($ip, $email, null, 0);
        sendJsonError('Incorrect email or password.');
    } catch (Exception $e) {
        sendJsonError('Server error. Please try again.', $e->getMessage());
    }
}

// If no action is set
sendJsonError('Invalid request.');