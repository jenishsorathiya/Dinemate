<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureTableAreasSchema($pdo);
ensureBookingTableAssignmentsTable($pdo);

$totalTables = (int) $pdo->query("SELECT COUNT(*) FROM restaurant_tables")->fetchColumn();
$activeAreas = (int) $pdo->query("SELECT COUNT(*) FROM table_areas WHERE is_active = 1")->fetchColumn();
$unassignedTables = (int) $pdo->query("SELECT COUNT(*) FROM restaurant_tables WHERE area_id IS NULL")->fetchColumn();
$bookedTablesToday = (int) $pdo->query(
    "SELECT COUNT(DISTINCT COALESCE(bta.table_id, b.table_id))
     FROM bookings b
     LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
     WHERE b.booking_date = CURDATE()
       AND b.status IN ('pending', 'confirmed')
       AND COALESCE(bta.table_id, b.table_id) IS NOT NULL"
)->fetchColumn();

$areasStmt = $pdo->query(
    "SELECT ta.area_id,
            ta.name,
            ta.table_number_start,
            ta.table_number_end,
            COUNT(rt.table_id) AS table_count,
            COALESCE(SUM(rt.capacity), 0) AS total_capacity
     FROM table_areas ta
     LEFT JOIN restaurant_tables rt ON rt.area_id = ta.area_id
     WHERE ta.is_active = 1
     GROUP BY ta.area_id
     ORDER BY ta.display_order ASC, ta.name ASC"
);
$areas = $areasStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$tableStmt = $pdo->query(
    "SELECT rt.table_number, rt.capacity, COALESCE(ta.name, 'No Area') AS area_name
     FROM restaurant_tables rt
     LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
     ORDER BY ta.display_order ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
     LIMIT 12"
);
$tablePreview = $tableStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$adminPageTitle = 'Tables Management';
$adminPageIcon = 'fa-chair';
$adminNotificationCount = $bookedTablesToday;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Tables Management</title>
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
        .stat-card,
        .area-card {
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
        .areas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .area-card {
            padding: 18px;
            border: 1px solid #e5e7eb;
        }
        .area-card h3 {
            font-size: 18px;
            margin-bottom: 12px;
            color: #111827;
        }
        .area-meta {
            color: #64748b;
            margin-bottom: 8px;
        }
        .table-preview {
            width: 100%;
            border-collapse: collapse;
        }
        .table-preview th,
        .table-preview td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        .table-preview th {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
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
        <a href="bookings-management.php">
            <i class="fa fa-clipboard-list"></i><span class="nav-label">Bookings</span>
        </a>
        <a href="tables-management.php" class="active">
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
                    <h1>Tables Management</h1>
                    <p>Use this screen as the operational overview for floor layout, area coverage, and table availability before moving into the live timeline grid.</p>
                </div>
                <div class="hero-actions">
                    <a href="timeline/new-dashboard.php#timelineGrid" class="btn btn-dark">Open Timeline Grid</a>
                    <a href="timeline/new-dashboard.php" class="btn btn-outline-secondary">Open Full Timeline</a>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-label">Total Tables</div>
                        <div class="stat-value"><?php echo $totalTables; ?></div>
                        <p class="stat-note">Current table inventory across the venue.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-label">Active Areas</div>
                        <div class="stat-value"><?php echo $activeAreas; ?></div>
                        <p class="stat-note">Areas currently enabled for service.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-label">Unassigned Tables</div>
                        <div class="stat-value"><?php echo $unassignedTables; ?></div>
                        <p class="stat-note">Tables not currently attached to an area.</p>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-label">Booked Today</div>
                        <div class="stat-value"><?php echo $bookedTablesToday; ?></div>
                        <p class="stat-note">Distinct tables already in use today.</p>
                    </div>
                </div>
            </div>

            <section class="panel-card">
                <h2>Area Overview</h2>
                <?php if (!empty($areas)): ?>
                    <div class="areas-grid">
                        <?php foreach ($areas as $area): ?>
                            <div class="area-card">
                                <h3><?php echo htmlspecialchars($area['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <div class="area-meta">Tables: <?php echo (int) $area['table_count']; ?></div>
                                <div class="area-meta">Seats: <?php echo (int) $area['total_capacity']; ?></div>
                                <div class="area-meta">
                                    Range:
                                    <?php
                                    if ($area['table_number_start'] !== null && $area['table_number_end'] !== null) {
                                        echo htmlspecialchars($area['table_number_start'] . ' - ' . $area['table_number_end'], ENT_QUOTES, 'UTF-8');
                                    } else {
                                        echo 'Manual';
                                    }
                                    ?>
                                </div>
                                <a href="timeline/new-dashboard.php" class="btn btn-sm btn-outline-dark mt-2">Manage in Timeline</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No active areas are set up yet.</div>
                <?php endif; ?>
            </section>

            <section class="panel-card">
                <h2>Table Preview</h2>
                <?php if (!empty($tablePreview)): ?>
                    <div class="table-responsive">
                        <table class="table-preview">
                            <thead>
                                <tr>
                                    <th>Table</th>
                                    <th>Area</th>
                                    <th>Capacity</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tablePreview as $table): ?>
                                    <tr>
                                        <td>T<?php echo htmlspecialchars($table['table_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($table['area_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $table['capacity']; ?> seats</td>
                                        <td><a class="btn btn-sm btn-outline-dark" href="timeline/new-dashboard.php#timelineGrid">Open Grid</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No tables have been created yet.</div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
</body>
</html>