<?php
require_once "../config/db.php";
session_start();

if($_SERVER["REQUEST_METHOD"] == "POST"){

$email = $_POST['email'];
$password = $_POST['password'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if($user && $password === $user['password']){

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['role'] = $user['role'];
$_SESSION['name'] = $user['name'];

header("Location: ../index.php");
exit;

}else{

$_SESSION['error'] = "Invalid credentials";
header("Location: login.php");
exit;

}

}
?>
