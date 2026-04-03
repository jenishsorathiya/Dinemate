<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureTableAreasSchema($pdo);
ensureBookingTableAssignmentsTable($pdo);

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$areaId = (int)($data['area_id'] ?? 0);

if($areaId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid area is required']);
    exit();
}

try {
    $areaStmt = $pdo->prepare("SELECT area_id, name FROM table_areas WHERE area_id = ? AND is_active = 1");
    $areaStmt->execute([$areaId]);
    $area = $areaStmt->fetch(PDO::FETCH_ASSOC);

    if(!$area) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Area not found']);
        exit();
    }

    $activeAreaCount = (int)$pdo->query("SELECT COUNT(*) FROM table_areas WHERE is_active = 1")->fetchColumn();
    if($activeAreaCount <= 1) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'You must keep at least one area']);
        exit();
    }

    $tableStmt = $pdo->prepare("SELECT table_id, table_number FROM restaurant_tables WHERE area_id = ? ORDER BY sort_order ASC, table_number + 0, table_number ASC");
    $tableStmt->execute([$areaId]);
    $tables = $tableStmt->fetchAll(PDO::FETCH_ASSOC);

    $tableIds = array_map(static function ($tableRow) {
        return (int)$tableRow['table_id'];
    }, $tables);

    $affectedBookingIds = [];

    $pdo->beginTransaction();

    if(!empty($tableIds)) {
        $placeholders = implode(',', array_fill(0, count($tableIds), '?'));

        $bookingStmt = $pdo->prepare("SELECT DISTINCT booking_id FROM booking_table_assignments WHERE table_id IN ($placeholders)");
        $bookingStmt->execute($tableIds);
        $affectedBookingIds = array_map('intval', $bookingStmt->fetchAll(PDO::FETCH_COLUMN));

        $remainingAssignmentStmt = $pdo->prepare("SELECT table_id FROM booking_table_assignments WHERE booking_id = ? AND table_id NOT IN ($placeholders) ORDER BY created_at ASC, table_id ASC");
        foreach ($affectedBookingIds as $bookingId) {
            $remainingAssignmentStmt->execute(array_merge([$bookingId], $tableIds));
            $remainingTableIds = array_map('intval', $remainingAssignmentStmt->fetchAll(PDO::FETCH_COLUMN));
            syncBookingTableAssignments($pdo, $bookingId, $remainingTableIds);
        }

        $deleteAssignmentsStmt = $pdo->prepare("DELETE FROM booking_table_assignments WHERE table_id IN ($placeholders)");
        $deleteAssignmentsStmt->execute($tableIds);

        $deleteTablesStmt = $pdo->prepare("DELETE FROM restaurant_tables WHERE table_id IN ($placeholders)");
        $deleteTablesStmt->execute($tableIds);
    }

    $deleteStmt = $pdo->prepare("DELETE FROM table_areas WHERE area_id = ?");
    $deleteStmt->execute([$areaId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'area_id' => $areaId,
        'name' => $area['name'],
        'deleted_table_ids' => $tableIds,
        'affected_booking_ids' => $affectedBookingIds,
    ]);
} catch(PDOException $e) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>