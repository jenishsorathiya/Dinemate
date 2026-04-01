<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";

header('Content-Type: application/json');

// Check if user is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if(!isset($data['booking_id']) || !isset($data['table_id']) || !isset($data['start_time']) || !isset($data['end_time'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$booking_id = intval($data['booking_id']);
$new_table_id = intval($data['table_id']);
$new_start_time = $data['start_time'];
$new_end_time = $data['end_time'];

try {
    // Get current booking
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id IN (SELECT user_id FROM users WHERE user_id = (SELECT user_id FROM bookings WHERE booking_id = ?))");
    $stmt->execute([$booking_id, $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit();
    }

    // Check for conflicts at new location
    $conflictStmt = $pdo->prepare("
        SELECT COUNT(*) as conflict_count
        FROM bookings 
        WHERE table_id = ? 
        AND booking_date = ?
        AND booking_id != ?
        AND status IN ('pending', 'confirmed')
        AND (
            (start_time < ? AND end_time > ?)
            OR (start_time >= ? AND start_time < ?)
            OR (end_time > ? AND end_time <= ?)
        )
    ");
    
    $conflictStmt->execute([
        $new_table_id,
        $booking['booking_date'],
        $booking_id,
        $new_end_time,
        $new_start_time,
        $new_start_time,
        $new_end_time,
        $new_start_time,
        $new_end_time
    ]);
    
    $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);

    if($conflict['conflict_count'] > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Time slot conflict at new table']);
        exit();
    }

    // Update booking
    $updateStmt = $pdo->prepare("
        UPDATE bookings 
        SET table_id = ?, start_time = ?, end_time = ?
        WHERE booking_id = ?
    ");
    
    $updateStmt->execute([$new_table_id, $new_start_time, $new_end_time, $booking_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Booking updated successfully',
        'booking_id' => $booking_id,
        'table_id' => $new_table_id,
        'start_time' => $new_start_time,
        'end_time' => $new_end_time
    ]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>
