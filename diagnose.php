<?php
/**
 * COMPREHENSIVE DIAGNOSTIC: Check booking system errors
 * Access: http://localhost/dinemate/diagnose.php
 */

require_once "config/db.php";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$diagnostics = [];

// 1. Check database connection
try {
    $pdo->query("SELECT 1");
    $diagnostics['db_connection'] = ['status' => 'OK', 'message' => 'Database connected'];
} catch (Exception $e) {
    $diagnostics['db_connection'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// 2. Check bookings table structure
try {
    $stmt = $pdo->query("DESCRIBE bookings");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    
    $required = ['booking_id', 'user_id', 'table_id', 'booking_date', 'start_time', 'end_time', 'status'];
    $missing = array_diff($required, $columnNames);
    
    if (empty($missing)) {
        $diagnostics['bookings_columns'] = [
            'status' => 'OK',
            'message' => 'All required columns present',
            'columns' => $columnNames
        ];
    } else {
        $diagnostics['bookings_columns'] = [
            'status' => 'ERROR',
            'message' => 'Missing columns: ' . implode(', ', $missing),
            'columns' => $columnNames,
            'missing' => $missing
        ];
    }
} catch (Exception $e) {
    $diagnostics['bookings_columns'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// 3. Check restaurant_tables
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM restaurant_tables");
    $result = $stmt->fetch();
    if ($result['count'] > 0) {
        $diagnostics['restaurant_tables'] = [
            'status' => 'OK',
            'message' => 'Tables exist: ' . $result['count']
        ];
    } else {
        $diagnostics['restaurant_tables'] = [
            'status' => 'WARNING',
            'message' => 'No restaurant tables defined'
        ];
    }
} catch (Exception $e) {
    $diagnostics['restaurant_tables'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// 4. Check sample bookings
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bookings");
    $result = $stmt->fetch();
    $diagnostics['bookings_count'] = [
        'status' => 'OK',
        'message' => 'Total bookings: ' . $result['count']
    ];
    
    // Get sample bookings with all fields
    $stmt = $pdo->query("SELECT * FROM bookings LIMIT 3");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($samples as $booking) {
        if (!$booking['start_time'] || !$booking['end_time']) {
            $diagnostics['bookings_data'] = [
                'status' => 'WARNING',
                'message' => 'Some bookings missing time data',
                'sample' => $booking
            ];
            break;
        }
    }
    
    if (!isset($diagnostics['bookings_data'])) {
        $diagnostics['bookings_data'] = [
            'status' => 'OK',
            'message' => 'Booking time data looks good',
            'sample_count' => count($samples)
        ];
    }
} catch (Exception $e) {
    $diagnostics['bookings_data'] = ['status' => 'ERROR', 'message' => $e->getMessage()];
}

// 5. Validate my-bookings.php query
try {
    $testUserId = 1; // Assuming user 1 exists
    $stmt = $pdo->prepare("
        SELECT b.*, t.table_number
        FROM bookings b
        LEFT JOIN restaurant_tables t
        ON b.table_id = t.table_id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$testUserId]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $diagnostics['mybookings_query'] = [
        'status' => 'OK',
        'message' => 'My-bookings query works',
        'result_count' => count($result)
    ];
} catch (Exception $e) {
    $diagnostics['mybookings_query'] = [
        'status' => 'ERROR',
        'message' => 'My-bookings query failed: ' . $e->getMessage()
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>DineMate Diagnostics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; padding: 32px 20px; background: #f5f7fb; }
        .container { max-width: 980px; margin: 0 auto; background: var(--dm-surface); border: 1px solid #e7ecf3; border-radius: 20px; box-shadow: 0 18px 42px rgba(15,23,42,0.08); padding: 32px; }
        .diag-box { background: white; padding: 18px; margin: 12px 0; border-radius: 16px; border: 1px solid #e7ecf3; }
        .status-OK { border-color: #ccefdc; background: #e6f7ee; }
        .status-ERROR { border-color: #ffd1d7; background: #ffe7ea; }
        .status-WARNING { border-color: #f3df9a; background: #fff8d8; }
        .key { font-weight: bold; }
        code { background: #eef2f6; padding: 2px 6px; border-radius: 6px; }
        h1, h2, h3 { color: #162033; }
        pre { background: var(--dm-surface-muted); padding: 12px; border-radius: 12px; overflow-x: auto; border: 1px solid #e7ecf3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 DineMate Diagnostic Report</h1>
        <p>Current Date: <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <?php foreach ($diagnostics as $key => $diag): ?>
            <div class="diag-box status-<?php echo $diag['status']; ?>">
                <span class="key"><?php echo str_replace('_', ' ', ucfirst($key)); ?>:</span>
                <span><?php echo $diag['message']; ?></span>
                
                <?php if (isset($diag['columns'])): ?>
                    <p style="margin-top: 10px; font-size: 12px;">Columns: <?php echo implode(', ', $diag['columns']); ?></p>
                <?php endif; ?>
                
                <?php if (isset($diag['missing'])): ?>
                    <p style="margin-top: 10px; font-size: 12px; color: red;"><strong>Missing:</strong> <?php echo implode(', ', $diag['missing']); ?></p>
                <?php endif; ?>
                
                <?php if (isset($diag['sample'])): ?>
                    <pre><?php echo json_encode($diag['sample'], JSON_PRETTY_PRINT); ?></pre>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <hr style="margin: 30px 0;">
        <h3>Next Steps:</h3>
        <ul>
            <li>If database errors exist, check <code>config/db.php</code></li>
            <li>If columns are missing, run <code>auto-fix.php</code> to repair the schema</li>
            <li>If queries fail, check MySQL user permissions</li>
            <li>Visit <code>bookings/book-table.php</code> and <code>admin/timeline/new-dashboard.php</code> to test the pending-booking flow</li>
        </ul>
    </div>
</body>
</html>
<?php
?>
