<?php
require_once "../config/db.php";
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if(!isCustomer()){
    header("Location: ../auth/login.php");
    exit();
}

// Fetch available tables sorted by capacity
$tables = $pdo->query("SELECT * FROM restaurant_tables WHERE status='available' ORDER BY capacity ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include "../includes/header.php"; ?>

<style>
    

