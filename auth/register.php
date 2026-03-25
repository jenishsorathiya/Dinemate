<?php session_start(); ?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

</head>    

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

</body>
</html> 

