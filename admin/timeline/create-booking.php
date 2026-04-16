<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureBookingRequestColumns($pdo);

requireAdmin(['json' => true]);

$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$customerProfileId = (int) ($data['customer_profile_id'] ?? 0);
$customerEmail = trim($data['customer_email'] ?? '');
$customerPhone = trim($data['customer_phone'] ?? '');
$bookingDate = trim($data['booking_date'] ?? '');
$startTimeInput = trim($data['start_time'] ?? '');
$guestCount = (int)($data['number_of_guests'] ?? 0);
$specialRequest = trim($data['special_request'] ?? '');

if($name === '' || $bookingDate === '' || $startTimeInput === '' || $guestCount < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All required fields must be provided']);
    exit();
}

if($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'If provided, email must be valid']);
    exit();
}

if($customerPhone !== '' && !preg_match('/^[0-9\s\-\(\)\+]+$/', $customerPhone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'If provided, phone number must be valid']);
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
    if ($customerProfileId > 0) {
        $profileStmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
        $profileStmt->execute([$customerProfileId]);
        $existingProfile = $profileStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingProfile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Selected customer profile was not found']);
            exit();
        }

        $profileName = $name !== '' ? $name : (string) ($existingProfile['name'] ?? 'Guest');
        $profileEmail = $customerEmail !== '' ? $customerEmail : (string) ($existingProfile['email'] ?? '');
        $profilePhone = $customerPhone !== '' ? $customerPhone : (string) ($existingProfile['phone'] ?? '');

        $customerProfileId = upsertCustomerProfile(
            $pdo,
            $profileName,
            $profileEmail !== '' ? $profileEmail : null,
            $profilePhone !== '' ? $profilePhone : null,
            isset($existingProfile['linked_user_id']) && $existingProfile['linked_user_id'] !== null ? (int) $existingProfile['linked_user_id'] : null
        );

        $name = $profileName;
        $customerEmail = $profileEmail;
        $customerPhone = $profilePhone;
    } else {
        $customerProfileId = upsertCustomerProfile(
            $pdo,
            $name,
            $customerEmail !== '' ? $customerEmail : null,
            $customerPhone !== '' ? $customerPhone : null,
            null
        );
    }

    $bookingStmt = $pdo->prepare("INSERT INTO bookings (user_id, customer_profile_id, customer_name, customer_phone, customer_email, guest_access_token, table_id, booking_date, start_time, end_time, requested_start_time, requested_end_time, number_of_guests, special_request, status, booking_source, created_by_user_id) VALUES (NULL, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'pending', 'admin_manual', ?)");
    $bookingStmt->execute([
        $customerProfileId,
        $name,
        $customerPhone !== '' ? $customerPhone : null,
        $customerEmail !== '' ? $customerEmail : null,
        generateGuestAccessToken(),
        $bookingDate,
        $startTime,
        $endTime,
        $startTime,
        $endTime,
        $guestCount,
        $specialRequest !== '' ? $specialRequest : null,
        (int) (getCurrentUserId() ?? 0) ?: null,
    ]);

    $bookingId = $pdo->lastInsertId();
    notifyBookingEvent($pdo, $bookingId, 'booking_request_received');

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
            'booking_source' => 'admin_manual',
            'booking_source_label' => getBookingSourceLabel('admin_manual'),
            'customer_name' => $name,
            'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
            'customer_email' => $customerEmail !== '' ? $customerEmail : null,
        ],
    ]);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not create booking']);
}
?>
