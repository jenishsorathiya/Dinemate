<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if(!isCustomer()){
header("Location: ../auth/login.php");
exit();
}

$stmt = $pdo->prepare("
SELECT b.*, t.table_number
FROM bookings b
JOIN restaurant_tables t
ON b.table_id = t.table_id
WHERE b.user_id = ?
ORDER BY b.booking_date DESC
");

$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include "../includes/header.php"; ?>