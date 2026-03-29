<?php 
session_start(); 
?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(-45deg, #f4d58d, #f6f1e9, #e9edc9, #f4b400);
    background-size: 400% 400%;
    animation: gradientBG 12s ease infinite;
}
@keyframes gradientBG {
    0% {background-position: 0% 50%;}
    50% {background-position: 100% 50%;}
    100% {background-position: 0% 50%;}
}

.auth-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.auth-box {
    width: 100%;
    max-width: 1000px;
    background: rgba(255,255,255,0.7);
    backdrop-filter: blur(15px);
    border-radius: 25px;
    display: flex;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,0.15);
}

@keyframes fadeIn {
    from {opacity:0; transform:translateY(20px);}
    to {opacity:1; transform:translateY(0);}
}

.auth-left {
    flex: 1;
    padding: 50px;
}

.auth-left h2 {
    font-weight: 600;
}

.auth-right {
    flex: 1;
    background: url('https://images.unsplash.com/photo-1552566626-52f8b828add9') center/cover no-repeat;
    position: relative;
}

.auth-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
}

.auth-right-content {
    position: absolute;
    bottom: 40px;
    left: 40px;
    color: white;
}
.form-control {
    border-radius: 50px;
    padding: 12px 20px;
}
.btn-main {
    background: #f4b400;
    border: none;
    border-radius: 50px;
    padding: 12px;
    font-weight: 600;
    transition: 0.3s;
}

.btn-main:hover {
    background: #d39e00;
    transform: scale(1.03);
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
   border-radius: 50px;
    border: 1px solid #ccc;
    padding: 10px;
    background: white;
    transition: 0.3s;
    font-weight: 500;
    display: flex;            
    align-items: center;       
    justify-content: center;   
    gap: 10px;  
}

.social-btn:hover {
    background: #f5f5f5;
    transform: translateY(-2px);
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
