<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

if (function_exists('ensureBookingRequestColumns')) {
    ensureBookingRequestColumns($pdo);
}
if (function_exists('ensureBookingTableAssignmentsTable')) {
    ensureBookingTableAssignmentsTable($pdo);
}
if (function_exists('ensureTableAreasSchema')) {
    ensureTableAreasSchema($pdo);
}

// Ensure inbox columns exist
try {
    $existingCols = $pdo->query('DESCRIBE bookings')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_notes', $existingCols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN admin_notes TEXT DEFAULT NULL');
    }
    if (!in_array('inbox_read', $existingCols, true)) {
        $pdo->exec('ALTER TABLE bookings ADD COLUMN inbox_read TINYINT(1) NOT NULL DEFAULT 0');
    }
} catch (Throwable $e) {}

$adminNewSidebarActive = 'inbox';
$todayDate = date('Y-m-d');

// Inbox stats
$inboxStats = ['total' => 0, 'unread' => 0, 'pending' => 0, 'no_table' => 0];
try {
    $statsRow = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN inbox_read = 0 THEN 1 ELSE 0 END) AS unread,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'confirmed' AND table_id IS NULL
                AND NOT EXISTS (SELECT 1 FROM booking_table_assignments bta WHERE bta.booking_id = bookings.booking_id)
                THEN 1 ELSE 0 END) AS no_table_count
        FROM bookings
        WHERE status = 'pending'
           OR (
               status = 'confirmed'
               AND table_id IS NULL
               AND NOT EXISTS (SELECT 1 FROM booking_table_assignments bta WHERE bta.booking_id = bookings.booking_id)
           )
    ")->fetch(PDO::FETCH_ASSOC) ?: [];
    $inboxStats = [
        'total'    => (int) ($statsRow['total'] ?? 0),
        'unread'   => (int) ($statsRow['unread'] ?? 0),
        'pending'  => (int) ($statsRow['pending_count'] ?? 0),
        'no_table' => (int) ($statsRow['no_table_count'] ?? 0),
    ];
} catch (Throwable $e) {}

// Query inbox items
$inboxBookings = [];
try {
    $inboxBookings = $pdo->query("
        SELECT
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.status,
            COALESCE(b.booking_type, 'normal') AS booking_type,
            b.booking_source,
            b.special_request,
            b.admin_notes,
            b.inbox_read,
            b.created_at,
            COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS display_name,
            COALESCE(NULLIF(b.customer_phone, ''), u.phone, '')  AS display_phone,
            COALESCE(NULLIF(b.customer_email, ''), u.email, '')  AS display_email,
            CASE WHEN b.table_id IS NOT NULL OR EXISTS (
                SELECT 1 FROM booking_table_assignments bta WHERE bta.booking_id = b.booking_id
            ) THEN 1 ELSE 0 END AS has_table
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.status = 'pending'
           OR (
               b.status = 'confirmed'
               AND b.table_id IS NULL
               AND NOT EXISTS (SELECT 1 FROM booking_table_assignments bta WHERE bta.booking_id = b.booking_id)
           )
        ORDER BY b.inbox_read ASC, b.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// Table areas with capacity info
$tableAreas = [];
try {
    $tableAreas = $pdo->query("
        SELECT ta.area_id, ta.name,
               COUNT(rt.table_id)   AS total_tables,
               MAX(rt.capacity)     AS max_capacity,
               SUM(rt.capacity)     AS total_capacity
        FROM table_areas ta
        LEFT JOIN restaurant_tables rt ON ta.area_id = rt.area_id
        WHERE ta.is_active = 1
        GROUP BY ta.area_id, ta.name
        ORDER BY ta.display_order ASC, ta.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// Compute availability for a booking against real booked tables
$getAvailability = static function (array $booking) use ($pdo, $tableAreas): array {
    $bookingId = (int) $booking['booking_id'];
    $date      = (string) ($booking['booking_date'] ?? '');
    $guests    = (int) ($booking['number_of_guests'] ?? 1);

    // Which areas have at least one table booked on this date (excluding this booking)
    $bookedAreas = [];
    if ($date !== '') {
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT rt.area_id
                FROM bookings b2
                JOIN restaurant_tables rt ON b2.table_id = rt.table_id
                WHERE b2.booking_date = ? AND b2.booking_id != ? AND b2.status NOT IN ('cancelled','no_show')
            ");
            $stmt->execute([$date, $bookingId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $aid) {
                $bookedAreas[(int) $aid] = true;
            }
        } catch (Throwable $e) {}
    }

    $result = [];
    foreach ($tableAreas as $area) {
        $areaId     = (int) ($area['area_id'] ?? 0);
        $maxCap     = (int) ($area['max_capacity'] ?? 0);
        $totalTables = (int) ($area['total_tables'] ?? 0);
        $areaName   = (string) ($area['name'] ?? '');

        if ($totalTables === 0 || $maxCap < $guests) {
            $state   = 'unavailable';
            $summary = 'Unavailable';
            $helper  = 'No tables suit this party size';
        } elseif (isset($bookedAreas[$areaId])) {
            $state   = 'limited';
            $summary = 'Partially booked';
            $helper  = 'Some tables may still be free';
        } else {
            $state   = 'available';
            $summary = $totalTables . ' table' . ($totalTables !== 1 ? 's' : '') . ' available';
            $helper  = 'Fits party of ' . $guests;
        }

        $result[] = [
            'area'    => $areaName,
            'summary' => $summary,
            'helper'  => $helper,
            'state'   => $state,
            'icon'    => 'bi-grid',
        ];
    }

    return $result;
};

// Classify booking into inbox type
$classifyBooking = static function (array $booking): array {
    $status   = $booking['status'];
    $type     = normalizeBookingType($booking['booking_type'] ?? 'normal');
    $hasTable = (bool) ($booking['has_table'] ?? false);

    if ($status === 'confirmed' && !$hasTable) {
        return ['title' => 'No Table Assigned', 'typeKey' => 'no-table', 'tone' => 'orange', 'icon' => 'bi-exclamation-triangle', 'preview_prefix' => 'Confirmed · needs table assignment'];
    }
    if ($type === 'function') {
        return ['title' => 'Function Enquiry', 'typeKey' => 'function', 'tone' => 'purple', 'icon' => 'bi-people', 'preview_prefix' => 'Function booking enquiry'];
    }
    if ($type === 'trivia') {
        return ['title' => 'Trivia Night Request', 'typeKey' => 'trivia', 'tone' => 'blue', 'icon' => 'bi-question-circle', 'preview_prefix' => 'Trivia night reservation'];
    }
    return ['title' => 'New Booking Request', 'typeKey' => 'new-booking', 'tone' => 'blue', 'icon' => 'bi-envelope', 'preview_prefix' => 'Pending confirmation'];
};

// Status info
$getStatusInfo = static function (array $booking): array {
    $status   = $booking['status'];
    $hasTable = (bool) ($booking['has_table'] ?? false);
    if ($status === 'confirmed' && !$hasTable) {
        return ['label' => 'No Table', 'key' => 'no-table', 'class' => 'needs-action'];
    }
    if ($status === 'pending') {
        return ['label' => 'Pending', 'key' => 'pending', 'class' => 'needs-action'];
    }
    return ['label' => ucfirst($status), 'key' => $status, 'class' => $status === 'confirmed' ? 'resolved' : $status];
};

$formatInboxDate = static function (?string $date): string {
    if (!$date) return 'Date TBC';
    $ts = strtotime($date);
    return $ts ? date('D, j M Y', $ts) : 'Date TBC';
};

$formatInboxTime = static function (?string $time): string {
    if (!$time) return 'Time TBC';
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : 'Time TBC';
};

$formatRelativeTime = static function (?string $createdAt): string {
    if (!$createdAt) return '-';
    $ts = strtotime($createdAt);
    if (!$ts) return '-';
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('j M', $ts);
};

// Build JS-ready request array
$inboxRequests = [];
$order = 1;
foreach ($inboxBookings as $booking) {
    $cl      = $classifyBooking($booking);
    $si      = $getStatusInfo($booking);
    $avail   = $getAvailability($booking);
    $special = trim((string) ($booking['special_request'] ?? ''));
    $preview = $special !== '' ? $special : $cl['preview_prefix'];

    $inboxRequests[] = [
        'id'           => 'booking-' . $booking['booking_id'],
        'bookingId'    => (int) $booking['booking_id'],
        'title'        => $cl['title'],
        'typeKey'      => $cl['typeKey'],
        'guest'        => $booking['display_name'],
        'preview'      => $preview,
        'received'     => $formatRelativeTime($booking['created_at']),
        'receivedFull' => date('j M Y, g:i A', strtotime((string) ($booking['created_at'] ?? 'now'))),
        'order'        => $order++,
        'partySize'    => (int) $booking['number_of_guests'],
        'isRead'       => (bool) $booking['inbox_read'],
        'status'       => $si['label'],
        'statusKey'    => $si['key'],
        'statusClass'  => $si['class'],
        'priorityKey'  => $booking['inbox_read'] ? 'normal' : 'high',
        'tone'         => $cl['tone'],
        'icon'         => $cl['icon'],
        'phone'        => $booking['display_phone'],
        'email'        => $booking['display_email'],
        'date'         => $formatInboxDate($booking['booking_date']),
        'dateRaw'      => $booking['booking_date'],
        'time'         => $formatInboxTime($booking['start_time']),
        'bookingType'  => ucfirst($booking['booking_type'] ?? 'Normal'),
        'seatingStyle' => ($booking['booking_type'] === 'function') ? 'Function setup' : 'Standard',
        'message'      => $special,
        'staffNote'    => trim((string) ($booking['admin_notes'] ?? '')),
        'availability' => $avail,
    ];
}

// Tab counts (computed from built request list)
$tabCounts = [
    'all'        => count($inboxRequests),
    'unread'     => count(array_filter($inboxRequests, fn ($r) => !$r['isRead'])),
    'action'     => count(array_filter($inboxRequests, fn ($r) => $r['statusKey'] === 'pending')),
    'unassigned' => count(array_filter($inboxRequests, fn ($r) => $r['typeKey'] === 'no-table')),
];

$styleVersion = (string) (@filemtime(__DIR__ . '/../../assets/css/style.css') ?: time());
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inbox | DineMate Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo $e($styleVersion); ?>">
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../partials/admin-new-sidebar.php'; ?>

        <main class="main-content admin-inbox-main" aria-label="Inbox page">
            <header class="page-header admin-inbox-header">
                <div>
                    <h1 class="page-title">
                        Inbox
                        <?php if ($inboxStats['unread'] > 0): ?>
                            <span class="admin-inbox-header-badge"><?php echo min($inboxStats['unread'], 99); ?></span>
                        <?php endif; ?>
                    </h1>
                    <p class="page-subtitle">
                        <?php echo number_format($inboxStats['total']); ?> item<?php echo $inboxStats['total'] !== 1 ? 's' : ''; ?> needing attention
                        <?php if ($inboxStats['unread'] > 0): ?>
                            <span class="admin-inbox-unread-dot"></span>
                            <?php echo number_format($inboxStats['unread']); ?> unread
                        <?php endif; ?>
                    </p>
                </div>

                <div class="header-actions admin-bookings-actions" aria-label="Inbox actions">
                    <a class="primary-btn header-add-booking-btn" href="../timeline/timeline.php?date=<?php echo urlencode($todayDate); ?>#bookingList">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span>Add Booking</span>
                    </a>
                    <a class="icon-btn notification-btn" href="admin_inbox.php" aria-label="Refresh inbox">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                    </a>
                </div>
            </header>

            <section class="admin-inbox-layout" aria-label="Inbox queue">
                <!-- Left: list panel -->
                <div class="admin-inbox-list-card card">
                    <div class="admin-inbox-list-head">
                        <strong data-inbox-count><?php echo number_format(count($inboxRequests)); ?> request<?php echo count($inboxRequests) !== 1 ? 's' : ''; ?></strong>
                        <label class="admin-inbox-sort-control" aria-label="Sort inbox">
                            <select data-inbox-sort>
                                <option value="newest">Newest first</option>
                                <option value="oldest">Oldest first</option>
                                <option value="unread">Unread first</option>
                                <option value="party-desc">Largest party</option>
                                <option value="party-asc">Smallest party</option>
                            </select>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </label>
                    </div>

                    <nav class="admin-inbox-list-tabs-row" aria-label="Inbox filters">
                        <?php foreach ([
                            'all'        => 'All',
                            'unread'     => 'Unread',
                            'action'     => 'Action',
                            'unassigned' => 'Unassigned',
                        ] as $tabKey => $tabLabel):
                            $tc = $tabCounts[$tabKey] ?? 0;
                        ?>
                            <button
                                type="button"
                                class="admin-inbox-list-tab<?php echo $tabKey === 'all' ? ' is-active' : ''; ?>"
                                data-inbox-tab="<?php echo $e($tabKey); ?>"
                                <?php echo $tabKey === 'all' ? 'aria-current="page"' : ''; ?>
                            >
                                <?php echo $e($tabLabel); ?>
                                <?php if ($tc > 0): ?>
                                    <span class="admin-inbox-tab-count"><?php echo min($tc, 99); ?></span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </nav>

                    <div class="admin-inbox-search-wrap">
                        <label class="admin-inbox-search-label" aria-label="Search inbox">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input
                                type="search"
                                class="admin-inbox-search-input"
                                placeholder="Search guest, phone, booking ID…"
                                data-inbox-search
                                autocomplete="off"
                            >
                        </label>
                    </div>

                    <div class="admin-inbox-list" data-inbox-list>
                        <?php foreach ($inboxRequests as $index => $request): ?>
                            <button
                                type="button"
                                class="admin-inbox-request<?php echo $index === 0 ? ' is-selected' : ''; ?><?php echo !$request['isRead'] ? ' is-unread' : ''; ?>"
                                data-inbox-row
                                data-request-id="<?php echo $e($request['id']); ?>"
                                data-status="<?php echo $e($request['statusKey']); ?>"
                                data-type="<?php echo $e($request['typeKey']); ?>"
                                data-priority="<?php echo $e($request['priorityKey']); ?>"
                                data-order="<?php echo $e((string) $request['order']); ?>"
                                data-party-size="<?php echo $e((string) $request['partySize']); ?>"
                                data-search-text="<?php echo $e(strtolower($request['guest'] . ' ' . $request['phone'] . ' ' . $request['bookingId'] . ' ' . $request['dateRaw'])); ?>"
                                aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                            >
                                <?php if (!$request['isRead']): ?>
                                    <span class="admin-inbox-unread-pip" aria-label="Unread"></span>
                                <?php endif; ?>

                                <span class="admin-inbox-request-icon tone-<?php echo $e($request['tone']); ?>">
                                    <i class="bi <?php echo $e($request['icon']); ?>" aria-hidden="true"></i>
                                </span>

                                <span class="admin-inbox-request-copy">
                                    <span class="admin-inbox-request-title"><?php echo $e($request['title']); ?></span>
                                    <span class="admin-inbox-request-guest"><?php echo $e($request['guest']); ?></span>
                                    <span class="admin-inbox-request-preview"><?php echo $e($request['preview']); ?></span>
                                </span>

                                <span class="admin-inbox-request-meta">
                                    <span class="admin-inbox-received"><?php echo $e($request['received']); ?></span>
                                    <span class="admin-inbox-party">
                                        <i class="bi bi-person" aria-hidden="true"></i>
                                        <?php echo $e((string) $request['partySize']); ?>
                                    </span>
                                    <span class="admin-inbox-status-badge status-<?php echo $e($request['statusClass']); ?>" data-row-status><?php echo $e($request['status']); ?></span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-inbox-list-empty" data-inbox-empty <?php echo !empty($inboxRequests) ? 'hidden' : ''; ?>>
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                        <strong>All clear</strong>
                        <span>No requests match this filter.</span>
                    </div>
                </div>

                <!-- Right: detail panel -->
                <article class="admin-inbox-detail-card card" data-inbox-detail aria-live="polite">
                    <?php if (empty($inboxRequests)): ?>
                        <div class="admin-inbox-empty-state">
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <strong>Inbox is empty</strong>
                            <span>No bookings need attention right now.</span>
                        </div>
                    <?php endif; ?>
                </article>
            </section>
        </main>
    </div>

    <script>
        (() => {
            const ACTION_URL = 'inbox-action.php';
            const inboxRequests = <?php echo json_encode($inboxRequests, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?>;
            const requestMap = new Map(inboxRequests.map((r) => [r.id, r]));
            const rows = Array.from(document.querySelectorAll('[data-inbox-row]'));
            const list = document.querySelector('[data-inbox-list]');
            const detail = document.querySelector('[data-inbox-detail]');
            const countEl = document.querySelector('[data-inbox-count]');
            const emptyEl = document.querySelector('[data-inbox-empty]');
            const sortSelect = document.querySelector('[data-inbox-sort]');
            const tabs = Array.from(document.querySelectorAll('[data-inbox-tab]'));
            const searchInput = document.querySelector('[data-inbox-search]');
            let activeTab = 'all';
            let searchQuery = '';
            let selectedId = inboxRequests[0]?.id || '';
            let activeDropdown = null;
            let activeMenuTrigger = null;

            const esc = (v) => String(v ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            // ── Dropdown management ────────────────────────────────────────
            const closeAnyDropdown = () => {
                if (activeDropdown) {
                    activeDropdown.hidden = true;
                    if (activeMenuTrigger) activeMenuTrigger.setAttribute('aria-expanded', 'false');
                    activeDropdown = null;
                    activeMenuTrigger = null;
                }
            };
            document.addEventListener('click', closeAnyDropdown);

            // ── AJAX helpers ───────────────────────────────────────────────
            const apiPost = async (data) => {
                const body = new URLSearchParams(data);
                const res = await fetch(ACTION_URL, { method: 'POST', body });
                return res.json();
            };

            // ── Mark as read ───────────────────────────────────────────────
            const markRead = async (requestId) => {
                const request = requestMap.get(requestId);
                if (!request || request.isRead) return;
                request.isRead = true;
                const row = rows.find((r) => r.dataset.requestId === requestId);
                if (row) {
                    row.classList.remove('is-unread');
                    const pip = row.querySelector('.admin-inbox-unread-pip');
                    if (pip) pip.remove();
                }
                try {
                    await apiPost({ action: 'mark_read', booking_id: request.bookingId });
                } catch (e) {}
            };

            // ── Mark as unread ─────────────────────────────────────────────
            const markUnread = async (requestId) => {
                const request = requestMap.get(requestId);
                if (!request || !request.isRead) return;
                request.isRead = false;
                const row = rows.find((r) => r.dataset.requestId === requestId);
                if (row) {
                    row.classList.add('is-unread');
                    if (!row.querySelector('.admin-inbox-unread-pip')) {
                        const pip = document.createElement('span');
                        pip.className = 'admin-inbox-unread-pip';
                        pip.setAttribute('aria-label', 'Unread');
                        row.insertBefore(pip, row.firstChild);
                    }
                }
                try {
                    await apiPost({ action: 'mark_unread', booking_id: request.bookingId });
                } catch (e) {}
                applyFilters();
            };

            // ── Row status update ──────────────────────────────────────────
            const updateRowStatus = (request) => {
                const row = rows.find((r) => r.dataset.requestId === request.id);
                if (!row) return;
                row.dataset.status = request.statusKey;
                const badge = row.querySelector('[data-row-status]');
                if (badge) {
                    badge.className = `admin-inbox-status-badge status-${request.statusClass}`;
                    badge.textContent = request.status;
                }
            };

            // ── Render meta item ───────────────────────────────────────────
            const metaItem = (icon, label, href = '') => {
                const content = href
                    ? `<a href="${esc(href)}" class="admin-inbox-detail-link">${esc(label)}</a>`
                    : `<span>${esc(label)}</span>`;
                return `<span class="admin-inbox-detail-meta-item">
                    <i class="bi ${esc(icon)}" aria-hidden="true"></i>
                    ${content}
                </span>`;
            };

            // ── Render availability ────────────────────────────────────────
            const renderAvailability = (areas) => areas.map((a) => `
                <article class="admin-inbox-area-card area-${esc(a.state)}">
                    <div class="admin-inbox-area-title">
                        <i class="bi ${esc(a.icon || 'bi-grid')}" aria-hidden="true"></i>
                        <strong>${esc(a.area)}</strong>
                    </div>
                    <span class="admin-inbox-area-summary">${esc(a.summary)}</span>
                    <small>${esc(a.helper)}</small>
                </article>
            `).join('');

            // ── Render full detail panel ───────────────────────────────────
            const renderDetail = (request, toastMsg = '', toastType = 'info') => {
                if (!detail || !request) return;

                const mailSubject = encodeURIComponent(`Re: Your booking at DineMate – ${request.date}`);
                const mailBody = encodeURIComponent(`Hi ${request.guest},\n\nThank you for your booking request.\n\n`);
                const mailHref = request.email ? `mailto:${request.email}?subject=${mailSubject}&body=${mailBody}` : '';
                const phoneHref = request.phone ? `tel:${request.phone.replace(/\s/g, '')}` : '';

                const toast = toastMsg
                    ? `<div class="admin-inbox-toast admin-inbox-toast-${esc(toastType)}" data-inbox-toast>
                            <i class="bi ${toastType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'}" aria-hidden="true"></i>
                            <span>${esc(toastMsg)}</span>
                        </div>`
                    : '';

                const noteContent = request.staffNote
                    ? `<textarea class="admin-inbox-notes" data-note-textarea rows="3" placeholder="Add a staff note…">${esc(request.staffNote)}</textarea>`
                    : `<textarea class="admin-inbox-notes" data-note-textarea rows="3" placeholder="Add a staff note…"></textarea>`;

                detail.innerHTML = `
                    <header class="admin-inbox-detail-header">
                        <span class="admin-inbox-detail-icon tone-${esc(request.tone)}">
                            <i class="bi ${esc(request.icon)}" aria-hidden="true"></i>
                        </span>
                        <div class="admin-inbox-detail-title-wrap">
                            <h2>${esc(request.title)}</h2>
                            <strong>${esc(request.guest)}</strong>
                            <p title="${esc(request.receivedFull)}">Received ${esc(request.received)}</p>
                        </div>
                        <span class="admin-inbox-status-badge status-${esc(request.statusClass)}">${esc(request.status)}</span>
                        <div class="admin-inbox-detail-menu">
                            <button type="button" class="admin-inbox-menu-button" data-menu-trigger aria-label="More options" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-three-dots" aria-hidden="true"></i>
                            </button>
                            <div class="admin-inbox-dropdown" data-menu-dropdown hidden>
                                <button type="button" class="admin-inbox-dropdown-item" data-inbox-mark-unread>
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    Mark as Unread
                                </button>
                                <a href="../timeline/timeline.php?date=${esc(request.dateRaw)}#bookingList" class="admin-inbox-dropdown-item">
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                    Open in Timeline
                                </a>
                            </div>
                        </div>
                    </header>

                    ${toast}

                    <div class="admin-inbox-detail-grid">
                        <section class="admin-inbox-detail-section">
                            <h3>Guest</h3>
                            <div class="admin-inbox-detail-meta">
                                ${metaItem('bi-person', request.guest)}
                                ${request.phone ? metaItem('bi-telephone', request.phone, phoneHref) : ''}
                                ${request.email ? metaItem('bi-envelope', request.email, mailHref) : ''}
                            </div>
                        </section>

                        <section class="admin-inbox-detail-section">
                            <h3>Booking Details</h3>
                            <div class="admin-inbox-booking-meta">
                                ${metaItem('bi-calendar3', request.date)}
                                ${metaItem('bi-clock', request.time)}
                                ${metaItem('bi-people', `${request.partySize} guest${request.partySize !== 1 ? 's' : ''}`)}
                                ${metaItem('bi-tag', request.bookingType)}
                            </div>
                        </section>
                    </div>

                    ${request.availability && request.availability.length > 0 ? `
                    <section class="admin-inbox-availability">
                        <h3>Availability by Area</h3>
                        <div class="admin-inbox-area-grid">
                            ${renderAvailability(request.availability)}
                        </div>
                    </section>` : ''}

                    <div class="admin-inbox-message-grid">
                        <section class="admin-inbox-message-section">
                            <h3><i class="bi bi-chat" aria-hidden="true"></i> Guest Message</h3>
                            ${request.message
                                ? `<div class="admin-inbox-message-card">${esc(request.message)}</div>`
                                : `<div class="admin-inbox-message-card admin-inbox-message-empty">No message provided.</div>`
                            }
                        </section>

                        <section class="admin-inbox-message-section">
                            <div class="admin-inbox-note-head">
                                <h3><i class="bi bi-journal-text" aria-hidden="true"></i> Staff Notes</h3>
                                <button type="button" class="admin-inbox-note-save" data-note-save aria-label="Save note">
                                    <i class="bi bi-floppy" aria-hidden="true"></i> Save
                                </button>
                            </div>
                            ${noteContent}
                            <span class="admin-inbox-note-status" data-note-status></span>
                        </section>
                    </div>

                    <footer class="admin-inbox-actions">
                        <button type="button" class="admin-inbox-action-btn action-confirm" data-inbox-action="confirm">
                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                            <span>Confirm</span>
                        </button>
                        <button type="button" class="admin-inbox-action-btn action-contact" data-inbox-contact>
                            <i class="bi bi-envelope" aria-hidden="true"></i>
                            <span>Email Guest</span>
                        </button>
                        <button type="button" class="admin-inbox-action-btn action-waitlist" data-inbox-action="waitlist">
                            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                            <span>Waitlist</span>
                        </button>
                        <button type="button" class="admin-inbox-action-btn action-decline" data-inbox-action="decline">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                            <span>Decline</span>
                        </button>
                    </footer>
                `;

                // Bind action buttons
                detail.querySelectorAll('[data-inbox-action]').forEach((btn) => {
                    btn.addEventListener('click', () => handleAction(btn.dataset.inboxAction));
                });

                // Email button → open mailto
                const contactBtn = detail.querySelector('[data-inbox-contact]');
                if (contactBtn) {
                    if (mailHref) {
                        contactBtn.addEventListener('click', () => { window.location.href = mailHref; });
                    } else {
                        contactBtn.disabled = true;
                        contactBtn.title = 'No email address on file';
                    }
                }

                // Note save
                const noteSaveBtn = detail.querySelector('[data-note-save]');
                const noteTextarea = detail.querySelector('[data-note-textarea]');
                const noteStatus = detail.querySelector('[data-note-status]');
                if (noteSaveBtn && noteTextarea) {
                    noteSaveBtn.addEventListener('click', async () => {
                        const noteText = noteTextarea.value.trim();
                        noteSaveBtn.disabled = true;
                        noteSaveBtn.innerHTML = '<i class="bi bi-hourglass" aria-hidden="true"></i> Saving…';
                        try {
                            const resp = await apiPost({ action: 'save_note', booking_id: request.bookingId, note: noteText });
                            request.staffNote = noteText;
                            if (noteStatus) {
                                noteStatus.textContent = resp.success ? 'Saved' : 'Failed to save';
                                noteStatus.className = `admin-inbox-note-status ${resp.success ? 'is-saved' : 'is-error'}`;
                                setTimeout(() => { noteStatus.textContent = ''; noteStatus.className = 'admin-inbox-note-status'; }, 2500);
                            }
                        } catch (e) {
                            if (noteStatus) { noteStatus.textContent = 'Error'; noteStatus.className = 'admin-inbox-note-status is-error'; }
                        }
                        noteSaveBtn.disabled = false;
                        noteSaveBtn.innerHTML = '<i class="bi bi-floppy" aria-hidden="true"></i> Save';
                    });
                }

                // Dropdown menu
                const menuTrigger = detail.querySelector('[data-menu-trigger]');
                const menuDropdown = detail.querySelector('[data-menu-dropdown]');
                if (menuTrigger && menuDropdown) {
                    menuTrigger.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const isOpen = !menuDropdown.hidden;
                        closeAnyDropdown();
                        if (!isOpen) {
                            menuDropdown.hidden = false;
                            menuTrigger.setAttribute('aria-expanded', 'true');
                            activeDropdown = menuDropdown;
                            activeMenuTrigger = menuTrigger;
                        }
                    });
                }

                // Mark as unread
                const markUnreadBtn = detail.querySelector('[data-inbox-mark-unread]');
                if (markUnreadBtn) {
                    markUnreadBtn.addEventListener('click', () => {
                        closeAnyDropdown();
                        markUnread(request.id);
                    });
                }
            };

            // ── Select a request ───────────────────────────────────────────
            const setSelected = (requestId) => {
                const request = requestMap.get(requestId);
                if (!request) return;
                selectedId = requestId;
                rows.forEach((row) => {
                    const sel = row.dataset.requestId === requestId;
                    row.classList.toggle('is-selected', sel);
                    row.setAttribute('aria-pressed', sel ? 'true' : 'false');
                });
                renderDetail(request);
                markRead(requestId);
            };

            // ── Handle confirm / decline / waitlist ────────────────────────
            const handleAction = async (action) => {
                const request = requestMap.get(selectedId);
                if (!request) return;

                const btn = detail?.querySelector(`[data-inbox-action="${action}"]`);
                if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

                try {
                    const resp = await apiPost({ action, booking_id: request.bookingId });
                    if (resp.success) {
                        request.status     = resp.statusLabel || request.status;
                        request.statusKey  = resp.status || request.statusKey;
                        request.statusClass = resp.statusClass || request.statusClass;
                        request.isRead     = true;
                        updateRowStatus(request);
                        renderDetail(request, resp.message || 'Done.', 'success');

                        // Remove from list if resolved/cancelled
                        if (action === 'confirm' || action === 'decline') {
                            const row = rows.find((r) => r.dataset.requestId === selectedId);
                            if (row) {
                                row.style.transition = 'opacity 300ms ease';
                                row.style.opacity = '0.4';
                            }
                        }
                        applyFilters();
                    } else {
                        renderDetail(request, resp.error || 'Action failed.', 'error');
                    }
                } catch (e) {
                    renderDetail(request, 'Network error. Try again.', 'error');
                } finally {
                    if (btn) { btn.disabled = false; btn.style.opacity = ''; }
                }
            };

            // ── Filter + sort ──────────────────────────────────────────────
            const visibleRows = () => rows.filter((r) => r.style.display !== 'none');

            const applyFilters = () => {
                const sortValue = sortSelect?.value || 'newest';

                const sorted = rows.slice().sort((a, b) => {
                    if (sortValue === 'oldest')     return +a.dataset.order - +b.dataset.order;
                    if (sortValue === 'party-desc') return +b.dataset.partySize - +a.dataset.partySize;
                    if (sortValue === 'party-asc')  return +a.dataset.partySize - +b.dataset.partySize;
                    if (sortValue === 'unread') {
                        const aUnread = requestMap.get(a.dataset.requestId)?.isRead ? 1 : 0;
                        const bUnread = requestMap.get(b.dataset.requestId)?.isRead ? 1 : 0;
                        if (aUnread !== bUnread) return aUnread - bUnread;
                        return +a.dataset.order - +b.dataset.order;
                    }
                    return +a.dataset.order - +b.dataset.order;
                });

                sorted.forEach((row) => list?.appendChild(row));

                rows.forEach((row) => {
                    const req = requestMap.get(row.dataset.requestId);
                    if (!req) { row.style.display = 'none'; return; }

                    const matchesTab = activeTab === 'all'
                        || (activeTab === 'unread'     && !req.isRead)
                        || (activeTab === 'action'     && req.statusKey === 'pending')
                        || (activeTab === 'unassigned' && req.typeKey === 'no-table')
                        || (activeTab === 'function'   && req.typeKey === 'function');
                    const matchesSearch = searchQuery === ''
                        || (row.dataset.searchText || '').includes(searchQuery)
                        || req.guest.toLowerCase().includes(searchQuery)
                        || req.preview.toLowerCase().includes(searchQuery);

                    row.style.display = (matchesTab && matchesSearch) ? '' : 'none';
                });

                const shown = visibleRows();
                if (countEl) countEl.textContent = `${shown.length} request${shown.length !== 1 ? 's' : ''}`;
                if (emptyEl) emptyEl.hidden = shown.length > 0;

                // Update each tab's count chip live
                const allReqs = Array.from(requestMap.values());
                tabs.forEach((tab) => {
                    const key = tab.dataset.inboxTab;
                    let n = 0;
                    if (key === 'all')        n = allReqs.length;
                    if (key === 'unread')     n = allReqs.filter((r) => !r.isRead).length;
                    if (key === 'action')     n = allReqs.filter((r) => r.statusKey === 'pending').length;
                    if (key === 'unassigned') n = allReqs.filter((r) => r.typeKey === 'no-table').length;
                    let chip = tab.querySelector('.admin-inbox-tab-count');
                    if (n > 0) {
                        if (!chip) {
                            chip = document.createElement('span');
                            chip.className = 'admin-inbox-tab-count';
                            tab.appendChild(chip);
                        }
                        chip.textContent = Math.min(n, 99);
                    } else if (chip) {
                        chip.remove();
                    }
                });

                if (!shown.some((r) => r.dataset.requestId === selectedId) && shown[0]) {
                    setSelected(shown[0].dataset.requestId);
                }
            };

            // ── Event wiring ───────────────────────────────────────────────
            rows.forEach((row) => {
                row.addEventListener('click', () => setSelected(row.dataset.requestId));
            });

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    activeTab = tab.dataset.inboxTab || 'all';
                    tabs.forEach((t) => {
                        const active = t === tab;
                        t.classList.toggle('is-active', active);
                        active ? t.setAttribute('aria-current', 'page') : t.removeAttribute('aria-current');
                    });
                    applyFilters();
                });
            });

            sortSelect?.addEventListener('change', applyFilters);

            searchInput?.addEventListener('input', () => {
                searchQuery = (searchInput.value || '').trim().toLowerCase();
                applyFilters();
            });

            // Initial render
            if (selectedId) {
                setSelected(selectedId);
            }
            applyFilters();
        })();
    </script>
</body>
</html>
