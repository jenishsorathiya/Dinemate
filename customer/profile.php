<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

requireCustomer();
ensureBookingRequestColumns($pdo);

$userId = (int) getCurrentUserId();
$error = '';
$success = '';
$profileCsrfToken = csrfToken('customer_profile');
$profileFlash = getFlashMessage();
if ($profileFlash) {
    if (($profileFlash['type'] ?? '') === 'error') {
        $error = (string) ($profileFlash['message'] ?? '');
    } else {
        $success = (string) ($profileFlash['message'] ?? '');
    }
}

$customerProfile = ensureCustomerProfileForUser($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('customer_profile', ['redirect' => appPath('customer/profile.php')]);

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $seatingPreference = trim((string) ($_POST['seating_preference'] ?? ''));
        $preferredBookingTime = trim((string) ($_POST['preferred_booking_time'] ?? ''));
        $dietaryNotes = trim((string) ($_POST['dietary_notes'] ?? ''));
        $customerNotes = trim((string) ($_POST['customer_notes'] ?? ''));
        $emailRemindersEnabled = isset($_POST['email_reminders_enabled']) ? 1 : 0;
        $smsRemindersEnabled = isset($_POST['sms_reminders_enabled']) ? 1 : 0;

        if ($name === '' || $email === '' || $phone === '') {
            $error = 'Name, email, and phone are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match("/^[0-9\\s\\-\\(\\)\\+]+$/", $phone)) {
            $error = 'Phone number can only contain digits, spaces, hyphens, parentheses, and plus signs.';
        } else {
            $emailCheckStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
            $emailCheckStmt->execute([$email, $userId]);

            if ($emailCheckStmt->fetch(PDO::FETCH_ASSOC)) {
                $error = 'That email address is already in use.';
            } else {
                $updateUserStmt = $pdo->prepare("
                    UPDATE users
                    SET name = ?, email = ?, phone = ?
                    WHERE user_id = ? AND role = 'customer'
                ");
                $updateUserStmt->execute([$name, $email, $phone, $userId]);

                $profileId = $customerProfile ? (int) ($customerProfile['customer_profile_id'] ?? 0) : 0;
                if ($profileId < 1) {
                    $customerProfile = ensureCustomerProfileForUser($pdo, $userId);
                    $profileId = $customerProfile ? (int) ($customerProfile['customer_profile_id'] ?? 0) : 0;
                }

                if ($profileId > 0) {
                    $updateProfileStmt = $pdo->prepare("
                        UPDATE customer_profiles
                        SET name = ?,
                            email = ?,
                            phone = ?,
                            normalized_email = ?,
                            normalized_phone = ?,
                            seating_preference = ?,
                            preferred_booking_time = ?,
                            dietary_notes = ?,
                            notes = ?,
                            email_reminders_enabled = ?,
                            sms_reminders_enabled = ?
                        WHERE customer_profile_id = ?
                    ");
                    $updateProfileStmt->execute([
                        $name,
                        $email,
                        $phone,
                        normalizeCustomerProfileEmail($email),
                        normalizeCustomerProfilePhone($phone),
                        $seatingPreference !== '' ? $seatingPreference : null,
                        $preferredBookingTime !== '' ? $preferredBookingTime : null,
                        $dietaryNotes !== '' ? $dietaryNotes : null,
                        $customerNotes !== '' ? $customerNotes : null,
                        $emailRemindersEnabled,
                        $smsRemindersEnabled,
                        $profileId,
                    ]);
                }

                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $success = 'Profile and preferences updated.';
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $error = 'Fill in all password fields to change your password.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New password and confirmation do not match.';
        } else {
            $userStmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
            $userStmt->execute([$userId]);
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$userRow || !password_verify($currentPassword, (string) ($userRow['password'] ?? ''))) {
                $error = 'Current password is incorrect.';
            } else {
                $passwordUpdateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ? AND role = 'customer'");
                $passwordUpdateStmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                $success = 'Password updated successfully.';
            }
        }
    }

    $customerProfile = ensureCustomerProfileForUser($pdo, $userId);
}

$profileStmt = $pdo->prepare("SELECT name, email, phone, created_at FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    header("Location: ../public/index.php");
    exit();
}

$bookings = getCustomerPortalBookings($pdo, $userId);
$completedCount = 0;
$pendingCount = 0;
$lastBookingDate = '';

foreach ($bookings as $booking) {
    $status = strtolower((string) ($booking['status'] ?? 'pending'));
    if ($status === 'completed') {
        $completedCount++;
    }
    if (in_array($status, getBookingActiveStatuses(), true)) {
        $pendingCount++;
    }
    if ($lastBookingDate === '' && !empty($booking['booking_date'])) {
        $lastBookingDate = (string) $booking['booking_date'];
    }
}

$customerProfile = $customerProfile ?: [];
?>

<?php
$pageTitle = 'Profile | DineMate';
$extraStylesheets = ['assets/css/pages/customer-profile.css'];
include '../includes/header.php';
?>


<div class="container profile-wrapper">
    <div class="profile-layout">
        <div class="profile-card">
            <div class="profile-header">
                <h2>Your Details</h2>
                <p>Keep your contact details, dining preferences, and reminders ready for the next visit.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($profileCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="profile">

                <div class="profile-grid">
                    <div class="profile-field">
                        <label for="profileName">Name</label>
                        <input type="text" class="profile-input" id="profileName" name="name" value="<?php echo htmlspecialchars((string) ($profile['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="profile-field">
                        <label for="profilePhone">Phone</label>
                        <input type="text" class="profile-input" id="profilePhone" name="phone" value="<?php echo htmlspecialchars((string) ($profile['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="profile-field full-width">
                        <label for="profileEmail">Email</label>
                        <input type="email" class="profile-input" id="profileEmail" name="email" value="<?php echo htmlspecialchars((string) ($profile['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="profile-field">
                        <label for="seatingPreference">Seating Preference</label>
                        <select class="profile-select" id="seatingPreference" name="seating_preference">
                            <option value="">No preference</option>
                            <option value="window" <?php echo (($customerProfile['seating_preference'] ?? '') === 'window') ? 'selected' : ''; ?>>Window</option>
                            <option value="quiet_corner" <?php echo (($customerProfile['seating_preference'] ?? '') === 'quiet_corner') ? 'selected' : ''; ?>>Quiet corner</option>
                            <option value="outdoor" <?php echo (($customerProfile['seating_preference'] ?? '') === 'outdoor') ? 'selected' : ''; ?>>Outdoor</option>
                            <option value="bar" <?php echo (($customerProfile['seating_preference'] ?? '') === 'bar') ? 'selected' : ''; ?>>Bar seating</option>
                            <option value="family_friendly" <?php echo (($customerProfile['seating_preference'] ?? '') === 'family_friendly') ? 'selected' : ''; ?>>Family-friendly area</option>
                        </select>
                    </div>
                    <div class="profile-field">
                        <label for="preferredBookingTime">Preferred Booking Time</label>
                        <select class="profile-select" id="preferredBookingTime" name="preferred_booking_time">
                            <option value="">No preference</option>
                            <option value="breakfast" <?php echo (($customerProfile['preferred_booking_time'] ?? '') === 'breakfast') ? 'selected' : ''; ?>>Breakfast</option>
                            <option value="lunch" <?php echo (($customerProfile['preferred_booking_time'] ?? '') === 'lunch') ? 'selected' : ''; ?>>Lunch</option>
                            <option value="afternoon" <?php echo (($customerProfile['preferred_booking_time'] ?? '') === 'afternoon') ? 'selected' : ''; ?>>Afternoon</option>
                            <option value="dinner" <?php echo (($customerProfile['preferred_booking_time'] ?? '') === 'dinner') ? 'selected' : ''; ?>>Dinner</option>
                        </select>
                    </div>
                    <div class="profile-field full-width">
                        <label for="dietaryNotes">Dietary or Allergy Notes</label>
                        <textarea class="profile-textarea" id="dietaryNotes" name="dietary_notes" placeholder="Allergies, dietary preferences, mobility needs, pram space, or anything helpful for the team."><?php echo htmlspecialchars((string) ($customerProfile['dietary_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="profile-field full-width">
                        <label for="customerNotes">Visit Notes</label>
                        <textarea class="profile-textarea" id="customerNotes" name="customer_notes" placeholder="A favourite area, regular occasion, accessibility note, or anything you often mention when booking."><?php echo htmlspecialchars((string) ($customerProfile['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>

                <div class="profile-section">
                    <h3>Reminders</h3>
                    <div class="toggle-row dm-mt-16">
                        <label class="toggle-item">
                            <div class="toggle-copy">
                                <strong>Email reminders</strong>
                                <span>Send reservation reminders and confirmation updates by email.</span>
                            </div>
                            <input type="checkbox" name="email_reminders_enabled" value="1" <?php echo !isset($customerProfile['email_reminders_enabled']) || (int) $customerProfile['email_reminders_enabled'] === 1 ? 'checked' : ''; ?>>
                        </label>
                        <label class="toggle-item">
                            <div class="toggle-copy">
                                <strong>SMS reminders</strong>
                                <span>Send text reminders to the phone number saved above.</span>
                            </div>
                            <input type="checkbox" name="sms_reminders_enabled" value="1" <?php echo !empty($customerProfile['sms_reminders_enabled']) ? 'checked' : ''; ?>>
                        </label>
                    </div>
                </div>

                <div class="profile-actions">
                    <a class="profile-btn profile-btn-secondary dm-no-underline" href="dashboard.php">Back to Dashboard</a>
                    <button type="submit" class="profile-btn profile-btn-primary">Save Profile</button>
                </div>
            </form>

            <div class="profile-section">
                <h3>Account Security</h3>
                <p class="dm-mt-8 dm-muted">Update your password when you need a fresh sign-in.</p>
                <form method="POST" class="dm-mt-18">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($profileCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="password">
                    <div class="profile-grid">
                        <div class="profile-field full-width">
                            <label for="currentPassword">Current Password</label>
                            <input type="password" class="profile-input" id="currentPassword" name="current_password" autocomplete="current-password">
                        </div>
                        <div class="profile-field">
                            <label for="newPassword">New Password</label>
                            <input type="password" class="profile-input" id="newPassword" name="new_password" autocomplete="new-password">
                        </div>
                        <div class="profile-field">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" class="profile-input" id="confirmPassword" name="confirm_password" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button type="submit" class="profile-btn profile-btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>

        <aside class="profile-side-card">
            <h3>At a Glance</h3>
            <p>A quick view of your visit history and saved preferences.</p>

            <div class="stats-list dm-mt-18">
                <div class="stat-item">
                    <strong>Completed visits</strong>
                    <span><?php echo number_format($completedCount); ?> completed reservations in your history.</span>
                </div>
                <div class="stat-item">
                    <strong>Active reservations</strong>
                    <span><?php echo number_format($pendingCount); ?> pending or confirmed reservations.</span>
                </div>
                <div class="stat-item">
                    <strong>Last booking</strong>
                    <span><?php echo $lastBookingDate !== '' ? htmlspecialchars(date('j M Y', strtotime($lastBookingDate)), ENT_QUOTES, 'UTF-8') : 'None'; ?></span>
                </div>
            </div>

            <div class="quick-links dm-mt-22">
                <div class="quick-link-card">
                    <strong>Reservation history</strong>
                    <span>Review previous reservations and rebook from history.</span>
                    <div class="dm-mt-12"><a href="my-bookings.php?view=past">View history</a></div>
                </div>
                <div class="quick-link-card">
                    <strong>Upcoming reservations</strong>
                    <span>View and manage your upcoming bookings.</span>
                    <div class="dm-mt-12"><a href="dashboard.php">View dashboard</a></div>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
