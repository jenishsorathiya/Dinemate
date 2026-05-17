<?php 
require_once __DIR__ . '/../includes/functions.php';

startAppSession();

if (isLoggedIn()) {
    redirect(getDefaultRedirectForRole(getCurrentUserRole()));
}

$loginCsrfToken = csrfToken('login');
?>

<!DOCTYPE html>
<html>
<head>
<title>DineMate | Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Fraunces:opsz,wght@9..144,600;9..144,700;9..144,800;9..144,900&family=League+Spartan:wght@500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?= htmlspecialchars(assetUrl('assets/css/app.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link href="<?= htmlspecialchars(assetUrl('assets/css/pages/auth-login.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link href="<?= htmlspecialchars(assetUrl('assets/css/pages/guest-experience.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

</head>
<body class="dm-auth-page dm-login-page">

<div class="auth-wrapper">
    <div class="auth-container">
        <!-- Form Section -->
        <div class="auth-form-section">
            <p class="guest-section-kicker">Customer Portal</p>
            <h1>Welcome back to DineMate.</h1>
            <p>Sign in to manage reservations, preferences, visit history, and post-meal reviews.</p>
            
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="process-login.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($loginCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-group">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" data-toggle-password aria-label="Show password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-footer">
                    <span></span>
                    <a href="<?= appPath('public/contact.php') ?>">Need help?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fa fa-right-to-bracket"></i> Sign In
                </button>
            </form>

            <div class="auth-signup">
                Don't have an account? <a href="<?= appPath('auth/register.php') ?>">Sign Up</a>
            </div>
        </div>

        <!-- Image Section -->
        <div class="auth-image-section">
            <div class="auth-content">
                <h2>Your table story, saved.</h2>
                <p>See upcoming visits, update details, and keep every booking in one clear place.</p>

                <div class="auth-features">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa fa-calendar-check"></i></div>
                        <div class="feature-text">
                            <h4>Book faster</h4>
                            <p>Use saved details and preferences to keep the next request simple.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa fa-bell"></i></div>
                        <div class="feature-text">
                            <h4>Return smoothly</h4>
                            <p>Keep your past visits, saved notes, and preferences ready for next time.</p>
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









