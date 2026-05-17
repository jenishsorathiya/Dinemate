<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureSettingsSchema($pdo);

if (empty($_SESSION['admin_settings_csrf'])) {
    $_SESSION['admin_settings_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_settings_csrf'];
$flashMessage = getFlashMessage();
$settings = getBookingSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        setFlashMessage('error', 'Your session expired. Please refresh and try again.');
        header('Location: settings.php');
        exit();
    }

    $enableOnlineBookings = isset($_POST['enable_online_bookings']) ? '1' : '0';
    $autoTableAssignment = isset($_POST['auto_table_assignment']) ? '1' : '0';
    $allowTableRequest = isset($_POST['allow_table_request']) ? '1' : '0';
    $allowBookingModification = isset($_POST['allow_booking_modification']) ? '1' : '0';

    $minPartySize = max(1, intval($_POST['min_party_size'] ?? 1));
    $maxPartySize = max($minPartySize, intval($_POST['max_party_size'] ?? $minPartySize));
    $minimumAdvancedBookingMinutes = max(0, intval($_POST['minimum_advanced_booking_minutes'] ?? 120));
    $bookingDurationMinutes = max(30, intval($_POST['booking_duration_minutes'] ?? 60));

    setSettingValue($pdo, 'enable_online_bookings', $enableOnlineBookings);
    setSettingValue($pdo, 'min_party_size', $minPartySize);
    setSettingValue($pdo, 'max_party_size', $maxPartySize);
    setSettingValue($pdo, 'minimum_advanced_booking_minutes', $minimumAdvancedBookingMinutes);
    setSettingValue($pdo, 'booking_duration_minutes', $bookingDurationMinutes);
    setSettingValue($pdo, 'auto_table_assignment', $autoTableAssignment);
    setSettingValue($pdo, 'allow_table_request', $allowTableRequest);
    setSettingValue($pdo, 'allow_booking_modification', $allowBookingModification);

    setFlashMessage('success', 'Admin booking settings saved successfully.');
    header('Location: settings.php');
    exit();
}

$settings = getBookingSettings($pdo);
$adminPageTitle = 'Settings';
$adminPageIcon = 'fa-gear';
$adminSidebarActive = 'settings';
$adminSidebarPathPrefix = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <?php include __DIR__ . '/../partials/admin-modernize.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/pages/admin-settings.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <main class="main-content admin-main">
            <div class="admin-workspace">
                <header class="admin-page-heading">
                    <div>
                        <p class="admin-page-kicker">Configuration</p>
                        <h1 class="admin-page-title">Booking Settings</h1>
                        <p class="admin-page-copy">Tune the customer booking flow, guest limits, default timing, and table assignment behavior.</p>
                    </div>
                    <div class="admin-actions">
                        <a class="secondary-btn" href="admin_bookings.php"><i class="bi bi-calendar-check"></i> View bookings</a>
                    </div>
                </header>

                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="admin-split-layout">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <section class="admin-panel">
                        <div class="admin-panel-header">
                            <div>
                                <h2 class="admin-panel-title">Customer Flow</h2>
                                <p class="admin-panel-copy">Choose what guests can do before a booking reaches the admin queue.</p>
                            </div>
                        </div>
                        <div class="admin-panel-body settings-grid">
                            <div class="setting-field">
                                <label for="enable-online-bookings">Online bookings</label>
                                <div class="setting-toggle">
                                    <input type="checkbox" name="enable_online_bookings" id="enable-online-bookings" value="1" <?php echo $settings['enable_online_bookings'] ? 'checked' : ''; ?>>
                                    <span>Enable customer booking flow</span>
                                </div>
                                <div class="field-description">Turn online booking availability on or off for customers.</div>
                            </div>

                            <div class="setting-field">
                                <label for="allow-booking-modification">Booking modifications</label>
                                <div class="setting-toggle">
                                    <input type="checkbox" name="allow_booking_modification" id="allow-booking-modification" value="1" <?php echo $settings['allow_booking_modification'] ? 'checked' : ''; ?>>
                                    <span>Allow customers to reschedule bookings</span>
                                </div>
                                <div class="field-description">When disabled, customers cannot edit existing reservations.</div>
                            </div>

                            <div class="setting-field">
                                <label for="allow-table-request">Table requests</label>
                                <div class="setting-toggle">
                                    <input type="checkbox" name="allow_table_request" id="allow-table-request" value="1" <?php echo $settings['allow_table_request'] ? 'checked' : ''; ?>>
                                    <span>Enable customer table preference notes</span>
                                </div>
                                <div class="field-description">Hide or show the table request / special requirements field on booking forms.</div>
                            </div>

                            <div class="setting-field">
                                <label for="auto-table-assignment">Auto table assignment</label>
                                <div class="setting-toggle">
                                    <input type="checkbox" name="auto_table_assignment" id="auto-table-assignment" value="1" <?php echo $settings['auto_table_assignment'] ? 'checked' : ''; ?>>
                                    <span>Attempt to assign an available table automatically</span>
                                </div>
                                <div class="field-description">If enabled, the system will select a matching available table when possible.</div>
                            </div>
                        </div>
                    </section>

                    <aside class="admin-panel">
                        <div class="admin-panel-header">
                            <div>
                                <h2 class="admin-panel-title">Rules</h2>
                                <p class="admin-panel-copy">These values feed the public booking form and admin-created reservations.</p>
                            </div>
                        </div>
                        <div class="admin-panel-body admin-form-stack">
                            <div class="setting-field">
                                <label for="min-party-size">Minimum party size</label>
                                <input type="number" name="min_party_size" id="min-party-size" class="setting-input" min="1" value="<?php echo htmlspecialchars((string) $settings['min_party_size'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="field-description">Minimum number of guests required for a booking.</div>
                            </div>

                            <div class="setting-field">
                                <label for="max-party-size">Maximum party size</label>
                                <input type="number" name="max_party_size" id="max-party-size" class="setting-input" min="1" value="<?php echo htmlspecialchars((string) $settings['max_party_size'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="field-description">Maximum number of guests allowed for online bookings.</div>
                            </div>

                            <div class="setting-field">
                                <label for="minimum-advanced-booking-minutes">Minimum advance booking</label>
                                <input type="number" name="minimum_advanced_booking_minutes" id="minimum-advanced-booking-minutes" class="setting-input" min="0" value="<?php echo htmlspecialchars((string) $settings['minimum_advanced_booking_minutes'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="field-description">Require this many minutes between now and the requested booking start time.</div>
                            </div>

                            <div class="setting-field">
                                <label for="booking-duration-minutes">Booking duration (minutes)</label>
                                <input type="number" name="booking_duration_minutes" id="booking-duration-minutes" class="setting-input" min="30" value="<?php echo htmlspecialchars((string) $settings['booking_duration_minutes'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="field-description">Use this duration for new online bookings.</div>
                            </div>

                            <div class="admin-inline-actions">
                                <button type="submit" class="primary-btn"><i class="bi bi-check2-circle"></i> Save Settings</button>
                            </div>
                        </div>
                    </aside>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
