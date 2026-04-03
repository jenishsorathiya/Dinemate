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

ensureTableAreasSchema($pdo);

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$tableNumberStart = isset($data['table_number_start']) && $data['table_number_start'] !== '' ? (int)$data['table_number_start'] : null;
$tableNumberEnd = isset($data['table_number_end']) && $data['table_number_end'] !== '' ? (int)$data['table_number_end'] : null;

if($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Area name is required']);
    exit();
}

if(strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Area name must be 100 characters or fewer']);
    exit();
}

if($tableNumberStart !== null && $tableNumberStart < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Area start number must be at least 1']);
    exit();
}

if($tableNumberEnd !== null && $tableNumberEnd < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Area end number must be at least 1']);
    exit();
}

if($tableNumberStart !== null && $tableNumberEnd !== null && $tableNumberEnd < $tableNumberStart) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Area end number must be greater than or equal to the start number']);
    exit();
}

try {
    $existsStmt = $pdo->prepare("SELECT area_id FROM table_areas WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $existsStmt->execute([$name]);
    $existingId = $existsStmt->fetchColumn();

    if($existingId) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'An area with that name already exists']);
        exit();
    }

    $displayOrder = (int)$pdo->query("SELECT COALESCE(MAX(display_order), 0) + 10 FROM table_areas")->fetchColumn();
    if($displayOrder < 1) {
        $displayOrder = 10;
    }

    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("INSERT INTO table_areas (name, display_order, table_number_start, table_number_end, is_active) VALUES (?, ?, ?, ?, 1)");
    $insertStmt->execute([$name, $displayOrder, $tableNumberStart, $tableNumberEnd]);
    $areaId = (int)$pdo->lastInsertId();

    $syncResult = syncAreaNumberedTables($pdo, $areaId, $tableNumberStart, $tableNumberEnd);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'area' => [
            'area_id' => $areaId,
            'name' => $name,
            'display_order' => $displayOrder,
            'table_number_start' => $tableNumberStart,
            'table_number_end' => $tableNumberEnd,
            'is_active' => 1,
        ],
        'area_tables' => $syncResult['area_tables'],
        'deleted_table_ids' => $syncResult['deleted_table_ids'],
        'affected_booking_ids' => $syncResult['affected_booking_ids'],
    ]);
} catch(PDOException $e) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>