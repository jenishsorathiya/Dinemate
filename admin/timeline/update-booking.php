<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);

// Check if user is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if(!isset($data['booking_id']) || !isset($data['start_time']) || !isset($data['end_time'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$booking_id = intval($data['booking_id']);
$new_start_time = $data['start_time'];
$new_end_time = $data['end_time'];

$new_table_ids = [];
if (isset($data['table_ids']) && is_array($data['table_ids'])) {
    foreach ($data['table_ids'] as $tableId) {
        $tableId = (int) $tableId;
        if ($tableId > 0 && !in_array($tableId, $new_table_ids, true)) {
            $new_table_ids[] = $tableId;
        }
    }
}

if (empty($new_table_ids) && isset($data['table_id'])) {
    $fallbackTableId = (int) $data['table_id'];
    if ($fallbackTableId > 0) {
        $new_table_ids[] = $fallbackTableId;
    }
}

try {
    // Get current booking
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit();
    }

    if(empty($new_table_ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A valid table is required']);
        exit();
    }

    $tablePlaceholders = implode(',', array_fill(0, count($new_table_ids), '?'));
    $tableStmt = $pdo->prepare("SELECT table_id, table_number, capacity FROM restaurant_tables WHERE table_id IN ($tablePlaceholders)");
    $tableStmt->execute($new_table_ids);
    $tableRows = $tableStmt->fetchAll(PDO::FETCH_ASSOC);

    if(count($tableRows) !== count($new_table_ids)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Target table not found']);
        exit();
    }

    $tableMap = [];
    $orderedTables = [];
    $totalCapacity = 0;
    foreach ($tableRows as $tableRow) {
        $tableMap[(int) $tableRow['table_id']] = $tableRow;
    }
    foreach ($new_table_ids as $tableId) {
        $orderedTables[] = $tableMap[$tableId];
        $totalCapacity += (int) $tableMap[$tableId]['capacity'];
    }

    // Check for conflicts at new location
    $conflictStmt = $pdo->prepare(" 
        SELECT COUNT(*) as conflict_count
        FROM booking_table_assignments bta
        INNER JOIN bookings b ON b.booking_id = bta.booking_id
        WHERE bta.table_id = ? 
        AND b.booking_date = ?
        AND b.booking_id != ?
        AND b.status IN ('pending', 'confirmed')
        AND (
            (b.start_time < ? AND b.end_time > ?)
            OR (b.start_time >= ? AND b.start_time < ?)
            OR (b.end_time > ? AND b.end_time <= ?)
        )
    ");

    foreach ($new_table_ids as $tableId) {
        $conflictStmt->execute([
            $tableId,
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
            echo json_encode(['success' => false, 'error' => 'Time slot conflict at one of the selected tables']);
            exit();
        }
    }

    // Update booking
    $updateStmt = $pdo->prepare("
        UPDATE bookings 
        SET start_time = ?, end_time = ?, status = 'confirmed'
        WHERE booking_id = ?
    ");

    $updateStmt->execute([$new_start_time, $new_end_time, $booking_id]);
    $new_table_ids = syncBookingTableAssignments($pdo, $booking_id, $new_table_ids);

    $tableNumbers = array_map(static function ($tableRow) {
        return (string) $tableRow['table_number'];
    }, $orderedTables);

    echo json_encode([
        'success' => true,
        'message' => 'Booking updated successfully',
        'booking_id' => $booking_id,
        'table_id' => $new_table_ids[0] ?? null,
        'table_number' => $tableNumbers[0] ?? null,
        'table_ids' => $new_table_ids,
        'table_numbers' => $tableNumbers,
        'total_capacity' => $totalCapacity,
        'over_capacity' => ((int) $booking['number_of_guests'] > $totalCapacity),
        'status' => 'confirmed',
        'start_time' => $new_start_time,
        'end_time' => $new_end_time
    ]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>
