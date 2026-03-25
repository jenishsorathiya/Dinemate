<?php session_start(); ?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>    

<body>

<div class="toast-box" id="toast"></div>
<div class="success-overlay" id="successAnim">Account Created Successfully 🎉</div>

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
                    <span class="eye" onclick="togglePassword()">👁</span>
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
        msg.innerHTML = "✓ Passwords match";
        msg.style.color = "green";
    } else {
        msg.innerHTML = "✗ Passwords do not match";
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
    
    // Email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        msg.innerHTML = "✗ Invalid email format";
        msg.style.color = "red";
        return;
    }
    
    msg.innerHTML = "✓ Email format valid";
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
    
    // Show success animation
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