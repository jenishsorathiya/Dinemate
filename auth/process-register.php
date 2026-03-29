<?php
require_once "../config/db.php";
session_start();

// Prevent session fixation
session_regenerate_id(true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Input validation and sanitization
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm)) {
        $_SESSION['error'] = "All fields are required";
        header("Location: register.php");
        exit;
    }

    // Validate name (letters, spaces, hyphens, apostrophes only)
    if (!preg_match("/^[a-zA-Z\s\-']+$/", $name)) {
        $_SESSION['error'] = "Name can only contain letters, spaces, hyphens, and apostrophes";
        header("Location: register.php");
        exit;
    }

    // Validate name length
    if (strlen($name) < 2 || strlen($name) > 50) {
        $_SESSION['error'] = "Name must be between 2 and 50 characters";
        header("Location: register.php");
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header("Location: register.php");
        exit;
    }

    // Validate email length
    if (strlen($email) > 100) {
        $_SESSION['error'] = "Email is too long";
        header("Location: register.php");
        exit;
    }

    // Validate phone (basic format: digits, spaces, hyphens, parentheses)
    if (!preg_match("/^[0-9\s\-\(\)\+]+$/", $phone)) {
        $_SESSION['error'] = "Phone number can only contain digits, spaces, hyphens, parentheses, and plus signs";
        header("Location: register.php");
        exit;
    }

    // Validate phone length
    if (strlen($phone) < 10 || strlen($phone) > 20) {
        $_SESSION['error'] = "Phone number must be between 10 and 20 characters";
        header("Location: register.php");
        exit;
    }

    /* CHECK PASSWORD STRENGTH */
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long";
        header("Location: register.php");
        exit;
    }

    // Check for common weak passwords (optional but recommended)
    $weak_passwords = ['password', '123456', '123456789', 'qwerty', 'abc123', 'password123'];
    if (in_array(strtolower($password), $weak_passwords)) {
        $_SESSION['error'] = "Please choose a stronger password";
        header("Location: register.php");
        exit;
    }

    /* CHECK PASSWORD MATCH */
    if ($password !== $confirm) {
        $_SESSION['error'] = "Passwords do not match";
        header("Location: register.php");
        exit;
    }

    try {
        /* CHECK IF EMAIL EXISTS */
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Email already registered";
            header("Location: register.php");
            exit;
        }

        /* HASH PASSWORD */
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        /* INSERT USER */
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $name,
            $email,
            $phone,
            $hashed_password,
            "customer"
        ]);

        $_SESSION['success'] = "Registration successful. Please login.";

        header("Location: login.php");
        exit;

    } catch (PDOException $e) {
        // Log database error securely
        error_log("Registration database error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred during registration. Please try again later.";
        header("Location: register.php");
        exit;
    }

} else {
    // Not a POST request
    header("Location: register.php");
    exit;
}
?>
