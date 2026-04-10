<?php
/**
 * Shared <head> content for all admin pages.
 *
 * Usage:
 *   $adminPageTitle = 'My Page';
 *   ...then inside <head>:
 *   <?php include __DIR__ . '/admin-head.php'; ?>
 *   <style>/* page-specific styles *\/</style>
 * </head>
 *
 * The <head> open and </head> close remain in the page file
 * so pages can inject extra <link>/<script> tags (e.g. chart.js).
 */
$adminPageTitle ??= 'Admin';
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($adminPageTitle, ENT_QUOTES, 'UTF-8') ?> | DineMate Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/app.css" rel="stylesheet">
