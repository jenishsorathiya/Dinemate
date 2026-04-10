<?php session_start(); ?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="../assets/css/app.css" rel="stylesheet">
<style>
body { margin: 0; font-family: 'Inter', sans-serif; background: #f5f7fb; }
.auth-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 28px; }
.auth-box { width: 100%; max-width: 1100px; background: #ffffff; border: 1px solid #e7ecf3; border-radius: 16px; overflow: hidden; display: flex; box-shadow: 0 16px 40px rgba(15,23,42,0.08); }
.auth-left { flex: 1; padding: 48px 52px; }
.auth-left h2 { font-size: 24px; font-weight: 700; color: #162033; margin: 0 0 6px; }
.auth-left > p { color: #69758b; margin: 0 0 24px; font-size: 14px; }
.auth-left p, .auth-left a, #emailMsg, #matchMsg { color: #69758b; }
.auth-right { flex: 1; background: url('https://images.unsplash.com/photo-1528605248644-14dd04022da1') center/cover no-repeat; position: relative; }
.auth-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(22,32,51,0.22), rgba(22,32,51,0.64)); }
.auth-right-content { position: absolute; bottom: 36px; left: 36px; color: white; }
.auth-right-content h3 { margin: 0 0 6px; font-size: 20px; font-weight: 700; }
.auth-right-content p { margin: 0; font-size: 14px; opacity: 0.85; }
.form-control { border-radius: 8px; padding: 11px 13px; border: 1px solid #d9e1ec; background: #ffffff; width: 100%; font-family: inherit; font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s; }
.form-control:focus { outline: none; box-shadow: 0 0 0 3px rgba(29,40,64,0.10); border-color: #b0bdd0; background: white; }
.btn-main { background: #1d2840; border: 1px solid #1d2840; border-radius: 8px; padding: 11px 14px; font-weight: 600; color: #ffffff; transition: opacity 0.15s; font-family: inherit; font-size: 14px; cursor: pointer; }
.btn-main:hover { opacity: 0.86; }
.password-wrapper { position: relative; }
.eye { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #8a9ab8; font-size: 14px; }
.eye:hover { color: #4a5568; }
.strength { height: 4px; border-radius: 3px; margin-top: 6px; width: 0%; transition: 0.3s; }
.strength.bg-danger { background: #ef4444; }
.strength.bg-warning { background: #f59e0b; }
.strength.bg-success { background: #22c55e; }
.toast-box { position: fixed; top: 24px; right: 24px; background: #1d2840; color: white; padding: 12px 18px; border-radius: 8px; display: none; z-index: 999; font-size: 14px; box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
.success-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; color: white; font-size: 22px; font-weight: 700; z-index: 9999; backdrop-filter: blur(4px); }
small { font-size: 12px; margin-top: 4px; display: block; }
a { color: #3d6bdf; text-decoration: none; font-weight: 500; }
a:hover { text-decoration: underline; }
@media (max-width: 991px) { .auth-box { flex-direction: column; } .auth-right { min-height: 220px; } .auth-left { padding: 28px; } }
</style>

</head>

<body>

<div class="toast-box" id="toast"></div>
<div class="success-overlay" id="successAnim">Account Created</div>

<div class="auth-wrapper">
    <div class="auth-box">
        
        <div class="auth-left">
            <h2>Create Account</h2>
            <p class="mb-4">Join DineMate and book effortlessly.</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="process-register.php" onsubmit="return validateForm()">
                <div class="mb-3">
                    <input type="text" name="name" class="form-control" placeholder="Full Name" required>
                </div>
                
                <div class="mb-3">
                    <input type="email" id="email" name="email" class="form-control" placeholder="Email Address" required onkeyup="checkEmail()">
                    <small id="emailMsg"></small>
                </div>
                
                <div class="mb-3">
                    <input type="text" name="phone" class="form-control" placeholder="Phone Number">
                </div>
                
                <div class="mb-3 password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Password" required onkeyup="checkStrength()">
                    <span class="eye" onclick="togglePassword()"><i class="fa-regular fa-eye"></i></span>
                    <div id="strengthBar" class="strength bg-danger"></div>
                </div>
                
                <div class="mb-3">
                    <input type="password" id="confirm" name="confirm_password" class="form-control" placeholder="Confirm Password" onkeyup="matchPassword()" required>
                    <small id="matchMsg"></small>
                </div>
                
                <button class="btn btn-main w-100 mb-3">Register</button>
            </form>
            
            <div class="text-center">
                Already have an account? 
                <a href="login.php">Sign In</a>
            </div>
        </div>
        
        <div class="auth-right">
            <div class="auth-overlay"></div>
            <div class="auth-right-content">
                <h3>Premium Dining Experience</h3>
                <p>Reserve smart. Dine better.</p>
            </div>
        </div>
        
    </div>
</div>

<script>
function showToast(msg) {
    const toast = document.getElementById("toast");
    toast.innerText = msg;
    toast.style.display = "block";
    setTimeout(() => {
        toast.style.display = "none";
    }, 3000);
}

function togglePassword() {
    const pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
}

function checkStrength() {
    const pass = document.getElementById("password").value;
    const bar = document.getElementById("strengthBar");
    
    if (pass.length === 0) {
        bar.style.width = "0%";
    } else if (pass.length < 6) {
        bar.style.width = "30%";
        bar.className = "strength bg-danger";
    } else if (pass.length < 10) {
        bar.style.width = "60%";
        bar.className = "strength bg-warning";
    } else {
        bar.style.width = "100%";
        bar.className = "strength bg-success";
    }
}

function matchPassword() {
    const p = document.getElementById("password").value;
    const c = document.getElementById("confirm").value;
    const msg = document.getElementById("matchMsg");
    
    if (c === "") {
        msg.innerHTML = "";
        return;
    }
    
    if (p === c) {
        msg.innerHTML = "&#10003; Passwords match";
        msg.style.color = "green";
    } else {
        msg.innerHTML = "&#10007; Passwords do not match";
        msg.style.color = "red";
    }
}

function checkEmail() {
    const email = document.getElementById("email").value;
    const msg = document.getElementById("emailMsg");
    
    if (email === "") {
        msg.innerHTML = "";
        return;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        msg.innerHTML = "&#10007; Invalid email format";
        msg.style.color = "red";
        return;
    }
    
    msg.innerHTML = "&#10003; Email format valid";
    msg.style.color = "green";
}

function validateForm() {
    const p = document.getElementById("password").value;
    const c = document.getElementById("confirm").value;
    const email = document.getElementById("email").value;
    const name = document.querySelector("input[name='name']").value;
    
    if (name.trim() === "") {
        showToast("Please enter your full name");
        return false;
    }
    
    if (email === "") {
        showToast("Please enter your email");
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showToast("Please enter a valid email address");
        return false;
    }
    
    if (p.length < 6) {
        showToast("Password must be at least 6 characters");
        return false;
    }
    
    if (p !== c) {
        showToast("Passwords do not match");
        return false;
    }
    
    const successAnim = document.getElementById("successAnim");
    successAnim.style.display = "flex";
    
    setTimeout(() => {
        successAnim.style.display = "none";
    }, 2000);
    
    return true;
}
</script>
</body>
</html>