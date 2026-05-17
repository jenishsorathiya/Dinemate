<?php
$adminNewSidebarActive = $adminNewSidebarActive ?? '';
$adminNewSidebarName = trim((string) ($_SESSION['name'] ?? 'Admin'));
if ($adminNewSidebarName === '') {
    $adminNewSidebarName = 'Admin';
}

$adminBuildPath = static function (string $path): string {
    return function_exists('appPath') ? appPath($path) : '/' . ltrim($path, '/');
};

$adminPagePath = static function (string $page) use ($adminBuildPath): string {
    return $adminBuildPath('admin/pages/' . ltrim($page, '/'));
};

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

$adminNewSidebarItems = [
    ['key' => 'home', 'label' => 'Home', 'href' => $adminPagePath('admin_home.php'), 'icon' => 'bi-house'],
    ['key' => 'bookings', 'label' => 'Bookings', 'href' => $adminPagePath('admin_bookings.php'), 'icon' => 'bi-calendar-check'],
    ['key' => 'inbox', 'label' => 'Inbox', 'href' => $adminPagePath('admin_inbox.php'), 'icon' => 'bi-inbox'],
    ['key' => 'functions', 'label' => 'Functions', 'href' => $adminPagePath('bookings-management.php'), 'icon' => 'bi-calendar-event'],
    ['key' => 'tables', 'label' => 'Tables', 'href' => $adminPagePath('tables-management.php'), 'icon' => 'bi-grid-3x3-gap'],
    ['key' => 'menu', 'label' => 'Menu', 'href' => $adminPagePath('menu-management.php'), 'icon' => 'bi-menu-button-wide'],
    ['key' => 'guests', 'label' => 'Guests', 'href' => $adminPagePath('customer-history.php'), 'icon' => 'bi-people'],
    ['key' => 'reviews', 'label' => 'Reviews', 'href' => $adminPagePath('admin_booking_reviews.php'), 'icon' => 'bi-star'],
    ['key' => 'users', 'label' => 'Users', 'href' => $adminPagePath('manage-users.php'), 'icon' => 'bi-person-gear'],
    ['key' => 'report', 'label' => 'Report', 'href' => $adminPagePath('analytics.php'), 'icon' => 'bi-bar-chart'],
    ['key' => 'settings', 'label' => 'Settings', 'href' => $adminPagePath('settings.php'), 'icon' => 'bi-gear'],
];
?>
<aside class="sidebar" aria-label="Admin sidebar">
    <div>
        <a class="sidebar-brand" href="<?php echo htmlspecialchars($adminPagePath('admin_home.php'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Old Canberra Inn admin home">
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
                    href="<?php echo htmlspecialchars((string) $sidebarItem['href'], ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                >
                    <i class="bi <?php echo htmlspecialchars((string) $sidebarItem['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars((string) $sidebarItem['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-profile">
            <div class="profile-avatar"><?php echo htmlspecialchars($adminNewSidebarInitials($adminNewSidebarName), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="profile-text">
                <strong><?php echo htmlspecialchars($adminNewSidebarName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span>Old Canberra Inn</span>
            </div>
            <i class="bi bi-chevron-down" aria-hidden="true"></i>
        </div>
        <a class="sidebar-link sidebar-logout" href="<?php echo htmlspecialchars($adminBuildPath('auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
<?php
$adminCsrfIncludeMeta = false;
include __DIR__ . '/admin-csrf.php';
unset($adminCsrfIncludeMeta);
?>
