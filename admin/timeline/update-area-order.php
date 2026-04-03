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
$areaIds = $data['area_ids'] ?? null;

if(!is_array($areaIds) || empty($areaIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid area order is required']);
    exit();
}

$normalizedAreaIds = [];
foreach ($areaIds as $areaId) {
    $areaId = (int)$areaId;
    if($areaId > 0 && !in_array($areaId, $normalizedAreaIds, true)) {
        $normalizedAreaIds[] = $areaId;
    }
}

if(empty($normalizedAreaIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid area order is required']);
    exit();
}

try {
    $placeholders = implode(',', array_fill(0, count($normalizedAreaIds), '?'));
    $checkStmt = $pdo->prepare("SELECT area_id FROM table_areas WHERE is_active = 1 AND area_id IN ($placeholders)");
    $checkStmt->execute($normalizedAreaIds);
    $existingAreaIds = array_map('intval', $checkStmt->fetchAll(PDO::FETCH_COLUMN));
    sort($existingAreaIds);

    $activeAreaIds = array_map('intval', $pdo->query("SELECT area_id FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, area_id ASC")->fetchAll(PDO::FETCH_COLUMN));
    $sortedNormalizedAreaIds = $normalizedAreaIds;
    sort($sortedNormalizedAreaIds);

    if($existingAreaIds !== $sortedNormalizedAreaIds || count($activeAreaIds) !== count($normalizedAreaIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Area order payload must include each active area exactly once']);
        exit();
    }

    $pdo->beginTransaction();

    $updateStmt = $pdo->prepare("UPDATE table_areas SET display_order = ? WHERE area_id = ?");
    foreach ($normalizedAreaIds as $index => $areaId) {
        $updateStmt->execute([($index + 1) * 10, $areaId]);
    }

    $pdo->commit();

    $areasStmt = $pdo->query("SELECT area_id, name, display_order, table_number_start, table_number_end, is_active FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
    echo json_encode([
        'success' => true,
        'areas' => $areasStmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch(PDOException $e) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>