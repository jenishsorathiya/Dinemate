<?php
$adminPageTitle = $adminPageTitle ?? 'Admin';
$adminPageIcon = $adminPageIcon ?? 'fa-compass';
$adminNotificationCount = isset($adminNotificationCount) ? (int) $adminNotificationCount : 0;
$adminProfileName = $adminProfileName ?? ($_SESSION['name'] ?? 'Admin');
$adminTopbarCenterContent = $adminTopbarCenterContent ?? '';
?>
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-page">
            <i class="fa <?php echo htmlspecialchars($adminPageIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
            <span class="topbar-page-title"><?php echo htmlspecialchars($adminPageTitle, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>
    <?php if ($adminTopbarCenterContent !== ''): ?>
        <div class="topbar-center">
            <?php echo $adminTopbarCenterContent; ?>
        </div>
    <?php endif; ?>
    <div class="topbar-right">
        <button type="button" class="topbar-icon-button" aria-label="Notifications">
            <i class="fa fa-bell"></i>
            <?php if ($adminNotificationCount > 0): ?>
                <span class="topbar-badge"><?php echo $adminNotificationCount; ?></span>
            <?php endif; ?>
        </button>
        <div class="topbar-profile" aria-label="Profile">
            <span class="topbar-profile-icon"><i class="fa fa-user-circle"></i></span>
            <span class="topbar-profile-name"><?php echo htmlspecialchars($adminProfileName, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>
</div>