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
