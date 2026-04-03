<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureTableAreasSchema($pdo);

requireAdmin(['json' => true]);

$data = json_decode(file_get_contents('php://input'), true);

$isAuto = isset($data['auto']) && $data['auto'] === true;
$requestedAreaId = (int)($data['area_id'] ?? 0);
$requestedSortOrder = (int)($data['sort_order'] ?? 0);

$defaultAreaStmt = $pdo->query("SELECT area_id FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, name ASC LIMIT 1");
$defaultAreaId = (int)$defaultAreaStmt->fetchColumn();
$areaId = $requestedAreaId > 0 ? $requestedAreaId : $defaultAreaId;

if($areaId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid area is required']);
    exit();
}

$areaStmt = $pdo->prepare("SELECT area_id, name, display_order, table_number_start, table_number_end FROM table_areas WHERE area_id = ? AND is_active = 1");
$areaStmt->execute([$areaId]);
$area = $areaStmt->fetch(PDO::FETCH_ASSOC);

if(!$area) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Selected area not found']);
    exit();
}

if($isAuto) {
    // Auto mode: next table number within the selected area, default capacity 8.
    $maxStmt = $pdo->prepare("SELECT MAX(CAST(table_number AS UNSIGNED)) FROM restaurant_tables WHERE area_id = ?");
    $maxStmt->execute([$areaId]);
    $maxTableNumber = intval($maxStmt->fetchColumn());
    $areaStartNumber = isset($area['table_number_start']) && $area['table_number_start'] !== null ? (int)$area['table_number_start'] : null;
    $areaEndNumber = isset($area['table_number_end']) && $area['table_number_end'] !== null ? (int)$area['table_number_end'] : null;
    $nextNumber = $maxTableNumber > 0 ? $maxTableNumber + 1 : ($areaStartNumber ?: 1);

    if($areaEndNumber !== null && $nextNumber > $areaEndNumber) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This area has reached its configured end table number']);
        exit();
    }

    $tableNumber = strval($nextNumber);
    $capacity = 8;
} else {
    if(empty($data['table_number']) || empty($data['capacity'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'table_number and capacity required']);
        exit();
    }

    $tableNumber = trim($data['table_number']);
    $capacity = intval($data['capacity']);

    if($capacity <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'capacity must be a positive integer']);
        exit();
    }
}

try {
    $numericTableNumber = filter_var($tableNumber, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $areaStartNumber = isset($area['table_number_start']) && $area['table_number_start'] !== null ? (int)$area['table_number_start'] : null;
    $areaEndNumber = isset($area['table_number_end']) && $area['table_number_end'] !== null ? (int)$area['table_number_end'] : null;

    if($numericTableNumber !== false) {
        if($areaStartNumber !== null && $numericTableNumber < $areaStartNumber) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Table number cannot be below this area\'s start number']);
            exit();
        }

        if($areaEndNumber !== null && $numericTableNumber > $areaEndNumber) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Table number cannot be above this area\'s end number']);
            exit();
        }
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE table_number = ? AND area_id = ?");
    $stmt->execute([$tableNumber, $areaId]);
    $exists = $stmt->fetchColumn();

    if($exists) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Table number already exists in this area']);
        exit();
    }

    if($requestedSortOrder < 1) {
        $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM restaurant_tables WHERE area_id = ?");
        $sortStmt->execute([$areaId]);
        $requestedSortOrder = (int)$sortStmt->fetchColumn();
        if($requestedSortOrder < 1) {
            $requestedSortOrder = 10;
        }
    }

    $insert = $pdo->prepare("INSERT INTO restaurant_tables (area_id, table_number, capacity, sort_order, status) VALUES (?, ?, ?, ?, 'available')");
    $insert->execute([$areaId, $tableNumber, $capacity, $requestedSortOrder]);

    echo json_encode([
        'success' => true,
        'table_id' => $pdo->lastInsertId(),
        'table_number' => $tableNumber,
        'capacity' => $capacity,
        'area_id' => (int)$area['area_id'],
        'area_name' => $area['name'],
        'area_display_order' => (int)$area['display_order'],
        'sort_order' => $requestedSortOrder,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>