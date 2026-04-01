<?php
/**
 * DineMate Booking System - Quick Start & Troubleshooting
 * Visit: http://localhost/dinemate/setup.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Quick Start Guide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 20px; }
        .container { background: white; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 40px; max-width: 900px; }
        h1 { color: #667eea; margin-bottom: 30px; }
        h3 { color: #764ba2; margin-top: 30px; margin-bottom: 20px; }
        .step { background: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 10px; border-left: 4px solid #667eea; }
        .step-number { display: inline-block; background: #667eea; color: white; width: 35px; height: 35px; border-radius: 50%; text-align: center; line-height: 35px; font-weight: bold; margin-right: 15px; }
        .btn-action { padding: 10px 25px; margin: 10px 5px 10px 0; font-weight: 600; }
        .check-list { list-style: none; padding: 0; }
        .check-list li { padding: 8px 0; }
        .check-list li:before { content: "✓ "; color: #28a745; font-weight: bold; margin-right: 10px; }
        .code-block { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; margin: 10px 0; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 13px; }
        .alert-box { border-radius: 10px; padding: 15px; margin: 15px 0; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error-box { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info-box { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .button-group { display: flex; gap: 10px; flex-wrap: wrap; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🍽️ DineMate Booking System - Setup Guide</h1>
        <p class="text-muted">Complete setup and troubleshooting for the restaurant table booking system</p>
        
        <hr>
        
        <h3>📋 Quick Setup (Do This First)</h3>
        
        <div class="step">
            <span class="step-number">1</span>
            <strong>Run Auto-Fix</strong>
            <p class="mt-2">This script automatically detects and fixes database issues:</p>
            <div class="button-group">
                <a href="auto-fix.php" class="btn btn-primary btn-action" target="_blank">
                    🔧 Run Auto-Fix Now
                </a>
            </div>
            <p class="text-muted" style="margin-top: 10px;">This will:</p>
            <ul class="check-list">
                <li>Create missing database columns (start_time, end_time, special_request)</li>
                <li>Populate NULL time values with defaults</li>
                <li>Verify data integrity</li>
            </ul>
        </div>
        
        <hr>
        
        <h3>✅ Testing</h3>
        
        <div class="step">
            <span class="step-number">2</span>
            <strong>Test Booking System</strong>
            <div class="button-group">
                <a href="bookings/book-table.php" class="btn btn-success btn-action" target="_blank">
                    📅 Book a Table
                </a>
                <a href="bookings/my-bookings.php" class="btn btn-info btn-action" target="_blank">
                    📋 View My Bookings
                </a>
            </div>
        </div>
        
        <div class="step">
            <span class="step-number">3</span>
            <strong>Run Diagnostics</strong>
            <p class="mt-2">If you encounter issues, check detailed system status:</p>
            <div class="button-group">
                <a href="diagnose.php" class="btn btn-warning btn-action" target="_blank">
                    🔍 Run Diagnostics
                </a>
                <a href="debug-booking.php" class="btn btn-warning btn-action" target="_blank">
                    🐛 Debug Console
                </a>
            </div>
        </div>
        
        <hr>
        
        <h3>🔧 Troubleshooting</h3>
        
        <div class="alert-box info-box">
            <h5>❓ Book Table page shows errors or no tables</h5>
            <ul>
                <li>Run <strong>Auto-Fix</strong> first</li>
                <li>Check that restaurant tables are added to the database</li>
                <li>Open browser console (F12) and look for JavaScript errors</li>
                <li>Run <strong>Diagnostics</strong> to verify all columns exist</li>
            </ul>
        </div>
        
        <div class="alert-box info-box">
            <h5>❓ My Bookings page shows errors or displays wrong times</h5>
            <ul>
                <li>This was caused by referencing <code>$b['booking_time']</code> instead of <code>$b['start_time']</code> and <code>$b['end_time']</code></li>
                <li><strong>✓ FIXED</strong> - Now displays time range properly (e.g., "2:00 PM - 3:00 PM")</li>
                <li>Clear browser cache (Ctrl+Shift+Delete) and refresh the page</li>
            </ul>
        </div>
        
        <div class="alert-box info-box">
            <h5>❓ Availability check not working when selecting date/time</h5>
            <ul>
                <li>Open browser Developer Tools (F12)</li>
                <li>Go to Network tab, select a date/time and check API response</li>
                <li>Run <strong>Debug Console</strong> to test the availability endpoint</li>
                <li>Check that <code>start_time</code> and <code>end_time</code> columns exist in bookings</li>
            </ul>
        </div>
        
        <div class="alert-box info-box">
            <h5>❓ Cannot see which tables are booked</h5>
            <ul>
                <li>Verify that bookings have <code>start_time</code> and <code>end_time</code> values (Run Auto-Fix)</li>
                <li>Check browser console for JavaScript errors</li>
                <li>Try clearing cache and refreshing</li>
                <li>Run <strong>Diagnostics</strong> to see sample booking data</li>
            </ul>
        </div>
        
        <hr>
        
        <h3>📚 Key Features</h3>
        
        <div class="step">
            <strong>Booking Form Validations:</strong>
            <ul class="check-list">
                <li>Restaurant hours: 10:00 AM - 10:00 PM</li>
                <li>Booking duration: 60-180 minutes</li>
                <li>Table capacity check</li>
                <li>Overlap detection (prevents double-booking)</li>
            </ul>
        </div>
        
        <div class="step">
            <strong>Time Slot System:</strong>
            <ul class="check-list">
                <li>Start time and end time for each booking</li>
                <li>Automatic conflict detection</li>
                <li>Real-time availability checking via AJAX</li>
                <li>Visual table status (available in light gray, booked in red)</li>
            </ul>
        </div>
        
        <hr>
        
        <h3>🗄️ Database Structure</h3>
        
        <p><strong>bookings table columns:</strong></p>
        <div class="code-block">
booking_id (INT)
user_id (INT)
table_id (INT)
booking_date (DATE)
start_time (TIME) ← Added for time slot system
end_time (TIME) ← Added for time slot system
number_of_guests (INT)
special_request (TEXT)
status (VARCHAR)
        </div>
        
        <hr>
        
        <h3>💡 What Was Fixed</h3>
        
        <div class="alert-box success-box">
            <h5>✓ Issues Resolved:</h5>
            <ul class="check-list">
                <li>Fixed my-bookings.php using wrong field name (booking_time → start_time + end_time)</li>
                <li>Added auto-migration for missing database columns</li>
                <li>Enhanced error handling in availability checking</li>
                <li>Added comprehensive diagnostics and auto-fix tools</li>
                <li>Improved form validation and error messages</li>
            </ul>
        </div>
        
        <hr>
        
        <div class="text-center mt-4">
            <p class="text-muted">For more help, run the Auto-Fix tool or visit the Diagnostics page</p>
            <div class="button-group justify-content-center">
                <a href="auto-fix.php" class="btn btn-primary btn-lg" target="_blank">Auto-Fix</a>
                <a href="diagnose.php" class="btn btn-warning btn-lg" target="_blank">Diagnostics</a>
                <a href="index.php" class="btn btn-secondary btn-lg">Home</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
?>
