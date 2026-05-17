<?php
require_once "../includes/functions.php";

startAppSession();

if (isLoggedIn() && getCurrentUserRole() === 'admin') {
    redirect(appPath('admin/pages/admin_home.php'));
}

$error = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_error']);
$adminLoginCsrfToken = csrfToken('admin_login');
?>
<!DOCTYPE html>
<html>
<head>
<title>DineMate Admin Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="<?= htmlspecialchars(assetUrl('assets/css/app.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
<link href="<?= htmlspecialchars(assetUrl('assets/css/pages/admin-login.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">

</head>
<body>

<div class="admin-container">

<div class="admin-left">

<h2>Admin Login</h2>
<p>Access the DineMate management analytics panel.</p>

<?php if($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="POST" action="process-admin-login.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminLoginCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

<div class="mb-3">
<input type="email" name="email" class="form-control" placeholder="Admin Email" required>
</div>

<div class="mb-4 password-wrapper">

<input type="password" name="password" id="password" class="form-control" placeholder="Password" required>

<button type="button" class="eye" data-toggle-password aria-label="Show password">
<i class="fa-regular fa-eye"></i>
</button>

</div>

<button class="btn btn-login w-100">Login</button>

</form>

</div>


<div class="admin-right">

<div class="admin-overlay"></div>

<div class="admin-text">
<h3>DineMate Admin</h3>
<p>Manage reservations, tables and customers efficiently.</p>
</div>

</div>

</div>

<script src="<?= htmlspecialchars(assetUrl('assets/js/pages/auth.js'), ENT_QUOTES, 'UTF-8') ?>"></script>

</body>

</html>

