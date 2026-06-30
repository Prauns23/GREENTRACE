<?php
require_once 'init_session.php';
require_once 'config.php';
require_once 'rate_limit.php'; // <-- ADD THIS

// Get client IP
$ip = $_SERVER['REMOTE_ADDR'];

//  SIGN UP (AJAX) 
if (isset($_POST['sign-up'])) {
    $email = trim($_POST['email'] ?? '');

    // Rate limit: 3 attempts per hour for registration
    if (!checkRateLimit($ip, $email, 3, 60)) {
        logLoginAttempt($ip, $email, null, 0);
        echo json_encode(['success' => false, 'error' => 'Too many registration attempts. Please try again later.']);
        exit;
    }

    $fname   = trim($_POST['first_name'] ?? '');
    $lname   = trim($_POST['last_name'] ?? '');
    $phone   = trim($_POST['phone_num'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $role    = $_POST['role'] ?? 'user';

    // Basic validation
    if (empty($fname) || empty($lname) || empty($email) || empty($_POST['password']) || empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit;
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users_tbl WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Email is already registered!']);
        exit;
    }
    $stmt->close();

    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users_tbl (fname, lname, email, phone_no, password, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $fname, $lname, $email, $phone, $password, $role);
    if ($stmt->execute()) {
        $newUserId = $conn->insert_id;
        logLoginAttempt($ip, $email, $newUserId, 1); // log success

        // Auto-login
        $_SESSION['first_name'] = $fname;
        $_SESSION['last_name']  = $lname;
        $_SESSION['email']      = $email;
        $_SESSION['role']       = $role;
        $_SESSION['user_id']    = $newUserId;

        // Send welcome email (optional)
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
        echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
    }
    exit;
}

//  SIGN IN (AJAX) 
if (isset($_POST['sign-in'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Please enter email and password.']);
        exit;
    }

    // Rate limit: 5 attempts per 15 minutes (by IP and email)
    if (!checkRateLimit($ip, $email, 5, 15)) {
        logLoginAttempt($ip, $email, null, 0);
        echo json_encode(['success' => false, 'error' => 'Too many login attempts. Please try again later.']);
        exit;
    }

    // Check user
    $stmt = $conn->prepare("SELECT id, fname, lname, email, password, role FROM users_tbl WHERE email = ? AND archived = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Log success
            logLoginAttempt($ip, $email, $user['id'], 1);
            clearLoginAttempts($ip); // optional: reset attempts after successful login

            $_SESSION['first_name'] = $user['fname'];
            $_SESSION['last_name']  = $user['lname'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['user_id']    = $user['id'];
            echo json_encode(['success' => true, 'message' => "Welcome back, " . $user['fname'] . "!"]);
            exit;
        }
    }

    // Login failed – log the failed attempt
    logLoginAttempt($ip, $email, null, 0);
    echo json_encode(['success' => false, 'error' => 'Incorrect email or password.']);
    exit;
}

// If no action is set
echo json_encode(['success' => false, 'error' => 'Invalid request.']);
exit;