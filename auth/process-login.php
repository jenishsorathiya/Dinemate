<?php
require_once "../config/db.php";
session_start();

// Prevent session fixation
session_regenerate_id(true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Input validation
    if (!isset($_POST['email']) || !isset($_POST['password'])) {
        $_SESSION['error'] = "Email and password are required";
        header("Location: login.php");
        exit;
    }

    // Sanitize and trim inputs
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format";
        header("Location: login.php");
        exit;
    }

    // Validate password is not empty
    if (empty($password)) {
        $_SESSION['error'] = "Password is required";
        header("Location: login.php");
        exit;
    }

    // Validate password length (should be reasonable)
    if (strlen($password) > 255) {
        $_SESSION['error'] = "Invalid password";
        header("Location: login.php");
        exit;
    }

    try {
        // Query database for user
        $stmt = $pdo->prepare("SELECT user_id, email, password, role, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify user exists and password is correct using password_verify()
        if ($user) {
            // Try password_verify first (for hashed passwords)
            $password_valid = password_verify($password, $user['password']);
            
            // Fallback to direct comparison for plain text passwords (legacy)
            if (!$password_valid && $password === $user['password']) {
                $password_valid = true;
            }
            
            if ($password_valid) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['logged_in'] = true;

                // Optional: Log successful login (for audit trail)
                // logLoginAttempt($user['user_id'], 'success');

                header("Location: ../index.php");
                exit;
            }
        }
        
        // Invalid credentials
        $_SESSION['error'] = "Invalid email or password";
        // Optional: Log failed attempt
        // logLoginAttempt($email, 'failed');
        header("Location: login.php");
        exit;

    } catch (PDOException $e) {
        // Log database error securely (don't expose to user)
        error_log("Login database error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred. Please try again later.";
        header("Location: login.php");
        exit;
    }
} else {
    // Not a POST request
    header("Location: login.php");
    exit;
}
?>
