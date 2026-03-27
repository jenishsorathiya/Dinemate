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
.booking-card:hover{
transform:translateY(-3px);
}

/* TITLE */

.booking-title{
font-weight:600;
margin-bottom:25px;
}

/* INPUTS */

.modern-input{
border-radius:10px;
padding:12px;
border:1px solid #e5e7eb;
transition:0.2s;
}

.modern-input:focus{
border-color:#ffb703;
box-shadow:0 0 0 3px rgba(244,180,0,0.2);
}

.modern-input:focus{
border-color:#f4b400;
box-shadow:0 0 0 3px rgba(244,180,0,0.2);
}

/* LABEL */

.form-label{
font-weight:500;
margin-bottom:6px;
}

/* BUTTON */

.btn-book{
background:#f4b400;
border:none;
padding:14px;
border-radius:40px;
font-weight:600;
font-size:16px;
transition:0.3s;
}

.btn-book:hover{
background:#e0a800;
transform:scale(1.05);
}