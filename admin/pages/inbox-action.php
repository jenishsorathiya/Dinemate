<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureBookingRequestColumns($pdo);
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
            $stmt = $pdo->prepare("
                UPDATE inbox_messages
                SET status = 'confirmed', folder = 'archived', last_action_at = NOW()
                WHERE inbox_id = ?
            ");
            $stmt->execute([$inboxId]);
            $flash = 'Booking confirmed.';
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
