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