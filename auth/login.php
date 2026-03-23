<?php session_start(); ?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

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


</head>
<body>
<div class="auth-wrapper">
     <h2 class="mb-3">Welcome Back</h2>
            <p class="mb-4">Login to manage your reservations.</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="process-login.php">
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
                    <i class="fab fa-apple"></i>
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
