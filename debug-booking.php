<?php
/**
 * DEBUG TOOL: Check booking system status and test availability
 * Access via: http://localhost/dinemate/debug-booking.php
 */

require_once "config/db.php";

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Booking System Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 20px; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .status-box { padding: 10px; margin: 10px 0; border-left: 4px solid; border-radius: 4px; }
        .status-success { border-left-color: #28a745; background: #f0f9f6; }
        .status-error { border-left-color: #dc3545; background: #f8f5f5; }
        .status-info { border-left-color: #17a2b8; background: #f0f7fa; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <h1 class="mb-4">🔧 DineMate Booking System - Debug Console</h1>

        <!-- Database Connection Status -->
        <div class="section">
            <h2>✓ Database Connection</h2>
            <div class="status-box status-success">
                <strong>Connected!</strong> PDO connection successful
            </div>
        </div>

        <!-- Tables Check -->
        <div class="section">
            <h2>📋 Database Tables Check</h2>
            <?php
            try {
                // Check restaurant_tables
                $stmt = $pdo->query("SELECT * FROM restaurant_tables LIMIT 1");
                $result = $stmt->fetch();
                if ($result) {
                    echo '<div class="status-box status-success">✓ restaurant_tables exists</div>';
                } else {
                    echo '<div class="status-box status-error">✗ restaurant_tables is empty or missing</div>';
                }
                
                // Check bookings table
                $stmt = $pdo->query("SHOW COLUMNS FROM bookings");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo '<div class="status-box status-info">';
                echo '<strong>Bookings Table Columns:</strong><br>';
                
                $requiredColumns = ['booking_id', 'user_id', 'table_id', 'booking_date', 'start_time', 'end_time', 'status'];
                foreach ($columns as $col) {
                    $colName = $col['Field'];
                    $isRequired = in_array($colName, $requiredColumns);
                    $icon = $isRequired ? '✓' : '•';
                    echo "$icon <strong>$colName</strong> ({$col['Type']}) - {$col['Null']}<br>";
                }
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="status-box status-error">Error: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>

        <!-- Sample Bookings -->
        <div class="section">
            <h2>📅 Sample Bookings Data</h2>
            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM bookings ORDER BY booking_date DESC, start_time DESC LIMIT 5");
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($bookings)) {
                    echo '<div class="status-box status-warning">⚠ No bookings found in database</div>';
                } else {
                    echo '<table class="table table-striped table-sm">';
                    echo '<thead><tr>';
                    echo '<th>ID</th><th>Table</th><th>Date</th><th>Time</th><th>Guests</th><th>Status</th>';
                    echo '</tr></thead><tbody>';
                    
                    foreach ($bookings as $booking) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($booking['booking_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($booking['table_id']) . '</td>';
                        echo '<td>' . htmlspecialchars($booking['booking_date']) . '</td>';
                        echo '<td>';
                        if ($booking['start_time'] && $booking['end_time']) {
                            echo htmlspecialchars($booking['start_time']) . ' - ' . htmlspecialchars($booking['end_time']);
                        } else {
                            echo '<span class="error">NULL</span>';
                        }
                        echo '</td>';
                        echo '<td>' . htmlspecialchars($booking['number_of_guests'] ?? 'N/A') . '</td>';
                        echo '<td><span class="badge bg-secondary">' . htmlspecialchars($booking['status']) . '</span></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            } catch (Exception $e) {
                echo '<div class="status-box status-error">Error: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>

        <!-- Test Availability Check -->
        <div class="section">
            <h2>🔍 Test Availability Check</h2>
            <?php
            // Get a date 5 days in future
            $testDate = date('Y-m-d', strtotime('+5 days'));
            $testStartTime = '14:00:00';
            $testEndTime = '15:00:00';
            ?>
            <p>Testing availability for: <strong><?php echo $testDate; ?> from <?php echo $testStartTime; ?> to <?php echo $testEndTime; ?></strong></p>
            
            <div id="test-result" class="status-box status-info" style="display:none;">
                <strong>API Response:</strong>
                <pre id="test-response"></pre>
            </div>
            
            <button class="btn btn-primary" onclick="testAvailability()">Run Availability Test</button>
        </div>

        <!-- Real Booking Test -->
        <div class="section">
            <h2>✅ Manual Availability Endpoint Test</h2>
            <form onsubmit="testManualAvailability(event)">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Date:</label>
                        <input type="date" id="test-date" class="form-control" value="<?php echo $testDate; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Time:</label>
                        <input type="time" id="test-start-time" class="form-control" value="14:00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Time:</label>
                        <input type="time" id="test-end-time" class="form-control" value="15:00">
                    </div>
                </div>
                <button type="submit" class="btn btn-secondary">Test with Custom Values</button>
            </form>
            <div id="manual-result" style="margin-top: 15px;"></div>
        </div>

        <!-- Browser Console -->
        <div class="section">
            <h2>📊 Browser Console Logs</h2>
            <p class="info">Proceed to <a href="bookings/book-table.php" target="_blank">Book Table</a> page and open Developer Tools (F12) Console tab to see real-time logs.</p>
            <p class="warning">Look for availability responses and any JavaScript errors.</p>
        </div>

    </div>

    <script>
        async function testAvailability() {
            const date = document.getElementById('test-date') ? document.getElementById('test-date').value : '<?php echo $testDate; ?>';
            const startTime = '14:00';
            const endTime = '15:00';
            
            console.log('Testing availability for:', { date, startTime, endTime });
            
            try {
                const response = await fetch(`bookings/check-availability.php?date=${date}&start_time=${startTime}&end_time=${endTime}`);
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('API Response:', data);
                
                document.getElementById('test-result').style.display = 'block';
                document.getElementById('test-response').textContent = JSON.stringify(data, null, 2);
                
                // Check response structure
                const checks = [];
                checks.push(`✓ Response received: ${data.success ? 'SUCCESS' : 'FAILED'}`);
                checks.push(`✓ Available tables: ${data.availableCount || 0}`);
                checks.push(`✓ Booked tables: ${data.bookedCount || 0}`);
                
                if (data.error) {
                    checks.push(`✗ Error: ${data.error}`);
                }
                
                console.log('Checks:', checks);
                
            } catch (error) {
                console.error('Fetch error:', error);
                document.getElementById('test-result').style.display = 'block';
                document.getElementById('test-response').textContent = `ERROR: ${error.message}`;
            }
        }
        
        async function testManualAvailability(event) {
            event.preventDefault();
            
            const date = document.getElementById('test-date').value;
            const startTime = document.getElementById('test-start-time').value;
            const endTime = document.getElementById('test-end-time').value;
            
            const resultDiv = document.getElementById('manual-result');
            resultDiv.innerHTML = '<p class="info">Loading...</p>';
            
            try {
                const response = await fetch(`bookings/check-availability.php?date=${date}&start_time=${startTime}&end_time=${endTime}`);
                const data = await response.json();
                
                if (data.success) {
                    let html = '<div class="status-box status-success">';
                    html += '<strong>✓ Test Successful!</strong><br>';
                    html += `Available: ${data.availableCount}, Booked: ${data.bookedCount}<br><br>`;
                    html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    html += '</div>';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.innerHTML = '<div class="status-box status-error"><strong>✗ Error:</strong> ' + data.error + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="status-box status-error"><strong>✗ Fetch Error:</strong> ' + error.message + '</div>';
            }
        }
        
        // Auto-run test on page load
        window.addEventListener('load', function() {
            console.log('%c🔧 DineMate Debug Console Active', 'font-size: 16px; color: green; font-weight: bold;');
        });
    </script>
</body>
</html>
