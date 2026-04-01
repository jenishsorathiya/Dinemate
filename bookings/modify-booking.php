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

$error = "";
$success = "";

/* 🔹 Handle Update */
if($_SERVER["REQUEST_METHOD"] === "POST"){

    $date = sanitize($_POST['booking_date']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $guests = intval($_POST['number_of_guests']);
    $special = sanitize($_POST['special_request']);

    if(empty($date) || empty($start_time) || empty($end_time) || empty($guests)){
        $error = "All fields are required.";
    } elseif($guests < 1) {
        $error = "Number of guests must be at least 1.";
    } else {
        $start_time = date('H:i:s', strtotime($start_time));
        $end_time = date('H:i:s', strtotime($end_time));
        $restaurantOpen = '10:00:00';
        $restaurantClose = '22:00:00';
        $durationMinutes = (strtotime($end_time) - strtotime($start_time)) / 60;

        if($start_time < $restaurantOpen) {
            $error = "Start time cannot be before restaurant opening time (10:00).";
        } elseif($end_time > $restaurantClose) {
            $error = "End time cannot be after restaurant closing time (22:00).";
        } elseif($end_time <= $start_time) {
            $error = "End time must be after start time.";
        } elseif($durationMinutes < 60) {
            $error = "Booking duration must be at least 60 minutes.";
        } elseif($durationMinutes > 180) {
            $error = "Booking duration cannot exceed 180 minutes.";
        } else {
            $capacityStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'available' AND capacity >= ?");
            $capacityStmt->execute([$guests]);

            if((int)$capacityStmt->fetchColumn() === 0) {
                $error = "We do not currently have a table that can accommodate that many guests.";
            } else {
                $stmt = $pdo->prepare("
                UPDATE bookings
                SET table_id = NULL,
                    booking_date = ?,
                    start_time = ?,
                    end_time = ?,
                    number_of_guests = ?,
                    special_request = ?,
                    status = 'pending'
                WHERE booking_id = ? AND user_id = ?
                ");

                $stmt->execute([
                    $date,
                    $start_time,
                    $end_time,
                    $guests,
                    $special,
                    $booking_id,
                    $_SESSION['user_id']
                ]);

                $success = "Booking request updated. Table assignment will be handled by staff.";

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

<div class="container modify-wrapper">

<div class="modify-card">

<h3 class="modify-title text-center">
<i class="fa fa-pen-to-square text-warning"></i>
Modify Booking
</h3>

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<form method="POST">

<div class="row">

<div class="col-md-6 mb-4">

<label class="form-label">
<i class="fa fa-calendar"></i> Select Date
</label>

<input type="date"
name="booking_date"
class="form-control modern-input"
value="<?= $booking['booking_date'] ?>"
required>

</div>

<div class="col-md-6 mb-4">

<label class="form-label">
<i class="fa fa-clock"></i> Start Time
</label>

<input type="time"
name="start_time"
class="form-control modern-input"
value="<?= date('H:i', strtotime($booking['start_time'])) ?>"
required>

</div>

<div class="col-md-6 mb-4">

<label class="form-label">
<i class="fa fa-hourglass-end"></i> End Time
</label>

<input type="time"
name="end_time"
class="form-control modern-input"
value="<?= date('H:i', strtotime($booking['end_time'])) ?>"
required>

</div>

<div class="col-md-6 mb-4">

<label class="form-label">
<i class="fa fa-users"></i> Number of Guests
</label>

<input type="number"
name="number_of_guests"
class="form-control modern-input"
value="<?= $booking['number_of_guests'] ?>"
required
min="1">

</div>

<div class="col-12 mb-4">

<div class="alert alert-info mb-0">
<i class="fa fa-circle-info"></i>
Any booking changes return the reservation to the unassigned queue so staff can place it back onto the timeline.
</div>

</div>

<div class="col-12 mb-4">

<label class="form-label">
<i class="fa fa-note-sticky"></i> Special Request
</label>

<textarea name="special_request"
class="form-control modern-input"
rows="3"><?= $booking['special_request'] ?></textarea>

</div>

</div>

<button class="btn btn-update w-100">
<i class="fa fa-save"></i> Update Booking
</button>

</form>

<div class="text-center mt-4">

<a href="my-bookings.php" class="btn btn-secondary btn-back">
Back to My Bookings
</a>

</div>

</div>

</div>

<?php include "../includes/footer.php"; ?> 