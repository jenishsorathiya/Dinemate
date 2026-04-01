<?php
// Simple test to verify XAMPP is working
echo "✓ XAMPP PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Date/Time: " . date('Y-m-d H:i:s') . "<br>";

// Check database connection
require_once "config/db.php";

try {
    $stmt = $pdo->query("SELECT 1");
    echo "✓ Database connection successful<br>";
    
    // Check bookings table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bookings");
    $result = $stmt->fetch();
    echo "✓ Bookings table accessible (total: " . $result['count'] . " bookings)<br>";
    
    echo "<hr>";
    echo "<h3>✓ All systems operational!</h3>";
    echo "<p><a href='index.php'>Go to DineMate Home</a></p>";
    
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}
?>
