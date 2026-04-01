<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

// Ensure start_time and end_time columns exist
try {
    $result = $pdo->query("DESCRIBE bookings");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN, 0);
    
    if (!in_array('start_time', $columns)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN start_time TIME NOT NULL DEFAULT '12:00:00'");
    }
    
    if (!in_array('end_time', $columns)) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN end_time TIME NOT NULL DEFAULT '13:00:00'");
    }
} catch(PDOException $e) {
    error_log('Migration error: ' . $e->getMessage());
}

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    redirect("book-table.php");
}

$user_id = $_SESSION['user_id'];

$date = sanitize($_POST['booking_date']);
$start_time = sanitize($_POST['start_time']);
$end_time = sanitize($_POST['end_time']);
$guests = intval($_POST['number_of_guests']);
$table_id = intval($_POST['table_id']);
$special = sanitize($_POST['special_request']);

// Restaurant hours configuration (should match frontend)
$restaurantOpen = '10:00';
$restaurantClose = '22:00';
$minDuration = 60; // minutes
$maxDuration = 180; // minutes

// Validate all required fields
if(empty($date) || empty($start_time) || empty($end_time) || empty($guests) || empty($table_id)){
    $_SESSION['error'] = 'All fields are required.';
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

/* ============ VALIDATION: Table Capacity ============ */
$stmt = $pdo->prepare("SELECT capacity FROM restaurant_tables WHERE table_id = ?");
$stmt->execute([$table_id]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$table){
    $_SESSION['error'] = 'Invalid table selected.';
    redirect("book-table.php");
}

if($guests > $table['capacity']){
    $_SESSION['error'] = 'Selected table cannot accommodate ' . $guests . ' guests. Maximum capacity is ' . $table['capacity'] . '.';
    redirect("book-table.php");
}

/* ============ VALIDATION: Check for Overlapping Bookings ============ */
// SQL Query to detect overlapping time slots for the same table on the same date
$stmt = $pdo->prepare("
    SELECT COUNT(*) as overlap_count 
    FROM bookings 
    WHERE table_id = ? 
    AND booking_date = ? 
    AND status IN ('pending', 'confirmed')
    AND (
        (start_time < ? AND end_time > ?)
        OR (start_time >= ? AND start_time < ?)
        OR (end_time > ? AND end_time <= ?)
    )
");

$stmt->execute([
    $table_id, 
    $date,
    $end_time,   // Check if existing start_time < new end_time
    $start_time, // AND existing end_time > new start_time
    $start_time, // OR existing start_time >= new start_time
    $end_time,   // AND existing start_time < new end_time
    $start_time, // OR existing end_time > new start_time
    $end_time    // AND existing end_time <= new end_time
]);

$result = $stmt->fetch(PDO::FETCH_ASSOC);

if($result['overlap_count'] > 0){
    $_SESSION['error'] = 'This table is already booked for the selected time slot. Please choose a different time, table, or date to complete your reservation.';
    redirect("book-table.php");
}

/* ============ INSERT BOOKING ============ */
$stmt = $pdo->prepare("
    INSERT INTO bookings 
    (user_id, table_id, booking_date, start_time, end_time, number_of_guests, special_request, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed')
");

try {
    $stmt->execute([
        $user_id, 
        $table_id, 
        $date, 
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

