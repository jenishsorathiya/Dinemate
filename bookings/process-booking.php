<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

// Ensure start_time and end_time columns exist
try {
    ensureBookingRequestColumns($pdo);

    // First, try to query the columns
    $result = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'start_time'");
    $startTimeExists = $result->rowCount() > 0;
    
    $result = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'end_time'");
    $endTimeExists = $result->rowCount() > 0;
    
    $result = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'special_request'");
    $specialRequestExists = $result->rowCount() > 0;

    $result = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'table_id'");
    $tableIdColumn = $result->fetch(PDO::FETCH_ASSOC);
    
    if (!$startTimeExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN start_time TIME NOT NULL DEFAULT '12:00:00' AFTER booking_date");
        error_log("Added start_time column to bookings table");
    }
    
    if (!$endTimeExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN end_time TIME NOT NULL DEFAULT '13:00:00' AFTER start_time");
        error_log("Added end_time column to bookings table");
    }
    
    if (!$specialRequestExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN special_request TEXT DEFAULT NULL");
        error_log("Added special_request column to bookings table");
    }

    if ($tableIdColumn && $tableIdColumn['Null'] !== 'YES') {
        $pdo->exec("ALTER TABLE bookings MODIFY COLUMN table_id {$tableIdColumn['Type']} NULL");
        error_log("Updated table_id column to allow NULL values");
    }
} catch(PDOException $e) {
    error_log('Column migration error: ' . $e->getMessage());
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    redirect("book-table.php");
}

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    redirect("book-table.php");
}

$user_id = $_SESSION['user_id'];

$date = sanitize($_POST['booking_date']);
$start_time = sanitize($_POST['start_time']);
$end_time = sanitize($_POST['end_time']);
$guests = intval($_POST['number_of_guests']);
$special = isset($_POST['special_request']) ? sanitize($_POST['special_request']) : '';

// Restaurant hours configuration (should match frontend)
$restaurantOpen = '10:00';
$restaurantClose = '22:00';
$minDuration = 60; // minutes
$maxDuration = 180; // minutes

// Validate all required fields
if(empty($date) || empty($start_time) || empty($end_time) || empty($guests)){
    $_SESSION['error'] = 'All fields are required.';
    redirect("book-table.php");
}

if($guests < 1){
    $_SESSION['error'] = 'Number of guests must be at least 1.';
    redirect("book-table.php");
}

// Convert time strings to comparable format (HH:MM:SS)
$start_time = date('H:i:s', strtotime($start_time));
$end_time = date('H:i:s', strtotime($end_time));

/* ============ VALIDATION: Restaurant Hours ============ */
if($start_time < $restaurantOpen){
    $_SESSION['error'] = 'Start time cannot be before restaurant opening time (' . $restaurantOpen . ').';
    redirect("book-table.php");
}

if($end_time > $restaurantClose){
    $_SESSION['error'] = 'End time cannot be after restaurant closing time (' . $restaurantClose . ').';
    redirect("book-table.php");
}

/* ============ VALIDATION: Time Order ============ */
if($end_time <= $start_time){
    $_SESSION['error'] = 'End time must be after start time.';
    redirect("book-table.php");
}

/* ============ VALIDATION: Booking Duration ============ */
$startDateTime = new DateTime('2000-01-01 ' . $start_time);
$endDateTime = new DateTime('2000-01-01 ' . $end_time);
$durationMinutes = ($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60;

if($durationMinutes < $minDuration){
    $_SESSION['error'] = 'Booking duration must be at least ' . $minDuration . ' minutes.';
    redirect("book-table.php");
}

if($durationMinutes > $maxDuration){
    $_SESSION['error'] = 'Booking duration cannot exceed ' . $maxDuration . ' minutes.';
    redirect("book-table.php");
}

/* ============ VALIDATION: Restaurant Capacity ============ */
$capacityStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'available' AND capacity >= ?");
$capacityStmt->execute([$guests]);

if((int)$capacityStmt->fetchColumn() === 0){
    $_SESSION['error'] = 'We do not currently have a table that can accommodate that many guests.';
    redirect("book-table.php");
}

/* ============ INSERT BOOKING ============ */
$stmt = $pdo->prepare("
    INSERT INTO bookings 
    (user_id, table_id, booking_date, start_time, end_time, requested_start_time, requested_end_time, number_of_guests, special_request, status)
    VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, 'pending')
");

try {
    $stmt->execute([
        $user_id, 
        $date, 
        $start_time, 
        $end_time, 
        $start_time,
        $end_time,
        $guests, 
        $special
    ]);
    
    $booking_id = $pdo->lastInsertId();
    redirect("booking-confirmation.php?id=" . $booking_id);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error creating booking. Please try again.';
    error_log('Booking insertion error: ' . $e->getMessage());
    redirect("book-table.php");
}
?>

