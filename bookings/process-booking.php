<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    redirect("book-table.php");
}

$user_id = $_SESSION['user_id'];

$date = sanitize($_POST['booking_date']);
$time = sanitize($_POST['booking_time']);
$guests = intval($_POST['number_of_guests']);
$table_id = intval($_POST['table_id']);
$special = sanitize($_POST['special_request']);

if(empty($date) || empty($time) || empty($guests) || empty($table_id)){
    die("All fields are required.");
}

/*  Capacity Check */
$stmt = $pdo->prepare("SELECT capacity FROM restaurant_tables WHERE table_id = ?");
$stmt->execute([$table_id]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$table){
    die("Invalid table.");
}

if($guests > $table['capacity']){
    die("Selected table cannot accommodate that many guests.");
}

/* Conflict Check */
$stmt = $pdo->prepare("
    SELECT * FROM bookings 
    WHERE table_id = ? 
    AND booking_date = ? 
    AND booking_time = ?
    AND status IN ('pending','confirmed')
");
$stmt->execute([$table_id, $date, $time]);

if($stmt->rowCount() > 0){
    die("This table is already booked for the selected time.");
}


