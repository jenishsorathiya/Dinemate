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
    body h1 {
        font-size: 20px !important;
    }

    body h2 {
        font-size: 16px !important;
    }

    body h3 {
        font-size: 14px !important;
    }

    body h4 {
        font-size: 12px !important;
    }

    body h5 {
        font-size: 10px !important;
    }

    .hero-title,
    .page-title,
    .ui-kit-title,
    .settings-header h1,
    .mn-header-copy h1,
    .bm-page-header h1 {
        font-size: 20px !important;
    }

    .section-title,
    .panel-title,
    .ui-panel-title,
    .queue-head-title,
    .side-card-title,
    .bm-history-head h2,
    .section-title-wrap h2,
    .mn-panel-title-wrap h2 {
        font-size: 16px !important;
    }

    .card-title,
    .history-card-title,
    .mn-category-title,
    .operation-card h3,
    .panel-top h3,
    .service-top h3,
    .mn-panel-title-wrap h3 {
        font-size: 14px !important;
    }

    .mn-item-name {
        font-size: 12px !important;
    }

    .sidebar-shell {
        width: 248px;
        min-width: 248px;
        flex: 0 0 248px;
        position: relative;
        overflow: visible;
        z-index: 40;
    }

    .sidebar {
        position: sticky;
        top: 0;
        width: 248px;
        min-height: 100vh;
        height: 100vh;
        display: flex;
        flex-direction: column;
        background: var(--dm-surface-muted);
        color: var(--dm-text);
        padding: 18px 14px;
        overflow-y: auto;
        overflow-x: hidden;
        border-right: 1px solid var(--dm-border);
        box-shadow: 18px 0 32px rgba(47, 48, 43, 0.08);
    }

    .sidebar-brand {
        color: var(--dm-text);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 14px;
        white-space: nowrap;
        padding: 6px 14px 22px;
        margin: 0 0 18px;
        border-bottom: 1px solid var(--dm-border);
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .sidebar-nav-primary {
        flex: 1 1 auto;
    }

    .sidebar-nav-bottom {
        flex: 0 0 auto;
        padding-top: 16px;
        margin-top: 16px;
        border-top: 1px solid var(--dm-border);
    }

    .sidebar a {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        padding: 11px 14px;
        color: var(--dm-text);
        text-decoration: none;
        border-radius: var(--dm-radius-sm);
        transition: background 0.2s ease, color 0.2s ease;
        white-space: nowrap;
    }

    .sidebar-brand i,
    .sidebar a i {
        width: 24px;
        min-width: 24px;
        text-align: center;
        font-size: 20px;
    }

    .brand-label,
    .nav-label {
        opacity: 1;
        max-width: 180px;
        margin-left: 12px;
        overflow: hidden;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background: var(--dm-surface);
        color: var(--dm-text);
        box-shadow: inset 0 0 0 1px var(--dm-border);
    }

    .sidebar a.active {
        background: var(--dm-surface);
        color: var(--dm-text);
        box-shadow: inset 0 0 0 1px var(--dm-border);
    }

    @media (max-width: 991px) {
        .sidebar-shell {
            display: block;
        }

        .sidebar-shell,
        .sidebar {
            width: 100%;
            min-width: auto;
            flex: 0 0 auto;
            position: relative;
            height: auto;
            min-height: auto;
            border-right: none;
            border-bottom: 1px solid var(--dm-border);
            box-shadow: none;
            padding: 14px 10px;
        }

        .sidebar {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
            gap: 6px;
        }

        .sidebar-shell {
            background: var(--dm-surface-muted);
        }

        .sidebar-brand {
            justify-content: flex-start;
            width: 100%;
            margin: 0 0 8px;
            padding: 0 12px 12px;
        }

        .sidebar a {
            justify-content: center;
            padding: 10px 12px;
            flex: 1 1 120px;
            min-width: 0;
        }

        .sidebar a i {
            width: auto;
            margin-right: 8px;
        }

        .sidebar-nav,
        .sidebar-nav-primary,
        .sidebar-nav-bottom {
            display: contents;
        }

        .brand-label,
        .nav-label {
            opacity: 1;
            max-width: none;
            margin-left: 8px;
        }
    }
</style>

<div class="sidebar-shell">
    <div class="sidebar">
        <div class="sidebar-brand"><i class="fa fa-utensils"></i><span class="brand-label">DineMate</span></div>
        <nav class="sidebar-nav sidebar-nav-primary" aria-label="Admin navigation">
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/admin_home.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('home'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-house"></i><span class="nav-label">Home</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/bookings-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('bookings'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-clipboard-list"></i><span class="nav-label">Bookings</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/admin_inbox.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('inbox'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-inbox"></i><span class="nav-label">Inbox</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/bookings-management.php?type=function'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('functions'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-calendar-check"></i><span class="nav-label">Functions</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('timeline/timeline.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('events'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-calendar-days"></i><span class="nav-label">Events</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/menu-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('menu'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-utensils"></i><span class="nav-label">Menu</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/customer-history.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('guests'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-address-book"></i><span class="nav-label">Guests</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/admin_booking_reviews.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('reviews'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-star"></i><span class="nav-label">Reviews</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/analytics.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('report'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-chart-line"></i><span class="nav-label">Report</span>
            </a>
            <a href="<?php echo htmlspecialchars($adminSidebarLink('pages/settings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($adminSidebarIsActive('settings'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa fa-gear"></i><span class="nav-label">Settings</span>
            </a>
        </nav>
    </div>
</div>


