<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);

$todayDate = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $todayDate;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || strtotime($selectedDate) === false) {
    $selectedDate = $todayDate;
}

$allowedViews = ['list', 'timeline', 'tables'];
$selectedView = strtolower(trim((string) ($_GET['view'] ?? 'list')));
if (!in_array($selectedView, $allowedViews, true)) {
    $selectedView = 'list';
}

$allowedBookingModes = ['bookings', 'requests'];
$selectedBookingMode = strtolower(trim((string) ($_GET['mode'] ?? 'bookings')));
if (!in_array($selectedBookingMode, $allowedBookingModes, true)) {
    $selectedBookingMode = 'bookings';
}

$selectedTimestamp = strtotime($selectedDate) ?: time();
$previousDate = date('Y-m-d', strtotime('-1 day', $selectedTimestamp));
$nextDate = date('Y-m-d', strtotime('+1 day', $selectedTimestamp));
$adminName = $_SESSION['name'] ?? 'Admin';

$homeDateUrl = static function (string $dateValue) use ($selectedView, $selectedBookingMode): string {
    return 'home.php?date=' . urlencode($dateValue) . '&view=' . urlencode($selectedView) . '&mode=' . urlencode($selectedBookingMode);
};

$homeViewUrl = static function (string $viewName) use ($selectedDate, $selectedBookingMode): string {
    return 'home.php?date=' . urlencode($selectedDate) . '&view=' . urlencode($viewName) . '&mode=' . urlencode($selectedBookingMode);
};

$homeModeUrl = static function (string $modeName) use ($selectedDate, $selectedView): string {
    return 'home.php?date=' . urlencode($selectedDate) . '&view=' . urlencode($selectedView) . '&mode=' . urlencode($modeName);
};

$timelineEmbedUrl = '../timeline/timeline.php?date=' . urlencode($selectedDate) . '&embed=1';

$formatDateLabel = static function (string $dateValue): string {
    $timestamp = strtotime($dateValue);
    return $timestamp ? date('D, j M', $timestamp) : 'Upcoming';
};

$formatQueueTime = static function (?string $dateValue, ?string $timeValue) use ($todayDate, $formatDateLabel): string {
    $dateValue = (string) ($dateValue ?? '');
    $timeValue = (string) ($timeValue ?? '');
    $timestamp = strtotime(trim($dateValue . ' ' . $timeValue));
    $timeLabel = $timestamp ? date('g:i A', $timestamp) : 'Time TBC';

    if ($dateValue === $todayDate) {
        return 'Today, ' . $timeLabel;
    }

    return $formatDateLabel($dateValue) . ', ' . $timeLabel;
};

$getInitials = static function (string $name): string {
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

    return $letters !== '' ? $letters : 'DM';
};

$normalizeAreaName = static function (string $value): string {
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
};

$resolveZoneKey = static function (string $name) use ($normalizeAreaName): string {
    $normalized = $normalizeAreaName($name);

    if (in_array($normalized, ['osf', 'osfpatio', 'outsidepatio'], true)) {
        return 'osf';
    }

    if (in_array($normalized, ['kookaburra', 'kookabura'], true)) {
        return 'kookaburra';
    }

    if (in_array($normalized, ['mainbar', 'bararea', 'bar', 'maindining', 'dining'], true)) {
        return 'main-bar';
    }

    if ($normalized === 'stables') {
        return 'stables';
    }

    if ($normalized === 'wisteria') {
        return 'wisteria';
    }

    if (in_array($normalized, ['schumack', 'schumacher', 'schumach'], true)) {
        return 'schumack';
    }

    return 'osf';
};

$resolveTableShape = static function (array $table): string {
    $shape = strtolower(trim((string) ($table['table_shape'] ?? 'auto')));
    $aliases = [
        'circle' => 'circle',
        'square' => 'square',
        'rect' => 'rect-horizontal',
        'rectangle' => 'rect-horizontal',
        'rect-h' => 'rect-horizontal',
        'horizontal' => 'rect-horizontal',
        'rect-horizontal' => 'rect-horizontal',
        'rect-v' => 'rect-vertical',
        'vertical' => 'rect-vertical',
        'rect-vertical' => 'rect-vertical',
    ];

    if (isset($aliases[$shape])) {
        return $aliases[$shape];
    }

    $capacity = (int) ($table['capacity'] ?? 0);
    if ($capacity <= 2) {
        return 'circle';
    }

    if ($capacity <= 4) {
        return 'square';
    }

    return $capacity >= 8 ? 'rect-horizontal' : 'rect-vertical';
};

$dashboardStatsStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END), 0) AS total_bookings,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN number_of_guests ELSE 0 END), 0) AS total_guests,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' AND start_time >= '12:00:00' AND start_time < '17:00:00' THEN 1 ELSE 0 END), 0) AS lunch_bookings,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' AND start_time >= '12:00:00' AND start_time < '17:00:00' THEN number_of_guests ELSE 0 END), 0) AS lunch_guests,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' AND start_time >= '17:00:00' THEN 1 ELSE 0 END), 0) AS dinner_bookings,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' AND start_time >= '17:00:00' THEN number_of_guests ELSE 0 END), 0) AS dinner_guests
    FROM bookings
    WHERE booking_date = ?
");
$dashboardStatsStmt->execute([$selectedDate]);
$dashboardStats = $dashboardStatsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$selectedBookingsCount = (int) ($dashboardStats['total_bookings'] ?? 0);
$selectedGuestsCount = (int) ($dashboardStats['total_guests'] ?? 0);
$selectedLunchCount = (int) ($dashboardStats['lunch_bookings'] ?? 0);
$selectedLunchGuestsCount = (int) ($dashboardStats['lunch_guests'] ?? 0);
$selectedDinnerCount = (int) ($dashboardStats['dinner_bookings'] ?? 0);
$selectedDinnerGuestsCount = (int) ($dashboardStats['dinner_guests'] ?? 0);
$pendingBookingsCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();

$queueStmt = $pdo->prepare("
    SELECT
        b.booking_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.requested_start_time,
        b.requested_end_time,
        b.number_of_guests,
        b.status,
        b.reservation_card_status,
        b.created_at,
        COALESCE(b.booking_type, 'normal') AS booking_type,
        b.special_request,
        COALESCE(NULLIF(b.customer_email, ''), u.email, '') AS customer_email,
        COALESCE(NULLIF(b.customer_phone, ''), u.phone, '') AS customer_phone,
        COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name,
        COALESCE(
            GROUP_CONCAT(DISTINCT assigned_tables.table_number ORDER BY assigned_tables.table_number + 0, assigned_tables.table_number SEPARATOR ', '),
            direct_table.table_number
        ) AS assigned_table_numbers,
        COALESCE(
            GROUP_CONCAT(DISTINCT assigned_tables.table_id ORDER BY assigned_tables.table_number + 0, assigned_tables.table_number SEPARATOR ','),
            direct_table.table_id
        ) AS assigned_table_ids,
        COALESCE(
            GROUP_CONCAT(DISTINCT table_areas.name ORDER BY table_areas.display_order ASC, table_areas.name ASC SEPARATOR ', '),
            direct_area.name,
            'Dining room'
        ) AS assigned_area_names
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
    LEFT JOIN restaurant_tables assigned_tables ON bta.table_id = assigned_tables.table_id
    LEFT JOIN table_areas ON assigned_tables.area_id = table_areas.area_id
    LEFT JOIN restaurant_tables direct_table ON b.table_id = direct_table.table_id
    LEFT JOIN table_areas direct_area ON direct_table.area_id = direct_area.area_id
    WHERE b.booking_date = ?
      AND b.status IN ('pending', 'confirmed')
    GROUP BY b.booking_id
    ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC
");
$queueStmt->execute([$selectedDate]);
$allQueueBookings = $queueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$requestBookings = array_values(array_filter($allQueueBookings, static function (array $booking): bool {
    return strtolower((string) ($booking['status'] ?? 'pending')) === 'pending';
}));
usort($requestBookings, static function (array $left, array $right): int {
    $leftCreated = strtotime((string) ($left['created_at'] ?? '')) ?: PHP_INT_MAX;
    $rightCreated = strtotime((string) ($right['created_at'] ?? '')) ?: PHP_INT_MAX;

    if ($leftCreated === $rightCreated) {
        return (int) ($left['booking_id'] ?? 0) <=> (int) ($right['booking_id'] ?? 0);
    }

    return $leftCreated <=> $rightCreated;
});
$confirmedBookings = array_values(array_filter($allQueueBookings, static function (array $booking): bool {
    return strtolower((string) ($booking['status'] ?? 'pending')) !== 'pending';
}));
$queueBookings = $selectedBookingMode === 'requests' ? $requestBookings : $confirmedBookings;

$triviaBookings = array_values(array_filter($queueBookings, static function (array $booking): bool {
    return normalizeBookingType($booking['booking_type'] ?? 'normal') === 'trivia';
}));
$functionBookings = array_values(array_filter($queueBookings, static function (array $booking): bool {
    return normalizeBookingType($booking['booking_type'] ?? 'normal') === 'function';
}));

$bookingEditPayload = static function (array $booking): string {
    $assignedTableIds = array_values(array_filter(array_map('intval', explode(',', (string) ($booking['assigned_table_ids'] ?? '')))));
    $payload = [
        'booking_id' => (int) ($booking['booking_id'] ?? 0),
        'booking_date' => (string) ($booking['booking_date'] ?? ''),
        'start_time' => (string) ($booking['start_time'] ?? ''),
        'end_time' => (string) ($booking['end_time'] ?? ''),
        'requested_start_time' => (string) ($booking['requested_start_time'] ?? $booking['start_time'] ?? ''),
        'requested_end_time' => (string) ($booking['requested_end_time'] ?? $booking['end_time'] ?? ''),
        'number_of_guests' => (int) ($booking['number_of_guests'] ?? 0),
        'booking_type' => normalizeBookingType($booking['booking_type'] ?? 'normal'),
        'special_request' => (string) ($booking['special_request'] ?? ''),
        'status' => (string) ($booking['status'] ?? 'pending'),
        'customer_name' => (string) ($booking['customer_name'] ?? 'Guest'),
        'customer_email' => (string) ($booking['customer_email'] ?? ''),
        'customer_phone' => (string) ($booking['customer_phone'] ?? ''),
        'table_id' => $assignedTableIds[0] ?? null,
    ];

    return htmlspecialchars(json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
};

$tableRows = $pdo->query("
    SELECT
        rt.table_id,
        rt.table_number,
        rt.capacity,
        rt.area_id,
        rt.sort_order,
        rt.status,
        COALESCE(rt.reservable, 1) AS reservable,
        rt.layout_x,
        rt.layout_y,
        COALESCE(rt.table_shape, 'auto') AS table_shape,
        COALESCE(ta.name, 'Dining room') AS area_name
    FROM restaurant_tables rt
    LEFT JOIN table_areas ta ON rt.area_id = ta.area_id
    ORDER BY COALESCE(ta.display_order, 9999) ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$areaRows = $pdo->query("
    SELECT area_id, name, display_order, layout_x, layout_y, layout_width, layout_height, label_layout_x, label_layout_y
    FROM table_areas
    WHERE is_active = 1
    ORDER BY display_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$bookingsByTableId = [];
$unassignedBookings = [];
foreach ($queueBookings as $booking) {
    $assignedTableIds = array_values(array_filter(array_map('intval', explode(',', (string) ($booking['assigned_table_ids'] ?? '')))));

    if (empty($assignedTableIds)) {
        $unassignedBookings[] = $booking;
        continue;
    }

    foreach ($assignedTableIds as $tableId) {
        $bookingsByTableId[$tableId] ??= [];
        $bookingsByTableId[$tableId][] = $booking;
    }
}

$floorBlueprints = [
    'stables' => ['label' => 'Stables', 'tone' => 'amber', 'x' => 42, 'y' => 16, 'width' => 166, 'height' => 92],
    'kookaburra' => ['label' => 'Kookaburra', 'tone' => 'green', 'x' => 42, 'y' => 126, 'width' => 112, 'height' => 138],
    'wisteria' => ['label' => 'Wisteria', 'tone' => 'pink', 'x' => 478, 'y' => 14, 'width' => 176, 'height' => 156],
    'schumack' => ['label' => 'Schumack', 'tone' => 'blue', 'x' => 478, 'y' => 194, 'width' => 188, 'height' => 118],
    'main-bar' => ['label' => 'Main Bar', 'tone' => 'lavender', 'x' => 176, 'y' => 190, 'width' => 316, 'height' => 184],
    'osf' => ['label' => 'OSF', 'tone' => 'mocha', 'x' => 42, 'y' => 392, 'width' => 644, 'height' => 192],
];

$areasById = [];
$floorZones = [];
foreach ($areaRows as $area) {
    $areaId = (int) ($area['area_id'] ?? 0);
    $zoneKey = $resolveZoneKey((string) ($area['name'] ?? ''));
    $blueprint = $floorBlueprints[$zoneKey] ?? $floorBlueprints['osf'];
    $zone = [
        'area_id' => $areaId,
        'name' => (string) ($area['name'] ?? $blueprint['label']),
        'label' => (string) ($area['name'] ?? $blueprint['label']),
        'tone' => $blueprint['tone'],
        'zone_key' => $zoneKey,
        'x' => $area['layout_x'] !== null ? (int) $area['layout_x'] : (int) $blueprint['x'],
        'y' => $area['layout_y'] !== null ? (int) $area['layout_y'] : (int) $blueprint['y'],
        'width' => $area['layout_width'] !== null ? (int) $area['layout_width'] : (int) $blueprint['width'],
        'height' => $area['layout_height'] !== null ? (int) $area['layout_height'] : (int) $blueprint['height'],
    ];
    $zone['label_x'] = $area['label_layout_x'] !== null ? (int) $area['label_layout_x'] : (int) round($zone['x'] + ($zone['width'] / 2));
    $zone['label_y'] = $area['label_layout_y'] !== null ? (int) $area['label_layout_y'] : (int) ($zone['y'] + 14);

    $areasById[$areaId] = $zone;
    $floorZones[] = $zone;
}

$tablesByAreaForLayout = [];
foreach ($tableRows as $table) {
    $areaId = (int) ($table['area_id'] ?? 0);
    $tablesByAreaForLayout[$areaId] ??= [];
    $tablesByAreaForLayout[$areaId][] = $table;
}

$floorTables = [];
foreach ($tableRows as $table) {
    $areaId = (int) ($table['area_id'] ?? 0);
    $zone = $areasById[$areaId] ?? null;
    $areaTables = $tablesByAreaForLayout[$areaId] ?? [];
    $tableIndex = 0;
    foreach ($areaTables as $index => $areaTable) {
        if ((int) ($areaTable['table_id'] ?? 0) === (int) ($table['table_id'] ?? 0)) {
            $tableIndex = $index;
            break;
        }
    }

    $isOsf = $zone && ($zone['zone_key'] ?? '') === 'osf';
    $columns = $isOsf ? 4 : 2;
    $gutterX = $isOsf ? 76 : 70;
    $gutterY = $isOsf ? 60 : 68;
    $layoutX = $table['layout_x'] !== null ? (int) $table['layout_x'] : null;
    $layoutY = $table['layout_y'] !== null ? (int) $table['layout_y'] : null;

    if (($layoutX === null || $layoutY === null) && $zone) {
        $layoutX = min((int) $zone['x'] + (int) $zone['width'] - 40, (int) $zone['x'] + 34 + ($tableIndex % $columns) * $gutterX);
        $layoutY = min((int) $zone['y'] + (int) $zone['height'] - 40, (int) $zone['y'] + 34 + floor($tableIndex / $columns) * $gutterY);
    }

    $tableId = (int) ($table['table_id'] ?? 0);
    $tableBookings = $bookingsByTableId[$tableId] ?? [];
    $floorTables[] = array_merge($table, [
        'layout_x' => $layoutX,
        'layout_y' => $layoutY,
        'shape' => $resolveTableShape($table),
        'tone' => $zone['tone'] ?? 'blue',
        'bookings' => $tableBookings,
        'is_occupied' => !empty($tableBookings),
    ]);
}

$viewMeta = [
    'list' => [
        'label' => $selectedBookingMode === 'requests' ? 'Requests' : 'Bookings',
        'icon' => 'fa-list',
        'title' => $selectedBookingMode === 'requests' ? 'Booking Requests' : 'Bookings',
        'note' => number_format(count($queueBookings)) . ' ' . ($selectedBookingMode === 'requests' ? 'requests' : 'bookings') . ' on ' . $formatDateLabel($selectedDate),
    ],
    'timeline' => [
        'label' => 'Timeline',
        'icon' => 'fa-timeline',
        'title' => 'Timeline View',
        'note' => 'Chronological service flow for ' . $formatDateLabel($selectedDate),
    ],
    'tables' => [
        'label' => 'Table',
        'icon' => 'fa-table-cells-large',
        'title' => 'Table View',
        'note' => number_format(count($tableRows)) . ' tables for ' . $formatDateLabel($selectedDate),
    ],
];

$bookingModeMeta = [
    'bookings' => [
        'label' => 'Bookings',
        'icon' => 'fa-calendar-check',
    ],
    'requests' => [
        'label' => 'Requests',
        'icon' => 'fa-inbox',
    ],
];

$adminPageTitle = $bookingModeMeta[$selectedBookingMode]['label'];
$adminSidebarActive = 'home';
$adminSidebarPathPrefix = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <style>
        .home-page {
            background: #f8fafc;
            color: var(--dm-text);
        }

        .home-shell {
            display: grid;
            gap: 20px;
            max-width: 1440px;
            margin: 0 auto;
        }

        .home-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--dm-border);
        }

        .home-title-wrap {
            min-width: 0;
        }

        .home-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .home-mode-menu {
            position: relative;
            width: fit-content;
            max-width: 100%;
        }

        .home-mode-menu summary {
            list-style: none;
            cursor: pointer;
        }

        .home-mode-menu summary::-webkit-details-marker {
            display: none;
        }

        .home-title-row h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .home-title-row i {
            color: var(--dm-text-soft);
            font-size: 13px;
        }

        .home-subtitle {
            margin: 4px 0 0;
            color: var(--dm-text-muted);
            font-size: 13px;
        }

        .home-mode-menu-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            z-index: 45;
            display: grid;
            min-width: 170px;
            padding: 6px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
            box-shadow: var(--dm-shadow-md);
        }

        .home-mode-menu:not([open]) .home-mode-menu-panel {
            display: none;
        }

        .home-mode-option {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 11px;
            border-radius: var(--dm-radius-xs);
            color: var(--dm-text);
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .home-mode-option:hover,
        .home-mode-option.is-active {
            background: var(--dm-surface-muted);
            color: var(--dm-text);
        }

        .home-mode-option i {
            width: 16px;
            color: var(--dm-text-muted);
            text-align: center;
        }

        .home-header-actions,
        .home-toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: nowrap;
            justify-content: flex-end;
        }

        .home-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            padding: 8px 13px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
            color: var(--dm-text);
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
            text-decoration: none;
            white-space: nowrap;
        }

        .home-button:hover {
            background: var(--dm-surface-muted);
            color: var(--dm-text);
        }

        .home-button-primary {
            border-color: var(--dm-primary);
            background: var(--dm-primary);
            color: var(--dm-primary-text);
            box-shadow: 0 8px 18px rgba(19, 231, 150, 0.18);
        }

        .home-button-primary:hover {
            border-color: var(--dm-primary-hover);
            background: var(--dm-primary-hover);
            color: var(--dm-primary-text);
        }

        .home-notification-button {
            position: relative;
        }

        .home-notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            border: 2px solid var(--dm-surface);
            border-radius: 999px;
            background: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 900;
            line-height: 1;
        }

        .home-date-form {
            display: flex;
            align-items: center;
            gap: 7px;
            flex: 0 0 auto;
            flex-wrap: nowrap;
        }

        .home-date-form .home-date-input {
            flex: 0 0 168px;
            width: 168px;
            min-width: 168px;
            max-width: 168px;
            min-height: 38px;
            padding: 8px 10px;
            border-radius: var(--dm-radius-sm);
            font-size: 13px;
            font-weight: 700;
        }

        .home-date-nav {
            flex: 0 0 38px;
            width: 38px;
            min-height: 38px;
            padding: 0;
        }

        .home-date-today {
            flex: 0 0 auto;
            min-height: 38px;
        }

        .home-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .home-chip-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .home-metric-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 7px 11px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
            color: var(--dm-text);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .home-metric-chip span {
            color: var(--dm-text-muted);
            font-weight: 700;
        }

        .home-metric-main {
            display: inline-flex;
            align-items: baseline;
            gap: 5px;
        }

        .home-metric-main strong {
            color: var(--dm-text);
            font-size: 13px;
            font-weight: 900;
        }

        .home-metric-guests {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 24px;
            padding: 4px 8px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-xs);
            background: #fbfbfc;
            color: var(--dm-text);
            font-weight: 900;
        }

        .home-metric-guests i,
        .home-metric-guests strong {
            color: var(--dm-text);
        }

        .home-metric-guests i {
            font-size: 12px;
        }

        .home-metric-dot {
            width: 8px;
            height: 8px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: var(--dm-primary);
        }

        .home-metric-dot.warning {
            background: #f59e0b;
        }

        .home-metric-dot.info {
            background: #38bdf8;
        }

        .home-metric-dot.soft {
            background: #a78bfa;
        }

        .home-search {
            position: relative;
            flex: 0 0 200px;
            width: 200px;
        }

        .home-search i {
            position: absolute;
            left: 13px;
            top: 50%;
            color: var(--dm-text-soft);
            font-size: 13px;
            transform: translateY(-50%);
        }

        .home-search input {
            min-height: 38px;
            padding: 8px 12px 8px 36px;
            border-radius: var(--dm-radius-sm);
            font-size: 13px;
        }

        .home-view-menu {
            position: relative;
            flex: 0 0 auto;
        }

        .home-view-menu summary {
            list-style: none;
            cursor: pointer;
        }

        .home-view-menu summary::-webkit-details-marker {
            display: none;
        }

        .home-view-menu-panel {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 40;
            display: grid;
            min-width: 180px;
            padding: 6px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
            box-shadow: var(--dm-shadow-md);
        }

        .home-view-menu:not([open]) .home-view-menu-panel {
            display: none;
        }

        .home-view-option {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 11px;
            border-radius: var(--dm-radius-xs);
            color: var(--dm-text);
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .home-view-option:hover,
        .home-view-option.is-active {
            background: var(--dm-surface-muted);
            color: var(--dm-text);
        }

        .home-view-option i {
            width: 16px;
            color: var(--dm-text-muted);
            text-align: center;
        }

        .home-view-title-menu {
            width: fit-content;
            max-width: 100%;
        }

        .home-view-title-menu .home-view-menu-panel {
            left: 0;
            right: auto;
        }

        .home-view-title-trigger {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            gap: 8px;
            padding: 0;
            border: 0;
            background: transparent;
            color: var(--dm-text);
            font-size: 15px;
            font-weight: 800;
            line-height: 1.2;
            cursor: pointer;
        }

        .home-view-title-trigger i {
            flex: 0 0 auto;
            color: var(--dm-text-muted);
            font-size: 12px;
        }

        .home-card {
            overflow: hidden;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
            box-shadow: var(--dm-shadow-sm);
        }

        .home-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--dm-border);
        }

        .home-card-heading {
            min-width: 0;
        }

        .home-card-title {
            margin: 0;
            color: var(--dm-text);
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .home-card-note {
            margin: 4px 0 0;
            color: var(--dm-text-muted);
            font-size: 12px;
        }

        .home-queue {
            display: grid;
            max-height: 410px;
            overflow: auto;
        }

        .home-queue-row {
            display: grid;
            grid-template-columns: minmax(260px, 1.15fr) minmax(220px, 0.9fr) minmax(210px, 0.8fr);
            gap: 18px;
            align-items: center;
            min-height: 76px;
            padding: 13px 18px;
            border-bottom: 1px solid var(--dm-border);
            color: var(--dm-text);
            text-decoration: none;
        }

        .home-queue-row:last-child {
            border-bottom: 0;
        }

        .home-queue-row:hover {
            background: #f9fafb;
            color: var(--dm-text);
        }

        .home-reservation-main {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
        }

        .home-avatar {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--dm-border);
            border-radius: 50%;
            background: var(--dm-primary-soft);
            color: var(--dm-primary-text);
            font-size: 12px;
            font-weight: 900;
        }

        .home-avatar.tone-1 {
            background: #e0f2fe;
            color: #075985;
        }

        .home-avatar.tone-2 {
            background: #fef3c7;
            color: #92400e;
        }

        .home-avatar.tone-3 {
            background: #fee2e2;
            color: #991b1b;
        }

        .home-avatar.tone-4 {
            background: #ede9fe;
            color: #5b21b6;
        }

        .home-reservation-name {
            margin: 0;
            overflow: hidden;
            color: var(--dm-text);
            font-size: 14px;
            font-weight: 900;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .home-reservation-copy {
            min-width: 0;
        }

        .home-reservation-meta,
        .home-reservation-note,
        .home-reservation-context {
            color: var(--dm-text-muted);
            font-size: 12px;
            line-height: 1.45;
        }

        .home-reservation-note {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .home-reservation-context {
            display: grid;
            gap: 3px;
            min-width: 0;
        }

        .home-reservation-context strong {
            overflow: hidden;
            color: var(--dm-text);
            font-size: 13px;
            font-weight: 800;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .home-reservation-status {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            min-width: 0;
        }

        .home-guest-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 32px;
            padding: 6px 10px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            color: var(--dm-text);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .home-confirm-request {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            border: 1px solid var(--dm-confirmed-border, #86efac);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-confirmed-bg, #dcfce7);
            color: var(--dm-confirmed-text, #166534);
            cursor: pointer;
            font-size: 13px;
        }

        .home-confirm-request:hover {
            filter: brightness(0.98);
        }

        .home-confirm-request.is-saving {
            cursor: wait;
            opacity: 0.7;
        }

        .home-empty {
            padding: 34px 18px;
            color: var(--dm-text-muted);
            font-size: 13px;
            text-align: center;
        }

        .home-timeline-view {
            display: grid;
            max-height: 520px;
            overflow: auto;
        }

        .home-timeline-row {
            display: grid;
            grid-template-columns: 112px 32px minmax(0, 1fr);
            gap: 12px;
            align-items: stretch;
            padding: 14px 18px;
            border-bottom: 1px solid var(--dm-border);
            color: var(--dm-text);
            text-decoration: none;
        }

        .home-timeline-row:last-child {
            border-bottom: 0;
        }

        .home-timeline-row:hover {
            background: #f9fafb;
            color: var(--dm-text);
        }

        .home-timeline-time {
            display: grid;
            align-content: center;
            gap: 2px;
            white-space: nowrap;
        }

        .home-timeline-time strong {
            color: var(--dm-text);
            font-size: 13px;
            font-weight: 900;
        }

        .home-timeline-time span {
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .home-timeline-rail {
            position: relative;
            display: flex;
            justify-content: center;
        }

        .home-timeline-rail::before {
            content: '';
            width: 2px;
            min-height: 72px;
            background: var(--dm-border);
        }

        .home-timeline-dot {
            position: absolute;
            top: 50%;
            width: 12px;
            height: 12px;
            border: 3px solid var(--dm-surface);
            border-radius: 50%;
            background: var(--dm-primary);
            box-shadow: 0 0 0 1px var(--dm-border);
            transform: translateY(-50%);
        }

        .home-timeline-card {
            display: grid;
            gap: 8px;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
        }

        .home-timeline-card-top,
        .home-timeline-card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-width: 0;
        }

        .home-timeline-card-title {
            overflow: hidden;
            color: var(--dm-text);
            font-size: 14px;
            font-weight: 900;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .home-timeline-card-meta {
            justify-content: flex-start;
            flex-wrap: wrap;
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .home-table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 12px;
            max-height: 560px;
            overflow: auto;
            padding: 16px;
        }

        .home-table-card {
            display: grid;
            gap: 12px;
            align-content: start;
            min-height: 156px;
            padding: 14px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
        }

        .home-table-card.is-empty {
            background: #fbfbfc;
        }

        .home-table-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .home-table-name {
            color: var(--dm-text);
            font-size: 14px;
            font-weight: 900;
        }

        .home-table-area {
            margin-top: 2px;
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .home-table-capacity {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 28px;
            padding: 5px 8px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-xs);
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .home-table-bookings {
            display: grid;
            gap: 8px;
        }

        .home-table-booking {
            display: grid;
            gap: 3px;
            padding: 9px 10px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-xs);
            background: var(--dm-primary-soft);
            color: var(--dm-primary-text);
            text-decoration: none;
        }

        .home-table-booking:hover {
            color: var(--dm-primary-text);
            filter: brightness(0.98);
        }

        .home-table-booking strong {
            overflow: hidden;
            font-size: 12px;
            font-weight: 900;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .home-table-booking span,
        .home-table-empty {
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .home-table-empty {
            align-self: end;
            padding: 9px 10px;
            border: 1px dashed var(--dm-border-strong);
            border-radius: var(--dm-radius-xs);
            text-align: center;
        }

        .home-floor-wrap {
            display: grid;
            gap: 12px;
            padding: 16px;
            background: #fbfcfd;
        }

        .home-floor-viewport {
            overflow: auto;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background:
                linear-gradient(90deg, rgba(80, 92, 118, 0.045) 1px, transparent 1px),
                linear-gradient(0deg, rgba(80, 92, 118, 0.045) 1px, transparent 1px),
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.98), rgba(244, 246, 250, 0.98));
            background-size: 24px 24px, 24px 24px, 100% 100%;
        }

        .home-floor-canvas {
            position: relative;
            width: 860px;
            height: 600px;
            margin: 0 auto;
        }

        .home-floor-zone {
            position: absolute;
            border: 2px dashed rgba(163, 176, 196, 0.58);
            border-radius: 22px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.82), rgba(247, 250, 255, 0.72));
        }

        .home-floor-zone.tone-lavender { background: linear-gradient(180deg, rgba(139, 115, 238, 0.08), rgba(139, 115, 238, 0.03)); }
        .home-floor-zone.tone-green { background: linear-gradient(180deg, rgba(126, 207, 145, 0.1), rgba(126, 207, 145, 0.04)); }
        .home-floor-zone.tone-blue { background: linear-gradient(180deg, rgba(95, 169, 243, 0.09), rgba(95, 169, 243, 0.04)); }
        .home-floor-zone.tone-amber { background: linear-gradient(180deg, rgba(255, 191, 69, 0.1), rgba(255, 191, 69, 0.04)); }
        .home-floor-zone.tone-pink { background: linear-gradient(180deg, rgba(255, 119, 183, 0.09), rgba(255, 119, 183, 0.04)); }
        .home-floor-zone.tone-mocha { background: linear-gradient(180deg, rgba(160, 120, 90, 0.1), rgba(160, 120, 90, 0.04)); }

        .home-floor-label {
            position: absolute;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            max-width: 150px;
            padding: 7px 13px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.88);
            color: var(--dm-text);
            box-shadow: 0 8px 20px rgba(55, 72, 105, 0.1);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.03em;
            line-height: 1.1;
            text-transform: uppercase;
            white-space: nowrap;
            transform: translate(-50%, 0);
        }

        .home-floor-table {
            position: absolute;
            z-index: 4;
            display: grid;
            place-items: center;
            border: 0;
            background: transparent;
            color: rgba(95, 169, 243, 0.52);
            padding: 0;
            text-decoration: none;
            transform: translate(-50%, -50%);
            transition: transform 0.14s ease, filter 0.14s ease;
        }

        .home-floor-table:hover {
            color: rgba(95, 169, 243, 0.62);
            filter: drop-shadow(0 14px 24px rgba(17, 24, 39, 0.16));
            transform: translate(-50%, -50%) scale(1.03);
        }

        .home-floor-table.tone-lavender { color: rgba(139, 115, 238, 0.52); }
        .home-floor-table.tone-green { color: rgba(126, 207, 145, 0.62); }
        .home-floor-table.tone-blue { color: rgba(95, 169, 243, 0.58); }
        .home-floor-table.tone-amber { color: rgba(255, 191, 69, 0.66); }
        .home-floor-table.tone-pink { color: rgba(255, 119, 183, 0.56); }
        .home-floor-table.tone-mocha { color: rgba(160, 120, 90, 0.62); }

        .home-floor-table.is-occupied {
            color: rgba(19, 231, 150, 0.86);
        }

        .home-floor-table.is-occupied .home-floor-table-top {
            background: linear-gradient(180deg, var(--dm-primary-soft), #ffffff);
            box-shadow: 0 12px 22px rgba(19, 231, 150, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }

        .home-floor-table.is-unreservable {
            opacity: 0.55;
        }

        .home-floor-table.shape-circle {
            width: 56px;
            height: 56px;
        }

        .home-floor-table.shape-square {
            width: 60px;
            height: 60px;
        }

        .home-floor-table.shape-rect-horizontal {
            width: 78px;
            height: 52px;
        }

        .home-floor-table.shape-rect-vertical {
            width: 52px;
            height: 78px;
        }

        .home-floor-table-shell {
            position: relative;
            display: grid;
            place-items: center;
            width: 100%;
            height: 100%;
        }

        .home-floor-table-top {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 2px;
            border: 2px solid currentColor;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 255, 0.92));
            box-shadow: 0 8px 18px rgba(59, 72, 98, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }

        .shape-circle .home-floor-table-top {
            width: 38px;
            height: 38px;
            border-radius: 50%;
        }

        .shape-square .home-floor-table-top {
            width: 40px;
            height: 40px;
            border-radius: 12px;
        }

        .shape-rect-horizontal .home-floor-table-top {
            width: 52px;
            height: 34px;
            border-radius: 12px;
        }

        .shape-rect-vertical .home-floor-table-top {
            width: 34px;
            height: 52px;
            border-radius: 12px;
        }

        .home-floor-table-number {
            color: var(--dm-text);
            font-size: 11px;
            font-weight: 900;
            line-height: 1;
        }

        .home-floor-table-meta {
            color: var(--dm-text-muted);
            font-size: 9px;
            font-weight: 800;
            line-height: 1;
        }

        .home-floor-booking-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            z-index: 5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border: 2px solid var(--dm-surface);
            border-radius: 999px;
            background: var(--dm-primary);
            color: var(--dm-primary-text);
            font-size: 10px;
            font-weight: 900;
        }

        .home-floor-chair {
            position: absolute;
            z-index: 1;
            width: 10px;
            height: 8px;
            border: 2px solid rgba(121, 136, 164, 0.45);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 4px 10px rgba(59, 72, 98, 0.12);
        }

        .home-floor-chair.top { top: 5px; left: 50%; transform: translateX(-50%); }
        .home-floor-chair.bottom { bottom: 5px; left: 50%; transform: translateX(-50%); }
        .home-floor-chair.left { left: 5px; top: 50%; transform: translateY(-50%) rotate(90deg); }
        .home-floor-chair.right { right: 5px; top: 50%; transform: translateY(-50%) rotate(90deg); }

        .shape-square .home-floor-chair.top,
        .shape-square .home-floor-chair.bottom,
        .shape-square .home-floor-chair.left,
        .shape-square .home-floor-chair.right {
            display: none;
        }

        .home-floor-legend {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 800;
        }

        .home-floor-key {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .home-floor-key::before {
            content: '';
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #9ca3af;
        }

        .home-floor-key.occupied::before {
            background: var(--dm-primary);
        }

        .home-floor-unassigned {
            display: grid;
            gap: 8px;
            padding: 12px;
            border: 1px dashed var(--dm-border-strong);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
        }

        .home-floor-unassigned-title {
            color: var(--dm-text);
            font-size: 13px;
            font-weight: 900;
        }

        .home-timeline-frame-wrap {
            background: var(--dm-bg);
        }

        .home-timeline-frame {
            display: block;
            width: 100%;
            height: calc(100vh - 250px);
            min-height: 720px;
            max-height: 960px;
            border: 0;
            background: var(--dm-bg);
        }

        .home-insight-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            align-items: stretch;
        }

        .home-special-card {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            min-height: 280px;
        }

        .home-special-heading {
            display: grid;
            grid-template-columns: 40px minmax(0, 1fr);
            align-items: center;
            gap: 12px;
        }

        .home-special-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--dm-radius-sm);
            background: #ecfdf5;
            color: #047857;
            font-size: 16px;
        }

        .home-special-icon.trivia {
            background: #fff7ed;
            color: #c2410c;
        }

        .home-special-icon.functions {
            background: #eef2ff;
            color: #4338ca;
        }

        .home-special-add {
            flex: 0 0 40px;
            width: 40px;
            min-height: 40px;
            padding: 0;
        }

        .home-special-list {
            display: grid;
            align-content: start;
            padding: 0;
        }

        .home-special-list .home-queue-row {
            grid-template-columns: minmax(180px, 1.05fr) minmax(120px, 0.75fr) max-content;
            gap: 14px;
            min-height: 78px;
            padding: 13px 18px;
            border-bottom: 1px solid var(--dm-border);
        }

        .home-special-list .home-queue-row:last-child {
            border-bottom: 0;
        }

        .home-special-list .home-reservation-status {
            gap: 8px;
        }

        .home-special-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) max-content;
            gap: 12px;
            align-items: center;
            padding: 13px 14px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
            color: inherit;
            text-decoration: none;
            transition: border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
        }

        .home-special-row:hover {
            border-color: rgba(15, 23, 42, 0.18);
            box-shadow: var(--dm-shadow-sm);
            transform: translateY(-1px);
        }

        .home-special-main {
            min-width: 0;
        }

        .home-special-name {
            margin: 0;
            overflow: hidden;
            color: var(--dm-text);
            font-size: 14px;
            font-weight: 900;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .home-special-meta {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
            margin-top: 4px;
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .home-special-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 9px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: #fbfbfc;
            color: var(--dm-text);
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .home-special-note {
            grid-column: 1 / -1;
            margin: -2px 0 0;
            overflow: hidden;
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 650;
            line-height: 1.4;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .home-special-empty {
            align-self: stretch;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 178px;
            border: 1px dashed var(--dm-border-strong);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface-muted);
            color: var(--dm-text-muted);
            font-size: 13px;
            font-weight: 800;
            text-align: center;
        }

        @media (max-width: 1180px) {
            .home-queue-row {
                grid-template-columns: minmax(240px, 1fr) minmax(190px, 0.8fr);
            }

            .home-special-list .home-queue-row {
                grid-template-columns: minmax(240px, 1fr) minmax(190px, 0.8fr);
            }

            .home-reservation-status {
                grid-column: 1 / -1;
                justify-content: flex-start;
            }

            .home-insight-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .home-header,
            .home-toolbar,
            .home-card-header {
                align-items: stretch;
                flex-direction: column;
            }

            .home-header-actions,
            .home-toolbar-actions,
            .home-date-form,
            .home-view-menu,
            .home-search,
            .home-button {
                width: 100%;
            }

            .home-view-menu-panel {
                left: 0;
                right: 0;
                min-width: 0;
            }

            .home-header-actions,
            .home-toolbar-actions {
                flex-wrap: wrap;
                justify-content: flex-start;
            }

            .home-button {
                min-width: 0;
            }

            .home-header-actions .home-notification-button {
                flex: 0 0 38px;
                width: 38px;
            }

            .home-date-form {
                flex-wrap: nowrap;
            }

            .home-date-form .home-date-input {
                flex: 1 1 auto;
                width: auto;
                min-width: 0;
                max-width: none;
            }

            .home-date-nav {
                flex: 0 0 38px;
                width: 38px;
            }

            .home-date-today {
                flex: 0 0 auto;
                width: auto;
                padding: 8px 10px;
            }

            .home-queue-row {
                grid-template-columns: 1fr;
                gap: 10px;
                min-height: 0;
            }

            .home-special-list .home-queue-row {
                grid-template-columns: 1fr;
            }

            .home-timeline-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .home-timeline-rail {
                display: none;
            }

            .home-timeline-card-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .home-table-grid {
                grid-template-columns: 1fr;
            }

            .home-timeline-frame {
                height: 760px;
                min-height: 760px;
            }

            .home-reservation-status {
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .home-insight-grid {
                gap: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <div class="main-content">
            <main class="admin-container home-page">
                <div class="home-shell">
                    <header class="home-header">
                        <div class="home-title-wrap">
                            <details class="home-mode-menu">
                                <summary class="home-title-row" aria-label="Choose booking section">
                                    <h1><?php echo htmlspecialchars($bookingModeMeta[$selectedBookingMode]['label'], ENT_QUOTES, 'UTF-8'); ?></h1>
                                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                </summary>
                                <div class="home-mode-menu-panel">
                                    <?php foreach ($bookingModeMeta as $modeName => $meta): ?>
                                        <a class="home-mode-option <?php echo $modeName === $selectedBookingMode ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars($homeModeUrl((string) $modeName), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fa-solid <?php echo htmlspecialchars((string) ($meta['icon'] ?? 'fa-calendar-check'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                            <span><?php echo htmlspecialchars((string) ($meta['label'] ?? ucfirst((string) $modeName)), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                            <p class="home-subtitle">
                                <?php echo htmlspecialchars(date('l, j M Y', $selectedTimestamp), ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($adminName)): ?>
                                    · <?php echo htmlspecialchars((string) $adminName, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="home-header-actions">
                            <form class="home-date-form" method="GET" action="home.php">
                                <input type="hidden" name="view" value="<?php echo htmlspecialchars($selectedView, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="mode" value="<?php echo htmlspecialchars($selectedBookingMode, ENT_QUOTES, 'UTF-8'); ?>">
                                <a class="home-button home-date-nav" href="<?php echo htmlspecialchars($homeDateUrl($previousDate), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous day">
                                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                                </a>
                                <input class="home-date-input" type="date" name="date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Dashboard date" onchange="this.form.submit()">
                                <a class="home-button home-date-nav" href="<?php echo htmlspecialchars($homeDateUrl($nextDate), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next day">
                                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                                </a>
                                <a class="home-button home-date-today" href="<?php echo htmlspecialchars($homeDateUrl($todayDate), ENT_QUOTES, 'UTF-8'); ?>">Today</a>
                            </form>
                            <a class="home-button home-date-nav home-notification-button" href="bookings-management.php" aria-label="Notifications" title="Notifications">
                                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                                <?php if ($pendingBookingsCount > 0): ?>
                                    <span class="home-notification-badge"><?php echo number_format($pendingBookingsCount); ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </header>

                    <section class="home-toolbar" aria-label="Home dashboard controls">
                        <div class="home-chip-group">
                            <span class="home-metric-chip">
                                <span class="home-metric-dot"></span>
                                <span class="home-metric-main"><strong><?php echo number_format($selectedBookingsCount); ?></strong><span>Bookings</span></span>
                                <span class="home-metric-guests"><i class="fa-solid fa-user-group" aria-hidden="true"></i><strong><?php echo number_format($selectedGuestsCount); ?></strong></span>
                            </span>
                            <span class="home-metric-chip">
                                <span class="home-metric-dot info"></span>
                                <span class="home-metric-main"><strong><?php echo number_format($selectedLunchCount); ?></strong><span>Lunch</span></span>
                                <span class="home-metric-guests"><i class="fa-solid fa-user-group" aria-hidden="true"></i><strong><?php echo number_format($selectedLunchGuestsCount); ?></strong></span>
                            </span>
                            <span class="home-metric-chip">
                                <span class="home-metric-dot warning"></span>
                                <span class="home-metric-main"><strong><?php echo number_format($selectedDinnerCount); ?></strong><span>Dinner</span></span>
                                <span class="home-metric-guests"><i class="fa-solid fa-user-group" aria-hidden="true"></i><strong><?php echo number_format($selectedDinnerGuestsCount); ?></strong></span>
                            </span>
                        </div>

                        <div class="home-toolbar-actions">
                            <a class="home-button" href="settings.php">
                                <i class="fa-solid fa-gear" aria-hidden="true"></i>
                                <span>Settings</span>
                            </a>
                            <?php if ($selectedView !== 'timeline'): ?>
                                <label class="home-search">
                                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                    <input type="search" placeholder="Search reservations" data-home-search aria-label="Search reservations">
                                </label>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="home-card">
                        <div class="home-card-header">
                            <div class="home-card-heading">
                                <details class="home-view-menu home-view-title-menu">
                                    <summary class="home-view-title-trigger" aria-label="Change home view">
                                        <i class="fa-solid <?php echo htmlspecialchars($viewMeta[$selectedView]['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                        <span><?php echo htmlspecialchars($viewMeta[$selectedView]['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                                    </summary>
                                    <div class="home-view-menu-panel">
                                        <?php foreach ($viewMeta as $viewName => $meta): ?>
                                            <?php if ($viewName === $selectedView) { continue; } ?>
                                            <a class="home-view-option" href="<?php echo htmlspecialchars($homeViewUrl((string) $viewName), ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fa-solid <?php echo htmlspecialchars((string) ($meta['icon'] ?? 'fa-list'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                                <span><?php echo htmlspecialchars((string) ($meta['label'] ?? ucfirst((string) $viewName)), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                                <p class="home-card-note"><?php echo htmlspecialchars($viewMeta[$selectedView]['note'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <a class="home-button home-button-primary home-date-nav" href="../timeline/timeline.php?date=<?php echo urlencode($selectedDate); ?>#bookingList" aria-label="Add booking" title="Add booking" data-booking-add-trigger data-booking-add-type="normal">
                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            </a>
                        </div>

                        <?php if ($selectedView === 'list'): ?>
                            <div class="home-queue">
                                <?php if (empty($queueBookings)): ?>
                                    <div class="home-empty">No <?php echo $selectedBookingMode === 'requests' ? 'requests' : 'bookings'; ?> for this date.</div>
                                <?php else: ?>
                                    <?php foreach ($queueBookings as $index => $booking): ?>
                                        <?php
                                            $bookingName = (string) ($booking['customer_name'] ?? 'Guest');
                                            $tableText = !empty($booking['assigned_table_numbers'])
                                                ? 'Table ' . (string) $booking['assigned_table_numbers']
                                                : 'No table';
                                            $areaText = (string) ($booking['assigned_area_names'] ?? 'Dining room');
                                            $noteText = trim((string) ($booking['special_request'] ?? ''));
                                            $status = strtolower((string) ($booking['status'] ?? 'pending'));
                                            $statusLabel = getBookingStatusLabel($status);
                                            $bookingTypeLabel = getBookingTypeLabel($booking['booking_type'] ?? 'normal');
                                            $searchText = strtolower(trim($bookingName . ' ' . $tableText . ' ' . $areaText . ' ' . $statusLabel . ' ' . $bookingTypeLabel . ' ' . $noteText));
                                        ?>
                                        <a
                                            class="home-queue-row"
                                            href="../timeline/timeline.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#bookingList"
                                            data-booking-edit-payload="<?php echo $bookingEditPayload($booking); ?>"
                                            data-home-row
                                            data-search-text="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <div class="home-reservation-main">
                                                <span class="home-avatar tone-<?php echo (int) ($index % 5); ?>">
                                                    <?php echo htmlspecialchars($getInitials($bookingName), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <div class="home-reservation-copy">
                                                    <p class="home-reservation-name"><?php echo htmlspecialchars($bookingName, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <div class="home-reservation-meta">
                                                        <?php echo htmlspecialchars($formatQueueTime((string) $booking['booking_date'], (string) $booking['start_time']), ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="home-reservation-context">
                                                <strong><?php echo htmlspecialchars($tableText, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span><?php echo htmlspecialchars($areaText, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>

                                            <div class="home-reservation-status">
                                                <?php if ($noteText !== ''): ?>
                                                    <span class="home-reservation-note"><?php echo htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                                <?php if ($selectedBookingMode === 'requests' && $status === 'pending'): ?>
                                                    <button type="button" class="home-confirm-request" data-confirm-request-id="<?php echo (int) ($booking['booking_id'] ?? 0); ?>" aria-label="Confirm booking request" title="Confirm booking request">
                                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="status-tag <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="home-guest-pill">
                                                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                                                    <?php echo number_format((int) ($booking['number_of_guests'] ?? 0)); ?>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($selectedView === 'timeline'): ?>
                            <div class="home-timeline-frame-wrap">
                                <iframe
                                    class="home-timeline-frame"
                                    src="<?php echo htmlspecialchars($timelineEmbedUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="DineMate timeline for <?php echo htmlspecialchars($formatDateLabel($selectedDate), ENT_QUOTES, 'UTF-8'); ?>"
                                ></iframe>
                            </div>
                        <?php else: ?>
                            <div class="home-floor-wrap">
                                <?php if (empty($floorTables)): ?>
                                    <div class="home-empty">No tables have been created yet.</div>
                                <?php else: ?>
                                    <div class="home-floor-legend" aria-label="Floor plan legend">
                                        <span class="home-floor-key occupied">Booked on selected date</span>
                                        <span class="home-floor-key">Available</span>
                                    </div>
                                    <div class="home-floor-viewport">
                                        <div class="home-floor-canvas" role="img" aria-label="Restaurant floor plan for <?php echo htmlspecialchars($formatDateLabel($selectedDate), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php foreach ($floorZones as $zone): ?>
                                                <div
                                                    class="home-floor-zone tone-<?php echo htmlspecialchars((string) $zone['tone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    style="left: <?php echo (int) $zone['x']; ?>px; top: <?php echo (int) $zone['y']; ?>px; width: <?php echo (int) $zone['width']; ?>px; height: <?php echo (int) $zone['height']; ?>px;"
                                                    aria-hidden="true"
                                                ></div>
                                                <div
                                                    class="home-floor-label"
                                                    style="left: <?php echo (int) $zone['label_x']; ?>px; top: <?php echo (int) $zone['label_y']; ?>px;"
                                                >
                                                    <?php echo htmlspecialchars((string) $zone['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endforeach; ?>

                                            <?php foreach ($floorTables as $table): ?>
                                                <?php
                                                    if ($table['layout_x'] === null || $table['layout_y'] === null) {
                                                        continue;
                                                    }

                                                    $tableBookings = $table['bookings'] ?? [];
                                                    $tableSearchParts = [
                                                        'table ' . (string) ($table['table_number'] ?? ''),
                                                        (string) ($table['area_name'] ?? ''),
                                                        (string) ($table['status'] ?? ''),
                                                    ];
                                                    foreach ($tableBookings as $tableBooking) {
                                                        $tableSearchParts[] = (string) ($tableBooking['customer_name'] ?? 'Guest');
                                                        $tableSearchParts[] = (string) ($tableBooking['assigned_area_names'] ?? '');
                                                    }
                                                    $tableSearchText = strtolower(trim(implode(' ', $tableSearchParts)));
                                                    $firstBooking = $tableBookings[0] ?? null;
                                                    $bookingTooltip = '';
                                                    if ($firstBooking) {
                                                        $bookingTooltip = (string) ($firstBooking['customer_name'] ?? 'Guest');
                                                        if (!empty($firstBooking['start_time'])) {
                                                            $bookingTooltip .= ' at ' . date('g:i A', strtotime((string) $firstBooking['start_time']));
                                                        }
                                                    } else {
                                                        $bookingTooltip = 'Available';
                                                    }
                                                ?>
                                                <a
                                                    class="home-floor-table shape-<?php echo htmlspecialchars((string) $table['shape'], ENT_QUOTES, 'UTF-8'); ?> tone-<?php echo htmlspecialchars((string) $table['tone'], ENT_QUOTES, 'UTF-8'); ?> <?php echo !empty($table['is_occupied']) ? 'is-occupied' : ''; ?> <?php echo empty($table['reservable']) ? 'is-unreservable' : ''; ?>"
                                                    href="../timeline/timeline.php?date=<?php echo urlencode($selectedDate); ?>#bookingList"
                                                    title="Table <?php echo htmlspecialchars((string) ($table['table_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($bookingTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                                    style="left: <?php echo (int) $table['layout_x']; ?>px; top: <?php echo (int) $table['layout_y']; ?>px;"
                                                    data-home-row
                                                    data-search-text="<?php echo htmlspecialchars($tableSearchText, ENT_QUOTES, 'UTF-8'); ?>"
                                                >
                                                    <span class="home-floor-table-shell">
                                                        <span class="home-floor-chair top"></span>
                                                        <span class="home-floor-chair bottom"></span>
                                                        <span class="home-floor-chair left"></span>
                                                        <span class="home-floor-chair right"></span>
                                                        <span class="home-floor-table-top">
                                                            <span class="home-floor-table-number">T<?php echo htmlspecialchars((string) ($table['table_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <span class="home-floor-table-meta"><?php echo number_format((int) ($table['capacity'] ?? 0)); ?>p</span>
                                                        </span>
                                                        <?php if (!empty($tableBookings)): ?>
                                                            <span class="home-floor-booking-dot"><?php echo number_format(count($tableBookings)); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($unassignedBookings)): ?>
                                        <div class="home-floor-unassigned">
                                            <div class="home-floor-unassigned-title">Unassigned bookings</div>
                                            <div class="home-table-bookings">
                                                <?php foreach ($unassignedBookings as $tableBooking): ?>
                                                    <?php
                                                        $tableBookingName = (string) ($tableBooking['customer_name'] ?? 'Guest');
                                                        $tableBookingTime = !empty($tableBooking['start_time']) ? date('g:i A', strtotime((string) $tableBooking['start_time'])) : 'Time TBC';
                                                    ?>
                                                    <a class="home-table-booking" href="../timeline/timeline.php?date=<?php echo urlencode((string) $tableBooking['booking_date']); ?>#bookingList">
                                                        <strong><?php echo htmlspecialchars($tableBookingName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <span><?php echo htmlspecialchars($tableBookingTime, ENT_QUOTES, 'UTF-8'); ?> · <?php echo number_format((int) ($tableBooking['number_of_guests'] ?? 0)); ?> guests</span>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="home-insight-grid" aria-label="Service insights">
                        <article class="home-card home-special-card">
                            <div class="home-card-header">
                                <div class="home-special-heading">
                                    <span class="home-special-icon trivia">
                                        <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                                    </span>
                                    <div class="home-card-heading">
                                        <h2 class="home-card-title">Trivia</h2>
                                        <p class="home-card-note"><?php echo number_format(count($triviaBookings)); ?> bookings on <?php echo htmlspecialchars($formatDateLabel($selectedDate), ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>
                                <a class="home-button home-button-primary home-special-add" href="../timeline/timeline.php?date=<?php echo urlencode($selectedDate); ?>#bookingList" aria-label="Add trivia booking" title="Add trivia booking" data-booking-add-trigger data-booking-add-type="trivia">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                </a>
                            </div>

                            <div class="home-special-list">
                                <?php if (empty($triviaBookings)): ?>
                                    <div class="home-special-empty">No trivia bookings for this date.</div>
                                <?php else: ?>
                                    <?php foreach ($triviaBookings as $index => $booking): ?>
                                        <?php
                                            $specialBookingName = (string) ($booking['customer_name'] ?? 'Guest');
                                            $specialBookingTable = !empty($booking['assigned_table_numbers']) ? 'Table ' . (string) $booking['assigned_table_numbers'] : 'No table';
                                            $specialBookingArea = (string) ($booking['assigned_area_names'] ?? 'Dining room');
                                            $specialBookingNote = trim((string) ($booking['special_request'] ?? ''));
                                            $specialBookingStatus = strtolower((string) ($booking['status'] ?? 'pending'));
                                            $specialBookingStatusLabel = getBookingStatusLabel($specialBookingStatus);
                                            $specialBookingTypeLabel = getBookingTypeLabel($booking['booking_type'] ?? 'trivia');
                                            $specialBookingSearch = strtolower(trim($specialBookingName . ' ' . $specialBookingTable . ' ' . $specialBookingArea . ' ' . $specialBookingStatusLabel . ' ' . $specialBookingTypeLabel . ' ' . $specialBookingNote));
                                        ?>
                                        <a
                                            class="home-queue-row"
                                            href="../timeline/timeline.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#bookingList"
                                            data-booking-edit-payload="<?php echo $bookingEditPayload($booking); ?>"
                                            data-home-row
                                            data-search-text="<?php echo htmlspecialchars($specialBookingSearch, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <div class="home-reservation-main">
                                                <span class="home-avatar tone-<?php echo (int) (($index + 2) % 5); ?>">
                                                    <?php echo htmlspecialchars($getInitials($specialBookingName), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <div class="home-reservation-copy">
                                                    <p class="home-reservation-name"><?php echo htmlspecialchars($specialBookingName, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <div class="home-reservation-meta">
                                                        <?php echo htmlspecialchars($formatQueueTime((string) $booking['booking_date'], (string) $booking['start_time']), ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="home-reservation-context">
                                                <strong><?php echo htmlspecialchars($specialBookingTable, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span><?php echo htmlspecialchars($specialBookingArea, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>

                                            <div class="home-reservation-status">
                                                <?php if ($specialBookingNote !== ''): ?>
                                                    <span class="home-reservation-note"><?php echo htmlspecialchars($specialBookingNote, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                                <?php if ($selectedBookingMode === 'requests' && $specialBookingStatus === 'pending'): ?>
                                                    <button type="button" class="home-confirm-request" data-confirm-request-id="<?php echo (int) ($booking['booking_id'] ?? 0); ?>" aria-label="Confirm booking request" title="Confirm booking request">
                                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="status-tag <?php echo htmlspecialchars($specialBookingStatus, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($specialBookingStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="home-guest-pill">
                                                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                                                    <?php echo number_format((int) ($booking['number_of_guests'] ?? 0)); ?>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </article>

                        <article class="home-card home-special-card">
                            <div class="home-card-header">
                                <div class="home-special-heading">
                                    <span class="home-special-icon functions">
                                        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                                    </span>
                                    <div class="home-card-heading">
                                        <h2 class="home-card-title">Functions</h2>
                                        <p class="home-card-note"><?php echo number_format(count($functionBookings)); ?> large/event bookings on <?php echo htmlspecialchars($formatDateLabel($selectedDate), ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>
                                <a class="home-button home-button-primary home-special-add" href="../timeline/timeline.php?date=<?php echo urlencode($selectedDate); ?>#bookingList" aria-label="Add function booking" title="Add function booking" data-booking-add-trigger data-booking-add-type="function">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                </a>
                            </div>

                            <div class="home-special-list">
                                <?php if (empty($functionBookings)): ?>
                                    <div class="home-special-empty">No function bookings for this date.</div>
                                <?php else: ?>
                                    <?php foreach ($functionBookings as $index => $booking): ?>
                                        <?php
                                            $specialBookingName = (string) ($booking['customer_name'] ?? 'Guest');
                                            $specialBookingTable = !empty($booking['assigned_table_numbers']) ? 'Table ' . (string) $booking['assigned_table_numbers'] : 'No table';
                                            $specialBookingArea = (string) ($booking['assigned_area_names'] ?? 'Dining room');
                                            $specialBookingNote = trim((string) ($booking['special_request'] ?? ''));
                                            $specialBookingStatus = strtolower((string) ($booking['status'] ?? 'pending'));
                                            $specialBookingStatusLabel = getBookingStatusLabel($specialBookingStatus);
                                            $specialBookingTypeLabel = getBookingTypeLabel($booking['booking_type'] ?? 'function');
                                            $specialBookingSearch = strtolower(trim($specialBookingName . ' ' . $specialBookingTable . ' ' . $specialBookingArea . ' ' . $specialBookingStatusLabel . ' ' . $specialBookingTypeLabel . ' ' . $specialBookingNote));
                                        ?>
                                        <a
                                            class="home-queue-row"
                                            href="../timeline/timeline.php?date=<?php echo urlencode((string) $booking['booking_date']); ?>#bookingList"
                                            data-booking-edit-payload="<?php echo $bookingEditPayload($booking); ?>"
                                            data-home-row
                                            data-search-text="<?php echo htmlspecialchars($specialBookingSearch, ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <div class="home-reservation-main">
                                                <span class="home-avatar tone-<?php echo (int) (($index + 3) % 5); ?>">
                                                    <?php echo htmlspecialchars($getInitials($specialBookingName), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <div class="home-reservation-copy">
                                                    <p class="home-reservation-name"><?php echo htmlspecialchars($specialBookingName, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <div class="home-reservation-meta">
                                                        <?php echo htmlspecialchars($formatQueueTime((string) $booking['booking_date'], (string) $booking['start_time']), ENT_QUOTES, 'UTF-8'); ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="home-reservation-context">
                                                <strong><?php echo htmlspecialchars($specialBookingTable, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span><?php echo htmlspecialchars($specialBookingArea, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>

                                            <div class="home-reservation-status">
                                                <?php if ($specialBookingNote !== ''): ?>
                                                    <span class="home-reservation-note"><?php echo htmlspecialchars($specialBookingNote, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                                <?php if ($selectedBookingMode === 'requests' && $specialBookingStatus === 'pending'): ?>
                                                    <button type="button" class="home-confirm-request" data-confirm-request-id="<?php echo (int) ($booking['booking_id'] ?? 0); ?>" aria-label="Confirm booking request" title="Confirm booking request">
                                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="status-tag <?php echo htmlspecialchars($specialBookingStatus, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($specialBookingStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="home-guest-pill">
                                                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                                                    <?php echo number_format((int) ($booking['number_of_guests'] ?? 0)); ?>
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </article>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <?php
    $bookingEditModalId = 'homeBookingEditModal';
    $bookingEditFormId = 'homeBookingEditForm';
    $bookingEditTitle = 'Edit Booking';
    $bookingEditSubmitLabel = 'Save Changes';
    $bookingEditShowDelete = true;
    $bookingEditShowStatus = true;
    $bookingEditShowTable = true;
    $bookingEditHiddenFields = [];
    $bookingEditTables = $tableRows;
    include __DIR__ . '/../../includes/components/booking-editing-modal.php';

    $bookingEditModalId = 'homeBookingAddModal';
    $bookingEditFormId = 'homeBookingAddForm';
    $bookingEditTitle = 'Add a Booking';
    $bookingEditSubmitLabel = 'Create Booking';
    $bookingEditShowDelete = false;
    $bookingEditShowStatus = false;
    $bookingEditShowTable = false;
    $bookingEditHiddenFields = [];
    $bookingEditTypes = getBookingTypes();
    include __DIR__ . '/../../includes/components/booking-editing-modal.php';
    ?>

    <script>
        const HOME_SELECTED_DATE = <?php echo json_encode($selectedDate); ?>;
        const homeSearchInput = document.querySelector('[data-home-search]');
        const homeRows = Array.from(document.querySelectorAll('[data-home-row]'));
        const bookingEditModal = document.getElementById('homeBookingEditModal');
        const bookingEditForm = document.getElementById('homeBookingEditForm');
        const bookingEditFields = bookingEditModal ? {
            id: bookingEditModal.querySelector('[data-booking-edit-id]'),
            date: bookingEditModal.querySelector('[data-booking-edit-date]'),
            name: bookingEditModal.querySelector('[data-booking-edit-name]'),
            start: bookingEditModal.querySelector('[data-booking-edit-start]'),
            end: bookingEditModal.querySelector('[data-booking-edit-end]'),
            guests: bookingEditModal.querySelector('[data-booking-edit-guests]'),
            type: bookingEditModal.querySelector('[data-booking-edit-type]'),
            table: bookingEditModal.querySelector('[data-booking-edit-table]'),
            notes: bookingEditModal.querySelector('[data-booking-edit-notes]'),
            status: bookingEditModal.querySelector('[data-booking-edit-status]'),
            email: bookingEditModal.querySelector('[data-booking-edit-email]'),
            phone: bookingEditModal.querySelector('[data-booking-edit-phone]'),
            delete: bookingEditModal.querySelector('[data-booking-edit-delete]'),
            error: bookingEditModal.querySelector('[data-booking-edit-error]'),
            save: bookingEditModal.querySelector('[data-booking-edit-save]'),
        } : null;
        const bookingAddModal = document.getElementById('homeBookingAddModal');
        const bookingAddForm = document.getElementById('homeBookingAddForm');
        const bookingAddFields = bookingAddModal ? {
            date: bookingAddModal.querySelector('[data-booking-edit-date]'),
            name: bookingAddModal.querySelector('[data-booking-edit-name]'),
            start: bookingAddModal.querySelector('[data-booking-edit-start]'),
            guests: bookingAddModal.querySelector('[data-booking-edit-guests]'),
            type: bookingAddModal.querySelector('[data-booking-edit-type]'),
            notes: bookingAddModal.querySelector('[data-booking-edit-notes]'),
            email: bookingAddModal.querySelector('[data-booking-edit-email]'),
            phone: bookingAddModal.querySelector('[data-booking-edit-phone]'),
            error: bookingAddModal.querySelector('[data-booking-edit-error]'),
            save: bookingAddModal.querySelector('[data-booking-edit-save]'),
        } : null;
        let activeBookingEditPayload = null;

        if (homeSearchInput) {
            homeSearchInput.addEventListener('input', () => {
                const query = homeSearchInput.value.trim().toLowerCase();

                homeRows.forEach((row) => {
                    row.hidden = query !== '' && !(row.dataset.searchText || '').includes(query);
                });
            });
        }

        function normalizeBookingEditTime(value) {
            return String(value || '').slice(0, 5);
        }

        function getBookingEditDurationMinutes(booking) {
            const start = normalizeBookingEditTime(booking.start_time);
            const end = normalizeBookingEditTime(booking.end_time);
            const [startHour, startMinute] = start.split(':').map(Number);
            const [endHour, endMinute] = end.split(':').map(Number);
            const startTotal = (startHour * 60) + (startMinute || 0);
            const endTotal = (endHour * 60) + (endMinute || 0);

            return endTotal > startTotal ? endTotal - startTotal : 60;
        }

        function addBookingEditMinutes(timeValue, minutesToAdd) {
            const [hours, minutes] = normalizeBookingEditTime(timeValue || '12:00').split(':').map(Number);
            const date = new Date();
            date.setHours(hours || 12, minutes || 0, 0, 0);
            date.setMinutes(date.getMinutes() + minutesToAdd);

            return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
        }

        function setBookingEditError(message = '') {
            if (!bookingEditFields?.error) {
                return;
            }

            bookingEditFields.error.textContent = message;
            bookingEditFields.error.classList.toggle('is-visible', Boolean(message));
        }

        function setBookingAddError(message = '') {
            if (!bookingAddFields?.error) {
                return;
            }

            bookingAddFields.error.textContent = message;
            bookingAddFields.error.classList.toggle('is-visible', Boolean(message));
        }

        function closeBookingEditModal() {
            if (!bookingEditModal) {
                return;
            }

            bookingEditModal.hidden = true;
            activeBookingEditPayload = null;
            setBookingEditError('');
        }

        function openBookingEditModal(booking) {
            if (!bookingEditModal || !bookingEditFields || !booking) {
                return;
            }

            activeBookingEditPayload = booking;
            bookingEditFields.id.value = booking.booking_id || '';
            bookingEditFields.date.value = booking.booking_date || '';
            bookingEditFields.name.value = booking.customer_name || '';
            bookingEditFields.start.value = normalizeBookingEditTime(booking.start_time);
            bookingEditFields.end.value = normalizeBookingEditTime(booking.end_time);
            bookingEditFields.guests.value = booking.number_of_guests || '1';
            bookingEditFields.type.value = ['normal', 'trivia', 'function'].includes(String(booking.booking_type || '').toLowerCase())
                ? String(booking.booking_type).toLowerCase()
                : 'normal';
            bookingEditFields.table.value = booking.table_id ? String(booking.table_id) : '';
            bookingEditFields.notes.value = booking.special_request || '';
            bookingEditFields.status.value = booking.status || 'pending';
            bookingEditFields.email.value = booking.customer_email || '';
            bookingEditFields.phone.value = booking.customer_phone || '';
            setBookingEditError('');
            bookingEditModal.hidden = false;
            requestAnimationFrame(() => bookingEditFields.name.focus());
        }

        function closeBookingAddModal() {
            if (!bookingAddModal) {
                return;
            }

            bookingAddModal.hidden = true;
            setBookingAddError('');
        }

        function openBookingAddModal(bookingType = 'normal') {
            if (!bookingAddModal || !bookingAddFields) {
                return;
            }

            bookingAddForm.reset();
            bookingAddFields.date.value = HOME_SELECTED_DATE;
            bookingAddFields.start.value = '12:00';
            bookingAddFields.guests.value = '2';
            bookingAddFields.type.value = ['normal', 'trivia', 'function'].includes(String(bookingType || '').toLowerCase())
                ? String(bookingType).toLowerCase()
                : 'normal';
            setBookingAddError('');
            bookingAddModal.hidden = false;
            requestAnimationFrame(() => bookingAddFields.name.focus());
        }

        document.querySelectorAll('[data-booking-edit-payload]').forEach((bookingLink) => {
            bookingLink.addEventListener('click', (event) => {
                event.preventDefault();

                try {
                    openBookingEditModal(JSON.parse(bookingLink.dataset.bookingEditPayload || '{}'));
                } catch (error) {
                    window.location.href = bookingLink.href;
                }
            });
        });

        document.querySelectorAll('[data-booking-add-trigger]').forEach((bookingAddTrigger) => {
            bookingAddTrigger.addEventListener('click', (event) => {
                event.preventDefault();
                openBookingAddModal(bookingAddTrigger.dataset.bookingAddType || 'normal');
            });
        });

        document.querySelectorAll('[data-confirm-request-id]').forEach((confirmButton) => {
            confirmButton.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();

                const bookingId = Number(confirmButton.dataset.confirmRequestId || 0);
                if (bookingId < 1 || confirmButton.classList.contains('is-saving')) {
                    return;
                }

                confirmButton.classList.add('is-saving');
                confirmButton.disabled = true;

                try {
                    const response = await fetch('../timeline/confirm-pending-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ booking_id: bookingId }),
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not confirm booking');
                    }

                    const row = confirmButton.closest('[data-home-row]');
                    if (row) {
                        row.remove();
                    }

                    document.dispatchEvent(new CustomEvent('admin-pending-bookings-changed'));
                } catch (error) {
                    confirmButton.classList.remove('is-saving');
                    confirmButton.disabled = false;
                    alert(error.message);
                }
            });
        });

        if (bookingEditModal && bookingEditForm && bookingEditFields) {
            bookingEditModal.querySelectorAll('[data-booking-edit-close], [data-booking-edit-cancel]').forEach((button) => {
                button.addEventListener('click', closeBookingEditModal);
            });

            bookingEditModal.addEventListener('click', (event) => {
                if (event.target === bookingEditModal) {
                    closeBookingEditModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !bookingEditModal.hidden) {
                    closeBookingEditModal();
                }
            });

            bookingEditForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!activeBookingEditPayload) {
                    return;
                }

                bookingEditFields.save.disabled = true;
                setBookingEditError('');

                const durationMinutes = getBookingEditDurationMinutes(activeBookingEditPayload);
                const nextEndTime = addBookingEditMinutes(bookingEditFields.start.value, durationMinutes);
                const payload = {
                    booking_id: bookingEditFields.id.value,
                    customer_name: bookingEditFields.name.value.trim(),
                    customer_email: bookingEditFields.email.value.trim(),
                    customer_phone: bookingEditFields.phone.value.trim(),
                    booking_date: bookingEditFields.date.value,
                    status: bookingEditFields.status.value,
                    requested_start_time: bookingEditFields.start.value,
                    requested_end_time: nextEndTime,
                    start_time: bookingEditFields.start.value,
                    end_time: nextEndTime,
                    number_of_guests: bookingEditFields.guests.value,
                    booking_type: bookingEditFields.type.value,
                    special_request: bookingEditFields.notes.value.trim(),
                    table_id: bookingEditFields.table.value,
                };

                try {
                    const response = await fetch('../timeline/update-booking-details.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not save booking');
                    }

                    window.location.reload();
                } catch (error) {
                    setBookingEditError(error.message);
                } finally {
                    bookingEditFields.save.disabled = false;
                }
            });

            bookingEditFields.delete.addEventListener('click', async () => {
                if (!activeBookingEditPayload || !confirm('Cancel this booking?')) {
                    return;
                }

                bookingEditFields.delete.disabled = true;
                setBookingEditError('');

                try {
                    const response = await fetch('../timeline/cancel-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ booking_id: bookingEditFields.id.value }),
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not cancel booking');
                    }

                    window.location.reload();
                } catch (error) {
                    setBookingEditError(error.message);
                } finally {
                    bookingEditFields.delete.disabled = false;
                }
            });
        }

        if (bookingAddModal && bookingAddForm && bookingAddFields) {
            bookingAddModal.querySelectorAll('[data-booking-edit-close], [data-booking-edit-cancel]').forEach((button) => {
                button.addEventListener('click', closeBookingAddModal);
            });

            bookingAddModal.addEventListener('click', (event) => {
                if (event.target === bookingAddModal) {
                    closeBookingAddModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !bookingAddModal.hidden) {
                    closeBookingAddModal();
                }
            });

            if (bookingAddFields.start) {
                bookingAddFields.start.max = '21:00';
            }

            bookingAddForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                bookingAddFields.save.disabled = true;
                bookingAddFields.save.textContent = 'Creating...';
                setBookingAddError('');

                const payload = {
                    name: bookingAddFields.name.value.trim(),
                    customer_email: bookingAddFields.email.value.trim(),
                    customer_phone: bookingAddFields.phone.value.trim(),
                    booking_date: bookingAddFields.date.value,
                    start_time: bookingAddFields.start.value,
                    number_of_guests: bookingAddFields.guests.value,
                    booking_type: bookingAddFields.type.value,
                    special_request: bookingAddFields.notes.value.trim(),
                };

                try {
                    const response = await fetch('../timeline/create-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not create booking');
                    }

                    window.location.reload();
                } catch (error) {
                    setBookingAddError(error.message);
                } finally {
                    bookingAddFields.save.disabled = false;
                    bookingAddFields.save.textContent = 'Create Booking';
                }
            });
        }

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || event.data?.type !== 'dinemate:edit-booking') {
                return;
            }

            openBookingEditModal(event.data.booking);
        });
    </script>
</body>
</html>
