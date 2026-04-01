<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);

$data = json_decode(file_get_contents('php://input'), true);

$bookingId = (int)($data['booking_id'] ?? 0);
$customerName = trim($data['customer_name'] ?? '');
$requestedStart = trim($data['requested_start_time'] ?? '');
$requestedEnd = trim($data['requested_end_time'] ?? '');
$assignedStart = trim($data['start_time'] ?? '');
$assignedEnd = trim($data['end_time'] ?? '');
$guestCount = (int)($data['number_of_guests'] ?? 0);
$specialRequest = trim($data['special_request'] ?? '');

if($bookingId < 1 || $customerName === '' || $requestedStart === '' || $requestedEnd === '' || $assignedStart === '' || $assignedEnd === '' || $guestCount < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All required fields must be provided']);
    exit();
}

function normalizeBookingTime($timeValue) {
    $timestamp = strtotime($timeValue);
    if($timestamp === false) {
        return null;
    }
    return date('H:i:s', $timestamp);
}

function validateTimelineTimeRange($startTime, $endTime) {
    if($startTime < '10:00:00') {
        return 'Start time cannot be before 10:00';
    }
    if($endTime > '22:00:00') {
        return 'End time cannot be after 22:00';
    }
    if($endTime <= $startTime) {
        return 'End time must be after start time';
    }

    $durationMinutes = (strtotime($endTime) - strtotime($startTime)) / 60;
    if($durationMinutes < 30) {
        return 'Booking duration must be at least 30 minutes';
    }

    return null;
}

$requestedStartTime = normalizeBookingTime($requestedStart);
$requestedEndTime = normalizeBookingTime($requestedEnd);
$assignedStartTime = normalizeBookingTime($assignedStart);
$assignedEndTime = normalizeBookingTime($assignedEnd);

if(!$requestedStartTime || !$requestedEndTime || !$assignedStartTime || !$assignedEndTime) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid time value provided']);
    exit();
}

$requestedTimeError = validateTimelineTimeRange($requestedStartTime, $requestedEndTime);
if($requestedTimeError) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Requested time: ' . $requestedTimeError]);
    exit();
}

$assignedTimeError = validateTimelineTimeRange($assignedStartTime, $assignedEndTime);
if($assignedTimeError) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Assigned time: ' . $assignedTimeError]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT b.*, u.name AS user_name FROM bookings b JOIN users u ON b.user_id = u.user_id WHERE b.booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit();
    }

    $assignedTablesStmt = $pdo->prepare("SELECT rt.table_id, rt.table_number, rt.capacity FROM booking_table_assignments bta INNER JOIN restaurant_tables rt ON rt.table_id = bta.table_id WHERE bta.booking_id = ? ORDER BY rt.table_number + 0, rt.table_number");
    $assignedTablesStmt->execute([$bookingId]);
    $assignedTables = $assignedTablesStmt->fetchAll(PDO::FETCH_ASSOC);

    $assignedTableIds = array_map(static function ($tableRow) {
        return (int)$tableRow['table_id'];
    }, $assignedTables);
    $assignedTableNumbers = array_map(static function ($tableRow) {
        return (string)$tableRow['table_number'];
    }, $assignedTables);
    $assignedCapacity = array_sum(array_map(static function ($tableRow) {
        return (int)$tableRow['capacity'];
    }, $assignedTables));

    if(!empty($assignedTableIds) && $assignedCapacity < $guestCount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Guest count exceeds the assigned table capacity']);
        exit();
    }

    if(!empty($assignedTableIds)) {
        $conflictStmt = $pdo->prepare("
            SELECT COUNT(*) as conflict_count
            FROM booking_table_assignments bta
            INNER JOIN bookings b ON b.booking_id = bta.booking_id
            WHERE bta.table_id = ?
            AND b.booking_date = ?
            AND b.booking_id != ?
            AND b.status IN ('pending', 'confirmed')
            AND (
                (b.start_time < ? AND b.end_time > ?)
                OR (b.start_time >= ? AND b.start_time < ?)
                OR (b.end_time > ? AND b.end_time <= ?)
            )
        ");

        foreach($assignedTableIds as $assignedTableId) {
            $conflictStmt->execute([
                $assignedTableId,
                $booking['booking_date'],
                $bookingId,
                $assignedEndTime,
                $assignedStartTime,
                $assignedStartTime,
                $assignedEndTime,
                $assignedStartTime,
                $assignedEndTime,
            ]);

            $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);
            if((int)$conflict['conflict_count'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Assigned time conflicts with another booking at one of the assigned tables']);
                exit();
            }
        }
    }

    $updateStmt = $pdo->prepare("
        UPDATE bookings
        SET customer_name_override = ?,
            requested_start_time = ?,
            requested_end_time = ?,
            start_time = ?,
            end_time = ?,
            number_of_guests = ?,
            special_request = ?
        WHERE booking_id = ?
    ");
    $updateStmt->execute([
        $customerName,
        $requestedStartTime,
        $requestedEndTime,
        $assignedStartTime,
        $assignedEndTime,
        $guestCount,
        $specialRequest !== '' ? $specialRequest : null,
        $bookingId,
    ]);

    echo json_encode([
        'success' => true,
        'booking' => [
            'booking_id' => $bookingId,
            'user_id' => (int)$booking['user_id'],
            'table_id' => $booking['table_id'] !== null ? (int)$booking['table_id'] : null,
            'table_number' => $assignedTableNumbers[0] ?? null,
            'assigned_table_ids' => $assignedTableIds,
            'assigned_table_numbers' => $assignedTableNumbers,
            'booking_date' => $booking['booking_date'],
            'start_time' => $assignedStartTime,
            'end_time' => $assignedEndTime,
            'requested_start_time' => $requestedStartTime,
            'requested_end_time' => $requestedEndTime,
            'number_of_guests' => $guestCount,
            'special_request' => $specialRequest !== '' ? $specialRequest : null,
            'status' => $booking['status'],
            'customer_name' => $customerName,
            'customer_name_override' => $customerName,
        ]
    ]);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not update booking details']);
}
?>