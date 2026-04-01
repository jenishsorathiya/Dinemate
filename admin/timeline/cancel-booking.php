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

ensureBookingTableAssignmentsTable($pdo);

$data = json_decode(file_get_contents('php://input'), true);
$bookingId = (int)($data['booking_id'] ?? 0);

if($bookingId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid booking_id is required']);
    exit();
}

try {
    $pdo->beginTransaction();

    $bookingStmt = $pdo->prepare("SELECT booking_id FROM bookings WHERE booking_id = ? LIMIT 1");
    $bookingStmt->execute([$bookingId]);

    if(!$bookingStmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit();
    }

    $deleteAssignmentsStmt = $pdo->prepare("DELETE FROM booking_table_assignments WHERE booking_id = ?");
    $deleteAssignmentsStmt->execute([$bookingId]);

    $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', table_id = NULL WHERE booking_id = ?");
    $updateStmt->execute([$bookingId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
    ]);
} catch(Throwable $e) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not cancel booking']);
}
?>