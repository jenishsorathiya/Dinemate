<?php 
session_start(); 
$appCssVersion = (string) (@filemtime(__DIR__ . '/../assets/css/app.css') ?: time());
?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="../assets/css/app.css?v=<?= htmlspecialchars($appCssVersion, ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'DM Sans', sans-serif;
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
}

.auth-form-section h1 {
    font-size: 32px;
    font-weight: 700;
    color: var(--dm-text);
    margin-bottom: 10px;
}

.auth-form-section > p {
    color: var(--dm-text-muted);
    margin-bottom: 40px;
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
    font-family: 'DM Sans', sans-serif;
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

.form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    font-size: 14px;
}

.form-footer a {
    color: #4A7C59;
    text-decoration: none;
    transition: color 0.3s ease;
}

.form-footer a:hover {
    color: #6BBE8D;
}

.btn-login {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #4A7C59, #6BBE8D);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 20px;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(107, 190, 141, 0.3);
}

.divider {
    display: flex;
    align-items: center;
    margin: 30px 0;
    color: var(--dm-border-strong);
    font-size: 12px;
}

.divider::before,
.divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--dm-border);
}

.divider span {
    margin: 0 15px;
}

.social-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 25px;
}

.social-btn {
    padding: 12px;
    border: 1px solid var(--dm-border);
    background: var(--dm-surface);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 500;
    font-size: 14px;
}

.social-btn:hover {
    border-color: #4A7C59;
    background: rgba(107, 190, 141, 0.05);
}

.auth-signup {
    text-align: center;
    color: var(--dm-text-muted);
    font-size: 14px;
}

.auth-signup a {
    color: #4A7C59;
    text-decoration: none;
    font-weight: 600;
}

.auth-signup a:hover {
    text-decoration: underline;
}

/* Right Side - Image & Content */
.auth-image-section {
    background: linear-gradient(135deg, #4A7C59 0%, #2C3E50 100%);
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
    margin-bottom: 30px;
}

.auth-features {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.feature-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.feature-icon {
    font-size: 24px;
    width: 30px;
    text-align: center;
    flex-shrink: 0;
}

.feature-text h4 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 5px;
    color: white;
}

.feature-text p {
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

    .social-buttons {
        grid-template-columns: 1fr;
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
            <h1>Welcome Back</h1>
            <p>Sign in to your account to manage your reservations</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="process-login.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-group">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-footer">
                    <label style="margin-bottom: 0;">
                        <input type="checkbox" name="remember" style="margin-right: 5px;">
                        Remember me
                    </label>
                    <a href="#">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fa fa-sign-in"></i> Sign In
                </button>
            </form>

            <div class="divider">
                <span>OR</span>
            </div>

            <div class="social-buttons">
                <button class="social-btn" onclick="showComingSoon()">
                    <i class="fa-brands fa-google"></i> Google
                </button>
                <button class="social-btn" onclick="showComingSoon()">
                    <i class="fa-brands fa-apple"></i> Apple
                </button>
            </div>

            <div class="auth-signup">
                Don't have an account? <a href="<?= appPath('auth/register.php') ?>">Sign Up</a>
            </div>
        </div>

        <!-- Image Section -->
        <div class="auth-image-section">
            <div class="auth-content">
                <h2>Dining Made Simple</h2>
                <p>Experience the future of restaurant reservations</p>

                <div class="auth-features">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa fa-lightning"></i></div>
                        <div class="feature-text">
                            <h4>Instant Booking</h4>
                            <p>Reserve your table in seconds</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa fa-calendar-check"></i></div>
                        <div class="feature-text">
                            <h4>Real-Time Updates</h4>
                            <p>Get instant confirmation and reminders</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa fa-shield-alt"></i></div>
                        <div class="feature-text">
                            <h4>Secure & Private</h4>
                            <p>Your data is always protected</p>
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

function showComingSoon() {
    alert('Social login integration coming soon!');
}
</script>

</body>
</html>






