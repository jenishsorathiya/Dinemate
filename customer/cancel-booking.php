<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

requireCustomer();
ensureBookingTableAssignmentsTable($pdo);

$id = intval($_GET['id'] ?? 0);

if ($id < 1) {
	redirect(appPath('customer/my-bookings.php'));
}

try {
	$pdo->beginTransaction();

	$bookingStmt = $pdo->prepare("
	SELECT booking_id
	FROM bookings
	WHERE booking_id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
	LIMIT 1
	");
	$bookingStmt->execute([$id, getCurrentUserId()]);

	if (!$bookingStmt->fetch(PDO::FETCH_ASSOC)) {
		$pdo->rollBack();
		setFlashMessage('error', 'Booking not found or it can no longer be cancelled.');
		redirect(appPath('customer/my-bookings.php'));
	}

	syncBookingTableAssignments($pdo, $id, []);

	$stmt = $pdo->prepare("
	UPDATE bookings
	SET status = 'cancelled',
	    reservation_card_status = NULL
	WHERE booking_id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
	");

	$stmt->execute([$id, getCurrentUserId()]);
	$pdo->commit();
	setFlashMessage('success', 'Your booking has been cancelled.');
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}

	setFlashMessage('error', 'Unable to cancel this booking right now.');
}

redirect(appPath('customer/my-bookings.php'));
?>
