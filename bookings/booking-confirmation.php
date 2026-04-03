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
    die("Booking not found.");
}

$tableLabel = $booking['table_number'] ? 'Table ' . $booking['table_number'] : 'To be assigned by staff';
$statusLabel = ucfirst($booking['status'] ?? 'pending');
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
border-radius:18px;
padding:40px;
box-shadow:0 25px 60px rgba(0,0,0,0.08);
text-align:center;
max-width:700px;
margin:auto;
}

/* Animated check icon */

.success-icon{
font-size:70px;
color:#22c55e;
animation:pop 0.6s ease;
}

@keyframes pop{
0%{transform:scale(0)}
70%{transform:scale(1.2)}
100%{transform:scale(1)}
}

/* Reservation ticket */

.ticket{
background:#f8f9fa;
border-radius:12px;
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
border-radius:10px;
box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

/* Button */

.btn-bookings{
background:#f4b400;
border:none;
padding:14px;
border-radius:40px;
font-weight:600;
margin-top:25px;
}

.btn-bookings:hover{
background:#e0a800;
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

<!-- Reservation Ticket -->

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

</div>

<div class="qr-box">
<div id="qr"></div>
</div>

</div>

<?php if($isLoggedInCustomer): ?>
<a href="my-bookings.php" class="btn btn-bookings w-100">
View My Bookings
</a>
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



