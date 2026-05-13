<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$action    = trim((string) ($_POST['action'] ?? ''));

if ($bookingId <= 0 || $action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Ensure columns exist before using them
try {
    $existingCols = $pdo->query('DESCRIBE bookings')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_notes', $existingCols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN admin_notes TEXT DEFAULT NULL');
    }
    if (!in_array('inbox_read', $existingCols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN inbox_read TINYINT(1) NOT NULL DEFAULT 0');
    }
} catch (Throwable $e) {}

// Verify the booking exists and belongs to inbox scope
try {
    $booking = $pdo->prepare("
        SELECT booking_id, status FROM bookings WHERE booking_id = ?
    ");
    $booking->execute([$bookingId]);
    $bookingRow = $booking->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

if (!$bookingRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit;
}

try {
    switch ($action) {
        case 'confirm':
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', inbox_read = 1 WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            echo json_encode([
                'success'    => true,
                'status'     => 'confirmed',
                'statusLabel' => 'Confirmed',
                'statusClass' => 'resolved',
                'message'    => 'Booking confirmed successfully.',
            ]);
            break;

        case 'decline':
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', inbox_read = 1 WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            echo json_encode([
                'success'    => true,
                'status'     => 'cancelled',
                'statusLabel' => 'Cancelled',
                'statusClass' => 'declined',
                'message'    => 'Booking declined.',
            ]);
            break;

        case 'waitlist':
            $stmt = $pdo->prepare("UPDATE bookings SET inbox_read = 1 WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            echo json_encode([
                'success'    => true,
                'status'     => 'pending',
                'statusLabel' => 'Waitlisted',
                'statusClass' => 'waiting',
                'message'    => 'Moved to waitlist.',
            ]);
            break;

        case 'mark_read':
            $stmt = $pdo->prepare("UPDATE bookings SET inbox_read = 1 WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            echo json_encode(['success' => true]);
            break;

        case 'mark_unread':
            $stmt = $pdo->prepare("UPDATE bookings SET inbox_read = 0 WHERE booking_id = ?");
            $stmt->execute([$bookingId]);
            echo json_encode(['success' => true]);
            break;

        case 'save_note':
            $note = trim((string) ($_POST['note'] ?? ''));
            $note = substr($note, 0, 2000);
            $stmt = $pdo->prepare("UPDATE bookings SET admin_notes = ? WHERE booking_id = ?");
            $stmt->execute([$note !== '' ? $note : null, $bookingId]);
            echo json_encode(['success' => true, 'message' => 'Note saved.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

exit;
