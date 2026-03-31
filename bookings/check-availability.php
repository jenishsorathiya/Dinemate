<?php
/**
 * AJAX Endpoint: Check Table Availability
 * Returns available tables for a selected date and time slot
 * Usage: GET /bookings/check-availability.php?date=2026-04-15&start_time=14:00&end_time=15:00
 */

require_once "../config/db.php";
require_once "../includes/session-check.php";

header('Content-Type: application/json');

// Validate inputs
if (empty($_GET['date']) || empty($_GET['start_time']) || empty($_GET['end_time'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$date = $_GET['date'];
$startTime = date('H:i:s', strtotime($_GET['start_time']));
$endTime = date('H:i:s', strtotime($_GET['end_time']));

try {
    // Get all available tables
    $stmt = $pdo->query("SELECT * FROM restaurant_tables WHERE status='available' ORDER BY capacity ASC");
    $allTables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $availableTables = [];
    $bookedTables = [];
    
    foreach ($allTables as $table) {
        // Check for conflicts
        $conflictStmt = $pdo->prepare("
            SELECT COUNT(*) as conflict_count
            FROM bookings 
            WHERE table_id = ? 
            AND booking_date = ? 
            AND status IN ('pending', 'confirmed')
            AND (
                (start_time < ? AND end_time > ?)
                OR (start_time >= ? AND start_time < ?)
                OR (end_time > ? AND end_time <= ?)
            )
        ");
        
        $conflictStmt->execute([
            $table['table_id'],
            $date,
            $endTime,
            $startTime,
            $startTime,
            $endTime,
            $startTime,
            $endTime
        ]);
        
        $result = $conflictStmt->fetch(PDO::FETCH_ASSOC);
        $hasConflict = $result['conflict_count'] > 0;
        
        $tableInfo = [
            'table_id' => $table['table_id'],
            'table_number' => $table['table_number'],
            'capacity' => $table['capacity'],
            'available' => !$hasConflict
        ];
        
        if ($hasConflict) {
            // Get the existing bookings for this table
            $bookingStmt = $pdo->prepare("
                SELECT start_time, end_time 
                FROM bookings 
                WHERE table_id = ? 
                AND booking_date = ? 
                AND status IN ('pending', 'confirmed')
                AND (
                    (start_time < ? AND end_time > ?)
                    OR (start_time >= ? AND start_time < ?)
                    OR (end_time > ? AND end_time <= ?)
                )
            ");
            
            $bookingStmt->execute([
                $table['table_id'],
                $date,
                $endTime,
                $startTime,
                $startTime,
                $endTime,
                $startTime,
                $endTime
            ]);
            
            $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);
            $tableInfo['conflictingBookings'] = $bookings;
            $bookedTables[] = $tableInfo;
        } else {
            $availableTables[] = $tableInfo;
        }
    }
    
    echo json_encode([
        'success' => true,
        'availableTables' => $availableTables,
        'bookedTables' => $bookedTables,
        'availableCount' => count($availableTables),
        'bookedCount' => count($bookedTables)
    ]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
