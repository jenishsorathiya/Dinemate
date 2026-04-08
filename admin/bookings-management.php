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
                $stmt = $pdo->prepare("
                    UPDATE bookings b
                    LEFT JOIN (
                        SELECT DISTINCT booking_id
                        FROM booking_table_assignments
                    ) assigned ON assigned.booking_id = b.booking_id
                    SET b.status = 'confirmed',
                        b.reservation_card_status = CASE
                            WHEN b.table_id IS NOT NULL OR assigned.booking_id IS NOT NULL THEN 'not_placed'
                            ELSE NULL
                        END
                    WHERE b.booking_id = ? AND b.status = 'pending'
                ");
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

                $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', table_id = NULL, reservation_card_status = NULL WHERE booking_id = ? AND status = 'pending'");
                $updateStmt->execute([$bookingId]);

                $pdo->commit();

                if ($updateStmt->rowCount() > 0) {
                    setFlashMessage('success', 'Pending booking rejected.');
                } else {
                    setFlashMessage('warning', 'That booking could not be rejected.');
                }
            } elseif (in_array($action, ['mark_completed', 'mark_no_show', 'cancel_confirmed'], true)) {
                $pdo->beginTransaction();

                if ($action === 'cancel_confirmed') {
                    $deleteAssignmentsStmt = $pdo->prepare("DELETE FROM booking_table_assignments WHERE booking_id = ?");
                    $deleteAssignmentsStmt->execute([$bookingId]);

                    $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', table_id = NULL, reservation_card_status = NULL WHERE booking_id = ? AND status = 'confirmed'");
                    $updateStmt->execute([$bookingId]);
                    $successMessage = 'Confirmed booking cancelled.';
                    $warningMessage = 'That confirmed booking could not be cancelled.';
                } elseif ($action === 'mark_completed') {
                    $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE booking_id = ? AND status = 'confirmed'");
                    $updateStmt->execute([$bookingId]);
                    $successMessage = 'Booking marked as completed.';
                    $warningMessage = 'That booking could not be marked as completed.';
                } else {
                    $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'no_show' WHERE booking_id = ? AND status = 'confirmed'");
                    $updateStmt->execute([$bookingId]);
                    $successMessage = 'Booking marked as no-show.';
                    $warningMessage = 'That booking could not be marked as no-show.';
                }

                $pdo->commit();

                if ($updateStmt->rowCount() > 0) {
                    setFlashMessage('success', $successMessage);
                } else {
                    setFlashMessage('warning', $warningMessage);
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

$completedTodayCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM bookings
     WHERE status = 'completed'
       AND booking_date = CURDATE()"
)->fetchColumn();

$noShowTodayCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM bookings
     WHERE status = 'no_show'
       AND booking_date = CURDATE()"
)->fetchColumn();

$notPlacedCount = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM (
        SELECT b.booking_id
        FROM bookings b
        LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
        WHERE b.booking_date >= CURDATE()
          AND b.status IN ('pending', 'confirmed')
          AND b.reservation_card_status = 'not_placed'
        GROUP BY b.booking_id
        HAVING MAX(COALESCE(bta.table_id, b.table_id)) IS NOT NULL
     ) not_placed_bookings"
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

$serviceOutcomesStmt = $pdo->query(
    "SELECT b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.status,
            COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name
     FROM bookings b
     LEFT JOIN users u ON b.user_id = u.user_id
     WHERE b.booking_date = CURDATE()
       AND b.status IN ('completed', 'cancelled', 'no_show')
     ORDER BY b.start_time DESC, b.booking_id DESC
     LIMIT 12"
);
$serviceOutcomes = $serviceOutcomesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$todayCapacity = (int) $pdo->query("SELECT COALESCE(SUM(capacity), 0) FROM restaurant_tables")->fetchColumn();

$todayDate = date('Y-m-d');
$nowTimestamp = time();

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
$adminSidebarActive = 'bookings';
$adminSidebarPathPrefix = '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Bookings Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard-theme.css" rel="stylesheet">
    <style>
        :root {
            --page-bg: #f6f8fc;
            --surface: rgba(255, 255, 255, 0.94);
            --surface-strong: #ffffff;
            --surface-muted: #f8fafc;
            --border-soft: #e6ebf4;
            --border-strong: #d9e1ee;
            --text-main: #1b2640;
            --text-muted: #63708a;
            --shadow-soft: 0 18px 38px rgba(52, 72, 105, 0.10);
            --shadow-card: 0 10px 24px rgba(52, 72, 105, 0.08);
            --accent-gold: #f6b100;
            --accent-gold-soft: #fff3cf;
            --accent-red: #f15b67;
            --accent-red-soft: #ffe7ea;
            --accent-green: #1f9d74;
            --accent-green-soft: #dff7ee;
            --accent-navy: #1f2d4d;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--page-bg);
            color: var(--text-main);
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
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
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            max-width: 1320px;
            width: 100%;
            margin: 0 auto;
        }

        .section-block {
            display: grid;
            gap: 16px;
        }

        .section-header h1,
        .section-header h2 {
            margin: 0;
            color: var(--text-main);
            font-weight: 700;
        }

        .section-header h1 {
            font-size: 22px;
            line-height: 1.15;
        }

        .section-header h2 {
            font-size: 20px;
        }

        .section-header p {
            margin: 8px 0 0;
            color: var(--text-muted);
            line-height: 1.55;
            max-width: 780px;
            font-size: 15px;
        }

        .hero-card {
            padding: 6px 0 2px;
        }

        .attention-grid,
        .workflow-grid {
            display: grid;
            gap: 16px;
        }

        .attention-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .workflow-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .workflow-grid {
            align-items: start;
        }

        .card-surface {
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: 18px;
            box-shadow: var(--shadow-soft);
        }

        .attention-card {
            padding: 16px 18px;
            min-height: 108px;
        }

        .attention-label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .attention-value {
            font-size: 18px;
            line-height: 1;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .attention-note {
            color: var(--text-muted);
            line-height: 1.5;
            margin: 0;
            font-size: 13px;
        }

        .attention-card.is-alert {
            background: linear-gradient(180deg, rgba(255, 247, 231, 0.95) 0%, rgba(255, 255, 255, 0.97) 100%);
        }

        .attention-card.is-warning {
            background: linear-gradient(180deg, rgba(255, 242, 244, 0.94) 0%, rgba(255, 255, 255, 0.97) 100%);
        }

        .panel-card {
            padding: 14px;
            display: grid;
            gap: 14px;
        }

        .panel-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 2px 2px 0;
        }

        .panel-top h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .panel-tools {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .panel-filter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 14px;
            border: 1px solid var(--border-soft);
            background: rgba(255, 255, 255, 0.88);
            padding: 8px 12px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .panel-filter .dots {
            display: inline-flex;
            gap: 4px;
            align-items: center;
        }

        .panel-filter .dots span {
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #ffc130;
            display: block;
        }

        .panel-count {
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-navy);
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06);
        }

        .queue-shell {
            border-radius: 18px;
            border: 1px solid var(--border-soft);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 255, 0.94));
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .workflow-list {
            display: grid;
            gap: 0;
        }

        .workflow-item {
            padding: 16px 18px;
            background: var(--surface-strong);
            display: grid;
            gap: 14px;
        }

        .workflow-item + .workflow-item {
            border-top: 1px solid var(--border-soft);
        }

        .workflow-main {
            min-width: 0;
        }

        .workflow-item-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .workflow-item-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .workflow-item-meta {
            color: var(--text-muted);
            font-size: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .workflow-meta-accent {
            color: #5d6a85;
            font-weight: 600;
        }

        .workflow-notes {
            margin: 0;
            color: #56627c;
            font-size: 12px;
            line-height: 1.45;
        }

        .workflow-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .workflow-hint {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .workflow-hint i {
            color: var(--accent-gold);
        }

        .workflow-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 0;
            justify-content: flex-end;
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
            padding: 9px 14px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid transparent;
            transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
        }

        .btn-workflow:hover,
        .btn-link-workflow:hover {
            transform: translateY(-1px);
        }

        .btn-workflow.is-confirm {
            background: var(--accent-navy);
            color: #ffffff;
        }

        .btn-workflow.is-reject {
            background: var(--accent-red-soft);
            border-color: #ffd1d7;
            color: #cc4157;
        }

        .btn-link-workflow {
            background: #f9fbff;
            border-color: var(--border-soft);
            color: #50607c;
        }

        .btn-workflow.is-complete {
            background: #dff7ee;
            border-color: #c4eddc;
            color: #177755;
        }

        .btn-workflow.is-no-show {
            background: #eef2ff;
            border-color: #d9deff;
            color: #4338ca;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .status-pill.pending {
            background: var(--accent-gold-soft);
            color: #976800;
        }

        .status-pill.confirmed {
            background: var(--accent-green-soft);
            color: #177755;
        }

        .status-pill.completed {
            background: #e1f7ef;
            color: #13674c;
        }

        .status-pill.cancelled {
            background: var(--accent-red-soft);
            color: #cc4157;
        }

        .status-pill.no-show {
            background: #eef2ff;
            color: #4338ca;
        }

        .service-load-card {
            padding: 18px 18px 20px;
            display: grid;
            gap: 14px;
        }

        .service-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .service-top h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .service-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 14px;
            border: 1px solid var(--border-soft);
            background: rgba(255, 255, 255, 0.86);
            padding: 9px 13px;
            color: #4f5f7b;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        .load-meta {
            color: var(--text-muted);
            font-size: 13px;
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
            color: var(--text-main);
        }

        .load-bar-track {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: #e7edf7;
            overflow: hidden;
        }

        .load-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #ffb000, #ffc73a);
        }

        .load-bar-fill.is-warning {
            background: linear-gradient(90deg, #f15b67, #df3d5d);
        }

        .load-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
        }

        .load-value.is-warning {
            color: #c13d56;
        }

        .empty-state {
            padding: 16px 18px;
            border: 1px dashed var(--border-strong);
            border-radius: 18px;
            text-align: left;
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        .alert {
            margin-bottom: 0;
            border-radius: 16px;
        }

        @media (max-width: 1180px) {
            .attention-grid,
            .workflow-grid {
                grid-template-columns: 1fr;
            }

            .workflow-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 991px) {
            .sidebar {
                display: none;
            }

            .page-shell {
                padding: 16px;
            }

            .section-header h1 {
                font-size: 20px;
            }
        }

        @media (max-width: 640px) {
            .load-row {
                grid-template-columns: 1fr;
            }

            .workflow-footer,
            .service-top,
            .workflow-item-top,
            .panel-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .panel-tools {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/admin-sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/admin-topbar.php'; ?>

        <div class="page-shell">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'error' ? 'danger' : $flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <section class="section-block">
                <div class="hero-card">
                    <div class="section-header">
                        <h1>Bookings Management</h1>
                        <p>Manage pending requests, assign tables, and record real service outcomes like completed visits, cancellations, and no-shows.</p>
                    </div>
                </div>

                <div class="attention-grid">
                    <div class="card-surface attention-card is-alert">
                        <div class="attention-label">Pending Requests</div>
                        <div class="attention-value"><?php echo $pendingRequestsCount; ?></div>
                        <p class="attention-note">Bookings waiting for a confirmation decision.</p>
                    </div>
                    <div class="card-surface attention-card">
                        <div class="attention-label">Unassigned Confirmed</div>
                        <div class="attention-value"><?php echo $unassignedConfirmedCount; ?></div>
                        <p class="attention-note">Confirmed guests that still need a table.</p>
                    </div>
                    <div class="card-surface attention-card is-warning">
                        <div class="attention-label">Overlapping / Conflicts</div>
                        <div class="attention-value"><?php echo $conflictPairsCount; ?></div>
                        <p class="attention-note">Potential clashes in table assignments today.</p>
                    </div>
                    <div class="card-surface attention-card">
                        <div class="attention-label">Not Placed</div>
                        <div class="attention-value"><?php echo $notPlacedCount; ?></div>
                        <p class="attention-note">Assigned bookings that still need a reservation card placed on the table.</p>
                    </div>
                    <div class="card-surface attention-card">
                        <div class="attention-label">Completed Today</div>
                        <div class="attention-value"><?php echo $completedTodayCount; ?></div>
                        <p class="attention-note">Confirmed bookings marked as attended and completed.</p>
                    </div>
                    <div class="card-surface attention-card is-warning">
                        <div class="attention-label">No-shows Today</div>
                        <div class="attention-value"><?php echo $noShowTodayCount; ?></div>
                        <p class="attention-note">Confirmed bookings marked as no-show.</p>
                    </div>
                </div>
            </section>

            <section class="section-block">
                <div class="workflow-grid">
                    <section class="card-surface panel-card">
                        <div class="panel-top">
                            <h3>Pending Queue</h3>
                            <div class="panel-tools">
                               
                                <span class="panel-count"><?php echo count($pendingQueue); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($pendingQueue)): ?>
                            <div class="queue-shell">
                                <div class="workflow-list">
                                <?php foreach ($pendingQueue as $booking): ?>
                                    <article class="workflow-item">
                                        <div class="workflow-main">
                                            <div class="workflow-item-top">
                                                <h4 class="workflow-item-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <span class="status-pill pending">Pending</span>
                                            </div>
                                            <div class="workflow-item-meta">
                                                <span><?php echo htmlspecialchars(date('D, j M', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="workflow-meta-accent">P<?php echo (int) $booking['number_of_guests']; ?></span>
                                            </div>
                                        </div>
                                        <div class="workflow-footer">
                                            <div>
                                                <div class="workflow-hint"><i class="fa-solid fa-circle"></i><span>Needs review</span></div>
                                                <p class="workflow-notes"><?php echo htmlspecialchars(trim((string) ($booking['special_request'] ?? '')) !== '' ? $booking['special_request'] : 'No notes added.', ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
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
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">All bookings confirmed. No pending bookings right now.</div>
                        <?php endif; ?>
                    </section>

                    <section class="card-surface panel-card">
                        <div class="panel-top">
                            <h3>Standby Queue</h3>
                            <div class="panel-tools">
                                
                                <span class="panel-count"><?php echo count($standbyQueue); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($standbyQueue)): ?>
                            <div class="queue-shell">
                                <div class="workflow-list">
                                <?php foreach ($standbyQueue as $booking): ?>
                                    <article class="workflow-item">
                                        <div class="workflow-main">
                                            <div class="workflow-item-top">
                                                <h4 class="workflow-item-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <span class="status-pill confirmed">Confirmed</span>
                                            </div>
                                            <div class="workflow-item-meta">
                                                <span><?php echo htmlspecialchars(date('D, j M', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="workflow-meta-accent">P<?php echo (int) $booking['number_of_guests']; ?></span>
                                            </div>
                                        </div>
                                        <div class="workflow-footer">
                                            <div>
                                                <div class="workflow-hint"><i class="fa-solid fa-circle"></i><span>Waiting for assignment</span></div>
                                                <p class="workflow-notes"><?php echo htmlspecialchars(trim((string) ($booking['special_request'] ?? '')) !== '' ? $booking['special_request'] : 'No notes added.', ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                            <div class="workflow-actions">
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Mark this booking as completed?');">
                                                    <input type="hidden" name="action" value="mark_completed">
                                                    <input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>">
                                                    <button type="submit" class="btn-workflow is-complete">Completed</button>
                                                </form>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Mark this booking as a no-show?');">
                                                    <input type="hidden" name="action" value="mark_no_show">
                                                    <input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>">
                                                    <button type="submit" class="btn-workflow is-no-show">No-show</button>
                                                </form>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Cancel this confirmed booking?');">
                                                    <input type="hidden" name="action" value="cancel_confirmed">
                                                    <input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>">
                                                    <button type="submit" class="btn-workflow is-reject">Cancel</button>
                                                </form>
                                                <a class="btn-workflow is-confirm" href="timeline/new-dashboard.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#timelineGrid">Assign Table</a>
                                                <a class="btn-link-workflow" href="timeline/new-dashboard.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#bookingList">View</a>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">All bookings have been assigned.</div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>

            <section class="section-block">
                <section class="card-surface panel-card">
                    <div class="panel-top">
                        <h3>Today's Outcomes</h3>
                        <div class="panel-tools">
                            <span class="panel-count"><?php echo count($serviceOutcomes); ?></span>
                        </div>
                    </div>

                    <?php if (!empty($serviceOutcomes)): ?>
                        <div class="queue-shell">
                            <div class="workflow-list">
                                <?php foreach ($serviceOutcomes as $booking): ?>
                                    <article class="workflow-item">
                                        <div class="workflow-main">
                                            <div class="workflow-item-top">
                                                <h4 class="workflow-item-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <span class="status-pill <?php echo htmlspecialchars(str_replace('_', '-', (string) $booking['status']), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars(getBookingStatusLabel($booking['status']), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </div>
                                            <div class="workflow-item-meta">
                                                <span><?php echo htmlspecialchars(date('D, j M', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="workflow-meta-accent">P<?php echo (int) $booking['number_of_guests']; ?></span>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">No completed, cancelled, or no-show outcomes recorded for today yet.</div>
                    <?php endif; ?>
                </section>
            </section>

            <section class="section-block">
                <section class="card-surface service-load-card">
                    <div class="service-top">
                        <div>
                            <h3>Service Load Bar</h3>
                            <p class="load-meta">Calculated from today's pending and confirmed guest counts against total available seating capacity of <?php echo $todayCapacity; ?> seats.</p>
                        </div>
                        <a class="service-link" href="timeline/new-dashboard.php#timelineGrid">Jump to Timeline Grid <i class="fa-solid fa-chevron-right"></i></a>
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
            </section>
        </div>
    </div>
</div>
</body>
</html>
