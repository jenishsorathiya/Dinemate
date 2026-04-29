<?php
session_start();
$appCssVersion = (string) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: time());
?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="../assets/css/app.css?v=<?= htmlspecialchars($appCssVersion, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<style>
body { margin: 0; font-family: 'DM Sans', sans-serif; background: var(--dm-bg); }
.auth-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 28px; }
.auth-box { width: 100%; max-width: 1100px; background: var(--dm-surface); border: 1px solid var(--dm-border); border-radius: var(--dm-radius-lg); overflow: hidden; display: flex; box-shadow: var(--dm-shadow-md); }
.auth-left { flex: 1; padding: 48px 52px; }
.auth-left h2 { font-size: 24px; font-weight: 700; color: var(--dm-text); margin: 0 0 6px; }
.auth-left > p { color: var(--dm-text-muted); margin: 0 0 24px; font-size: 14px; }
.auth-left p, .auth-left a, #emailMsg, #matchMsg { color: var(--dm-text-muted); }
.auth-right { flex: 1; background: url('https://images.unsplash.com/photo-1528605248644-14dd04022da1') center/cover no-repeat; position: relative; }
.auth-overlay { position: absolute; inset: 0; background: var(--dm-overlay-auth); }
.auth-right-content { position: absolute; bottom: 36px; left: 36px; color: var(--dm-white); }
.auth-right-content h3 { margin: 0 0 6px; font-size: 20px; font-weight: 700; }
.auth-right-content p { margin: 0; font-size: 14px; opacity: 0.85; }
.form-control { border-radius: var(--dm-radius-sm); padding: 10px 12px; border: 1px solid var(--dm-border-strong); background: var(--dm-surface); width: 100%; font-family: inherit; font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s; }
.form-control:focus { outline: none; box-shadow: var(--dm-focus-ring); border-color: var(--dm-border-strong); background: var(--dm-surface); }
.btn-main { background: var(--dm-accent-dark); border: 1px solid var(--dm-accent-dark); border-radius: var(--dm-radius-sm); padding: 10px 14px; font-weight: 600; color: var(--dm-surface); transition: background 0.15s; font-family: inherit; font-size: 14px; cursor: pointer; }
.btn-main:hover { background: var(--dm-accent-dark-hover); border-color: var(--dm-accent-dark-hover); color: var(--dm-surface); }
.password-wrapper { position: relative; }
.eye { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--dm-text-soft); font-size: 14px; }
.eye:hover { color: var(--dm-text-muted); }
.strength { height: 4px; border-radius: 3px; margin-top: 6px; width: 0%; transition: 0.3s; }
.strength.bg-danger { background: var(--dm-danger-strong); }
.strength.bg-warning { background: var(--dm-warning-strong); }
.strength.bg-success { background: var(--dm-success-strong); }
.toast-box { position: fixed; top: 24px; right: 24px; background: var(--dm-accent-dark); color: var(--dm-white); padding: 12px 18px; border-radius: var(--dm-radius-sm); display: none; z-index: 999; font-size: 14px; box-shadow: 0 8px 20px rgba(23,68,56,0.22); }
.success-overlay { position: fixed; inset: 0; background: rgba(18,54,45,0.58); display: none; align-items: center; justify-content: center; color: var(--dm-white); font-size: 22px; font-weight: 700; z-index: 9999; backdrop-filter: blur(4px); }
small { font-size: 12px; margin-top: 4px; display: block; }
a { color: var(--dm-link); text-decoration: none; font-weight: 500; }
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
