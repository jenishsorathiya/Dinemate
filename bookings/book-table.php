<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ensureBookingRequestColumns($pdo);

$prefillName = '';
$prefillEmail = '';
$prefillPhone = '';

if(isLoggedIn() && getCurrentUserRole() === 'customer') {
    $customerStmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE user_id = ? LIMIT 1");
    $customerStmt->execute([getCurrentUserId()]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    if($customer) {
        $prefillName = (string)($customer['name'] ?? '');
        $prefillEmail = (string)($customer['email'] ?? '');
        $prefillPhone = (string)($customer['phone'] ?? '');
    }
}

// Restaurant hours configuration
$restaurantHours = [
    'open' => '10:00',
    'close' => '22:00',
    'minDuration' => 60, // Minimum booking duration in minutes
    'maxDuration' => 180  // Maximum booking duration in minutes
];
?>

<?php include "../includes/header.php"; ?>

<style>

/* PAGE SPACING */
.booking-container{
    margin-top: 120px;
    margin-bottom: 80px;
    max-width: 920px;
}

/* BOOKING CARD */
.booking-card{
    background: white;
    border-radius: 16px;
    padding: 36px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.08);
    transition: 0.3s;
    max-width: 820px;
    margin: 0 auto;
}

.booking-card:hover{
    transform: translateY(-3px);
}

/* TITLE */
.booking-title{
    font-weight: 600;
    margin-bottom: 25px;
}

/* INPUTS */
.modern-input{
    border-radius: 10px;
    padding: 12px;
    border: 1px solid #e5e7eb;
    transition: 0.2s;
}

.modern-input:focus{
    border-color: #ffb703;
    box-shadow: 0 0 0 3px rgba(244,180,0,0.2);
}

/* LABEL */
.form-label{
    font-weight: 500;
    margin-bottom: 6px;
}

/* FORM LAYOUT */
#booking-form .row{
    row-gap: 4px;
}

#booking-form .col-md-4,
#booking-form .col-md-6,
#booking-form .col-12{
    width: 100%;
}

/* INFO BOX */
.booking-card .alert{
    border-radius: 12px;
}

/* BUTTON */
.btn-book{
    background: #f4b400;
    border: none;
    padding: 14px;
    border-radius: 40px;
    font-weight: 600;
    font-size: 16px;
    transition: 0.3s;
}

.btn-book:hover{
    background: #e0a800;
    transform: scale(1.02);
}

/* VALIDATION MESSAGE */
.validation-message {
    color: #dc3545;
    font-size: 14px;
    margin-top: 4px;
    display: none;
}

@media (max-width: 768px) {
    .booking-container {
        max-width: 100%;
        padding-left: 14px;
        padding-right: 14px;
    }

    .booking-card {
        padding: 24px;
    }
}

</style>

<div class="container booking-container">

<div class="booking-card">

<h3 class="booking-title text-center">
<i class="fa fa-calendar-check text-warning"></i>
Book a Reservation
</h3>

<!-- VALIDATION MESSAGE CONTAINER -->
<div id="validation-message" class="alert alert-danger validation-message" style="display: none;">
<i class="fa fa-exclamation-triangle"></i>
<span id="validation-text"></span>
</div>

<form action="process-booking.php" method="POST" id="booking-form">

<div class="row">
<div class="col-md-6 mb-4">
<label class="form-label">
<i class="fa fa-user"></i> Full Name
</label>
<input
type="text"
name="customer_name"
class="form-control modern-input"
required
value="<?= htmlspecialchars($prefillName) ?>"
>
</div>

<div class="col-md-6 mb-4">
<label class="form-label">
<i class="fa fa-envelope"></i> Email Address
</label>
<input
type="email"
name="customer_email"
class="form-control modern-input"
required
value="<?= htmlspecialchars($prefillEmail) ?>"
>
</div>

<div class="col-md-6 mb-4">
<label class="form-label">
<i class="fa fa-phone"></i> Phone Number
</label>
<input
type="text"
name="customer_phone"
class="form-control modern-input"
required
value="<?= htmlspecialchars($prefillPhone) ?>"
>
</div>
</div>

<!-- DATE, TIME, GUESTS ROW -->
<div class="row">
<div class="col-md-4 mb-4">
<label class="form-label">
<i class="fa fa-calendar"></i> Select Date
</label>
<input
type="date"
name="booking_date"
class="form-control modern-input"
required
min="<?= date('Y-m-d') ?>"
id="booking-date"
>
</div>

<div class="col-md-6 mb-4">
<label class="form-label">
<i class="fa fa-clock"></i> Start Time
</label>
<input
type="time"
name="start_time"
class="form-control modern-input"
required
id="start-time"
min="<?= $restaurantHours['open'] ?>"
max="<?= $restaurantHours['close'] ?>"
value="12:00"
>
<small class="form-text text-muted">Restaurant hours: <?= $restaurantHours['open'] ?> - <?= $restaurantHours['close'] ?></small>
<div id="start-time-error" class="validation-message"></div>
</div>

<div class="col-md-6 mb-4">
<label class="form-label">
<i class="fa fa-hourglass-end"></i> End Time
</label>
<input
type="time"
name="end_time"
class="form-control modern-input"
required
id="end-time"
min="<?= $restaurantHours['open'] ?>"
max="<?= $restaurantHours['close'] ?>"
value="13:00"
>
<small class="form-text text-muted">Booking duration: <?= $restaurantHours['minDuration'] ?> - <?= $restaurantHours['maxDuration'] ?> minutes</small>
<div id="end-time-error" class="validation-message"></div>
</div>

<div class="col-md-4 mb-4">
<label class="form-label">
<i class="fa fa-users"></i> Number of Guests
</label>
<input
type="number"
name="number_of_guests"
class="form-control modern-input"
min="1"
required
id="number-of-guests"
>
</div>
</div>

<div class="col-12 mb-4">
<div class="alert alert-info mb-0" style="font-size: 14px;">
<strong><i class="fa fa-circle-info"></i> Table assignment handled by staff.</strong>
<div class="mt-2">Choose your preferred date, time, guest count, and add a note if needed. The admin team will place your booking into the schedule.</div>
</div>
</div>

<!-- SPECIAL REQUEST -->
<div class="col-12 mb-4">
<label class="form-label">
<i class="fa fa-note-sticky"></i> Special Request
</label>
<textarea
name="special_request"
class="form-control modern-input"
rows="3"
placeholder="Birthday celebration, window seat, etc."
></textarea>
</div>

<!-- SUBMIT BUTTON -->
<button type="submit" class="btn btn-book w-100" id="submit-btn">
<i class="fa fa-check"></i>
Confirm Booking
</button>

</form>

</div>

</div>

<script>
// Time slot booking configuration
const RESTAURANT_HOURS = {
    open: '<?= $restaurantHours['open'] ?>',
    close: '<?= $restaurantHours['close'] ?>',
    minDuration: <?= $restaurantHours['minDuration'] ?>,
    maxDuration: <?= $restaurantHours['maxDuration'] ?>
};

// Form validation and interaction handling
document.addEventListener('DOMContentLoaded', function() {
    const bookingForm = document.getElementById('booking-form');
    const validationMessage = document.getElementById('validation-message');
    const validationText = document.getElementById('validation-text');
    const submitBtn = document.getElementById('submit-btn');
    
    const startTimeInput = document.getElementById('start-time');
    const endTimeInput = document.getElementById('end-time');
    const bookingDateInput = document.getElementById('booking-date');
    const startTimeError = document.getElementById('start-time-error');
    const endTimeError = document.getElementById('end-time-error');

    startTimeInput.addEventListener('change', () => {
        validateTimeSlot();
    });
    
    endTimeInput.addEventListener('change', () => {
        validateTimeSlot();
    });

    // Real-time time validation
    startTimeInput.addEventListener('change', validateTimeSlot);
    endTimeInput.addEventListener('change', validateTimeSlot);

    // Form submission validation
    bookingForm.addEventListener('submit', function(e) {
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        const selectedDate = document.getElementById('booking-date').value;
        const guestCount = document.getElementById('number-of-guests').value;

        // Check if date is selected
        if (!selectedDate) {
            e.preventDefault();
            showValidationMessage('Please select a reservation date.');
            return false;
        }

        // Comprehensive time validation
        if (!startTime || !endTime) {
            e.preventDefault();
            showValidationMessage('Please select both start and end times.');
            return false;
        }

        // Check if guest count is valid
        if (!guestCount || guestCount < 1) {
            e.preventDefault();
            showValidationMessage('Please enter a valid number of guests.');
            return false;
        }

        // Validate time slot
        if (!isValidTimeSlot(startTime, endTime)) {
            e.preventDefault();
            return false;
        }

        // All validations passed - allow form submission
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
    });

    // Time slot validation function
    function validateTimeSlot() {
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        
        clearTimeErrors();

        if (!startTime || !endTime) {
            return true; // Allow if not filled yet
        }

        // Validate restaurant hours
        if (startTime < RESTAURANT_HOURS.open) {
            startTimeError.textContent = `Start time cannot be before ${RESTAURANT_HOURS.open}`;
            startTimeError.style.display = 'block';
            return false;
        }

        if (endTime > RESTAURANT_HOURS.close) {
            endTimeError.textContent = `End time cannot be after ${RESTAURANT_HOURS.close}`;
            endTimeError.style.display = 'block';
            return false;
        }

        // Validate end time is after start time
        if (endTime <= startTime) {
            endTimeError.textContent = 'End time must be after start time';
            endTimeError.style.display = 'block';
            return false;
        }

        // Calculate duration in minutes
        const start = new Date(`2000-01-01T${startTime}`);
        const end = new Date(`2000-01-01T${endTime}`);
        const durationMinutes = (end - start) / 60000;

        // Validate minimum duration
        if (durationMinutes < RESTAURANT_HOURS.minDuration) {
            endTimeError.textContent = `Booking duration must be at least ${RESTAURANT_HOURS.minDuration} minutes`;
            endTimeError.style.display = 'block';
            return false;
        }

        // Validate maximum duration
        if (durationMinutes > RESTAURANT_HOURS.maxDuration) {
            endTimeError.textContent = `Booking duration cannot exceed ${RESTAURANT_HOURS.maxDuration} minutes`;
            endTimeError.style.display = 'block';
            return false;
        }

        clearTimeErrors();
        return true;
    }

    // Comprehensive time slot validation
    function isValidTimeSlot(startTime, endTime) {
        // Validate restaurant hours
        if (startTime < RESTAURANT_HOURS.open) {
            showValidationMessage(`Start time cannot be before ${RESTAURANT_HOURS.open}`);
            return false;
        }

        if (endTime > RESTAURANT_HOURS.close) {
            showValidationMessage(`End time cannot be after ${RESTAURANT_HOURS.close}`);
            return false;
        }

        // Validate end time is after start time
        if (endTime <= startTime) {
            showValidationMessage('End time must be after start time');
            return false;
        }

        // Calculate duration in minutes
        const start = new Date(`2000-01-01T${startTime}`);
        const end = new Date(`2000-01-01T${endTime}`);
        const durationMinutes = (end - start) / 60000;

        // Validate minimum duration
        if (durationMinutes < RESTAURANT_HOURS.minDuration) {
            showValidationMessage(`Booking duration must be at least ${RESTAURANT_HOURS.minDuration} minutes`);
            return false;
        }

        // Validate maximum duration
        if (durationMinutes > RESTAURANT_HOURS.maxDuration) {
            showValidationMessage(`Booking duration cannot exceed ${RESTAURANT_HOURS.maxDuration} minutes`);
            return false;
        }

        return true;
    }

    // Helper functions for validation messages
    function showValidationMessage(message) {
        validationText.textContent = message;
        validationMessage.style.display = 'block';
        validationMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideValidationMessage() {
        validationMessage.style.display = 'none';
    }

    function clearTimeErrors() {
        startTimeError.style.display = 'none';
        startTimeError.textContent = '';
        endTimeError.style.display = 'none';
        endTimeError.textContent = '';
    }
});
</script>

<?php include "../includes/footer.php"; ?>

