<?php
require_once "../config/db.php";
session_start();

// Prevent session fixation
session_regenerate_id(true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Input validation
    if (!isset($_POST['email']) || !isset($_POST['password'])) {
        $_SESSION['admin_error'] = "Email and password are required";
        header("Location: admin-login.php");
        exit;
    }

    // Sanitize and trim inputs
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_error'] = "Invalid email format";
        header("Location: admin-login.php");
        exit;
    }

    // Validate password is not empty
    if (empty($password)) {
        $_SESSION['admin_error'] = "Password is required";
        header("Location: admin-login.php");
        exit;
    }

    // Validate password length (should be reasonable)
    if (strlen($password) > 255) {
        $_SESSION['admin_error'] = "Invalid password";
        header("Location: admin-login.php");
        exit;
    }

    try {
        // Query database for admin user using prepared statement
        $stmt = $pdo->prepare("SELECT user_id, email, password, role, name FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify user exists, is admin, and password is correct using password_verify()
        if ($user) {
            // Try password_verify first (for hashed passwords)
            $password_valid = password_verify($password, $user['password']);

            // Fallback to direct comparison for plain text passwords (legacy)
            if (!$password_valid && $password === $user['password']) {
                $password_valid = true;
            }

            if ($password_valid) {
                // Set session variables for admin
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                $_SESSION['admin_logged_in'] = true;

                // Optional: Log successful admin login (for audit trail)
                // logAdminLogin($user['user_id'], 'success');

                header("Location: bookings-management.php");
                exit;
            }
        }

        // Invalid credentials or not an admin
        $_SESSION['admin_error'] = "Invalid email or password";
        // Optional: Log failed admin login attempt
        // logAdminLogin($email, 'failed');
        header("Location: admin-login.php");
        exit;

    } catch (PDOException $e) {
        // Log database error securely (don't expose to user)
        error_log("Admin login database error: " . $e->getMessage());
        $_SESSION['admin_error'] = "An error occurred. Please try again later.";
        header("Location: /Dinemate/admin/bookings-management.php");
        exit;
    }
} else {
    // Not a POST request
    header("Location: admin-login.php");
    exit;
}
?>