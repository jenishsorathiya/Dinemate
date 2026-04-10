<?php
require_once "config/db.php";

$columns = [];
$bookings = [];
$tables = [];
$hasStartTime = false;
$hasEndTime = false;
$errorMessage = '';

try {
    $result = $pdo->query("SHOW COLUMNS FROM bookings");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        if (($col['Field'] ?? '') === 'start_time') {
            $hasStartTime = true;
        }
        if (($col['Field'] ?? '') === 'end_time') {
            $hasEndTime = true;
        }
    }

    if (!$hasStartTime) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN start_time TIME NOT NULL DEFAULT '12:00:00' AFTER booking_date");
        $hasStartTime = true;
        $result = $pdo->query("SHOW COLUMNS FROM bookings");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    }

    if (!$hasEndTime) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN end_time TIME NOT NULL DEFAULT '13:00:00' AFTER start_time");
        $hasEndTime = true;
        $result = $pdo->query("SHOW COLUMNS FROM bookings");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    }

    $bookings = $pdo->query("SELECT booking_id, user_id, table_id, booking_date, start_time, end_time, number_of_guests, status FROM bookings LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $tables = $pdo->query("SELECT * FROM restaurant_tables ORDER BY table_number ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate Database Test</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; padding: 32px 20px; }
        .wrap { max-width: 1080px; margin: 0 auto; background: var(--dm-surface); border: 1px solid #e7ecf3; border-radius: 20px; box-shadow: 0 18px 42px rgba(15,23,42,0.08); padding: 32px; }
        .panel { background: var(--dm-surface); border: 1px solid #e7ecf3; border-radius: 16px; padding: 20px; margin-top: 18px; }
        .flag { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .flag.ok { background: #e6f7ee; color: #1d7a53; }
        .flag.error { background: #ffe7ea; color: #c13f56; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e7ecf3; text-align: left; font-size: 14px; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #8a94a6; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Database Diagnostic Report</h1>
        <p class="dm-body-text">Schema and sample data check for booking-related tables.</p>

        <?php if ($errorMessage !== ''): ?>
            <div class="panel" style="background:#ffe7ea;border-color:#ffd1d7;color:#c13f56;">
                <strong>Database error</strong>
                <div><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php else: ?>
            <div class="panel">
                <h2 class="dm-section-title">Column Verification</h2>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
                    <span class="flag <?php echo $hasStartTime ? 'ok' : 'error'; ?>">start_time <?php echo $hasStartTime ? 'present' : 'missing'; ?></span>
                    <span class="flag <?php echo $hasEndTime ? 'ok' : 'error'; ?>">end_time <?php echo $hasEndTime ? 'present' : 'missing'; ?></span>
                </div>
            </div>

            <div class="panel">
                <h2 class="dm-section-title">Bookings Table Structure</h2>
                <table>
                    <thead>
                        <tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($columns as $col): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) ($col['Field'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($col['Type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($col['Null'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($col['Key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) (($col['Default'] ?? 'N/A')), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel">
                <h2 class="dm-section-title">Sample Bookings</h2>
                <?php if (!empty($bookings)): ?>
                    <table>
                        <thead>
                            <tr><th>ID</th><th>User</th><th>Table</th><th>Date</th><th>Start</th><th>End</th><th>Guests</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo (int) $booking['booking_id']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $booking['user_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $booking['table_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $booking['booking_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($booking['start_time'] ?? 'NULL'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($booking['end_time'] ?? 'NULL'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $booking['number_of_guests'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) $booking['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="dm-body-text">No bookings found in the database.</p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2 class="dm-section-title">Restaurant Tables</h2>
                <p class="dm-body-text">Total tables: <?php echo count($tables); ?></p>
                <?php if (count($tables) === 0): ?>
                    <div class="flag error">No restaurant tables found. Add tables first.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

