<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

requireCustomer();
ensureBookingRequestColumns($pdo);

$userId = (int) getCurrentUserId();
$error = '';
$success = '';

$customerProfile = ensureCustomerProfileForUser($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    header("Location: ../index.php");
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

<?php include "../includes/header.php"; ?>

<style>
.profile-wrapper {
    margin-top: 118px;
    margin-bottom: 84px;
}

.profile-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
    gap: 22px;
}

.profile-card,
.profile-side-card {
    background: #ffffff;
    border: 1px solid #e7ecf3;
    border-radius: 24px;
    box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
}

.profile-card {
    padding: 30px;
}

.profile-side-card {
    padding: 24px;
}

.profile-header {
    margin-bottom: 24px;
}

.profile-header h2,
.profile-card h3,
.profile-side-card h3 {
    margin: 0;
    color: #162033;
}

.profile-header p,
.profile-side-card p {
    margin: 10px 0 0;
    color: #64748b;
    font-size: 14px;
}

.profile-section {
    margin-top: 28px;
    padding-top: 24px;
    border-top: 1px solid #eef2f7;
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.profile-field.full-width {
    grid-column: 1 / -1;
}

.profile-field label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #31415f;
    margin-bottom: 8px;
}

.profile-input,
.profile-select,
.profile-textarea {
    width: 100%;
    border: 1px solid #d9e1ec;
    border-radius: 14px;
    padding: 13px 14px;
    font: inherit;
    color: #111827;
    background: #ffffff;
}

.profile-textarea {
    min-height: 112px;
    resize: vertical;
}

.profile-input:focus,
.profile-select:focus,
.profile-textarea:focus {
    outline: none;
    border-color: #bdc9da;
    box-shadow: 0 0 0 4px rgba(29, 40, 64, 0.12);
}

.profile-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 22px;
    flex-wrap: wrap;
}

.profile-btn {
    border: none;
    border-radius: 14px;
    padding: 12px 18px;
    font-weight: 700;
}

.profile-btn-primary {
    background: #1d2840;
    color: #ffffff;
    box-shadow: 0 14px 28px rgba(29, 40, 64, 0.16);
}

.profile-btn-secondary {
    background: #ffffff;
    border: 1px solid #d9e1ec;
    color: #31415f;
}

.toggle-row,
.stats-list,
.quick-links {
    display: grid;
    gap: 12px;
}

.toggle-item,
.stat-item,
.quick-link-card {
    border: 1px solid #e7ecf3;
    border-radius: 18px;
    padding: 16px;
    background: #f8fafc;
}

.toggle-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.toggle-copy strong,
.stat-item strong,
.quick-link-card strong {
    display: block;
    color: #162033;
}

.toggle-copy span,
.stat-item span,
.quick-link-card span {
    display: block;
    margin-top: 6px;
    color: #64748b;
    font-size: 13px;
}

.quick-link-card a {
    color: #1d4ed8;
    text-decoration: none;
    font-weight: 700;
}

@media (max-width: 991px) {
    .profile-layout,
    .profile-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container profile-wrapper">
    <div class="profile-layout">
        <div class="profile-card">
            <div class="profile-header">
                <h2><i class="fa fa-user text-warning"></i> My Profile And Preferences</h2>
                <p>Update your contact details, reminder preferences, saved dining notes, and account password from one place.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST">
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
                        <label for="dietaryNotes">Dietary Or Allergy Notes</label>
                        <textarea class="profile-textarea" id="dietaryNotes" name="dietary_notes" placeholder="Share allergies, dietary preferences, mobility needs, pram space, or other dining notes."><?php echo htmlspecialchars((string) ($customerProfile['dietary_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="profile-field full-width">
                        <label for="customerNotes">Additional Booking Notes</label>
                        <textarea class="profile-textarea" id="customerNotes" name="customer_notes" placeholder="Anything you want saved on your customer profile for future bookings."><?php echo htmlspecialchars((string) ($customerProfile['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>

                <div class="profile-section">
                    <h3>Reminder Preferences</h3>
                    <div class="toggle-row" style="margin-top:16px;">
                        <label class="toggle-item">
                            <div class="toggle-copy">
                                <strong>Email reminders</strong>
                                <span>Use my saved email address for booking reminders and confirmation follow-ups.</span>
                            </div>
                            <input type="checkbox" name="email_reminders_enabled" value="1" <?php echo !isset($customerProfile['email_reminders_enabled']) || (int) $customerProfile['email_reminders_enabled'] === 1 ? 'checked' : ''; ?>>
                        </label>
                        <label class="toggle-item">
                            <div class="toggle-copy">
                                <strong>SMS reminders</strong>
                                <span>Store my preference for text reminders when the restaurant starts sending them.</span>
                            </div>
                            <input type="checkbox" name="sms_reminders_enabled" value="1" <?php echo !empty($customerProfile['sms_reminders_enabled']) ? 'checked' : ''; ?>>
                        </label>
                    </div>
                </div>

                <div class="profile-actions">
                    <a class="profile-btn profile-btn-secondary" href="dashboard.php" style="text-decoration:none;">Back To Dashboard</a>
                    <button type="submit" class="profile-btn profile-btn-primary">Save Profile</button>
                </div>
            </form>

            <div class="profile-section">
                <h3>Account Security</h3>
                <p style="margin-top:8px;color:#64748b;">Change your password without leaving the customer portal.</p>
                <form method="POST" style="margin-top:18px;">
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
            <h3>Profile Snapshot</h3>
            <p>Your saved customer profile also links older guest and admin-entered bookings into one history whenever the details match.</p>

            <div class="stats-list" style="margin-top:18px;">
                <div class="stat-item">
                    <strong>Completed Visits</strong>
                    <span><?php echo number_format($completedCount); ?> completed bookings in your customer history.</span>
                </div>
                <div class="stat-item">
                    <strong>Active Bookings</strong>
                    <span><?php echo number_format($pendingCount); ?> pending or confirmed reservations.</span>
                </div>
                <div class="stat-item">
                    <strong>Last Booking</strong>
                    <span><?php echo $lastBookingDate !== '' ? htmlspecialchars(date('j M Y', strtotime($lastBookingDate)), ENT_QUOTES, 'UTF-8') : 'No bookings yet'; ?></span>
                </div>
                <div class="stat-item">
                    <strong>Customer Profile ID</strong>
                    <span><?php echo !empty($customerProfile['customer_profile_id']) ? '#' . (int) $customerProfile['customer_profile_id'] : 'Creating on first save'; ?></span>
                </div>
            </div>

            <div class="quick-links" style="margin-top:22px;">
                <div class="quick-link-card">
                    <strong>Need to rebook quickly?</strong>
                    <span>Jump into your booking history and repeat a previous reservation in one step.</span>
                    <div style="margin-top:12px;"><a href="my-bookings.php?view=past">Open booking history</a></div>
                </div>
                <div class="quick-link-card">
                    <strong>Have a reservation already?</strong>
                    <span>Use the dashboard to find your next table, reschedule it, or review reminders.</span>
                    <div style="margin-top:12px;"><a href="dashboard.php">Go to dashboard</a></div>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
