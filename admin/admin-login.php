<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

$email = sanitize($_POST['email']);
$password = $_POST['password'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role='admin'");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $password === $user['password']) {

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role'];

    header("Location: dashboard.php");
    exit;

} else {
    $error = "Invalid admin credentials.";
}

} else {
$error = "Admin account not found.";
}
?>
<!DOCTYPE html>
<html>
<head>
<title>DineMate Admin Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="../assets/css/dashboard-theme.css" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: #f5f7fb;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.admin-container {
    width: 100%;
    max-width: 1050px;
    display: flex;
    border-radius: 20px;
    overflow: hidden;
    background: white;
    border: 1px solid #e7ecf3;
    box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
}

.admin-left {
    flex: 1;
    padding: 56px;
}

.admin-left h2 {
    font-size: 28px;
    font-weight: 700;
    color: #162033;
    margin-bottom: 8px;
}

.admin-left p {
    color: #69758b;
    margin-bottom: 30px;
}

.form-control {
    border-radius: 12px;
    padding: 13px 14px;
    border: 1px solid #d9e1ec;
    background: #ffffff;
    transition: 0.3s;
}

.form-control:focus {
    background: white;
    box-shadow: 0 0 0 4px rgba(29, 40, 64, 0.12);
    border-color: #bdc9da;
}

.btn-login {
    background: #1d2840;
    border: 1px solid #1d2840;
    color: #ffffff;
    border-radius: 12px;
    padding: 14px;
    font-weight: 600;
    transition: 0.18s ease;
}

.btn-login:hover {
    background: #141d31;
    border-color: #141d31;
    transform: translateY(-1px);
}

.password-wrapper {
    position: relative;
}

.eye {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 18px;
    opacity: 0.6;
}

.eye:hover {
    opacity: 1;
}

.admin-right {
    flex: 1;
    background: url("https://images.unsplash.com/photo-1559339352-11d035aa65de") center/cover no-repeat;
    position: relative;
}

.admin-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(22, 32, 51, 0.22), rgba(22, 32, 51, 0.68));
}

@media (max-width: 991px) {
    .admin-container {
        flex-direction: column;
        margin: 20px;
    }

    .admin-left {
        padding: 28px;
    }

    .admin-right {
        min-height: 240px;
    }
}

.admin-text {
    position: relative;
    color: white;
    z-index: 2;
    padding: 40px;
    bottom: 0;
    position: absolute;
}

.admin-text h3 {
    font-weight: 600;
}

.alert {
    border-radius: 12px;
}    
</style>
</head>
<body>

<div class="admin-container">

<div class="admin-left">

<h2>Admin Login</h2>
<p>Access the DineMate management dashboard.</p>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

<div class="mb-3">
<input type="email" name="email" class="form-control" placeholder="Admin Email" required>
</div>

<div class="mb-4 password-wrapper">

<input type="password" name="password" id="password" class="form-control" placeholder="Password" required>

<span class="eye" onclick="togglePassword()">👁</span>

</div>

<button class="btn btn-login w-100">Login</button>

</form>

</div>


<div class="admin-right">

<div class="admin-overlay"></div>

<div class="admin-text">
<h3>DineMate Admin</h3>
<p>Manage reservations, tables and customers efficiently.</p>
</div>

</div>

</div>

<script>

function togglePassword(){

const pass=document.getElementById("password");

if(pass.type==="password"){
pass.type="text";
}else{
pass.type="password";
}

}

</script>

</body>
