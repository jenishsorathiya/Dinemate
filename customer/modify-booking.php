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
$modifyBookingCsrfToken = csrfToken('modify_booking');
$modifyFlash = getFlashMessage();
if ($modifyFlash) {
    if (($modifyFlash['type'] ?? '') === 'error') {
        $error = (string) ($modifyFlash['message'] ?? '');
    } else {
        $success = (string) ($modifyFlash['message'] ?? '');
    }
}

/* 🔹 Handle Update */
if($_SERVER["REQUEST_METHOD"] === "POST"){
    requireValidCsrfToken('modify_booking', ['redirect' => appPath('customer/modify-booking.php?id=' . $booking_id)]);

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
                    $success = "Booking request updated. The restaurant team will confirm the new details.";

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

<?php
$pageTitle = 'Update Reservation | DineMate';
$extraStylesheets = ['assets/css/pages/customer-modify-booking.css'];
include '../includes/header.php';
?>


<div class="container modify-wrapper">
    <div class="modify-layout">
        <section class="modify-card">
            <div class="portal-form-header">
                <p class="guest-section-kicker">Reservation update</p>
                <h3 class="modify-title">Change your table request.</h3>
                <p>Adjust the date, arrival time, party size, or notes for this reservation.</p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($modifyBookingCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="profile-grid">
                    <div class="profile-field">
                        <label for="bookingDate">Date</label>
                        <input id="bookingDate" type="date" name="booking_date" class="form-control modern-input" value="<?= htmlspecialchars((string) $booking['booking_date'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="profile-field">
                        <label for="startTime">Arrival time</label>
                        <input id="startTime" type="time" name="start_time" class="form-control modern-input" value="<?= htmlspecialchars(date('H:i', strtotime((string) $booking['start_time'])), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="profile-field">
                        <label for="endTime">End time</label>
                        <input id="endTime" type="time" name="end_time" class="form-control modern-input" value="<?= htmlspecialchars(date('H:i', strtotime((string) $booking['end_time'])), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="profile-field">
                        <label for="guestCount">Guests</label>
                        <input id="guestCount" type="number" name="number_of_guests" class="form-control modern-input" value="<?= htmlspecialchars((string) $booking['number_of_guests'], ENT_QUOTES, 'UTF-8') ?>" required min="<?php echo htmlspecialchars((string) $bookingSettings['min_party_size'], ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars((string) $bookingSettings['max_party_size'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <?php if ($bookingSettings['allow_table_request']): ?>
                        <div class="profile-field full-width">
                            <label for="specialRequest">Notes for the team</label>
                            <textarea id="specialRequest" name="special_request" class="form-control modern-input profile-textarea" rows="4" placeholder="Allergies, occasion, seating preference, pram space, or anything helpful."><?= htmlspecialchars((string) $booking['special_request'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="special_request" value="">
                    <?php endif; ?>
                </div>

                <div class="profile-actions">
                    <a href="my-bookings.php" class="profile-btn profile-btn-secondary dm-no-underline">Back to Reservations</a>
                    <button class="profile-btn profile-btn-primary" type="submit">
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </section>

        <aside class="portal-side-panel">
            <h3>Current Request</h3>
            <ul class="portal-side-list">
                <li><i class="fa fa-calendar"></i><span><?php echo htmlspecialchars(date('l, j F Y', strtotime((string) $booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></span></li>
                <li><i class="fa fa-clock"></i><span><?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?> to <?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span></li>
                <li><i class="fa fa-users"></i><span><?php echo (int) $booking['number_of_guests']; ?> guests</span></li>
                <li><i class="fa fa-circle-info"></i><span><?php echo htmlspecialchars(getBookingStatusLabel($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?></span></li>
            </ul>
            <div class="hint-card">
                Saved changes go back to the restaurant team for confirmation.
            </div>
        </aside>
    </div>
</div>

<?php include "../includes/footer.php"; ?> 
