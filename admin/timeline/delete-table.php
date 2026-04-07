<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureTableAreasSchema($pdo);
ensureBookingTableAssignmentsTable($pdo);

requireAdmin(['json' => true]);

$data = json_decode(file_get_contents('php://input'), true);
$tableId = (int)($data['table_id'] ?? 0);

if($tableId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid table is required']);
    exit();
}

try {
    $tableStmt = $pdo->prepare("SELECT table_id, table_number, area_id FROM restaurant_tables WHERE table_id = ?");
    $tableStmt->execute([$tableId]);
    $table = $tableStmt->fetch(PDO::FETCH_ASSOC);

    if(!$table) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Table not found']);
        exit();
    }

    $bookingCheckStmt = $pdo->prepare(" 
        SELECT COUNT(*)
        FROM booking_table_assignments bta
        INNER JOIN bookings b ON b.booking_id = bta.booking_id
        WHERE bta.table_id = ?
        AND b.status IN ('pending', 'confirmed')
    ");
    $bookingCheckStmt->execute([$tableId]);

    if((int)$bookingCheckStmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This table still has active bookings assigned to it']);
        exit();
    }

    $cleanupAssignmentsStmt = $pdo->prepare("DELETE FROM booking_table_assignments WHERE table_id = ?");
    $cleanupAssignmentsStmt->execute([$tableId]);

    $deleteStmt = $pdo->prepare("DELETE FROM restaurant_tables WHERE table_id = ?");
    $deleteStmt->execute([$tableId]);

    echo json_encode([
        'success' => true,
        'table_id' => $tableId,
        'table_number' => $table['table_number'],
        'area_id' => (int)$table['area_id'],
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>