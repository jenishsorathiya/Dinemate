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
            b.booking_source,
            creator.name AS created_by_name,
            COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name
     FROM bookings b
     LEFT JOIN users u ON b.user_id = u.user_id
     LEFT JOIN users creator ON b.created_by_user_id = creator.user_id
     WHERE b.booking_date = CURDATE()
       AND b.status IN ('completed', 'cancelled', 'no_show')
     ORDER BY b.start_time DESC, b.booking_id DESC
     LIMIT 12"
);
$serviceOutcomes = $serviceOutcomesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$historyStatus = strtolower(trim((string) ($_GET['history_status'] ?? 'all')));
$historySearch = trim((string) ($_GET['history_search'] ?? ''));
$historyDateFrom = trim((string) ($_GET['history_from'] ?? ''));
$historyDateTo = trim((string) ($_GET['history_to'] ?? ''));

$allowedHistoryStatuses = ['all', 'completed', 'cancelled', 'no_show', 'pending', 'confirmed'];
if (!in_array($historyStatus, $allowedHistoryStatuses, true)) {
    $historyStatus = 'all';
}

$historyFilters = [
    "(b.booking_date < CURDATE() OR (b.booking_date = CURDATE() AND b.status IN ('completed', 'cancelled', 'no_show')))"
];
$historyParams = [];

if ($historyStatus !== 'all') {
    $historyFilters[] = "b.status = ?";
    $historyParams[] = $historyStatus;
}

if ($historySearch !== '') {
    $historyFilters[] = "COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') LIKE ?";
    $historyParams[] = '%' . $historySearch . '%';
}

if ($historyDateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $historyDateFrom)) {
    $historyFilters[] = "b.booking_date >= ?";
    $historyParams[] = $historyDateFrom;
} else {
    $historyDateFrom = '';
}

if ($historyDateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $historyDateTo)) {
    $historyFilters[] = "b.booking_date <= ?";
    $historyParams[] = $historyDateTo;
} else {
    $historyDateTo = '';
}

$bookingHistorySql = "
    SELECT b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.status,
            b.reservation_card_status,
            b.booking_source,
            creator.name AS created_by_name,
            COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name,
            GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
     FROM bookings b
     LEFT JOIN users u ON b.user_id = u.user_id
     LEFT JOIN users creator ON b.created_by_user_id = creator.user_id
     LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
     LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
     WHERE " . implode("\n       AND ", $historyFilters) . "
     GROUP BY b.booking_id
     ORDER BY b.booking_date DESC, b.start_time DESC, b.booking_id DESC
     LIMIT 50
";
$bookingHistoryStmt = $pdo->prepare($bookingHistorySql);
$bookingHistoryStmt->execute($historyParams);
$bookingHistory = $bookingHistoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
<html lang="en">
<head>
    <?php include __DIR__ . '/admin-head.php'; ?>
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
            background: #ffffff;
            border-left: 3px solid #d4a017;
        }

        .attention-card.is-warning {
            background: #ffffff;
            border-left: 3px solid #c9505d;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 9px;
            border-radius: 4px;
            background: #eef2f7;
            color: #4a5568;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #d9e1ec;
        }

        .queue-shell {
            border-radius: 10px;
            border: 1px solid var(--border-soft);
            background: #ffffff;
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
            color: #9aabbd;
            font-size: 6px;
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
            border-radius: 6px;
            padding: 7px 13px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            transition: background 0.15s ease, opacity 0.15s ease;
        }

        .btn-workflow:hover,
        .btn-link-workflow:hover {
            opacity: 0.85;
        }

        .btn-workflow.is-confirm {
            background: #1f2d4d;
            color: #ffffff;
            border-color: #1f2d4d;
        }

        .btn-workflow.is-reject {
            background: #ffffff;
            border-color: #e0b8bc;
            color: #b03443;
        }

        .btn-link-workflow {
            background: #ffffff;
            border-color: var(--border-strong);
            color: #4a5568;
        }

        .btn-workflow.is-complete {
            background: #ffffff;
            border-color: #b6d9c6;
            color: #1a6845;
        }

        .btn-workflow.is-no-show {
            background: #ffffff;
            border-color: #c5c6e8;
            color: #3d3fa8;
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

        .history-filters {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }

        .history-filter-group {
            display: grid;
            gap: 6px;
        }

        .history-filter-group label {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
        }

        .history-filter-group input,
        .history-filter-group select {
            width: 100%;
            border: 1px solid var(--border-strong);
            border-radius: 12px;
            background: #fff;
            color: var(--text-main);
            padding: 10px 12px;
            font-size: 14px;
        }

        .history-filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-start;
        }

        .history-filter-submit,
        .history-filter-clear {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border-radius: 12px;
            padding: 0 14px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .history-filter-submit {
            border: none;
            background: var(--accent-navy);
            color: #fff;
        }

        .history-filter-clear {
            border: 1px solid var(--border-strong);
            background: #fff;
            color: var(--text-main);
        }

        .alert {
            margin-bottom: 0;
            border-radius: 16px;
        }

        @media (max-width: 1180px) {
            .attention-grid,
            .workflow-grid,
            .history-filters {
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

        /* ===== REDESIGNED LAYOUT ===== */
        .bm-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 4px;
        }
        .bm-page-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: var(--text-main); }
        .bm-page-header p { font-size: 14px; color: var(--text-muted); margin: 0; }
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            overflow: hidden;
            background: var(--border-soft);
            gap: 1px;
        }
        .stat-cell { background: #ffffff; padding: 14px 16px; }
        .stat-cell.is-alert { background: #fffdf5; }
        .stat-cell.is-warning { background: #fff8f8; }
        .stat-num { font-size: 26px; font-weight: 700; line-height: 1; color: var(--text-main); }
        .stat-num.is-alert-num { color: #8a5e00; }
        .stat-num.is-warning-num { color: #b03443; }
        .stat-label { font-size: 11px; font-weight: 500; color: var(--text-muted); margin-top: 5px; }
        .bm-main-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 16px;
            align-items: start;
        }
        .action-panel {
            background: #ffffff;
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            overflow: hidden;
        }
        .queue-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 11px 18px;
            background: #f9fafc;
            border-bottom: 1px solid var(--border-soft);
            border-top: 2px solid var(--border-soft);
        }
        .queue-head:first-child { border-top: none; }
        .queue-head-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin: 0; }
        .queue-count { font-size: 11px; font-weight: 600; color: var(--text-muted); background: #eef2f7; border: 1px solid var(--border-soft); border-radius: 3px; padding: 1px 7px; }
        .bk-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 18px;
            border-bottom: 1px solid var(--border-soft);
        }
        .bk-row:last-child { border-bottom: none; }
        .bk-info { flex: 1; min-width: 0; }
        .bk-name-line { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; }
        .bk-name { font-size: 14px; font-weight: 600; color: var(--text-main); }
        .bk-meta { font-size: 12px; color: var(--text-muted); }
        .bk-request { font-size: 12px; color: #5d6a82; margin-top: 2px; font-style: italic; }
        .bk-actions { display: flex; align-items: center; gap: 5px; flex-shrink: 0; flex-wrap: wrap; }
        .bk-empty { padding: 18px; font-size: 13px; color: var(--text-muted); }
        .side-panel { display: grid; gap: 12px; }
        .side-card { background: #ffffff; border: 1px solid var(--border-soft); border-radius: 12px; padding: 16px 18px; }
        .side-card-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin: 0 0 12px; }
        .today-nums { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
        .today-num-cell { background: #f8fafc; border: 1px solid var(--border-soft); border-radius: 8px; padding: 10px; text-align: center; }
        .today-big-num { font-size: 22px; font-weight: 700; color: var(--text-main); line-height: 1; }
        .today-num-label { font-size: 10px; color: var(--text-muted); margin-top: 3px; }
        .side-link { display: block; font-size: 12px; font-weight: 600; color: #3d558a; text-decoration: none; }
        .side-link:hover { text-decoration: underline; }
        .sl-row { display: grid; grid-template-columns: 68px 1fr 38px; gap: 8px; align-items: center; margin-bottom: 7px; }
        .sl-row:last-child { margin-bottom: 0; }
        .sl-time { font-size: 11px; font-weight: 600; color: var(--text-main); }
        .sl-track { height: 5px; border-radius: 3px; background: #eef2f7; overflow: hidden; }
        .sl-fill { height: 100%; border-radius: 3px; background: #c9a23c; }
        .sl-fill.sl-high { background: #b03443; }
        .sl-pct { font-size: 11px; font-weight: 600; color: var(--text-muted); text-align: right; }
        .sl-pct.sl-high { color: #b03443; }
        .outcome-row { display: flex; align-items: center; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--border-soft); }
        .outcome-row:last-child { border-bottom: none; }
        .outcome-name { font-size: 13px; font-weight: 600; color: var(--text-main); }
        .outcome-meta { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
        .bm-history-card { background: #ffffff; border: 1px solid var(--border-soft); border-radius: 12px; overflow: hidden; }
        .bm-history-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid var(--border-soft); }
        .bm-history-head h2 { font-size: 14px; font-weight: 700; color: var(--text-main); margin: 0; }
        .bm-history-filters { display: flex; gap: 10px; padding: 12px 18px; border-bottom: 1px solid var(--border-soft); flex-wrap: wrap; align-items: flex-end; background: #f9fafc; }
        .bm-filter-group { display: flex; flex-direction: column; gap: 4px; }
        .bm-filter-group label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .bm-filter-group input, .bm-filter-group select { padding: 7px 10px; border: 1px solid var(--border-strong); border-radius: 6px; font-size: 13px; color: var(--text-main); background: #ffffff; min-width: 110px; }
        .bm-filter-actions { display: flex; gap: 8px; align-items: flex-end; }
        .bm-btn-apply { padding: 7px 14px; border-radius: 6px; background: var(--accent-navy); color: #ffffff; font-size: 12px; font-weight: 600; border: none; cursor: pointer; }
        .bm-btn-clear { padding: 7px 12px; border-radius: 6px; background: #ffffff; color: var(--text-muted); font-size: 12px; font-weight: 600; border: 1px solid var(--border-strong); text-decoration: none; display: inline-flex; align-items: center; }
        .bm-table { width: 100%; border-collapse: collapse; }
        .bm-table thead th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); padding: 10px 18px; text-align: left; border-bottom: 1px solid var(--border-soft); background: #f9fafc; white-space: nowrap; }
        .bm-table tbody tr { border-bottom: 1px solid var(--border-soft); }
        .bm-table tbody tr:last-child { border-bottom: none; }
        .bm-table tbody tr:hover { background: #f6f8fd; }
        .bm-table tbody td { font-size: 13px; color: var(--text-main); padding: 10px 18px; vertical-align: middle; }
        .bm-table .td-muted { color: var(--text-muted); }
        .bm-table .td-bold { font-weight: 600; }
        .bm-empty { padding: 20px 18px; font-size: 13px; color: var(--text-muted); }
        @media (max-width: 1100px) {
            .bm-main-grid { grid-template-columns: 1fr; }
            .stats-strip { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 640px) {
            .stats-strip { grid-template-columns: repeat(2, 1fr); }
            .bk-row { flex-direction: column; align-items: flex-start; }
            .bm-page-header { flex-direction: column; }
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

            <div class="bm-page-header">
                <div>
                    <h1>Bookings</h1>
                    <p>Review requests, assign tables, and record service outcomes.</p>
                </div>
                <a href="timeline/new-dashboard.php" class="btn-workflow is-confirm">Open Timeline</a>
            </div>

            <div class="stats-strip">
                <div class="stat-cell <?php echo $pendingRequestsCount > 0 ? 'is-alert' : ''; ?>">
                    <div class="stat-num <?php echo $pendingRequestsCount > 0 ? 'is-alert-num' : ''; ?>"><?php echo $pendingRequestsCount; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-cell">
                    <div class="stat-num"><?php echo $unassignedConfirmedCount; ?></div>
                    <div class="stat-label">Unassigned</div>
                </div>
                <div class="stat-cell <?php echo $conflictPairsCount > 0 ? 'is-warning' : ''; ?>">
                    <div class="stat-num <?php echo $conflictPairsCount > 0 ? 'is-warning-num' : ''; ?>"><?php echo $conflictPairsCount; ?></div>
                    <div class="stat-label">Conflicts</div>
                </div>
                <div class="stat-cell">
                    <div class="stat-num"><?php echo $notPlacedCount; ?></div>
                    <div class="stat-label">Not Placed</div>
                </div>
                <div class="stat-cell">
                    <div class="stat-num"><?php echo $completedTodayCount; ?></div>
                    <div class="stat-label">Completed Today</div>
                </div>
                <div class="stat-cell <?php echo $noShowTodayCount > 0 ? 'is-warning' : ''; ?>">
                    <div class="stat-num <?php echo $noShowTodayCount > 0 ? 'is-warning-num' : ''; ?>"><?php echo $noShowTodayCount; ?></div>
                    <div class="stat-label">No-shows Today</div>
                </div>
            </div>

            <div class="bm-main-grid">
                <!-- Left: Action queues -->
                <div class="action-panel">
                    <div class="queue-head">
                        <h2 class="queue-head-title">Pending Review</h2>
                        <span class="queue-count"><?php echo count($pendingQueue); ?></span>
                    </div>
                    <?php if (!empty($pendingQueue)): ?>
                        <?php foreach ($pendingQueue as $booking): ?>
                        <div class="bk-row">
                            <div class="bk-info">
                                <div class="bk-name-line">
                                    <span class="bk-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="status-tag pending">Pending</span>
                                </div>
                                <div class="bk-meta">
                                    <?php echo htmlspecialchars(date('D j M', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?>
                                    &middot;
                                    <?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' – ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?>
                                    &middot;
                                    <?php echo (int) $booking['number_of_guests']; ?> guests
                                </div>
                                <?php if (trim((string) ($booking['special_request'] ?? '')) !== ''): ?>
                                    <div class="bk-request">"<?php echo htmlspecialchars($booking['special_request'], ENT_QUOTES, 'UTF-8'); ?>"</div>
                                <?php endif; ?>
                            </div>
                            <div class="bk-actions">
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
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bk-empty">No pending bookings.</div>
                    <?php endif; ?>

                    <div class="queue-head">
                        <h2 class="queue-head-title">Confirmed &mdash; Awaiting Table</h2>
                        <span class="queue-count"><?php echo count($standbyQueue); ?></span>
                    </div>
                    <?php if (!empty($standbyQueue)): ?>
                        <?php foreach ($standbyQueue as $booking): ?>
                        <div class="bk-row">
                            <div class="bk-info">
                                <div class="bk-name-line">
                                    <span class="bk-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="status-tag confirmed">Confirmed</span>
                                </div>
                                <div class="bk-meta">
                                    <?php echo htmlspecialchars(date('D j M', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?>
                                    &middot;
                                    <?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' – ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?>
                                    &middot;
                                    <?php echo (int) $booking['number_of_guests']; ?> guests
                                </div>
                                <?php if (trim((string) ($booking['special_request'] ?? '')) !== ''): ?>
                                    <div class="bk-request">"<?php echo htmlspecialchars($booking['special_request'], ENT_QUOTES, 'UTF-8'); ?>"</div>
                                <?php endif; ?>
                            </div>
                            <div class="bk-actions">
                                <a class="btn-workflow is-confirm" href="timeline/new-dashboard.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#timelineGrid">Assign Table</a>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Mark this booking as completed?');">
                                    <input type="hidden" name="action" value="mark_completed">
                                    <input type="hidden" name="booking_id" value="<?php echo (int) $booking['booking_id']; ?>">
                                    <button type="submit" class="btn-workflow is-complete">Done</button>
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
                                <a class="btn-link-workflow" href="timeline/new-dashboard.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#bookingList">View</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="bk-empty">All confirmed bookings have a table assigned.</div>
                    <?php endif; ?>
                </div>

                <!-- Right: Context sidebar -->
                <div class="side-panel">
                    <div class="side-card">
                        <div class="side-card-title">Today</div>
                        <div class="today-nums">
                            <div class="today-num-cell">
                                <div class="today-big-num"><?php echo count($todayBookings); ?></div>
                                <div class="today-num-label">Active</div>
                            </div>
                            <div class="today-num-cell">
                                <div class="today-big-num"><?php echo $todayCapacity; ?></div>
                                <div class="today-num-label">Seats</div>
                            </div>
                        </div>
                        <a class="side-link" href="timeline/new-dashboard.php">Open Timeline &rarr;</a>
                    </div>

                    <?php if (!empty($serviceLoadRows)): ?>
                    <div class="side-card">
                        <div class="side-card-title">Capacity Forecast</div>
                        <?php foreach ($serviceLoadRows as $row): ?>
                        <div class="sl-row">
                            <div class="sl-time"><?php echo htmlspecialchars($row['time_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="sl-track">
                                <div class="sl-fill<?php echo $row['is_warning'] ? ' sl-high' : ''; ?>" style="width: <?php echo (int) $row['width_percent']; ?>%;"></div>
                            </div>
                            <div class="sl-pct<?php echo $row['is_warning'] ? ' sl-high' : ''; ?>"><?php echo (int) $row['percent']; ?>%</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($serviceOutcomes)): ?>
                    <div class="side-card">
                        <div class="side-card-title">Today's Outcomes</div>
                        <?php foreach ($serviceOutcomes as $booking): ?>
                        <div class="outcome-row">
                            <div>
                                <div class="outcome-name"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="outcome-meta"><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?> &middot; <?php echo (int) $booking['number_of_guests']; ?>p</div>
                            </div>
                            <span class="status-tag <?php echo htmlspecialchars((string) $booking['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(getBookingStatusLabel($booking['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bm-history-card">
                <div class="bm-history-head">
                    <h2>Booking History</h2>
                    <span class="queue-count"><?php echo count($bookingHistory); ?> results</span>
                </div>
                <div class="bm-history-filters">
                    <form method="GET" style="display: contents;">
                        <div class="bm-filter-group">
                            <label for="historyStatus">Status</label>
                            <select id="historyStatus" name="history_status">
                                <option value="all"<?php echo $historyStatus === 'all' ? ' selected' : ''; ?>>All</option>
                                <option value="completed"<?php echo $historyStatus === 'completed' ? ' selected' : ''; ?>>Completed</option>
                                <option value="cancelled"<?php echo $historyStatus === 'cancelled' ? ' selected' : ''; ?>>Cancelled</option>
                                <option value="no_show"<?php echo $historyStatus === 'no_show' ? ' selected' : ''; ?>>No-show</option>
                                <option value="confirmed"<?php echo $historyStatus === 'confirmed' ? ' selected' : ''; ?>>Confirmed</option>
                                <option value="pending"<?php echo $historyStatus === 'pending' ? ' selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="bm-filter-group">
                            <label for="historySearch">Customer</label>
                            <input type="text" id="historySearch" name="history_search" placeholder="Name…" value="<?php echo htmlspecialchars($historySearch, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="bm-filter-group">
                            <label for="historyFrom">From</label>
                            <input type="date" id="historyFrom" name="history_from" value="<?php echo htmlspecialchars($historyDateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="bm-filter-group">
                            <label for="historyTo">To</label>
                            <input type="date" id="historyTo" name="history_to" value="<?php echo htmlspecialchars($historyDateTo, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="bm-filter-actions">
                            <button type="submit" class="bm-btn-apply">Apply</button>
                            <a href="bookings-management.php" class="bm-btn-clear">Clear</a>
                        </div>
                    </form>
                </div>
                <?php if (!empty($bookingHistory)): ?>
                <table class="bm-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Guests</th>
                            <th>Table</th>
                            <th>Source</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookingHistory as $booking): ?>
                        <?php
                        $assignedTableSummary = trim((string) ($booking['assigned_table_numbers'] ?? '')) !== ''
                            ? 'T' . $booking['assigned_table_numbers']
                            : '—';
                        $sourceSummary = getBookingSourceLabel($booking['booking_source'] ?? '');
                        if (($booking['booking_source'] ?? '') === 'admin_manual' && !empty($booking['created_by_name'])) {
                            $sourceSummary .= ' – ' . $booking['created_by_name'];
                        }
                        ?>
                        <tr>
                            <td class="td-bold"><?php echo htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="td-muted"><?php echo htmlspecialchars(date('D j M Y', strtotime($booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="td-muted"><?php echo htmlspecialchars(date('g:i A', strtotime($booking['start_time'])) . ' – ' . date('g:i A', strtotime($booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="td-muted"><?php echo (int) $booking['number_of_guests']; ?></td>
                            <td class="td-muted"><?php echo htmlspecialchars($assignedTableSummary, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="td-muted"><?php echo htmlspecialchars($sourceSummary, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="status-tag <?php echo htmlspecialchars((string) $booking['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(getBookingStatusLabel($booking['status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="bm-empty">No history matches the current filters.</div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>
