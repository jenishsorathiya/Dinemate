<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

requireCustomer();

$id = intval($_GET['id'] ?? 0);

if ($id < 1) {
	header("Location: my-bookings.php");
	exit();
}

$stmt = $pdo->prepare("
UPDATE bookings SET status='cancelled'
WHERE booking_id=? AND user_id=?
");

$stmt->execute([$id, getCurrentUserId()]);

header("Location: my-bookings.php");
exit();
?>
