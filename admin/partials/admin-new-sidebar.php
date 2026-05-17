<?php
$adminNewSidebarActive = $adminNewSidebarActive ?? '';
$adminNewSidebarPrefix = $adminNewSidebarPrefix ?? '';
$adminNewSidebarName = trim((string) ($_SESSION['name'] ?? 'Admin'));
if ($adminNewSidebarName === '') {
    $adminNewSidebarName = 'Admin';
}

$adminNewSidebarInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';

    foreach ($parts ?: [] as $part) {
        if ($part !== '') {
            $letters .= strtoupper(substr($part, 0, 1));
        }

        if (strlen($letters) >= 2) {
            break;
        }
    }

    if (strlen($letters) < 2) {
        $compactName = preg_replace('/[^A-Za-z0-9]/', '', $name);
        if (is_string($compactName) && strlen($compactName) >= 2) {
            $letters = strtoupper(substr($compactName, 0, 2));
        }
    }

    return $letters !== '' ? $letters : 'AD';
};

$adminNewSidebarLink = static function (string $path) use ($adminNewSidebarPrefix): string {
    return $adminNewSidebarPrefix . $path;
};

$adminNewSidebarItems = [
    ['key' => 'home', 'label' => 'Home', 'href' => 'admin_home.php', 'icon' => 'bi-house'],
    ['key' => 'bookings', 'label' => 'Bookings', 'href' => 'admin_bookings.php', 'icon' => 'bi-calendar-check'],
    ['key' => 'inbox', 'label' => 'Inbox', 'href' => 'admin_inbox.php', 'icon' => 'bi-inbox'],
    ['key' => 'functions', 'label' => 'Functions', 'href' => 'admin_functions.php', 'icon' => 'bi-calendar-event'],
    ['key' => 'events', 'label' => 'Events', 'href' => 'admin_events.php', 'icon' => 'bi-calendar3'],
    ['key' => 'menu', 'label' => 'Menu', 'href' => 'admin_menu.php', 'icon' => 'bi-menu-button-wide'],
    ['key' => 'guests', 'label' => 'Guests', 'href' => 'admin_guests.php', 'icon' => 'bi-people'],
    ['key' => 'reviews', 'label' => 'Reviews', 'href' => 'admin_booking_reviews.php', 'icon' => 'bi-star'],
    ['key' => 'report', 'label' => 'Report', 'href' => 'admin_report.php', 'icon' => 'bi-bar-chart'],
    ['key' => 'settings', 'label' => 'Settings', 'href' => 'admin_settings.php', 'icon' => 'bi-gear'],
];
?>
<aside class="sidebar" aria-label="Admin sidebar">
    <div>
        <a class="sidebar-brand" href="<?php echo htmlspecialchars($adminNewSidebarLink('admin_home.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Old Canberra Inn admin home">
            <div class="brand-icon">
                <i class="bi bi-bank" aria-hidden="true"></i>
            </div>
            <span>Old Canberra Inn</span>
        </a>

        <nav class="sidebar-nav" aria-label="Primary navigation">
            <?php foreach ($adminNewSidebarItems as $sidebarItem): ?>
                <?php $isActive = $adminNewSidebarActive === $sidebarItem['key']; ?>
                <a
                    class="sidebar-link<?php echo $isActive ? ' active' : ''; ?>"
                    href="<?php echo htmlspecialchars($adminNewSidebarLink((string) $sidebarItem['href']), ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                >
                    <i class="bi <?php echo htmlspecialchars((string) $sidebarItem['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars((string) $sidebarItem['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="sidebar-profile">
        <div class="profile-avatar"><?php echo htmlspecialchars($adminNewSidebarInitials($adminNewSidebarName), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="profile-text">
            <strong><?php echo htmlspecialchars($adminNewSidebarName, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>Old Canberra Inn</span>
        </div>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </div>
</aside>
