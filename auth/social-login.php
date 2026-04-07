<?php
session_start();
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

if (!isset($_GET['provider'])) {
    header("Location: login.php");
    exit();
}

$provider = $_GET['provider'];

if ($provider === "google") {
    $email = "google_demo@dinemate.com";
    $name = "Google Demo User";
}
elseif ($provider === "apple") {
    $email = "apple_demo@dinemate.com";
    $name = "Apple Demo User";
}
else {
    header("Location: login.php");
    exit();
}

/* Check if exists */
$stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $password = password_hash("social123", PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (name,email,password,role)
        VALUES (?,?,?, 'customer')
    ");
    $stmt->execute([$name,$email,$password]);
    $user_id = $pdo->lastInsertId();
} else {
    $user_id = $user['user_id'];
}

storeUserSession([
    'user_id' => $user_id,
    'role' => 'customer',
    'name' => $name,
    'email' => $email,
]);

header("Location: " . getPostLoginRedirect('customer'));
exit();
