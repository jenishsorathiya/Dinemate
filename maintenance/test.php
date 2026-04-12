<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$checks = [
    ['label' => 'PHP runtime', 'status' => 'ok', 'message' => 'XAMPP PHP is working'],
    ['label' => 'PHP version', 'status' => 'info', 'message' => phpversion()],
    ['label' => 'Current date/time', 'status' => 'info', 'message' => date('Y-m-d H:i:s')],
];

try {
    $pdo->query("SELECT 1");
    $checks[] = ['label' => 'Database connection', 'status' => 'ok', 'message' => 'Connection successful'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bookings");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $checks[] = ['label' => 'Bookings table', 'status' => 'ok', 'message' => 'Accessible with ' . (int) ($result['count'] ?? 0) . ' bookings'];
} catch (Exception $e) {
    $checks[] = ['label' => 'Database', 'status' => 'error', 'message' => $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate System Test</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(appPath('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; padding: 32px 20px; }
        .wrap { max-width: 760px; margin: 0 auto; background: var(--dm-surface); border: 1px solid #e7ecf3; border-radius: 20px; box-shadow: 0 18px 42px rgba(15,23,42,0.08); padding: 32px; }
        .check { border: 1px solid #e7ecf3; border-radius: 14px; padding: 14px 16px; margin-bottom: 12px; background: var(--dm-surface); }
        .check.ok { background: #e6f7ee; border-color: #ccefdc; }
        .check.error { background: #ffe7ea; border-color: #ffd1d7; }
        .check.info { background: var(--dm-surface-muted); }
        .check strong { display: block; color: #162033; margin-bottom: 4px; }
        .check span { color: var(--dm-text-muted); }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>System Test</h1>
        <p class="dm-body-text">Quick health check for the DineMate application runtime and database connectivity.</p>
        <?php foreach ($checks as $check): ?>
            <div class="check <?php echo htmlspecialchars($check['status'], ENT_QUOTES, 'UTF-8'); ?>">
                <strong><?php echo htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <span><?php echo htmlspecialchars($check['message'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endforeach; ?>
        <a href="<?php echo htmlspecialchars(appPath('public/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="dm-button" style="margin-top:12px;">Back to Home</a>
    </div>
</body>
</html>
