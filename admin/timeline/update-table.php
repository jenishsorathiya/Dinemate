<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$tableId = (int)($data['table_id'] ?? 0);
$capacity = (int)($data['capacity'] ?? 0);

if($tableId < 1 || $capacity < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid table_id and capacity are required']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE restaurant_tables SET capacity = ? WHERE table_id = ?");
    $stmt->execute([$capacity, $tableId]);

    if($stmt->rowCount() === 0) {
        $checkStmt = $pdo->prepare("SELECT table_number, capacity FROM restaurant_tables WHERE table_id = ?");
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
            ]
        ]);
        exit();
    }

    $tableStmt = $pdo->prepare("SELECT table_number, capacity FROM restaurant_tables WHERE table_id = ?");
    $tableStmt->execute([$tableId]);
    $table = $tableStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'table' => [
            'table_id' => $tableId,
            'table_number' => $table['table_number'],
            'capacity' => (int)$table['capacity'],
        ]
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>