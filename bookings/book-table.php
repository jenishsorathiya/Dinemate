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
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    place-items: center;
    max-width: 600px;
    margin: 0 auto;
}

/* Table Cards */
.table-card {
    width: 110px;
    height: 130px;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    position: relative;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 3px solid #e5e7eb;
    text-align: center;
    padding: 5px;
    gap: 0px;
}

.table-card:hover:not(.unavailable):not(.booked) {
    transform: translateY(-12px) scale(1.08);
    box-shadow: 0 12px 32px rgba(244, 180, 0, 0.25);
    border-color: #f4b400;
}

.table-card.selected {
    background: linear-gradient(135deg, #ff6b35 0%, #f4b400 100%);
    border-color: #ff6b35;
    box-shadow: 0 12px 40px rgba(255, 107, 53, 0.35);
    transform: scale(1.15);
    color: white;
}

.table-card.selected .table-number {
    color: white;
    font-weight: 800;
}

.table-card.selected .table-capacity {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    border-color: rgba(255, 255, 255, 0.5);
}

.table-card.unavailable {
    background: #ececec;
    border-color: #d0d0d0;
    opacity: 0.6;
    cursor: not-allowed;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.table-card.booked {
    background: linear-gradient(135deg, #ffe5e5 0%, #ffd9d9 100%);
    border-color: #ff6b6b;
    cursor: not-allowed;
    box-shadow: 0 8px 24px rgba(255, 107, 107, 0.15);
}

/* Restaurant Floor Plan Layout */
.restaurant-layout {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 25px;
    padding: 40px;
    background: linear-gradient(135deg, #fafbfc 0%, #f0f2f5 100%);
    border-radius: 20px;
    min-height: 600px;
    position: relative;
}

/* Table Grid Container */
.table-grid {
    grid-column: 1 / -1;
}

/* Table Visual Icon and Styling */
.table-visual {
    width: 100%;
    height: 70px;
    display: block;
    margin-bottom: 4px;
}

/* Available table - light gray */
.table-card:not(.booked) .table-visual .table-shape {
    fill: #e8e8e8;
    stroke: #999;
}

.table-card:not(.booked) .table-visual .availability-indicator {
    fill: #ff6b35;
    opacity: 0.9;
}

/* Booked table - red coloring */
.table-card.booked .table-visual .table-shape {
    fill: #ff6b6b;
    stroke: #cc0000;
}

.table-card.booked .table-visual rect[fill="#999"],
.table-card.booked .table-visual rect[fill="#666"] {
    fill: #cc0000;
}

.table-card.booked .table-visual .availability-indicator {
    opacity: 0;
}

/* Selected table - bright orange */
.table-card.selected .table-visual .table-shape {
    fill: #ff6b35;
    stroke: #ff5a1f;
}

.table-card.selected .table-visual rect[fill="#999"],
.table-card.selected .table-visual rect[fill="#666"] {
    fill: #ff5a1f;
}

.table-card.selected .table-visual .availability-indicator {
    opacity: 0;
}

.table-number {
    font-size: 16px;
    font-weight: 700;
    color: #2c3e50;
    margin: 4px 0;
    display: block;
}

.table-capacity {
    font-size: 12px;
    color: #666;
    background: #f0f0f0;
    padding: 4px 8px;
    border-radius: 8px;
    display: inline-block;
    margin: 6px 0;
    font-weight: 500;
    border: 1px solid #e0e0e0;
}

.table-status {
    margin-top: 8px;
}

.table-status .badge {
    padding: 4px 8px;
    font-size: 10px;
    font-weight: 600;
    background-color: #28a745;
}

.table-conflict-info {
    margin-top: 8px;
}

.table-conflict-info .badge {
    padding: 4px 8px;
    font-size: 10px;
    background-color: #dc3545;
}

.conflict-time {
    font-size: 10px;
    color: #dc3545;
    font-weight: 600;
    margin-top: 4px;
    display: block;
}

/* Availability Counter */
.availability-status {
    padding: 12px 16px;
    margin-bottom: 20px;
    border-radius: 8px;
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    font-size: 14px;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .table-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
    
    .table-section-left {
        grid-column: 1 / 2;
    }
    
    .table-section-middle {
        grid-column: 2 / 4;
        grid-template-columns: repeat(2, 1fr);
    }
    
    .table-section-right {
        grid-column: 4 / 5;
    }
}

@media (max-width: 768px) {
    .restaurant-layout {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .table-section-left {
        grid-column: 1 / 2;
    }
    
    .table-section-middle {
        grid-column: 2 / 3;
        grid-template-columns: 1fr;
    }
    
    .table-section-right {
        grid-column: 3 / 4;
    }
}

.table-card.booked {
    border-color: #dc3545;
    background: #fff5f5;
}

@media (max-width: 768px) {
    .table-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    
    .table-card {
        width: 100px;
        height: 100px;
    }
    
    .table-visual {
        height: 50px;
    }
}

@media (max-width: 576px) {
    .table-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .table-card {
        width: 90px;
        height: 90px;
    }
    
    .table-visual {
        height: 48px;
    }
    
    .table-number {
        font-size: 14px;
    }
    
    .table-capacity {
        font-size: 10px;
        padding: 2px 4px;
    }
    
    .restaurant-layout {
        padding: 20px;
    }
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

<!-- TABLE SELECTION -->
<div class="col-12 mb-4">
<label class="form-label" style="font-size: 18px; font-weight: 600; margin-bottom: 20px;">
<i class="fa fa-chair"></i> Select a Table
</label>

<div id="availability-status" class="alert alert-info" style="display: none; font-size: 14px;">
<small>
<strong><i class="fa fa-check-circle"></i> Availability:</strong> 
<span id="available-count" style="font-weight: 600;">0</span> available • 
<span id="booked-count" style="font-weight: 600;">0</span> already booked
</small>
</div>

<input type="hidden" name="table_id" id="selected_table_id" required>

<!-- Restaurant Floor Plan -->
<div class="restaurant-layout" id="table_grid">
    <div class="table-grid">
    <?php 
    // Helper function to display table card
    function displayTableCard($table) {
        $tableId = $table['table_id'];
        $tableNum = $table['table_number'];
        $capacity = $table['capacity'];
        
        // All tables use same rectangular design with 4 chairs - matching restaurant UI
        $svg = <<<SVG
        <svg class="table-visual" viewBox="0 0 80 100" xmlns="http://www.w3.org/2000/svg">
            <!-- Main rectangular table -->
            <rect class="table-shape" x="15" y="25" width="50" height="50" rx="6" fill="#d3d3d3" stroke="#999" stroke-width="2"/>
            
            <!-- Left side chair dashes (2) -->
            <rect x="8" y="32" width="5" height="8" fill="#999" stroke="#666" stroke-width="0.5" rx="1"/>
            <rect x="8" y="60" width="5" height="8" fill="#999" stroke="#666" stroke-width="0.5" rx="1"/>
            
            <!-- Right side chair dashes (2) -->
            <rect x="67" y="32" width="5" height="8" fill="#999" stroke="#666" stroke-width="0.5" rx="1"/>
            <rect x="67" y="60" width="5" height="8" fill="#999" stroke="#666" stroke-width="0.5" rx="1"/>
            
            <!-- Orange availability indicator dot -->
            <circle class="availability-indicator" cx="65" cy="28" r="5" fill="#ff6b35" opacity="0.9"/>
        </svg>
        SVG;
        ?>
        <div class="table-card" data-table-id="<?= $tableId ?>" data-table-number="<?= $tableNum ?>" data-capacity="<?= $capacity ?>" title="Table <?= $tableNum ?> - Capacity: <?= $capacity ?> guests">
            <?= $svg ?>
            <span class="table-number">T<?= $tableNum ?></span>
            
            <div class="table-status mt-2" style="display: none;">
                <span class="badge bg-success" style="font-size: 10px;">Available</span>
            </div>
            
            <div class="table-conflict-info mt-2" style="display: none; width: 100%;">
                <span class="badge bg-danger" style="font-size: 10px; display: block;">Booked</span>
                <small class="d-block conflict-time" style="color: #dc3545; font-size: 10px; margin-top: 4px;"></small>
            </div>
        </div>
        <?php
    }
    
    // Now call the function in the loop
    if (!empty($tables)) {
        foreach ($tables as $table) {
            displayTableCard($table);
        }
    } else {
        echo '<div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
            <p style="font-size: 18px; color: #999;">No tables available</p>
            <p style="color: #ccc;">Please try another date.</p>
        </div>';
    }
    ?>
    </div>
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

// Format time for display
function formatTimeDisplay(time) {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${period}`;
}

// Show availability error message to user
function showAvailabilityError(message) {
    let errorDiv = document.getElementById('availability-error');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'availability-error';
        errorDiv.style.cssText = 'padding: 12px; margin: 15px 0; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; display: block;';
        const tableGrid = document.getElementById('table_grid');
        tableGrid.parentNode.insertBefore(errorDiv, tableGrid);
    }
    errorDiv.textContent = '❌ ' + message;
    errorDiv.style.display = 'block';
}

// Hide availability error message
function hideAvailabilityError() {
    const errorDiv = document.getElementById('availability-error');
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
}

// Check table availability for selected date and time
function checkTableAvailability() {
    const date = document.getElementById('booking-date').value;
    const startTime = document.getElementById('start-time').value;
    const endTime = document.getElementById('end-time').value;
    
    // Don't check if fields are empty
    if (!date || !startTime || !endTime) {
        return;
    }
    
    // Show loading state
    const tableGrid = document.getElementById('table_grid');
    tableGrid.style.opacity = '0.6';
    
    // Call AJAX endpoint
    fetch(`check-availability.php?date=${date}&start_time=${startTime}&end_time=${endTime}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server error: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Availability response:', data);
            if (data.success) {
                updateTableAvailability(data);
                hideAvailabilityError();
            } else {
                showAvailabilityError(data.error || 'Failed to check availability');
            }
        })
        .catch(error => {
            console.error('Error checking availability:', error);
            showAvailabilityError(`Network error: ${error.message}`);
        })
        .finally(() => {
            tableGrid.style.opacity = '1';
        });
}

// Update table cards based on availability
function updateTableAvailability(data) {
    const tableCards = document.querySelectorAll('.table-card:not(.unavailable)');
    const selectedTableInput = document.getElementById('selected_table_id');
    
    // Update counts
    document.getElementById('available-count').textContent = data.availableCount;
    document.getElementById('booked-count').textContent = data.bookedCount;
    document.getElementById('availability-status').style.display = 'block';
    
    // Clear current selection if table is now booked
    const currentSelection = selectedTableInput.value;
    const currentCard = document.querySelector(`.table-card[data-table-id="${currentSelection}"]`);
    if (currentCard && currentCard.classList.contains('unavailable')) {
        selectedTableInput.value = '';
        currentCard.classList.remove('selected');
    }
    
    // Update available tables
    data.availableTables.forEach(table => {
        const card = document.querySelector(`.table-card[data-table-id="${table.table_id}"]`);
        if (card) {
            card.classList.remove('unavailable');
            card.classList.remove('booked');
            card.querySelector('.table-status').style.display = 'block';
            card.querySelector('.table-conflict-info').style.display = 'none';
        }
    });
    
    // Update booked tables
    data.bookedTables.forEach(table => {
        const card = document.querySelector(`.table-card[data-table-id="${table.table_id}"]`);
        if (card) {
            card.classList.add('unavailable', 'booked');
            card.classList.remove('selected');
            
            const statusDiv = card.querySelector('.table-status');
            const conflictDiv = card.querySelector('.table-conflict-info');
            const conflictTime = card.querySelector('.conflict-time');
            
            statusDiv.style.display = 'none';
            conflictDiv.style.display = 'block';
            
            // Format and display conflicting time
            if (table.conflictingBookings && table.conflictingBookings.length > 0) {
                const booking = table.conflictingBookings[0];
                const startFormatted = formatTimeDisplay(booking.start_time);
                const endFormatted = formatTimeDisplay(booking.end_time);
                conflictTime.textContent = `Booked: ${startFormatted} - ${endFormatted}`;
            }
        }
    });
}

// Form validation and interaction handling
document.addEventListener('DOMContentLoaded', function() {
    const selectedTableInput = document.getElementById('selected_table_id');
    const bookingForm = document.getElementById('booking-form');
    const validationMessage = document.getElementById('validation-message');
    const validationText = document.getElementById('validation-text');
    const submitBtn = document.getElementById('submit-btn');
    
    const startTimeInput = document.getElementById('start-time');
    const endTimeInput = document.getElementById('end-time');
    const bookingDateInput = document.getElementById('booking-date');
    const startTimeError = document.getElementById('start-time-error');
    const endTimeError = document.getElementById('end-time-error');

    // Table selection handling
    document.addEventListener('click', function(e) {
        const card = e.target.closest('.table-card');
        if (card && !card.classList.contains('unavailable') && !card.classList.contains('booked')) {
            document.querySelectorAll('.table-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedTableInput.value = card.getAttribute('data-table-id');
            hideValidationMessage();
        }
    });

    // Check availability when inputs change
    startTimeInput.addEventListener('change', () => {
        validateTimeSlot();
        checkTableAvailability();
    });
    
    endTimeInput.addEventListener('change', () => {
        validateTimeSlot();
        checkTableAvailability();
    });
    
    bookingDateInput.addEventListener('change', () => {
        checkTableAvailability();
    });

    // Real-time time validation
    startTimeInput.addEventListener('change', validateTimeSlot);
    endTimeInput.addEventListener('change', validateTimeSlot);

    // Form submission validation
    bookingForm.addEventListener('submit', function(e) {
        const selectedTable = selectedTableInput.value;
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        const selectedDate = document.getElementById('booking-date').value;
        const guestCount = document.getElementById('number-of-guests').value;

        // Check if table is selected
        if (!selectedTable) {
            e.preventDefault();
            showValidationMessage('Please select an available table.');
            return false;
        }

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

