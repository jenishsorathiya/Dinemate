<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureBookingRequestColumns($pdo);

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$customerEmail = trim($data['customer_email'] ?? '');
$customerPhone = trim($data['customer_phone'] ?? '');
$bookingDate = trim($data['booking_date'] ?? '');
$startTimeInput = trim($data['start_time'] ?? '');
$guestCount = (int)($data['number_of_guests'] ?? 0);
$specialRequest = trim($data['special_request'] ?? '');

if($name === '' || $customerEmail === '' || $customerPhone === '' || $bookingDate === '' || $startTimeInput === '' || $guestCount < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All required fields must be provided']);
    exit();
}

if(!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid email address is required']);
    exit();
}

if(!preg_match('/^[0-9\s\-\(\)\+]+$/', $customerPhone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid phone number is required']);
    exit();
}

$startTimestamp = strtotime($startTimeInput);
if($startTimestamp === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking time']);
    exit();
}

$startTime = date('H:i:s', $startTimestamp);
$endTime = date('H:i:s', strtotime('+60 minutes', $startTimestamp));

if($startTime < '10:00:00' || $endTime > '22:00:00') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bookings must fit within restaurant hours 10:00 - 22:00']);
    exit();
}

$capacityStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'available' AND capacity >= ?");
$capacityStmt->execute([$guestCount]);
if((int)$capacityStmt->fetchColumn() === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No available table can accommodate that many guests']);
    exit();
}

try {
    $bookingStmt = $pdo->prepare("INSERT INTO bookings (user_id, customer_name, customer_phone, customer_email, guest_access_token, table_id, booking_date, start_time, end_time, requested_start_time, requested_end_time, number_of_guests, special_request, status) VALUES (NULL, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $bookingStmt->execute([
        $name,
        $customerPhone,
        $customerEmail,
        generateGuestAccessToken(),
        $bookingDate,
        $startTime,
        $endTime,
        $startTime,
        $endTime,
        $guestCount,
        $specialRequest !== '' ? $specialRequest : null,
    ]);

    $bookingId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'booking' => [
            'booking_id' => (int)$bookingId,
            'user_id' => null,
            'table_id' => null,
            'table_number' => null,
            'booking_date' => $bookingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'requested_start_time' => $startTime,
            'requested_end_time' => $endTime,
            'number_of_guests' => $guestCount,
            'special_request' => $specialRequest !== '' ? $specialRequest : null,
            'status' => 'pending',
            'customer_name' => $name,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
        ],
    ]);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not create booking']);
}
?>