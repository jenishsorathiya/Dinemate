<?php
$adminSidebarActive = $adminSidebarActive ?? '';
$adminSidebarPathPrefix = $adminSidebarPathPrefix ?? '';

if ($adminSidebarPathPrefix === '') {
    $adminSidebarRequestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($adminSidebarRequestUri, '/admin/pages/') !== false || strpos($adminSidebarRequestUri, '/admin/timeline/') !== false) {
        $adminSidebarPathPrefix = '../';
    }
}

$adminSidebarLink = static function (string $path) use ($adminSidebarPathPrefix): string {
    return $adminSidebarPathPrefix . $path;
};

$adminSidebarIsActive = static function (string $key) use ($adminSidebarActive): string {
    return $adminSidebarActive === $key ? 'active' : '';
};
?>
<style>
    .sidebar-shell {
        width: 96px;
        min-width: 96px;
        flex: 0 0 96px;
        position: relative;
        overflow: visible;
        z-index: 40;
    }

    .sidebar {
        position: sticky;
        top: 0;
        width: 96px;
        min-height: 100vh;
        height: 100vh;
        background: var(--dm-accent-dark);
        color: #ffffff;
        padding: 18px 14px;
        overflow-y: auto;
        overflow-x: hidden;
        border-right: 1px solid rgba(255,255,255,0.06);
        transition: width 0.25s ease, box-shadow 0.25s ease;
    }

    .sidebar:hover {
        width: 248px;
        box-shadow: 18px 0 32px rgba(10, 18, 34, 0.18);
    }

    .sidebar h4 {
        color: #ffffff;
        margin-bottom: 30px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 14px;
        white-space: nowrap;
    }

    .sidebar a {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 11px 14px;
        color: rgba(255, 255, 255, 0.84);
        text-decoration: none;
        border-radius: var(--dm-radius-sm);
        margin-bottom: 8px;
        transition: background 0.2s ease, color 0.2s ease, justify-content 0.2s ease;
        white-space: nowrap;
    }

    .sidebar:hover a {
        justify-content: flex-start;
    }

    .sidebar h4 i,
    .sidebar a i {
        width: 24px;
        min-width: 24px;
        text-align: center;
        font-size: 20px;
    }

    .brand-label,
    .nav-label {
        opacity: 0;
        max-width: 0;
        margin-left: 0;
        overflow: hidden;
        transition: opacity 0.2s ease, max-width 0.25s ease, margin-left 0.25s ease;
    }

    .sidebar:hover .brand-label,
    .sidebar:hover .nav-label {
        opacity: 1;
        max-width: 180px;
        margin-left: 12px;
    }

    .sidebar:not(:hover) h4 {
        justify-content: center;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.05);
    }

    @media (max-width: 991px) {
        .sidebar-shell,
        .sidebar {
            display: none;
        }
    }
</style>

<div class="sidebar-shell">
    <div class="sidebar">
        <h4><i class="fa fa-utensils"></i><span class="brand-label">DineMate</span></h4>
        <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/analytics.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('dashboard'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa fa-chart-line"></i><span class="nav-label">Analytics</span>
        </a>
        <a href="<?php echo htmlspecialchars($adminSidebarLink('timeline/timeline.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('timeline'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa fa-calendar-days"></i><span class="nav-label">Timeline</span>
        </a>
        <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/bookings-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('bookings'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa fa-clipboard-list"></i><span class="nav-label">Bookings</span>
        </a>
        <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/tables-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('tables'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa fa-chair"></i><span class="nav-label">Tables</span>
        </a>
        <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/menu-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('menu'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa fa-utensils"></i><span class="nav-label">Menu</span>
        </a>
        <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/manage-users.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('users'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa fa-users"></i><span class="nav-label">Users</span>
        </a>
        <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/customer-history.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('customers'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa fa-address-book"></i><span class="nav-label">Customers</span>
        </a>
        <a href="<?php echo htmlspecialchars($adminSidebarLink('../auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa fa-sign-out-alt"></i><span class="nav-label">Logout</span>
        </a>
    </div>
</div>


