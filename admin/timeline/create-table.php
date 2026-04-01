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

$isAuto = isset($data['auto']) && $data['auto'] === true;

if($isAuto) {
    // Auto mode: next table number in sequence, default capacity 8.
    $maxStmt = $pdo->query("SELECT MAX(CAST(table_number AS UNSIGNED)) FROM restaurant_tables");
    $maxTableNumber = intval($maxStmt->fetchColumn());
    $tableNumber = strval($maxTableNumber + 1);
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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE table_number = ?");
    $stmt->execute([$tableNumber]);
    $exists = $stmt->fetchColumn();

    if($exists) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Table number already exists']);
        exit();
    }

    $insert = $pdo->prepare("INSERT INTO restaurant_tables (table_number, capacity, status) VALUES (?, ?, 'available')");
    $insert->execute([$tableNumber, $capacity]);

    echo json_encode(['success' => true, 'table_id' => $pdo->lastInsertId(), 'table_number' => $tableNumber, 'capacity' => $capacity]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>