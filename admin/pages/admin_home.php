<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);

$adminNewSidebarActive = 'home';
$adminActionCsrfToken = csrfToken('admin_actions');
$todayDate = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $todayDate;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || strtotime($selectedDate) === false) {
    $selectedDate = $todayDate;
}

$selectedTimestamp = strtotime($selectedDate) ?: time();
$previousDate = date('Y-m-d', strtotime('-1 day', $selectedTimestamp));
$nextDate = date('Y-m-d', strtotime('+1 day', $selectedTimestamp));
$dashboardDateLabel = date('l, j F Y', $selectedTimestamp);
$dashboardDateValue = date('d/m/Y', $selectedTimestamp);
$dashboardWeekStart = date('Y-m-d', strtotime('-' . ((int) date('N', $selectedTimestamp) - 1) . ' days', $selectedTimestamp));
$dashboardWeekEnd = date('Y-m-d', strtotime('+6 days', strtotime($dashboardWeekStart)));
$selectedCapacityService = strtolower(trim((string) ($_GET['capacity_service'] ?? 'lunch')));
if (!in_array($selectedCapacityService, ['all', 'lunch', 'dinner'], true)) {
    $selectedCapacityService = 'lunch';
}
$nextCapacityService = $selectedCapacityService === 'lunch' ? 'dinner' : 'lunch';
$selectedCapacityLabel = $selectedCapacityService === 'all' ? 'All' : ucfirst($selectedCapacityService);
$adminHomeFlash = $_SESSION['admin_home_flash'] ?? null;
unset($_SESSION['admin_home_flash']);
$allowedDashboardTabs = ['bookings', 'trivia', 'functions', 'timeline', 'floor'];
$selectedDashboardTab = strtolower(trim((string) ($_GET['dashboard_tab'] ?? 'bookings')));
if (!in_array($selectedDashboardTab, $allowedDashboardTabs, true)) {
    $selectedDashboardTab = 'bookings';
}
$allowedRequestPanels = ['requests', 'unassigned'];
$selectedRequestPanel = strtolower(trim((string) ($_GET['request_panel'] ?? 'requests')));
if (!in_array($selectedRequestPanel, $allowedRequestPanels, true)) {
    $selectedRequestPanel = 'requests';
}
$nextRequestPanel = $selectedRequestPanel === 'requests' ? 'unassigned' : 'requests';
$selectedRequestPanelLabel = $selectedRequestPanel === 'requests' ? 'Requests' : 'Unassigned';

$pendingBookingsCount = 0;
$selectedBookingsCount = 0;
$selectedGuestsCount = 0;
$selectedLunchCount = 0;
$selectedLunchGuestsCount = 0;
$selectedDinnerCount = 0;
$selectedDinnerGuestsCount = 0;
$selectedPendingRequestCount = 0;
$selectedUnassignedActionCount = 0;
$selectedNoteActionCount = 0;
$dashboardLunchCapacity = 0;
try {
    $pendingBookingsCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();

    $dashboardStatsStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN 1 ELSE 0 END), 0) AS total_bookings,
            COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN number_of_guests ELSE 0 END), 0) AS total_guests,
            COALESCE(SUM(CASE WHEN status <> 'cancelled' AND start_time >= '12:00:00' AND start_time < '17:00:00' THEN 1 ELSE 0 END), 0) AS lunch_bookings,
            COALESCE(SUM(CASE WHEN status <> 'cancelled' AND start_time >= '12:00:00' AND start_time < '17:00:00' THEN number_of_guests ELSE 0 END), 0) AS lunch_guests,
            COALESCE(SUM(CASE WHEN status <> 'cancelled' AND start_time >= '17:00:00' THEN 1 ELSE 0 END), 0) AS dinner_bookings,
            COALESCE(SUM(CASE WHEN status <> 'cancelled' AND start_time >= '17:00:00' THEN number_of_guests ELSE 0 END), 0) AS dinner_guests,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_requests,
            COALESCE(SUM(CASE WHEN status <> 'cancelled' AND TRIM(COALESCE(special_request, '')) <> '' THEN 1 ELSE 0 END), 0) AS note_actions
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
    $selectedPendingRequestCount = (int) ($dashboardStats['pending_requests'] ?? 0);
    $selectedNoteActionCount = (int) ($dashboardStats['note_actions'] ?? 0);

    try {
        $unassignedActionsStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE booking_date = ?
              AND status = 'confirmed'
              AND table_id IS NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM booking_table_assignments bta
                  WHERE bta.booking_id = bookings.booking_id
              )
        ");
        $unassignedActionsStmt->execute([$selectedDate]);
        $selectedUnassignedActionCount = (int) $unassignedActionsStmt->fetchColumn();
    } catch (Throwable $unassignedError) {
        $unassignedFallbackStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE booking_date = ?
              AND status = 'confirmed'
              AND table_id IS NULL
        ");
        $unassignedFallbackStmt->execute([$selectedDate]);
        $selectedUnassignedActionCount = (int) $unassignedFallbackStmt->fetchColumn();
    }

    try {
        $capacityStmt = $pdo->query("
            SELECT COALESCE(SUM(
                CASE
                    WHEN COALESCE(reservable, 1) = 1
                     AND LOWER(COALESCE(status, 'available')) NOT IN ('inactive', 'disabled')
                    THEN capacity
                    ELSE 0
                END
            ), 0)
            FROM restaurant_tables
        ");
        $dashboardLunchCapacity = (int) $capacityStmt->fetchColumn();
    } catch (Throwable $capacityError) {
        $dashboardLunchCapacity = (int) $pdo->query("SELECT COALESCE(SUM(capacity), 0) FROM restaurant_tables")->fetchColumn();
    }
} catch (Throwable $error) {
    $pendingBookingsCount = 0;
}

$selectedCapacityGuestsCount = match ($selectedCapacityService) {
    'all' => $selectedGuestsCount,
    'dinner' => $selectedDinnerGuestsCount,
    default => $selectedLunchGuestsCount,
};
$dashboardLunchCapacityPercent = $dashboardLunchCapacity > 0
    ? (int) round(($selectedCapacityGuestsCount / $dashboardLunchCapacity) * 100)
    : 0;
$pendingActionsCount = $selectedPendingRequestCount + $selectedUnassignedActionCount;

$formatActionLabel = static function (int $count, string $singular, ?string $plural = null): string {
    return number_format($count) . ' ' . ($count === 1 ? $singular : ($plural ?? $singular . 's'));
};

$formatRequestTime = static function (string $dateValue, string $timeValue): string {
    $timestamp = strtotime(trim($dateValue . ' ' . $timeValue));
    return $timestamp ? date('g:i A', $timestamp) : 'Time TBC';
};

$dashboardUrl = static function (array $overrides = []) use ($selectedDate, $selectedCapacityService, $selectedDashboardTab, $selectedRequestPanel): string {
    $params = array_merge([
        'date' => $selectedDate,
        'capacity_service' => $selectedCapacityService,
        'dashboard_tab' => $selectedDashboardTab,
        'request_panel' => $selectedRequestPanel,
    ], $overrides);

    return 'admin_home.php?' . http_build_query($params);
};
$dashboardTabs = [
    [
        'key' => 'bookings',
        'label' => 'Bookings',
        'icon' => 'bi-card-checklist',
    ],
    [
        'key' => 'trivia',
        'label' => 'Trivia',
        'icon' => 'bi-question-circle',
    ],
    [
        'key' => 'functions',
        'label' => 'Functions',
        'icon' => 'bi-cake2',
    ],
    [
        'key' => 'timeline',
        'label' => 'Timeline',
        'icon' => 'bi-clock-history',
    ],
    [
        'key' => 'floor',
        'label' => 'Floor View',
        'icon' => 'bi-layout-wtf',
    ],
];

$selectedDashboardTabLabel = 'Bookings';
foreach ($dashboardTabs as $dashboardTab) {
    if ((string) $dashboardTab['key'] === $selectedDashboardTab) {
        $selectedDashboardTabLabel = (string) $dashboardTab['label'];
        break;
    }
}

$pendingRequestPanelBookings = [];
$unassignedPanelBookings = [];
try {
    $requestPanelStmt = $pdo->prepare("
        SELECT
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.number_of_guests,
            b.special_request,
            b.created_at,
            COALESCE(b.booking_type, 'normal') AS booking_type,
            COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.booking_date = ?
          AND b.status = 'pending'
        ORDER BY b.created_at ASC, b.start_time ASC, b.booking_id ASC
    ");
    $requestPanelStmt->execute([$selectedDate]);
    $pendingRequestPanelBookings = $requestPanelStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $requestPanelError) {
    $pendingRequestPanelBookings = [];
}

try {
    $unassignedPanelStmt = $pdo->prepare("
        SELECT
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.number_of_guests,
            b.special_request,
            b.created_at,
            COALESCE(b.booking_type, 'normal') AS booking_type,
            COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.booking_date = ?
          AND b.status = 'confirmed'
          AND b.table_id IS NULL
          AND NOT EXISTS (
              SELECT 1
              FROM booking_table_assignments bta
              WHERE bta.booking_id = b.booking_id
          )
        ORDER BY b.start_time ASC, b.booking_id ASC
    ");
    $unassignedPanelStmt->execute([$selectedDate]);
    $unassignedPanelBookings = $unassignedPanelStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $unassignedPanelError) {
    $unassignedPanelStmt = $pdo->prepare("
        SELECT
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.number_of_guests,
            b.special_request,
            b.created_at,
            COALESCE(b.booking_type, 'normal') AS booking_type,
            COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.booking_date = ?
          AND b.status = 'confirmed'
          AND b.table_id IS NULL
        ORDER BY b.start_time ASC, b.booking_id ASC
    ");
    $unassignedPanelStmt->execute([$selectedDate]);
    $unassignedPanelBookings = $unassignedPanelStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$requestPanelBookings = $selectedRequestPanel === 'requests'
    ? $pendingRequestPanelBookings
    : $unassignedPanelBookings;
$requestPanelCount = $selectedRequestPanel === 'requests'
    ? $selectedPendingRequestCount
    : $selectedUnassignedActionCount;
$requestPanelEmptyMessage = $selectedRequestPanel === 'requests'
    ? 'No requests for this date.'
    : 'No unassigned bookings for this date.';

$bookingTableRows = [];
try {
    $bookingTableStmt = $pdo->prepare("
        SELECT
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.requested_start_time,
            b.requested_end_time,
            b.number_of_guests,
            COALESCE(b.spend_amount, 0.00) AS spend_amount,
            b.status,
            b.reservation_card_status,
            b.booking_source,
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
          AND b.status <> 'pending'
          AND b.status <> 'cancelled'
        GROUP BY b.booking_id
        ORDER BY b.start_time ASC, b.booking_id ASC
    ");
    $bookingTableStmt->execute([$selectedDate]);
    $bookingTableRows = $bookingTableStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $bookingTableError) {
    $bookingTableRows = [];
}

$dashboardTabCounts = [
    'bookings' => 0,
    'trivia' => 0,
    'functions' => 0,
    'timeline' => $selectedBookingsCount,
];
foreach ($bookingTableRows as $bookingTableRow) {
    $bookingType = normalizeBookingType($bookingTableRow['booking_type'] ?? 'normal');
    if ($bookingType === 'trivia') {
        $dashboardTabCounts['trivia']++;
    } elseif ($bookingType === 'function') {
        $dashboardTabCounts['functions']++;
    } else {
        $dashboardTabCounts['bookings']++;
    }
}

$bookingTableVisibleRows = array_values(array_filter($bookingTableRows, static function (array $booking) use ($selectedDashboardTab): bool {
    $bookingType = normalizeBookingType($booking['booking_type'] ?? 'normal');

    if ($selectedDashboardTab === 'trivia') {
        return $bookingType === 'trivia';
    }

    if ($selectedDashboardTab === 'functions') {
        return $bookingType === 'function';
    }

    return $selectedDashboardTab === 'bookings' && $bookingType === 'normal';
}));

$bookingTableTitle = $dashboardDateLabel;
$bookingTableEmptyDateLabel = $selectedDate === $todayDate ? 'today' : 'for this date';
$bookingTableEmptyIcon = 'bi-calendar-x';
$bookingTableEmptyTitle = 'No bookings ' . $bookingTableEmptyDateLabel;
$bookingTableEmptyMessage = 'Bookings will appear here.';
if ($selectedDashboardTab === 'trivia') {
    $bookingTableEmptyIcon = 'bi-question-circle';
    $bookingTableEmptyTitle = 'No trivia bookings ' . $bookingTableEmptyDateLabel;
    $bookingTableEmptyMessage = 'Trivia bookings will appear here.';
} elseif ($selectedDashboardTab === 'functions') {
    $bookingTableEmptyIcon = 'bi-calendar-event';
    $bookingTableEmptyTitle = 'No functions ' . $bookingTableEmptyDateLabel;
    $bookingTableEmptyMessage = 'Function bookings will appear here.';
}

$bookingTableTotals = [
    'bookings' => count($bookingTableVisibleRows),
    'covers' => array_sum(array_map(static fn(array $booking): int => (int) ($booking['number_of_guests'] ?? 0), $bookingTableVisibleRows)),
    'spend' => array_sum(array_map(static fn(array $booking): float => (float) ($booking['spend_amount'] ?? 0), $bookingTableVisibleRows)),
];
$bookingTableTotals['average_spend'] = $bookingTableTotals['covers'] > 0
    ? $bookingTableTotals['spend'] / $bookingTableTotals['covers']
    : 0;

$bookingHeadsUpItems = [];
$formatHeadsUpTime = static function (?string $timeValue): string {
    $timestamp = strtotime((string) $timeValue);
    return $timestamp ? date('g:i A', $timestamp) : 'Time TBC';
};

try {
    $headsUpStartDate = date('Y-m-d', strtotime('+1 day', $selectedTimestamp));
    if ($headsUpStartDate < $todayDate && $todayDate <= $dashboardWeekEnd) {
        $headsUpStartDate = $todayDate;
    }

    if ($headsUpStartDate <= $dashboardWeekEnd) {
        $headsUpDailyCoversStmt = $pdo->prepare("
            SELECT
                booking_date,
                COUNT(*) AS day_bookings,
                COALESCE(SUM(number_of_guests), 0) AS day_covers
            FROM bookings
            WHERE booking_date BETWEEN ? AND ?
              AND status <> 'pending'
              AND status <> 'cancelled'
            GROUP BY booking_date
            HAVING day_covers > 100
            ORDER BY booking_date ASC
        ");
        $headsUpDailyCoversStmt->execute([$headsUpStartDate, $dashboardWeekEnd]);
        $headsUpBusyDays = $headsUpDailyCoversStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($headsUpBusyDays as $busyDay) {
            $busyDate = (string) ($busyDay['booking_date'] ?? $selectedDate);
            $busyTimestamp = strtotime($busyDate) ?: $selectedTimestamp;
            $bookingHeadsUpItems[] = [
                'tone' => 'large',
                'icon' => 'bi-people-fill',
                'title' => 'Big Crowd on ' . date('l', $busyTimestamp),
                'meta' => number_format((int) ($busyDay['day_covers'] ?? 0)) . ' guests across ' . number_format((int) ($busyDay['day_bookings'] ?? 0)) . ' bookings',
                'date' => $busyDate,
                'sort_time' => '00:00:00',
                'action' => 'Open day',
            ];
        }

        $headsUpFunctionStmt = $pdo->prepare("
            SELECT
                b.booking_id,
                b.booking_date,
                b.start_time,
                b.number_of_guests,
                COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.user_id
            WHERE b.booking_date BETWEEN ? AND ?
              AND b.status <> 'pending'
              AND b.status <> 'cancelled'
              AND COALESCE(b.booking_type, 'normal') = 'function'
            ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC
            LIMIT 4
        ");
        $headsUpFunctionStmt->execute([$headsUpStartDate, $dashboardWeekEnd]);
        $headsUpFunctions = $headsUpFunctionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($headsUpFunctions as $functionBooking) {
            $functionDate = (string) ($functionBooking['booking_date'] ?? $selectedDate);
            $functionTimestamp = strtotime($functionDate) ?: $selectedTimestamp;
            $functionName = trim((string) ($functionBooking['customer_name'] ?? 'Guest'));
            $functionCovers = number_format((int) ($functionBooking['number_of_guests'] ?? 0)) . ' guests';
            $bookingHeadsUpItems[] = [
                'tone' => 'function',
                'icon' => 'bi-calendar-event',
                'title' => 'Function this ' . date('l', $functionTimestamp),
                'meta' => $functionName . ' - ' . $functionCovers . ' at ' . $formatHeadsUpTime($functionBooking['start_time'] ?? null),
                'date' => $functionDate,
                'sort_time' => (string) ($functionBooking['start_time'] ?? '00:00:00'),
                'action' => 'View booking',
            ];
        }

        usort($bookingHeadsUpItems, static function (array $left, array $right): int {
            $leftSort = (string) ($left['date'] ?? '') . ' ' . (string) ($left['sort_time'] ?? '');
            $rightSort = (string) ($right['date'] ?? '') . ' ' . (string) ($right['sort_time'] ?? '');

            return strcmp($leftSort, $rightSort);
        });

        $bookingHeadsUpItems = array_slice($bookingHeadsUpItems, 0, 4);
    }
} catch (Throwable $headsUpError) {
    $bookingHeadsUpItems = [];
}

$normalizeDashboardAreaName = static function (string $value): string {
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value))) ?? '';
};

$resolveDashboardFloorZoneKey = static function (string $name) use ($normalizeDashboardAreaName): string {
    $normalized = $normalizeDashboardAreaName($name);

    if (in_array($normalized, ['osf', 'osfpatio', 'outsidepatio'], true)) {
        return 'osf';
    }

    if (in_array($normalized, ['kookaburra', 'kookabura'], true)) {
        return 'kookaburra';
    }

    if (in_array($normalized, ['mainbar', 'bararea', 'bar', 'maindining', 'dining', 'mainfloor'], true)) {
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

$dashboardFloorBlueprints = [
    'stables' => ['label' => 'Stables', 'tone' => 'amber', 'icon' => 'bi-house-door', 'x' => 148, 'y' => 12, 'width' => 274, 'height' => 150],
    'kookaburra' => ['label' => 'Kookaburra', 'tone' => 'green', 'icon' => 'bi-tree', 'x' => 24, 'y' => 52, 'width' => 104, 'height' => 350],
    'wisteria' => ['label' => 'Wisteria', 'tone' => 'pink', 'icon' => 'bi-flower1', 'x' => 532, 'y' => 12, 'width' => 292, 'height' => 242],
    'schumack' => ['label' => 'Schumack', 'tone' => 'blue', 'icon' => 'bi-water', 'x' => 532, 'y' => 272, 'width' => 294, 'height' => 128],
    'main-bar' => ['label' => 'Main Bar', 'tone' => 'lavender', 'icon' => 'bi-cup-straw', 'x' => 142, 'y' => 186, 'width' => 372, 'height' => 216],
    'osf' => ['label' => 'OSF', 'tone' => 'mocha', 'icon' => 'bi-tree-fill', 'x' => 20, 'y' => 416, 'width' => 820, 'height' => 160],
];

$dashboardFloorTableRows = [];
$dashboardFloorAreaRows = [];
try {
    $dashboardFloorTableRows = $pdo->query("
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
            COALESCE(ta.name, 'Dining room') AS area_name
        FROM restaurant_tables rt
        LEFT JOIN table_areas ta ON rt.area_id = ta.area_id
        ORDER BY COALESCE(ta.display_order, 9999) ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $dashboardFloorAreaRows = $pdo->query("
        SELECT area_id, name, display_order, layout_x, layout_y, layout_width, layout_height, label_layout_x, label_layout_y
        FROM table_areas
        WHERE is_active = 1
        ORDER BY display_order ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $floorDataError) {
    $dashboardFloorTableRows = [];
    $dashboardFloorAreaRows = [];
}

$dashboardFloorBookingsByTableId = [];
$dashboardFloorUnassignedBookings = [];
foreach ($bookingTableRows as $bookingTableRow) {
    $bookingStartTime = (string) ($bookingTableRow['start_time'] ?? '');
    $isLunchBooking = $bookingStartTime >= '12:00:00' && $bookingStartTime < '17:00:00';
    $isDinnerBooking = $bookingStartTime >= '17:00:00';

    if (
        ($selectedCapacityService === 'lunch' && !$isLunchBooking)
        || ($selectedCapacityService === 'dinner' && !$isDinnerBooking)
    ) {
        continue;
    }

    $assignedTableIds = array_values(array_filter(array_map('intval', explode(',', (string) ($bookingTableRow['assigned_table_ids'] ?? '')))));

    if (empty($assignedTableIds)) {
        $dashboardFloorUnassignedBookings[] = $bookingTableRow;
        continue;
    }

    foreach ($assignedTableIds as $assignedTableId) {
        $dashboardFloorBookingsByTableId[$assignedTableId] ??= [];
        $dashboardFloorBookingsByTableId[$assignedTableId][] = $bookingTableRow;
    }
}

$dashboardFloorAreasById = [];
$dashboardFloorZones = [];
foreach ($dashboardFloorAreaRows as $areaRow) {
    $areaId = (int) ($areaRow['area_id'] ?? 0);
    if ($areaId < 1) {
        continue;
    }

    $zoneKey = $resolveDashboardFloorZoneKey((string) ($areaRow['name'] ?? ''));
    $blueprint = $dashboardFloorBlueprints[$zoneKey] ?? $dashboardFloorBlueprints['osf'];
    $zone = [
        'area_id' => $areaId,
        'name' => (string) ($areaRow['name'] ?? $blueprint['label']),
        'label' => (string) ($areaRow['name'] ?? $blueprint['label']),
        'tone' => (string) $blueprint['tone'],
        'icon' => (string) $blueprint['icon'],
        'zone_key' => $zoneKey,
        'x' => $areaRow['layout_x'] !== null ? (int) $areaRow['layout_x'] : (int) $blueprint['x'],
        'y' => $areaRow['layout_y'] !== null ? (int) $areaRow['layout_y'] : (int) $blueprint['y'],
        'width' => $areaRow['layout_width'] !== null ? (int) $areaRow['layout_width'] : (int) $blueprint['width'],
        'height' => $areaRow['layout_height'] !== null ? (int) $areaRow['layout_height'] : (int) $blueprint['height'],
    ];
    $defaultLabelOffset = $zoneKey === 'osf' ? 190 : 52;
    $zone['label_x'] = $areaRow['label_layout_x'] !== null ? (int) $areaRow['label_layout_x'] : (int) min($zone['x'] + $zone['width'] - 28, $zone['x'] + $defaultLabelOffset);
    $zone['label_y'] = $areaRow['label_layout_y'] !== null ? (int) $areaRow['label_layout_y'] : (int) ($zone['y'] + 14);

    $dashboardFloorAreasById[$areaId] = $zone;
    $dashboardFloorZones[] = $zone;
}

$dashboardFloorTablesByArea = [];
foreach ($dashboardFloorTableRows as $tableRow) {
    $areaId = (int) ($tableRow['area_id'] ?? 0);
    $dashboardFloorTablesByArea[$areaId] ??= [];
    $dashboardFloorTablesByArea[$areaId][] = $tableRow;
}

$buildDashboardFloorTables = static function (array $tables, array $bookingsByTableId) use ($dashboardFloorAreasById, $dashboardFloorTablesByArea): array {
    $builtTables = [];

    foreach ($tables as $tableRow) {
        $tableId = (int) ($tableRow['table_id'] ?? 0);
        $areaId = (int) ($tableRow['area_id'] ?? 0);
        $zone = $dashboardFloorAreasById[$areaId] ?? null;
        $areaTables = $dashboardFloorTablesByArea[$areaId] ?? [];
        $tableIndex = 0;
        foreach ($areaTables as $index => $areaTable) {
            if ((int) ($areaTable['table_id'] ?? 0) === $tableId) {
                $tableIndex = (int) $index;
                break;
            }
        }

        $zoneKey = $zone['zone_key'] ?? '';
        switch ($zoneKey) {
            case 'kookaburra':
                $layoutGrid = ['columns' => 1, 'gutter_x' => 0, 'gutter_y' => 88];
                break;
            case 'stables':
                $layoutGrid = ['columns' => 3, 'gutter_x' => 70, 'gutter_y' => 64];
                break;
            case 'wisteria':
            case 'schumack':
            case 'main-bar':
                $layoutGrid = ['columns' => 4, 'gutter_x' => 68, 'gutter_y' => 62];
                break;
            case 'osf':
                $layoutGrid = ['columns' => 9, 'gutter_x' => 88, 'gutter_y' => 72];
                break;
            default:
                $layoutGrid = ['columns' => 3, 'gutter_x' => 70, 'gutter_y' => 66];
                break;
        }

        $layoutX = $tableRow['layout_x'] !== null ? (int) $tableRow['layout_x'] : null;
        $layoutY = $tableRow['layout_y'] !== null ? (int) $tableRow['layout_y'] : null;

        if (($layoutX === null || $layoutY === null) && $zone) {
            $columns = max(1, (int) $layoutGrid['columns']);
            $layoutX = min((int) $zone['x'] + (int) $zone['width'] - 40, (int) $zone['x'] + 34 + ($tableIndex % $columns) * (int) $layoutGrid['gutter_x']);
            $layoutY = min((int) $zone['y'] + (int) $zone['height'] - 40, (int) $zone['y'] + 34 + floor($tableIndex / $columns) * (int) $layoutGrid['gutter_y']);
        }

        $tableBookings = $bookingsByTableId[$tableId] ?? [];
        $builtTables[] = array_merge($tableRow, [
            'layout_x' => $layoutX,
            'layout_y' => $layoutY,
            'tone' => $zone['tone'] ?? 'blue',
            'bookings' => $tableBookings,
            'is_occupied' => !empty($tableBookings),
        ]);
    }

    return $builtTables;
};

$dashboardFloorTables = $buildDashboardFloorTables($dashboardFloorTableRows, $dashboardFloorBookingsByTableId);
$dashboardTableAssignmentTables = $buildDashboardFloorTables($dashboardFloorTableRows, []);

$bookingAssignmentConflictRows = [];
try {
    $bookingAssignmentConflictStmt = $pdo->prepare("
        SELECT
            b.booking_id,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.special_request,
            COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name,
            COALESCE(
                GROUP_CONCAT(DISTINCT assigned_tables.table_id ORDER BY assigned_tables.table_number + 0, assigned_tables.table_number SEPARATOR ','),
                direct_table.table_id
            ) AS assigned_table_ids
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
        LEFT JOIN restaurant_tables assigned_tables ON bta.table_id = assigned_tables.table_id
        LEFT JOIN restaurant_tables direct_table ON b.table_id = direct_table.table_id
        WHERE b.booking_date = ?
          AND b.status IN ('pending', 'confirmed')
        GROUP BY b.booking_id
    ");
    $bookingAssignmentConflictStmt->execute([$selectedDate]);
    $bookingAssignmentConflictRows = $bookingAssignmentConflictStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $bookingAssignmentConflictError) {
    $bookingAssignmentConflictRows = [];
}

$assignmentConflictRowsByBookingId = [];
foreach ($bookingAssignmentConflictRows as $conflictRow) {
    $conflictBookingId = (int) ($conflictRow['booking_id'] ?? 0);
    if ($conflictBookingId > 0) {
        $assignmentConflictRowsByBookingId[$conflictBookingId] = $conflictRow;
    }
}

$formatDashboardAssignmentTime = static function (?string $timeValue): string {
    $timestamp = strtotime((string) $timeValue);
    return $timestamp ? date('g:i A', $timestamp) : 'Time TBC';
};

$dashboardTableAssignmentBookings = [];
foreach ($bookingTableRows as $bookingTableRow) {
    $bookingId = (int) ($bookingTableRow['booking_id'] ?? 0);
    if ($bookingId < 1) {
        continue;
    }

    $bookingDate = (string) ($bookingTableRow['booking_date'] ?? $selectedDate);
    $bookingDateTimestamp = strtotime($bookingDate);
    $selectedTableIds = array_values(array_filter(array_map('intval', explode(',', (string) ($bookingTableRow['assigned_table_ids'] ?? '')))));
    $bookingStart = (string) ($bookingTableRow['start_time'] ?? '18:00:00');
    $bookingEnd = (string) ($bookingTableRow['end_time'] ?? '20:00:00');
    $bookingConflicts = [];

    foreach ($assignmentConflictRowsByBookingId as $conflictBookingId => $conflictRow) {
        if ($conflictBookingId === $bookingId) {
            continue;
        }

        $conflictStart = (string) ($conflictRow['start_time'] ?? '');
        $conflictEnd = (string) ($conflictRow['end_time'] ?? '');
        if ($conflictStart === '' || $conflictEnd === '' || !($conflictStart < $bookingEnd && $conflictEnd > $bookingStart)) {
            continue;
        }

        $conflictTableIds = array_values(array_filter(array_map('intval', explode(',', (string) ($conflictRow['assigned_table_ids'] ?? '')))));
        foreach ($conflictTableIds as $conflictTableId) {
            $bookingConflicts[(string) $conflictTableId] = [
                'name' => (string) ($conflictRow['customer_name'] ?? 'Guest'),
                'time' => $formatDashboardAssignmentTime($conflictRow['start_time'] ?? null),
                'guests' => (int) ($conflictRow['number_of_guests'] ?? 0),
                'notes' => trim((string) ($conflictRow['special_request'] ?? '')),
            ];
        }
    }

    $dashboardTableAssignmentBookings[(string) $bookingId] = [
        'id' => $bookingId,
        'name' => (string) ($bookingTableRow['customer_name'] ?? 'Guest'),
        'date' => $bookingDateTimestamp ? date('D, j M Y', $bookingDateTimestamp) : $bookingDate,
        'time' => $formatDashboardAssignmentTime($bookingTableRow['start_time'] ?? null),
        'time_range' => $formatDashboardAssignmentTime($bookingTableRow['start_time'] ?? null) . ' - ' . $formatDashboardAssignmentTime($bookingTableRow['end_time'] ?? null),
        'guests' => (int) ($bookingTableRow['number_of_guests'] ?? 0),
        'type' => inboxChipForBookingType((string) ($bookingTableRow['booking_type'] ?? 'normal')),
        'selected_table_ids' => $selectedTableIds,
        'conflicts' => $bookingConflicts,
    ];
}

$dashboardTableAssignmentPayloadJson = json_encode(
    ['bookings' => $dashboardTableAssignmentBookings],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);
$dashboardTableAssignmentPayloadJson = $dashboardTableAssignmentPayloadJson !== false ? $dashboardTableAssignmentPayloadJson : '{"bookings":{}}';

$formatMoney = static function (float $amount): string {
    return '$' . number_format($amount, 0);
};

$formatBookingTableTime = static function (?string $timeValue): string {
    $timestamp = strtotime((string) $timeValue);
    return $timestamp ? date('g:i A', $timestamp) : 'Time TBC';
};

$styleVersion = (string) (@filemtime(__DIR__ . '/../../assets/css/style.css') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Home | DineMate Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../partials/admin-new-sidebar.php'; ?>

        <main class="main-content">
            <header class="page-header">
                <div>
                    <h1 class="page-title">Old Canberra Inn</h1>
                    <div class="page-subtitle">
                        <?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </div>
                </div>

                <div class="header-actions" aria-label="Dashboard date and booking actions">
                    <a class="icon-btn" href="<?php echo htmlspecialchars($dashboardUrl(['date' => $previousDate]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous day">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </a>

                    <label class="date-btn">
                        <span><?php echo htmlspecialchars($dashboardDateValue, ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                        <input type="date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Dashboard date">
                    </label>

                    <a class="icon-btn" href="<?php echo htmlspecialchars($dashboardUrl(['date' => $nextDate]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next day">
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </a>

                    <a class="secondary-btn" href="<?php echo htmlspecialchars($dashboardUrl(['date' => $todayDate]), ENT_QUOTES, 'UTF-8'); ?>">Today</a>

                    <button class="primary-btn header-add-booking-btn" type="button" data-admin-booking-create-open>
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span>Add Booking</span>
                    </button>

                    <?php include __DIR__ . '/../partials/admin-notification-dropdown.php'; ?>

                </div>
            </header>

            <section class="kpi-grid" aria-label="Daily dashboard metrics">
                <article class="kpi-card card">
                    <div class="kpi-icon icon-blue">
                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                    </div>
                    <div class="kpi-copy">
                        <div class="kpi-main">
                            <div class="kpi-value"><?php echo number_format($selectedBookingsCount); ?></div>
                            <div class="kpi-label">Total Bookings</div>
                        </div>
                        <div class="kpi-subtext"><?php echo number_format($selectedGuestsCount); ?> Guests</div>
                    </div>
                </article>

                <article class="kpi-card card">
                    <div class="kpi-icon icon-green">
                        <i class="bi bi-people" aria-hidden="true"></i>
                    </div>
                    <div class="kpi-copy">
                        <div class="kpi-main">
                            <div class="kpi-value"><?php echo number_format($selectedLunchCount); ?></div>
                            <div class="kpi-label">Lunch Bookings</div>
                        </div>
                        <div class="kpi-subtext"><?php echo number_format($selectedLunchGuestsCount); ?> Guests</div>
                    </div>
                </article>

                <article class="kpi-card card">
                    <div class="kpi-icon icon-orange">
                        <i class="bi bi-people-fill" aria-hidden="true"></i>
                    </div>
                    <div class="kpi-copy">
                        <div class="kpi-main">
                            <div class="kpi-value"><?php echo number_format($selectedDinnerCount); ?></div>
                            <div class="kpi-label">Dinner Bookings</div>
                        </div>
                        <div class="kpi-subtext"><?php echo number_format($selectedDinnerGuestsCount); ?> Guests</div>
                    </div>
                </article>

                <article class="kpi-card card">
                    <div class="kpi-icon icon-purple">
                        <i class="bi bi-person-check" aria-hidden="true"></i>
                    </div>
                    <div class="kpi-copy">
                        <div class="kpi-main">
                            <div class="kpi-value"><?php echo number_format($dashboardLunchCapacityPercent); ?>%</div>
                            <div class="kpi-label">
                                Capacity
                                (<a class="kpi-toggle-link" href="<?php echo htmlspecialchars($dashboardUrl(['capacity_service' => $nextCapacityService]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Switch capacity card to <?php echo htmlspecialchars($nextCapacityService, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($selectedCapacityLabel, ENT_QUOTES, 'UTF-8'); ?></a>)
                            </div>
                        </div>
                        <div class="kpi-subtext"><?php echo number_format($selectedCapacityGuestsCount); ?> of <?php echo number_format($dashboardLunchCapacity); ?> Guests</div>
                    </div>
                </article>

                <article class="kpi-card card">
                    <div class="kpi-icon icon-orange">
                        <i class="bi bi-calendar2-check-fill" aria-hidden="true"></i>
                    </div>
                    <div class="kpi-copy">
                        <div class="kpi-main">
                            <div class="kpi-value"><?php echo number_format($pendingActionsCount); ?></div>
                            <div class="kpi-label">Pending Actions</div>
                        </div>
                        <div class="kpi-subtext">
                            <?php echo htmlspecialchars($formatActionLabel($selectedPendingRequestCount, 'request'), ENT_QUOTES, 'UTF-8'); ?> &middot;
                            <?php echo htmlspecialchars($formatActionLabel($selectedUnassignedActionCount, 'unassigned', 'unassigned'), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>
                </article>
            </section>

            <section class="dashboard-grid">
                <div class="dashboard-column dashboard-requests-column">
                    <nav class="tabs-bar dashboard-column-tabs request-column-tabs" aria-label="Request panel views">
                        <a
                            class="tab-item<?php echo $selectedRequestPanel === 'requests' ? ' active' : ''; ?>"
                            href="<?php echo htmlspecialchars($dashboardUrl(['request_panel' => 'requests']), ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $selectedRequestPanel === 'requests' ? 'aria-current="page"' : ''; ?>
                        >
                            <i class="bi bi-list-ul" aria-hidden="true"></i>
                            <span class="tab-label">Requests</span>
                            <?php if ($selectedPendingRequestCount > 0): ?>
                                <span class="count-badge"><?php echo number_format($selectedPendingRequestCount); ?></span>
                            <?php endif; ?>
                        </a>

                        <a
                            class="tab-item<?php echo $selectedRequestPanel === 'unassigned' ? ' active' : ''; ?>"
                            href="<?php echo htmlspecialchars($dashboardUrl(['request_panel' => 'unassigned']), ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $selectedRequestPanel === 'unassigned' ? 'aria-current="page"' : ''; ?>
                        >
                            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                            <span class="tab-label">No Table</span>
                            <?php if ($selectedUnassignedActionCount > 0): ?>
                                <span class="count-badge"><?php echo number_format($selectedUnassignedActionCount); ?></span>
                            <?php endif; ?>
                        </a>
                    </nav>

                    <aside class="requests-panel card">
                    <div class="request-list<?php echo empty($requestPanelBookings) ? ' is-empty' : ''; ?>">
                        <?php if (empty($requestPanelBookings)): ?>
                            <div class="empty-panel-note">
                                <span class="empty-panel-icon" aria-hidden="true">
                                    <i class="bi bi-calendar-check"></i>
                                </span>
                                <strong>All clear</strong>
                                <span><?php echo htmlspecialchars($requestPanelEmptyMessage, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($requestPanelBookings as $requestBooking): ?>
                                <?php
                                $requestNote = trim((string) ($requestBooking['special_request'] ?? ''));
                                $requestBookingType = strtolower(trim((string) ($requestBooking['booking_type'] ?? 'normal')));
                                $requestBookingTypeLabel = $requestBookingType === 'trivia'
                                    ? 'Trivia'
                                    : ($requestBookingType === 'function' ? 'Function' : '');
                                ?>
                                <article class="request-card">
                                    <div class="request-line request-line-primary">
                                        <span class="request-name-wrap">
                                            <strong><?php echo htmlspecialchars((string) ($requestBooking['customer_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if ($requestBookingTypeLabel !== ''): ?>
                                                <span class="request-type-pill <?php echo htmlspecialchars($requestBookingType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($requestBookingTypeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span><?php echo htmlspecialchars($formatRequestTime((string) ($requestBooking['booking_date'] ?? ''), (string) ($requestBooking['start_time'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="request-covers">
                                            <i class="bi bi-people-fill" aria-hidden="true"></i>
                                            <?php echo number_format((int) ($requestBooking['number_of_guests'] ?? 0)); ?>
                                        </span>
                                    </div>

                                    <?php if ($requestNote !== ''): ?>
                                        <div class="request-note" title="<?php echo htmlspecialchars($requestNote, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($requestNote, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>

                                    <div class="request-actions">
                                        <?php if ($selectedRequestPanel === 'requests'): ?>
                                            <button class="outline-btn" type="button" data-decline-request-id="<?php echo (int) ($requestBooking['booking_id'] ?? 0); ?>">Decline</button>
                                            <button class="confirm-btn" type="button" data-confirm-request-id="<?php echo (int) ($requestBooking['booking_id'] ?? 0); ?>">Confirm</button>
                                        <?php else: ?>
                                            <button
                                                class="outline-btn"
                                                type="button"
                                                data-admin-booking-open
                                                data-booking-id="<?php echo (int) ($requestBooking['booking_id'] ?? 0); ?>"
                                                data-booking-name="<?php echo htmlspecialchars((string) ($requestBooking['customer_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-booking-date="<?php echo htmlspecialchars((string) ($requestBooking['booking_date'] ?? $selectedDate), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-booking-time="<?php echo htmlspecialchars($formatRequestTime((string) ($requestBooking['booking_date'] ?? ''), (string) ($requestBooking['start_time'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-booking-guests="<?php echo (int) ($requestBooking['number_of_guests'] ?? 0); ?>"
                                                data-booking-table="No table"
                                                data-booking-status="<?php echo htmlspecialchars(getBookingStatusLabel((string) ($requestBooking['status'] ?? 'confirmed')), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-booking-notes="<?php echo htmlspecialchars($requestNote, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-booking-action-url="admin_bookings.php?status_view=needs_action&booking_search=<?php echo urlencode((string) ($requestBooking['booking_id'] ?? '')); ?>"
                                            >View</button>
                                            <?php if (isset($dashboardTableAssignmentBookings[(string) ($requestBooking['booking_id'] ?? '')])): ?>
                                                <button class="confirm-btn" type="button" data-home-table-assign-open data-booking-id="<?php echo (int) ($requestBooking['booking_id'] ?? 0); ?>">Assign</button>
                                            <?php else: ?>
                                                <a class="confirm-btn" href="admin_bookings.php?status_view=needs_action&booking_search=<?php echo urlencode((string) ($requestBooking['booking_id'] ?? '')); ?>">Assign</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <a href="admin_bookings.php?status_view=needs_action&booking_date_start=<?php echo urlencode($selectedDate); ?>&booking_date_end=<?php echo urlencode($selectedDate); ?>" class="view-link">
                        View all <?php echo htmlspecialchars(strtolower($selectedRequestPanelLabel), ENT_QUOTES, 'UTF-8'); ?>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                    </aside>
                </div>

                <div class="dashboard-column dashboard-bookings-column">
                    <nav class="tabs-bar dashboard-column-tabs booking-column-tabs" aria-label="Dashboard views">
                        <?php foreach ($dashboardTabs as $dashboardTab): ?>
                            <?php
                            $tabKey = (string) $dashboardTab['key'];
                            $isActiveTab = $selectedDashboardTab === $tabKey;
                            $dashboardTabCount = $dashboardTabCounts[$tabKey] ?? null;
                            ?>
                            <a
                                class="tab-item<?php echo $isActiveTab ? ' active' : ''; ?>"
                                href="<?php echo htmlspecialchars($dashboardUrl(['dashboard_tab' => $tabKey]), ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo $isActiveTab ? 'aria-current="page"' : ''; ?>
                            >
                                <i class="bi <?php echo htmlspecialchars((string) $dashboardTab['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                <span class="tab-label"><?php echo htmlspecialchars((string) $dashboardTab['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($dashboardTabCount !== null && $dashboardTabCount > 0): ?>
                                    <span class="count-badge"><?php echo number_format($dashboardTabCount); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>

                    <?php if (in_array($selectedDashboardTab, ['bookings', 'trivia', 'functions'], true)): ?>
                        <section class="booking-table-panel card" aria-label="<?php echo htmlspecialchars($selectedDashboardTabLabel, ENT_QUOTES, 'UTF-8'); ?> table">
                            <div class="booking-table-head">
                                <div>
                                    <h2><?php echo htmlspecialchars($bookingTableTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                                </div>
                            </div>

                            <div class="booking-table-wrap">
                                <table class="booking-table">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Name</th>
                                            <th>Table</th>
                                            <th>Notes</th>
                                            <th>Guests</th>
                                            <th>Status</th>
                                            <th aria-label="Actions"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($bookingTableVisibleRows)): ?>
                                            <tr class="booking-table-empty-row">
                                                <td colspan="7">
                                                    <div class="booking-table-empty">
                                                        <span class="booking-table-empty-icon">
                                                            <i class="bi <?php echo htmlspecialchars($bookingTableEmptyIcon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                                        </span>
                                                        <strong><?php echo htmlspecialchars($bookingTableEmptyTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <span><?php echo htmlspecialchars($bookingTableEmptyMessage, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($bookingTableVisibleRows as $index => $booking): ?>
                                                <?php
                                                $bookingName = (string) ($booking['customer_name'] ?? 'Guest');
                                                $bookingTime = $formatBookingTableTime($booking['start_time'] ?? null);
                                                $assignedTables = trim((string) ($booking['assigned_table_numbers'] ?? ''));
                                                $hasAssignedTable = $assignedTables !== '';
                                                $tableText = $hasAssignedTable ? 'Table ' . $assignedTables : 'No table';
                                                $areaText = trim((string) ($booking['assigned_area_names'] ?? 'Dining room'));
                                                $noteText = trim((string) ($booking['special_request'] ?? ''));
                                                $status = strtolower(trim((string) ($booking['status'] ?? 'confirmed')));
                                                $statusLabel = getBookingStatusLabel($status);
                                                ?>
                                                <tr<?php echo $hasAssignedTable ? '' : ' class="is-missing-table"'; ?>>
                                                    <td class="booking-table-time"><?php echo htmlspecialchars($bookingTime, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <button
                                                            class="booking-table-guest"
                                                            type="button"
                                                            data-admin-booking-open
                                                            data-booking-id="<?php echo (int) ($booking['booking_id'] ?? 0); ?>"
                                                            data-booking-name="<?php echo htmlspecialchars($bookingName, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-date="<?php echo htmlspecialchars((string) ($booking['booking_date'] ?? $selectedDate), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-time="<?php echo htmlspecialchars($bookingTime, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-guests="<?php echo (int) ($booking['number_of_guests'] ?? 0); ?>"
                                                            data-booking-table="<?php echo htmlspecialchars($tableText, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-area="<?php echo htmlspecialchars($areaText, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-status="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-phone="<?php echo htmlspecialchars((string) ($booking['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-email="<?php echo htmlspecialchars((string) ($booking['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-notes="<?php echo htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-action-url="admin_bookings.php?booking_search=<?php echo urlencode((string) ($booking['booking_id'] ?? '')); ?>&booking_date_start=<?php echo urlencode((string) ($booking['booking_date'] ?? $selectedDate)); ?>&booking_date_end=<?php echo urlencode((string) ($booking['booking_date'] ?? $selectedDate)); ?>"
                                                        >
                                                            <span class="booking-table-guest-copy">
                                                                <strong><?php echo htmlspecialchars($bookingName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                            </span>
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <span class="booking-table-assignment<?php echo $hasAssignedTable ? '' : ' is-missing'; ?>">
                                                            <strong><?php echo htmlspecialchars($tableText, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                            <?php if ($hasAssignedTable && $areaText !== ''): ?>
                                                                <small><?php echo htmlspecialchars($areaText, ENT_QUOTES, 'UTF-8'); ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td class="booking-table-notes<?php echo $noteText === '' ? ' is-empty' : ''; ?>" title="<?php echo htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($noteText !== '' ? $noteText : '-', ENT_QUOTES, 'UTF-8'); ?>
                                                    </td>
                                                    <td>
                                                        <span class="booking-table-covers">
                                                            <i class="bi bi-people-fill" aria-hidden="true"></i>
                                                            <?php echo number_format((int) ($booking['number_of_guests'] ?? 0)); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="booking-status-pill <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </td>
                                                    <td class="booking-table-action-cell">
                                                        <button
                                                            class="booking-table-action"
                                                            type="button"
                                                            aria-label="Open booking"
                                                            data-admin-booking-open
                                                            data-booking-id="<?php echo (int) ($booking['booking_id'] ?? 0); ?>"
                                                            data-booking-name="<?php echo htmlspecialchars($bookingName, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-date="<?php echo htmlspecialchars((string) ($booking['booking_date'] ?? $selectedDate), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-time="<?php echo htmlspecialchars($bookingTime, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-guests="<?php echo (int) ($booking['number_of_guests'] ?? 0); ?>"
                                                            data-booking-table="<?php echo htmlspecialchars($tableText, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-area="<?php echo htmlspecialchars($areaText, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-status="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-phone="<?php echo htmlspecialchars((string) ($booking['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-email="<?php echo htmlspecialchars((string) ($booking['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-notes="<?php echo htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-booking-action-url="admin_bookings.php?booking_search=<?php echo urlencode((string) ($booking['booking_id'] ?? '')); ?>&booking_date_start=<?php echo urlencode((string) ($booking['booking_date'] ?? $selectedDate)); ?>&booking_date_end=<?php echo urlencode((string) ($booking['booking_date'] ?? $selectedDate)); ?>"
                                                        >
                                                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($selectedDashboardTab === 'bookings' && !empty($bookingHeadsUpItems)): ?>
                                <section class="booking-heads-up" aria-label="Booking alerts">
                                    <?php foreach ($bookingHeadsUpItems as $headsUpItem): ?>
                                        <?php
                                        $headsUpBookingDate = (string) ($headsUpItem['date'] ?? $selectedDate);
                                        $headsUpTitle = (string) ($headsUpItem['title'] ?? 'Heads Up');
                                        $headsUpMeta = (string) ($headsUpItem['meta'] ?? '');
                                        $headsUpTone = (string) ($headsUpItem['tone'] ?? 'large');
                                        $headsUpIcon = (string) ($headsUpItem['icon'] ?? 'bi-info-circle');
                                        $headsUpAction = (string) ($headsUpItem['action'] ?? 'Open day');
                                        ?>
                                        <a class="booking-heads-up-item <?php echo htmlspecialchars($headsUpTone, ENT_QUOTES, 'UTF-8'); ?>" href="admin_bookings.php?status_view=needs_action&booking_date_start=<?php echo urlencode($headsUpBookingDate); ?>&booking_date_end=<?php echo urlencode($headsUpBookingDate); ?>">
                                            <span class="booking-heads-up-item-icon"><i class="bi <?php echo htmlspecialchars($headsUpIcon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
                                            <span class="booking-heads-up-copy">
                                                <strong><?php echo htmlspecialchars($headsUpTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <small><?php echo htmlspecialchars($headsUpMeta, ENT_QUOTES, 'UTF-8'); ?></small>
                                            </span>
                                            <span class="booking-heads-up-action">
                                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </section>
                            <?php endif; ?>
                        </section>
                    <?php elseif ($selectedDashboardTab === 'timeline'): ?>
                        <section class="booking-timeline-panel card" aria-label="Timeline view">
                            <div class="booking-table-head">
                                <div>
                                    <h2><?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?></h2>
                                </div>
                                <a class="icon-btn booking-table-expand-btn" href="../timeline/timeline.php?date=<?php echo urlencode($selectedDate); ?>" aria-label="Open full timeline" title="Open full timeline">
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                </a>
                            </div>

                            <iframe
                                class="booking-timeline-frame"
                                title="Timeline for <?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                src="../timeline/timeline.php?embedded=1&date=<?php echo urlencode($selectedDate); ?>"
                                loading="lazy"
                            ></iframe>
                        </section>
                    <?php elseif ($selectedDashboardTab === 'floor'): ?>
                        <section class="dashboard-floor-panel card" aria-label="Floor view">
                            <div class="booking-table-head">
                                <div>
                                    <h2><?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?></h2>
                                </div>
                                <nav class="dashboard-service-toggle dashboard-floor-service-toggle" aria-label="Floor service">
                                    <a
                                        class="<?php echo $selectedCapacityService === 'all' ? 'is-active' : ''; ?>"
                                        href="<?php echo htmlspecialchars($dashboardUrl(['dashboard_tab' => 'floor', 'capacity_service' => 'all']), ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $selectedCapacityService === 'all' ? 'aria-current="true"' : ''; ?>
                                    ><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i><span>All</span></a>
                                    <a
                                        class="<?php echo $selectedCapacityService === 'lunch' ? 'is-active' : ''; ?>"
                                        href="<?php echo htmlspecialchars($dashboardUrl(['dashboard_tab' => 'floor', 'capacity_service' => 'lunch']), ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $selectedCapacityService === 'lunch' ? 'aria-current="true"' : ''; ?>
                                    ><i class="bi bi-sun" aria-hidden="true"></i><span>Lunch</span></a>
                                    <a
                                        class="<?php echo $selectedCapacityService === 'dinner' ? 'is-active' : ''; ?>"
                                        href="<?php echo htmlspecialchars($dashboardUrl(['dashboard_tab' => 'floor', 'capacity_service' => 'dinner']), ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $selectedCapacityService === 'dinner' ? 'aria-current="true"' : ''; ?>
                                    ><i class="bi bi-moon-stars" aria-hidden="true"></i><span>Dinner</span></a>
                                </nav>
                                <a class="icon-btn booking-table-expand-btn" href="tables-management.php" aria-label="Open full floor view" title="Open full floor view">
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                </a>
                            </div>

                            <div class="dashboard-floor-content">
                                <?php if (empty($dashboardFloorTables)): ?>
                                    <div class="dashboard-floor-empty">No tables have been created yet.</div>
                                <?php else: ?>
                                    <div class="dashboard-floor-map">
                                        <div class="dashboard-floor-viewport" data-dashboard-floor-viewport>
                                            <div class="dashboard-floor-stage">
                                                <div class="dashboard-floor-canvas" role="img" aria-label="Restaurant floor plan for <?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php foreach ($dashboardFloorZones as $zone): ?>
                                                        <div
                                                            class="dashboard-floor-zone tone-<?php echo htmlspecialchars((string) $zone['tone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            style="left: <?php echo (int) $zone['x']; ?>px; top: <?php echo (int) $zone['y']; ?>px; width: <?php echo (int) $zone['width']; ?>px; height: <?php echo (int) $zone['height']; ?>px;"
                                                            aria-hidden="true"
                                                        ></div>
                                                        <div
                                                            class="dashboard-floor-label tone-<?php echo htmlspecialchars((string) $zone['tone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            style="left: <?php echo (int) $zone['label_x']; ?>px; top: <?php echo (int) $zone['label_y']; ?>px;"
                                                        >
                                                            <i class="bi <?php echo htmlspecialchars((string) ($zone['icon'] ?? 'bi-geo-alt'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                                            <span><?php echo htmlspecialchars((string) $zone['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>

                                                    <?php foreach ($dashboardFloorTables as $floorTable): ?>
                                                        <?php
                                                        $floorTableId = (int) ($floorTable['table_id'] ?? 0);
                                                        if ($floorTableId < 1 || $floorTable['layout_x'] === null || $floorTable['layout_y'] === null) {
                                                            continue;
                                                        }

                                                        $floorTableBookings = $floorTable['bookings'] ?? [];
                                                        $floorFirstBooking = $floorTableBookings[0] ?? null;
                                                        $floorTableStatus = strtolower(trim((string) ($floorTable['status'] ?? 'available')));
                                                        $floorTableUnavailable = (int) ($floorTable['reservable'] ?? 1) !== 1 || in_array($floorTableStatus, ['inactive', 'disabled'], true);
                                                        $floorTableDisplayNumber = preg_replace('/^T/i', '', (string) ($floorTable['table_number'] ?? ''));
                                                        $floorBookingTooltip = 'Available';
                                                        $floorBookingName = '';
                                                        $floorBookingTime = '';
                                                        $floorBookingNote = '';
                                                        $floorBookingGuests = 0;

                                                        if ($floorFirstBooking) {
                                                            $floorBookingName = (string) ($floorFirstBooking['customer_name'] ?? 'Guest');
                                                            $floorBookingTime = $formatBookingTableTime($floorFirstBooking['start_time'] ?? null);
                                                            $floorCardTimeTimestamp = strtotime((string) ($floorFirstBooking['start_time'] ?? ''));
                                                            $floorCardTime = $floorCardTimeTimestamp ? date('g:i', $floorCardTimeTimestamp) : 'Time TBC';
                                                            $floorBookingNote = trim((string) ($floorFirstBooking['special_request'] ?? ''));
                                                            $floorBookingGuests = (int) ($floorFirstBooking['number_of_guests'] ?? 0);
                                                            $floorBookingTooltip = $floorBookingName . ' at ' . $floorBookingTime;
                                                        } elseif ($floorTableUnavailable) {
                                                            $floorBookingTooltip = 'Unavailable';
                                                        }

                                                        $floorTableDetailBookings = array_map(static function (array $booking) use ($selectedDate, $formatBookingTableTime): array {
                                                            $bookingDate = (string) ($booking['booking_date'] ?? $selectedDate);
                                                            $bookingTimestamp = strtotime($bookingDate);
                                                            $phoneValue = trim((string) ($booking['customer_phone'] ?? ''));
                                                            $emailValue = trim((string) ($booking['customer_email'] ?? ''));

                                                            return [
                                                                'id' => (int) ($booking['booking_id'] ?? 0),
                                                                'name' => (string) ($booking['customer_name'] ?? 'Guest'),
                                                                'time' => $formatBookingTableTime($booking['start_time'] ?? null),
                                                                'date' => $bookingTimestamp ? date('D, j M Y', $bookingTimestamp) : $bookingDate,
                                                                'guests' => (int) ($booking['number_of_guests'] ?? 0),
                                                                'phone' => $phoneValue,
                                                                'email' => $emailValue,
                                                                'contact' => $phoneValue !== '' ? $phoneValue : $emailValue,
                                                                'notes' => trim((string) ($booking['special_request'] ?? '')),
                                                                'status' => getBookingStatusLabel((string) ($booking['status'] ?? 'confirmed')),
                                                                'url' => 'admin_bookings.php?booking_search=' . urlencode((string) ($booking['booking_id'] ?? '')) . '&booking_date_start=' . urlencode($bookingDate) . '&booking_date_end=' . urlencode($bookingDate),
                                                            ];
                                                        }, $floorTableBookings);

                                                        $floorTableDetailPayload = [
                                                            'id' => $floorTableId,
                                                            'number' => (string) ($floorTable['table_number'] ?? ''),
                                                            'area' => (string) ($floorTable['area_name'] ?? 'Dining room'),
                                                            'capacity' => (int) ($floorTable['capacity'] ?? 0),
                                                            'available' => !$floorTableUnavailable,
                                                            'bookings' => $floorTableDetailBookings,
                                                        ];
                                                        ?>
                                                        <button
                                                            class="dashboard-floor-table tone-<?php echo htmlspecialchars((string) $floorTable['tone'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($floorTable['is_occupied']) ? ' is-occupied' : ''; ?><?php echo $floorTableUnavailable ? ' is-unreservable' : ''; ?>"
                                                            type="button"
                                                            title="Table <?php echo htmlspecialchars((string) ($floorTable['table_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($floorBookingTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                                            style="left: <?php echo (int) $floorTable['layout_x']; ?>px; top: <?php echo (int) $floorTable['layout_y']; ?>px;"
                                                            data-dashboard-floor-table="<?php echo htmlspecialchars(json_encode($floorTableDetailPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>"
                                                        >
                                                            <span class="dashboard-floor-table-shell">
                                                                <span class="dashboard-floor-table-card">
                                                                    <?php if ($floorFirstBooking): ?>
                                                                        <span class="dashboard-floor-card-time"><?php echo htmlspecialchars($floorCardTime, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                        <span class="dashboard-floor-card-main"><?php echo htmlspecialchars($floorBookingName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                        <?php if ($floorBookingNote !== ''): ?>
                                                                            <span class="dashboard-floor-card-note-icon" title="<?php echo htmlspecialchars($floorBookingNote, ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span>
                                                                        <?php endif; ?>
                                                                        <span class="dashboard-floor-card-corner"><i class="bi bi-people-fill" aria-hidden="true"></i><?php echo number_format($floorBookingGuests); ?></span>
                                                                    <?php else: ?>
                                                                        <span class="dashboard-floor-card-number"><?php echo htmlspecialchars($floorTableDisplayNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                        <span class="dashboard-floor-card-corner"><i class="bi bi-people-fill" aria-hidden="true"></i><?php echo number_format((int) ($floorTable['capacity'] ?? 0)); ?></span>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <?php if (count($floorTableBookings) > 1): ?>
                                                                    <span class="dashboard-floor-booking-dot"><?php echo number_format(count($floorTableBookings)); ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </button>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <aside class="dashboard-floor-detail" data-dashboard-floor-detail aria-label="Selected table booking details">
                                        <div class="dashboard-floor-detail-empty" data-dashboard-floor-detail-empty>
                                            <i class="bi bi-layout-sidebar-inset-reverse" aria-hidden="true"></i>
                                            <strong>Select a table</strong>
                                            <span>Booking details will appear here.</span>
                                        </div>

                                        <div class="dashboard-floor-detail-card" data-dashboard-floor-detail-card hidden>
                                            <div class="dashboard-floor-detail-head">
                                                <h3 data-dashboard-detail-table>Table</h3>
                                                <button class="dashboard-floor-detail-close" type="button" data-dashboard-floor-detail-close aria-label="Close details">
                                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                </button>
                                            </div>

                                            <div class="dashboard-floor-booking-tabs" data-dashboard-detail-tabs></div>

                                            <div class="dashboard-floor-detail-body" data-dashboard-detail-body>
                                                <div class="dashboard-floor-detail-time">
                                                    <strong data-dashboard-detail-time></strong>
                                                    <span data-dashboard-detail-date></span>
                                                </div>

                                                <div class="dashboard-floor-detail-line">
                                                    <i class="bi bi-people" aria-hidden="true"></i>
                                                    <strong data-dashboard-detail-guests></strong>
                                                </div>

                                                <div class="dashboard-floor-detail-guest">
                                                    <strong data-dashboard-detail-name></strong>
                                                    <div class="dashboard-floor-detail-contact-list" data-dashboard-detail-contacts></div>
                                                </div>

                                                <div class="dashboard-floor-detail-notes" data-dashboard-detail-notes-wrap>
                                                    <span>Notes</span>
                                                    <p data-dashboard-detail-notes></p>
                                                </div>

                                                <div class="dashboard-floor-detail-actions">
                                                    <a class="outline-btn dashboard-floor-detail-action" href="admin_bookings.php?booking_date_start=<?php echo urlencode($selectedDate); ?>&booking_date_end=<?php echo urlencode($selectedDate); ?>" data-dashboard-detail-view>View Booking</a>
                                                    <a class="confirm-btn dashboard-floor-detail-action" href="admin_bookings.php?booking_date_start=<?php echo urlencode($selectedDate); ?>&booking_date_end=<?php echo urlencode($selectedDate); ?>" data-dashboard-detail-edit>Add Booking</a>
                                                </div>
                                            </div>
                                        </div>
                                    </aside>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    <?php
    $adminBookingCreateDefaultDate = $selectedDate;
    $adminBookingCreateMinDate = $todayDate;
    $adminBookingCreateEndpoint = '../actions/create-booking.php';
    include __DIR__ . '/../partials/admin-booking-create-modal.php';
    ?>
    <div class="admin-modal" data-admin-booking-detail-modal hidden>
        <div class="admin-modal-card admin-booking-detail-modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-booking-detail-title">
            <header class="admin-modal-head">
                <div>
                    <h2 id="admin-booking-detail-title" data-booking-detail-name>Booking</h2>
                    <p data-booking-detail-subtitle></p>
                </div>
                <button class="icon-btn admin-modal-close" type="button" data-admin-modal-close aria-label="Close booking details">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>

            <div class="admin-booking-detail-grid">
                <div>
                    <span>Date</span>
                    <strong data-booking-detail-date></strong>
                </div>
                <div>
                    <span>Time</span>
                    <strong data-booking-detail-time></strong>
                </div>
                <div>
                    <span>Guests</span>
                    <strong data-booking-detail-guests></strong>
                </div>
                <div>
                    <span>Table</span>
                    <strong data-booking-detail-table></strong>
                </div>
                <div>
                    <span>Status</span>
                    <strong data-booking-detail-status></strong>
                </div>
                <div>
                    <span>Contact</span>
                    <strong data-booking-detail-contact></strong>
                </div>
                <div class="admin-modal-field-wide">
                    <span>Notes</span>
                    <p data-booking-detail-notes></p>
                </div>
            </div>

            <footer class="admin-modal-actions">
                <button class="secondary-btn" type="button" data-admin-modal-close>Close</button>
                <a class="primary-btn" href="admin_bookings.php" data-booking-detail-action>
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                    <span>Open in Bookings</span>
                </a>
            </footer>
        </div>
    </div>

    <div class="admin-inbox-table-modal" data-home-table-assignment-modal hidden>
        <div class="admin-inbox-table-modal-card" role="dialog" aria-modal="true" aria-labelledby="home-table-assignment-title">
            <header class="admin-inbox-table-modal-head">
                <div>
                    <h2 id="home-table-assignment-title">Select Table</h2>
                    <p>
                        <span data-home-assignment-date><?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        at
                        <span data-home-assignment-time>Time TBC</span>
                        for
                        <span data-home-assignment-guests>0</span>
                        guests
                    </p>
                </div>
                <button type="button" class="icon-btn admin-inbox-table-modal-close" data-home-table-assignment-close aria-label="Close table selection">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>

            <form method="post" action="../actions/update-booking-tables.php" class="admin-inbox-table-form" data-home-table-assignment-form>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminActionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="booking_id" value="" data-home-assignment-booking-id>
                <input type="hidden" name="redirect_url" value="admin_home.php?date=<?php echo urlencode($selectedDate); ?>&dashboard_tab=floor&capacity_service=<?php echo urlencode($selectedCapacityService); ?>">
                <input type="hidden" name="table_id" value="" data-table-selected-input>
                <span data-table-selected-fields hidden></span>

                <div class="admin-inbox-table-modal-body">
                    <aside class="admin-inbox-table-modal-side" aria-label="Booking and selected table details">
                        <section class="admin-inbox-table-modal-section">
                            <h3>Booking Details</h3>
                            <dl class="admin-inbox-table-modal-details">
                                <div>
                                    <dt><i class="bi bi-person" aria-hidden="true"></i> Guest</dt>
                                    <dd data-home-assignment-name>Guest</dd>
                                </div>
                                <div>
                                    <dt><i class="bi bi-calendar-event" aria-hidden="true"></i> Date</dt>
                                    <dd data-home-assignment-detail-date><?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?></dd>
                                </div>
                                <div>
                                    <dt><i class="bi bi-clock" aria-hidden="true"></i> Time</dt>
                                    <dd data-home-assignment-detail-time>Time TBC</dd>
                                </div>
                                <div>
                                    <dt><i class="bi bi-people" aria-hidden="true"></i> Guests</dt>
                                    <dd data-home-assignment-detail-guests>0</dd>
                                </div>
                                <div>
                                    <dt><i class="bi bi-grid" aria-hidden="true"></i> Type</dt>
                                    <dd data-home-assignment-type>Booking</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="admin-inbox-table-modal-section">
                            <div class="admin-inbox-table-modal-section-head">
                                <h3>Selected Tables</h3>
                                <span data-table-selected-count>0</span>
                            </div>
                            <div class="admin-inbox-table-modal-summary">
                                <span>Current selection</span>
                                <strong data-table-selected-label>No table assigned</strong>
                            </div>
                            <p><span data-home-assignment-available-count>0</span> selectable for this time.</p>
                        </section>
                    </aside>

                    <?php if (empty($dashboardTableAssignmentTables)): ?>
                        <div class="admin-inbox-empty subtle">
                            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                            <strong>No tables available</strong>
                            <span>Create tables first, then assign one here.</span>
                        </div>
                    <?php else: ?>
                        <div class="booking-edit-floor-panel admin-inbox-floor-panel">
                            <div class="home-floor-wrap">
                                <div class="home-floor-viewport">
                                    <div class="home-floor-stage">
                                        <div class="home-floor-canvas" role="img" aria-label="Restaurant floor plan table selection">
                                            <?php foreach ($dashboardFloorZones as $zone): ?>
                                                <div
                                                    class="home-floor-zone tone-<?php echo htmlspecialchars((string) $zone['tone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    style="left: <?php echo (int) $zone['x']; ?>px; top: <?php echo (int) $zone['y']; ?>px; width: <?php echo (int) $zone['width']; ?>px; height: <?php echo (int) $zone['height']; ?>px;"
                                                    aria-hidden="true"
                                                ></div>
                                                <button
                                                    type="button"
                                                    class="home-floor-label tone-<?php echo htmlspecialchars((string) $zone['tone'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    style="left: <?php echo (int) $zone['label_x']; ?>px; top: <?php echo (int) $zone['label_y']; ?>px;"
                                                    data-table-area-choice
                                                    data-table-area-id="<?php echo (int) $zone['area_id']; ?>"
                                                    data-table-area-label="<?php echo htmlspecialchars((string) $zone['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    aria-pressed="false"
                                                    title="Select all tables in <?php echo htmlspecialchars((string) $zone['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                                >
                                                    <i class="bi <?php echo htmlspecialchars((string) ($zone['icon'] ?? 'bi-geo-alt'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                                    <span><?php echo htmlspecialchars((string) $zone['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </button>
                                            <?php endforeach; ?>

                                            <?php foreach ($dashboardTableAssignmentTables as $tableOption): ?>
                                                <?php
                                                $optionTableId = (int) ($tableOption['table_id'] ?? 0);
                                                if ($optionTableId < 1 || $tableOption['layout_x'] === null || $tableOption['layout_y'] === null) {
                                                    continue;
                                                }

                                                $tableNumber = (string) ($tableOption['table_number'] ?? '');
                                                $tableDisplayNumber = preg_replace('/^T/i', '', $tableNumber);
                                                $tableStatus = strtolower(trim((string) ($tableOption['status'] ?? 'available')));
                                                $tableReservable = (int) ($tableOption['reservable'] ?? 1) === 1 && !in_array($tableStatus, ['inactive', 'disabled'], true);
                                                $tableCapacity = (int) ($tableOption['capacity'] ?? 0);
                                                $optionLabel = 'Table ' . $tableNumber . ' - ' . (string) ($tableOption['area_name'] ?? 'Dining Room');
                                                ?>
                                                <button
                                                    type="button"
                                                    class="home-floor-table tone-<?php echo htmlspecialchars((string) ($tableOption['tone'] ?? 'blue'), ENT_QUOTES, 'UTF-8'); ?><?php echo !$tableReservable ? ' is-unreservable' : ''; ?>"
                                                    title="Table <?php echo htmlspecialchars($tableNumber, ENT_QUOTES, 'UTF-8'); ?>"
                                                    style="left: <?php echo (int) $tableOption['layout_x']; ?>px; top: <?php echo (int) $tableOption['layout_y']; ?>px;"
                                                    data-table-choice
                                                    data-table-id="<?php echo $optionTableId; ?>"
                                                    data-table-area-id="<?php echo (int) ($tableOption['area_id'] ?? 0); ?>"
                                                    data-table-label="<?php echo htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-table-number="<?php echo htmlspecialchars($tableNumber, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-table-display-number="<?php echo htmlspecialchars($tableDisplayNumber, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-table-capacity="<?php echo $tableCapacity; ?>"
                                                    data-table-reservable="<?php echo $tableReservable ? '1' : '0'; ?>"
                                                    aria-pressed="false"
                                                >
                                                    <span class="home-floor-table-shell">
                                                        <span class="home-floor-table-card">
                                                            <span class="home-floor-card-number"><?php echo htmlspecialchars($tableDisplayNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <span class="home-floor-card-corner"><i class="bi bi-people-fill" aria-hidden="true"></i><?php echo number_format($tableCapacity); ?></span>
                                                        </span>
                                                    </span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <footer class="admin-inbox-table-modal-actions">
                    <button type="button" class="action-btn table-clear-btn" data-table-clear>
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        Clear Table
                    </button>
                    <button type="button" class="action-btn" data-home-table-assignment-close>Cancel</button>
                    <button type="submit" class="action-btn confirm" data-table-submit>
                        <i class="bi bi-check2" aria-hidden="true"></i>
                        <span data-table-submit-text>Assign Table</span>
                    </button>
                </footer>
            </form>
        </div>
    </div>

    <script type="application/json" id="dashboard-table-assignment-data"><?php echo $dashboardTableAssignmentPayloadJson; ?></script>

    <?php if ($adminHomeFlash): ?>
        <div class="admin-toast-container" aria-live="polite" aria-atomic="true">
            <div class="admin-toast" role="status" data-toast data-auto-dismiss="4000">
                <span class="admin-toast-icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
                <span class="admin-toast-message"><?php echo htmlspecialchars((string) $adminHomeFlash, ENT_QUOTES, 'UTF-8'); ?></span>
                <button type="button" class="admin-toast-close" aria-label="Dismiss notification" data-toast-close>
                    <i class="bi bi-x" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <script>
        document.querySelectorAll('.date-btn input[type="date"]').forEach((input) => {
            input.addEventListener('change', () => {
                if (input.value) {
                    const params = new URLSearchParams(window.location.search);
                    params.set('date', input.value);
                    params.set('capacity_service', '<?php echo htmlspecialchars($selectedCapacityService, ENT_QUOTES, 'UTF-8'); ?>');
                    params.set('dashboard_tab', '<?php echo htmlspecialchars($selectedDashboardTab, ENT_QUOTES, 'UTF-8'); ?>');
                    params.set('request_panel', '<?php echo htmlspecialchars($selectedRequestPanel, ENT_QUOTES, 'UTF-8'); ?>');
                    window.location.href = `admin_home.php?${params.toString()}`;
                }
            });
        });

        const bookingDetailModal = document.querySelector('[data-admin-booking-detail-modal]');
        const setBookingDetailText = (selector, value) => {
            const element = bookingDetailModal?.querySelector(selector);
            if (element) {
                element.textContent = value || '-';
            }
        };
        const closeBookingDetailModal = () => {
            if (bookingDetailModal) {
                bookingDetailModal.hidden = true;
                document.body.classList.remove('admin-modal-open');
            }
        };
        const openBookingDetailModal = (bookingData) => {
            if (!bookingDetailModal) {
                return;
            }

            const tableText = [bookingData.bookingTable, bookingData.bookingArea].filter(Boolean).join(' - ');
            const contactText = bookingData.bookingPhone || bookingData.bookingEmail || '-';
            const action = bookingDetailModal.querySelector('[data-booking-detail-action]');

            setBookingDetailText('[data-booking-detail-name]', bookingData.bookingName || 'Booking');
            setBookingDetailText('[data-booking-detail-subtitle]', bookingData.bookingId ? `Booking #${bookingData.bookingId}` : '');
            setBookingDetailText('[data-booking-detail-date]', bookingData.bookingDate || '-');
            setBookingDetailText('[data-booking-detail-time]', bookingData.bookingTime || '-');
            setBookingDetailText('[data-booking-detail-guests]', bookingData.bookingGuests ? `${bookingData.bookingGuests} guests` : '-');
            setBookingDetailText('[data-booking-detail-table]', tableText || '-');
            setBookingDetailText('[data-booking-detail-status]', bookingData.bookingStatus || '-');
            setBookingDetailText('[data-booking-detail-contact]', contactText);
            setBookingDetailText('[data-booking-detail-notes]', bookingData.bookingNotes || 'No notes recorded.');

            if (action) {
                action.href = bookingData.bookingActionUrl || 'admin_bookings.php';
            }

            bookingDetailModal.hidden = false;
            document.body.classList.add('admin-modal-open');
        };

        document.querySelectorAll('[data-admin-modal-close]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = button.closest('.admin-modal');
                if (modal === bookingDetailModal) {
                    closeBookingDetailModal();
                }
            });
        });

        if (bookingDetailModal) {
            bookingDetailModal.addEventListener('click', (event) => {
                if (event.target === bookingDetailModal) {
                    closeBookingDetailModal();
                }
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && bookingDetailModal && !bookingDetailModal.hidden) {
                closeBookingDetailModal();
            }
        });

        document.querySelectorAll('[data-admin-booking-open]').forEach((button) => {
            button.addEventListener('click', () => {
                openBookingDetailModal(button.dataset);
            });
        });

        window.addEventListener('message', (event) => {
            if (event.origin !== window.location.origin || !event.data || event.data.type !== 'dinemate:edit-booking') {
                return;
            }

            const booking = event.data.booking || {};
            const assignedTables = Array.isArray(booking.assigned_table_numbers)
                ? booking.assigned_table_numbers.filter(Boolean).join(', ')
                : (booking.assigned_table_numbers || booking.table_number || '');
            const bookingDate = booking.booking_date || '<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>';
            const startTime = booking.start_time ? String(booking.start_time).slice(0, 5) : '';
            const endTime = booking.end_time ? String(booking.end_time).slice(0, 5) : '';

            openBookingDetailModal({
                bookingId: booking.booking_id || '',
                bookingName: booking.customer_name || 'Guest',
                bookingDate,
                bookingTime: [startTime, endTime].filter(Boolean).join(' - '),
                bookingGuests: booking.number_of_guests || '',
                bookingTable: assignedTables ? `Table ${assignedTables}` : 'No table',
                bookingArea: '',
                bookingStatus: booking.status || '',
                bookingPhone: booking.customer_phone || '',
                bookingEmail: booking.customer_email || '',
                bookingNotes: booking.special_request || '',
                bookingActionUrl: `admin_bookings.php?booking_search=${encodeURIComponent(booking.booking_id || '')}&booking_date_start=${encodeURIComponent(bookingDate)}&booking_date_end=${encodeURIComponent(bookingDate)}`,
            });
        });

        const assignmentDataNode = document.getElementById('dashboard-table-assignment-data');
        let dashboardTableAssignmentData = { bookings: {} };
        if (assignmentDataNode) {
            try {
                dashboardTableAssignmentData = JSON.parse(assignmentDataNode.textContent || '{"bookings":{}}');
            } catch (error) {
                dashboardTableAssignmentData = { bookings: {} };
            }
        }

        const tableAssignmentModal = document.querySelector('[data-home-table-assignment-modal]');
        const tableAssignmentForm = tableAssignmentModal?.querySelector('[data-home-table-assignment-form]');
        const tableAssignmentFields = {
            bookingId: tableAssignmentModal?.querySelector('[data-home-assignment-booking-id]'),
            date: tableAssignmentModal?.querySelector('[data-home-assignment-date]'),
            time: tableAssignmentModal?.querySelector('[data-home-assignment-time]'),
            guests: tableAssignmentModal?.querySelector('[data-home-assignment-guests]'),
            name: tableAssignmentModal?.querySelector('[data-home-assignment-name]'),
            detailDate: tableAssignmentModal?.querySelector('[data-home-assignment-detail-date]'),
            detailTime: tableAssignmentModal?.querySelector('[data-home-assignment-detail-time]'),
            detailGuests: tableAssignmentModal?.querySelector('[data-home-assignment-detail-guests]'),
            type: tableAssignmentModal?.querySelector('[data-home-assignment-type]'),
            availableCount: tableAssignmentModal?.querySelector('[data-home-assignment-available-count]'),
            selectedInput: tableAssignmentModal?.querySelector('[data-table-selected-input]'),
            selectedFields: tableAssignmentModal?.querySelector('[data-table-selected-fields]'),
            selectedLabel: tableAssignmentModal?.querySelector('[data-table-selected-label]'),
            selectedCount: tableAssignmentModal?.querySelector('[data-table-selected-count]'),
            submitText: tableAssignmentModal?.querySelector('[data-table-submit-text]'),
        };
        const tableAssignmentChoices = tableAssignmentModal ? Array.from(tableAssignmentModal.querySelectorAll('[data-table-choice]')) : [];
        const tableAssignmentAreaChoices = tableAssignmentModal ? Array.from(tableAssignmentModal.querySelectorAll('[data-table-area-choice]')) : [];
        const tableAssignmentCloseButtons = tableAssignmentModal ? tableAssignmentModal.querySelectorAll('[data-home-table-assignment-close]') : [];
        const tableAssignmentClearButton = tableAssignmentModal?.querySelector('[data-table-clear]');
        const tableAssignmentFloorCanvas = tableAssignmentModal?.querySelector('.home-floor-canvas');
        const tableAssignmentFloorStage = tableAssignmentModal?.querySelector('.home-floor-stage');
        const tableAssignmentFloorViewport = tableAssignmentModal?.querySelector('.home-floor-viewport');
        let lastTableAssignmentTrigger = null;

        const setAssignmentText = (element, value) => {
            if (element) {
                element.textContent = value;
            }
        };

        const updateAssignmentFloorLayoutScale = () => {
            if (!tableAssignmentFloorCanvas || !tableAssignmentFloorViewport) {
                return;
            }

            const layoutWidth = 860;
            const layoutHeight = 600;
            const viewportRect = tableAssignmentFloorViewport.getBoundingClientRect();
            const availableWidth = Math.max(0, viewportRect.width - 2);
            const availableHeight = Math.max(0, viewportRect.height - 2);
            const rawScale = Math.min(1, availableWidth / layoutWidth, availableHeight / layoutHeight);
            const scale = Number.isFinite(rawScale) && rawScale > 0 ? rawScale : 1;

            tableAssignmentFloorCanvas.style.width = `${layoutWidth}px`;
            tableAssignmentFloorCanvas.style.height = `${layoutHeight}px`;
            tableAssignmentFloorCanvas.style.transform = scale < 1 ? `scale(${scale})` : '';

            if (tableAssignmentFloorStage) {
                tableAssignmentFloorStage.style.width = `${Math.ceil(layoutWidth * scale)}px`;
                tableAssignmentFloorStage.style.height = `${Math.ceil(layoutHeight * scale)}px`;
            }
        };

        const getSelectedAssignmentChoices = () => tableAssignmentChoices.filter((choice) => choice.classList.contains('is-selected') && !choice.disabled);
        const getSelectableAssignmentAreaTables = (areaId) => tableAssignmentChoices.filter((choice) => choice.dataset.tableAreaId === areaId && !choice.disabled);
        const getAssignmentAreaLabel = (areaButton) => (areaButton.dataset.tableAreaLabel || areaButton.textContent || '').trim();

        const getAssignmentSummaryLabels = (selectedChoices) => {
            const selectedSet = new Set(selectedChoices);
            const coveredChoices = new Set();
            const fullAreaLabels = [];
            const summaryLabels = [];

            tableAssignmentAreaChoices.forEach((areaButton) => {
                const areaTables = getSelectableAssignmentAreaTables(areaButton.dataset.tableAreaId || '');
                if (areaTables.length === 0 || !areaTables.every((choice) => selectedSet.has(choice))) {
                    return;
                }

                areaTables.forEach((choice) => coveredChoices.add(choice));
                const areaLabel = getAssignmentAreaLabel(areaButton);
                if (areaLabel !== '') {
                    fullAreaLabels.push(areaLabel);
                }
            });

            if (fullAreaLabels.length > 0) {
                const areaText = fullAreaLabels.length === 1
                    ? fullAreaLabels[0]
                    : fullAreaLabels.length === 2
                        ? `${fullAreaLabels[0]} and ${fullAreaLabels[1]}`
                        : `${fullAreaLabels.slice(0, -1).join(', ')} and ${fullAreaLabels[fullAreaLabels.length - 1]}`;
                summaryLabels.push(`All ${areaText}`);
            }

            selectedChoices.forEach((choice) => {
                if (!coveredChoices.has(choice) && choice.dataset.tableLabel) {
                    summaryLabels.push(choice.dataset.tableLabel);
                }
            });

            return summaryLabels;
        };

        const updateAssignmentAreaSelectionState = () => {
            tableAssignmentAreaChoices.forEach((areaButton) => {
                const areaTables = getSelectableAssignmentAreaTables(areaButton.dataset.tableAreaId || '');
                const selectedAreaTables = areaTables.filter((choice) => choice.classList.contains('is-selected'));
                const allSelected = areaTables.length > 0 && selectedAreaTables.length === areaTables.length;
                const someSelected = selectedAreaTables.length > 0 && !allSelected;

                areaButton.disabled = areaTables.length === 0;
                areaButton.classList.toggle('is-selected', allSelected);
                areaButton.classList.toggle('is-partial', someSelected);
                areaButton.setAttribute('aria-pressed', allSelected ? 'true' : (someSelected ? 'mixed' : 'false'));
                areaButton.title = areaTables.length === 0
                    ? 'No selectable tables in this area'
                    : allSelected
                        ? 'Clear tables in this area'
                        : 'Select all tables in this area';
            });
        };

        const updateAssignmentSelectedTables = () => {
            const selectedChoices = getSelectedAssignmentChoices();
            const selectedIds = selectedChoices.map((choice) => choice.dataset.tableId || '').filter(Boolean);
            const selectedLabels = selectedChoices.map((choice) => choice.dataset.tableLabel || '').filter(Boolean);
            const summaryLabels = getAssignmentSummaryLabels(selectedChoices);

            if (tableAssignmentFields.selectedInput) {
                tableAssignmentFields.selectedInput.value = selectedIds[0] || '';
            }

            if (tableAssignmentFields.selectedFields) {
                tableAssignmentFields.selectedFields.replaceChildren();
                selectedIds.forEach((tableId) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'table_ids[]';
                    input.value = tableId;
                    tableAssignmentFields.selectedFields.appendChild(input);
                });
            }

            setAssignmentText(tableAssignmentFields.selectedLabel, summaryLabels.length ? summaryLabels.join(', ') : 'No table assigned');
            setAssignmentText(tableAssignmentFields.selectedCount, selectedLabels.length.toString());

            if (tableAssignmentFields.submitText) {
                tableAssignmentFields.submitText.textContent = selectedLabels.length > 1
                    ? `Assign ${selectedLabels.length} Tables`
                    : selectedLabels.length === 1
                        ? 'Assign ' + selectedLabels[0].replace('Table ', 'T')
                        : 'Assign Table';
            }

            updateAssignmentAreaSelectionState();
        };

        const renderAssignmentTableCard = (button, conflict = null) => {
            const card = button.querySelector('.home-floor-table-card');
            if (!card) {
                return;
            }

            if (conflict) {
                card.innerHTML = `
                    <span class="home-floor-card-time"></span>
                    <span class="home-floor-card-main"></span>
                    ${conflict.notes ? '<span class="home-floor-card-note"></span>' : ''}
                    <span class="home-floor-card-corner"><i class="bi bi-people-fill" aria-hidden="true"></i>${Number(conflict.guests || 0).toLocaleString()}</span>
                `;
                card.querySelector('.home-floor-card-time').textContent = conflict.time || 'Time TBC';
                card.querySelector('.home-floor-card-main').textContent = conflict.name || 'Guest';
                const note = card.querySelector('.home-floor-card-note');
                if (note) {
                    note.textContent = conflict.notes || '';
                }
                return;
            }

            card.innerHTML = `
                <span class="home-floor-card-number"></span>
                <span class="home-floor-card-corner"><i class="bi bi-people-fill" aria-hidden="true"></i>${Number(button.dataset.tableCapacity || 0).toLocaleString()}</span>
            `;
            card.querySelector('.home-floor-card-number').textContent = button.dataset.tableDisplayNumber || button.dataset.tableNumber || '';
        };

        const prepareAssignmentChoices = (booking) => {
            const selectedIds = new Set((booking.selected_table_ids || []).map((value) => String(value)));
            const conflicts = booking.conflicts || {};
            let selectableCount = 0;

            tableAssignmentChoices.forEach((button) => {
                const tableId = button.dataset.tableId || '';
                const isSelected = selectedIds.has(tableId);
                const conflict = conflicts[tableId] || null;
                const isReservable = button.dataset.tableReservable === '1';
                const isBusy = Boolean(conflict) && !isSelected;
                const isSelectable = !isBusy && (isReservable || isSelected);

                button.disabled = !isSelectable;
                button.classList.toggle('is-selected', isSelected);
                button.classList.toggle('is-busy', isBusy);
                button.classList.toggle('is-occupied', isBusy);
                button.classList.toggle('is-unreservable', !isReservable);
                button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                button.title = isBusy
                    ? `${button.dataset.tableLabel || 'Table'}: ${conflict.name || 'Guest'} at ${conflict.time || 'Time TBC'}`
                    : button.dataset.tableLabel || 'Table';
                renderAssignmentTableCard(button, isBusy ? conflict : null);

                if (isSelectable) {
                    selectableCount++;
                }
            });

            setAssignmentText(tableAssignmentFields.availableCount, `${selectableCount.toLocaleString()} ${selectableCount === 1 ? 'table is' : 'tables are'}`);
            updateAssignmentSelectedTables();
        };

        const openHomeTableAssignmentModal = (bookingId, trigger = null) => {
            if (!tableAssignmentModal || !tableAssignmentForm) {
                return false;
            }

            const booking = dashboardTableAssignmentData.bookings?.[String(bookingId)];
            if (!booking) {
                return false;
            }

            lastTableAssignmentTrigger = trigger;
            if (tableAssignmentFields.bookingId) {
                tableAssignmentFields.bookingId.value = String(booking.id || bookingId);
            }

            setAssignmentText(tableAssignmentFields.date, booking.date || '<?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?>');
            setAssignmentText(tableAssignmentFields.time, booking.time || 'Time TBC');
            setAssignmentText(tableAssignmentFields.guests, Number(booking.guests || 0).toLocaleString());
            setAssignmentText(tableAssignmentFields.name, booking.name || 'Guest');
            setAssignmentText(tableAssignmentFields.detailDate, booking.date || '<?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?>');
            setAssignmentText(tableAssignmentFields.detailTime, booking.time_range || booking.time || 'Time TBC');
            setAssignmentText(tableAssignmentFields.detailGuests, Number(booking.guests || 0).toLocaleString());
            setAssignmentText(tableAssignmentFields.type, booking.type || 'Booking');

            prepareAssignmentChoices(booking);
            tableAssignmentModal.hidden = false;
            document.body.classList.add('admin-inbox-modal-open');
            window.requestAnimationFrame(updateAssignmentFloorLayoutScale);
            const selected = tableAssignmentModal.querySelector('[data-table-choice].is-selected:not(:disabled)');
            window.setTimeout(() => (selected || tableAssignmentModal.querySelector('[data-home-table-assignment-close]'))?.focus(), 0);
            return true;
        };

        const closeHomeTableAssignmentModal = () => {
            if (!tableAssignmentModal) {
                return;
            }

            tableAssignmentModal.hidden = true;
            document.body.classList.remove('admin-inbox-modal-open');
            lastTableAssignmentTrigger?.focus?.();
        };

        tableAssignmentChoices.forEach((button) => {
            button.addEventListener('click', () => {
                if (button.disabled) {
                    return;
                }
                const isSelected = button.classList.toggle('is-selected');
                button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                updateAssignmentSelectedTables();
            });
        });

        tableAssignmentAreaChoices.forEach((areaButton) => {
            areaButton.addEventListener('click', () => {
                const areaTables = getSelectableAssignmentAreaTables(areaButton.dataset.tableAreaId || '');
                if (areaTables.length === 0) {
                    return;
                }

                const shouldSelect = !areaTables.every((choice) => choice.classList.contains('is-selected'));
                areaTables.forEach((choice) => {
                    choice.classList.toggle('is-selected', shouldSelect);
                    choice.setAttribute('aria-pressed', shouldSelect ? 'true' : 'false');
                });
                updateAssignmentSelectedTables();
            });
        });

        tableAssignmentClearButton?.addEventListener('click', () => {
            tableAssignmentChoices.forEach((choice) => {
                choice.classList.remove('is-selected');
                choice.setAttribute('aria-pressed', 'false');
            });
            updateAssignmentSelectedTables();
            if (tableAssignmentForm?.requestSubmit) {
                tableAssignmentForm.requestSubmit();
            } else {
                tableAssignmentForm?.submit();
            }
        });

        tableAssignmentCloseButtons.forEach((button) => {
            button.addEventListener('click', closeHomeTableAssignmentModal);
        });

        tableAssignmentModal?.addEventListener('click', (event) => {
            if (event.target === tableAssignmentModal) {
                closeHomeTableAssignmentModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && tableAssignmentModal && !tableAssignmentModal.hidden) {
                closeHomeTableAssignmentModal();
            }
        });

        window.addEventListener('resize', updateAssignmentFloorLayoutScale);

        document.querySelectorAll('[data-home-table-assign-open]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!openHomeTableAssignmentModal(button.dataset.bookingId, button)) {
                    window.location.href = `admin_bookings.php?status_view=needs_action&booking_search=${encodeURIComponent(button.dataset.bookingId || '')}`;
                }
            });
        });

        document.querySelectorAll('[data-toast]').forEach((toast) => {
            const dismiss = () => {
                toast.classList.add('is-leaving');
                window.setTimeout(() => toast.remove(), 220);
            };

            toast.querySelector('[data-toast-close]')?.addEventListener('click', dismiss);

            const delay = parseInt(toast.dataset.autoDismiss || '0', 10);
            if (delay > 0) {
                window.setTimeout(dismiss, delay);
            }

            requestAnimationFrame(() => toast.classList.add('is-visible'));
        });

        const resizeDashboardFloor = () => {
            const canvasWidth = 860;
            const canvasHeight = 600;

            document.querySelectorAll('[data-dashboard-floor-viewport]').forEach((viewport) => {
                const stage = viewport.querySelector('.dashboard-floor-stage');
                if (!stage) {
                    return;
                }

                const widthScale = viewport.clientWidth / canvasWidth;
                const heightScale = viewport.clientHeight / canvasHeight;
                const scale = Math.max(0.35, Math.min(widthScale, heightScale, 1));
                stage.style.setProperty('--dashboard-floor-scale', scale.toFixed(4));
            });
        };

        window.addEventListener('resize', resizeDashboardFloor);
        window.addEventListener('load', resizeDashboardFloor);
        resizeDashboardFloor();

        const dashboardFloorButtons = Array.from(document.querySelectorAll('[data-dashboard-floor-table]'));
        const dashboardFloorDetail = {
            empty: document.querySelector('[data-dashboard-floor-detail-empty]'),
            card: document.querySelector('[data-dashboard-floor-detail-card]'),
            tabs: document.querySelector('[data-dashboard-detail-tabs]'),
            table: document.querySelector('[data-dashboard-detail-table]'),
            time: document.querySelector('[data-dashboard-detail-time]'),
            date: document.querySelector('[data-dashboard-detail-date]'),
            guests: document.querySelector('[data-dashboard-detail-guests]'),
            name: document.querySelector('[data-dashboard-detail-name]'),
            contacts: document.querySelector('[data-dashboard-detail-contacts]'),
            notesWrap: document.querySelector('[data-dashboard-detail-notes-wrap]'),
            notes: document.querySelector('[data-dashboard-detail-notes]'),
            view: document.querySelector('[data-dashboard-detail-view]'),
            edit: document.querySelector('[data-dashboard-detail-edit]'),
            close: document.querySelector('[data-dashboard-floor-detail-close]'),
        };

        const getDashboardFloorPayload = (button) => {
            try {
                return JSON.parse(button.dataset.dashboardFloorTable || '{}');
            } catch (error) {
                return {};
            }
        };

        const setDetailText = (element, value) => {
            if (element) {
                element.textContent = value;
            }
        };

        const renderDashboardFloorContacts = (phone, email, fallback = '') => {
            if (!dashboardFloorDetail.contacts) {
                return;
            }

            dashboardFloorDetail.contacts.innerHTML = '';
            const rows = [];

            if (phone) {
                rows.push({ icon: 'bi-telephone', text: phone });
            }

            if (email) {
                rows.push({ icon: 'bi-envelope', text: email });
            }

            if (rows.length === 0 && fallback) {
                rows.push({ icon: 'bi-info-circle', text: fallback });
            }

            rows.forEach((row) => {
                const item = document.createElement('span');
                item.className = 'dashboard-floor-detail-contact';

                const icon = document.createElement('i');
                icon.className = `bi ${row.icon}`;
                icon.setAttribute('aria-hidden', 'true');

                const text = document.createElement('span');
                text.textContent = row.text;

                item.append(icon, text);
                dashboardFloorDetail.contacts.appendChild(item);
            });
        };

        const setDetailLink = (element, value, label) => {
            if (!element) {
                return;
            }

            element.href = value;
            element.textContent = label;
        };

        const renderDashboardFloorDetail = (button, bookingIndex = 0) => {
            if (!dashboardFloorDetail.card || !dashboardFloorDetail.empty) {
                return;
            }

            const payload = getDashboardFloorPayload(button);
            const bookings = Array.isArray(payload.bookings) ? payload.bookings : [];
            const activeIndex = bookings[bookingIndex] ? bookingIndex : 0;
            const booking = bookings[activeIndex] || null;
            const bookingsUrl = 'admin_bookings.php?booking_date_start=<?php echo urlencode($selectedDate); ?>&booking_date_end=<?php echo urlencode($selectedDate); ?>';

            dashboardFloorButtons.forEach((tableButton) => {
                tableButton.classList.toggle('is-selected', tableButton === button);
            });

            dashboardFloorDetail.empty.hidden = true;
            dashboardFloorDetail.card.hidden = false;
            setDetailText(dashboardFloorDetail.table, `Table ${payload.number || ''}`.trim());

            if (dashboardFloorDetail.tabs) {
                dashboardFloorDetail.tabs.innerHTML = '';
                dashboardFloorDetail.tabs.hidden = bookings.length < 2;

                if (bookings.length > 1) {
                    bookings.forEach((item, index) => {
                        const tab = document.createElement('button');
                        tab.type = 'button';
                        tab.className = `dashboard-floor-booking-tab${index === activeIndex ? ' is-active' : ''}`;
                        tab.setAttribute('aria-pressed', index === activeIndex ? 'true' : 'false');

                        const label = document.createElement('span');
                        label.textContent = item.name || 'Guest';
                        tab.appendChild(label);
                        tab.addEventListener('click', () => renderDashboardFloorDetail(button, index));
                        dashboardFloorDetail.tabs.appendChild(tab);
                    });
                }
            }

            if (!booking) {
                setDetailText(dashboardFloorDetail.time, 'Available');
                setDetailText(dashboardFloorDetail.date, '<?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?>');
                setDetailText(dashboardFloorDetail.guests, `${Number(payload.capacity || 0)} seats`);
                setDetailText(dashboardFloorDetail.name, `Table ${payload.number || ''}`.trim());
                renderDashboardFloorContacts('', '', payload.area || 'Dining room');
                if (dashboardFloorDetail.notesWrap) {
                    dashboardFloorDetail.notesWrap.hidden = true;
                }
                setDetailText(dashboardFloorDetail.notes, '');
                setDetailLink(dashboardFloorDetail.view, 'tables-management.php', 'Manage Table');
                setDetailLink(dashboardFloorDetail.edit, bookingsUrl, 'Add Booking');
                return;
            }

            if (dashboardFloorDetail.notesWrap) {
                dashboardFloorDetail.notesWrap.hidden = false;
            }
            setDetailText(dashboardFloorDetail.time, booking.time || 'Time TBC');
            setDetailText(dashboardFloorDetail.date, booking.date || '<?php echo htmlspecialchars($dashboardDateLabel, ENT_QUOTES, 'UTF-8'); ?>');
            setDetailText(dashboardFloorDetail.guests, `${Number(booking.guests || 0)} Guests`);
            setDetailText(dashboardFloorDetail.name, booking.name || 'Guest');
            renderDashboardFloorContacts(booking.phone || '', booking.email || '', 'No contact details');
            setDetailText(dashboardFloorDetail.notes, booking.notes || '-');
            setDetailLink(dashboardFloorDetail.view, booking.url || bookingsUrl, 'View Booking');
            setDetailLink(dashboardFloorDetail.edit, booking.url || bookingsUrl, 'Edit Booking');
        };

        dashboardFloorButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const payload = getDashboardFloorPayload(button);
                const bookings = Array.isArray(payload.bookings) ? payload.bookings : [];
                const firstBookingId = bookings[0]?.id || 0;

                if (firstBookingId && openHomeTableAssignmentModal(firstBookingId, button)) {
                    return;
                }

                renderDashboardFloorDetail(button);
            });
        });

        if (dashboardFloorDetail.close) {
            dashboardFloorDetail.close.addEventListener('click', () => {
                dashboardFloorButtons.forEach((button) => button.classList.remove('is-selected'));
                if (dashboardFloorDetail.card) {
                    dashboardFloorDetail.card.hidden = true;
                }
                if (dashboardFloorDetail.empty) {
                    dashboardFloorDetail.empty.hidden = false;
                }
            });
        }

        document.querySelectorAll('[data-confirm-request-id]').forEach((confirmButton) => {
            confirmButton.addEventListener('click', async () => {
                const bookingId = Number(confirmButton.dataset.confirmRequestId || 0);
                if (bookingId < 1 || confirmButton.disabled) {
                    return;
                }

                confirmButton.disabled = true;

                try {
                    const response = await fetch('../actions/confirm-pending-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ booking_id: bookingId }),
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not confirm request');
                    }

                    window.location.reload();
                } catch (error) {
                    confirmButton.disabled = false;
                    alert(error.message);
                }
            });
        });

        document.querySelectorAll('[data-decline-request-id]').forEach((declineButton) => {
            declineButton.addEventListener('click', async () => {
                const bookingId = Number(declineButton.dataset.declineRequestId || 0);
                if (bookingId < 1 || declineButton.disabled) {
                    return;
                }

                if (!window.confirm('Decline this booking request?')) {
                    return;
                }

                declineButton.disabled = true;

                try {
                    const response = await fetch('../actions/cancel-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ booking_id: bookingId }),
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not decline request');
                    }

                    window.location.reload();
                } catch (error) {
                    declineButton.disabled = false;
                    alert(error.message);
                }
            });
        });
    </script>
</body>
</html>
