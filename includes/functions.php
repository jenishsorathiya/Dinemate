<?php
// existing functions
function redirect($location) {
    header("Location: $location");
    exit();
}

function sanitize($data) {
    return htmlspecialchars(trim($data));
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

// ===== NEW FUNCTIONS TO ADD =====

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user name
function getCurrentUserName() {
    return $_SESSION['name'] ?? 'Guest';
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Require login (redirects if not logged in)
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect("/Dinemate/auth/login.php");
    }
}

// Require admin access (redirects if not admin)
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect("/Dinemate/auth/login.php");
    }
}

// Require customer access (redirects if not customer)
function requireCustomer() {
    requireLogin();
    if (!isCustomer()) {
        redirect("/Dinemate/auth/login.php");
    }
}

// Set flash message (temporary message that disappears after one page load)
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,  // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

// Get flash message and clear it
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Display flash message as HTML
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

// Logout function
function logout() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
}
?>