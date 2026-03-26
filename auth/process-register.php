<?php
require_once "../config/db.php";
session_start();

if($_SERVER["REQUEST_METHOD"] == "POST"){

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$password = trim($_POST['password']);
$confirm = trim($_POST['confirm_password']);

/* CHECK PASSWORD MATCH */

if($password !== $confirm){
$_SESSION['error'] = "Passwords do not match";
header("Location: register.php");
exit;
}

/* CHECK IF EMAIL EXISTS */

$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email=?");
$stmt->execute([$email]);

if($stmt->rowCount() > 0){
$_SESSION['error'] = "Email already registered";
header("Location: register.php");
exit;
}

/* INSERT USER */

$stmt = $pdo->prepare("
INSERT INTO users (name,email,phone,password,role)
VALUES (?,?,?,?,?)
");

$stmt->execute([
$name,
$email,
$phone,
$password,
"customer"
]);

$_SESSION['success'] = "Registration successful. Please login.";

header("Location: login.php");
exit;

}
?>
