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

if($bookingId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid booking is required']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT booking_id, table_id, status FROM bookings WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit();
    }

    $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
    $updateStmt->execute([$bookingId]);

    $assignedTablesStmt = $pdo->prepare("SELECT rt.table_id, rt.table_number FROM booking_table_assignments bta INNER JOIN restaurant_tables rt ON rt.table_id = bta.table_id WHERE bta.booking_id = ? ORDER BY rt.table_number + 0, rt.table_number ASC");
    $assignedTablesStmt->execute([$bookingId]);
    $assignedTables = $assignedTablesStmt->fetchAll(PDO::FETCH_ASSOC);

    $assignedTableIds = array_map(static function($tableRow) {
        return (int)$tableRow['table_id'];
    }, $assignedTables);
    $assignedTableNumbers = array_map(static function($tableRow) {
        return (string)$tableRow['table_number'];
    }, $assignedTables);

    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
        'status' => 'confirmed',
        'table_id' => !empty($assignedTableIds) ? $assignedTableIds[0] : null,
        'table_number' => !empty($assignedTableNumbers) ? $assignedTableNumbers[0] : null,
        'assigned_table_ids' => $assignedTableIds,
        'assigned_table_numbers' => $assignedTableNumbers,
    ]);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not confirm booking']);
}
?>