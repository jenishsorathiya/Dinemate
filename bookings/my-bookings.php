<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

requireCustomer();
ensureBookingRequestColumns($pdo);

$userId = (int) getCurrentUserId();
$bookings = getCustomerPortalBookings($pdo, $userId);

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

<?php include "../includes/header.php"; ?>

<style>
.bookings-wrapper {
    margin-top: 118px;
    margin-bottom: 84px;
}

.bookings-shell,
.booking-card {
    background: #ffffff;
    border: 1px solid #e7ecf3;
    box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
}

.bookings-shell {
    border-radius: 24px;
    padding: 28px;
}

.bookings-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.bookings-hero h2 {
    margin: 0;
    color: #162033;
}

.bookings-hero p {
    margin: 10px 0 0;
    color: #64748b;
    max-width: 640px;
}

.hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.btn-surface,
.btn-primary-solid {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border-radius: 14px;
    padding: 12px 16px;
    text-decoration: none;
    font-weight: 700;
}

.btn-surface {
    background: #ffffff;
    border: 1px solid #d9e1ec;
    color: #31415f;
}

.btn-primary-solid {
    background: #1d2840;
    color: #ffffff;
    border: 1px solid #1d2840;
}

.view-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 22px;
}

.view-tab {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border-radius: 999px;
    padding: 10px 14px;
    background: #f8fafc;
    border: 1px solid #dbe3ef;
    color: #31415f;
    text-decoration: none;
    font-weight: 700;
}

.view-tab.is-active {
    background: #1d2840;
    border-color: #1d2840;
    color: #ffffff;
}

.filter-row {
    margin-top: 22px;
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) repeat(2, minmax(180px, 0.45fr));
    gap: 14px;
}

.filter-input,
.filter-select {
    width: 100%;
    border: 1px solid #d9e1ec;
    border-radius: 14px;
    padding: 13px 14px;
    background: #ffffff;
}

.booking-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 18px;
    margin-top: 24px;
}

.booking-card {
    border-radius: 20px;
    padding: 20px;
    display: grid;
    gap: 16px;
}

.booking-card-top {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
}

.booking-card-title {
    font-size: 20px;
    font-weight: 700;
    color: #162033;
}

.booking-card-subtitle {
    margin-top: 6px;
    color: #64748b;
    font-size: 13px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 700;
}

.status-pill.pending { background: #fff4df; color: #b66a11; }
.status-pill.confirmed { background: #e6f7ee; color: #1d7a53; }
.status-pill.completed { background: #e6f7ee; color: #1d7a53; }
.status-pill.cancelled { background: #ffe7ea; color: #c13f56; }
.status-pill.no_show { background: #eef2ff; color: #4338ca; }

.booking-chip-list,
.booking-details-list,
.booking-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.booking-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    border: 1px solid #dbe3ef;
    background: #f8fafc;
    color: #31415f;
    padding: 8px 12px;
    font-size: 13px;
}

.booking-detail {
    width: 100%;
    color: #475569;
    font-size: 14px;
    line-height: 1.55;
}

.empty-state {
    margin-top: 24px;
    border: 1px dashed #d9e1ec;
    border-radius: 20px;
    background: #f8fafc;
    padding: 34px 22px;
    text-align: center;
    color: #64748b;
}

.hint-card {
    margin-top: 20px;
    border-radius: 18px;
    background: #fffaf0;
    border: 1px solid #f7e1b5;
    padding: 16px 18px;
    color: #6a5320;
    font-size: 14px;
}

@media (max-width: 991px) {
    .filter-row {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container bookings-wrapper">
    <div class="bookings-shell">
        <div class="bookings-hero">
            <div>
                <h2><i class="fa fa-calendar-check text-warning"></i> My Reservations</h2>
                <p>Track upcoming tables, review past visit outcomes, filter by booking status, and rebook a previous reservation without starting from scratch.</p>
            </div>
            <div class="hero-actions">
                <a href="dashboard.php" class="btn-surface"><i class="fa fa-gauge"></i> Dashboard</a>
                <a href="book-table.php" class="btn-primary-solid"><i class="fa fa-calendar-plus"></i> New Booking</a>
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
            <input type="search" name="q" class="filter-input" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by date, table, note, or source...">
            <select name="status" class="filter-select">
                <option value="all">All statuses</option>
                <?php foreach (getBookingStatuses() as $statusOption): ?>
                    <option value="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $statusFilter === $statusOption ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(getBookingStatusLabel($statusOption), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-surface" style="justify-content:center;"><i class="fa fa-filter"></i> Apply Filters</button>
        </form>

        <div class="hint-card">
            Pending and confirmed bookings can still be rescheduled or cancelled. Completed, cancelled, and no-show bookings stay in your history so you can review outcomes and rebook faster.
        </div>

        <?php if (!empty($filteredBookings)): ?>
            <div class="booking-grid">
                <?php foreach ($filteredBookings as $booking): ?>
                    <?php
                    $status = strtolower((string) ($booking['status'] ?? 'pending'));
                    $isEditable = in_array($status, getBookingActiveStatuses(), true);
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
                            <span class="status-pill <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(getBookingStatusLabel($status), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                        <div class="booking-chip-list">
                            <span class="booking-chip"><i class="fa fa-users"></i> <?php echo (int) ($booking['number_of_guests'] ?? 0); ?> guests</span>
                            <span class="booking-chip"><i class="fa fa-table-cells"></i> <?php echo !empty($booking['table_number']) ? 'Table ' . htmlspecialchars((string) $booking['table_number'], ENT_QUOTES, 'UTF-8') : 'Table assignment pending'; ?></span>
                            <span class="booking-chip"><i class="fa fa-location-dot"></i> <?php echo htmlspecialchars(getBookingPlacementLabel($booking['reservation_card_status'] ?? 'not_placed'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="booking-chip"><i class="fa fa-diagram-project"></i> <?php echo htmlspecialchars(getBookingSourceLabel($booking['booking_source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="booking-details-list">
                            <?php if (!empty($booking['special_request'])): ?>
                                <div class="booking-detail"><strong>Saved note:</strong> <?php echo htmlspecialchars((string) $booking['special_request'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <?php if (($booking['booking_source'] ?? '') === 'admin_manual' && !empty($booking['created_by_name'])): ?>
                                <div class="booking-detail"><strong>Entered by:</strong> <?php echo htmlspecialchars((string) $booking['created_by_name'], ENT_QUOTES, 'UTF-8'); ?> from the admin side.</div>
                            <?php endif; ?>
                            <?php if (($booking['booking_source'] ?? '') === 'guest_web'): ?>
                                <div class="booking-detail"><strong>Guest continuity:</strong> This reservation was originally made without logging in and is now attached to your customer history.</div>
                            <?php endif; ?>
                        </div>

                        <div class="booking-actions">
                            <?php if ($isEditable): ?>
                                <a href="modify-booking.php?id=<?php echo (int) $booking['booking_id']; ?>" class="btn-surface"><i class="fa fa-pen"></i> Reschedule</a>
                                <a href="cancel-booking.php?id=<?php echo (int) $booking['booking_id']; ?>" class="btn-surface" onclick="return confirm('Cancel this booking?');"><i class="fa fa-ban"></i> Cancel</a>
                            <?php endif; ?>
                            <a href="<?php echo htmlspecialchars($rebookUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-primary-solid"><i class="fa fa-repeat"></i> Rebook</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No reservations matched this view yet.</p>
                <a href="book-table.php" class="btn-primary-solid" style="margin-top:10px;"><i class="fa fa-calendar-plus"></i> Book Your Next Table</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
