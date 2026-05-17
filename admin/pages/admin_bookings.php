<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);

$adminNewSidebarActive = 'bookings';
$todayDate = date('Y-m-d');
$selectedBookingStatusView = strtolower(trim((string) ($_GET['status_view'] ?? 'today')));
if (!in_array($selectedBookingStatusView, ['today', 'upcoming', 'past', 'cancelled'], true)) {
    $selectedBookingStatusView = 'today';
}
$bookingStatusCounts = [
    'all' => 0,
    'upcoming' => 0,
    'today' => 0,
    'needs_action' => 0,
    'past' => 0,
    'cancelled' => 0,
];
try {
    $bookingStatusStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS all_count,
            COALESCE(SUM(CASE WHEN booking_date > ? AND status <> 'cancelled' THEN 1 ELSE 0 END), 0) AS upcoming_count,
            COALESCE(SUM(CASE WHEN booking_date = ? AND status <> 'cancelled' THEN 1 ELSE 0 END), 0) AS today_count,
            COALESCE(SUM(CASE WHEN status = 'pending' OR (
                status <> 'cancelled'
                AND table_id IS NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM booking_table_assignments bta_count
                    WHERE bta_count.booking_id = bookings.booking_id
                )
            ) THEN 1 ELSE 0 END), 0) AS needs_action_count,
            COALESCE(SUM(CASE WHEN booking_date < ? AND status <> 'cancelled' THEN 1 ELSE 0 END), 0) AS past_count,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count
        FROM bookings
    ");
    $bookingStatusStmt->execute([$todayDate, $todayDate, $todayDate]);
    $bookingStatusRow = $bookingStatusStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $bookingStatusCounts = [
        'all' => (int) ($bookingStatusRow['all_count'] ?? 0),
        'upcoming' => (int) ($bookingStatusRow['upcoming_count'] ?? 0),
        'today' => (int) ($bookingStatusRow['today_count'] ?? 0),
        'needs_action' => (int) ($bookingStatusRow['needs_action_count'] ?? 0),
        'past' => (int) ($bookingStatusRow['past_count'] ?? 0),
        'cancelled' => (int) ($bookingStatusRow['cancelled_count'] ?? 0),
    ];
} catch (Throwable $pendingCountError) {
    $bookingStatusCounts = array_fill_keys(array_keys($bookingStatusCounts), 0);
}
$pendingBookingsCount = $bookingStatusCounts['needs_action'];
$bookingStatusTabs = [
    'today' => 'Today',
    'upcoming' => 'Upcoming',
    'past' => 'Past',
    'cancelled' => 'Cancelled',
];

$readArrayParam = static function (string $name): array {
    $value = $_GET[$name] ?? [];
    if (!is_array($value)) {
        $value = [$value];
    }

    return array_values(array_filter(array_map(static function ($item): string {
        return strtolower(trim((string) $item));
    }, $value), static fn (string $item): bool => $item !== ''));
};

$bookingSearch = trim((string) ($_GET['booking_search'] ?? ''));
$bookingSearch = substr($bookingSearch, 0, 80);
$normalizeBookingDate = static function ($value): string {
    $dateValue = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) || strtotime($dateValue) === false) {
        return '';
    }

    return $dateValue;
};
$formatBookingDateLabel = static function (string $dateValue): string {
    return date('d/m/Y', strtotime($dateValue));
};
$selectedBookingDateStart = $normalizeBookingDate($_GET['booking_date_start'] ?? '');
$selectedBookingDateEnd = $normalizeBookingDate($_GET['booking_date_end'] ?? '');
$legacyBookingDate = $normalizeBookingDate($_GET['booking_date'] ?? '');
if ($selectedBookingDateStart === '' && $selectedBookingDateEnd === '' && $legacyBookingDate !== '') {
    $selectedBookingDateStart = $legacyBookingDate;
    $selectedBookingDateEnd = $legacyBookingDate;
}
if ($selectedBookingDateStart === '' && $selectedBookingDateEnd !== '') {
    $selectedBookingDateStart = $selectedBookingDateEnd;
}
if ($selectedBookingDateEnd === '' && $selectedBookingDateStart !== '') {
    $selectedBookingDateEnd = $selectedBookingDateStart;
}
if ($selectedBookingDateStart !== '' && $selectedBookingDateEnd !== '' && strtotime($selectedBookingDateStart) > strtotime($selectedBookingDateEnd)) {
    [$selectedBookingDateStart, $selectedBookingDateEnd] = [$selectedBookingDateEnd, $selectedBookingDateStart];
}
$hasActiveBookingDateFilter = $selectedBookingDateStart !== '' && $selectedBookingDateEnd !== '';
$calendarBookingDateStart = $hasActiveBookingDateFilter ? $selectedBookingDateStart : $todayDate;
$calendarBookingDateEnd = $hasActiveBookingDateFilter ? $selectedBookingDateEnd : $todayDate;
$bookingDateControlLabel = 'Any Date';
if ($hasActiveBookingDateFilter) {
    $bookingDateControlLabel = $calendarBookingDateStart === $calendarBookingDateEnd
        ? $formatBookingDateLabel($calendarBookingDateStart)
        : $formatBookingDateLabel($calendarBookingDateStart) . ' - ' . $formatBookingDateLabel($calendarBookingDateEnd);
}
$bookingSortOptions = [
    'time_earliest' => 'Time Earliest',
    'time_latest' => 'Time Latest',
    'created_newest' => 'Created Newest',
    'created_oldest' => 'Created Oldest',
    'party_large' => 'Large to Small',
    'party_small' => 'Small to Large',
];
$bookingSortSummaryLabels = [
    'time_earliest' => 'Time Earliest',
    'time_latest' => 'Time Latest',
    'created_newest' => 'Created Newest',
    'created_oldest' => 'Created Oldest',
    'party_large' => 'Large to Small',
    'party_small' => 'Small to Large',
];
$allowedBookingSorts = array_keys($bookingSortOptions);
$selectedBookingSort = strtolower(trim((string) ($_GET['sort'] ?? 'time_earliest')));
if ($selectedBookingSort === 'earliest') {
    $selectedBookingSort = 'time_earliest';
} elseif ($selectedBookingSort === 'latest') {
    $selectedBookingSort = 'time_latest';
}
if (!in_array($selectedBookingSort, $allowedBookingSorts, true)) {
    $selectedBookingSort = 'time_earliest';
}

$bookingsPerPage = (int) ($_GET['per_page'] ?? 100);
if ($bookingsPerPage < 1 || $bookingsPerPage > 100) {
    $bookingsPerPage = 7;
}

$allowedGuestCountFilters = ['1-7', '8-20', '20-40', '40-60', '60-100', '100plus'];
$allowedSourceFilters = ['website', 'staff'];
$allowedAssignmentFilters = ['assigned', 'no_table', 'waitlist'];

$selectedGuestCounts = array_values(array_intersect($readArrayParam('guest_count'), $allowedGuestCountFilters));
$selectedSources = array_values(array_intersect($readArrayParam('source'), $allowedSourceFilters));
$selectedAssignments = array_values(array_intersect($readArrayParam('assignment'), $allowedAssignmentFilters));

$activeMoreFilterCount = count($selectedGuestCounts)
    + count($selectedSources)
    + count($selectedAssignments);
$hasActiveMoreFilters = $activeMoreFilterCount > 0;

$buildBookingUrl = static function (array $overrides = []) use (
    $selectedBookingStatusView,
    $bookingsPerPage,
    $bookingSearch,
    $selectedBookingDateStart,
    $selectedBookingDateEnd,
    $hasActiveBookingDateFilter,
    $selectedBookingSort,
    $selectedGuestCounts,
    $selectedSources,
    $selectedAssignments
): string {
    $params = [
        'status_view' => $selectedBookingStatusView,
        'per_page' => $bookingsPerPage,
        'sort' => $selectedBookingSort,
    ];

    if ($bookingSearch !== '') {
        $params['booking_search'] = $bookingSearch;
    }
    if ($hasActiveBookingDateFilter) {
        $params['booking_date_start'] = $selectedBookingDateStart;
        if ($selectedBookingDateEnd !== $selectedBookingDateStart) {
            $params['booking_date_end'] = $selectedBookingDateEnd;
        }
    }
    if (!empty($selectedGuestCounts)) {
        $params['guest_count'] = $selectedGuestCounts;
    }
    if (!empty($selectedSources)) {
        $params['source'] = $selectedSources;
    }
    if (!empty($selectedAssignments)) {
        $params['assignment'] = $selectedAssignments;
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '' || $value === []) {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }

    return 'admin_bookings.php?' . http_build_query($params);
};

$activeFilterChips = [];
$guestCountFilterLabels = [
    '1-7' => '1-7 guests',
    '8-20' => '8-20 guests',
    '20-40' => '20-40 guests',
    '40-60' => '40-60 guests',
    '60-100' => '60-100 guests',
    '100plus' => '100+ guests',
];
$sourceFilterLabels = [
    'website' => 'Website',
    'staff' => 'Staff',
];
$assignmentFilterLabels = [
    'assigned' => 'Assigned',
    'no_table' => 'No Table',
    'waitlist' => 'Waitlist',
];

if ($bookingSearch !== '') {
    $activeFilterChips[] = [
        'label' => 'Search: ' . $bookingSearch,
        'url' => $buildBookingUrl(['booking_search' => null, 'page' => 1]),
    ];
}

if ($hasActiveBookingDateFilter) {
    $activeFilterChips[] = [
        'label' => 'Date: ' . ($selectedBookingDateStart === $selectedBookingDateEnd
            ? $formatBookingDateLabel($selectedBookingDateStart)
            : $formatBookingDateLabel($selectedBookingDateStart) . ' - ' . $formatBookingDateLabel($selectedBookingDateEnd)),
        'url' => $buildBookingUrl([
            'booking_date' => null,
            'booking_date_start' => null,
            'booking_date_end' => null,
            'page' => 1,
        ]),
    ];
}

foreach ($selectedGuestCounts as $guestCountFilter) {
    $activeFilterChips[] = [
        'label' => $guestCountFilterLabels[$guestCountFilter] ?? $guestCountFilter,
        'url' => $buildBookingUrl(['guest_count' => array_values(array_diff($selectedGuestCounts, [$guestCountFilter])), 'page' => 1]),
    ];
}

foreach ($selectedSources as $sourceFilter) {
    $activeFilterChips[] = [
        'label' => $sourceFilterLabels[$sourceFilter] ?? ucfirst($sourceFilter),
        'url' => $buildBookingUrl(['source' => array_values(array_diff($selectedSources, [$sourceFilter])), 'page' => 1]),
    ];
}

foreach ($selectedAssignments as $assignmentFilter) {
    $activeFilterChips[] = [
        'label' => $assignmentFilterLabels[$assignmentFilter] ?? ucfirst(str_replace('_', ' ', $assignmentFilter)),
        'url' => $buildBookingUrl(['assignment' => array_values(array_diff($selectedAssignments, [$assignmentFilter])), 'page' => 1]),
    ];
}

$clearAllFiltersUrl = $buildBookingUrl([
    'booking_search' => null,
    'booking_date' => null,
    'booking_date_start' => null,
    'booking_date_end' => null,
    'guest_count' => null,
    'source' => null,
    'assignment' => null,
    'page' => 1,
]);

$renderAdminBookingHiddenInput = static function (string $name, string $value): string {
    if ($value === '') {
        return '';
    }

    return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">' . PHP_EOL;
};

$bookingListWhereClauses = [];
$bookingListParams = [];
if ($selectedBookingStatusView === 'upcoming') {
    $bookingListWhereClauses[] = "b.booking_date > ? AND b.status <> 'cancelled'";
    $bookingListParams[] = $todayDate;
} elseif ($selectedBookingStatusView === 'today') {
    $bookingListWhereClauses[] = "b.booking_date = ? AND b.status <> 'cancelled'";
    $bookingListParams[] = $todayDate;
} elseif ($selectedBookingStatusView === 'past') {
    $bookingListWhereClauses[] = "b.booking_date < ? AND b.status <> 'cancelled'";
    $bookingListParams[] = $todayDate;
} elseif ($selectedBookingStatusView === 'cancelled') {
    $bookingListWhereClauses[] = "b.status = 'cancelled'";
}

if ($bookingSearch !== '') {
    $bookingSearchLike = '%' . strtolower($bookingSearch) . '%';
    $bookingListWhereClauses[] = "(
        LOWER(COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest')) LIKE ?
        OR LOWER(COALESCE(NULLIF(b.customer_phone, ''), u.phone, '')) LIKE ?
        OR LOWER(COALESCE(NULLIF(b.customer_email, ''), u.email, '')) LIKE ?
        OR CAST(b.booking_id AS CHAR) LIKE ?
    )";
    array_push($bookingListParams, $bookingSearchLike, $bookingSearchLike, $bookingSearchLike, '%' . $bookingSearch . '%');
}

if ($hasActiveBookingDateFilter) {
    if ($selectedBookingDateStart === $selectedBookingDateEnd) {
        $bookingListWhereClauses[] = 'b.booking_date = ?';
        $bookingListParams[] = $selectedBookingDateStart;
    } else {
        $bookingListWhereClauses[] = 'b.booking_date BETWEEN ? AND ?';
        array_push($bookingListParams, $selectedBookingDateStart, $selectedBookingDateEnd);
    }
}

if (!empty($selectedGuestCounts)) {
    $guestConditions = [];
    foreach ($selectedGuestCounts as $guestCountFilter) {
        if ($guestCountFilter === '1-7') {
            $guestConditions[] = 'b.number_of_guests BETWEEN ? AND ?';
            array_push($bookingListParams, 1, 7);
        } elseif ($guestCountFilter === '8-20') {
            $guestConditions[] = 'b.number_of_guests BETWEEN ? AND ?';
            array_push($bookingListParams, 8, 20);
        } elseif ($guestCountFilter === '20-40') {
            $guestConditions[] = 'b.number_of_guests BETWEEN ? AND ?';
            array_push($bookingListParams, 20, 40);
        } elseif ($guestCountFilter === '40-60') {
            $guestConditions[] = 'b.number_of_guests BETWEEN ? AND ?';
            array_push($bookingListParams, 40, 60);
        } elseif ($guestCountFilter === '60-100') {
            $guestConditions[] = 'b.number_of_guests BETWEEN ? AND ?';
            array_push($bookingListParams, 60, 100);
        } elseif ($guestCountFilter === '100plus') {
            $guestConditions[] = 'b.number_of_guests >= ?';
            $bookingListParams[] = 100;
        }
    }
    if (!empty($guestConditions)) {
        $bookingListWhereClauses[] = '(' . implode(' OR ', $guestConditions) . ')';
    }
}

if (!empty($selectedSources)) {
    $sourceConditions = [];
    foreach ($selectedSources as $sourceFilter) {
        if ($sourceFilter === 'website') {
            $sourceConditions[] = "b.booking_source IN ('customer_account', 'guest_web')";
        } elseif ($sourceFilter === 'staff') {
            $sourceConditions[] = "b.booking_source = 'admin_manual'";
        }
    }
    if (!empty($sourceConditions)) {
        $bookingListWhereClauses[] = '(' . implode(' OR ', $sourceConditions) . ')';
    }
}

if (!empty($selectedAssignments)) {
    $assignmentConditions = [];
    foreach ($selectedAssignments as $assignmentFilter) {
        if ($assignmentFilter === 'assigned') {
            $assignmentConditions[] = "(b.table_id IS NOT NULL OR EXISTS (
                SELECT 1 FROM booking_table_assignments bta_assigned
                WHERE bta_assigned.booking_id = b.booking_id
            ))";
        } elseif ($assignmentFilter === 'no_table') {
            $assignmentConditions[] = "(b.table_id IS NULL AND NOT EXISTS (
                SELECT 1 FROM booking_table_assignments bta_no_table
                WHERE bta_no_table.booking_id = b.booking_id
            ))";
        } elseif ($assignmentFilter === 'waitlist') {
            $assignmentConditions[] = "(b.status = 'pending' AND b.table_id IS NULL AND NOT EXISTS (
                SELECT 1 FROM booking_table_assignments bta_waitlist
                WHERE bta_waitlist.booking_id = b.booking_id
            ))";
        }
    }
    if (!empty($assignmentConditions)) {
        $bookingListWhereClauses[] = '(' . implode(' OR ', $assignmentConditions) . ')';
    }
}

$bookingListWhere = !empty($bookingListWhereClauses) ? implode(' AND ', $bookingListWhereClauses) : '1 = 1';
$bookingListOrderSql = match ($selectedBookingSort) {
    'time_latest' => 'b.booking_date DESC, b.start_time DESC, b.booking_id DESC',
    'created_newest' => 'b.created_at DESC, b.booking_id DESC',
    'created_oldest' => 'b.created_at ASC, b.booking_id ASC',
    'party_large' => 'b.number_of_guests DESC, b.booking_date ASC, b.start_time ASC',
    'party_small' => 'b.number_of_guests ASC, b.booking_date ASC, b.start_time ASC',
    default => 'b.booking_date ASC, b.start_time ASC, b.booking_id ASC',
};

$bookingListFromSql = "
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
    LEFT JOIN restaurant_tables assigned_tables ON bta.table_id = assigned_tables.table_id
    LEFT JOIN table_areas ON assigned_tables.area_id = table_areas.area_id
    LEFT JOIN restaurant_tables direct_table ON b.table_id = direct_table.table_id
    LEFT JOIN table_areas direct_area ON direct_table.area_id = direct_area.area_id
";

$bookingListRows = [];
try {
    $bookingListStmt = $pdo->prepare("
        SELECT
            b.booking_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.status,
            b.reservation_card_status,
            COALESCE(b.booking_type, 'normal') AS booking_type,
            b.special_request,
            COALESCE(NULLIF(b.customer_phone, ''), u.phone, '') AS customer_phone,
            COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name,
            COALESCE(
                GROUP_CONCAT(DISTINCT assigned_tables.table_number ORDER BY assigned_tables.table_number + 0, assigned_tables.table_number SEPARATOR ', '),
                direct_table.table_number
            ) AS assigned_table_numbers,
            COALESCE(
                GROUP_CONCAT(DISTINCT table_areas.name ORDER BY table_areas.display_order ASC, table_areas.name ASC SEPARATOR ', '),
                direct_area.name,
                ''
            ) AS assigned_area_names
        {$bookingListFromSql}
        WHERE {$bookingListWhere}
        GROUP BY b.booking_id
        ORDER BY {$bookingListOrderSql}
    ");
    $bookingListStmt->execute($bookingListParams);
    $bookingListRows = $bookingListStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $bookingListError) {
    $bookingListRows = [];
}

$formatBookingListTime = static function (?string $timeValue): string {
    $timestamp = strtotime((string) $timeValue);
    return $timestamp ? date('g:i A', $timestamp) : 'Time TBC';
};

$formatBookingListDate = static function (?string $dateValue): string {
    $timestamp = strtotime((string) $dateValue);
    return $timestamp ? date('D, j M', $timestamp) : 'Date TBC';
};

$resolveBookingListType = static function (array $booking): array {
    $bookingType = normalizeBookingType($booking['booking_type'] ?? 'normal');
    if ($bookingType === 'function') {
        return ['label' => 'Function', 'icon' => 'bi-diagram-3', 'tone' => 'function'];
    }

    if ($bookingType === 'trivia') {
        return ['label' => 'Trivia', 'icon' => 'bi-question-circle', 'tone' => 'trivia'];
    }

    $startTime = (string) ($booking['start_time'] ?? '');
    if ($startTime >= '17:00:00') {
        return ['label' => 'Dinner', 'icon' => 'bi-people-fill', 'tone' => 'dinner'];
    }

    return ['label' => 'Lunch', 'icon' => 'bi-people', 'tone' => 'lunch'];
};

$resolveBookingListStatus = static function (array $booking, bool $hasAssignedTable): array {
    $status = strtolower(trim((string) ($booking['status'] ?? 'confirmed')));
    if ($status === 'cancelled') {
        return ['label' => 'Cancelled', 'tone' => 'cancelled'];
    }

    if ($status === 'pending') {
        return ['label' => 'Pending', 'tone' => 'pending'];
    }

    if (!$hasAssignedTable) {
        return ['label' => 'No Table', 'tone' => 'no-table'];
    }

    return ['label' => getBookingStatusLabel($status), 'tone' => $status !== '' ? $status : 'confirmed'];
};

$bookingGroupBlueprints = [
    'lunch' => [
        'label' => 'Lunch',
        'range' => '11:00 AM - 2:30 PM',
        'icon' => 'bi-people-fill',
        'tone' => 'lunch',
    ],
    'dinner' => [
        'label' => 'Dinner',
        'range' => '5:00 PM - 10:00 PM',
        'icon' => 'bi-person-fill',
        'tone' => 'dinner',
    ],
    'trivia' => [
        'label' => 'Trivia',
        'range' => '7:30 PM - 10:30 PM',
        'icon' => 'bi-question-circle-fill',
        'tone' => 'trivia',
    ],
    'function' => [
        'label' => 'Functions',
        'range' => 'All Day',
        'icon' => 'bi-calendar2-check-fill',
        'tone' => 'function',
    ],
];
$bookingListGroups = [];
foreach ($bookingGroupBlueprints as $groupKey => $groupMeta) {
    $bookingListGroups[$groupKey] = $groupMeta + [
        'rows' => [],
        'guest_count' => 0,
        'pending_count' => 0,
    ];
}

foreach ($bookingListRows as $bookingListRow) {
    $groupMeta = $resolveBookingListType($bookingListRow);
    $groupKey = (string) ($groupMeta['tone'] ?? 'lunch');
    if (!isset($bookingListGroups[$groupKey])) {
        $groupKey = 'lunch';
    }

    $bookingListGroups[$groupKey]['rows'][] = $bookingListRow;
    $bookingListGroups[$groupKey]['guest_count'] += (int) ($bookingListRow['number_of_guests'] ?? 0);
    if (strtolower(trim((string) ($bookingListRow['status'] ?? ''))) === 'pending') {
        $bookingListGroups[$groupKey]['pending_count']++;
    }
}

$visibleBookingGroups = array_filter($bookingListGroups, static function (array $group): bool {
    return !empty($group['rows']);
});
$defaultOpenBookingGroup = !empty($visibleBookingGroups['dinner']) ? 'dinner' : (array_key_first($visibleBookingGroups) ?: '');
$formatBookingGroupMetric = static function (int $count, string $singular, ?string $plural = null): string {
    return number_format($count) . ' ' . ($count === 1 ? $singular : ($plural ?? $singular . 's'));
};
$styleVersion = (string) (@filemtime(__DIR__ . '/../../assets/css/style.css') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bookings | DineMate Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../partials/admin-new-sidebar.php'; ?>

        <main class="main-content" aria-label="Bookings page">
            <header class="page-header admin-bookings-header">
                <div>
                    <h1 class="page-title">Bookings</h1>
                    <p class="page-subtitle"><span data-result-count><?php echo number_format(count($bookingListRows)); ?> booking<?php echo count($bookingListRows) !== 1 ? 's' : ''; ?></span></p>
                </div>

                <div class="header-actions admin-bookings-actions" aria-label="Booking actions">
                    <button class="primary-btn header-add-booking-btn" type="button" data-admin-booking-create-open>
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span>Add Booking</span>
                    </button>

                    <a class="icon-btn notification-btn" href="admin_inbox.php" aria-label="Notifications">
                        <i class="bi bi-bell-fill" aria-hidden="true"></i>
                        <?php if ($pendingBookingsCount > 0): ?>
                            <span class="notification-badge"><?php echo htmlspecialchars((string) min($pendingBookingsCount, 99), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </header>

            <div class="admin-bookings-layout">
                <div class="admin-bookings-primary-column">
                    <form class="admin-bookings-status-form" method="get" aria-label="Booking status views">
                        <?php
                            echo $renderAdminBookingHiddenInput('per_page', (string) $bookingsPerPage);
                            echo $renderAdminBookingHiddenInput('page', '1');
                            echo $renderAdminBookingHiddenInput('sort', $selectedBookingSort);
                            echo $renderAdminBookingHiddenInput('booking_search', $bookingSearch);
                            if ($hasActiveBookingDateFilter) {
                                echo $renderAdminBookingHiddenInput('booking_date_start', $selectedBookingDateStart);
                                if ($selectedBookingDateEnd !== $selectedBookingDateStart) {
                                    echo $renderAdminBookingHiddenInput('booking_date_end', $selectedBookingDateEnd);
                                }
                            }
                            foreach ([
                                'guest_count' => $selectedGuestCounts,
                                'source' => $selectedSources,
                                'assignment' => $selectedAssignments,
                            ] as $hiddenName => $hiddenValues) {
                                foreach ($hiddenValues as $hiddenValue) {
                                    echo $renderAdminBookingHiddenInput($hiddenName . '[]', (string) $hiddenValue);
                                }
                            }
                        ?>
                        <div class="admin-bookings-status-row">
                            <?php foreach ($bookingStatusTabs as $statusViewValue => $statusViewLabel): ?>
                                <button
                                    type="submit"
                                    name="status_view"
                                    value="<?php echo htmlspecialchars($statusViewValue, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="admin-bookings-status-tab<?php echo $selectedBookingStatusView === $statusViewValue ? ' is-active' : ''; ?>"
                                    <?php echo $selectedBookingStatusView === $statusViewValue ? 'aria-current="page"' : ''; ?>
                                >
                                    <span class="admin-bookings-status-label"><?php echo htmlspecialchars($statusViewLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php $tabCount = $bookingStatusCounts[$statusViewValue] ?? 0; if ($tabCount > 0): ?>
                                        <span class="admin-bookings-status-count"><?php echo number_format(min($tabCount, 999)); ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <form class="admin-bookings-filter-row card" method="get" aria-label="Booking filters">
                        <input type="hidden" name="per_page" value="<?php echo htmlspecialchars((string) $bookingsPerPage, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="page" value="1">
                        <input type="hidden" name="status_view" value="<?php echo htmlspecialchars($selectedBookingStatusView, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="admin-bookings-filter-main">
                            <label class="admin-bookings-search" aria-label="Search bookings">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input
                                    type="search"
                                    name="booking_search"
                                    value="<?php echo htmlspecialchars($bookingSearch, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Search bookings, guests, phone..."
                                    data-live-search
                                >
                            </label>

                            <details
                                class="admin-bookings-date-picker"
                                data-date-range-picker
                                data-today="<?php echo htmlspecialchars($todayDate, ENT_QUOTES, 'UTF-8'); ?>"
                                data-start="<?php echo htmlspecialchars($calendarBookingDateStart, ENT_QUOTES, 'UTF-8'); ?>"
                                data-end="<?php echo htmlspecialchars($calendarBookingDateEnd, ENT_QUOTES, 'UTF-8'); ?>"
                                data-has-filter="<?php echo $hasActiveBookingDateFilter ? '1' : '0'; ?>"
                            >
                                <summary class="admin-bookings-filter-control admin-bookings-date-control" aria-label="Booking date range">
                                    <span class="admin-bookings-control-label">Date</span>
                                    <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                    <span data-date-summary><?php echo htmlspecialchars($bookingDateControlLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <i class="bi bi-chevron-down admin-bookings-date-caret" aria-hidden="true"></i>
                                </summary>
                                <input
                                    type="hidden"
                                    name="booking_date_start"
                                    value="<?php echo htmlspecialchars($hasActiveBookingDateFilter ? $selectedBookingDateStart : '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date-start-input
                                >
                                <input
                                    type="hidden"
                                    name="booking_date_end"
                                    value="<?php echo htmlspecialchars($hasActiveBookingDateFilter ? $selectedBookingDateEnd : '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-date-end-input
                                >
                                <div class="admin-date-popover" role="dialog" aria-label="Choose booking date range">
                                    <div class="admin-date-popover-head">
                                        <button type="button" class="admin-date-nav" data-calendar-prev aria-label="Previous month">
                                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                        </button>
                                        <strong data-calendar-title><?php echo htmlspecialchars(date('F Y', strtotime($calendarBookingDateStart)), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <button type="button" class="admin-date-nav" data-calendar-next aria-label="Next month">
                                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                        </button>
                                    </div>

                                    <div class="admin-date-weekdays" aria-hidden="true">
                                        <span>Mon</span>
                                        <span>Tue</span>
                                        <span>Wed</span>
                                        <span>Thu</span>
                                        <span>Fri</span>
                                        <span>Sat</span>
                                        <span>Sun</span>
                                    </div>
                                    <div class="admin-date-grid" data-calendar-grid></div>

                                    <div class="admin-date-range-preview" aria-live="polite">
                                        <span>
                                            <small>Start</small>
                                            <strong data-date-start-label><?php echo htmlspecialchars($hasActiveBookingDateFilter ? $formatBookingDateLabel($calendarBookingDateStart) : 'Select date', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </span>
                                        <span>
                                            <small>End</small>
                                            <strong data-date-end-label><?php echo htmlspecialchars($hasActiveBookingDateFilter ? $formatBookingDateLabel($calendarBookingDateEnd) : 'Select date', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </span>
                                    </div>

                                </div>
                            </details>

                            <details class="admin-bookings-sort-filter">
                                <summary class="admin-bookings-filter-control admin-bookings-sort-button">
                                    <span class="admin-bookings-control-label">Sort</span>
                                    <span class="admin-bookings-control-value"><?php echo htmlspecialchars($bookingSortSummaryLabels[$selectedBookingSort] ?? 'Time Earliest', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                </summary>

                                <div class="admin-bookings-sort-popover">
                                    <div class="admin-sort-option-list" role="radiogroup" aria-label="Sort bookings">
                                        <?php foreach ($bookingSortOptions as $sortValue => $sortLabel): ?>
                                            <label class="admin-sort-option">
                                                <input
                                                    type="radio"
                                                    name="sort"
                                                    value="<?php echo htmlspecialchars($sortValue, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php echo $selectedBookingSort === $sortValue ? 'checked' : ''; ?>
                                                    onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit();"
                                                >
                                                <span><?php echo htmlspecialchars($sortLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </details>

                            <details class="admin-bookings-more-filter" <?php echo $hasActiveMoreFilters ? 'open' : ''; ?>>
                                <summary class="admin-bookings-filter-control admin-bookings-filter-button">
                                    <span>More Filters</span>
                                    <?php if ($activeMoreFilterCount > 0): ?>
                                        <strong><?php echo number_format($activeMoreFilterCount); ?></strong>
                                    <?php endif; ?>
                                    <i class="bi bi-filter" aria-hidden="true"></i>
                                </summary>

                                <div class="admin-bookings-filter-popover">
                                <section class="admin-filter-group" aria-labelledby="guest-count-filter-title">
                                    <h2 id="guest-count-filter-title">Guest Count</h2>
                                    <div class="admin-filter-chip-grid compact">
                                        <?php
                                            $guestCountFilterOptions = [
                                                '1-7' => '1-7',
                                                '8-20' => '8-20',
                                                '20-40' => '20-40',
                                                '40-60' => '40-60',
                                                '60-100' => '60-100',
                                                '100plus' => '100+',
                                            ];
                                        ?>
                                        <?php foreach ($guestCountFilterOptions as $guestValue => $guestLabel): ?>
                                            <label class="admin-filter-chip">
                                                <input type="checkbox" name="guest_count[]" value="<?php echo htmlspecialchars($guestValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($guestValue, $selectedGuestCounts, true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($guestLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <section class="admin-filter-group" aria-labelledby="source-filter-title">
                                    <h2 id="source-filter-title">Source</h2>
                                    <div class="admin-filter-chip-grid compact">
                                        <?php foreach (['website' => 'Website', 'staff' => 'Staff'] as $sourceValue => $sourceLabel): ?>
                                            <label class="admin-filter-chip">
                                                <input type="checkbox" name="source[]" value="<?php echo htmlspecialchars($sourceValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($sourceValue, $selectedSources, true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <section class="admin-filter-group" aria-labelledby="assignment-filter-title">
                                    <h2 id="assignment-filter-title">Table Assignment</h2>
                                    <div class="admin-filter-chip-grid compact">
                                        <?php
                                            $assignmentFilterOptions = [
                                                'assigned' => 'Assigned',
                                                'no_table' => 'No Table',
                                                'waitlist' => 'Waitlist',
                                            ];
                                        ?>
                                        <?php foreach ($assignmentFilterOptions as $assignmentValue => $assignmentLabel): ?>
                                            <label class="admin-filter-chip">
                                                <input type="checkbox" name="assignment[]" value="<?php echo htmlspecialchars($assignmentValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($assignmentValue, $selectedAssignments, true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($assignmentLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <div class="admin-filter-actions">
                                    <a class="admin-filter-reset" href="<?php echo htmlspecialchars($buildBookingUrl([
                                        'guest_count' => null,
                                        'source' => null,
                                        'assignment' => null,
                                        'page' => 1,
                                    ]), ENT_QUOTES, 'UTF-8'); ?>">Reset</a>
                                    <button type="submit" class="admin-filter-apply">Apply Filters</button>
                                </div>
                                </div>
                            </details>
                        </div>

                        <?php if (!empty($activeFilterChips)): ?>
                            <div class="admin-bookings-active-filters" aria-label="Active booking filters">
                                <?php foreach ($activeFilterChips as $activeFilterChip): ?>
                                    <a class="admin-bookings-active-filter" href="<?php echo htmlspecialchars((string) $activeFilterChip['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <span><?php echo htmlspecialchars((string) $activeFilterChip['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <i class="bi bi-x" aria-hidden="true"></i>
                                    </a>
                                <?php endforeach; ?>
                                <a class="admin-bookings-clear-filters" href="<?php echo htmlspecialchars($clearAllFiltersUrl, ENT_QUOTES, 'UTF-8'); ?>">Clear all</a>
                            </div>
                        <?php endif; ?>
                    </form>

                    <section class="admin-bookings-grouped-view" aria-label="Bookings grouped by service">
                        <?php if (empty($visibleBookingGroups)): ?>
                            <div class="admin-booking-group-empty card">
                                <span class="booking-table-empty-icon">
                                    <i class="bi bi-calendar-x" aria-hidden="true"></i>
                                </span>
                                <strong>No bookings found</strong>
                                <span>Bookings matching this view will appear here.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($visibleBookingGroups as $groupKey => $group): ?>
                                <?php
                                    $groupRows = $group['rows'] ?? [];
                                    $groupBookingCount = count($groupRows);
                                    $groupGuestCount = (int) ($group['guest_count'] ?? 0);
                                    $groupPendingCount = (int) ($group['pending_count'] ?? 0);
                                    $extraBookingCount = max(0, $groupBookingCount - 3);
                                    $isDefaultOpenGroup = $groupKey === $defaultOpenBookingGroup;
                                ?>
                                <details class="admin-booking-group admin-booking-group-<?php echo htmlspecialchars((string) $group['tone'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isDefaultOpenGroup ? 'open' : ''; ?>>
                                    <summary class="admin-booking-group-summary">
                                        <span class="admin-booking-group-icon">
                                            <i class="bi <?php echo htmlspecialchars((string) $group['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                        </span>
                                        <span class="admin-booking-group-copy">
                                            <strong><?php echo htmlspecialchars((string) $group['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span>
                                                <?php echo htmlspecialchars((string) $group['range'], ENT_QUOTES, 'UTF-8'); ?>
                                                <b aria-hidden="true">&bull;</b>
                                                <?php echo htmlspecialchars($formatBookingGroupMetric($groupBookingCount, 'booking'), ENT_QUOTES, 'UTF-8'); ?>
                                                <b aria-hidden="true">&bull;</b>
                                                <?php echo htmlspecialchars($formatBookingGroupMetric($groupGuestCount, 'guest'), ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if ($groupPendingCount > 0 && !in_array($selectedBookingStatusView, ['past', 'cancelled'], true)): ?>
                                                    <b aria-hidden="true">&bull;</b>
                                                    <em><?php echo htmlspecialchars($formatBookingGroupMetric($groupPendingCount, 'pending'), ENT_QUOTES, 'UTF-8'); ?></em>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                        <i class="bi bi-chevron-down admin-booking-group-chevron" aria-hidden="true"></i>
                                    </summary>

                                    <div class="admin-booking-group-list">
                                        <div class="admin-booking-group-header" aria-hidden="true">
                                            <span>Time</span>
                                            <span>Guest</span>
                                            <span>Party</span>
                                            <span>Table</span>
                                            <span>Status</span>
                                            <span>Notes</span>
                                            <span></span>
                                        </div>
                                        <?php foreach ($groupRows as $bookingIndex => $booking): ?>
                                            <?php
                                                $guestName = (string) ($booking['customer_name'] ?? 'Guest');
                                                $guestPhone = trim((string) ($booking['customer_phone'] ?? ''));
                                                $assignedTables = trim((string) ($booking['assigned_table_numbers'] ?? ''));
                                                $assignedArea = trim((string) ($booking['assigned_area_names'] ?? ''));
                                                $hasAssignedTable = $assignedTables !== '';
                                                $statusMeta = $resolveBookingListStatus($booking, $hasAssignedTable);
                                                $noteText = trim((string) ($booking['special_request'] ?? ''));
                                            ?>
                                            <article class="admin-booking-group-row<?php echo $bookingIndex >= 3 ? ' is-extra' : ''; ?>" data-search-text="<?php echo htmlspecialchars(strtolower($guestName . ' ' . $guestPhone . ' ' . ($booking['booking_id'] ?? '') . ' ' . $assignedTables . ' ' . $assignedArea), ENT_QUOTES, 'UTF-8'); ?>">
                                                <time class="admin-booking-row-time" datetime="<?php echo htmlspecialchars((string) ($booking['booking_date'] ?? '') . ' ' . (string) ($booking['start_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <strong><?php echo htmlspecialchars($formatBookingListTime($booking['start_time'] ?? null), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <small><?php echo htmlspecialchars($formatBookingListDate($booking['booking_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></small>
                                                </time>
                                                <span class="admin-booking-row-guest">
                                                    <strong><?php echo htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <small><?php echo htmlspecialchars($guestPhone !== '' ? $guestPhone : '-', ENT_QUOTES, 'UTF-8'); ?></small>
                                                </span>
                                                <span class="admin-booking-row-count">
                                                    <i class="bi bi-person" aria-hidden="true"></i>
                                                    <?php echo number_format((int) ($booking['number_of_guests'] ?? 0)); ?>
                                                </span>
                                                <span class="admin-booking-row-table">
                                                    <strong><?php echo htmlspecialchars($hasAssignedTable ? 'Table ' . $assignedTables : 'No Table', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <?php if ($hasAssignedTable): ?>
                                                        <small><?php echo htmlspecialchars($assignedArea !== '' ? $assignedArea : 'Dining Room', ENT_QUOTES, 'UTF-8'); ?></small>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="admin-bookings-status-pill <?php echo htmlspecialchars((string) $statusMeta['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars((string) $statusMeta['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <span class="admin-booking-row-note<?php echo $noteText === '' ? ' is-empty' : ''; ?>" title="<?php echo htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars($noteText !== '' ? $noteText : '-', ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <a class="admin-bookings-action" href="<?php echo htmlspecialchars($buildBookingUrl([
                                                    'booking_search' => (string) ($booking['booking_id'] ?? ''),
                                                    'booking_date_start' => (string) ($booking['booking_date'] ?? $todayDate),
                                                    'booking_date_end' => (string) ($booking['booking_date'] ?? $todayDate),
                                                    'page' => 1,
                                                ]), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Open booking">
                                                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                                                </a>
                                            </article>
                                        <?php endforeach; ?>

                                        <?php if ($extraBookingCount > 0): ?>
                                            <button type="button" class="admin-booking-group-more" data-booking-group-more aria-expanded="false">
                                                <span data-more-label>+ <?php echo htmlspecialchars($formatBookingGroupMetric($extraBookingCount, 'more booking'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </main>
    </div>
    <?php
    $adminBookingCreateDefaultDate = $todayDate;
    $adminBookingCreateMinDate = $todayDate;
    $adminBookingCreateEndpoint = '../actions/create-booking.php';
    include __DIR__ . '/../partials/admin-booking-create-modal.php';
    ?>
    <script>
        (() => {
            const dateFormat = new Intl.DateTimeFormat('en-AU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            const monthFormat = new Intl.DateTimeFormat('en-AU', {
                month: 'long',
                year: 'numeric'
            });

            const parseIsoDate = (value) => {
                if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                    return null;
                }
                const [year, month, day] = value.split('-').map(Number);
                return new Date(year, month - 1, day);
            };

            const toIsoDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const formatIsoDate = (value) => {
                const date = parseIsoDate(value);
                return date ? dateFormat.format(date) : '';
            };

            const compareIsoDates = (left, right) => {
                const leftDate = parseIsoDate(left);
                const rightDate = parseIsoDate(right);
                if (!leftDate || !rightDate) {
                    return 0;
                }
                return leftDate.getTime() - rightDate.getTime();
            };

            document.querySelectorAll('[data-date-range-picker]').forEach((picker) => {
                const today = picker.dataset.today;
                const startInput = picker.querySelector('[data-date-start-input]');
                const endInput = picker.querySelector('[data-date-end-input]');
                const summary = picker.querySelector('[data-date-summary]');
                const title = picker.querySelector('[data-calendar-title]');
                const grid = picker.querySelector('[data-calendar-grid]');
                const startLabel = picker.querySelector('[data-date-start-label]');
                const endLabel = picker.querySelector('[data-date-end-label]');
                const prevButton = picker.querySelector('[data-calendar-prev]');
                const nextButton = picker.querySelector('[data-calendar-next]');
                const form = picker.closest('form');

                let rangeStart = picker.dataset.start || today;
                let rangeEnd = picker.dataset.end || rangeStart;
                let hasSelection = picker.dataset.hasFilter === '1';
                let displayMonth = parseIsoDate(rangeStart) || parseIsoDate(today) || new Date();
                let choosingEnd = false;
                let hoverRangeEnd = '';

                const updateLabels = () => {
                    if (!hasSelection) {
                        summary.textContent = 'Any Date';
                        startLabel.textContent = 'Select date';
                        endLabel.textContent = 'Select date';
                        return;
                    }
                    if (choosingEnd) {
                        summary.textContent = formatIsoDate(rangeStart);
                        if (hoverRangeEnd && compareIsoDates(hoverRangeEnd, rangeStart) < 0) {
                            startLabel.textContent = formatIsoDate(hoverRangeEnd);
                            endLabel.textContent = formatIsoDate(rangeStart);
                        } else {
                            startLabel.textContent = formatIsoDate(rangeStart);
                            endLabel.textContent = hoverRangeEnd ? formatIsoDate(hoverRangeEnd) : 'Pick another date';
                        }
                        return;
                    }
                    const label = rangeStart === rangeEnd
                        ? formatIsoDate(rangeStart)
                        : `${formatIsoDate(rangeStart)} - ${formatIsoDate(rangeEnd)}`;
                    summary.textContent = label;
                    startLabel.textContent = formatIsoDate(rangeStart);
                    endLabel.textContent = formatIsoDate(rangeEnd);
                };

                const syncInputs = () => {
                    startInput.value = rangeStart;
                    endInput.value = rangeEnd;
                };

                const submitRange = () => {
                    syncInputs();
                    picker.removeAttribute('open');
                    if (form?.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        form?.submit();
                    }
                };

                const renderCalendar = () => {
                    const monthStart = new Date(displayMonth.getFullYear(), displayMonth.getMonth(), 1);
                    const mondayOffset = (monthStart.getDay() + 6) % 7;
                    const gridStart = new Date(monthStart);
                    gridStart.setDate(monthStart.getDate() - mondayOffset);
                    title.textContent = monthFormat.format(monthStart);
                    grid.innerHTML = '';
                    let previewStart = rangeStart;
                    let previewEnd = rangeEnd;
                    if (choosingEnd && hoverRangeEnd) {
                        if (compareIsoDates(hoverRangeEnd, rangeStart) < 0) {
                            previewStart = hoverRangeEnd;
                            previewEnd = rangeStart;
                        } else {
                            previewStart = rangeStart;
                            previewEnd = hoverRangeEnd;
                        }
                    }

                    for (let index = 0; index < 42; index += 1) {
                        const dayDate = new Date(gridStart);
                        dayDate.setDate(gridStart.getDate() + index);
                        const dayIso = toIsoDate(dayDate);
                        const isSelected = hasSelection && (
                            dayIso === rangeStart
                            || (!choosingEnd && dayIso === rangeEnd)
                            || (choosingEnd && hoverRangeEnd && dayIso === hoverRangeEnd)
                        );
                        const isInRange = hasSelection
                            && compareIsoDates(dayIso, previewStart) > 0
                            && compareIsoDates(dayIso, previewEnd) < 0;
                        const isOutside = dayDate.getMonth() !== monthStart.getMonth();

                        const dayButton = document.createElement('button');
                        dayButton.type = 'button';
                        dayButton.className = [
                            'admin-date-day',
                            isOutside ? 'is-outside' : '',
                            dayIso === today ? 'is-today' : '',
                            isInRange ? 'is-in-range' : '',
                            isSelected ? 'is-selected' : ''
                        ].filter(Boolean).join(' ');
                        dayButton.textContent = String(dayDate.getDate());
                        dayButton.setAttribute('aria-label', dateFormat.format(dayDate));
                        dayButton.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                        dayButton.addEventListener('mouseenter', () => {
                            if (!choosingEnd || hoverRangeEnd === dayIso) {
                                return;
                            }
                            hoverRangeEnd = dayIso;
                            updateLabels();
                            renderCalendar();
                        });
                        dayButton.addEventListener('click', () => {
                            if (!choosingEnd) {
                                rangeStart = dayIso;
                                rangeEnd = dayIso;
                                hasSelection = true;
                                choosingEnd = true;
                                hoverRangeEnd = '';
                            } else {
                                if (compareIsoDates(dayIso, rangeStart) < 0) {
                                    rangeEnd = rangeStart;
                                    rangeStart = dayIso;
                                } else {
                                    rangeEnd = dayIso;
                                }
                                choosingEnd = false;
                                hoverRangeEnd = '';
                            }
                            displayMonth = parseIsoDate(rangeStart) || displayMonth;
                            updateLabels();
                            renderCalendar();

                            if (!choosingEnd) {
                                submitRange();
                            }
                        });
                        grid.appendChild(dayButton);
                    }
                };

                prevButton.addEventListener('click', () => {
                    displayMonth = new Date(displayMonth.getFullYear(), displayMonth.getMonth() - 1, 1);
                    renderCalendar();
                });

                nextButton.addEventListener('click', () => {
                    displayMonth = new Date(displayMonth.getFullYear(), displayMonth.getMonth() + 1, 1);
                    renderCalendar();
                });

                grid.addEventListener('mouseleave', () => {
                    if (!choosingEnd || hoverRangeEnd === '') {
                        return;
                    }
                    hoverRangeEnd = '';
                    updateLabels();
                    renderCalendar();
                });

                form?.addEventListener('submit', () => {
                    if (hasSelection && !choosingEnd) {
                        syncInputs();
                    } else {
                        startInput.value = '';
                        endInput.value = '';
                    }
                });

                document.addEventListener('click', (event) => {
                    const eventPath = typeof event.composedPath === 'function' ? event.composedPath() : [];
                    if (!picker.contains(event.target) && !eventPath.includes(picker)) {
                        picker.removeAttribute('open');
                    }
                });

                picker.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        picker.removeAttribute('open');
                    }
                });

                picker.addEventListener('toggle', () => {
                    if (picker.open) {
                        renderCalendar();
                    }
                });

                updateLabels();
                renderCalendar();
            });
        })();
    </script>
    <script>
        (() => {
            document.querySelectorAll('[data-booking-group-more]').forEach((button) => {
                const group = button.closest('.admin-booking-group');
                const label = button.querySelector('[data-more-label]');
                const hiddenCount = group?.querySelectorAll('.admin-booking-group-row.is-extra').length || 0;
                const defaultText = label?.textContent || '';

                button.addEventListener('click', () => {
                    const isExpanded = group?.classList.toggle('show-all') || false;
                    button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                    if (label) {
                        label.textContent = isExpanded ? 'Show fewer bookings' : defaultText;
                    }
                    button.querySelector('i')?.classList.toggle('is-open', isExpanded);
                });

                if (hiddenCount === 0) {
                    button.hidden = true;
                }
            });
        })();
    </script>
    <script>
        (() => {
            const liveSearchInput = document.querySelector('[data-live-search]');
            if (!liveSearchInput) return;

            const resultCount = document.querySelector('[data-result-count]');
            const allRows = Array.from(document.querySelectorAll('.admin-booking-group-row'));
            const total = allRows.length;

            liveSearchInput.addEventListener('input', () => {
                const query = liveSearchInput.value.trim().toLowerCase();
                let visible = 0;

                allRows.forEach((row) => {
                    const text = (row.dataset.searchText || '').toLowerCase();
                    const match = query === '' || text.includes(query);
                    row.style.display = match ? '' : 'none';
                    if (match) visible++;
                });

                document.querySelectorAll('.admin-booking-group').forEach((group) => {
                    const hasVisible = group.querySelectorAll('.admin-booking-group-row:not([style*="none"])').length > 0;
                    group.style.display = hasVisible ? '' : 'none';
                });

                if (resultCount) {
                    if (query === '') {
                        resultCount.textContent = `${total} booking${total !== 1 ? 's' : ''}`;
                    } else {
                        resultCount.textContent = `${visible} of ${total} match${visible !== 1 ? 'es' : ''}`;
                    }
                }
            });
        })();
    </script>
</body>
</html>
