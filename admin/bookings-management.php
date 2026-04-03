<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);

$pendingBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$confirmedToday = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE() AND status = 'confirmed'")->fetchColumn();
$upcomingBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date >= CURDATE() AND status IN ('pending', 'confirmed')")->fetchColumn();
$guestBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date >= CURDATE() AND user_id IS NULL AND status IN ('pending', 'confirmed')")->fetchColumn();

$recentStmt = $pdo->query(
    "SELECT b.booking_id,
            b.booking_date,
            b.start_time,
            b.number_of_guests,
            b.status,
            COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name,
            GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
     FROM bookings b
     LEFT JOIN users u ON b.user_id = u.user_id
     LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
     LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
     WHERE b.booking_date >= CURDATE() AND b.status IN ('pending', 'confirmed')
     GROUP BY b.booking_id
     ORDER BY FIELD(b.status, 'pending', 'confirmed'), b.booking_date ASC, b.start_time ASC
     LIMIT 10"
);
$recentBookings = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$adminPageTitle = 'Bookings Management';
$adminPageIcon = 'fa-clipboard-list';
$adminNotificationCount = $pendingBookings;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Bookings Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #f5f7fb;
        }
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 88px;
            background: #111827;
            color: white;
            padding: 20px;
            overflow-y: auto;
            overflow-x: hidden;
            flex-shrink: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: width 0.25s ease;
        }
        .sidebar:hover {
            width: 260px;
        }
        .sidebar h4 {
            color: #f4b400;
            margin-bottom: 30px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
            white-space: nowrap;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 15px;
            color: #ddd;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: background 0.2s ease, color 0.2s ease, justify-content 0.2s ease;
            white-space: nowrap;
        }
        .sidebar:hover a {
            justify-content: flex-start;
        }
        .sidebar h4 i,
        .sidebar a i {
            width: 24px;
            min-width: 24px;
            text-align: center;
            font-size: 20px;
        }
        .brand-label,
        .nav-label {
            opacity: 0;
            max-width: 0;
            margin-left: 0;
            overflow: hidden;
            transition: opacity 0.2s ease, max-width 0.25s ease, margin-left 0.25s ease;
        }
        .sidebar:hover .brand-label,
        .sidebar:hover .nav-label {
            opacity: 1;
            max-width: 180px;
            margin-left: 12px;
        }
        .sidebar:not(:hover) h4 {
            justify-content: center;
        }
        .sidebar a:hover,
        .sidebar a.active {
            background: #f4b400;
            color: #111827;
        }
        .main-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .page-shell {
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .hero-card,
        .panel-card,
        .stat-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }
        .hero-card {
            padding: 28px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-start;
        }
        .hero-card h1 {
            font-size: 30px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
        }
        .hero-card p {
            margin: 0;
            max-width: 700px;
            color: #64748b;
            line-height: 1.7;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .stat-card {
            padding: 22px;
            height: 100%;
        }
        .stat-card .stat-label {
            color: #64748b;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }
        .stat-card .stat-value {
            font-size: 34px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 8px;
        }
        .stat-card .stat-note {
            color: #64748b;
            margin: 0;
        }
        .panel-card {
            padding: 24px;
        }
        .panel-card h2 {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 18px;
        }
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .quick-link {
            display: block;
            text-decoration: none;
            padding: 18px;
            border-radius: 18px;
            background: linear-gradient(135deg, #fff7ed, #fffbeb);
            color: #9a3412;
            border: 1px solid rgba(249, 115, 22, 0.12);
        }
        .quick-link strong {
            display: block;
            color: #7c2d12;
            margin-bottom: 6px;
        }
        .quick-link span {
            color: #9a3412;
            font-size: 14px;
        }
        .booking-table {
            width: 100%;
            border-collapse: collapse;
        }
        .booking-table th,
        .booking-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
        }
        .booking-table th {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
        }
        .status-pill.pending {
            background: #fff7ed;
            color: #c2410c;
        }
        .status-pill.confirmed {
            background: #ecfdf5;
            color: #047857;
        }
        .empty-state {
            padding: 24px;
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            text-align: center;
            color: #64748b;
        }
        @media (max-width: 991px) {
            .sidebar {
                display: none;
            }
            .page-shell {
                padding: 20px;
            }
            .hero-card {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h4><i class="fa fa-utensils"></i><span class="brand-label">DineMate</span></h4>
        <a href="dashboard.php">
            <i class="fa fa-chart-line"></i><span class="nav-label">Analytics</span>
        </a>
        <a href="timeline/new-dashboard.php">
            <i class="fa fa-calendar-days"></i><span class="nav-label">Timeline</span>
        </a>
        <a href="bookings-management.php" class="active">
            <i class="fa fa-clipboard-list"></i><span class="nav-label">Bookings</span>
        </a>
        <a href="tables-management.php">
            <i class="fa fa-chair"></i><span class="nav-label">Tables</span>
        </a>
        <a href="menu-management.php">
            <i class="fa fa-utensils"></i><span class="nav-label">Menu</span>
        </a>
        <a href="manage-users.php">
            <i class="fa fa-users"></i><span class="nav-label">Users</span>
        </a>
        <a href="../auth/logout.php">
            <i class="fa fa-sign-out-alt"></i><span class="nav-label">Logout</span>
        </a>
    </div>

    <div class="main-content">
        <?php include __DIR__ . '/admin-topbar.php'; ?>

        <div class="page-shell">
            <section class="hero-card">
                <div>
                    <h1>Bookings Management</h1>
                    <p>This screen gives you a fast control point for pending requests, guest volume, and upcoming service load before you jump into the timeline.</p>
                </div>
                <div class="hero-actions">
                    <a href="timeline/new-dashboard.php?date=<?php echo urlencode(date('Y-m-d')); ?>#bookingList" class="btn btn-dark">Open Today's Timeline</a>
                    <a href="timeline/new-dashboard.php#bookingList" class="btn btn-outline-secondary">Jump to Booking List</a>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-label">Pending Requests</div>
                        <div class="stat-value"><?php echo $pendingBookings; ?></div>
                        <p class="stat-note">Need confirmation or assignment.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-label">Confirmed Today</div>
                        <div class="stat-value"><?php echo $confirmedToday; ?></div>
                        <p class="stat-note">Locked into today's service.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-label">Upcoming Bookings</div>
                        <div class="stat-value"><?php echo $upcomingBookings; ?></div>
                        <p class="stat-note">Pending and confirmed future visits.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-label">Guest Bookings</div>
                        <div class="stat-value"><?php echo $guestBookings; ?></div>
                        <p class="stat-note">Future bookings without a linked account.</p>
                    </div>
                </div>
            </div>

            <section class="panel-card">
                <h2>Quick Actions</h2>
                <div class="quick-grid">
                    <a class="quick-link" href="timeline/new-dashboard.php#pendingTabBtn">
                        <strong>Review Pending Queue</strong>
                        <span>Open the timeline on the booking panel and work through confirmations.</span>
                    </a>
                    <a class="quick-link" href="timeline/new-dashboard.php#bookingList">
                        <strong>Manage All Bookings</strong>
                        <span>Use the left-side list to filter between pending, standby, and confirmed bookings.</span>
                    </a>
                    <a class="quick-link" href="timeline/new-dashboard.php">
                        <strong>Assign Tables</strong>
                        <span>Open the full timeline grid to place or adjust table assignments.</span>
                    </a>
                </div>
            </section>

            <section class="panel-card">
                <h2>Upcoming Booking Snapshot</h2>
                <?php if (!empty($recentBookings)): ?>
                    <div class="table-responsive">
                        <table class="booking-table">
                            <thead>
                                <tr>
                                    <th>Guest</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Guests</th>
                                    <th>Tables</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(date('D, j M', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $booking['number_of_guests']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['assigned_table_numbers'] ?: 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="status-pill <?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-dark" href="timeline/new-dashboard.php?date=<?php echo urlencode($booking['booking_date']); ?>#bookingList">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No upcoming bookings are waiting right now.</div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
</body>
</html>