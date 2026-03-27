<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if(!isCustomer()){
    header("Location: ../auth/login.php");
    exit();
}

// Fetch available tables sorted by capacity
$tables = $pdo->query("SELECT * FROM restaurant_tables WHERE status='available' ORDER BY capacity ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include "../includes/header.php"; ?>

<style>

/* PAGE SPACING */

.booking-container{
margin-top:120px;
margin-bottom:80px;
}

/* BOOKING CARD */

.booking-card{
background:white;
border-radius:16px;
padding:40px;
box-shadow:0 25px 60px rgba(0,0,0,0.08);
transition:0.3s;
max-width:700px;
margin:auto;
}
