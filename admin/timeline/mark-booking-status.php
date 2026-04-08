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
$nextStatus = strtolower(trim((string) ($data['status'] ?? '')));
$allowedTransitions = ['completed', 'cancelled', 'no_show'];

if ($bookingId < 1 || !in_array($nextStatus, $allowedTransitions, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid booking and status are required']);
    exit();
}

try {
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare("SELECT booking_id, status, reservation_card_status FROM bookings WHERE booking_id = ? LIMIT 1");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit();
    }

    if (strtolower((string) ($booking['status'] ?? '')) !== 'confirmed') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Only confirmed bookings can be marked with a service outcome']);
        exit();
    }

    if ($nextStatus === 'cancelled') {
        $deleteAssignmentsStmt = $pdo->prepare("DELETE FROM booking_table_assignments WHERE booking_id = ?");
        $deleteAssignmentsStmt->execute([$bookingId]);

        $updateStmt = $pdo->prepare("UPDATE bookings SET status = ?, table_id = NULL, reservation_card_status = NULL WHERE booking_id = ?");
        $updateStmt->execute([$nextStatus, $bookingId]);
        $nextPlacementStatus = null;
    } else {
        $updateStmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
        $updateStmt->execute([$nextStatus, $bookingId]);
        $currentPlacementStatus = strtolower((string) ($booking['reservation_card_status'] ?? ''));
        $nextPlacementStatus = in_array($currentPlacementStatus, getBookingPlacementStatuses(), true)
            ? $currentPlacementStatus
            : null;
    }

    $pdo->commit();

    $assignedTablesStmt = $pdo->prepare("SELECT rt.table_id, rt.table_number FROM booking_table_assignments bta INNER JOIN restaurant_tables rt ON rt.table_id = bta.table_id WHERE bta.booking_id = ? ORDER BY rt.table_number + 0, rt.table_number ASC");
    $assignedTablesStmt->execute([$bookingId]);
    $assignedTables = $assignedTablesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
        'status' => $nextStatus,
        'status_label' => getBookingStatusLabel($nextStatus),
        'table_id' => !empty($assignedTables) ? (int) $assignedTables[0]['table_id'] : null,
        'table_number' => !empty($assignedTables) ? (string) $assignedTables[0]['table_number'] : null,
        'assigned_table_ids' => array_map(static function ($tableRow) {
            return (int) $tableRow['table_id'];
        }, $assignedTables),
        'assigned_table_numbers' => array_map(static function ($tableRow) {
            return (string) $tableRow['table_number'];
        }, $assignedTables),
        'reservation_card_status' => $nextPlacementStatus,
        'reservation_card_status_label' => $nextPlacementStatus !== null ? getBookingPlacementLabel($nextPlacementStatus) : null,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not update booking status']);
}
?>
