<?php
session_start();

// Check if already logged in as admin
if(isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: dashboard.php");
    exit();
}
$error = "";
?>
<!DOCTYPE html>
<html>
<head>
<title>DineMate Admin Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.admin-container {
    width: 100%;
    max-width: 1050px;
    display: flex;
    border-radius: 18px;
    overflow: hidden;
    background: white;
    box-shadow: 0 25px 60px rgba(0,0,0,0.35);
    animation: fadeIn 0.8s ease;
}

@keyframes fadeIn {
    from {opacity: 0; transform: translateY(20px);}
    to {opacity: 1; transform: translateY(0);}
}

.admin-left {
    flex: 1;
    padding: 60px;
}

.admin-left h2 {
    font-weight: 600;
    margin-bottom: 8px;
}

.admin-left p {
    color: #666;
    margin-bottom: 30px;
}

.form-control {
    border-radius: 40px;
    padding: 14px 20px;
    border: 1px solid #e5e7eb;
    background: #f4f4f4;
    transition: 0.3s;
}

.form-control:focus {
    background: white;
    box-shadow: 0 0 0 3px #f4b400;
    border-color: #f4b400;
}

.btn-login {
    background: #f4b400;
    border: none;
    border-radius: 40px;
    padding: 14px;
    font-weight: 600;
    transition: 0.3s;
}

.btn-login:hover {
    background: #e0a800;
    transform: scale(1.03);
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
    background: rgba(0,0,0,0.6);
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
        
        <?php if(isset($_SESSION['admin_error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['admin_error']; unset($_SESSION['admin_error']); ?></div>
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
function togglePassword() {
    const pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
}
</script>

</body>
</html>