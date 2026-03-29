<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if(! isCustomer()){
    header("Location: ../auth/login.php");
    exit();
}

// Fetch available tables sorted by capacity
$tables = $pdo->query("SELECT * FROM restaurant_tables WHERE status='available' ORDER BY capacity ASC")->fetchAll(PDO::FETCH_ASSOC);

// Generate time slots from 12:00 PM to 9:00 PM every 30 minutes
$timeSlots = [];
$startTime = strtotime('12:00 PM');
$endTime = strtotime('9:00 PM');

for ($time = $startTime; $time <= $endTime; $time += 1800) { // 1800 seconds = 30 minutes
    $timeValue = date('H:i:s', $time); // MySQL TIME format (HH:MM:SS)
    $timeLabel = date('g:i A', $time); // User-friendly format (7:00 PM)
    $timeSlots[] = [
        'value' => $timeValue,
        'label' => $timeLabel
    ];
}
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

.table-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-top: 10px;
}

.table-card {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    background: #fff;
    transition: 0.2s;
    min-height: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.table-card.selected {
    border-color: #f4b400;
    box-shadow: 0 0 0 3px rgba(244,180,0,0.28);
}

.table-card.unavailable {
    border-color: #ccc;
    background: #f8f9fa;
    color: #999;
    cursor: not-allowed;
}

/* TIME SLOT STYLING */
.time-slot {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 8px 12px;
    text-align: center;
    cursor: pointer;
    background: #fff;
    transition: 0.2s;
    margin: 4px;
    display: inline-block;
    min-width: 80px;
}

.time-slot.selected {
    border-color: #f4b400;
    background: #f4b400;
    color: white;
}

.time-slot.disabled {
    border-color: #ccc;
    background: #f8f9fa;
    color: #999;
    cursor: not-allowed;
}

.time-slots-container {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    margin-top: 8px;
}

/* VALIDATION MESSAGE */
.validation-message {
    color: #dc3545;
    font-size: 14px;
    margin-top: 4px;
    display: none;
}

</style>

<div class="container booking-container">

<div class="booking-card">

<h3 class="booking-title text-center">
<i class="fa fa-calendar-check text-warning"></i>
Book a Table
</h3>

<!-- VALIDATION MESSAGE CONTAINER -->
<div id="validation-message" class="alert alert-danger validation-message" style="display: none;">
<i class="fa fa-exclamation-triangle"></i>
<span id="validation-text"></span>
</div>

<form action="process-booking.php" method="POST" id="booking-form">

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

<div class="col-md-4 mb-4">
<label class="form-label">
<i class="fa fa-clock"></i> Select Time
</label>
<select
name="booking_time"
class="form-control modern-input"
required
id="booking-time"
>
<option value="">Choose time...</option>
<?php foreach ($timeSlots as $slot): ?>
<option value="<?= $slot['value'] ?>"><?= $slot['label'] ?></option>
<?php endforeach; ?>
</select>
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

<!-- TABLE SELECTION -->
<div class="col-12 mb-4">
<label class="form-label">
<i class="fa fa-chair"></i> Select Table
</label>

<input type="hidden" name="table_id" id="selected_table_id" required>

<div class="table-grid" id="table_grid">
<?php foreach ($tables as $table): ?>
<div class="table-card" data-table-id="<?= $table['table_id'] ?>" data-table-number="<?= $table['table_number'] ?>" data-capacity="<?= $table['capacity'] ?>">
<h5 class="mb-1">Table <?= $table['table_number'] ?></h5>
<small>Capacity: <?= $table['capacity'] ?></small>
<span class="badge bg-success mt-2">Available</span>
</div>
<?php endforeach; ?>

<?php if (empty($tables)): ?>
<div class="table-card unavailable">
<strong>No tables available</strong>
<small>Please try another date.</small>
</div>
<?php endif; ?>
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
// Form validation and interaction handling
document.addEventListener('DOMContentLoaded', function() {
    const tableCards = document.querySelectorAll('.table-card:not(.unavailable)');
    const selectedTableInput = document.getElementById('selected_table_id');
    const bookingForm = document.getElementById('booking-form');
    const validationMessage = document.getElementById('validation-message');
    const validationText = document.getElementById('validation-text');
    const submitBtn = document.getElementById('submit-btn');

    // Table selection handling
    tableCards.forEach(card => {
        card.addEventListener('click', () => {
            tableCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedTableInput.value = card.getAttribute('data-table-id');
            hideValidationMessage();
        });
    });

    // Form submission validation
    bookingForm.addEventListener('submit', function(e) {
        const selectedTable = selectedTableInput.value;
        const selectedTime = document.getElementById('booking-time').value;
        const selectedDate = document.getElementById('booking-date').value;
        const guestCount = document.getElementById('number-of-guests').value;

        // Check if table is selected
        if (!selectedTable) {
            e.preventDefault();
            showValidationMessage('Please select a table before confirming your booking.');
            return false;
        }

        // Check if time is selected
        if (!selectedTime) {
            e.preventDefault();
            showValidationMessage('Please select a reservation time.');
            return false;
        }

        // Check if date is selected
        if (!selectedDate) {
            e.preventDefault();
            showValidationMessage('Please select a reservation date.');
            return false;
        }

        // Check if guest count is valid
        if (!guestCount || guestCount < 1) {
            e.preventDefault();
            showValidationMessage('Please enter a valid number of guests.');
            return false;
        }

        // All validations passed - allow form submission
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
    });

    // Helper functions for validation messages
    function showValidationMessage(message) {
        validationText.textContent = message;
        validationMessage.style.display = 'block';
        validationMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideValidationMessage() {
        validationMessage.style.display = 'none';
    }
});
</script>

<?php include "../includes/footer.php"; ?>

