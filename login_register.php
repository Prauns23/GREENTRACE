<?php
require_once 'init_session.php';
require_once 'config.php';

// CSRF validation for POST request (currently commented out for testing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    error_log('=== CSRF Validation Debug ===');
    error_log('POST csrf_token: ' . substr($postToken, 0, 16) . (strlen($postToken) > 16 ? '...' : ''));
    error_log('Session token: ' . substr($sessionToken, 0, 16) . (strlen($sessionToken) > 16 ? '...' : ''));
    error_log('SessionID: ' . session_id());
    error_log('Session file: ' . session_save_path() . '/sess_' . session_id());
    error_log('POST token present: ' . (isset($_POST['csrf_token']) ? 'YES' : 'NO'));
    error_log('Session token present: ' . (isset($_SESSION['csrf_token']) ? 'YES' : 'NO'));

    // if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    //     error_log('CSRF validation FAILED');
    //     $_SESSION['csrf_error'] = 'Invalid CSRF token. Please refresh the page and try again.';
    //     $_SESSION['active_form'] = isset($_POST['sign-in']) ? 'sign-in' : 'sign-up';
    //     header("Location: index.php");
    //     exit();
    // }
    // error_log('CSRF validation PASSED');
}

// Sign Up
if (isset($_POST['sign-up'])) {
    $fname   = trim($_POST['first_name'] ?? '');
    $lname   = trim($_POST['last_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone_num'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $role    = $_POST['role'] ?? 'user';

    // Basic validation
    if (empty($fname) || empty($lname) || empty($email) || empty($_POST['password']) || empty($phone)) {
        $_SESSION['register_error'] = 'All fields, including phone number, are required.';
        $_SESSION['active_form'] = 'sign-up';
        header("Location: index.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT email FROM users_tbl WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['register_error'] = 'Email is already registered!';
        $_SESSION['active_form'] = 'sign-up';
    } else {
        $stmt = $conn->prepare("INSERT INTO users_tbl (fname, lname, email, phone_no, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $fname, $lname, $email, $phone, $password, $role);
        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;

            // Auto-login: set session variables
            $_SESSION['first_name'] = $fname;
            $_SESSION['last_name']  = $lname;
            $_SESSION['email']      = $email;
            $_SESSION['role']       = $role;
            $_SESSION['user_id']    = $newUserId;
            $_SESSION['register_success'] = "Account created successfully! Welcome, $fname!";

            // Send welcome email
            require_once __DIR__ . '/email_helper.php';
            $templatePath = __DIR__ . '/welcome_template.html';
            if (file_exists($templatePath)) {
                $emailBody = file_get_contents($templatePath);
                $fullName = $fname . ' ' . $lname;
                $accountUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/greentrace/my_applications.php';
                $baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/greentrace/';

                $emailBody = str_replace('Hello, <span class="highlight">Franz Harvey Bautista</span>!', 'Hello, <span class="highlight">' . htmlspecialchars($fullName) . '</span>!', $emailBody);
                $emailBody = str_replace('[[ACCOUNT_URL]]', $accountUrl, $emailBody);
                $emailBody = str_replace('[[BASE_URL]]', $baseUrl, $emailBody);
                $emailBody = str_replace('[[IMAGE_URL]]', 'https://i.postimg.cc/90NN9vxj/undraw-celebration-wtm8.png', $emailBody);

                sendEmail($email, 'Welcome to GreenTrace!', $emailBody);
            } else {
                error_log("Welcome email template not found: $templatePath");
            }
        } else {
            $_SESSION['register_error'] = 'Database error. Please try again.';
            $_SESSION['active_form'] = 'sign-up';
        }
    }
    header("Location: index.php");
    exit();
}

// Sign In
if (isset($_POST['sign-in'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter email and password.';
        $_SESSION['active_form'] = 'sign-in';
        header("Location: index.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users_tbl WHERE email = ? AND archived = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['first_name'] = $user['fname'];
            $_SESSION['last_name']  = $user['lname'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['login_success'] = "Welcome back, " . $user['fname'] . "!";
            header("Location: index.php");
            exit();
        }
    }

    // Login failed
    $_SESSION['login_error'] = 'Incorrect email or password or account deactivated.';
    $_SESSION['active_form'] = 'sign-in';
    header("Location: index.php");
    exit();
}
