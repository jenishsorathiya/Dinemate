<?php
require_once "../config/db.php";
require_once "../includes/functions.php";

startAppSession();

try {
    ensureBookingRequestColumns($pdo);
    ensureSettingsSchema($pdo);
    $bookingSettings = getBookingSettings($pdo);
} catch(PDOException $e) {
    error_log('Column migration error: ' . $e->getMessage());
    $_SESSION['error'] = 'Booking service is temporarily unavailable. Please try again shortly.';
    redirect("book-table.php");
}

if($_SERVER["REQUEST_METHOD"] !== "POST"){
    redirect("book-table.php");
}

requireValidCsrfToken('booking', ['redirect' => appPath('customer/book-table.php')]);

$user_id = (isLoggedIn() && getCurrentUserRole() === 'customer') ? (int) $_SESSION['user_id'] : null;
$bookingSource = $user_id ? 'customer_account' : 'guest_web';
$createdByUserId = $user_id ?: null;

$customer_name = trim($_POST['customer_name'] ?? '');
$customer_email = trim($_POST['customer_email'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');

$date = sanitize($_POST['booking_date']);
$start_time = sanitize($_POST['start_time']);
$guests = intval($_POST['number_of_guests']);
$special = isset($_POST['special_request']) ? sanitize($_POST['special_request']) : '';

// Ensure booking is enabled
if (!$bookingSettings['enable_online_bookings']) {
    $_SESSION['error'] = 'Online bookings are currently disabled.';
    redirect("book-table.php");
}

// Restaurant hours configuration (should match frontend)
$restaurantOpen = '10:00';
$restaurantClose = '22:00';
$minDuration = max(30, intval($bookingSettings['booking_duration_minutes']));
$maxDuration = $minDuration;
$minGuests = max(1, intval($bookingSettings['min_party_size']));
$maxGuests = max($minGuests, intval($bookingSettings['max_party_size']));
$minimumAdvanceMinutes = max(0, intval($bookingSettings['minimum_advanced_booking_minutes']));

// Validate all required fields
if(empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($date) || empty($start_time) || empty($guests)){
    $_SESSION['error'] = 'All fields are required.';
    redirect("book-table.php");
}

if (strlen($customer_name) < 2 || strlen($customer_name) > 100) {
    $_SESSION['error'] = 'Name must be between 2 and 100 characters.';
    redirect("book-table.php");
}

if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Please enter a valid email address.';
    redirect("book-table.php");
}

if (strlen($customer_email) > 100) {
    $_SESSION['error'] = 'Email address is too long.';
    redirect("book-table.php");
}

if (!preg_match("/^[0-9\s\-\(\)\+]+$/", $customer_phone)) {
    $_SESSION['error'] = 'Phone number can only contain digits, spaces, hyphens, parentheses, and plus signs.';
    redirect("book-table.php");
}

if (strlen(preg_replace('/\D+/', '', $customer_phone)) < 6 || strlen($customer_phone) > 30) {
    $_SESSION['error'] = 'Phone number must be at least 6 digits and no longer than 30 characters.';
    redirect("book-table.php");
}

if($guests < $minGuests){
    $_SESSION['error'] = 'Number of guests must be at least ' . $minGuests . '.';
    redirect("book-table.php");
}

if($guests > $maxGuests){
    $_SESSION['error'] = 'Number of guests cannot exceed ' . $maxGuests . '.';
    redirect("book-table.php");
}

$requestedDateTime = strtotime($date . ' ' . $start_time);
if ($requestedDateTime === false) {
    $_SESSION['error'] = 'Please choose a valid booking date and time.';
    redirect("book-table.php");
}

$minimumAdvanceSeconds = $minimumAdvanceMinutes * 60;
if ($requestedDateTime < time() + $minimumAdvanceSeconds) {
    $_SESSION['error'] = 'Bookings must be made at least ' . $minimumAdvanceMinutes . ' minutes in advance.';
    redirect("book-table.php");
}

if (!$bookingSettings['allow_table_request']) {
    $special = '';
}

// Convert the selected arrival time into a fixed booking duration request.
$start_time = date('H:i:s', strtotime($start_time));
$end_time = date('H:i:s', strtotime($start_time . ' +' . $minDuration . ' minutes'));

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
$customerProfileId = upsertCustomerProfile(
    $pdo,
    $customer_name,
    $customer_email,
    $customer_phone,
    $user_id
);

$assignedTableId = null;
if ($bookingSettings['auto_table_assignment']) {
    $assignedTableId = findAvailableTableForBooking(
        $pdo,
        $date,
        $start_time,
        $end_time,
        $guests
    );
}

$stmt = $pdo->prepare("
    INSERT INTO bookings 
    (user_id, customer_profile_id, customer_name, customer_phone, customer_email, guest_access_token, table_id, booking_date, start_time, end_time, requested_start_time, requested_end_time, number_of_guests, special_request, status, booking_source, created_by_user_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
");

try {
    $guestAccessToken = generateGuestAccessToken();

    $stmt->execute([
        $user_id,
        $customerProfileId,
        $customer_name,
        $customer_phone,
        $customer_email,
        $guestAccessToken,
        $assignedTableId,
        $date,
        $start_time,
        $end_time,
        $start_time,
        $end_time,
        $guests,
        $special,
        $bookingSource,
        $createdByUserId
    ]);
    
    $booking_id = $pdo->lastInsertId();
    notifyBookingEvent($pdo, (int)$booking_id, 'booking_request_received');
    redirect("booking-confirmation.php?id=" . $booking_id . "&token=" . urlencode($guestAccessToken));
    
} catch(PDOException $e) {
    // Always log the full exception for server-side diagnostics
    error_log('Booking insertion error: ' . $e->getMessage());
    error_log($e->getTraceAsString());

    $_SESSION['error'] = 'Error creating booking. Please try again.';

    // Optional: write a concise message to a local log file for easier inspection
    $logLine = '[' . date('Y-m-d H:i:s') . '] Booking insertion error: ' . $e->getMessage() . "\n";
    @file_put_contents(__DIR__ . '/../storage/bookings_errors.log', $logLine, FILE_APPEND | LOCK_EX);

    redirect("book-table.php");
}
?>

