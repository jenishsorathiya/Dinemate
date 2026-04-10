<?php
$adminPageTitle = $adminPageTitle ?? 'Admin';
$adminPageIcon = $adminPageIcon ?? 'fa-compass';
$adminNotificationCount = isset($adminNotificationCount) ? (int) $adminNotificationCount : 0;
$adminProfileName = $adminProfileName ?? ($_SESSION['name'] ?? 'Admin');
$adminTopbarCenterContent = $adminTopbarCenterContent ?? '';
$adminPendingBookings = [];

if (isset($pdo)) {
    try {
        if (function_exists('ensureBookingRequestColumns')) {
            ensureBookingRequestColumns($pdo);
        }

        $pendingStmt = $pdo->query("
            SELECT b.booking_id,
                   b.booking_date,
                   b.start_time,
                   b.number_of_guests,
                   COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name,
                   COALESCE(ta.name, '') AS area_name,
                   GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.user_id
            LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
            LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
            LEFT JOIN table_areas ta ON rt.area_id = ta.area_id
            WHERE b.status = 'pending'
            GROUP BY b.booking_id
            ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC
            LIMIT 8
        ");

        $adminPendingBookings = $pendingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $adminPendingBookings = [];
    }
}

$adminPendingBookingCount = count($adminPendingBookings);
$adminTimelineBasePath = strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/timeline/') !== false
    ? 'new-dashboard.php'
    : 'timeline/new-dashboard.php';
$adminPendingFeedPath = strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/timeline/') !== false
    ? '../pending-bookings-feed.php'
    : 'pending-bookings-feed.php';
?>
<style>
    .topbar {
        min-height: 74px;
        background: rgba(255, 255, 255, 0.94);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 24px;
        border-bottom: 1px solid var(--dm-border);
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.04);
        backdrop-filter: blur(10px);
        flex-shrink: 0;
        gap: 20px;
    }

    .topbar-left,
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .topbar-left {
        min-width: 0;
    }

    .topbar-center {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 0;
        flex: 1;
    }

    .topbar-page {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        color: var(--dm-text);
    }

    .topbar-page i {
        color: var(--dm-text);
        font-size: 18px;
    }

    .topbar-page-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--dm-text);
        white-space: nowrap;
    }

    .topbar-right {
        margin-left: auto;
        white-space: nowrap;
    }

    .topbar-icon-button {
        position: relative;
        width: 44px;
        height: 44px;
        border: 1px solid var(--dm-border);
        border-radius: var(--dm-radius-sm);
        background: var(--dm-surface);
        color: var(--dm-text);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .topbar-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 50%;
        background: var(--dm-danger-text);
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .topbar-profile {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 6px 10px 6px 6px;
        border-radius: var(--dm-radius-md);
        border: 1px solid var(--dm-border);
        background: var(--dm-surface);
    }

    .topbar-profile-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--dm-accent-dark);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .topbar-profile-name {
        color: var(--dm-text-muted);
        font-weight: 600;
    }

    .topbar-pending-launcher {
        position: relative;
    }

    .topbar-pending-launcher.is-hidden,
    .topbar-action-badge.is-hidden {
        display: none;
    }

    .topbar-action-button {
        border: 1px solid var(--dm-border);
        background: var(--dm-surface);
        color: var(--dm-text-muted);
        border-radius: var(--dm-radius-md);
        min-height: 42px;
        padding: 0 14px;
        font-size: 13px;
        font-weight: 600;
        font-family: inherit;
        letter-spacing: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        position: relative;
        transition: background 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
    }

    .topbar-action-button:hover {
        background: var(--dm-surface-muted);
    }

    .topbar-action-button.has-pending {
        background: var(--dm-danger-bg);
        color: var(--dm-danger-text);
        border-color: #ffd5db;
        box-shadow: none;
    }

    .topbar-action-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 50%;
        background: var(--dm-accent-dark);
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
    }

    .topbar-action-button.has-pending .topbar-action-badge {
        background: var(--dm-danger-text);
    }

    .topbar-pending-panel {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: min(350px, 82vw);
        background: #ffffff;
        border: 1px solid var(--dm-border);
        border-radius: var(--dm-radius-md);
        box-shadow: 0 24px 54px rgba(15, 23, 42, 0.12);
        padding: 14px;
        display: none;
        z-index: 1100;
    }

    .topbar-pending-panel.open {
        display: block;
    }

    .topbar-pending-header {
        margin-bottom: 10px;
    }

    .topbar-pending-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--dm-text);
        letter-spacing: 0.01em;
    }

    .topbar-pending-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-height: 320px;
        overflow-y: auto;
    }

    .topbar-pending-item {
        display: block;
        border: 1px solid var(--dm-border);
        background: #fbfcfe;
        border-radius: var(--dm-radius-sm);
        padding: 10px 12px;
        text-decoration: none;
        transition: border-color 0.18s ease, background 0.18s ease;
    }

    .topbar-pending-item:focus-visible {
        outline: 2px solid rgba(37, 99, 235, 0.24);
        outline-offset: 2px;
    }

    .topbar-pending-item:hover {
        border-color: var(--dm-border-strong);
        background: #ffffff;
    }

    .topbar-pending-item-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 4px;
    }

    .topbar-pending-item-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--dm-text);
    }

    .topbar-pending-item-time {
        font-size: 12px;
        font-weight: 600;
        color: var(--dm-text-muted);
        white-space: nowrap;
    }

    .topbar-pending-item-meta {
        font-size: 12px;
        color: var(--dm-text-muted);
        line-height: 1.4;
    }

    .topbar-pending-empty {
        font-size: 12px;
        font-weight: 600;
        color: var(--dm-text-muted);
        padding: 12px 6px;
        text-align: center;
    }

    @media (max-width: 991px) {
        .topbar {
            padding: 18px 20px;
            flex-wrap: wrap;
            align-items: flex-start;
        }

        .topbar-left,
        .topbar-center,
        .topbar-right {
            width: 100%;
            justify-content: space-between;
        }

        .topbar-center {
            order: 3;
            justify-content: flex-start;
        }
    }

    @media (max-width: 640px) {
        .topbar-page-title {
            font-size: 18px;
        }

        .topbar-right {
            gap: 10px;
        }

        .topbar-profile-name {
            display: none;
        }
    }
</style>
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
        <div class="topbar-pending-launcher<?php echo $adminPendingBookingCount > 0 ? '' : ' is-hidden'; ?>" id="adminPendingBookingsLauncher">
            <button type="button" class="topbar-action-button<?php echo $adminPendingBookingCount > 0 ? ' has-pending' : ''; ?>" id="adminPendingBookingsBtn" aria-label="New bookings">
                <span id="adminPendingBookingsLabel">New Bookings</span>
                <span class="topbar-action-badge<?php echo $adminPendingBookingCount > 0 ? '' : ' is-hidden'; ?>" id="adminPendingBookingsBadge"><?php echo $adminPendingBookingCount; ?></span>
            </button>
            <div class="topbar-pending-panel" id="adminPendingBookingsPanel">
                <div class="topbar-pending-header">
                    <span class="topbar-pending-title">Pending Bookings</span>
                </div>
                <div class="topbar-pending-list" id="adminPendingBookingsList">
                    <?php if ($adminPendingBookingCount > 0): ?>
                        <?php foreach ($adminPendingBookings as $pendingBooking): ?>
                            <?php
                                $tableSummary = !empty($pendingBooking['assigned_table_numbers'])
                                    ? trim(($pendingBooking['area_name'] !== '' ? $pendingBooking['area_name'] . ' • ' : '') . 'T' . $pendingBooking['assigned_table_numbers'])
                                    : '';
                                $metaSummary = 'P' . (int)$pendingBooking['number_of_guests'] . ' • Pending';
                                if ($tableSummary !== '') {
                                    $metaSummary .= ' • ' . $tableSummary;
                                }
                                $timelineDayLink = $adminTimelineBasePath . '?date=' . urlencode((string)$pendingBooking['booking_date']);
                            ?>
                            <a class="topbar-pending-item" href="<?php echo htmlspecialchars($timelineDayLink, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="topbar-pending-item-top">
                                    <span class="topbar-pending-item-name"><?php echo htmlspecialchars($pendingBooking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="topbar-pending-item-time"><?php echo htmlspecialchars(date('M d', strtotime($pendingBooking['booking_date'])) . ' • ' . date('g:i A', strtotime($pendingBooking['start_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="topbar-pending-item-meta">
                                    <?php echo htmlspecialchars($metaSummary, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="topbar-pending-empty">No pending bookings right now.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
<script>
    (function() {
        const pendingBtn = document.getElementById('adminPendingBookingsBtn');
        const pendingLauncher = document.getElementById('adminPendingBookingsLauncher');
        const pendingBadge = document.getElementById('adminPendingBookingsBadge');
        const pendingPanel = document.getElementById('adminPendingBookingsPanel');
        const pendingList = document.getElementById('adminPendingBookingsList');
        const timelineBasePath = <?php echo json_encode($adminTimelineBasePath, JSON_UNESCAPED_SLASHES); ?>;
        const pendingFeedPath = <?php echo json_encode($adminPendingFeedPath, JSON_UNESCAPED_SLASHES); ?>;
        let pendingRefreshInFlight = false;

        if(!pendingBtn || !pendingLauncher || !pendingBadge || !pendingPanel || !pendingList) {
            return;
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatBookingTime(dateValue, timeValue) {
            if(!dateValue || !timeValue) {
                return '';
            }

            const date = new Date(`${dateValue}T${timeValue}`);
            if(Number.isNaN(date.getTime())) {
                return `${dateValue} • ${timeValue}`;
            }

            return `${date.toLocaleDateString(undefined, { month: 'short', day: '2-digit' })} • ${date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`;
        }

        function buildPendingMeta(booking) {
            const tableSummary = booking.assigned_table_numbers
                ? `${booking.area_name ? `${booking.area_name} • ` : ''}T${booking.assigned_table_numbers}`
                : '';

            return tableSummary
                ? `P${Number(booking.number_of_guests || 0)} • Pending • ${tableSummary}`
                : `P${Number(booking.number_of_guests || 0)} • Pending`;
        }

        function renderPendingBookings(bookings) {
            const bookingCount = Array.isArray(bookings) ? bookings.length : 0;

            pendingLauncher.classList.toggle('is-hidden', bookingCount === 0);
            pendingBtn.classList.toggle('has-pending', bookingCount > 0);
            pendingBadge.classList.toggle('is-hidden', bookingCount === 0);
            pendingBadge.textContent = String(bookingCount);

            if(bookingCount === 0) {
                pendingPanel.classList.remove('open');
                pendingList.innerHTML = '<div class="topbar-pending-empty">No pending bookings right now.</div>';
                return;
            }

            pendingList.innerHTML = bookings.map(function(booking) {
                const href = `${timelineBasePath}?date=${encodeURIComponent(String(booking.booking_date || ''))}`;
                return `\n                    <a class="topbar-pending-item" href="${escapeHtml(href)}">\n                        <div class="topbar-pending-item-top">\n                            <span class="topbar-pending-item-name">${escapeHtml(booking.customer_name || 'Guest')}</span>\n                            <span class="topbar-pending-item-time">${escapeHtml(formatBookingTime(booking.booking_date, booking.start_time))}</span>\n                        </div>\n                        <div class="topbar-pending-item-meta">${escapeHtml(buildPendingMeta(booking))}</div>\n                    </a>\n                `;
            }).join('');
        }

        function refreshPendingBookings() {
            if(pendingRefreshInFlight) {
                return Promise.resolve();
            }

            pendingRefreshInFlight = true;

            return fetch(`${pendingFeedPath}?t=${Date.now()}`, {
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(async function(response) {
                    const data = await response.json();
                    if(!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not refresh pending bookings');
                    }

                    renderPendingBookings(Array.isArray(data.bookings) ? data.bookings : []);
                })
                .catch(function() {
                    return null;
                })
                .finally(function() {
                    pendingRefreshInFlight = false;
                });
        }

        pendingBtn.addEventListener('click', function(event) {
            event.stopPropagation();
            if(pendingLauncher.classList.contains('is-hidden')) {
                return;
            }
            pendingPanel.classList.toggle('open');
        });

        pendingPanel.addEventListener('click', function(event) {
            event.stopPropagation();
        });

        document.addEventListener('click', function() {
            pendingPanel.classList.remove('open');
        });

        document.addEventListener('keydown', function(event) {
            if(event.key === 'Escape') {
                pendingPanel.classList.remove('open');
            }
        });

        document.addEventListener('visibilitychange', function() {
            if(document.visibilityState === 'visible') {
                refreshPendingBookings();
            }
        });

        document.addEventListener('admin-pending-bookings-changed', function() {
            refreshPendingBookings();
        });

        window.refreshAdminPendingBookings = refreshPendingBookings;
        window.setInterval(refreshPendingBookings, 15000);
    })();
</script>