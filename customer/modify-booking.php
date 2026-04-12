<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureSettingsSchema($pdo);
$bookingSettings = getBookingSettings($pdo);

if (!$bookingSettings['allow_booking_modification']) {
    $_SESSION['error'] = 'Booking modifications are currently disabled.';
    header('Location: my-bookings.php');
    exit();
}

requireCustomer();
if(!isset($_GET['id'])){
    header("Location: my-bookings.php");
    exit();
}

$booking_id = intval($_GET['id']);

/* 🔹 Fetch existing booking */
$stmt = $pdo->prepare("
SELECT * FROM bookings 
WHERE booking_id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
");
$stmt->execute([$booking_id, getCurrentUserId()]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$booking){
    $_SESSION['error'] = 'Booking not found or you do not have permission to modify it.';
    header('Location: my-bookings.php');
    exit();
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

    if (!$bookingSettings['allow_table_request']) {
        $special = '';
    }

    $minGuests = max(1, intval($bookingSettings['min_party_size']));
    $maxGuests = max($minGuests, intval($bookingSettings['max_party_size']));
    $minimumAdvanceMinutes = max(0, intval($bookingSettings['minimum_advanced_booking_minutes']));
    $durationMinutesConfig = max(30, intval($bookingSettings['booking_duration_minutes']));

    if(empty($date) || empty($start_time) || empty($end_time) || empty($guests)){
        $error = "All fields are required.";
    } elseif($guests < $minGuests) {
        $error = "Number of guests must be at least {$minGuests}.";
    } elseif($guests > $maxGuests) {
        $error = "Number of guests cannot exceed {$maxGuests}.";
    } else {
        $start_time = date('H:i:s', strtotime($start_time));
        $end_time = date('H:i:s', strtotime($end_time));
        $restaurantOpen = '10:00:00';
        $restaurantClose = '22:00:00';
        $durationMinutes = (strtotime($end_time) - strtotime($start_time)) / 60;

        $requestedDateTime = strtotime($date . ' ' . $start_time);
        if ($requestedDateTime === false) {
            $error = "Please enter a valid booking date and time.";
        } elseif ($requestedDateTime < time() + ($minimumAdvanceMinutes * 60)) {
            $error = "Bookings must be made at least {$minimumAdvanceMinutes} minutes in advance.";
        } elseif($start_time < $restaurantOpen) {
            $error = "Start time cannot be before restaurant opening time (10:00).";
        } elseif($end_time > $restaurantClose) {
            $error = "End time cannot be after restaurant closing time (22:00).";
        } elseif($end_time <= $start_time) {
            $error = "End time must be after start time.";
        } elseif($durationMinutes !== $durationMinutesConfig) {
            $error = "Booking duration must be exactly {$durationMinutesConfig} minutes.";
        } else {
            $capacityStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'available' AND capacity >= ?");
            $capacityStmt->execute([$guests]);

            if((int)$capacityStmt->fetchColumn() === 0) {
                $error = "We do not currently have a table that can accommodate that many guests.";
            } else {
                try {
                    $pdo->beginTransaction();
                    syncBookingTableAssignments($pdo, $booking_id, []);

                    $stmt = $pdo->prepare("
                    UPDATE bookings
                    SET booking_date = ?,
                        start_time = ?,
                        end_time = ?,
                        requested_start_time = ?,
                        requested_end_time = ?,
                        number_of_guests = ?,
                        special_request = ?,
                        status = 'pending',
                        reservation_card_status = NULL
                    WHERE booking_id = ? AND user_id = ?
                    ");

                    $stmt->execute([
                        $date,
                        $start_time,
                        $end_time,
                        $start_time,
                        $end_time,
                        $guests,
                        $special,
                        $booking_id,
                        getCurrentUserId()
                    ]);

                    $pdo->commit();
                    $success = "Booking request updated. Table assignment will be handled by staff.";

                    $stmt = $pdo->prepare("
                    SELECT * FROM bookings 
                    WHERE booking_id = ?
                    ");

                    $stmt->execute([$booking_id]);
                    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error = "Unable to update your booking right now.";
                }
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
border:1px solid var(--dm-border);
border-radius:10px;
padding:34px;
box-shadow:0 4px 16px rgba(15,23,42,0.06);
}

/* TITLE */

.modify-title{
font-weight:700;
margin-bottom:25px;
color:var(--dm-text);
}

/* LABEL */

.form-label{
font-weight:500;
margin-bottom:6px;
}

/* INPUT */

.modern-input{
border-radius:8px;
padding:12px 14px;
border:1px solid var(--dm-border-strong);
transition:0.2s;
}

.modern-input:focus{
border-color:var(--dm-border-strong);
box-shadow:0 0 0 4px rgba(29,40,64,0.12);
}

/* BUTTON */

.btn-update{
background:var(--dm-accent-dark);
border:1px solid var(--dm-accent-dark);
color:var(--dm-surface);
padding:14px;
border-radius:8px;
font-weight:600;
font-size:16px;
transition:0.3s;
}

.btn-update:hover{
background:var(--dm-accent-dark-hover);
border-color:var(--dm-accent-dark-hover);
}

/* BACK BUTTON */

.btn-back{
border-radius:8px;
padding:10px 18px;
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
min="<?php echo htmlspecialchars((string) $bookingSettings['min_party_size'], ENT_QUOTES, 'UTF-8'); ?>"
max="<?php echo htmlspecialchars((string) $bookingSettings['max_party_size'], ENT_QUOTES, 'UTF-8'); ?>">

</div>

<div class="col-12 mb-4">

<div class="alert alert-info mb-0">
<i class="fa fa-circle-info"></i>
Any booking changes return the reservation to the unassigned queue so staff can place it back onto the timeline.
</div>

</div>

<div class="col-12 mb-4">

<?php if ($bookingSettings['allow_table_request']): ?>
    <label class="form-label">
        <i class="fa fa-note-sticky"></i> Special Request
    </label>

    <textarea name="special_request"
    class="form-control modern-input"
    rows="3"><?= htmlspecialchars((string) $booking['special_request'], ENT_QUOTES, 'UTF-8'); ?></textarea>
<?php else: ?>
    <input type="hidden" name="special_request" value="">
<?php endif; ?>

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
