<?php
/**
 * DineMate Booking System - Quick Start & Troubleshooting
 * Visit: http://localhost/dinemate/setup.php
 */
require_once __DIR__ . "/../includes/functions.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Quick Start Guide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appPath('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <style>
        body { background: #f5f7fb; min-height: 100vh; padding: 40px 20px; font-family: 'Inter', sans-serif; }
        .container { background: var(--dm-surface); border: 1px solid #e7ecf3; border-radius: 20px; box-shadow: 0 20px 48px rgba(15, 23, 42, 0.08); padding: 40px; max-width: 960px; }
        h1 { color: #162033; margin-bottom: 18px; }
        h3 { color: #162033; margin-top: 30px; margin-bottom: 18px; font-size: 20px; font-weight: 600; }
        .step { background: var(--dm-surface); border: 1px solid #e7ecf3; padding: 20px; margin: 15px 0; border-radius: 18px; box-shadow: 0 12px 28px rgba(15,23,42,0.05); }
        .step-number { display: inline-flex; align-items: center; justify-content: center; background: #1d2840; color: white; width: 35px; height: 35px; border-radius: 999px; font-weight: 700; margin-right: 15px; }
        .btn-action { padding: 10px 25px; margin: 10px 5px 10px 0; font-weight: 600; }
        .check-list { list-style: none; padding: 0; }
        .check-list li { padding: 8px 0; }
        .check-list li:before { content: "✓ "; color: #28a745; font-weight: bold; margin-right: 10px; }
        .code-block { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 8px; margin: 10px 0; overflow-x: auto; font-family: 'Courier New', monospace; font-size: 13px; }
        .alert-box { border-radius: 16px; padding: 15px; margin: 15px 0; }
        .success-box { background: #e6f7ee; border: 1px solid #ccefdc; color: #1d7a53; }
        .error-box { background: #ffe7ea; border: 1px solid #ffd1d7; color: #c13f56; }
        .info-box { background: var(--dm-surface-muted); border: 1px solid #e7ecf3; color: var(--dm-text-muted); }
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
                <a href="<?php echo htmlspecialchars(appPath('maintenance/auto-fix.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-action" target="_blank">
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
                <a href="<?php echo htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success btn-action" target="_blank">
                    📅 Book a Reservation
                </a>
                <a href="<?php echo htmlspecialchars(appPath('customer/my-bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-info btn-action" target="_blank">
                    📋 View My Bookings
                </a>
            </div>
        </div>
        
        <div class="step">
            <span class="step-number">3</span>
            <strong>Run Diagnostics</strong>
            <p class="mt-2">If you encounter issues, check detailed system status:</p>
            <div class="button-group">
                <a href="<?php echo htmlspecialchars(appPath('maintenance/diagnose.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warning btn-action" target="_blank">
                    🔍 Run Diagnostics
                </a>
            </div>
        </div>
        
        <hr>
        
        <h3>🔧 Troubleshooting</h3>
        
        <div class="alert-box info-box">
            <h5>❓ Reservation page shows errors</h5>
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
            <h5>❓ Unassigned bookings are not appearing in the admin timeline</h5>
            <ul>
                <li>Verify that bookings have <code>start_time</code>, <code>end_time</code>, and <code>status</code> values (Run Auto-Fix)</li>
                <li>Confirm new customer bookings are being created with <code>table_id = NULL</code></li>
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
                <li>Guest-count capacity check</li>
                <li>Special request capture</li>
            </ul>
        </div>
        
        <div class="step">
            <strong>Assignment Workflow:</strong>
            <ul class="check-list">
                <li>Start time and end time for each booking</li>
                <li>Customers submit unassigned booking requests</li>
                <li>Admins drag pending bookings onto the timeline</li>
                <li>Assignment confirms the booking and attaches a table</li>
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
                <li>Switched customer bookings to pending unassigned requests</li>
                <li>Added comprehensive diagnostics and auto-fix tools</li>
                <li>Improved form validation and error messages</li>
            </ul>
        </div>
        
        <hr>
        
        <div class="text-center mt-4">
            <p class="text-muted">For more help, run the Auto-Fix tool or visit the Diagnostics page</p>
            <div class="button-group justify-content-center">
                <a href="<?php echo htmlspecialchars(appPath('maintenance/auto-fix.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg" target="_blank">Auto-Fix</a>
                <a href="<?php echo htmlspecialchars(appPath('maintenance/diagnose.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-warning btn-lg" target="_blank">Diagnostics</a>
                <a href="<?php echo htmlspecialchars(appPath('public/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary btn-lg">Home</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php
?>
