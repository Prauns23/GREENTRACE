<?php
require_once 'init_session.php';
require_once 'config.php';

// Sign Up
if (isset($_POST['sign-up'])) {
    $fname   = $_POST['first_name'];
    $lname   = $_POST['last_name'];
    $email   = $_POST['email'];
    $phone   = $_POST['phone_num'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role    = $_POST['role'] ?? 'user';

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
        $stmt->execute();
        $_SESSION['register_success'] = "Account created successfully!";
    }
    header("Location: index.php");
    exit();
}


// Sign In
if (isset($_POST['sign-in'])) {
    $email    = $_POST['email'];
    $password = $_POST['password'];

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
