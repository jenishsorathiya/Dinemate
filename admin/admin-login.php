<?php
require_once "../includes/functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isLoggedIn() && getCurrentUserRole() === 'admin') {
    redirect(appPath('admin/pages/analytics.php'));
}

$error = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_error']);
?>
<!DOCTYPE html>
<html>
<head>
<title>DineMate Admin Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="../assets/css/app.css" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: var(--dm-bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.admin-container {
    width: 100%;
    max-width: 1050px;
    display: flex;
    border-radius: var(--dm-radius-lg);
    overflow: hidden;
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    box-shadow: var(--dm-shadow-md);
}

.admin-left {
    flex: 1;
    padding: 56px;
}

.admin-left h2 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dm-text);
    margin-bottom: 8px;
}

.admin-left p {
    color: var(--dm-text-muted);
    margin-bottom: 30px;
}

.form-control {
    border-radius: var(--dm-radius-sm);
    padding: 10px 12px;
    border: 1px solid var(--dm-border-strong);
    background: var(--dm-surface);
    transition: 0.3s;
}

.form-control:focus {
    background: white;
    box-shadow: var(--dm-focus-ring);
    border-color: var(--dm-border-strong);
}

.btn-login {
    background: var(--dm-accent-dark);
    border: 1px solid var(--dm-accent-dark);
    color: var(--dm-surface);
    border-radius: var(--dm-radius-sm);
    padding: 10px 14px;
    font-weight: 600;
    transition: background 0.18s ease;
}

.btn-login:hover {
    background: var(--dm-accent-dark-hover);
    border-color: var(--dm-accent-dark-hover);
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
    border-radius: var(--dm-radius-sm);
}    
</style>
</head>
<body>

<div class="admin-container">

<div class="admin-left">

<h2>Admin Login</h2>
<p>Access the DineMate management analytics panel.</p>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" action="process-admin-login.php">

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

