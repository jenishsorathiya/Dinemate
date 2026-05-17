<?php
/**
 * Shared admin notification dropdown.
 *
 * Expects the current page to have loaded config/db.php and session helpers.
 */
$adminNotificationInboxUrl = $adminNotificationInboxUrl ?? 'admin_inbox.php';
$adminNotificationLimit = isset($adminNotificationLimit) ? max(1, min(8, (int) $adminNotificationLimit)) : 5;
$adminNotificationCounts = ['requests' => 0, 'unassigned' => 0, 'waitlist' => 0];
$adminNotificationItems = [];
$adminNotificationTotal = 0;

try {
    ensureBookingRequestColumns($pdo);
    ensureBookingTableAssignmentsTable($pdo);
    ensureInboxMessagesTable($pdo);

    $adminNotificationCounts = getInboxFolderCounts($pdo);
    $adminNotificationTotal = array_sum($adminNotificationCounts);

    $adminNotificationStmt = $pdo->query("
        SELECT im.*,
               b.booking_date,
               b.start_time,
               b.end_time,
               b.booking_type,
               b.status AS booking_status,
               b.number_of_guests AS booking_guests
        FROM inbox_messages im
        LEFT JOIN bookings b ON b.booking_id = im.booking_id
        WHERE im.folder <> 'archived'
          AND (b.booking_date IS NULL OR b.booking_date >= CURDATE())
        ORDER BY im.is_read ASC, im.received_at DESC, im.inbox_id DESC
        LIMIT {$adminNotificationLimit}
    ");
    $adminNotificationItems = $adminNotificationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $adminNotificationError) {
    $adminNotificationTotal = isset($pendingBookingsCount) ? (int) $pendingBookingsCount : 0;
    $adminNotificationItems = [];
}

$adminNotificationDropdownId = 'admin-notification-dropdown';
$adminNotificationBadge = $adminNotificationTotal > 99 ? '99+' : (string) $adminNotificationTotal;
$adminNotificationFolderLabel = static function (string $folder): string {
    return match ($folder) {
        'unassigned' => 'Unassigned',
        'waitlist' => 'Waitlist',
        default => 'Requests',
    };
};
$adminNotificationUrl = static function (array $item) use ($adminNotificationInboxUrl): string {
    $query = [
        'folder' => (string) ($item['folder'] ?? 'requests'),
        'id' => (int) ($item['inbox_id'] ?? 0),
    ];
    return $adminNotificationInboxUrl . '?' . http_build_query($query);
};
?>
<div class="notification-wrapper" data-admin-notifications>
    <button
        class="icon-btn notification-btn"
        type="button"
        aria-label="Notifications"
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="<?php echo htmlspecialchars($adminNotificationDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
        data-notification-toggle
    >
        <i class="bi bi-bell-fill" aria-hidden="true"></i>
        <?php if ($adminNotificationTotal > 0): ?>
            <span class="notification-badge"><?php echo htmlspecialchars($adminNotificationBadge, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
    </button>

    <div
        class="notification-dropdown"
        id="<?php echo htmlspecialchars($adminNotificationDropdownId, ENT_QUOTES, 'UTF-8'); ?>"
        data-notification-menu
        hidden
    >
        <div class="notification-dropdown-header">
            <div>
                <h2>Notifications</h2>
                <p><?php echo $adminNotificationTotal > 0 ? htmlspecialchars(number_format($adminNotificationTotal) . ' item' . ($adminNotificationTotal === 1 ? '' : 's') . ' need attention', ENT_QUOTES, 'UTF-8') : 'Everything is up to date'; ?></p>
            </div>
            <a href="<?php echo htmlspecialchars($adminNotificationInboxUrl, ENT_QUOTES, 'UTF-8'); ?>">Inbox</a>
        </div>

        <div class="notification-summary" aria-label="Notification summary">
            <?php foreach ($adminNotificationCounts as $folderKey => $folderCount): ?>
                <a href="<?php echo htmlspecialchars($adminNotificationInboxUrl . '?folder=' . urlencode((string) $folderKey), ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo htmlspecialchars($adminNotificationFolderLabel((string) $folderKey), ENT_QUOTES, 'UTF-8'); ?></span>
                    <strong><?php echo number_format((int) $folderCount); ?></strong>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="notification-list">
            <?php if (empty($adminNotificationItems)): ?>
                <div class="notification-empty">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                    <span>No new requests or booking changes.</span>
                </div>
            <?php else: ?>
                <?php foreach ($adminNotificationItems as $notificationItem): ?>
                    <?php
                    $notificationMeta = inboxTypeMeta((string) ($notificationItem['type'] ?? ''));
                    $notificationDate = !empty($notificationItem['booking_date'])
                        ? date('D, j M', strtotime((string) $notificationItem['booking_date']))
                        : '';
                    $notificationTime = !empty($notificationItem['start_time'])
                        ? date('g:i A', strtotime((string) $notificationItem['start_time']))
                        : '';
                    $notificationGuests = (int) ($notificationItem['party_size'] ?: $notificationItem['booking_guests'] ?: 0);
                    $notificationMetaLine = trim(implode(' · ', array_filter([
                        $notificationDate,
                        $notificationTime,
                        $notificationGuests > 0 ? 'P' . $notificationGuests : '',
                        inboxFormatRelativeTime((string) ($notificationItem['received_at'] ?? '')),
                    ])));
                    ?>
                    <a
                        class="notification-item <?php echo empty($notificationItem['is_read']) ? 'is-unread' : ''; ?>"
                        href="<?php echo htmlspecialchars($adminNotificationUrl($notificationItem), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <span class="notification-item-icon notification-tone-<?php echo htmlspecialchars((string) $notificationMeta['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="bi <?php echo htmlspecialchars((string) $notificationMeta['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                        </span>
                        <span class="notification-item-copy">
                            <span class="notification-item-title">
                                <?php echo htmlspecialchars((string) ($notificationItem['guest_name'] ?: 'Guest'), ENT_QUOTES, 'UTF-8'); ?>
                                <em><?php echo htmlspecialchars((string) $notificationMeta['label'], ENT_QUOTES, 'UTF-8'); ?></em>
                            </span>
                            <span class="notification-item-preview"><?php echo htmlspecialchars((string) ($notificationItem['preview'] ?: $notificationItem['subject'] ?: 'Review this item'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($notificationMetaLine !== ''): ?>
                                <span class="notification-item-meta"><?php echo htmlspecialchars($notificationMetaLine, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <a class="notification-footer-link" href="<?php echo htmlspecialchars($adminNotificationInboxUrl, ENT_QUOTES, 'UTF-8'); ?>">
            View all notifications
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</div>

<script>
    (function () {
        if (window.DineMateNotificationsBound) {
            return;
        }

        window.DineMateNotificationsBound = true;

        const closeMenus = (exceptWrapper) => {
            document.querySelectorAll('[data-admin-notifications]').forEach((wrapper) => {
                if (wrapper === exceptWrapper) {
                    return;
                }

                const menu = wrapper.querySelector('[data-notification-menu]');
                const toggle = wrapper.querySelector('[data-notification-toggle]');

                if (menu) {
                    menu.hidden = true;
                }

                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        };

        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-notification-toggle]');

            if (toggle) {
                const wrapper = toggle.closest('[data-admin-notifications]');
                const menu = wrapper ? wrapper.querySelector('[data-notification-menu]') : null;

                if (!menu) {
                    return;
                }

                const shouldOpen = menu.hidden;
                closeMenus(wrapper);
                menu.hidden = !shouldOpen;
                toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                return;
            }

            if (!event.target.closest('[data-admin-notifications]')) {
                closeMenus(null);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenus(null);
            }
        });
    })();
</script>
