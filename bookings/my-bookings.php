<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if(!isCustomer()){
header("Location: ../auth/login.php");
exit();
}

$stmt = $pdo->prepare("
SELECT b.*, t.table_number
FROM bookings b
JOIN restaurant_tables t
ON b.table_id = t.table_id
WHERE b.user_id = ?
ORDER BY b.booking_date DESC
");

$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include "../includes/header.php"; ?>

<style> 

/* PAGE SPACING */

.bookings-wrapper{
margin-top:120px;
margin-bottom:80px;
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
border-radius:16px;
padding:25px;
box-shadow:0 20px 50px rgba(0,0,0,0.08);
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
background:#f4b400;
color:black;
padding:6px 14px;
border-radius:30px;
font-weight:600;
font-size:14px;
}

/* STATUS */

.status{
font-size:13px;
padding:6px 10px;
border-radius:20px;
font-weight:600;
}

.status.confirmed{
background:#22c55e;
color:white;
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
background:#3b82f6;
color:white;
border:none;
padding:6px 14px;
border-radius:8px;
font-size:13px;
}

.btn-cancel{
background:#ef4444;
color:white;
border:none;
padding:6px 14px;
border-radius:8px;
font-size:13px;
}

</style>


<div class="container bookings-wrapper">

<h3 class="text-center mb-5">

<i class="fa fa-calendar-check text-warning"></i>
My Reservations

</h3>

<?php if($bookings): ?>

<div class="booking-grid">

<?php foreach($bookings as $b): ?>

<div class="booking-card">

<div class="booking-header">

<div class="table-badge">
Table <?= $b['table_number'] ?>
</div>

<div class="status confirmed">
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
<?= date("h:i A",strtotime($b['booking_time'])) ?>
</p>

<p>
<i class="fa fa-users"></i>
<strong>Guests:</strong>
<?= $b['number_of_guests'] ?>
</p>

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

<?php else: ?>

<div class="text-center">

<p>No reservations found.</p>

<a href="book-table.php" class="btn btn-warning">
Book Your First Table
</a>

</div>

<?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>

