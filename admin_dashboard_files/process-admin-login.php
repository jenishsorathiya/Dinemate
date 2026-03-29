<?php
require_once __DIR__ . '/../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin-login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    $_SESSION['admin_error'] = 'Please enter both email and password.';
    header('Location: admin-login.php');
    exit();
}

$stmt = $pdo->prepare('SELECT user_id, name, email, password, role FROM users WHERE email = ? AND role = ? LIMIT 1');
$stmt->execute([$email, 'admin']);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$valid = false;
if ($admin) {
    // Supports both hashed passwords and older plain-text passwords.
    if (password_get_info($admin['password'])['algo'] !== null) {
        $valid = password_verify($password, $admin['password']);
    } else {
        $valid = hash_equals((string)$admin['password'], $password);
    }
}

if (!$valid) {
    $_SESSION['admin_error'] = 'Invalid admin credentials.';
    header('Location: admin-login.php');
    exit();
}

session_regenerate_id(true);
$_SESSION['user_id'] = $admin['user_id'];
$_SESSION['role'] = $admin['role'];
$_SESSION['name'] = $admin['name'];

header('Location: dashboard.php');
exit();
?>
