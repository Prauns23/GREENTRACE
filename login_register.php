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

//  SIGN UP (AJAX) 
if (isset($_POST['sign-up'])) {
    $fname   = trim($_POST['first_name'] ?? '');
    $lname   = trim($_POST['last_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone_num'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $role    = $_POST['role'] ?? 'user';

    $response = [];

    // Basic validation
    if (empty($fname) || empty($lname) || empty($email) || empty($_POST['password']) || empty($phone)) {
        $response = ['success' => false, 'error' => 'All fields are required.'];
        echo json_encode($response);
        exit();
    }

    $stmt = $conn->prepare("SELECT email FROM users_tbl WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $response = ['success' => false, 'error' => 'Email is already registered!'];
        echo json_encode($response);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO users_tbl (fname, lname, email, phone_no, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $fname, $lname, $email, $phone, $password, $role);
    if ($stmt->execute()) {
        $newUserId = $conn->insert_id;

        // Auto-login
        $_SESSION['first_name'] = $fname;
        $_SESSION['last_name']  = $lname;
        $_SESSION['email']      = $email;
        $_SESSION['role']       = $role;
        $_SESSION['user_id']    = $newUserId;
        $response = ['success' => true, 'message' => "Account created successfully! Welcome, $fname!"];

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
        } else {
            error_log("Welcome email template not found: $templatePath");
        }
    } else {
        $response = ['success' => false, 'error' => 'Database error. Please try again.'];
    }
    echo json_encode($response);
    exit();
}

//  SIGN IN (AJAX) 
if (isset($_POST['sign-in'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $response = [];

    if (empty($email) || empty($password)) {
        $response = ['success' => false, 'error' => 'Please enter email and password.'];
        echo json_encode($response);
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
            $response = ['success' => true, 'message' => "Welcome back, " . $user['fname'] . "!"];
            echo json_encode($response);
            exit();
        }
    }

    // Login failed
    $response = ['success' => false, 'error' => 'Incorrect email or password.'];
    echo json_encode($response);
    exit();
}

// If no action is set, just return an error (should not happen)
echo json_encode(['success' => false, 'error' => 'Invalid request.']);
exit();
?>