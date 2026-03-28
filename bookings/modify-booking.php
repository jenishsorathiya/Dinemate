 <?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if(! isCustomer()){
    header("Location: ../auth/login.php");
    exit();
}
if(!isset($_GET['id'])){
    header("Location: my-bookings.php");
    exit();
}

$booking_id = intval($_GET['id']);

/* 🔹 Fetch existing booking */
$stmt = $pdo->prepare("
SELECT * FROM bookings 
WHERE booking_id = ? AND user_id = ?
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$booking){
    die("Booking not found.");
}
/* 🔹 Fetch available tables */
$tables = $pdo->query("
SELECT * FROM restaurant_tables 
WHERE status='available' 
ORDER BY capacity ASC
")->fetchAll(PDO::FETCH_ASSOC);

$error = "";
$success = "";

/* 🔹 Handle Update */
if($_SERVER["REQUEST_METHOD"] === "POST"){

    $date = sanitize($_POST['booking_date']);
    $time = sanitize($_POST['booking_time']);
    $guests = intval($_POST['number_of_guests']);
    $table_id = intval($_POST['table_id']);
    $special = sanitize($_POST['special_request']);

    if(empty($date) || empty($time) || empty($guests) || empty($table_id)){
        $error = "All fields are required.";
    } else {

        /* 1️⃣ Capacity Check */
        $stmt = $pdo->prepare("SELECT capacity FROM restaurant_tables WHERE table_id=?");
        $stmt->execute([$table_id]);
        $table = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$table){
            $error = "Invalid table selected.";
        }
        elseif($guests > $table['capacity']){
            $error = "Selected table cannot accommodate that many guests.";
        }
        else {

            /* 2️⃣ Conflict Check (exclude current booking) */
            $stmt = $pdo->prepare("
                SELECT * FROM bookings
                WHERE table_id = ?
                AND booking_date = ?
                AND booking_time = ?
                AND booking_id != ?
                AND status IN ('pending','confirmed')
            "); 

             $stmt->execute([$table_id, $date, $time, $booking_id]);

            
            if($stmt->rowCount() > 0){
                $error = "This table is already booked for that time.";
            }
            else {

         /* 3️⃣ Update Booking */
                $stmt = $pdo->prepare("
                UPDATE bookings
                SET table_id=?, booking_date=?, booking_time=?,
                    number_of_guests=?, special_request=?
                WHERE booking_id=? AND user_id=?
                ");

                $stmt->execute([
                    $table_id,
                    $date,
                    $time,
                    $guests,
                    $special,
                    $booking_id,
                    $_SESSION['user_id']
                ]);

                $success = "Booking updated successfully.";

                /* Refresh updated data */
                $stmt = $pdo->prepare("
                SELECT * FROM bookings 
                WHERE booking_id = ?
                ");

                $stmt->execute([$booking_id]);
                $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
}
?>

<?php include "../includes/header.php"; ?>

<style>

/* PAGE SPACING */

.modify-wrapper{
margin-top:120px;
margin-bottom:80px;
}

/* CARD */

.modify-card{
background:white;
border-radius:18px;
padding:40px;
box-shadow:0 25px 60px rgba(0,0,0,0.08);
transition:0.3s;
}

.modify-card:hover{
transform:translateY(-3px);
}

/* TITLE */

.modify-title{
font-weight:600;
margin-bottom:25px;
}

/* LABEL */

.form-label{
font-weight:500;
margin-bottom:6px;
}

/* INPUT */

.modern-input{
border-radius:10px;
padding:12px;
border:1px solid #e5e7eb;
transition:0.2s;
}

.modern-input:focus{
border-color:#f4b400;
box-shadow:0 0 0 3px rgba(244,180,0,0.2);
}

/* BUTTON */

.btn-update{
background:#f4b400;
border:none;
padding:14px;
border-radius:40px;
font-weight:600;
font-size:16px;
transition:0.3s;
}

.btn-update:hover{
background:#e0a800;
transform:scale(1.02);
}

/* BACK BUTTON */

.btn-back{
border-radius:30px;
padding:10px 20px;
}

</style>