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


