<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

requireAdmin();
requireValidCsrfToken('admin_actions', ['redirect' => appPath('admin/pages/admin_home.php')]);
ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureInboxMessagesTable($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(appPath('admin/pages/admin_home.php'));
}

$redirectInput = trim((string) ($_POST['redirect_url'] ?? 'admin_home.php'));
if (!preg_match('/^[a-z0-9_\-]+\.php(?:\?[A-Za-z0-9_\-=&%.]+)?$/', $redirectInput)) {
    $redirectInput = 'admin_home.php';
}
$redirectUrl = '../pages/' . $redirectInput;

$bookingId = (int) ($_POST['booking_id'] ?? 0);
if ($bookingId < 1) {
    $_SESSION['admin_home_flash'] = 'Invalid booking.';
    header('Location: ' . $redirectUrl);
    exit;
}

$selectedTableIds = [];
if (isset($_POST['table_ids']) && is_array($_POST['table_ids'])) {
    foreach ($_POST['table_ids'] as $postedTableId) {
        $postedTableId = (int) $postedTableId;
        if ($postedTableId > 0 && !in_array($postedTableId, $selectedTableIds, true)) {
            $selectedTableIds[] = $postedTableId;
        }
    }
}

if (empty($selectedTableIds)) {
    $fallbackTableId = (int) ($_POST['table_id'] ?? 0);
    if ($fallbackTableId > 0) {
        $selectedTableIds[] = $fallbackTableId;
    }
}

try {
    $bookingStmt = $pdo->prepare("
        SELECT booking_id,
               booking_date,
               COALESCE(start_time, requested_start_time, '18:00:00') AS start_time,
               COALESCE(end_time, requested_end_time, '20:00:00') AS end_time,
               status
        FROM bookings
        WHERE booking_id = ?
        LIMIT 1
    ");
    $bookingStmt->execute([$bookingId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        $_SESSION['admin_home_flash'] = 'Booking not found.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    if (strtolower((string) ($booking['status'] ?? '')) === 'cancelled') {
        $_SESSION['admin_home_flash'] = 'Cancelled bookings cannot be assigned to tables.';
        header('Location: ' . $redirectUrl);
        exit;
    }

    $pdo->beginTransaction();

    if (!empty($selectedTableIds)) {
        $tablePlaceholders = implode(',', array_fill(0, count($selectedTableIds), '?'));
        $tableStmt = $pdo->prepare("
            SELECT table_id, table_number
            FROM restaurant_tables
            WHERE table_id IN ($tablePlaceholders)
              AND COALESCE(reservable, 1) = 1
              AND LOWER(COALESCE(status, 'available')) NOT IN ('inactive', 'disabled')
        ");
        $tableStmt->execute($selectedTableIds);
        $tables = $tableStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (count($tables) !== count($selectedTableIds)) {
            $pdo->rollBack();
            $_SESSION['admin_home_flash'] = 'One or more selected tables are not available for assignment.';
            header('Location: ' . $redirectUrl);
            exit;
        }

        $conflictStmt = $pdo->prepare("
            SELECT COUNT(*) AS conflict_count
            FROM booking_table_assignments bta
            INNER JOIN bookings b ON b.booking_id = bta.booking_id
            WHERE bta.table_id = ?
              AND b.booking_date = ?
              AND b.booking_id != ?
              AND b.status IN ('pending', 'confirmed')
              AND b.start_time < ?
              AND b.end_time > ?
        ");

        foreach ($selectedTableIds as $selectedTableId) {
            $conflictStmt->execute([
                $selectedTableId,
                (string) ($booking['booking_date'] ?? date('Y-m-d')),
                $bookingId,
                (string) $booking['end_time'],
                (string) $booking['start_time'],
            ]);

            if ((int) $conflictStmt->fetchColumn() > 0) {
                $pdo->rollBack();
                $_SESSION['admin_home_flash'] = 'One or more selected tables are already assigned at this time.';
                header('Location: ' . $redirectUrl);
                exit;
            }
        }
    }

    syncBookingTableAssignments($pdo, $bookingId, $selectedTableIds);

    if (!empty($selectedTableIds)) {
        $placementStmt = $pdo->prepare("
            UPDATE bookings
            SET reservation_card_status = CASE
                WHEN status IN ('pending', 'confirmed') THEN COALESCE(reservation_card_status, 'not_placed')
                ELSE reservation_card_status
            END
            WHERE booking_id = ?
        ");
        $placementStmt->execute([$bookingId]);
    } else {
        $placementStmt = $pdo->prepare("UPDATE bookings SET reservation_card_status = NULL WHERE booking_id = ?");
        $placementStmt->execute([$bookingId]);
    }

    normalizeInboxFolders($pdo);
    $pdo->commit();

    $_SESSION['admin_home_flash'] = !empty($selectedTableIds)
        ? (count($selectedTableIds) > 1 ? 'Tables assigned.' : 'Table assigned.')
        : 'Table assignment cleared.';
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Home table assignment error: ' . $error->getMessage());
    $_SESSION['admin_home_flash'] = 'Could not update table assignment.';
}

header('Location: ' . $redirectUrl);
exit;
