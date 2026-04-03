<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/session-check.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

requireAdmin(['json' => true]);

try {
    ensureBookingRequestColumns($pdo);

    $pendingStmt = $pdo->query(
        "SELECT b.booking_id,
                b.booking_date,
                b.start_time,
                b.number_of_guests,
                COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name,
                COALESCE(ta.name, '') AS area_name,
                GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
         FROM bookings b
         LEFT JOIN users u ON b.user_id = u.user_id
         LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
         LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
         LEFT JOIN table_areas ta ON rt.area_id = ta.area_id
         WHERE b.status = 'pending'
         GROUP BY b.booking_id
         ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC
         LIMIT 8"
    );

    $pendingBookings = $pendingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'count' => count($pendingBookings),
        'bookings' => $pendingBookings,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Could not load pending bookings',
    ]);
}
?>