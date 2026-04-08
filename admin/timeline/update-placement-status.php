<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

requireAdmin(['json' => true]);

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);

$data = json_decode(file_get_contents('php://input'), true);

$bookingId = (int) ($data['booking_id'] ?? 0);
$nextPlacementStatus = strtolower(trim((string) ($data['reservation_card_status'] ?? '')));

if ($bookingId < 1 || !in_array($nextPlacementStatus, getBookingPlacementStatuses(), true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid booking and placement status are required']);
    exit();
}

try {
    $bookingStmt = $pdo->prepare("
        SELECT b.booking_id, b.status,
               GROUP_CONCAT(DISTINCT bta.table_id ORDER BY bta.table_id SEPARATOR ',') AS assigned_table_ids
        FROM bookings b
        LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
        WHERE b.booking_id = ?
        GROUP BY b.booking_id
        LIMIT 1
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit();
    }

    $bookingStatus = strtolower((string) ($booking['status'] ?? ''));
    if ($bookingStatus === 'cancelled') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Cancelled bookings do not have a placement state']);
        exit();
    }

    $hasAssignedTables = trim((string) ($booking['assigned_table_ids'] ?? '')) !== '';
    if (!$hasAssignedTables) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Assign a table before updating placement']);
        exit();
    }

    $updateStmt = $pdo->prepare("UPDATE bookings SET reservation_card_status = ? WHERE booking_id = ?");
    $updateStmt->execute([$nextPlacementStatus, $bookingId]);

    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
        'reservation_card_status' => $nextPlacementStatus,
        'reservation_card_status_label' => getBookingPlacementLabel($nextPlacementStatus),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not update placement status']);
}
?>
