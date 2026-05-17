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
            <div class="admin-workspace admin-ops settings-shell">
                <header class="admin-page-heading">
                    <div>
                        <p class="admin-page-kicker">Control Room</p>
                        <h1 class="admin-page-title">Booking Rules</h1>
                        <p class="admin-page-copy">Set the reservation switches and limits staff rely on before bookings reach the floor.</p>
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

                <form method="POST" class="ops-page-grid">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <section class="admin-panel ops-stack">
                        <div class="admin-panel-header">
                            <div>
                                <h2 class="admin-panel-title">Reservation Switches</h2>
                                <p class="admin-panel-copy">Turn core booking behavior on or off for guests and staff-created reservations.</p>
                            </div>
                        </div>
                        <div class="admin-panel-body ops-control-grid">
                            <article class="ops-control-card <?php echo $settings['enable_online_bookings'] ? '' : 'is-off'; ?>">
                                <div class="ops-control-top">
                                    <div>
                                        <h3 class="ops-control-title">Online Bookings</h3>
                                        <p class="ops-control-copy">Guests can create bookings from the public booking page.</p>
                                    </div>
                                    <label class="ops-toggle" for="enable-online-bookings">
                                        <input type="checkbox" name="enable_online_bookings" id="enable-online-bookings" value="1" <?php echo $settings['enable_online_bookings'] ? 'checked' : ''; ?>>
                                        <span aria-hidden="true"></span>
                                    </label>
                                </div>
                                <span class="admin-chip <?php echo $settings['enable_online_bookings'] ? 'is-success' : 'is-danger'; ?>">
                                    <?php echo $settings['enable_online_bookings'] ? 'Accepting online bookings' : 'Online booking paused'; ?>
                                </span>
                            </article>

                            <article class="ops-control-card <?php echo $settings['allow_booking_modification'] ? '' : 'is-off'; ?>">
                                <div class="ops-control-top">
                                    <div>
                                        <h3 class="ops-control-title">Customer Edits</h3>
                                        <p class="ops-control-copy">Customers can reschedule or update their own reservations.</p>
                                    </div>
                                    <label class="ops-toggle" for="allow-booking-modification">
                                        <input type="checkbox" name="allow_booking_modification" id="allow-booking-modification" value="1" <?php echo $settings['allow_booking_modification'] ? 'checked' : ''; ?>>
                                        <span aria-hidden="true"></span>
                                    </label>
                                </div>
                                <span class="admin-chip <?php echo $settings['allow_booking_modification'] ? 'is-success' : 'is-warning'; ?>">
                                    <?php echo $settings['allow_booking_modification'] ? 'Self-service edits on' : 'Staff edits only'; ?>
                                </span>
                            </article>

                            <article class="ops-control-card <?php echo $settings['allow_table_request'] ? '' : 'is-off'; ?>">
                                <div class="ops-control-top">
                                    <div>
                                        <h3 class="ops-control-title">Table Requests</h3>
                                        <p class="ops-control-copy">Guests can leave seating preferences and visit notes.</p>
                                    </div>
                                    <label class="ops-toggle" for="allow-table-request">
                                        <input type="checkbox" name="allow_table_request" id="allow-table-request" value="1" <?php echo $settings['allow_table_request'] ? 'checked' : ''; ?>>
                                        <span aria-hidden="true"></span>
                                    </label>
                                </div>
                                <span class="admin-chip <?php echo $settings['allow_table_request'] ? 'is-success' : ''; ?>">
                                    <?php echo $settings['allow_table_request'] ? 'Preferences collected' : 'Preference field hidden'; ?>
                                </span>
                            </article>

                            <article class="ops-control-card <?php echo $settings['auto_table_assignment'] ? '' : 'is-off'; ?>">
                                <div class="ops-control-top">
                                    <div>
                                        <h3 class="ops-control-title">Table Matching</h3>
                                        <p class="ops-control-copy">The system can choose a suitable available table when one fits.</p>
                                    </div>
                                    <label class="ops-toggle" for="auto-table-assignment">
                                        <input type="checkbox" name="auto_table_assignment" id="auto-table-assignment" value="1" <?php echo $settings['auto_table_assignment'] ? 'checked' : ''; ?>>
                                        <span aria-hidden="true"></span>
                                    </label>
                                </div>
                                <span class="admin-chip <?php echo $settings['auto_table_assignment'] ? 'is-primary' : ''; ?>">
                                    <?php echo $settings['auto_table_assignment'] ? 'Automatic matching on' : 'Manual assignment'; ?>
                                </span>
                            </article>
                        </div>
                    </section>

                    <aside class="ops-stack">
                        <section class="ops-metric-grid">
                            <div class="ops-metric">
                                <span>Party Range</span>
                                <strong><?php echo (int) $settings['min_party_size']; ?>-<?php echo (int) $settings['max_party_size']; ?></strong>
                                <small>Guests per booking</small>
                            </div>
                            <div class="ops-metric">
                                <span>Advance Notice</span>
                                <strong><?php echo (int) $settings['minimum_advanced_booking_minutes']; ?></strong>
                                <small>Minutes before arrival</small>
                            </div>
                            <div class="ops-metric">
                                <span>Default Slot</span>
                                <strong><?php echo (int) $settings['booking_duration_minutes']; ?></strong>
                                <small>Minutes per booking</small>
                            </div>
                        </section>

                        <section class="admin-panel">
                        <div class="admin-panel-header">
                            <div>
                                <h2 class="admin-panel-title">Limits & Timing</h2>
                                <p class="admin-panel-copy">Keep the booking window realistic for service and table turnover.</p>
                            </div>
                        </div>
                        <div class="admin-panel-body ops-form-grid">
                            <div class="ops-form-field">
                                <label for="min-party-size">Minimum party size</label>
                                <input type="number" name="min_party_size" id="min-party-size" class="setting-input" min="1" value="<?php echo htmlspecialchars((string) $settings['min_party_size'], ENT_QUOTES, 'UTF-8'); ?>">
                                <small>Smallest party accepted online.</small>
                            </div>

                            <div class="ops-form-field">
                                <label for="max-party-size">Maximum party size</label>
                                <input type="number" name="max_party_size" id="max-party-size" class="setting-input" min="1" value="<?php echo htmlspecialchars((string) $settings['max_party_size'], ENT_QUOTES, 'UTF-8'); ?>">
                                <small>Largest party accepted online.</small>
                            </div>

                            <div class="ops-form-field">
                                <label for="minimum-advanced-booking-minutes">Minimum advance booking</label>
                                <input type="number" name="minimum_advanced_booking_minutes" id="minimum-advanced-booking-minutes" class="setting-input" min="0" value="<?php echo htmlspecialchars((string) $settings['minimum_advanced_booking_minutes'], ENT_QUOTES, 'UTF-8'); ?>">
                                <small>Required lead time before arrival.</small>
                            </div>

                            <div class="ops-form-field">
                                <label for="booking-duration-minutes">Booking duration (minutes)</label>
                                <input type="number" name="booking_duration_minutes" id="booking-duration-minutes" class="setting-input" min="30" value="<?php echo htmlspecialchars((string) $settings['booking_duration_minutes'], ENT_QUOTES, 'UTF-8'); ?>">
                                <small>Default table hold duration.</small>
                            </div>

                            <div class="ops-action-bar ops-form-field is-wide">
                                <a class="secondary-btn" href="settings.php"><i class="bi bi-arrow-counterclockwise"></i> Reset changes</a>
                                <button type="submit" class="primary-btn"><i class="bi bi-check2-circle"></i> Save Settings</button>
                            </div>
                        </div>
                        </section>

                        <section class="ops-note-list" aria-label="Settings notes">
                            <div class="ops-note">Use conservative party and lead-time limits when staffing is tight or table turnover is uneven.</div>
                            <div class="ops-note">Auto table matching respects available table capacity, but staff can still adjust assignments from the bookings page.</div>
                        </section>
                    </aside>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
