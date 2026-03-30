<?php
require_once "../includes/session-check.php";
require_once "../includes/functions.php";

if(!isAdmin()){
    header("Location: ../auth/login.php");
    exit();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-4 mb-4">
    <a class="navbar-brand fw-bold text-warning" href="dashboard.php">Admin Panel</a>

    <div class="ms-auto">
        <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
        <a href="manage-bookings.php" class="btn btn-outline-light btn-sm me-2">Bookings</a>
        <a href="manage-tables.php" class="btn btn-outline-light btn-sm me-2">Tables</a>
        <a href="manage-users.php" class="btn btn-outline-light btn-sm me-2">Users</a>
        <a href="../auth/logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
</nav>