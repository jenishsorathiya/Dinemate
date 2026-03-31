<?php
/**
 * Database Migration Runner
 * This script adds the time_slot columns to the bookings table
 * Run this once by accessing it in your browser: http://localhost/dinemate/migrate.php
 */

require_once "config/db.php";

try {
    echo "🔄 Starting migration...\n\n";
    
    // Check if columns already exist
    $result = $pdo->query("DESCRIBE bookings");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN, 0);
    
    echo "Current bookings table columns:\n";
    foreach ($columns as $col) {
        echo "  - " . $col . "\n";
    }
    echo "\n";
    
    $startTimeExists = in_array('start_time', $columns);
    $endTimeExists = in_array('end_time', $columns);
    
    if ($startTimeExists && $endTimeExists) {
        echo "✅ Both start_time and end_time columns already exist!\n";
        exit;
    }
    
    // Add columns if they don't exist
    if (!$startTimeExists) {
        echo "📝 Adding start_time column...\n";
        $pdo->exec("ALTER TABLE bookings ADD COLUMN start_time TIME NOT NULL DEFAULT '12:00:00'");
        echo "✅ start_time column added\n";
    }
    
    if (!$endTimeExists) {
        echo "📝 Adding end_time column...\n";
        $pdo->exec("ALTER TABLE bookings ADD COLUMN end_time TIME NOT NULL DEFAULT '13:00:00'");
        echo "✅ end_time column added\n";
    }
    
    // Create index for performance
    echo "📝 Creating index for performance...\n";
    $pdo->exec("CREATE INDEX idx_bookings_date_time ON bookings(booking_date, start_time, end_time, table_id, status)");
    echo "✅ Index created\n";
    
    echo "\n✅ Migration completed successfully!\n";
    echo "You can now use the time slot booking system.\n";
    
} catch(PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
