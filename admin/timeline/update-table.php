<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureTableAreasSchema($pdo);

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$tableId = (int)($data['table_id'] ?? 0);
$capacity = (int)($data['capacity'] ?? 0);
$areaId = (int)($data['area_id'] ?? 0);
$sortOrder = (int)($data['sort_order'] ?? 0);

if($tableId < 1 || $capacity < 1 || $areaId < 1 || $sortOrder < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid table, capacity, area, and sort order are required']);
    exit();
}

try {
    $areaStmt = $pdo->prepare("SELECT area_id, name, display_order FROM table_areas WHERE area_id = ? AND is_active = 1");
    $areaStmt->execute([$areaId]);
    $area = $areaStmt->fetch(PDO::FETCH_ASSOC);

    if(!$area) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Area not found']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE restaurant_tables SET capacity = ?, area_id = ?, sort_order = ? WHERE table_id = ?");
    $stmt->execute([$capacity, $areaId, $sortOrder, $tableId]);

    if($stmt->rowCount() === 0) {
        $checkStmt = $pdo->prepare("SELECT rt.table_number, rt.capacity, rt.area_id, rt.sort_order, ta.name AS area_name, ta.display_order AS area_display_order FROM restaurant_tables rt LEFT JOIN table_areas ta ON ta.area_id = rt.area_id WHERE rt.table_id = ?");
        $checkStmt->execute([$tableId]);
        $table = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if(!$table) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Table not found']);
            exit();
        }

        echo json_encode([
            'success' => true,
            'table' => [
                'table_id' => $tableId,
                'table_number' => $table['table_number'],
                'capacity' => (int)$table['capacity'],
                'area_id' => (int)$table['area_id'],
                'area_name' => $table['area_name'],
                'area_display_order' => (int)$table['area_display_order'],
                'sort_order' => (int)$table['sort_order'],
            ]
        ]);
        exit();
    }

    $tableStmt = $pdo->prepare("SELECT rt.table_number, rt.capacity, rt.area_id, rt.sort_order, ta.name AS area_name, ta.display_order AS area_display_order FROM restaurant_tables rt LEFT JOIN table_areas ta ON ta.area_id = rt.area_id WHERE rt.table_id = ?");
    $tableStmt->execute([$tableId]);
    $table = $tableStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'table' => [
            'table_id' => $tableId,
            'table_number' => $table['table_number'],
            'capacity' => (int)$table['capacity'],
            'area_id' => (int)$table['area_id'],
            'area_name' => $table['area_name'],
            'area_display_order' => (int)$table['area_display_order'],
            'sort_order' => (int)$table['sort_order'],
        ]
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>