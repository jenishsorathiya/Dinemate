<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$bookingDate = trim($data['booking_date'] ?? '');
$startTimeInput = trim($data['start_time'] ?? '');
$guestCount = (int)($data['number_of_guests'] ?? 0);
$specialRequest = trim($data['special_request'] ?? '');

if($name === '' || $bookingDate === '' || $startTimeInput === '' || $guestCount < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All required fields must be provided']);
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
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare("SELECT user_id, name FROM users WHERE role = 'customer' AND name = ? ORDER BY user_id ASC LIMIT 1");
    $userStmt->execute([$name]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if(!$user) {
        $emailBase = preg_replace('/[^a-z0-9]+/i', '.', strtolower($name));
        $emailBase = trim($emailBase, '.');
        if($emailBase === '') {
            $emailBase = 'guest';
        }

        $generatedEmail = sprintf('%s.%s@admin-booking.local', $emailBase, uniqid());
        $generatedPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);

        $insertUserStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, created_at) VALUES (?, ?, NULL, ?, 'customer', NOW())");
        $insertUserStmt->execute([$name, $generatedEmail, $generatedPassword]);

        $user = [
            'user_id' => $pdo->lastInsertId(),
            'name' => $name,
        ];
    }

    $bookingStmt = $pdo->prepare("INSERT INTO bookings (user_id, table_id, booking_date, start_time, end_time, number_of_guests, special_request, status) VALUES (?, NULL, ?, ?, ?, ?, ?, 'pending')");
    $bookingStmt->execute([
        $user['user_id'],
        $bookingDate,
        $startTime,
        $endTime,
        $guestCount,
        $specialRequest !== '' ? $specialRequest : null,
    ]);

    $bookingId = $pdo->lastInsertId();
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'booking' => [
            'booking_id' => (int)$bookingId,
            'user_id' => (int)$user['user_id'],
            'table_id' => null,
            'table_number' => null,
            'booking_date' => $bookingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'number_of_guests' => $guestCount,
            'special_request' => $specialRequest !== '' ? $specialRequest : null,
            'status' => 'pending',
            'customer_name' => $user['name'],
        ],
    ]);
} catch(Throwable $e) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not create booking']);
}
?>