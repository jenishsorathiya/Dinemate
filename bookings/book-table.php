<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ensureBookingRequestColumns($pdo);

$prefillName = '';
$prefillEmail = '';
$prefillPhone = '';
$prefillPhoneCountry = '+61';
$prefillPhoneLocal = '';

if(isLoggedIn() && getCurrentUserRole() === 'customer') {
    $customerStmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE user_id = ? LIMIT 1");
    $customerStmt->execute([getCurrentUserId()]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if($customer) {
        $prefillName = (string)($customer['name'] ?? '');
        $prefillEmail = (string)($customer['email'] ?? '');
        $prefillPhone = (string)($customer['phone'] ?? '');
    }
}

$showAccountPrompt = !(isLoggedIn() && getCurrentUserRole() === 'customer');

// Restaurant hours configuration
$restaurantHours = [
    'open' => '10:00',
    'close' => '22:00',
    'minDuration' => 60, // Minimum booking duration in minutes
    'maxDuration' => 180  // Maximum booking duration in minutes
];

$pageError = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$defaultBookingDate = date('Y-m-d');
$defaultStartTime = '12:00';
$timeOptions = [];
$timeCursor = strtotime($restaurantHours['open']);
$timeLastSlot = strtotime($restaurantHours['close'] . ' -60 minutes');

while ($timeCursor <= $timeLastSlot) {
    $timeOptions[] = date('H:i', $timeCursor);
    $timeCursor = strtotime('+30 minutes', $timeCursor);
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

<?php include "../includes/header.php"; ?>

<style>
.booking-shell {
    margin-top: 118px;
    margin-bottom: 80px;
    max-width: 1100px;
}

.booking-stage {
    background: linear-gradient(180deg, #fffef7 0%, #ffffff 100%);
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 28px;
    box-shadow: 0 28px 70px rgba(15, 23, 42, 0.08);
    padding: 28px;
}

.booking-topbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}

.booking-topbar-aside {
    display: grid;
    justify-items: end;
    gap: 12px;
}

.booking-heading h2 {
    margin: 0;
    font-size: 32px;
    line-height: 1.05;
    font-weight: 700;
    color: #1f2937;
}

.booking-heading p {
    margin: 10px 0 0;
    color: #6b7280;
    font-size: 15px;
    max-width: 560px;
}

.booking-progress {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.booking-step {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #94a3b8;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.booking-step-dot {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e5e7eb;
    color: #64748b;
}

.booking-step.is-active,
.booking-step.is-complete {
    color: #111827;
}

.booking-step.is-active .booking-step-dot,
.booking-step.is-complete .booking-step-dot {
    background: #f4b400;
    color: #111827;
}

.booking-layout {
    display: grid;
    grid-template-columns: minmax(320px, 440px) minmax(320px, 1fr);
    gap: 24px;
    align-items: start;
}

.booking-left-stack {
    display: grid;
    gap: 16px;
}

.booking-calendar-panel {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
}

.booking-calendar-top {
    display: grid;
    grid-template-columns: 150px 1fr;
    min-height: 360px;
}

.booking-selected-date {
    background: linear-gradient(180deg, #ffd60a 0%, #f4b400 100%);
    color: #111827;
    padding: 22px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.booking-selected-year {
    font-size: 30px;
    font-weight: 700;
    line-height: 1;
}

.booking-selected-day {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.1;
}

.booking-selected-date p {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.booking-calendar-grid {
    padding: 22px 24px 24px;
}

.booking-calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.booking-calendar-title {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
}

.booking-calendar-nav {
    width: 42px;
    height: 42px;
    border: none;
    background: #f8fafc;
    border-radius: 999px;
    color: #64748b;
    font-size: 17px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.18s ease, color 0.18s ease;
}

.booking-calendar-nav:hover {
    background: #f1f5f9;
    color: #111827;
}

.booking-weekdays,
.booking-days {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 8px;
}

.booking-weekdays span {
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    color: #94a3b8;
    padding-bottom: 2px;
}

.booking-day {
    aspect-ratio: 1;
    border: none;
    border-radius: 16px;
    background: transparent;
    color: #334155;
    font-size: 16px;
    font-weight: 600;
    transition: transform 0.16s ease, background 0.16s ease, color 0.16s ease, box-shadow 0.16s ease;
}

.booking-day:hover:not(:disabled) {
    background: #fff8d6;
    color: #111827;
    transform: translateY(-1px);
}

.booking-day.is-selected {
    background: #8b1e5c;
    color: #ffffff;
    box-shadow: 0 12px 22px rgba(139, 30, 92, 0.24);
}

.booking-day.is-today:not(.is-selected) {
    box-shadow: inset 0 0 0 2px #f4b400;
}

.booking-day.is-muted,
.booking-day:disabled {
    color: #cbd5e1;
    cursor: not-allowed;
}

.booking-card-stack {
    display: grid;
    gap: 18px;
}

.booking-account-card {
    width: min(100%, 360px);
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 14px 16px;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
}

.booking-account-card.is-benefits {
    width: 100%;
    border-radius: 20px;
    padding: 14px 16px;
    background: linear-gradient(180deg, #fffdf6 0%, #ffffff 100%);
}

.booking-account-copy {
    margin: 0;
    font-size: 13px;
    line-height: 1.45;
    color: #64748b;
}

.booking-account-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.booking-account-row.is-inline {
    align-items: center;
    gap: 10px;
}

.booking-account-title {
    margin: 0 0 4px;
    font-size: 14px;
    font-weight: 700;
    color: #1f2937;
}

.booking-account-title.is-inline {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}

.booking-account-links {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.booking-mini-link {
    color: #2563eb;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.booking-mini-link:hover {
    color: #1d4ed8;
}

.booking-mini-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 92px;
    padding: 10px 14px;
    border-radius: 12px;
    background: #f3f4f6;
    color: #1f2937;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.18s ease, transform 0.18s ease;
}

.booking-mini-btn:hover {
    background: #e5e7eb;
    transform: translateY(-1px);
}

.booking-mini-btn.is-primary {
    background: linear-gradient(135deg, #b97aa5 0%, #a85583 100%);
    color: #ffffff;
}

.booking-benefits-top {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.booking-benefits-icon {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    background: linear-gradient(180deg, #ffe58f 0%, #ffd60a 100%);
    color: #8a5a00;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
}

.booking-benefits-copy {
    min-width: 0;
}

.booking-benefits-copy .booking-account-title {
    margin-bottom: 2px;
}

.booking-benefits-copy .booking-account-copy {
    font-size: 12px;
}

.booking-benefits-list {
    display: grid;
    gap: 6px;
    margin: 8px 0 12px;
    padding: 0;
    list-style: none;
}

.booking-benefits-list li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    color: #475569;
    font-size: 13px;
    line-height: 1.45;
}

.booking-benefits-list i {
    color: #d4a018;
    margin-top: 3px;
    font-size: 8px;
}

.booking-step-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 20px 44px rgba(15, 23, 42, 0.06);
}

.booking-step-card[hidden] {
    display: none !important;
}

.booking-step-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.booking-step-header h3 {
    margin: 0;
    font-size: 30px;
    font-weight: 700;
    color: #111827;
}

.booking-step-header p {
    margin: 8px 0 0;
    color: #6b7280;
    font-size: 14px;
}

.booking-card-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    border-radius: 999px;
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
}

.booking-field-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.booking-field,
.booking-field.full-width {
    display: grid;
    gap: 6px;
}

.booking-field.full-width {
    grid-column: 1 / -1;
}

.booking-field label {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.booking-input,
.booking-select,
.booking-textarea,
.booking-guests-display {
    width: 100%;
    border: 1px solid #dbe3ef;
    border-radius: 16px;
    background: #ffffff;
    color: #111827;
    padding: 13px 15px;
    font: inherit;
    transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.booking-input:focus,
.booking-select:focus,
.booking-textarea:focus {
    outline: none;
    border-color: #f4b400;
    box-shadow: 0 0 0 4px rgba(244, 180, 0, 0.16);
}

.booking-textarea {
    min-height: 112px;
    resize: vertical;
}

.booking-phone-row {
    display: grid;
    grid-template-columns: 132px minmax(0, 1fr);
    gap: 10px;
    align-items: center;
}

.booking-phone-country {
    border-radius: 16px;
}

.booking-guests-control {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.booking-guest-btn {
    width: 46px;
    height: 46px;
    border: none;
    border-radius: 999px;
    background: #f3f4f6;
    color: #64748b;
    font-size: 24px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.booking-guest-btn:hover {
    background: #e5e7eb;
    color: #111827;
    transform: translateY(-1px);
}

.booking-guest-btn.is-primary {
    background: #ffd60a;
    color: #111827;
    box-shadow: 0 10px 24px rgba(244, 180, 0, 0.25);
}

.booking-guests-display {
    width: 110px;
    text-align: center;
    font-size: 34px;
    font-weight: 700;
    padding: 9px 10px;
}

.booking-hint {
    color: #64748b;
    font-size: 13px;
    line-height: 1.45;
}

.booking-summary {
    display: grid;
    gap: 12px;
    margin-top: 6px;
    padding: 16px;
    border-radius: 18px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}

.booking-summary-item {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 14px;
}

.booking-summary-item span:first-child {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.booking-summary-item span:last-child {
    text-align: right;
    font-size: 15px;
    font-weight: 600;
    color: #111827;
}

.booking-actions {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 22px;
}

.booking-btn {
    min-width: 122px;
    border: none;
    border-radius: 16px;
    padding: 14px 20px;
    font-size: 15px;
    font-weight: 700;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.booking-btn:hover {
    transform: translateY(-1px);
}

.booking-btn-secondary {
    background: #e5e7eb;
    color: #64748b;
}

.booking-btn-primary {
    margin-left: auto;
    background: linear-gradient(135deg, #b97aa5 0%, #a85583 100%);
    color: #ffffff;
    box-shadow: 0 16px 34px rgba(168, 85, 131, 0.24);
}

.booking-alert {
    border-radius: 18px;
    border: 1px solid #fecaca;
    background: #fff1f2;
    color: #be123c;
    padding: 14px 16px;
    font-size: 14px;
    margin-bottom: 18px;
}

.booking-inline-error {
    display: none;
    color: #be123c;
    font-size: 13px;
    font-weight: 600;
    margin-top: 6px;
}

@media (max-width: 991px) {
    .booking-topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .booking-topbar-aside {
        width: 100%;
        justify-items: stretch;
    }

    .booking-layout {
        grid-template-columns: 1fr;
    }

    .booking-progress {
        justify-content: flex-start;
    }

    .booking-account-card {
        width: 100%;
    }

    .booking-calendar-top {
        grid-template-columns: 1fr;
        min-height: 0;
    }

    .booking-selected-date {
        min-height: 150px;
    }
}

@media (max-width: 640px) {
    .booking-shell {
        padding-left: 12px;
        padding-right: 12px;
    }

    .booking-stage {
        padding: 18px;
        border-radius: 22px;
    }

    .booking-heading h2 {
        font-size: 26px;
    }

    .booking-field-grid {
        grid-template-columns: 1fr;
    }

    .booking-step-card {
        padding: 18px;
        border-radius: 20px;
    }

    .booking-actions {
        flex-direction: column-reverse;
    }

    .booking-btn,
    .booking-btn-primary {
        width: 100%;
        margin-left: 0;
    }
}
</style>

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
                            <p class="booking-account-title is-inline">Have an Account? <a class="booking-mini-link" href="../auth/login.php">Log in</a> for a faster booking.</p>
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

        <form action="process-booking.php" method="POST" id="booking-form" novalidate>
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
                                60-minute request
                            </span>
                        </div>

                        <div class="booking-field-grid">
                            <div class="booking-field full-width">
                                <label for="number-of-guests">How many people are coming?</label>
                                <div class="booking-guests-control">
                                    <button type="button" class="booking-guest-btn" id="decreaseGuestsBtn" aria-label="Decrease guests">−</button>
                                    <input type="number" name="number_of_guests" id="number-of-guests" class="booking-guests-display" min="1" value="2" required>
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
                            <div class="booking-field full-width">
                                <label for="special-request">Add Note or Special Requirements</label>
                                <textarea name="special_request" id="special-request" class="booking-textarea" placeholder="Birthday celebration, pram space, allergy note, preferred seating, or anything the team should know."></textarea>
                            </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bookingForm = document.getElementById('booking-form');
    const bookingDateInput = document.getElementById('booking-date');
    const startTimeInput = document.getElementById('start-time');
    const endTimeInput = document.getElementById('end-time');
    const guestCountInput = document.getElementById('number-of-guests');
    const customerNameInput = document.getElementById('customer-name');
    const customerEmailInput = document.getElementById('customer-email');
    const customerPhoneInput = document.getElementById('customer-phone');
    const customerPhoneCountryInput = document.getElementById('customer-phone-country');
    const customerPhoneLocalInput = document.getElementById('customer-phone-local');
    const submitBtn = document.getElementById('submit-btn');
    const goToDetailsBtn = document.getElementById('goToDetailsBtn');
    const backToBookingBtn = document.getElementById('backToBookingBtn');
    const decreaseGuestsBtn = document.getElementById('decreaseGuestsBtn');
    const increaseGuestsBtn = document.getElementById('increaseGuestsBtn');
    const bookingStepCardBooking = document.getElementById('bookingStepCardBooking');
    const bookingStepCardDetails = document.getElementById('bookingStepCardDetails');
    const bookingStepBooking = document.getElementById('bookingStepBooking');
    const bookingStepDetails = document.getElementById('bookingStepDetails');
    const guestCountError = document.getElementById('guest-count-error');
    const selectedYearLabel = document.getElementById('selectedYearLabel');
    const selectedDayLabel = document.getElementById('selectedDayLabel');
    const selectedDateLabel = document.getElementById('selectedDateLabel');
    const summaryDateText = document.getElementById('summaryDateText');
    const summaryTimeText = document.getElementById('summaryTimeText');
    const summaryGuestsText = document.getElementById('summaryGuestsText');
    const calendarMonthLabel = document.getElementById('calendarMonthLabel');
    const calendarDays = document.getElementById('bookingCalendarDays');
    const calendarPrevBtn = document.getElementById('calendarPrevBtn');
    const calendarNextBtn = document.getElementById('calendarNextBtn');
    const todayString = '<?= $defaultBookingDate ?>';
    let selectedDate = bookingDateInput.value || todayString;
    let visibleMonth = selectedDate.slice(0, 7);

    function parseLocalDate(dateString) {
        const [year, month, day] = String(dateString).split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function formatLocalDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatDisplayDate(dateString, options) {
        return parseLocalDate(dateString).toLocaleDateString(undefined, options);
    }

    function getEndTime(startTimeValue) {
        const [hours, minutes] = String(startTimeValue).split(':').map(Number);
        const date = new Date(2000, 0, 1, hours, minutes || 0, 0);
        date.setMinutes(date.getMinutes() + <?= (int)$restaurantHours['minDuration'] ?>);
        return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    function syncSummary() {
        bookingDateInput.value = selectedDate;
        endTimeInput.value = getEndTime(startTimeInput.value);
        selectedYearLabel.textContent = formatDisplayDate(selectedDate, { year: 'numeric' });
        selectedDayLabel.textContent = formatDisplayDate(selectedDate, { weekday: 'short', day: '2-digit' });
        selectedDateLabel.textContent = formatDisplayDate(selectedDate, { month: 'long', day: '2-digit' });
        summaryDateText.textContent = formatDisplayDate(selectedDate, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        summaryTimeText.textContent = `${formatTimeLabel(startTimeInput.value)} to ${formatTimeLabel(endTimeInput.value)}`;
        summaryGuestsText.textContent = `${guestCountInput.value || 0} ${Number(guestCountInput.value || 0) === 1 ? 'guest' : 'guests'}`;
    }

    function formatTimeLabel(timeValue) {
        if(!timeValue) {
            return '';
        }

        const [hours, minutes] = String(timeValue).split(':').map(Number);
        const date = new Date(2000, 0, 1, hours, minutes || 0, 0);
        return date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    }

    function renderCalendar() {
        const [year, month] = visibleMonth.split('-').map(Number);
        const firstDate = new Date(year, month - 1, 1);
        const lastDate = new Date(year, month, 0);
        const offset = (firstDate.getDay() + 6) % 7;
        const totalSlots = Math.ceil((offset + lastDate.getDate()) / 7) * 7;
        const startDate = new Date(year, month - 1, 1 - offset);
        calendarMonthLabel.textContent = firstDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        calendarDays.innerHTML = '';

        for(let index = 0; index < totalSlots; index += 1) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + index);
            const currentDateValue = formatLocalDate(currentDate);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'booking-day';
            button.textContent = String(currentDate.getDate());

            if(currentDate.getMonth() !== firstDate.getMonth()) {
                button.classList.add('is-muted');
            }

            if(currentDateValue < todayString) {
                button.disabled = true;
            }

            if(currentDateValue === todayString) {
                button.classList.add('is-today');
            }

            if(currentDateValue === selectedDate) {
                button.classList.add('is-selected');
            }

            button.addEventListener('click', function() {
                if(button.disabled) {
                    return;
                }
                selectedDate = currentDateValue;
                visibleMonth = selectedDate.slice(0, 7);
                syncSummary();
                renderCalendar();
            });

            calendarDays.appendChild(button);
        }
    }

    function showBookingStep() {
        bookingStepCardBooking.hidden = false;
        bookingStepCardDetails.hidden = true;
        bookingStepBooking.classList.add('is-active');
        bookingStepBooking.classList.remove('is-complete');
        bookingStepDetails.classList.remove('is-active');
    }

    function showDetailsStep() {
        bookingStepCardBooking.hidden = true;
        bookingStepCardDetails.hidden = false;
        bookingStepBooking.classList.remove('is-active');
        bookingStepBooking.classList.add('is-complete');
        bookingStepDetails.classList.add('is-active');
    }

    function validateBookingStep() {
        const guestCount = Number(guestCountInput.value || 0);
        guestCountError.style.display = 'none';

        if(!selectedDate) {
            window.alert('Please choose a booking date.');
            return false;
        }

        if(!startTimeInput.value) {
            window.alert('Please choose a preferred time.');
            return false;
        }

        if(!guestCount || guestCount < 1) {
            guestCountError.textContent = 'Please choose at least 1 guest.';
            guestCountError.style.display = 'block';
            guestCountInput.focus();
            return false;
        }

        return true;
    }

    function validateDetailsStep() {
        const normalizedPhone = String(customerPhoneLocalInput.value || '').trim();
        const normalizedDigits = normalizedPhone.replace(/[^\d]/g, '');

        if(customerNameInput.value.trim().length < 2) {
            window.alert('Please enter your full name.');
            customerNameInput.focus();
            return false;
        }

        if(!customerEmailInput.value.trim()) {
            window.alert('Please enter your email address.');
            customerEmailInput.focus();
            return false;
        }

        if(!customerEmailInput.checkValidity()) {
            window.alert('Please enter a valid email address.');
            customerEmailInput.focus();
            return false;
        }

        if(!normalizedPhone) {
            window.alert('Please enter your phone number.');
            customerPhoneLocalInput.focus();
            return false;
        }

        if(normalizedDigits.length < 6) {
            window.alert('Please enter a valid phone number.');
            customerPhoneLocalInput.focus();
            return false;
        }

        customerPhoneInput.value = `${customerPhoneCountryInput.value} ${normalizedPhone}`.trim();
        return true;
    }

    function adjustGuests(delta) {
        const currentValue = Number(guestCountInput.value || 0);
        const nextValue = Math.max(1, currentValue + delta);
        guestCountInput.value = String(nextValue);
        guestCountError.style.display = 'none';
        syncSummary();
    }

    decreaseGuestsBtn.addEventListener('click', function() {
        adjustGuests(-1);
    });

    increaseGuestsBtn.addEventListener('click', function() {
        adjustGuests(1);
    });

    guestCountInput.addEventListener('input', function() {
        if(Number(guestCountInput.value || 0) < 1) {
            guestCountInput.value = '1';
        }
        guestCountError.style.display = 'none';
        syncSummary();
    });

    startTimeInput.addEventListener('change', syncSummary);

    goToDetailsBtn.addEventListener('click', function() {
        if(!validateBookingStep()) {
            return;
        }

        showDetailsStep();
        requestAnimationFrame(function() {
            customerNameInput.focus();
        });
    });

    backToBookingBtn.addEventListener('click', function() {
        showBookingStep();
    });

    calendarPrevBtn.addEventListener('click', function() {
        const [year, month] = visibleMonth.split('-').map(Number);
        const date = new Date(year, month - 2, 1);
        visibleMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        renderCalendar();
    });

    calendarNextBtn.addEventListener('click', function() {
        const [year, month] = visibleMonth.split('-').map(Number);
        const date = new Date(year, month, 1);
        visibleMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        renderCalendar();
    });

    bookingForm.addEventListener('submit', function(event) {
        if(!validateBookingStep()) {
            event.preventDefault();
            showBookingStep();
            return;
        }

        if(!validateDetailsStep()) {
            event.preventDefault();
            showDetailsStep();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
    });

    syncSummary();
    renderCalendar();
    showBookingStep();
});
</script>

<?php include "../includes/footer.php"; ?>

