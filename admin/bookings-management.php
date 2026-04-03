<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    if ($bookingId > 0) {
        try {
            if ($action === 'confirm_pending') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ? AND status = 'pending'");
                $stmt->execute([$bookingId]);

                if ($stmt->rowCount() > 0) {
                    setFlashMessage('success', 'Pending booking confirmed.');
                } else {
                    setFlashMessage('warning', 'That booking could not be confirmed.');
                }
            } elseif ($action === 'reject_pending') {
                $pdo->beginTransaction();

                $deleteAssignmentsStmt = $pdo->prepare("DELETE FROM booking_table_assignments WHERE booking_id = ?");
                $deleteAssignmentsStmt->execute([$bookingId]);

                $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', table_id = NULL WHERE booking_id = ? AND status = 'pending'");
                $updateStmt->execute([$bookingId]);

                $pdo->commit();

                if ($updateStmt->rowCount() > 0) {
                    setFlashMessage('success', 'Pending booking rejected.');
                } else {
                    setFlashMessage('warning', 'That booking could not be rejected.');
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlashMessage('error', 'Booking workflow action failed.');
        }
    }

    header('Location: bookings-management.php');
    exit();
}

$pendingRequestsCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM bookings
     WHERE status = 'pending'
       AND booking_date >= CURDATE()"
)->fetchColumn();

$unassignedConfirmedCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM (
        SELECT b.booking_id
        FROM bookings b
        LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
        WHERE b.status = 'confirmed'
          AND b.booking_date >= CURDATE()
        GROUP BY b.booking_id
        HAVING MAX(COALESCE(bta.table_id, b.table_id)) IS NULL
     ) standby"
)->fetchColumn();

$conflictPairsCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM (
        SELECT DISTINCT CONCAT(LEAST(b1.booking_id, b2.booking_id), '-', GREATEST(b1.booking_id, b2.booking_id)) AS pair_id
        FROM bookings b1
        INNER JOIN booking_table_assignments bta1 ON b1.booking_id = bta1.booking_id
        INNER JOIN booking_table_assignments bta2 ON bta1.table_id = bta2.table_id
        INNER JOIN bookings b2 ON b2.booking_id = bta2.booking_id
        WHERE b1.booking_id < b2.booking_id
          AND b1.booking_date = b2.booking_date
          AND b1.booking_date >= CURDATE()
          AND b1.status IN ('pending', 'confirmed')
          AND b2.status IN ('pending', 'confirmed')
          AND b1.start_time < b2.end_time
          AND b1.end_time > b2.start_time
     ) conflicts"
)->fetchColumn();

$pendingQueueStmt = $pdo->query(
    "SELECT b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.special_request,
            COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name
     FROM bookings b
     LEFT JOIN users u ON b.user_id = u.user_id
     WHERE b.status = 'pending'
       AND b.booking_date >= CURDATE()
     ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC
     LIMIT 12"
);
$pendingQueue = $pendingQueueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$standbyQueueStmt = $pdo->query(
    "SELECT b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.special_request,
            COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name
     FROM bookings b
     LEFT JOIN users u ON b.user_id = u.user_id
     LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
     WHERE b.status = 'confirmed'
       AND b.booking_date >= CURDATE()
     GROUP BY b.booking_id
     HAVING MAX(COALESCE(bta.table_id, b.table_id)) IS NULL
     ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC
     LIMIT 12"
);
$standbyQueue = $standbyQueueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$todayBookingsStmt = $pdo->query(
    "SELECT b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.status,
            b.special_request,
            COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name,
            GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
     FROM bookings b
     LEFT JOIN users u ON b.user_id = u.user_id
     LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
     LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
     WHERE b.booking_date = CURDATE()
       AND b.status IN ('pending', 'confirmed')
     GROUP BY b.booking_id
     ORDER BY b.start_time ASC, b.booking_id ASC"
);
$todayBookings = $todayBookingsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$todayCapacity = (int) $pdo->query("SELECT COALESCE(SUM(capacity), 0) FROM restaurant_tables")->fetchColumn();

$todayFlow = [
    'now' => [],
    'next' => [],
    'later' => [],
];

$todayDate = date('Y-m-d');
$nowTimestamp = time();
$nextWindowTimestamp = strtotime('+2 hours', $nowTimestamp);

foreach ($todayBookings as $booking) {
    $startTimestamp = strtotime($todayDate . ' ' . substr((string) $booking['start_time'], 0, 8));
    $endTimestamp = strtotime($todayDate . ' ' . substr((string) $booking['end_time'], 0, 8));

    if ($endTimestamp <= $nowTimestamp) {
        continue;
    }

    if ($startTimestamp <= $nowTimestamp && $endTimestamp > $nowTimestamp) {
        $todayFlow['now'][] = $booking;
    } elseif ($startTimestamp > $nowTimestamp && $startTimestamp <= $nextWindowTimestamp) {
        $todayFlow['next'][] = $booking;
    } else {
        $todayFlow['later'][] = $booking;
    }
}

$serviceLoadRows = [];
if ($todayCapacity > 0) {
    $minuteNow = (int) date('i', $nowTimestamp);
    $hourBase = strtotime(date('Y-m-d H:00:00', $nowTimestamp));

    if ($minuteNow === 0 || $minuteNow === 30) {
        $slotTimestamp = strtotime(date('Y-m-d H:i:00', $nowTimestamp));
    } elseif ($minuteNow < 30) {
        $slotTimestamp = strtotime('+30 minutes', $hourBase);
    } else {
        $slotTimestamp = strtotime('+1 hour', $hourBase);
    }

    $closingTimestamp = strtotime($todayDate . ' 22:00:00');
    $slotCount = 0;

    while ($slotTimestamp < $closingTimestamp && $slotCount < 8) {
        $guestLoad = 0;

        foreach ($todayBookings as $booking) {
            $bookingStart = strtotime($todayDate . ' ' . substr((string) $booking['start_time'], 0, 8));
            $bookingEnd = strtotime($todayDate . ' ' . substr((string) $booking['end_time'], 0, 8));

            if ($bookingStart <= $slotTimestamp && $bookingEnd > $slotTimestamp) {
                $guestLoad += (int) ($booking['number_of_guests'] ?? 0);
            }
        }

        $loadPercent = $todayCapacity > 0 ? (int) round(($guestLoad / $todayCapacity) * 100) : 0;
        $serviceLoadRows[] = [
            'time_label' => date('g:i A', $slotTimestamp),
            'guest_load' => $guestLoad,
            'percent' => $loadPercent,
            'width_percent' => min($loadPercent, 100),
            'is_warning' => $loadPercent >= 90,
        ];

        $slotTimestamp = strtotime('+30 minutes', $slotTimestamp);
        $slotCount++;
    }
}

$flash = getFlashMessage();

$adminPageTitle = 'Bookings Management';
$adminPageIcon = 'fa-clipboard-list';
$adminNotificationCount = $pendingRequestsCount;
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
            flex: 1;
            overflow-y: auto;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .section-block {
            display: grid;
            gap: 18px;
        }

        .section-header h1,
        .section-header h2 {
            margin: 0;
            color: #111827;
            font-weight: 700;
        }

        .section-header h1 {
            font-size: 34px;
        }

        .section-header h2 {
            font-size: 24px;
        }

        .section-header p {
            margin: 8px 0 0;
            color: #64748b;
            line-height: 1.6;
            max-width: 760px;
        }

        .attention-grid,
        .workflow-grid,
        .flow-grid {
            display: grid;
            gap: 18px;
        }

        .attention-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .workflow-grid,
        .flow-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .card-surface {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .attention-card {
            padding: 22px;
        }

        .attention-label {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .attention-value {
            font-size: 38px;
            line-height: 1;
            font-weight: 700;
            color: #111827;
            margin-bottom: 10px;
        }

        .attention-note {
            color: #64748b;
            line-height: 1.55;
            margin: 0;
        }

        .attention-card.is-alert {
            background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
            border-color: rgba(249, 115, 22, 0.2);
        }

        .attention-card.is-warning {
            background: linear-gradient(180deg, #fff1f2 0%, #ffffff 100%);
            border-color: rgba(225, 29, 72, 0.16);
        }

        .panel-card {
            padding: 22px;
            display: grid;
            gap: 18px;
        }

        .panel-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .panel-top h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #111827;
        }

        .panel-top p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .panel-count {
            min-width: 44px;
            height: 44px;
            padding: 0 12px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #111827;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
        }

        .workflow-list,
        .flow-list {
            display: grid;
            gap: 12px;
        }

        .workflow-item,
        .flow-item {
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px;
            background: #fcfcfd;
        }

        .workflow-item-top,
        .flow-item-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .workflow-item-name,
        .flow-item-name {
            font-size: 17px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }

        .workflow-item-meta,
        .flow-item-meta {
            color: #64748b;
            font-size: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .workflow-notes,
        .flow-notes {
            margin: 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.5;
        }

        .workflow-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .inline-form {
            margin: 0;
        }

        .btn-workflow,
        .btn-link-workflow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
        }

        .btn-workflow:hover,
        .btn-link-workflow:hover {
            transform: translateY(-1px);
        }

        .btn-workflow.is-confirm {
            background: #111827;
            color: #ffffff;
        }

        .btn-workflow.is-reject {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-link-workflow {
            background: #fff8d6;
            color: #8a5a00;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .status-pill.pending {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-pill.confirmed {
            background: #ecfdf5;
            color: #047857;
        }

        .service-load-card {
            padding: 22px;
            display: grid;
            gap: 16px;
        }

        .load-meta {
            color: #64748b;
            font-size: 14px;
            margin: 0;
        }

        .load-list {
            display: grid;
            gap: 12px;
        }

        .load-row {
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
        }

        .load-time {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
        }

        .load-bar-track {
            width: 100%;
            height: 14px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .load-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #f4b400, #f59e0b);
        }

        .load-bar-fill.is-warning {
            background: linear-gradient(90deg, #ef4444, #e11d48);
        }

        .load-value {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
            white-space: nowrap;
        }

        .load-value.is-warning {
            color: #be123c;
        }

        .flow-column {
            display: grid;
            gap: 12px;
        }

        .flow-column h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .empty-state {
            padding: 20px;
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            text-align: center;
            color: #64748b;
            background: #ffffff;
        }

        .alert {
            margin-bottom: 0;
            border-radius: 16px;
        }

        @media (max-width: 1180px) {
            .attention-grid,
            .workflow-grid,
            .flow-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 991px) {
            .sidebar {
                display: none;
            }

            .page-shell {
                padding: 20px;
            }

            .section-header h1 {
                font-size: 28px;
            }
        }

        @media (max-width: 640px) {
            .load-row {
                grid-template-columns: 1fr;
            }

            .workflow-item-top,
            .flow-item-top,
            .panel-top {
                flex-direction: column;
                align-items: flex-start;
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
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'error' ? 'danger' : $flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <section class="section-block">
                <div class="section-header">
                    <h1>What needs attention</h1>
                    <p>Use these counts to decide what staff should resolve first: unconfirmed demand, confirmed bookings still waiting on tables, and future overlap risk.</p>
                </div>
                <div class="attention-grid">
                    <div class="card-surface attention-card is-alert">
                        <div class="attention-label">Pending Requests</div>
                        <div class="attention-value"><?php echo $pendingRequestsCount; ?></div>
                        <p class="attention-note">Bookings still waiting for staff confirmation.</p>
                    </div>
                    <div class="card-surface attention-card">
                        <div class="attention-label">Unassigned Confirmed</div>
                        <div class="attention-value"><?php echo $unassignedConfirmedCount; ?></div>
                        <p class="attention-note">Confirmed bookings that still need a table assignment.</p>
                    </div>
                    <div class="card-surface attention-card is-warning">
                        <div class="attention-label">Overlapping / Conflicts</div>
                        <div class="attention-value"><?php echo $conflictPairsCount; ?></div>
                        <p class="attention-note">Assigned table overlaps detected across upcoming service windows.</p>
                    </div>
                </div>
            </section>

            <section class="section-block">
                <div class="section-header">
                    <h2>Action Panels</h2>
                    <p>These are the real workflows staff need most: clearing the pending queue, then getting confirmed bookings out of standby and onto tables.</p>
                </div>
                <div class="workflow-grid">
                    <section class="card-surface panel-card">
                        <div class="panel-top">
                            <div>
                                <h3>Pending Queue</h3>
                                <p>Review incoming requests and either confirm them, reject them, or open the day in the timeline to edit details.</p>
                            </div>
                            <span class="panel-count"><?php echo count($pendingQueue); ?></span>
                        </div>

                        <?php if (!empty($pendingQueue)): ?>
                            <div class="workflow-list">
                                <?php foreach ($pendingQueue as $booking): ?>
                                    <article class="workflow-item">
                                        <div class="workflow-item-top">
                                            <div>
                                                <h4 class="workflow-item-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <div class="workflow-item-meta">
                                                    <span><?php echo htmlspecialchars(date('D, j M', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span>P<?php echo (int) $booking['number_of_guests']; ?></span>
                                                </div>
                                            </div>
                                            <span class="status-pill pending">Pending</span>
                                        </div>
                                        <p class="workflow-notes"><?php echo htmlspecialchars(trim((string) ($booking['special_request'] ?? '')) !== '' ? $booking['special_request'] : 'No notes added.', ENT_QUOTES, 'UTF-8'); ?></p>
                                        <div class="workflow-actions">
                                            <form method="POST" class="inline-form">
                                                <input type="hidden" name="action" value="confirm_pending">
                                                <input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>">
                                                <button type="submit" class="btn-workflow is-confirm">Confirm</button>
                                            </form>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('Reject this pending booking?');">
                                                <input type="hidden" name="action" value="reject_pending">
                                                <input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>">
                                                <button type="submit" class="btn-workflow is-reject">Reject</button>
                                            </form>
                                            <a class="btn-link-workflow" href="timeline/new-dashboard.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#bookingList">Edit</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No pending requests are waiting right now.</div>
                        <?php endif; ?>
                    </section>

                    <section class="card-surface panel-card">
                        <div class="panel-top">
                            <div>
                                <h3>Standby</h3>
                                <p>These bookings are confirmed, but they still have no table. Move them into the timeline and assign a table before service catches up.</p>
                            </div>
                            <span class="panel-count"><?php echo count($standbyQueue); ?></span>
                        </div>

                        <?php if (!empty($standbyQueue)): ?>
                            <div class="workflow-list">
                                <?php foreach ($standbyQueue as $booking): ?>
                                    <article class="workflow-item">
                                        <div class="workflow-item-top">
                                            <div>
                                                <h4 class="workflow-item-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <div class="workflow-item-meta">
                                                    <span><?php echo htmlspecialchars(date('D, j M', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span>P<?php echo (int) $booking['number_of_guests']; ?></span>
                                                </div>
                                            </div>
                                            <span class="status-pill confirmed">Confirmed</span>
                                        </div>
                                        <p class="workflow-notes"><?php echo htmlspecialchars(trim((string) ($booking['special_request'] ?? '')) !== '' ? $booking['special_request'] : 'No notes added.', ENT_QUOTES, 'UTF-8'); ?></p>
                                        <div class="workflow-actions">
                                            <a class="btn-link-workflow" href="timeline/new-dashboard.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#timelineGrid">Assign Table</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No confirmed bookings are waiting for a table assignment.</div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>

            <section class="section-block">
                <div class="section-header">
                    <h2>Today's Flow</h2>
                    <p>Keep service grounded in what is active now, what is arriving next, and how full the room is getting across the next booking windows.</p>
                </div>

                <section class="card-surface service-load-card">
                    <div>
                        <h3 class="mb-2">Service Load Bar</h3>
                        <p class="load-meta">Calculated from today's pending and confirmed guest counts against total available seating capacity of <?php echo $todayCapacity; ?> seats.</p>
                    </div>

                    <?php if (!empty($serviceLoadRows)): ?>
                        <div class="load-list">
                            <?php foreach ($serviceLoadRows as $row): ?>
                                <div class="load-row">
                                    <div class="load-time"><?php echo htmlspecialchars($row['time_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="load-bar-track">
                                        <div class="load-bar-fill<?php echo $row['is_warning'] ? ' is-warning' : ''; ?>" style="width: <?php echo (int) $row['width_percent']; ?>%;"></div>
                                    </div>
                                    <div class="load-value<?php echo $row['is_warning'] ? ' is-warning' : ''; ?>"><?php echo (int) $row['percent']; ?>% full<?php echo $row['is_warning'] ? ' ⚠️' : ''; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No service load data is available for the remaining booking windows today.</div>
                    <?php endif; ?>
                </section>

                <div class="flow-grid">
                    <section class="flow-column">
                        <h3>Now</h3>
                        <?php if (!empty($todayFlow['now'])): ?>
                            <div class="flow-list">
                                <?php foreach ($todayFlow['now'] as $booking): ?>
                                    <article class="card-surface flow-item">
                                        <div class="flow-item-top">
                                            <div>
                                                <h4 class="flow-item-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <div class="flow-item-meta">
                                                    <span><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span>P<?php echo (int) $booking['number_of_guests']; ?></span>
                                                    <span><?php echo htmlspecialchars($booking['assigned_table_numbers'] ? 'T' . $booking['assigned_table_numbers'] : 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </div>
                                            <span class="status-pill <?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <p class="flow-notes"><?php echo htmlspecialchars(trim((string) ($booking['special_request'] ?? '')) !== '' ? $booking['special_request'] : 'No notes added.', ENT_QUOTES, 'UTF-8'); ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No bookings are active in the current service window.</div>
                        <?php endif; ?>
                    </section>

                    <section class="flow-column">
                        <h3>Next 1-2 Hours</h3>
                        <?php if (!empty($todayFlow['next'])): ?>
                            <div class="flow-list">
                                <?php foreach ($todayFlow['next'] as $booking): ?>
                                    <article class="card-surface flow-item">
                                        <div class="flow-item-top">
                                            <div>
                                                <h4 class="flow-item-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <div class="flow-item-meta">
                                                    <span><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span>P<?php echo (int) $booking['number_of_guests']; ?></span>
                                                    <span><?php echo htmlspecialchars($booking['assigned_table_numbers'] ? 'T' . $booking['assigned_table_numbers'] : 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </div>
                                            <span class="status-pill <?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <p class="flow-notes"><?php echo htmlspecialchars(trim((string) ($booking['special_request'] ?? '')) !== '' ? $booking['special_request'] : 'No notes added.', ENT_QUOTES, 'UTF-8'); ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No bookings are due in the next two hours.</div>
                        <?php endif; ?>
                    </section>

                    <section class="flow-column">
                        <h3>Later</h3>
                        <?php if (!empty($todayFlow['later'])): ?>
                            <div class="flow-list">
                                <?php foreach ($todayFlow['later'] as $booking): ?>
                                    <article class="card-surface flow-item">
                                        <div class="flow-item-top">
                                            <div>
                                                <h4 class="flow-item-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <div class="flow-item-meta">
                                                    <span><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span>P<?php echo (int) $booking['number_of_guests']; ?></span>
                                                    <span><?php echo htmlspecialchars($booking['assigned_table_numbers'] ? 'T' . $booking['assigned_table_numbers'] : 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </div>
                                            <span class="status-pill <?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($booking['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <p class="flow-notes"><?php echo htmlspecialchars(trim((string) ($booking['special_request'] ?? '')) !== '' ? $booking['special_request'] : 'No notes added.', ENT_QUOTES, 'UTF-8'); ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No later bookings remain for today.</div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        </div>
    </div>
</div>
</body>
</html>