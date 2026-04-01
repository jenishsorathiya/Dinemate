<?php
require_once "config/db.php";

echo "<h2>Database Diagnostic Report</h2>";

try {
    // Check bookings table structure
    echo "<h3>1. Bookings Table Structure:</h3>";
    $result = $pdo->query("SHOW COLUMNS FROM bookings");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if start_time and end_time columns exist
    echo "<h3>2. Column Verification:</h3>";
    $hasStartTime = false;
    $hasEndTime = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'start_time') $hasStartTime = true;
        if ($col['Field'] === 'end_time') $hasEndTime = true;
    }
    
    echo "start_time column exists: " . ($hasStartTime ? "<span style='color:green'>✓ YES</span>" : "<span style='color:red'>✗ NO</span>") . "<br>";
    echo "end_time column exists: " . ($hasEndTime ? "<span style='color:green'>✓ YES</span>" : "<span style='color:red'>✗ NO</span>") . "<br>";
    
    if (!$hasStartTime || !$hasEndTime) {
        echo "<h3>3. Adding Missing Columns...</h3>";
        if (!$hasStartTime) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN start_time TIME NOT NULL DEFAULT '12:00:00' AFTER booking_date");
            echo "<span style='color:green'>✓ Added start_time column</span><br>";
        }
        if (!$hasEndTime) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN end_time TIME NOT NULL DEFAULT '13:00:00' AFTER start_time");
            echo "<span style='color:green'>✓ Added end_time column</span><br>";
        }
    }
    
    // Check sample bookings
    echo "<h3>4. Sample Bookings Data:</h3>";
    $bookings = $pdo->query("SELECT booking_id, user_id, table_id, booking_date, start_time, end_time, number_of_guests, status FROM bookings LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($bookings) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User</th><th>Table</th><th>Date</th><th>Start</th><th>End</th><th>Guests</th><th>Status</th></tr>";
        foreach ($bookings as $booking) {
            echo "<tr>";
            echo "<td>" . $booking['booking_id'] . "</td>";
            echo "<td>" . $booking['user_id'] . "</td>";
            echo "<td>" . $booking['table_id'] . "</td>";
            echo "<td>" . $booking['booking_date'] . "</td>";
            echo "<td>" . ($booking['start_time'] ?? 'NULL') . "</td>";
            echo "<td>" . ($booking['end_time'] ?? 'NULL') . "</td>";
            echo "<td>" . $booking['number_of_guests'] . "</td>";
            echo "<td>" . $booking['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No bookings found in database.</p>";
    }
    
    // Check restaurants tables
    echo "<h3>5. Restaurant Tables:</h3>";
    $tables = $pdo->query("SELECT * FROM restaurant_tables ORDER BY table_number ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "Total tables: " . count($tables) . "<br>";
    
    if (count($tables) === 0) {
        echo "<p style='color:orange'>⚠ No restaurant tables found. Please add tables first.</p>";
    }
    
} catch(Exception $e) {
    echo "<span style='color:red'>ERROR: " . $e->getMessage() . "</span>";
    echo "<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>

