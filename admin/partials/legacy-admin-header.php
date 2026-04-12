<?php
require_once __DIR__ . "/../../includes/session-check.php";
require_once __DIR__ . "/../../includes/functions.php";

requireAdmin();
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-4 mb-4">
    <a class="navbar-brand fw-bold text-warning" href="../pages/analytics.php">Admin Panel</a>

    <div class="ms-auto">
        <a href="../pages/analytics.php" class="btn btn-outline-light btn-sm me-2">Analytics</a>
        <a href="../pages/bookings-management.php" class="btn btn-outline-light btn-sm me-2">Bookings</a>
        <a href="../pages/tables-management.php" class="btn btn-outline-light btn-sm me-2">Tables</a>
        <a href="../pages/manage-users.php" class="btn btn-outline-light btn-sm me-2">Users</a>
        <a href="../auth/logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
</nav>
