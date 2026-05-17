<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
$appCssVersion = (string) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: time());
?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="../assets/css/app.css?v=<?= htmlspecialchars($appCssVersion, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: var(--dm-font-sans);
    background: var(--dm-bg);
}

.auth-wrapper {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.auth-container {
    width: 100%;
    max-width: 1100px;
    background: var(--dm-surface);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
    display: grid;
    grid-template-columns: 1fr 1fr;
}

/* Left Side - Form */
.auth-form-section {
    padding: 60px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    max-height: 100vh;
    overflow-y: auto;
}

.auth-form-section h1 {
    font-size: 32px;
    font-weight: 700;
    color: var(--dm-text);
    margin-bottom: 10px;
}

.auth-form-section > p {
    color: var(--dm-text-muted);
    margin-bottom: 30px;
    font-size: 16px;
}

.alert {
    border: none;
    border-radius: 8px;
    margin-bottom: 25px;
}

.alert-danger {
    background: #fde8e2;
    color: #a83524;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--dm-text);
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--dm-border);
    border-radius: 8px;
    font-size: 14px;
    font-family: var(--dm-font-sans);
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #4A7C59;
    box-shadow: 0 0 0 3px rgba(107, 190, 141, 0.1);
}

.password-group {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--dm-text-muted);
    transition: color 0.3s ease;
    background: none;
    border: none;
    font-size: 18px;
}

.toggle-password:hover {
    color: var(--dm-text);
}

.password-strength {
    height: 3px;
    background: #e5e7eb;
    border-radius: 3px;
    margin-top: 8px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0%;
    transition: width 0.3s ease;
    background: #dc2626;
}

.strength-bar.weak {
    width: 33%;
    background: #f59e0b;
}

.strength-bar.fair {
    width: 66%;
    background: #eab308;
}

.strength-bar.strong {
    width: 100%;
    background: #4A7C59;
}

.form-hint {
    font-size: 12px;
    color: var(--dm-text-muted);
    margin-top: 6px;
    display: block;
}

.form-hint.error {
    color: #dc2626;
}

.form-hint.success {
    color: #4A7C59;
}

.btn-register {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #2C3E50, #1f2d3a);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
}

.btn-register:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(44, 62, 80, 0.24);
}

.auth-signin {
    text-align: center;
    color: var(--dm-text-muted);
    font-size: 14px;
    margin-top: 20px;
}

.auth-signin a {
    color: #4A7C59;
    text-decoration: none;
    font-weight: 600;
}

.auth-signin a:hover {
    text-decoration: underline;
}

/* Right Side - Image & Content */
.auth-image-section {
    background: linear-gradient(135deg, #2C3E50 0%, #1f2d3a 100%);
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 60px 40px;
}

.auth-image-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 60%;
    height: 150%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="rgba(107,190,141,0.1)"/><circle cx="70" cy="30" r="25" fill="rgba(107,190,141,0.08)"/></svg>');
    opacity: 0.5;
}

.auth-content {
    position: relative;
    z-index: 2;
    color: white;
    text-align: center;
}

.auth-content h2 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 15px;
}

.auth-content p {
    font-size: 16px;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 40px;
}

.auth-benefits {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.benefit-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.benefit-icon {
    font-size: 24px;
    width: 30px;
    text-align: center;
    flex-shrink: 0;
}

.benefit-text h4 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 5px;
    color: white;
}

.benefit-text p {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

@media (max-width: 991px) {
    .auth-container {
        grid-template-columns: 1fr;
    }

    .auth-form-section {
        padding: 40px;
    }

    .auth-image-section {
        min-height: 300px;
        padding: 40px;
    }

    .auth-content h2 {
        font-size: 24px;
    }
}

@media (max-width: 576px) {
    .auth-form-section {
        padding: 30px 20px;
    }

    .auth-form-section h1 {
        font-size: 24px;
    }

    .auth-image-section {
        min-height: 250px;
        padding: 30px 20px;
    }
}
</style>

</head>

<body>

<div class="auth-wrapper">
    <div class="auth-container">
        <!-- Form Section -->
        <div class="auth-form-section">
            <h1>Create Account</h1>
            <p>Join DineMate and start booking amazing dining experiences</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="process-register.php" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required onkeyup="checkEmail()">
                    <span class="form-hint" id="emailMsg"></span>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="+61 2 XXXX XXXX">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-group">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter a strong password" required onkeyup="checkStrength()">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <span class="form-hint" id="strengthMsg"></span>
                </div>

                <div class="form-group">
                    <label for="confirm">Confirm Password</label>
                    <input type="password" id="confirm" name="confirm_password" class="form-control" placeholder="Confirm your password" required onkeyup="matchPassword()">
                    <span class="form-hint" id="matchMsg"></span>
                </div>

                <button type="submit" class="btn-register">
                    <i class="fa fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="auth-signin">
                Already have an account? <a href="<?= appPath('auth/login.php') ?>">Sign In</a>
            </div>
        </div>

        <!-- Image Section -->
        <div class="auth-image-section">
            <div class="auth-content">
                <h2>Join the Community</h2>
                <p>Experience dining reimagined</p>

                <div class="auth-benefits">
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="fa fa-user-check"></i></div>
                        <div class="benefit-text">
                            <h4>Exclusive Access</h4>
                            <p>Book member-only dining experiences</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="fa fa-award"></i></div>
                        <div class="benefit-text">
                            <h4>Rewards Program</h4>
                            <p>Earn points with every reservation</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="fa fa-bell"></i></div>
                        <div class="benefit-text">
                            <h4>Priority Alerts</h4>
                            <p>Get notified about special offers first</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const password = document.getElementById('password');
    const icon = document.querySelector('.toggle-password i');
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function checkStrength() {
    const password = document.getElementById('password').value;
    const bar = document.getElementById('strengthBar');
    const msg = document.getElementById('strengthMsg');
    
    if (password.length === 0) {
        bar.className = 'strength-bar';
        msg.innerHTML = '';
        return;
    }
    
    if (password.length < 6) {
        bar.className = 'strength-bar';
        msg.innerHTML = 'Too short. Minimum 6 characters';
        msg.classList.add('error');
        msg.classList.remove('success');
    } else if (password.length < 10) {
        bar.className = 'strength-bar weak';
        msg.innerHTML = 'Weak password. Try adding more characters';
        msg.classList.add('error');
        msg.classList.remove('success');
    } else if (password.length < 14) {
        bar.className = 'strength-bar fair';
        msg.innerHTML = 'Fair password. Consider adding special characters';
        msg.classList.add('error');
        msg.classList.remove('success');
    } else {
        bar.className = 'strength-bar strong';
        msg.innerHTML = '✓ Strong password';
        msg.classList.remove('error');
        msg.classList.add('success');
    }
}

function matchPassword() {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm').value;
    const msg = document.getElementById('matchMsg');
    
    if (confirm === '') {
        msg.innerHTML = '';
        return;
    }
    
    if (password === confirm) {
        msg.innerHTML = '✓ Passwords match';
        msg.classList.remove('error');
        msg.classList.add('success');
    } else {
        msg.innerHTML = '✗ Passwords do not match';
        msg.classList.remove('success');
        msg.classList.add('error');
    }
}

function checkEmail() {
    const email = document.getElementById('email').value;
    const msg = document.getElementById('emailMsg');
    
    if (email === '') {
        msg.innerHTML = '';
        return;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        msg.innerHTML = '✗ Invalid email format';
        msg.classList.remove('success');
        msg.classList.add('error');
        return;
    }
    
    msg.innerHTML = '✓ Valid email';
    msg.classList.remove('error');
    msg.classList.add('success');
}

function validateForm() {
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm').value;
    
    if (name.trim() === '') {
        alert('Please enter your full name');
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters');
        return false;
    }
    
    if (password !== confirm) {
        alert('Passwords do not match');
        return false;
    }
    
    return true;
}
</script>

</body>
</html>









