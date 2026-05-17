<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

requireCustomer();
ensureBookingRequestColumns($pdo);
ensureBookingReviewsSchema($pdo);
ensureSettingsSchema($pdo);
$bookingSettings = getBookingSettings($pdo);

$userId = (int) getCurrentUserId();
$bookings = getCustomerPortalBookings($pdo, $userId);
$customerBookingActionCsrfToken = csrfToken('customer_booking_action');

$view = strtolower(trim((string) ($_GET['view'] ?? 'upcoming')));
$allowedViews = ['upcoming', 'past', 'cancelled', 'no_show', 'all'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'upcoming';
}

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$allowedStatusFilters = array_merge(['all'], getBookingStatuses());
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$search = trim((string) ($_GET['q'] ?? ''));
$today = date('Y-m-d');
$now = strtotime(date('Y-m-d H:i:s'));

$counts = [
    'upcoming' => 0,
    'past' => 0,
    'cancelled' => 0,
    'no_show' => 0,
    'all' => count($bookings),
];

$filteredBookings = [];

foreach ($bookings as $booking) {
    $status = strtolower((string) ($booking['status'] ?? 'pending'));
    $bookingTimestamp = strtotime((string) ($booking['booking_date'] ?? '') . ' ' . (string) ($booking['start_time'] ?? '00:00:00'));
    $isUpcoming = in_array($status, getBookingActiveStatuses(), true) && $bookingTimestamp !== false && $bookingTimestamp >= $now;
    $isPastBucket = !$isUpcoming && !in_array($status, ['cancelled', 'no_show'], true);

    if ($isUpcoming) {
        $counts['upcoming']++;
    }
    if ($isPastBucket) {
        $counts['past']++;
    }
    if ($status === 'cancelled') {
        $counts['cancelled']++;
    }
    if ($status === 'no_show') {
        $counts['no_show']++;
    }

    if ($view === 'upcoming' && !$isUpcoming) {
        continue;
    }
    if ($view === 'past' && !$isPastBucket) {
        continue;
    }
    if ($view === 'cancelled' && $status !== 'cancelled') {
        continue;
    }
    if ($view === 'no_show' && $status !== 'no_show') {
        continue;
    }

    if ($statusFilter !== 'all' && $status !== $statusFilter) {
        continue;
    }

    if ($search !== '') {
        $haystack = strtolower(implode(' ', [
            (string) ($booking['booking_date'] ?? ''),
            (string) ($booking['table_number'] ?? ''),
            (string) ($booking['special_request'] ?? ''),
            (string) getBookingSourceLabel($booking['booking_source'] ?? ''),
            (string) getBookingStatusLabel($booking['status'] ?? ''),
        ]));
        if (strpos($haystack, strtolower($search)) === false) {
            continue;
        }
    }

    $filteredBookings[] = $booking;
}
?>

<?php
$pageTitle = 'Reservations | DineMate';
$extraStylesheets = ['assets/css/pages/customer-bookings.css'];
include '../includes/header.php';
?>


<div class="container bookings-wrapper">
    <div class="bookings-shell">
        <div class="bookings-hero">
            <div>
                <h2>Your Reservations</h2>
                <p>See what is booked, revisit past plans, and book something similar when it feels right.</p>
            </div>
            <div class="hero-actions">
                <a href="dashboard.php" class="btn-surface"><i class="fa fa-gauge"></i> Dashboard</a>
                <a href="book-table.php" class="btn-primary-solid"><i class="fa fa-calendar-plus"></i> Book a Table</a>
            </div>
        </div>

        <div class="view-tabs">
            <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled', 'no_show' => 'No-show', 'all' => 'All'] as $key => $label): ?>
                <a class="view-tab <?php echo $view === $key ? 'is-active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query(['view' => $key, 'status' => $statusFilter, 'q' => $search]), ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?php echo number_format((int) ($counts[$key] ?? 0)); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="filter-row">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="search" name="q" class="filter-input" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search date, table, note, or status">
            <select name="status" class="filter-select">
                <option value="all">All statuses</option>
                <?php foreach (getBookingStatuses() as $statusOption): ?>
                    <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statusFilter === $statusOption ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(getBookingStatusLabel($statusOption), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-surface dm-justify-center"><i class="fa fa-filter"></i> Apply</button>
        </form>

        <div class="hint-card">
            Pending and confirmed reservations can be rescheduled or cancelled from this page.
        </div>

        <?php if (!empty($filteredBookings)): ?>
            <div class="booking-grid">
                <?php foreach ($filteredBookings as $booking): ?>
                    <?php
                    $status = strtolower((string) ($booking['status'] ?? 'pending'));
                    $isEditable = in_array($status, getBookingActiveStatuses(), true) && $bookingSettings['allow_booking_modification'];
                    $rebookUrl = 'book-table.php?' . http_build_query([
                        'rebook' => (int) $booking['booking_id'],
                        'date' => (string) ($booking['booking_date'] ?? ''),
                        'time' => date('H:i', strtotime((string) ($booking['start_time'] ?? '12:00:00'))),
                        'guests' => (int) ($booking['number_of_guests'] ?? 2),
                        'special' => (string) ($booking['special_request'] ?? ''),
                    ]);
                    ?>
                    <article class="booking-card">
                        <div class="booking-card-top">
                            <div>
                                <div class="booking-card-title"><?php echo htmlspecialchars(date('D, j M Y', strtotime((string) $booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="booking-card-subtitle"><?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <span class="status-tag <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(getBookingStatusLabel($status), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                        <div class="booking-chip-list">
                            <span class="booking-chip"><i class="fa fa-users"></i> <?php echo (int) ($booking['number_of_guests'] ?? 0); ?> guests</span>
                            <span class="booking-chip"><i class="fa fa-table-cells"></i> <?php echo !empty($booking['table_number']) ? 'Table ' . htmlspecialchars((string) $booking['table_number'], ENT_QUOTES, 'UTF-8') : 'Pending assignment'; ?></span>
                        </div>

                        <div class="booking-details-list">
                            <?php if (!empty($booking['special_request'])): ?>
                                <div class="booking-detail"><strong>Saved note:</strong> <?php echo htmlspecialchars((string) $booking['special_request'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($booking['review_rating'])): ?>
                                <div class="booking-detail"><strong>Review:</strong> Rated <?php echo (int) $booking['review_rating']; ?>/5<?php if (!empty($booking['review_comment'])): ?> - <?php echo htmlspecialchars((string) $booking['review_comment'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></div>
                            <?php elseif ($status === 'completed'): ?>
                                <div class="booking-detail booking-review-hint"><strong>Review ready:</strong> Share a quick note about this visit.</div>
                            <?php endif; ?>
                        </div>

                        <div class="booking-actions">
                            <?php if ($isEditable): ?>
                                <a href="modify-booking.php?id=<?php echo (int) $booking['booking_id']; ?>" class="btn-surface"><i class="fa fa-pen"></i> Reschedule</a>
                                <form method="POST" action="cancel-booking.php" class="inline-action-form" onsubmit="return confirm('Cancel this booking?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($customerBookingActionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) $booking['booking_id']; ?>">
                                    <button type="submit" class="btn-surface"><i class="fa fa-ban"></i> Cancel</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($status === 'completed' && empty($booking['review_rating'])): ?>
                                <a href="rate-booking.php?id=<?php echo (int) $booking['booking_id']; ?>" class="btn-surface"><i class="fa fa-star"></i> Review Visit</a>
                            <?php elseif (!empty($booking['review_rating'])): ?>
                                <span class="rating-chip"><i class="fa fa-star"></i> Rated <?php echo (int) $booking['review_rating']; ?>/5</span>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars($rebookUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-primary-solid"><i class="fa fa-repeat"></i> Rebook</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No reservations found.</p>
                <a href="book-table.php" class="btn-primary-solid dm-mt-10"><i class="fa fa-calendar-plus"></i> Book a Table</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
