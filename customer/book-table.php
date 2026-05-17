<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

startAppSession();

ensureBookingRequestColumns($pdo);
ensureSettingsSchema($pdo);
$bookingSettings = getBookingSettings($pdo);
$bookingCsrfToken = csrfToken('booking');

if (!$bookingSettings['enable_online_bookings']) {
    setFlashMessage('error', 'Online bookings are currently disabled.');
    redirect(appPath('public/index.php'));
}

$prefillName = '';
$prefillEmail = '';
$prefillPhone = '';
$prefillPhoneCountry = '+61';
$prefillPhoneLocal = '';
$prefillSpecialRequest = trim((string) ($_GET['special'] ?? ''));

if(isLoggedIn() && getCurrentUserRole() === 'customer') {
    $customerStmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE user_id = ? LIMIT 1");
    $customerStmt->execute([getCurrentUserId()]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if($customer) {
        $prefillName = (string)($customer['name'] ?? '');
        $prefillEmail = (string)($customer['email'] ?? '');
        $prefillPhone = (string)($customer['phone'] ?? '');
    }

    $customerProfile = ensureCustomerProfileForUser($pdo, (int) getCurrentUserId());
    if ($customerProfile) {
        if ($prefillSpecialRequest === '' && !empty($customerProfile['dietary_notes'])) {
            $prefillSpecialRequest = (string) $customerProfile['dietary_notes'];
        }
    }
}

if (!$bookingSettings['allow_table_request']) {
    $prefillSpecialRequest = '';
}

$bookingDurationMinutes = max(30, intval($bookingSettings['booking_duration_minutes']));
$showAccountPrompt = !(isLoggedIn() && getCurrentUserRole() === 'customer');

// Restaurant hours configuration
$restaurantHours = [
    'open' => '10:00',
    'close' => '22:00',
    'minDuration' => $bookingDurationMinutes,
    'maxDuration' => $bookingDurationMinutes
];

$pageError = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
$bookingFlash = getFlashMessage();
if ($bookingFlash && ($bookingFlash['type'] ?? '') === 'error') {
    $pageError = (string) ($bookingFlash['message'] ?? $pageError);
}

$defaultBookingDate = trim((string) ($_GET['date'] ?? date('Y-m-d')));
$defaultStartTime = trim((string) ($_GET['time'] ?? '12:00'));
$defaultGuests = max(1, (int) ($_GET['guests'] ?? 2));
$timeOptions = [];
$timeCursor = strtotime($restaurantHours['open']);
$timeLastSlot = strtotime($restaurantHours['close'] . ' -' . $bookingDurationMinutes . ' minutes');

while ($timeCursor <= $timeLastSlot) {
    $timeOptions[] = date('H:i', $timeCursor);
    $timeCursor = strtotime('+30 minutes', $timeCursor);
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $defaultBookingDate)) {
    $defaultBookingDate = date('Y-m-d');
}

if (!preg_match('/^\d{2}:\d{2}$/', $defaultStartTime) || !in_array($defaultStartTime, $timeOptions, true)) {
    $defaultStartTime = '12:00';
}

if ($prefillPhone !== '') {
    if (preg_match('/^(\+\d{1,3})\s*(.*)$/', $prefillPhone, $phoneMatches)) {
        $prefillPhoneCountry = $phoneMatches[1];
        $prefillPhoneLocal = trim($phoneMatches[2]);
    } else {
        $prefillPhoneLocal = $prefillPhone;
    }
}

$phoneCountryOptions = [
    ['label' => 'AU +61', 'value' => '+61'],
    ['label' => 'NZ +64', 'value' => '+64'],
    ['label' => 'US +1', 'value' => '+1'],
    ['label' => 'UK +44', 'value' => '+44'],
    ['label' => 'SG +65', 'value' => '+65'],
    ['label' => 'IN +91', 'value' => '+91'],
    ['label' => 'AE +971', 'value' => '+971'],
];
?>

<?php
$pageTitle = 'Book a Table | DineMate';
$extraStylesheets = ['assets/css/pages/customer-book-table.css'];
include '../includes/header.php';
?>


<div class="container booking-shell">
    <div class="booking-stage">
        <div class="booking-topbar">
            <div class="booking-heading">
                <h2>Make A Booking</h2>
            </div>
            <div class="booking-topbar-aside">
                <div class="booking-progress" aria-label="Booking progress">
                    <div class="booking-step is-active" id="bookingStepBooking">
                        <span class="booking-step-dot">1</span>
                        <span>Booking</span>
                    </div>
                    <div class="booking-step" id="bookingStepDetails">
                        <span class="booking-step-dot">2</span>
                        <span>Your Details</span>
                    </div>
                </div>
                <?php if ($showAccountPrompt): ?>
                    <div class="booking-account-card">
                        <div class="booking-account-row is-inline">
                            <p class="booking-account-title is-inline">Have an account? <a class="booking-mini-link" href="../auth/login.php">Log in</a> to continue.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pageError !== ''): ?>
            <div class="booking-alert">
                <i class="fa fa-circle-exclamation"></i>
                <?= htmlspecialchars($pageError) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['rebook'])): ?>
            <div class="booking-alert booking-alert-neutral">
                <i class="fa fa-repeat"></i>
                Previous booking details have been prefilled for review.
            </div>
        <?php endif; ?>

        <form
            action="process-booking.php"
            method="POST"
            id="booking-form"
            data-default-date="<?= htmlspecialchars($defaultBookingDate, ENT_QUOTES, 'UTF-8') ?>"
            data-min-duration="<?= (int) $restaurantHours['minDuration'] ?>"
            novalidate
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($bookingCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="booking_date" id="booking-date" value="<?= htmlspecialchars($defaultBookingDate) ?>">
            <input type="hidden" name="end_time" id="end-time" value="13:00">

            <div class="booking-layout">
                <div class="booking-left-stack">
                    <section class="booking-calendar-panel" aria-label="Booking calendar">
                        <div class="booking-calendar-top">
                            <div class="booking-selected-date">
                                <div>
                                    <div class="booking-selected-year" id="selectedYearLabel"></div>
                                    <div class="booking-selected-day" id="selectedDayLabel"></div>
                                </div>
                                <p id="selectedDateLabel"></p>
                            </div>
                            <div class="booking-calendar-grid">
                                <div class="booking-calendar-header">
                                    <button type="button" class="booking-calendar-nav" id="calendarPrevBtn" aria-label="Previous month">
                                        <i class="fa fa-chevron-left"></i>
                                    </button>
                                    <div class="booking-calendar-title" id="calendarMonthLabel"></div>
                                    <button type="button" class="booking-calendar-nav" id="calendarNextBtn" aria-label="Next month">
                                        <i class="fa fa-chevron-right"></i>
                                    </button>
                                </div>
                                <div class="booking-weekdays">
                                    <span>Mon</span>
                                    <span>Tue</span>
                                    <span>Wed</span>
                                    <span>Thu</span>
                                    <span>Fri</span>
                                    <span>Sat</span>
                                    <span>Sun</span>
                                </div>
                                <div class="booking-days" id="bookingCalendarDays"></div>
                            </div>
                        </div>
                    </section>
                    <?php if ($showAccountPrompt): ?>
                        <aside class="booking-account-card is-benefits" aria-label="Account benefits">
                            <div class="booking-benefits-top">
                                <span class="booking-benefits-icon"><i class="fa fa-user"></i></span>
                                <div class="booking-benefits-copy">
                                    <p class="booking-account-title">Unlock perks with an account.</p>
                                </div>
                            </div>
                            <ul class="booking-benefits-list">
                                <li><i class="fa fa-circle"></i><span>Easily manage and update your bookings</span></li>
                                <li><i class="fa fa-circle"></i><span>View your booking history.</span></li>
                                <li><i class="fa fa-circle"></i><span>Receive exclusive offers and gift vouchers</span></li>
                            </ul>
                            <div class="booking-account-links">
                                <a class="booking-mini-btn" href="../auth/login.php">Log In</a>
                                <a class="booking-mini-btn is-primary" href="../auth/register.php">Register</a>
                            </div>
                        </aside>
                    <?php endif; ?>
                </div>

                <div class="booking-card-stack">
                    <section class="booking-step-card" id="bookingStepCardBooking">
                        <div class="booking-step-header">
                            <div>
                                <h3>Booking</h3>
                                <p>Pick your party size and a preferred arrival time.</p>
                            </div>
                            <span class="booking-card-pill">
                                <i class="fa fa-clock"></i>
                                <?php echo htmlspecialchars((int) $bookingDurationMinutes, ENT_QUOTES, 'UTF-8'); ?>-minute request
                            </span>
                        </div>

                        <div class="booking-field-grid">
                            <div class="booking-field full-width">
                                <label for="number-of-guests">How many people are coming?</label>
                                <div class="booking-guests-control">
                                    <button type="button" class="booking-guest-btn" id="decreaseGuestsBtn" aria-label="Decrease guests">−</button>
                                    <input type="number" name="number_of_guests" id="number-of-guests" class="booking-guests-display" min="<?php echo htmlspecialchars((string) $bookingSettings['min_party_size'], ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars((string) $bookingSettings['max_party_size'], ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo (int) $defaultGuests; ?>" required>
                                    <button type="button" class="booking-guest-btn is-primary" id="increaseGuestsBtn" aria-label="Increase guests">+</button>
                                </div>
                                <div class="booking-inline-error" id="guest-count-error"></div>
                            </div>

                            <div class="booking-field full-width">
                                <label for="start-time">Preferred Time</label>
                                <select name="start_time" id="start-time" class="booking-select" required>
                                    <?php foreach ($timeOptions as $timeOption): ?>
                                        <option value="<?= htmlspecialchars($timeOption) ?>" <?= $timeOption === $defaultStartTime ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(date('g:i A', strtotime($timeOption))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="booking-hint">We are open from <?= htmlspecialchars(date('g:i A', strtotime($restaurantHours['open']))) ?> to <?= htmlspecialchars(date('g:i A', strtotime($restaurantHours['close']))) ?>.</div>
                            </div>
                        </div>

                        <div class="booking-summary">
                            <div class="booking-summary-item">
                                <span>Date</span>
                                <span id="summaryDateText"></span>
                            </div>
                            <div class="booking-summary-item">
                                <span>Time</span>
                                <span id="summaryTimeText"></span>
                            </div>
                            <div class="booking-summary-item">
                                <span>Party</span>
                                <span id="summaryGuestsText"></span>
                            </div>
                        </div>

                        <div class="booking-actions">
                            <span></span>
                            <button type="button" class="booking-btn booking-btn-primary" id="goToDetailsBtn">Next</button>
                        </div>
                    </section>

                    <section class="booking-step-card" id="bookingStepCardDetails" hidden>
                        <div class="booking-step-header">
                            <div>
                                <h3>Your Details</h3>
                                <p>We will use these details to confirm your request and contact you if needed.</p>
                            </div>
                            <span class="booking-card-pill">
                                <i class="fa fa-circle-info"></i>
                                Table assigned by staff
                            </span>
                        </div>

                        <div class="booking-field-grid">
                            <div class="booking-field full-width">
                                <label for="customer-name">Name</label>
                                <input type="text" name="customer_name" id="customer-name" class="booking-input" value="<?= htmlspecialchars($prefillName) ?>" required>
                            </div>
                            <div class="booking-field">
                                <label for="customer-email">Email</label>
                                <input type="email" name="customer_email" id="customer-email" class="booking-input" value="<?= htmlspecialchars($prefillEmail) ?>" required>
                            </div>
                            <div class="booking-field">
                                <label for="customer-phone">Phone Number</label>
                                <input type="hidden" name="customer_phone" id="customer-phone" value="<?= htmlspecialchars($prefillPhone) ?>">
                                <div class="booking-phone-row">
                                    <select id="customer-phone-country" class="booking-select booking-phone-country" aria-label="Country code">
                                        <?php foreach ($phoneCountryOptions as $countryOption): ?>
                                            <option value="<?= htmlspecialchars($countryOption['value']) ?>" <?= $countryOption['value'] === $prefillPhoneCountry ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($countryOption['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="tel" id="customer-phone-local" class="booking-input" inputmode="tel" placeholder="Mobile*" value="<?= htmlspecialchars($prefillPhoneLocal) ?>" required>
                                </div>
                            </div>
                            <?php if ($bookingSettings['allow_table_request']): ?>
                                <div class="booking-field full-width">
                                    <label for="special-request">Add Note or Special Requirements</label>
                                    <textarea name="special_request" id="special-request" class="booking-textarea" placeholder="Birthday celebration, pram space, allergy note, preferred seating, or anything the team should know."><?php echo htmlspecialchars($prefillSpecialRequest, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="special_request" value="">
                            <?php endif; ?>
                        </div>

                        <div class="booking-actions">
                            <button type="button" class="booking-btn booking-btn-secondary" id="backToBookingBtn">Back</button>
                            <button type="submit" class="booking-btn booking-btn-primary" id="submit-btn">Confirm Booking</button>
                        </div>
                    </section>
                </div>
            </div>
        </form>
    </div>
</div>


<?php
$extraFooterScripts = ['assets/js/pages/customer-book-table.js'];
include '../includes/footer.php';
?>

