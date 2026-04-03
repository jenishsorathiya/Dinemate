<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

requireCustomer();

$userId = getCurrentUserId();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || $email === '' || $phone === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match("/^[0-9\s\-\(\)\+]+$/", $phone)) {
        $error = 'Phone number can only contain digits, spaces, hyphens, parentheses, and plus signs.';
    } else {
        $emailCheckStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
        $emailCheckStmt->execute([$email, $userId]);

        if ($emailCheckStmt->fetch(PDO::FETCH_ASSOC)) {
            $error = 'That email address is already in use.';
        } else {
            $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ? AND role = 'customer'");
            $updateStmt->execute([$name, $email, $phone, $userId]);

            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $success = 'Profile updated successfully.';
        }
    }
}

$profileStmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    header("Location: ../index.php");
    exit();
}
?>

<?php include "../includes/header.php"; ?>

<style>
.profile-wrapper {
    margin-top: 120px;
    margin-bottom: 80px;
}

.profile-card {
    max-width: 760px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 22px;
    padding: 36px;
    box-shadow: 0 22px 50px rgba(15, 23, 42, 0.08);
}

.profile-card h3 {
    margin-bottom: 24px;
    font-weight: 700;
}

.profile-card .form-label {
    font-weight: 600;
}

.profile-card .form-control {
    border-radius: 12px;
    padding: 12px 14px;
}

.profile-save-btn {
    background: #f4b400;
    border: none;
    color: #111827;
    padding: 12px 18px;
    border-radius: 999px;
    font-weight: 700;
}
</style>

<div class="container profile-wrapper">
    <div class="profile-card">
        <h3><i class="fa fa-user text-warning"></i> My Profile</h3>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label" for="profileName">Name</label>
                <input type="text" class="form-control" id="profileName" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="profileEmail">Email</label>
                <input type="email" class="form-control" id="profileEmail" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label" for="profilePhone">Phone</label>
                <input type="text" class="form-control" id="profilePhone" name="phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" required>
            </div>
            <button type="submit" class="profile-save-btn">Save Profile</button>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>