<?php
/**
 * DINEMATE BOOKING SYSTEM - AUTO FIXER
 * This script automatically detects and fixes common issues
 * Run once: http://localhost/dinemate/auto-fix.php
 */

require_once "config/db.php";

$fixed = [];
$errors = [];

try {
    
    // 1. Ensure start_time and end_time columns exist in bookings table
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'start_time'");
    if ($stmt->rowCount() === 0) {
        try {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN start_time TIME DEFAULT NULL AFTER booking_date");
            $fixed[] = "✓ Added start_time column to bookings table";
        } catch (Exception $e) {
            $errors[] = "✗ Failed to add start_time: " . $e->getMessage();
        }
    } else {
        $fixed[] = "✓ start_time column already exists";
    }
    
    // 2. Ensure end_time column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'end_time'");
    if ($stmt->rowCount() === 0) {
        try {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN end_time TIME DEFAULT NULL AFTER start_time");
            $fixed[] = "✓ Added end_time column to bookings table";
        } catch (Exception $e) {
            $errors[] = "✗ Failed to add end_time: " . $e->getMessage();
        }
    } else {
        $fixed[] = "✓ end_time column already exists";
    }
    
    // 2b. Ensure special_request column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'special_request'");
    if ($stmt->rowCount() === 0) {
        try {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN special_request TEXT DEFAULT NULL");
            $fixed[] = "✓ Added special_request column to bookings table";
        } catch (Exception $e) {
            $errors[] = "✗ Failed to add special_request: " . $e->getMessage();
        }
    } else {
        $fixed[] = "✓ special_request column already exists";
    }
    
    // 3. Populate NULL time values with sensible defaults (if any exist)
    $stmt = $pdo->query("SELECT COUNT(*) as null_count FROM bookings WHERE start_time IS NULL OR end_time IS NULL");
    $result = $stmt->fetch();
    
    if ($result['null_count'] > 0) {
        try {
            // Set default booking times: 12:00-13:00 for NULL values
            $pdo->exec("UPDATE bookings SET start_time = '12:00:00' WHERE start_time IS NULL");
            $pdo->exec("UPDATE bookings SET end_time = '13:00:00' WHERE end_time IS NULL");
            $fixed[] = "✓ Populated {$result['null_count']} bookings with default times (12:00-13:00)";
        } catch (Exception $e) {
            $errors[] = "✗ Failed to populate times: " . $e->getMessage();
        }
    } else {
        $fixed[] = "✓ All bookings have time values";
    }
    
    // 4. Check restaurant_tables exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as table_count FROM restaurant_tables");
    $result = $stmt->fetch();
    
    if ($result['table_count'] === 0) {
        $errors[] = "⚠ WARNING: No restaurant tables defined. Add tables in the database first.";
    } else {
        $fixed[] = "✓ Restaurant has {$result['table_count']} tables defined";
    }
    
    // 5. Verify booking data integrity
    $stmt = $pdo->query("SELECT COUNT(*) as issue_count FROM bookings WHERE end_time <= start_time");
    $result = $stmt->fetch();
    
    if ($result['issue_count'] > 0) {
        // Try to fix: set end_time to start_time + 1 hour
        try {
            $pdo->exec("UPDATE bookings SET end_time = DATE_ADD(start_time, INTERVAL 1 HOUR) WHERE end_time <= start_time");
            $fixed[] = "✓ Fixed {$result['issue_count']} bookings with invalid time ranges";
        } catch (Exception $e) {
            $errors[] = "✗ Failed to fix time ranges: " . $e->getMessage();
        }
    } else {
        $fixed[] = "✓ All booking times are valid (end_time > start_time)";
    }
    
    // 6. Check for orphaned bookings (booking with non-existent table_id)
    $stmt = $pdo->query("
        SELECT COUNT(*) as orphan_count 
        FROM bookings b 
        LEFT JOIN restaurant_tables t ON b.table_id = t.table_id 
        WHERE t.table_id IS NULL
    ");
    $result = $stmt->fetch();
    
    if ($result['orphan_count'] > 0) {
        $errors[] = "⚠ WARNING: {$result['orphan_count']} bookings reference non-existent tables. Consider deleting them.";
    } else {
        $fixed[] = "✓ All bookings reference valid tables";
    }
    
    // 7. Verify session-check.php exists
    if (!file_exists('includes/session-check.php')) {
        $errors[] = "✗ Missing includes/session-check.php";
    } else {
        $fixed[] = "✓ includes/session-check.php exists";
    }
    
    // 8. Verify check-availability.php exists
    if (!file_exists('bookings/check-availability.php')) {
        $errors[] = "✗ Missing bookings/check-availability.php";
    } else {
        $fixed[] = "✓ bookings/check-availability.php exists";
    }
    
    // 9. Verify process-booking.php exists
    if (!file_exists('bookings/process-booking.php')) {
        $errors[] = "✗ Missing bookings/process-booking.php";
    } else {
        $fixed[] = "✓ bookings/process-booking.php exists";
    }
    
} catch (Exception $e) {
    $errors[] = "✗ Critical Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>DineMate Auto-Fix</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; }
        .section { margin: 20px 0; }
        .fixed-item { color: #28a745; padding: 8px; margin: 5px 0; border-left: 4px solid #28a745; background: #f0f8f4; }
        .error-item { color: #dc3545; padding: 8px; margin: 5px 0; border-left: 4px solid #dc3545; background: #fff5f5; }
        .summary { font-size: 18px; font-weight: bold; margin: 20px 0; padding: 15px; background: #e7f3ff; border-radius: 5px; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 DineMate Booking System Auto-Fix</h1>
        <p>Checking and fixing database configuration...</p>
        
        <div class="section">
            <h3>✓ Fixed Items (<?php echo count($fixed); ?>)</h3>
            <?php foreach ($fixed as $item): ?>
                <div class="fixed-item"><?php echo htmlspecialchars($item); ?></div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($errors) > 0): ?>
            <div class="section">
                <h3>⚠ Issues (<?php echo count($errors); ?>)</h3>
                <?php foreach ($errors as $item): ?>
                    <div class="error-item"><?php echo htmlspecialchars($item); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="summary">
            Status: <span class="<?php echo count($errors) === 0 ? 'success' : 'warning'; ?>">
                <?php echo count($errors) === 0 ? '✓ All systems go!' : '⚠ Please review issues above'; ?>
            </span>
        </div>
        
        <hr>
        <h3>Next Steps:</h3>
        <ol>
            <li>Visit <a href="bookings/book-table.php">Book a Table</a> - Try booking a table</li>
            <li>Visit <a href="bookings/my-bookings.php">My Bookings</a> - View your bookings</li>
            <li>If issues persist, visit <a href="diagnose.php">Diagnostics</a> page</li>
        </ol>
    </div>
</body>
</html>
