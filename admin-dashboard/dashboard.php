<?php
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin-login.php');
    exit();
}

function fetchValue(PDO $pdo, string $sql, array $params = [], $default = 0) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function fetchRows(PDO $pdo, string $sql, array $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

$totalBookings = fetchValue($pdo, 'SELECT COUNT(*) FROM bookings');
$todayBookings = fetchValue($pdo, 'SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE()');
$totalCustomers = fetchValue($pdo, "SELECT COUNT(*) FROM users WHERE role = 'customer'");
$totalTables = fetchValue($pdo, 'SELECT COUNT(*) FROM restaurant_tables');
$availableTables = fetchValue($pdo, "SELECT COUNT(*) FROM restaurant_tables WHERE status = 'available'");
$bookedTables = fetchValue($pdo, "SELECT COUNT(*) FROM restaurant_tables WHERE status = 'booked'");

$recentBookings = fetchRows(
    $pdo,
    "SELECT b.booking_id, b.booking_date, b.booking_time, b.number_of_guests,
            u.name AS customer_name, u.email,
            t.table_number
     FROM bookings b
     JOIN users u ON b.user_id = u.user_id
     JOIN restaurant_tables t ON b.table_id = t.table_id
     ORDER BY b.booking_date DESC, b.booking_time DESC
     LIMIT 10"
);

$tableOverview = fetchRows(
    $pdo,
    "SELECT table_number, capacity, status
     FROM restaurant_tables
     ORDER BY table_number ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #0f172a, #1e293b);
            color: white;
            padding: 28px 22px;
            position: sticky;
            top: 0;
        }
        .brand {
            font-size: 28px;
            font-weight: 700;
            color: #f4b400;
            margin-bottom: 8px;
        }
        .brand-sub {
            color: #cbd5e1;
            font-size: 14px;
            margin-bottom: 32px;
        }
        .side-link {
            display: block;
            color: #e2e8f0;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 10px;
            transition: 0.25s;
        }
        .side-link:hover,
        .side-link.active {
            background: rgba(244, 180, 0, 0.16);
            color: #fff;
        }
        .main-area {
            padding: 30px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        .page-title h1 {
            font-size: 30px;
            margin: 0;
            font-weight: 700;
        }
        .page-title p {
            margin: 6px 0 0;
            color: #6b7280;
        }
        .admin-badge {
            background: white;
            border-radius: 999px;
            padding: 10px 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            font-weight: 500;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.07);
            height: 100%;
        }
        .stat-icon {
            width: 54px;
            height: 54px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: rgba(244, 180, 0, 0.14);
            color: #b58100;
            font-size: 22px;
            margin-bottom: 18px;
        }
        .stat-label {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 34px;
            font-weight: 700;
            line-height: 1;
        }
        .panel {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.07);
            margin-top: 24px;
        }
        .panel h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .table thead th {
            border-bottom: none;
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
        }
        .status-pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-available {
            background: rgba(34, 197, 94, 0.15);
            color: #15803d;
        }
        .status-booked {
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }
        .quick-actions a {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        @media (max-width: 991px) {
            .sidebar {
                min-height: auto;
                position: relative;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-xl-2 sidebar">
            <div class="brand">DineMate</div>
            <div class="brand-sub">Admin dashboard</div>

            <a href="dashboard.php" class="side-link active"><i class="fa fa-chart-line me-2"></i> Dashboard</a>
            <a href="../bookings/book-table.php" class="side-link"><i class="fa fa-calendar-check me-2"></i> Booking page</a>
            <a href="../index.php" class="side-link"><i class="fa fa-house me-2"></i> Main website</a>
            <a href="../auth/logout.php" class="side-link"><i class="fa fa-right-from-bracket me-2"></i> Logout</a>
        </div>

        <div class="col-lg-9 col-xl-10 main-area">
            <div class="topbar">
                <div class="page-title">
                    <h1>Admin Dashboard</h1>
                    <p>Overview of bookings, customers, and table availability.</p>
                </div>
                <div class="admin-badge">
                    <i class="fa fa-user-shield me-2"></i>
                    Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa fa-calendar-days"></i></div>
                        <div class="stat-label">Total Bookings</div>
                        <div class="stat-value"><?= (int)$totalBookings ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa fa-sun"></i></div>
                        <div class="stat-label">Today's Bookings</div>
                        <div class="stat-value"><?= (int)$todayBookings ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa fa-users"></i></div>
                        <div class="stat-label">Customers</div>
                        <div class="stat-value"><?= (int)$totalCustomers ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fa fa-chair"></i></div>
                        <div class="stat-label">Available Tables</div>
                        <div class="stat-value"><?= (int)$availableTables ?>/<?= (int)$totalTables ?></div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
                    <h3 class="mb-0">Quick Actions</h3>
                    <div class="quick-actions">
                        <a href="../bookings/book-table.php" class="btn btn-warning"><i class="fa fa-plus me-2"></i>Create Booking</a>
                        <a href="../index.php" class="btn btn-outline-dark"><i class="fa fa-globe me-2"></i>View Site</a>
                    </div>
                </div>
                <p class="text-muted mb-0">This dashboard is restricted to admin users using session and role checks.</p>
            </div>

            <div class="row">
                <div class="col-xl-8">
                    <div class="panel">
                        <h3>Recent Bookings</h3>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Customer</th>
                                        <th>Table</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Guests</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($recentBookings)): ?>
                                    <?php foreach ($recentBookings as $booking): ?>
                                        <tr>
                                            <td>#<?= (int)$booking['booking_id'] ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($booking['customer_name'] ?? 'N/A') ?></div>
                                                <div class="text-muted small"><?= htmlspecialchars($booking['email'] ?? '') ?></div>
                                            </td>
                                            <td>Table <?= htmlspecialchars($booking['table_number'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($booking['booking_date'] ?? '-') ?></td>
                                            <td><?= !empty($booking['booking_time']) ? htmlspecialchars(date('h:i A', strtotime($booking['booking_time']))) : '-' ?></td>
                                            <td><?= (int)($booking['number_of_guests'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No bookings found yet.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="panel">
                        <h3>Table Overview</h3>
                        <div class="mb-3 text-muted small">
                            Booked: <strong><?= (int)$bookedTables ?></strong> · Available: <strong><?= (int)$availableTables ?></strong>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Table</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($tableOverview)): ?>
                                    <?php foreach ($tableOverview as $table): ?>
                                        <?php $status = strtolower((string)($table['status'] ?? 'unknown')); ?>
                                        <tr>
                                            <td><?= htmlspecialchars($table['table_number'] ?? '-') ?></td>
                                            <td><?= (int)($table['capacity'] ?? 0) ?></td>
                                            <td>
                                                <span class="status-pill <?= $status === 'available' ? 'status-available' : 'status-booked' ?>">
                                                    <?= htmlspecialchars(ucfirst($status)) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No table data found.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
