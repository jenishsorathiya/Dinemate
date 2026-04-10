<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

requireCustomer();
ensureBookingRequestColumns($pdo);

$userId = (int) getCurrentUserId();
$customerProfile = ensureCustomerProfileForUser($pdo, $userId);
$bookings = getCustomerPortalBookings($pdo, $userId);

$upcomingBookings = [];
$pastBookings = [];
$completedCount = 0;
$cancelledCount = 0;
$noShowCount = 0;
$adminCreatedCount = 0;
$guestHistoryCount = 0;
$totalGuestsHosted = 0;
$lastCompletedBooking = null;

$today = date('Y-m-d');
$now = strtotime(date('Y-m-d H:i:s'));

foreach ($bookings as $booking) {
    $status = strtolower((string) ($booking['status'] ?? 'pending'));
    $bookingTimestamp = strtotime((string) ($booking['booking_date'] ?? '') . ' ' . (string) ($booking['start_time'] ?? '00:00:00'));
    $isUpcoming = in_array($status, getBookingActiveStatuses(), true) && $bookingTimestamp !== false && $bookingTimestamp >= $now;

    if ($isUpcoming) {
        $upcomingBookings[] = $booking;
    } else {
        $pastBookings[] = $booking;
    }

    if ($status === 'completed') {
        $completedCount++;
        $totalGuestsHosted += (int) ($booking['number_of_guests'] ?? 0);
        if ($lastCompletedBooking === null) {
            $lastCompletedBooking = $booking;
        }
    } elseif ($status === 'cancelled') {
        $cancelledCount++;
    } elseif ($status === 'no_show') {
        $noShowCount++;
    }

    if (($booking['booking_source'] ?? '') === 'admin_manual') {
        $adminCreatedCount++;
    } elseif (($booking['booking_source'] ?? '') === 'guest_web') {
        $guestHistoryCount++;
    }
}

$nextBooking = $upcomingBookings[0] ?? null;
$recentHistory = array_slice($pastBookings, 0, 5);
$visitCount = $completedCount;
$averagePartySize = $completedCount > 0 ? round($totalGuestsHosted / $completedCount, 1) : 0;
$favouriteBookingTime = $customerProfile['preferred_booking_time'] ?? '';
$seatingPreference = trim((string) ($customerProfile['seating_preference'] ?? ''));
$dietaryNotes = trim((string) ($customerProfile['dietary_notes'] ?? ''));
$notes = trim((string) ($customerProfile['notes'] ?? ''));
?>

<?php include "../includes/header.php"; ?>

<style>
.customer-dashboard {
    margin-top: 118px;
    margin-bottom: 84px;
}

.dashboard-shell {
    display: grid;
    gap: 22px;
}

.dashboard-hero,
.dashboard-panel {
    background: #ffffff;
    border: 1px solid #e7ecf3;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
}

.dashboard-hero {
    padding: 28px;
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.9fr);
    gap: 20px;
    align-items: stretch;
    background: #ffffff;
}

.hero-copy h1 {
    margin: 0;
    font-size: 36px;
    line-height: 1.04;
    color: #162033;
}

.hero-copy p {
    margin: 12px 0 0;
    max-width: 620px;
    color: #64748b;
    font-size: 15px;
}

.hero-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 22px;
}

.btn-portal,
.btn-portal-secondary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border-radius: 8px;
    padding: 12px 16px;
    text-decoration: none;
    font-weight: 700;
}

.btn-portal {
    background: #1d2840;
    color: #ffffff;
    box-shadow: 0 14px 28px rgba(29, 40, 64, 0.16);
}

.btn-portal-secondary {
    border: 1px solid #d9e1ec;
    background: #ffffff;
    color: #31415f;
}

.hero-focus {
    border-radius: 10px;
    background: #172133;
    color: #f8fafc;
    padding: 22px;
    display: grid;
    gap: 14px;
}

.hero-focus-label {
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 12px;
    font-weight: 700;
}

.hero-focus-title {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.1;
}

.hero-focus-meta {
    display: grid;
    gap: 8px;
    color: rgba(255, 255, 255, 0.84);
}

.metric-grid,
.dashboard-columns,
.dashboard-lower {
    display: grid;
    gap: 18px;
}

.metric-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.metric-card {
    background: #ffffff;
    border: 1px solid #e7ecf3;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.metric-label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    font-weight: 700;
}

.metric-value {
    margin-top: 10px;
    font-size: 24px;
    line-height: 1;
    color: #162033;
    font-weight: 700;
}

.metric-meta {
    margin-top: 10px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.dashboard-columns {
    grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
}

.dashboard-lower {
    grid-template-columns: minmax(0, 1fr) minmax(300px, 0.8fr);
}

.dashboard-panel {
    padding: 24px;
}

.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}

.panel-heading h2,
.panel-heading h3 {
    margin: 0;
    color: #162033;
    font-size: 22px;
}

.panel-heading p {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 14px;
}

.booking-timeline {
    display: grid;
    gap: 14px;
}

.timeline-card {
    border: 1px solid #e7ecf3;
    border-radius: 10px;
    padding: 18px;
    display: grid;
    gap: 12px;
    background: #f9fbfd;
}

.timeline-card-top,
.timeline-card-meta,
.mini-stat-list,
.history-list,
.profile-notes {
    display: grid;
    gap: 10px;
}

.timeline-card-top {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
}

.timeline-card-title {
    font-size: 19px;
    font-weight: 700;
    color: #162033;
}

.timeline-card-subtitle,
.history-meta,
.tiny-copy {
    color: #64748b;
    font-size: 13px;
}



.timeline-card-meta {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.timeline-meta-chip,
.history-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #dbe3ef;
    padding: 6px 10px;
    font-size: 13px;
    color: #31415f;
    width: fit-content;
}

.history-item {
    padding: 14px 0;
    border-top: 1px solid #eef2f7;
}

.history-item:first-child {
    border-top: none;
    padding-top: 0;
}

.history-row {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.profile-chip {
    border-radius: 10px;
    border: 1px solid #e7ecf3;
    background: #f8fafc;
    padding: 14px;
}

.profile-chip strong {
    display: block;
    color: #162033;
    font-size: 14px;
}

.profile-chip span {
    display: block;
    margin-top: 6px;
    color: #64748b;
    font-size: 13px;
}

.notes-card {
    border-radius: 8px;
    background: #fffaf0;
    border: 1px solid #f7e1b5;
    padding: 16px;
}

.empty-state {
    border-radius: 8px;
    background: #f8fafc;
    border: 1px dashed #d9e1ec;
    padding: 26px;
    color: #64748b;
    text-align: center;
}

@media (max-width: 991px) {
    .dashboard-hero,
    .dashboard-columns,
    .dashboard-lower,
    .metric-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container customer-dashboard">
    <div class="dashboard-shell">
        <section class="dashboard-hero">
            <div class="hero-copy">
                <h1>Welcome back, <?php echo htmlspecialchars((string) getCurrentUserName(), ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>Manage your bookings, profile, and dining preferences.</p>
                <div class="hero-actions">
                    <a class="btn-portal" href="book-table.php"><i class="fa fa-calendar-plus"></i> New Booking</a>
                    <a class="btn-portal-secondary" href="my-bookings.php"><i class="fa fa-clock-rotate-left"></i> View Booking History</a>
                    <a class="btn-portal-secondary" href="profile.php"><i class="fa fa-user-gear"></i> Update Profile</a>
                </div>
            </div>
            <aside class="hero-focus">
                <div class="hero-focus-label">Next Up</div>
                <?php if ($nextBooking): ?>
                    <div class="hero-focus-title"><?php echo htmlspecialchars(date('D, j M', strtotime((string) $nextBooking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?> at <?php echo htmlspecialchars(date('g:i A', strtotime((string) $nextBooking['start_time'])), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="hero-focus-meta">
                        <span><i class="fa fa-users"></i> <?php echo (int) ($nextBooking['number_of_guests'] ?? 0); ?> guests</span>
                        <span><i class="fa fa-table-cells-large"></i> <?php echo !empty($nextBooking['table_number']) ? 'Table ' . htmlspecialchars((string) $nextBooking['table_number'], ENT_QUOTES, 'UTF-8') : 'Pending assignment'; ?></span>
                        <span><i class="fa fa-circle-info"></i> <?php echo htmlspecialchars(getBookingStatusLabel($nextBooking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div>
                        <a class="btn-portal-secondary" href="modify-booking.php?id=<?php echo (int) $nextBooking['booking_id']; ?>"><i class="fa fa-pen"></i> Reschedule</a>
                    </div>
                <?php else: ?>
                    <div class="hero-focus-title">No upcoming bookings</div>
                    <div class="hero-focus-meta">
                        <span>No upcoming reservations scheduled.</span>
                    </div>
                <?php endif; ?>
            </aside>
        </section>

        <section class="metric-grid">
            <article class="metric-card">
                <div class="metric-label">Completed Visits</div>
                <div class="metric-value"><?php echo number_format($visitCount); ?></div>
                <div class="metric-meta">Completed reservations in your history.</div>
            </article>
            <article class="metric-card">
                <div class="metric-label">Upcoming</div>
                <div class="metric-value"><?php echo number_format(count($upcomingBookings)); ?></div>
                <div class="metric-meta">Pending and confirmed reservations.</div>
            </article>
            <article class="metric-card">
                <div class="metric-label">Average Party</div>
                <div class="metric-value"><?php echo htmlspecialchars(number_format($averagePartySize, $averagePartySize == floor($averagePartySize) ? 0 : 1), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="metric-meta">Average guests per booking.</div>
            </article>
            <article class="metric-card">
                <div class="metric-label">Shared History</div>
                <div class="metric-value"><?php echo number_format($guestHistoryCount + $adminCreatedCount); ?></div>
                <div class="metric-meta">Bookings linked to your customer profile.</div>
            </article>
        </section>

        <section class="dashboard-columns">
            <article class="dashboard-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Upcoming And Rebook</h2>
                        <p>Manage upcoming reservations and rebook recent visits.</p>
                    </div>
                    <a class="btn-portal-secondary" href="my-bookings.php?view=upcoming">Open My Bookings</a>
                </div>

                <?php if (!empty($upcomingBookings)): ?>
                    <div class="booking-timeline">
                        <?php foreach (array_slice($upcomingBookings, 0, 3) as $booking): ?>
                            <article class="timeline-card">
                                <div class="timeline-card-top">
                                    <div>
                                        <div class="timeline-card-title"><?php echo htmlspecialchars(date('l, j F', strtotime((string) $booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="timeline-card-subtitle"><?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <span class="status-tag <?php echo htmlspecialchars((string) ($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(getBookingStatusLabel($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="timeline-card-meta">
                                    <span class="timeline-meta-chip"><i class="fa fa-users"></i> <?php echo (int) ($booking['number_of_guests'] ?? 0); ?> guests</span>
                                    <span class="timeline-meta-chip"><i class="fa fa-table-cells"></i> <?php echo !empty($booking['table_number']) ? 'Table ' . htmlspecialchars((string) $booking['table_number'], ENT_QUOTES, 'UTF-8') : 'Pending assignment'; ?></span>
                                    <span class="timeline-meta-chip"><i class="fa fa-location-dot"></i> <?php echo htmlspecialchars(getBookingPlacementLabel($booking['reservation_card_status'] ?? 'not_placed'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="timeline-meta-chip"><i class="fa fa-diagram-project"></i> <?php echo htmlspecialchars(getBookingSourceLabel($booking['booking_source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="hero-actions" style="margin-top:0;">
                                    <a class="btn-portal-secondary" href="modify-booking.php?id=<?php echo (int) $booking['booking_id']; ?>"><i class="fa fa-pen"></i> Reschedule</a>
                                    <a class="btn-portal-secondary" href="cancel-booking.php?id=<?php echo (int) $booking['booking_id']; ?>" onclick="return confirm('Cancel this booking request?');"><i class="fa fa-ban"></i> Cancel</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">You do not have any active bookings.</div>
                <?php endif; ?>
            </article>

            <article class="dashboard-panel">
                <div class="panel-heading">
                    <div>
                        <h3>Customer Snapshot</h3>
                        <p>Saved profile and booking preferences.</p>
                    </div>
                </div>
                <div class="profile-grid">
                    <div class="profile-chip">
                        <strong>Preferred Time</strong>
                        <span><?php echo htmlspecialchars($favouriteBookingTime !== '' ? ucfirst(str_replace('_', ' ', $favouriteBookingTime)) : 'Not set', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="profile-chip">
                        <strong>Seating Preference</strong>
                        <span><?php echo htmlspecialchars($seatingPreference !== '' ? ucfirst(str_replace('_', ' ', $seatingPreference)) : 'No preference saved', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="profile-chip">
                        <strong>Admin-entered Bookings</strong>
                        <span><?php echo number_format($adminCreatedCount); ?> in your history</span>
                    </div>
                    <div class="profile-chip">
                        <strong>Guest-Web Continuity</strong>
                        <span><?php echo number_format($guestHistoryCount); ?> guest bookings linked to your profile</span>
                    </div>
                </div>

                <div class="profile-notes" style="margin-top:16px;">
                    <div class="notes-card">
                        <strong>Dietary Notes</strong>
                        <div class="tiny-copy" style="margin-top:8px;"><?php echo htmlspecialchars($dietaryNotes !== '' ? $dietaryNotes : 'None', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="notes-card">
                        <strong>Staff Notes</strong>
                        <div class="tiny-copy" style="margin-top:8px;"><?php echo htmlspecialchars($notes !== '' ? $notes : 'None', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </article>
        </section>

        <section class="dashboard-lower">
            <article class="dashboard-panel">
                <div class="panel-heading">
                    <div>
                        <h3>Recent Visit History</h3>
                        <p>Recent bookings and rebooking access.</p>
                    </div>
                    <a class="btn-portal-secondary" href="my-bookings.php?view=past">See Full History</a>
                </div>

                <?php if (!empty($recentHistory)): ?>
                    <div class="history-list">
                        <?php foreach ($recentHistory as $booking): ?>
                            <?php
                            $rebookUrl = 'book-table.php?' . http_build_query([
                                'rebook' => (int) $booking['booking_id'],
                                'date' => (string) ($booking['booking_date'] ?? ''),
                                'time' => date('H:i', strtotime((string) ($booking['start_time'] ?? '12:00:00'))),
                                'guests' => (int) ($booking['number_of_guests'] ?? 2),
                                'special' => (string) ($booking['special_request'] ?? ''),
                            ]);
                            ?>
                            <div class="history-item">
                                <div class="history-row">
                                    <div>
                                        <div class="timeline-card-title" style="font-size:17px;"><?php echo htmlspecialchars(date('D, j M Y', strtotime((string) $booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="history-meta"><?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) ($booking['number_of_guests'] ?? 0); ?> guests</div>
                                    </div>
                                    <span class="status-tag <?php echo htmlspecialchars((string) ($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(getBookingStatusLabel($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="hero-actions" style="margin-top:12px;">
                                    <span class="history-chip"><i class="fa fa-diagram-project"></i> <?php echo htmlspecialchars(getBookingSourceLabel($booking['booking_source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="history-chip"><i class="fa fa-table-cells"></i> <?php echo !empty($booking['table_number']) ? 'Table ' . htmlspecialchars((string) $booking['table_number'], ENT_QUOTES, 'UTF-8') : 'Unassigned'; ?></span>
                                    <a class="btn-portal-secondary" href="<?php echo htmlspecialchars($rebookUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-repeat"></i> Rebook</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">Completed visits will appear here.</div>
                <?php endif; ?>
            </article>

            <article class="dashboard-panel">
                <div class="panel-heading">
                    <div>
                        <h3>Loyalty Snapshot</h3>
                        <p>Summary of your booking activity.</p>
                    </div>
                </div>
                <div class="mini-stat-list">
                    <div class="profile-chip">
                        <strong>Last Completed Visit</strong>
                        <span><?php echo $lastCompletedBooking ? htmlspecialchars(date('j M Y', strtotime((string) $lastCompletedBooking['booking_date'])), ENT_QUOTES, 'UTF-8') : 'None'; ?></span>
                    </div>
                    <div class="profile-chip">
                        <strong>Cancelled Bookings</strong>
                        <span><?php echo number_format($cancelledCount); ?> cancelled in your booking history</span>
                    </div>
                    <div class="profile-chip">
                        <strong>No-shows</strong>
                        <span><?php echo number_format($noShowCount); ?> marked no-show</span>
                    </div>
                    <div class="profile-chip">
                        <strong>Reminder Preferences</strong>
                        <span><?php echo !empty($customerProfile['email_reminders_enabled']) ? 'Email reminders on' : 'Email reminders off'; ?>, <?php echo !empty($customerProfile['sms_reminders_enabled']) ? 'SMS reminders on' : 'SMS reminders off'; ?></span>
                    </div>
                </div>
            </article>
        </section>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
