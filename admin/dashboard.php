<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

// Get dashboard statistics
$totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalTables = $pdo->query("SELECT COUNT(*) FROM restaurant_tables")->fetchColumn();
$todayBookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE()")->fetchColumn();

// Get latest bookings
$stmt = $pdo->query("
    SELECT b.*, COALESCE(b.customer_name_override, b.customer_name, u.name) AS name, t.table_number 
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN restaurant_tables t ON b.table_id = t.table_id
    ORDER BY b.created_at DESC
    LIMIT 5
");
$recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get table status for today
$tableStatus = $pdo->query("
    SELECT 
        t.table_id,
        t.table_number,
        t.capacity,
        CASE
            WHEN COUNT(b.booking_id) > 0 THEN 'booked'
            ELSE 'available'
        END AS status
    FROM restaurant_tables t
    LEFT JOIN bookings b
        ON t.table_id = b.table_id
        AND b.booking_date = CURDATE()
        AND b.status IN ('pending','confirmed')
    GROUP BY t.table_id
    ORDER BY t.table_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get chart data (last 7 days)
$chartQuery = $pdo->query("
    SELECT DATE(booking_date) AS day, COUNT(*) AS total
    FROM bookings
    WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(booking_date)
    ORDER BY DATE(booking_date) ASC
");
$chartData = $chartQuery->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart arrays
$days = [];
$totals = [];
foreach($chartData as $row) {
    $days[] = date('M d', strtotime($row['day']));
    $totals[] = $row['total'];
}

// Fill missing days with 0
$last7Days = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayLabel = date('M d', strtotime($date));
    if(!in_array($dayLabel, $days)) {
        $days[] = $dayLabel;
        $totals[] = 0;
    }
}
// Sort by date
array_multisort($days, $totals);

$adminPageTitle = 'Analytics';
$adminPageIcon = 'fa-chart-line';
$adminNotificationCount = (int) $todayBookings;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'dashboard';
$adminSidebarPathPrefix = '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>DineMate Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard-theme.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            transition: 0.3s;
        }

        .admin-layout {
            display: flex;
            height: 100vh;
        }
        
        body.dark-mode {
            background: #111827;
            color: white;
        }
        
        /* SIDEBAR */
        
        body.dark-mode .sidebar {
            background: #0f172a;
        }
        
        .main-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        body.dark-mode .topbar {
            background: #1f2937;
            color: white;
        }

        body.dark-mode .topbar-page,
        body.dark-mode .topbar-page-title,
        body.dark-mode .topbar-page i,
        body.dark-mode .topbar-profile-name {
            color: white;
        }

        body.dark-mode .topbar-icon-button,
        body.dark-mode .topbar-profile {
            background: #111827;
            color: white;
        }

        body.dark-mode .topbar-profile-icon {
            background: #f4b400;
            color: #111827;
        }
        
        /* MAIN CONTENT */
        .main {
            flex: 1;
            overflow-y: auto;
            padding: 30px;
        }
        
        /* STAT CARDS */
        .stat-card {
            background: white;
            border: 1px solid #e7ecf3;
            padding: 24px;
            border-radius: 18px;
            box-shadow: 0 16px 36px rgba(15,23,42,0.06);
            transition: 0.3s;
            text-align: center;
        }
        
        body.dark-mode .stat-card {
            background: #1f2937;
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
        }
        
        .stat-card i {
            font-size: 32px;
            color: #556176;
            margin-bottom: 12px;
        }
        
        .stat-card h2 {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0;
            color: #1f2937;
        }
        
        body.dark-mode .stat-card h2 {
            color: white;
        }
        
        .stat-card p {
            color: #6b7280;
            margin: 0;
        }
        
        body.dark-mode .stat-card p {
            color: #9ca3af;
        }
        
        /* CARDS */
        .card-custom {
            background: white;
            border: 1px solid #e7ecf3;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 16px 36px rgba(15,23,42,0.06);
            margin-bottom: 30px;
        }
        
        body.dark-mode .card-custom {
            background: #1f2937;
        }
        
        .card-custom h5 {
            font-weight: 600;
            margin-bottom: 20px;
            color: #1f2937;
        }
        
        body.dark-mode .card-custom h5 {
            color: white;
        }
        
        /* TABLE */
        .table-custom {
            width: 100%;
        }
        
        .table-custom th {
            background: #f9fafb;
            padding: 12px;
            font-weight: 600;
            color: #374151;
        }
        
        body.dark-mode .table-custom th {
            background: #374151;
            color: white;
        }
        
        .table-custom td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        body.dark-mode .table-custom td {
            color: #e5e7eb;
            border-color: #374151;
        }
        
        /* TABLE GRID */
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 18px;
        }
        
        .table-box {
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table-box i {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .table-box.available {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }
        
        .table-box.booked {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .table-box:hover {
            transform: translateY(-5px);
        }
        
        /* DARK MODE TOGGLE */
        .dark-mode-toggle {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: 0.3s;
        }
        
        .dark-mode-toggle:hover {
            background: #f3f4f6;
        }
        
        body.dark-mode .dark-mode-toggle:hover {
            background: #374151;
        }
        
        /* BADGES */
        .badge-success {
            background: #22c55e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
        }
        
        .badge-danger {
            background: #ef4444;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
        }
        
    </style>
</head>
<body>

<div class="admin-layout">

<!-- SIDEBAR -->
<?php include __DIR__ . '/admin-sidebar.php'; ?>

<div class="main-content">
    <?php include __DIR__ . '/admin-topbar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
    
    <!-- STATS CARDS -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fa fa-calendar-check"></i>
                <h2><?= $totalBookings ?></h2>
                <p>Total Bookings</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fa fa-users"></i>
                <h2><?= $totalUsers ?></h2>
                <p>Total Customers</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fa fa-chair"></i>
                <h2><?= $totalTables ?></h2>
                <p>Total Tables</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fa fa-clock"></i>
                <h2><?= $todayBookings ?></h2>
                <p>Today's Bookings</p>
            </div>
        </div>
    </div>
    
    <!-- CHART SECTION -->
    <div class="card-custom">
        <h5><i class="fa fa-chart-line"></i> Booking Analytics (Last 7 Days)</h5>
        <canvas id="bookingChart" height="100"></canvas>
    </div>
    
    <!-- RECENT BOOKINGS & TABLE STATUS -->
    <div class="row">
        <div class="col-md-7">
            <div class="card-custom">
                <h5><i class="fa fa-clock"></i> Recent Reservations</h5>
                <?php if(count($recentBookings) > 0): ?>
                <table class="table-custom">
                    <thead>
                        <tr><th>Customer</th><th>Table</th><th>Date</th><th>Time</th><th>Guests</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentBookings as $booking): ?>
                        <tr>
                            <td><?= htmlspecialchars($booking['name']) ?></td>
                            <td><?= $booking['table_number'] ? 'Table ' . htmlspecialchars($booking['table_number']) : 'Unassigned' ?></td>
                            <td><?= $booking['booking_date'] ?></td>
                            <td><?= date('h:i A', strtotime($booking['start_time'])) ?> - <?= date('h:i A', strtotime($booking['end_time'])) ?></td>
                            <td><?= $booking['number_of_guests'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted text-center mb-0">No recent bookings found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-5">
            <div class="card-custom">
                <h5><i class="fa fa-chair"></i> Table Availability (Today)</h5>
                <div class="table-grid">
                    <?php foreach($tableStatus as $table): ?>
                    <div class="table-box <?= $table['status'] ?>">
                        <i class="fa fa-chair"></i>
                        <div>Table <?= $table['table_number'] ?></div>
                        <small><?= $table['capacity'] ?> seats</small>
                        <div class="mt-1">
                            <small><?= ucfirst($table['status']) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
</div>

<script>
// Chart.js
const ctx = document.getElementById('bookingChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($days) ?>,
        datasets: [{
            label: 'Bookings',
            data: <?= json_encode($totals) ?>,
            borderColor: '#f4b400',
            backgroundColor: 'rgba(244, 180, 0, 0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#f4b400',
            pointBorderColor: '#fff',
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Bookings: ${context.raw}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Dark Mode Toggle
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
}

// Check saved dark mode preference
if(localStorage.getItem('darkMode') === 'true') {
    document.body.classList.add('dark-mode');
}

// Auto-refresh every 30 seconds (optional)
setTimeout(function() {
    location.reload();
}, 30000);
</script>

</body>
</html>