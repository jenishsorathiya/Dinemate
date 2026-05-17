<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
requireValidCsrfToken('admin_actions', ['redirect' => appPath('admin/pages/admin_inbox.php')]);
ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);
ensureInboxMessagesTable($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_inbox.php');
    exit;
}

$action   = strtolower(trim((string) ($_POST['action'] ?? '')));
$inboxId  = (int) ($_POST['inbox_id'] ?? 0);
$folder   = strtolower(trim((string) ($_POST['folder'] ?? 'requests')));
$allowedFolders = ['requests', 'unassigned', 'waitlist'];
if (!in_array($folder, $allowedFolders, true)) {
    $folder = 'requests';
}

if ($inboxId <= 0) {
    $_SESSION['inbox_flash'] = 'Invalid message.';
    header('Location: admin_inbox.php?folder=' . urlencode($folder));
    exit;
}

$msgStmt = $pdo->prepare("SELECT * FROM inbox_messages WHERE inbox_id = ? LIMIT 1");
$msgStmt->execute([$inboxId]);
$message = $msgStmt->fetch(PDO::FETCH_ASSOC);

if (!$message) {
    $_SESSION['inbox_flash'] = 'Message not found.';
    header('Location: admin_inbox.php?folder=' . urlencode($folder));
    exit;
}

$bookingId = !empty($message['booking_id']) ? (int) $message['booking_id'] : 0;
$redirectUrl = 'admin_inbox.php?folder=' . urlencode($folder);
$flash = '';

try {
    $pdo->beginTransaction();

    switch ($action) {
        case 'confirm':
            if ($bookingId > 0) {
                $update = $pdo->prepare("
                    UPDATE bookings
                    SET status = 'confirmed',
                        start_time = COALESCE(requested_start_time, start_time),
                        end_time   = COALESCE(requested_end_time, end_time),
                        requested_start_time = NULL,
                        requested_end_time = NULL
                    WHERE booking_id = ?
                ");
                $update->execute([$bookingId]);
            }

            $hasAssignedTable = false;
            if ($bookingId > 0) {
                $assignmentStmt = $pdo->prepare("
                    SELECT
                        CASE
                            WHEN b.table_id IS NOT NULL THEN 1
                            WHEN EXISTS (
                                SELECT 1
                                FROM booking_table_assignments bta
                                WHERE bta.booking_id = b.booking_id
                            ) THEN 1
                            ELSE 0
                        END AS has_assigned_table
                    FROM bookings b
                    WHERE b.booking_id = ?
                    LIMIT 1
                ");
                $assignmentStmt->execute([$bookingId]);
                $hasAssignedTable = (int) $assignmentStmt->fetchColumn() === 1;
            }

            if ($bookingId > 0 && !$hasAssignedTable) {
                $stmt = $pdo->prepare("
                    UPDATE inbox_messages
                    SET status = 'waiting', folder = 'unassigned', last_action_at = NOW()
                    WHERE inbox_id = ?
                ");
                $stmt->execute([$inboxId]);
                $folder = 'unassigned';
                $redirectUrl = 'admin_inbox.php?folder=unassigned&id=' . $inboxId;
                $flash = 'Booking confirmed. Assign a table next.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE inbox_messages
                    SET status = 'confirmed', folder = 'archived', last_action_at = NOW()
                    WHERE inbox_id = ?
                ");
                $stmt->execute([$inboxId]);
                $flash = 'Booking confirmed.';
            }
            break;

        case 'decline':
            if ($bookingId > 0) {
                $update = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?");
                $update->execute([$bookingId]);
            }
            $stmt = $pdo->prepare("
                UPDATE inbox_messages
                SET status = 'declined', folder = 'archived', last_action_at = NOW()
                WHERE inbox_id = ?
            ");
            $stmt->execute([$inboxId]);
            $flash = 'Request declined.';
            break;

        case 'waitlist':
            $stmt = $pdo->prepare("
                UPDATE inbox_messages
                SET folder = 'waitlist', status = 'waiting', last_action_at = NOW()
                WHERE inbox_id = ?
            ");
            $stmt->execute([$inboxId]);
            $folder = 'waitlist';
            $redirectUrl = 'admin_inbox.php?folder=waitlist&id=' . $inboxId;
            $flash = 'Moved to waitlist.';
            break;

        case 'contact':
            $stmt = $pdo->prepare("
                UPDATE inbox_messages
                SET status = 'waiting', last_action_at = NOW()
                WHERE inbox_id = ?
            ");
            $stmt->execute([$inboxId]);
            $email = trim((string) ($message['guest_email'] ?? ''));
            $redirectUrl = 'admin_inbox.php?folder=' . urlencode($folder) . '&id=' . $inboxId;
            $flash = $email !== ''
                ? 'Marked as awaiting reply — open ' . $email . ' to follow up.'
                : 'Marked as awaiting reply.';
            break;

        case 'assign_table':
            $redirectUrl = 'admin_inbox.php?folder=' . urlencode($folder) . '&id=' . $inboxId;
            if ($bookingId <= 0) {
                $pdo->rollBack();
                $_SESSION['inbox_flash'] = 'No booking is linked to this message.';
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

            $bookingStmt = $pdo->prepare("
                SELECT booking_date,
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
                $pdo->rollBack();
                $_SESSION['inbox_flash'] = 'Booking not found.';
                header('Location: ' . $redirectUrl);
                exit;
            }

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
                    $_SESSION['inbox_flash'] = 'One or more selected tables are not available for assignment.';
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
                        $_SESSION['inbox_flash'] = 'One or more selected tables are already assigned at this time.';
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

            if (!empty($selectedTableIds) && $folder === 'unassigned') {
                $stmt = $pdo->prepare("
                    UPDATE inbox_messages
                    SET folder = 'archived', status = 'resolved', last_action_at = NOW()
                    WHERE inbox_id = ?
                ");
                $stmt->execute([$inboxId]);
                $redirectUrl = 'admin_inbox.php?folder=unassigned';
            } else {
                $stmt = $pdo->prepare("UPDATE inbox_messages SET last_action_at = NOW() WHERE inbox_id = ?");
                $stmt->execute([$inboxId]);
            }
            $flash = !empty($selectedTableIds)
                ? (count($selectedTableIds) > 1 ? 'Tables assigned.' : 'Table assigned.')
                : 'Table assignment cleared.';
            break;

        case 'save_notes':
            $notes = trim((string) ($_POST['staff_notes'] ?? ''));
            $stmt = $pdo->prepare("UPDATE inbox_messages SET staff_notes = ? WHERE inbox_id = ?");
            $stmt->execute([$notes !== '' ? $notes : null, $inboxId]);
            $pdo->commit();

            $isXhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
                || strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
                || (!empty($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'cors');
            if ($isXhr) {
                http_response_code(204);
                exit;
            }

            $_SESSION['inbox_flash'] = 'Staff note saved.';
            header('Location: admin_inbox.php?folder=' . urlencode($folder) . '&id=' . $inboxId);
            exit;

        case 'archive':
            $stmt = $pdo->prepare("UPDATE inbox_messages SET folder = 'archived', last_action_at = NOW() WHERE inbox_id = ?");
            $stmt->execute([$inboxId]);
            $flash = 'Message archived.';
            break;

        default:
            $pdo->rollBack();
            $_SESSION['inbox_flash'] = 'Unknown action.';
            header('Location: ' . $redirectUrl);
            exit;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['inbox_flash'] = 'Could not complete action.';
    header('Location: admin_inbox.php?folder=' . urlencode($folder));
    exit;
}

$_SESSION['inbox_flash'] = $flash;
header('Location: ' . $redirectUrl);
exit;
