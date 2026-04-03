<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

requireCustomer();

$stmt = $pdo->prepare("
SELECT b.*, t.table_number
FROM bookings b
LEFT JOIN restaurant_tables t
ON b.table_id = t.table_id
WHERE b.user_id = ?
ORDER BY b.booking_date DESC
");

$stmt->execute([getCurrentUserId()]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$upcomingBookings = [];
$pastBookings = [];

foreach ($bookings as $booking) {
	$isUpcoming = ($booking['booking_date'] >= $today) && (($booking['status'] ?? '') !== 'cancelled');
	if ($isUpcoming) {
		$upcomingBookings[] = $booking;
	} else {
		$pastBookings[] = $booking;
	}
}
?>

<?php include "../includes/header.php"; ?>

<style> 

/* PAGE SPACING */

.bookings-wrapper{
margin-top:120px;
margin-bottom:80px;
}

.bookings-shell {
background:#ffffff;
border:1px solid #e7ecf3;
border-radius:20px;
padding:28px;
box-shadow:0 18px 42px rgba(15,23,42,0.08);
}

/* CARD GRID */

.booking-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
gap:25px;
}

/* BOOKING CARD */

.booking-card{
background:white;
border:1px solid #e7ecf3;
border-radius:18px;
padding:22px;
box-shadow:0 12px 28px rgba(15,23,42,0.06);
transition:0.3s;
position:relative;
overflow:hidden;
}

.booking-card:hover{
transform:translateY(-5px);
}

/* HEADER */

.booking-header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:15px;
}

/* TABLE BADGE */

.table-badge{
background:#eef2f6;
color:#556176;
padding:6px 12px;
border-radius:999px;
font-weight:600;
font-size:12px;
}

/* STATUS */

.status{
font-size:13px;
padding:6px 10px;
border-radius:20px;
font-weight:600;
}

.status.confirmed{
background:#e6f7ee;
color:#1d7a53;
}

.status.pending{
background:#fff2df;
color:#b66a11;
}

/* DETAILS */

.booking-details{
margin-top:10px;
}

.booking-details p{
margin-bottom:6px;
font-size:14px;
color:#444;
}

/* ACTION BUTTONS */

.booking-actions{
margin-top:15px;
display:flex;
gap:10px;
}

.btn-edit{
background:#1d2840;
color:white;
border:none;
padding:9px 14px;
border-radius:12px;
font-size:13px;
}

.btn-cancel{
background:#ffe7ea;
color:#c13f56;
border:1px solid #ffd1d7;
padding:9px 14px;
border-radius:12px;
font-size:13px;
}

</style>


<div class="container bookings-wrapper">

<div class="bookings-shell">

<h3 class="text-center mb-5">

<i class="fa fa-calendar-check text-warning"></i>
My Reservations

</h3>

<?php if($upcomingBookings): ?>
<h4 class="mb-4">Upcoming Bookings</h4>
<div class="booking-grid mb-5">
<?php foreach($upcomingBookings as $b): ?>

<div class="booking-card">

<div class="booking-header">

<div class="table-badge">
<?= $b['table_number'] ? 'Table ' . htmlspecialchars($b['table_number']) : 'Table assignment pending' ?>
</div>

<div class="status <?= htmlspecialchars($b['status']) ?>">
<?= ucfirst($b['status']) ?>
</div>

</div>

<div class="booking-details">

<p>
<i class="fa fa-calendar"></i>
<strong>Date:</strong>
<?= $b['booking_date'] ?>
</p>

<p>
<i class="fa fa-clock"></i>
<strong>Time:</strong>
<?= date("h:i A",strtotime($b['start_time'])) ?>  -  <?= date("h:i A",strtotime($b['end_time'])) ?>
</p>

<p>
<i class="fa fa-users"></i>
<strong>Guests:</strong>
<?= $b['number_of_guests'] ?>
</p>

<?php if(!empty($b['special_request'])): ?>
<p>
<i class="fa fa-note-sticky"></i>
<strong>Note:</strong>
<?= htmlspecialchars($b['special_request']) ?>
</p>
<?php endif; ?>

</div>

<div class="booking-actions">

<a href="modify-booking.php?id=<?= $b['booking_id'] ?>" class="btn-edit">
Edit
</a>

<a href="cancel-booking.php?id=<?= $b['booking_id'] ?>" class="btn-cancel">
Cancel
</a>

</div>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

<?php if($pastBookings): ?>
<h4 class="mb-4">Past Bookings</h4>
<div class="booking-grid">
<?php foreach($pastBookings as $b): ?>

<div class="booking-card">

<div class="booking-header">

<div class="table-badge">
<?= $b['table_number'] ? 'Table ' . htmlspecialchars($b['table_number']) : 'Table assignment pending' ?>
</div>

<div class="status <?= htmlspecialchars($b['status']) ?>">
<?= ucfirst($b['status']) ?>
</div>

</div>

<div class="booking-details">
<p><i class="fa fa-calendar"></i> <strong>Date:</strong> <?= $b['booking_date'] ?></p>
<p><i class="fa fa-clock"></i> <strong>Time:</strong> <?= date("h:i A",strtotime($b['start_time'])) ?>  -  <?= date("h:i A",strtotime($b['end_time'])) ?></p>
<p><i class="fa fa-users"></i> <strong>Guests:</strong> <?= $b['number_of_guests'] ?></p>
<?php if(!empty($b['special_request'])): ?>
<p><i class="fa fa-note-sticky"></i> <strong>Note:</strong> <?= htmlspecialchars($b['special_request']) ?></p>
<?php endif; ?>
</div>

</div>

<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if(!$upcomingBookings && !$pastBookings): ?>

<div class="text-center">

<p>No reservations found.</p>

<a href="book-table.php" class="btn btn-warning">
Book Your First Table
</a>

</div>

<?php endif; ?>

</div>

</div>

<?php include "../includes/footer.php"; ?>

