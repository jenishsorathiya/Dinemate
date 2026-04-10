<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$tableLabel = $booking['table_number'] ? 'Table ' . $booking['table_number'] : 'To be assigned by staff';
$statusLabel = getBookingStatusLabel($booking['status'] ?? 'pending');
$sourceLabel = getBookingSourceLabel($booking['booking_source'] ?? '');
$placementLabel = getBookingPlacementLabel($booking['reservation_card_status'] ?? 'not_placed');
$customerProfile = null;

if ($isLoggedInCustomer) {
    $customerProfile = ensureCustomerProfileForUser($pdo, (int) getCurrentUserId());
}
?>

<?php include "../includes/header.php"; ?>

<!-- Confetti Library -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<!-- QR Code Generator -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>

.confirm-wrapper{
margin-top:120px;
margin-bottom:80px;
}

.confirm-card{
background:white;
border:1px solid #e7ecf3;
border-radius:10px;
padding:36px;
box-shadow:0 4px 16px rgba(15,23,42,0.06);
text-align:center;
max-width:700px;
margin:auto;
}

/* Animated check icon */

.success-icon{
font-size:70px;
color:#22c55e;
}

/* Reservation ticket */

.ticket{
background:#f8fafc;
border:1px solid #e7ecf3;
border-radius:10px;
padding:20px;
margin-top:25px;
display:flex;
justify-content:space-between;
align-items:center;
flex-wrap:wrap;
}

.ticket-info{
text-align:left;
}

.ticket-info p{
margin-bottom:6px;
font-size:15px;
}

.qr-box{
padding:10px;
background:white;
border:1px solid #e7ecf3;
border-radius:8px;
box-shadow:0 4px 12px rgba(15,23,42,0.05);
}

/* Button */

.btn-bookings{
background:#1d2840;
border:1px solid #1d2840;
color:#ffffff;
padding:14px;
border-radius:8px;
font-weight:600;
margin-top:25px;
}

.btn-bookings:hover{
background:#141d31;
}

.confirm-grid{
display:grid;
grid-template-columns:minmax(0,1.1fr) minmax(240px,0.9fr);
gap:20px;
margin-top:25px;
text-align:left;
}

.confirm-side{
border:1px solid #e7ecf3;
border-radius:10px;
padding:18px;
background:#f8fafc;
}

.confirm-side h4{
margin:0 0 12px;
font-size:18px;
color:#162033;
}

.confirm-side p{
font-size:14px;
color:#556176;
margin-bottom:10px;
}

.confirm-links{
display:flex;
gap:12px;
flex-wrap:wrap;
margin-top:18px;
}

@media (max-width: 767px){
.confirm-grid{
grid-template-columns:1fr;
}
}

</style>

<div class="container confirm-wrapper">

<div class="confirm-card">

<div class="success-icon">
<i class="fa fa-circle-check"></i>
</div>

<h3 class="text-success mt-2">
Reservation Request Submitted
</h3>

<p class="text-muted">
Your request has been saved. A table will be assigned by the admin team.
</p>

<div class="confirm-grid">
<div class="ticket">
<div class="ticket-info">
<p><strong>Table:</strong> <?= htmlspecialchars($tableLabel) ?></p>
<p><strong>Status:</strong> <?= htmlspecialchars($statusLabel) ?></p>
<p><strong>Source:</strong> <?= htmlspecialchars($sourceLabel) ?></p>
<p><strong>Placed:</strong> <?= htmlspecialchars($placementLabel) ?></p>
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
<div id="qr"></div>
</div>
</div>

<aside class="confirm-side">
<h4>Booking Status</h4>
<p>Your booking is available in your account for future updates.</p>
<p>Table assignment and reservation-card placement are managed by staff.</p>
<?php if ($isLoggedInCustomer && $customerProfile): ?>
<p><strong>Reminder preferences:</strong> <?= !empty($customerProfile['email_reminders_enabled']) ? 'Email reminders on' : 'Email reminders off'; ?>, <?= !empty($customerProfile['sms_reminders_enabled']) ? 'SMS reminders on' : 'SMS reminders off'; ?>.</p>
<?php endif; ?>
<div class="confirm-links">
<?php if($isLoggedInCustomer): ?>
<a href="dashboard.php" class="btn btn-outline-dark">Customer Dashboard</a>
<a href="my-bookings.php" class="btn btn-outline-secondary">Booking History</a>
<?php else: ?>
<a href="../auth/register.php" class="btn btn-outline-dark">Create Account</a>
<?php endif; ?>
</div>
</aside>
</div>

<?php if($isLoggedInCustomer): ?>
<div class="confirm-links">
<a href="my-bookings.php" class="btn btn-bookings flex-fill">
View My Bookings
</a>
<a href="book-table.php?<?= htmlspecialchars(http_build_query(['rebook' => (int) $booking['booking_id'], 'date' => (string) $booking['booking_date'], 'time' => date('H:i', strtotime((string) $booking['start_time'])), 'guests' => (int) $booking['number_of_guests'], 'special' => (string) ($booking['special_request'] ?? '')]), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary flex-fill">
Book Similar Again
</a>
</div>
<?php else: ?>
<a href="book-table.php" class="btn btn-bookings w-100">
Book Another Reservation
</a>
<?php endif; ?>

</div>

</div>

<script>

/* Confetti animation */

confetti({
particleCount:120,
spread:70,
origin:{ y:0.6 }
});


/* Generate QR Code */

const qrData = `
Reservation
Table: <?= addslashes($tableLabel) ?>

Status: <?= addslashes($statusLabel) ?>

Date: <?= $booking['booking_date'] ?>

Time: <?= $booking['start_time'] ?> - <?= $booking['end_time'] ?>

Guests: <?= $booking['number_of_guests'] ?>
`;

new QRCode(document.getElementById("qr"),{
text: qrData,
width:120,
height:120
});

</script>

<?php include "../includes/footer.php"; ?>



