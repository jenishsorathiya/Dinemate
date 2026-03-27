<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";

if(!isset($_GET['id'])){
    header("Location: book-table.php");
    exit();
}

$booking_id = intval($_GET['id']);

$stmt = $pdo->prepare("
SELECT b.*, t.table_number 
FROM bookings b
JOIN restaurant_tables t ON b.table_id = t.table_id
WHERE b.booking_id = ? AND b.user_id = ?
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$booking){
    die("Booking not found.");
}
?>

<?php include "../includes/header.php"; ?>

<!-- Confetti Library -->
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<!-- QR Code Generator -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>





