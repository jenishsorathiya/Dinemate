<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store the requested URL to redirect back after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: /Dinemate/auth/login.php");
    exit();
}
?>