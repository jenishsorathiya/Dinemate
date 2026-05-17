<?php
$adminSidebarActive = $adminSidebarActive ?? '';

$adminNewSidebarActive = match ($adminSidebarActive) {
    'events', 'timeline' => 'timeline',
    'requests' => 'inbox',
    default => $adminSidebarActive,
};

include __DIR__ . '/admin-new-sidebar.php';
?>
