<?php
$adminSidebarActive = $adminSidebarActive ?? '';
$adminSidebarPathPrefix = $adminSidebarPathPrefix ?? '';

$adminSidebarLink = static function (string $path) use ($adminSidebarPathPrefix): string {
    return $adminSidebarPathPrefix . $path;
};

$adminSidebarIsActive = static function (string $key) use ($adminSidebarActive): string {
    return $adminSidebarActive === $key ? 'active' : '';
};
?>
<style>
    .sidebar {
        width: 96px;
        background: #162033;
        color: #ffffff;
        padding: 18px 14px;
        overflow-y: auto;
        overflow-x: hidden;
        flex-shrink: 0;
        border-right: 1px solid rgba(255,255,255,0.06);
        transition: width 0.25s ease;
    }

    .sidebar:hover {
        width: 248px;
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
        color: #d5dceb;
        text-decoration: none;
        border-radius: 14px;
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
        .sidebar {
            display: none;
        }
    }
</style>

<div class="sidebar">
    <h4><i class="fa fa-utensils"></i><span class="brand-label">DineMate</span></h4>
    <a href="<?php echo htmlspecialchars($adminSidebarLink('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('dashboard'), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fa fa-chart-line"></i><span class="nav-label">Analytics</span>
    </a>
    <a href="<?php echo htmlspecialchars($adminSidebarLink('timeline/new-dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('timeline'), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fa fa-calendar-days"></i><span class="nav-label">Timeline</span>
    </a>
    <a href="<?php echo htmlspecialchars($adminSidebarLink('bookings-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('bookings'), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fa fa-clipboard-list"></i><span class="nav-label">Bookings</span>
    </a>
    <a href="<?php echo htmlspecialchars($adminSidebarLink('tables-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('tables'), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fa fa-chair"></i><span class="nav-label">Tables</span>
    </a>
    <a href="<?php echo htmlspecialchars($adminSidebarLink('menu-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('menu'), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fa fa-utensils"></i><span class="nav-label">Menu</span>
    </a>
    <a href="<?php echo htmlspecialchars($adminSidebarLink('manage-users.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('users'), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fa fa-users"></i><span class="nav-label">Users</span>
    </a>
    <a href="<?php echo htmlspecialchars($adminSidebarLink('../auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fa fa-sign-out-alt"></i><span class="nav-label">Logout</span>
    </a>
</div>