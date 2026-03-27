<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";

$id = intval($_GET['id']);

$stmt = $pdo->prepare("
UPDATE bookings SET status='cancelled'
WHERE booking_id=? AND user_id=?
");