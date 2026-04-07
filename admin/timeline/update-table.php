<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureTableAreasSchema($pdo);

requireAdmin(['json' => true]);

$normalizeTableShape = static function (string $value): string {
    $shape = strtolower(trim($value));

    $aliases = [
        'auto' => 'auto',
        'circle' => 'circle',
        'square' => 'square',
        'rect' => 'rect-horizontal',
        'rectangle' => 'rect-horizontal',
        'rect-h' => 'rect-horizontal',
        'horizontal' => 'rect-horizontal',
        'rect-horizontal' => 'rect-horizontal',
        'rect-v' => 'rect-vertical',
        'vertical' => 'rect-vertical',
        'rect-vertical' => 'rect-vertical',
    ];

    return $aliases[$shape] ?? 'auto';
};

$data = json_decode(file_get_contents('php://input'), true);

$tableId = (int)($data['table_id'] ?? 0);
$capacity = (int)($data['capacity'] ?? 0);
$areaId = (int)($data['area_id'] ?? 0);
$sortOrder = (int)($data['sort_order'] ?? 0);
$reservable = array_key_exists('reservable', $data) ? (int)!empty($data['reservable']) : 1;
$layoutX = isset($data['layout_x']) && $data['layout_x'] !== '' ? (int)$data['layout_x'] : null;
$layoutY = isset($data['layout_y']) && $data['layout_y'] !== '' ? (int)$data['layout_y'] : null;
$tableShape = $normalizeTableShape((string)($data['table_shape'] ?? 'auto'));

if($tableId < 1 || $capacity < 1 || $areaId < 1 || $sortOrder < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid table, capacity, area, and sort order are required']);
    exit();
}

try {
    $currentTableStmt = $pdo->prepare("SELECT table_id, table_number FROM restaurant_tables WHERE table_id = ?");
    $currentTableStmt->execute([$tableId]);
    $currentTable = $currentTableStmt->fetch(PDO::FETCH_ASSOC);

    if(!$currentTable) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Table not found']);
        exit();
    }

    $tableNumber = isset($data['table_number']) ? trim((string)$data['table_number']) : (string)$currentTable['table_number'];
    if($tableNumber === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Table number is required']);
        exit();
    }

    $areaStmt = $pdo->prepare("SELECT area_id, name, display_order FROM table_areas WHERE area_id = ? AND is_active = 1");
    $areaStmt->execute([$areaId]);
    $area = $areaStmt->fetch(PDO::FETCH_ASSOC);

    if(!$area) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Area not found']);
        exit();
    }

    $duplicateStmt = $pdo->prepare("SELECT table_id FROM restaurant_tables WHERE table_number = ? AND area_id = ? AND table_id != ? LIMIT 1");
    $duplicateStmt->execute([$tableNumber, $areaId, $tableId]);
    if($duplicateStmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Table number already exists in this area']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE restaurant_tables SET table_number = ?, capacity = ?, area_id = ?, sort_order = ?, reservable = ?, layout_x = ?, layout_y = ?, table_shape = ? WHERE table_id = ?");
    $stmt->execute([$tableNumber, $capacity, $areaId, $sortOrder, $reservable, $layoutX, $layoutY, $tableShape, $tableId]);

    if($stmt->rowCount() === 0) {
        $checkStmt = $pdo->prepare("SELECT rt.table_number, rt.capacity, rt.area_id, rt.sort_order, rt.reservable, rt.layout_x, rt.layout_y, rt.table_shape, ta.name AS area_name, ta.display_order AS area_display_order FROM restaurant_tables rt LEFT JOIN table_areas ta ON ta.area_id = rt.area_id WHERE rt.table_id = ?");
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
                'reservable' => (int)$table['reservable'],
                'layout_x' => $table['layout_x'] !== null ? (int)$table['layout_x'] : null,
                'layout_y' => $table['layout_y'] !== null ? (int)$table['layout_y'] : null,
                'table_shape' => $normalizeTableShape((string)($table['table_shape'] ?: 'auto')),
            ]
        ]);
        exit();
    }

    $tableStmt = $pdo->prepare("SELECT rt.table_number, rt.capacity, rt.area_id, rt.sort_order, rt.reservable, rt.layout_x, rt.layout_y, rt.table_shape, ta.name AS area_name, ta.display_order AS area_display_order FROM restaurant_tables rt LEFT JOIN table_areas ta ON ta.area_id = rt.area_id WHERE rt.table_id = ?");
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
            'reservable' => (int)$table['reservable'],
            'layout_x' => $table['layout_x'] !== null ? (int)$table['layout_x'] : null,
            'layout_y' => $table['layout_y'] !== null ? (int)$table['layout_y'] : null,
            'table_shape' => $normalizeTableShape((string)($table['table_shape'] ?: 'auto')),
        ]
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>