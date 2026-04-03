<?php 
session_start(); 
?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="../assets/css/dashboard-theme.css" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: #f5f7fb;
}

.auth-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 28px;
}
.auth-box {
    width: 100%;
    max-width: 1080px;
    background: #ffffff;
    border: 1px solid #e7ecf3;
    border-radius: 20px;
    display: flex;
    overflow: hidden;
    box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08);
}

.auth-left {
    flex: 1;
    padding: 56px;
}

.auth-left h2 {
    font-size: 28px;
    font-weight: 700;
    color: #162033;
    margin-bottom: 10px;
}

.auth-left p,
.auth-left .text-muted {
    color: #69758b !important;
}

.auth-right {
    flex: 1;
    background: url('https://images.unsplash.com/photo-1552566626-52f8b828add9') center/cover no-repeat;
    position: relative;
}

.auth-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(22, 32, 51, 0.22), rgba(22, 32, 51, 0.62));
}

.auth-right-content {
    position: absolute;
    bottom: 40px;
    left: 40px;
    color: white;
}
.form-control {
    border-radius: 12px;
    padding: 13px 14px;
}
.btn-main {
    background: #1d2840;
    border: 1px solid #1d2840;
    border-radius: 12px;
    padding: 12px;
    font-weight: 600;
    color: #ffffff;
    transition: 0.18s ease;
}

.btn-main:hover {
    background: #141d31;
    border-color: #141d31;
    transform: translateY(-1px);
}

.password-wrapper {
    position: relative;
}
.toggle-password {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
}

.eye-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 18px;
    opacity: 0.6;
}

.eye-icon:hover {
    opacity: 1;
}

.divider {
    text-align: center;
    margin: 20px 0;
}

.social-btn {
    border-radius: 12px;
     border: 1px solid #d9e1ec;
     padding: 10px 12px;
    background: white;
    transition: 0.3s;
    font-weight: 500;
    display: flex;            
    align-items: center;       
    justify-content: center;   
    gap: 10px;  
}

.social-btn:hover {
    background: #f8fafc;
    transform: translateY(-2px);
}

@media (max-width: 991px) {
    .auth-box {
        flex-direction: column;
    }

    .auth-right {
        min-height: 240px;
    }

    .auth-left {
        padding: 28px;
    }
}

</style>

</head>
<body>
<div class="auth-wrapper">
    <div class="auth-box">
        <div class="auth-left"> 
            
            <h2 class="mb-3">Welcome Back</h2>
            <p class="mb-4">Login to manage your reservations.</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="process-login.php" class="form">
                <div class="mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                </div>
                 <div class="mb-3 password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
                    <span class="eye-icon" onclick="togglePassword()">👁</span>
                </div>
                
                <button class="btn btn-main w-100">Login</button>
            </form>
            
            <div class="divider">OR</div>
            
            <div class="d-grid gap-2 mb-3">
                <button class="social-btn" onclick="googleLogin()">
                    <img src="https://img.icons8.com/color/20/google-logo.png" alt="google">
                    Continue with Google
                </button>
                <button class="social-btn" onclick="appleLogin()">
                    <img src="https://img.icons8.com/ios-filled/20/000000/mac-os.png">
                    Continue with Apple
                </button>
            </div>
            <div class="text-center mt-3">
                Don't have an account? 
                <a href="register.php">Create Account</a>
            </div>
        </div>
        
        <div class="auth-right">
            <div class="auth-overlay"></div>
            <div class="auth-right-content">
                <h3>Reserve in Seconds</h3>
                <p>Modern dining starts here.</p>
            </div>
        </div>
        
    </div>
</div>
<script>
function togglePassword() {
    const pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
}

function googleLogin() {
    alert("Google OAuth integration coming soon!");
}

function appleLogin() {
    alert("Apple Login integration coming soon!");
}
</script>
</body>
</html>
