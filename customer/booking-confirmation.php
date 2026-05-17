<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

startAppSession();

ensureBookingRequestColumns($pdo);

if(!isset($_GET['id'])){
    header("Location: book-table.php");
    exit();
}

$booking_id = intval($_GET['id']);
$guest_token = trim($_GET['token'] ?? '');

$isLoggedInCustomer = isLoggedIn() && getCurrentUserRole() === 'customer';

if($isLoggedInCustomer) {
    $stmt = $pdo->prepare("
        SELECT b.*, t.table_number
        FROM bookings b
        LEFT JOIN restaurant_tables t ON b.table_id = t.table_id
        WHERE b.booking_id = ?
          AND (b.user_id = ? OR b.guest_access_token = ?)
        LIMIT 1
    ");
    $stmt->execute([$booking_id, getCurrentUserId(), $guest_token]);
} else {
    $stmt = $pdo->prepare("
        SELECT b.*, t.table_number
        FROM bookings b
        LEFT JOIN restaurant_tables t ON b.table_id = t.table_id
        WHERE b.booking_id = ? AND b.guest_access_token = ?
        LIMIT 1
    ");
    $stmt->execute([$booking_id, $guest_token]);
}

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$booking){
    $_SESSION['error'] = 'Booking confirmation not found.';
    header('Location: book-table.php');
    exit();
}

$tableLabel = $booking['table_number'] ? 'Table ' . $booking['table_number'] : 'To be confirmed';
$statusLabel = getBookingStatusLabel($booking['status'] ?? 'pending');
$sourceLabel = getBookingSourceLabel($booking['booking_source'] ?? '');
$placementLabel = getBookingPlacementLabel($booking['reservation_card_status'] ?? 'not_placed');
$qrData = "Reservation\n"
    . "Table: " . $tableLabel . "\n"
    . "Status: " . $statusLabel . "\n"
    . "Date: " . (string) $booking['booking_date'] . "\n"
    . "Time: " . (string) $booking['start_time'] . ' - ' . (string) $booking['end_time'] . "\n"
    . "Guests: " . (string) $booking['number_of_guests'];
$customerProfile = null;

if ($isLoggedInCustomer) {
    $customerProfile = ensureCustomerProfileForUser($pdo, (int) getCurrentUserId());
}
?>

<?php
$pageTitle = 'Booking Confirmation | DineMate';
$extraStylesheets = ['assets/css/pages/customer-confirmation.css'];
$extraFooterScripts = [
    'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
    'assets/js/pages/customer-confirmation.js',
];
include '../includes/header.php';
?>

<div class="container confirm-wrapper">

<div class="confirm-card">

<div class="success-icon">
<i class="fa fa-circle-check"></i>
</div>

<h3 class="mt-2">
Your Table Request Is In
</h3>

<p>
Your request has been saved in DineMate. The restaurant team will confirm the details for your visit.
</p>

<div class="confirm-grid">
<div class="ticket">
<div class="ticket-info">
<p><strong>Table:</strong> <?= htmlspecialchars($tableLabel) ?></p>
<p><strong>Status:</strong> <?= htmlspecialchars($statusLabel) ?></p>
<p><strong>Name:</strong> <?= htmlspecialchars($booking['customer_name'] ?? '') ?></p>
<p><strong>Email:</strong> <?= htmlspecialchars($booking['customer_email'] ?? '') ?></p>
<p><strong>Phone:</strong> <?= htmlspecialchars($booking['customer_phone'] ?? '') ?></p>
<p><strong>Date:</strong> <?= $booking['booking_date'] ?></p>
<p><strong>Time:</strong> <?= date("h:i A",strtotime($booking['start_time'])) ?> - <?= date("h:i A",strtotime($booking['end_time'])) ?></p>
<p><strong>Guests:</strong> <?= $booking['number_of_guests'] ?></p>
<?php if (!empty($booking['special_request'])): ?>
<p><strong>Note:</strong> <?= htmlspecialchars($booking['special_request']) ?></p>
<?php endif; ?>
</div>
<div class="qr-box">
<div id="qr" data-reservation-qr="<?php echo htmlspecialchars($qrData, ENT_QUOTES, 'UTF-8'); ?>"></div>
</div>
</div>

<aside class="confirm-side">
<h4>What happens next</h4>
<p>Your reservation is available in your account for future updates.</p>
<p>If the restaurant needs to adjust anything, they will contact you using the details on this booking.</p>
<?php if ($isLoggedInCustomer && $customerProfile): ?>
<p><strong>Reminder preferences:</strong> <?= !empty($customerProfile['email_reminders_enabled']) ? 'Email reminders on' : 'Email reminders off'; ?>, <?= !empty($customerProfile['sms_reminders_enabled']) ? 'SMS reminders on' : 'SMS reminders off'; ?>.</p>
<?php endif; ?>
<div class="confirm-links">
<?php if($isLoggedInCustomer): ?>
<a href="dashboard.php" class="btn-surface">Customer Dashboard</a>
<a href="my-bookings.php" class="btn-surface">Reservations</a>
<?php else: ?>
<a href="../auth/register.php" class="btn-surface">Create Account</a>
<?php endif; ?>
</div>
</aside>
</div>

<?php if($isLoggedInCustomer): ?>
<div class="confirm-links">
<a href="my-bookings.php" class="btn-bookings flex-fill">
View Reservations
</a>
<a href="book-table.php?<?= htmlspecialchars(http_build_query(['rebook' => (int) $booking['booking_id'], 'date' => (string) $booking['booking_date'], 'time' => date('H:i', strtotime((string) $booking['start_time'])), 'guests' => (int) $booking['number_of_guests'], 'special' => (string) ($booking['special_request'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>" class="btn-surface flex-fill">
Book Similar Again
</a>
</div>
<?php else: ?>
<a href="book-table.php" class="btn-bookings w-100">
Book Another Reservation
</a>
<?php endif; ?>

</div>

</div>


<?php include "../includes/footer.php"; ?>



