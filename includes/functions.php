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

function ensureBookingRequestColumns($pdo) {
    $startTimeStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'start_time'");
    $startTimeExists = $startTimeStmt->rowCount() > 0;

    $endTimeStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'end_time'");
    $endTimeExists = $endTimeStmt->rowCount() > 0;

    $requestedStartStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'requested_start_time'");
    $requestedStartExists = $requestedStartStmt->rowCount() > 0;

    $requestedEndStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'requested_end_time'");
    $requestedEndExists = $requestedEndStmt->rowCount() > 0;

    $nameOverrideStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'customer_name_override'");
    $nameOverrideExists = $nameOverrideStmt->rowCount() > 0;

    if (!$requestedStartExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN requested_start_time TIME DEFAULT NULL AFTER end_time");
    }

    if (!$requestedEndExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN requested_end_time TIME DEFAULT NULL AFTER requested_start_time");
    }

    if (!$nameOverrideExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN customer_name_override VARCHAR(100) DEFAULT NULL AFTER user_id");
    }

    if ($startTimeExists) {
        $pdo->exec("UPDATE bookings SET requested_start_time = start_time WHERE requested_start_time IS NULL AND start_time IS NOT NULL");
    }

    if ($endTimeExists) {
        $pdo->exec("UPDATE bookings SET requested_end_time = end_time WHERE requested_end_time IS NULL AND end_time IS NOT NULL");
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