<?php
session_start();
require_once "../config/db.php";

if($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        
        header("Location: dashboard.php");
        exit();
    } else {
        $_SESSION['admin_error'] = "Invalid admin credentials.";
        header("Location: admin-login.php");
        exit();
    }
} else {
    header("Location: admin-login.php");
    exit();
}
?>