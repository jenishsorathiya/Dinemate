<?php
$adminSidebarActive = $adminSidebarActive ?? '';

$adminNewSidebarActive = match ($adminSidebarActive) {
    'events' => 'functions',
    'requests' => 'inbox',
    'booking-reviews' => 'reviews',
    default => $adminSidebarActive,
};

include __DIR__ . '/admin-new-sidebar.php';
?>
