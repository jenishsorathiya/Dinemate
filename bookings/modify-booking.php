 <?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if(!isCustomer()){
    header("Location: ../auth/login.php");
    exit();
}
if(!isset($_GET['id'])){
    header("Location: my-bookings.php");
    exit();
}

$booking_id = intval($_GET['id']);

/* 🔹 Fetch existing booking */
$stmt = $pdo->prepare("
SELECT * FROM bookings 
WHERE booking_id = ? AND user_id = ?
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$booking){
    die("Booking not found.");
}
/* 🔹 Fetch available tables */
$tables = $pdo->query("
SELECT * FROM restaurant_tables 
WHERE status='available' 
ORDER BY capacity ASC
")->fetchAll(PDO::FETCH_ASSOC);

$error = "";
$success = "";