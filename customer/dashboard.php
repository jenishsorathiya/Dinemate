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
$customerProfile = ensureCustomerProfileForUser($pdo, $userId);
$bookings = getCustomerPortalBookings($pdo, $userId);
$customerBookingActionCsrfToken = csrfToken('customer_booking_action');

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

<?php
$pageTitle = 'Customer Dashboard | DineMate';
$extraStylesheets = ['assets/css/pages/customer-dashboard.css'];
include '../includes/header.php';
?>


<div class="container customer-dashboard">
    <div class="dashboard-shell">
        <section class="dashboard-hero">
            <div class="hero-copy">
                <h1>Welcome back, <?php echo htmlspecialchars((string) getCurrentUserName(), ENT_QUOTES, 'UTF-8'); ?></h1>
                <p>Manage your bookings, profile, and dining preferences.</p>
                <div class="hero-actions">
                    <?php if ($bookingSettings['enable_online_bookings']): ?>
                        <a class="btn-portal" href="book-table.php"><i class="fa fa-calendar-plus"></i> New Booking</a>
                    <?php endif; ?>
                    <a class="btn-portal-secondary" href="my-bookings.php"><i class="fa fa-clock-rotate-left"></i> View Booking History</a>
                    <a class="btn-portal-secondary" href="profile.php"><i class="fa fa-user-gear"></i> Update Profile</a>
                    <?php if (!empty($lastCompletedBooking) && empty($lastCompletedBooking['review_rating'])): ?>
                        <a class="btn-portal-secondary" href="rate-booking.php?id=<?php echo (int) $lastCompletedBooking['booking_id']; ?>"><i class="fa fa-star"></i> Rate Last Visit</a>
                    <?php endif; ?>
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
                        <?php if ($bookingSettings['allow_booking_modification']): ?>
                            <a class="btn-portal-secondary" href="modify-booking.php?id=<?php echo (int) $nextBooking['booking_id']; ?>"><i class="fa fa-pen"></i> Reschedule</a>
                        <?php endif; ?>
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
                        <h2>Upcoming Reservations</h2>
                        <p>Review your next bookings, make changes, or rebook a recent visit.</p>
                    </div>
                    <a class="btn-portal-secondary" href="my-bookings.php?view=upcoming">Open Reservations</a>
                </div>

                <?php if (!empty($upcomingBookings)): ?>
                    <div class="reservation-list">
                        <?php foreach (array_slice($upcomingBookings, 0, 3) as $booking): ?>
                            <article class="reservation-card">
                                <div class="reservation-card-top">
                                    <div>
                                        <div class="reservation-card-title"><?php echo htmlspecialchars(date('l, j F', strtotime((string) $booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="reservation-card-subtitle"><?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <span class="status-tag <?php echo htmlspecialchars((string) ($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(getBookingStatusLabel($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="reservation-card-meta">
                                    <span class="reservation-meta-chip"><i class="fa fa-users"></i> <?php echo (int) ($booking['number_of_guests'] ?? 0); ?> guests</span>
                                    <span class="reservation-meta-chip"><i class="fa fa-table-cells"></i> <?php echo !empty($booking['table_number']) ? 'Table ' . htmlspecialchars((string) $booking['table_number'], ENT_QUOTES, 'UTF-8') : 'Pending assignment'; ?></span>
                                    <span class="reservation-meta-chip"><i class="fa fa-location-dot"></i> <?php echo htmlspecialchars(getBookingPlacementLabel($booking['reservation_card_status'] ?? 'not_placed'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="reservation-meta-chip"><i class="fa fa-diagram-project"></i> <?php echo htmlspecialchars(getBookingSourceLabel($booking['booking_source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="hero-actions dm-mt-0">
                                    <?php if ($bookingSettings['allow_booking_modification']): ?>
                                        <a class="btn-portal-secondary" href="modify-booking.php?id=<?php echo (int) $booking['booking_id']; ?>"><i class="fa fa-pen"></i> Reschedule</a>
                                    <?php endif; ?>
                                    <form method="POST" action="cancel-booking.php" class="inline-action-form" onsubmit="return confirm('Cancel this booking request?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($customerBookingActionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $booking['booking_id']; ?>">
                                        <button type="submit" class="btn-portal-secondary"><i class="fa fa-ban"></i> Cancel</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No upcoming reservations yet. Start a new booking when you are ready to visit.</div>
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
                        <strong>Staff-created Bookings</strong>
                        <span><?php echo number_format($adminCreatedCount); ?> in your history</span>
                    </div>
                    <div class="profile-chip">
                        <strong>Online Bookings</strong>
                        <span><?php echo number_format($guestHistoryCount); ?> guest bookings linked to your profile</span>
                    </div>
                </div>

                <div class="profile-notes dm-mt-16">
                    <div class="notes-card">
                        <strong>Dietary Notes</strong>
                        <div class="tiny-copy dm-mt-8"><?php echo htmlspecialchars($dietaryNotes !== '' ? $dietaryNotes : 'None', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="notes-card">
                        <strong>Visit Notes</strong>
                        <div class="tiny-copy dm-mt-8"><?php echo htmlspecialchars($notes !== '' ? $notes : 'None', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </article>
        </section>

        <section class="dashboard-lower">
            <article class="dashboard-panel">
                <div class="panel-heading">
                    <div>
                        <h3>Recent Visit History</h3>
                        <p>Recent visits, ratings, and quick rebooking actions.</p>
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
                                        <div class="reservation-card-title dm-text-md"><?php echo htmlspecialchars(date('D, j M Y', strtotime((string) $booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="history-meta"><?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) ($booking['number_of_guests'] ?? 0); ?> guests</div>
                                    </div>
                                    <span class="status-tag <?php echo htmlspecialchars((string) ($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(getBookingStatusLabel($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="hero-actions dm-mt-12">
                                    <span class="history-chip"><i class="fa fa-diagram-project"></i> <?php echo htmlspecialchars(getBookingSourceLabel($booking['booking_source'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="history-chip"><i class="fa fa-table-cells"></i> <?php echo !empty($booking['table_number']) ? 'Table ' . htmlspecialchars((string) $booking['table_number'], ENT_QUOTES, 'UTF-8') : 'Unassigned'; ?></span>
                                    <?php if (strtolower((string) ($booking['status'] ?? '')) === 'completed' && empty($booking['review_rating'])): ?>
                                        <a class="btn-portal-secondary" href="rate-booking.php?id=<?php echo (int) $booking['booking_id']; ?>"><i class="fa fa-star"></i> Rate Experience</a>
                                    <?php elseif (!empty($booking['review_rating'])): ?>
                                        <span class="history-chip"><i class="fa fa-star"></i> Rated <?php echo (int) $booking['review_rating']; ?>/5</span>
                                    <?php endif; ?>
                                    <a class="btn-portal-secondary" href="<?php echo htmlspecialchars($rebookUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-repeat"></i> Rebook</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">Completed visits will appear here after your first reservation is finished.</div>
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
