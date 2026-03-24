<?php
session_start();

// Check admin access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

// Sample data for frontend display (will be connected to database later)
$stats = [
    'total_bookings' => 156,
    'total_users' => 48,
    'total_tables' => 10,
    'today_bookings' => 8
];

$recent_bookings = [
    ['customer' => 'John Doe', 'table' => 'Table 4', 'date' => '2024-03-25', 'guests' => 4],
    ['customer' => 'Sarah Smith', 'table' => 'Table 2', 'date' => '2024-03-25', 'guests' => 2],
    ['customer' => 'Mike Johnson', 'table' => 'Table 6', 'date' => '2024-03-24', 'guests' => 6],
    ['customer' => 'Emma Wilson', 'table' => 'Table 3', 'date' => '2024-03-24', 'guests' => 4],
    ['customer' => 'David Brown', 'table' => 'Table 1', 'date' => '2024-03-23', 'guests' => 2]
];

$tables = [
    ['id' => 1, 'number' => 1, 'capacity' => 2, 'status' => 'available'],
    ['id' => 2, 'number' => 2, 'capacity' => 2, 'status' => 'booked'],
    ['id' => 3, 'number' => 3, 'capacity' => 4, 'status' => 'available'],
    ['id' => 4, 'number' => 4, 'capacity' => 4, 'status' => 'available'],
    ['id' => 5, 'number' => 5, 'capacity' => 6, 'status' => 'booked'],
    ['id' => 6, 'number' => 6, 'capacity' => 8, 'status' => 'available'],
    ['id' => 7, 'number' => 7, 'capacity' => 4, 'status' => 'available'],
    ['id' => 8, 'number' => 8, 'capacity' => 5, 'status' => 'booked']
];

$chart_days = ['Mar 18', 'Mar 19', 'Mar 20', 'Mar 21', 'Mar 22', 'Mar 23', 'Mar 24'];
$chart_totals = [5, 8, 12, 7, 15, 10, 18];
?>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h4><i class="fa fa-utensils"></i> DineMate</h4>
    <a href="dashboard.php" class="active">
        <i class="fa fa-chart-line"></i> Dashboard
    </a>
    <a href="manage-bookings.php">
        <i class="fa fa-calendar-check"></i> Bookings
    </a>
    <a href="manage-tables.php">
        <i class="fa fa-chair"></i> Tables
    </a>
    <a href="manage-users.php">
        <i class="fa fa-users"></i> Users
    </a>
    <a href="../auth/logout.php">
        <i class="fa fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- TOPBAR -->
<div class="topbar">
    <h5 class="mb-0">Admin Dashboard</h5>
    <div>
        <button onclick="toggleDarkMode()" class="dark-mode-toggle">
            <i class="fa fa-moon"></i>
        </button>
        <span class="ms-3">
            <i class="fa fa-bell"></i>
            <span class="badge bg-danger ms-1"><?= $stats['today_bookings'] ?></span>
        </span>
        <span class="ms-3">
            <i class="fa fa-user-circle"></i> <?= $_SESSION['name'] ?? 'Admin' ?>
        </span>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main">
    
    <!-- STATS CARDS -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fa fa-calendar-check"></i>
                <h2><?= $stats['total_bookings'] ?></h2>
                <p>Total Bookings</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fa fa-users"></i>
                <h2><?= $stats['total_users'] ?></h2>
                <p>Total Customers</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fa fa-chair"></i>
                <h2><?= $stats['total_tables'] ?></h2>
                <p>Total Tables</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fa fa-clock"></i>
                <h2><?= $stats['today_bookings'] ?></h2>
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
                <table class="table-custom">
                    <thead>
                        <tr><th>Customer</th><th>Table</th><th>Date</th><th>Guests</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_bookings as $booking): ?>
                        <tr>
                            <td><?= $booking['customer'] ?></td>
                            <td><?= $booking['table'] ?></td>
                            <td><?= $booking['date'] ?></td>
                            <td><?= $booking['guests'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="col-md-5">
            <div class="card-custom">
                <h5><i class="fa fa-chair"></i> Table Availability</h5>
                <div class="table-grid">
                    <?php foreach($tables as $table): ?>
                    <div class="table-box <?= $table['status'] ?>">
                        <i class="fa fa-chair"></i>
                        <div>Table <?= $table['number'] ?></div>
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

<script>
// Chart.js
const ctx = document.getElementById('bookingChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_days) ?>,
        datasets: [{
            label: 'Bookings',
            data: <?= json_encode($chart_totals) ?>,
            borderColor: '#f4b400',
            backgroundColor: 'rgba(244, 180, 0, 0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
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
</script>

</body>
</html>