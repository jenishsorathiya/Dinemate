<?php
require_once __DIR__ . '/../includes/functions.php';

startAppSession();

if (isLoggedIn()) {
    redirect(getDefaultRedirectForRole(getCurrentUserRole()));
}

$registerCsrfToken = csrfToken('register');
?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?= htmlspecialchars(assetUrl('assets/css/app.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link href="<?= htmlspecialchars(assetUrl('assets/css/pages/auth-register.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

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

            <form method="POST" action="process-register.php" data-register-form>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($registerCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required>
                    <span class="form-hint" id="emailMsg"></span>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="+61 2 XXXX XXXX">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-group">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter a strong password" required>
                        <button type="button" class="toggle-password" data-toggle-password aria-label="Show password">
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
                    <input type="password" id="confirm" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
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

<script src="<?= htmlspecialchars(assetUrl('assets/js/pages/auth.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

</body>
</html>









