<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);
ensureInboxMessagesTable($pdo);

$adminNewSidebarActive = 'inbox';
$adminActionCsrfToken = csrfToken('admin_actions');

$allowedFolders = ['requests', 'unassigned', 'waitlist'];
$activeFolder = strtolower(trim((string) ($_GET['folder'] ?? 'requests')));
if (!in_array($activeFolder, $allowedFolders, true)) {
    $activeFolder = 'requests';
}

$folderCounts = getInboxFolderCounts($pdo);
$totalInboxNotifications = array_sum($folderCounts);

$inboxPerPage = 6;
$inboxTotal = (int) ($folderCounts[$activeFolder] ?? 0);
$inboxTotalPages = max(1, (int) ceil($inboxTotal / $inboxPerPage));
$inboxPage = max(1, (int) ($_GET['page'] ?? 1));
if ($inboxPage > $inboxTotalPages) {
    $inboxPage = $inboxTotalPages;
}
$inboxOffset = ($inboxPage - 1) * $inboxPerPage;

$messageStmt = $pdo->prepare("
    SELECT im.*,
           b.booking_date,
           b.start_time,
           b.end_time,
           b.number_of_guests AS booking_guests,
           b.booking_type,
           b.status AS booking_status,
           b.table_id,
           b.special_request,
           b.menu_items,
           (SELECT COUNT(*) FROM booking_table_assignments bta WHERE bta.booking_id = b.booking_id) AS assignment_count
    FROM inbox_messages im
    LEFT JOIN bookings b ON b.booking_id = im.booking_id
    WHERE im.folder = ?
      AND (b.booking_date IS NULL OR b.booking_date >= CURDATE())
    ORDER BY im.received_at DESC, im.inbox_id DESC
    LIMIT {$inboxPerPage} OFFSET {$inboxOffset}
");
$messageStmt->execute([$activeFolder]);
$inboxMessages = $messageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selectedInboxId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$selectedMessage = null;
if ($selectedInboxId > 0) {
    foreach ($inboxMessages as $row) {
        if ((int) $row['inbox_id'] === $selectedInboxId) {
            $selectedMessage = $row;
            break;
        }
    }

    if ($selectedMessage === null) {
        $loadStmt = $pdo->prepare("
            SELECT im.*, b.booking_date, b.start_time, b.end_time,
                   b.number_of_guests AS booking_guests, b.booking_type,
                   b.status AS booking_status, b.table_id, b.special_request, b.menu_items
            FROM inbox_messages im
            LEFT JOIN bookings b ON b.booking_id = im.booking_id
            WHERE im.inbox_id = ?
            LIMIT 1
        ");
        $loadStmt->execute([$selectedInboxId]);
        $loadedRow = $loadStmt->fetch(PDO::FETCH_ASSOC);
        if ($loadedRow) {
            $selectedMessage = $loadedRow;
        }
    }
}
if ($selectedMessage === null && !empty($inboxMessages)) {
    $selectedMessage = $inboxMessages[0];
    $selectedInboxId = (int) $selectedMessage['inbox_id'];
}

if ($selectedMessage !== null && empty($selectedMessage['is_read'])) {
    $markRead = $pdo->prepare("UPDATE inbox_messages SET is_read = 1 WHERE inbox_id = ?");
    $markRead->execute([(int) $selectedMessage['inbox_id']]);
    $selectedMessage['is_read'] = 1;
}

$areaAvailability = [];
if ($selectedMessage !== null && !empty($selectedMessage['booking_date'])) {
    $areaAvailability = inboxAreaAvailability(
        $pdo,
        (string) $selectedMessage['booking_date'],
        (string) ($selectedMessage['start_time'] ?? '18:00:00'),
        (string) ($selectedMessage['end_time'] ?? '20:00:00')
    );
}

$selectedBookingId = $selectedMessage !== null && !empty($selectedMessage['booking_id'])
    ? (int) $selectedMessage['booking_id']
    : 0;
$tableAssignmentFloorZones = [];
$tableAssignmentFloorTables = [];
$tableAssignmentAvailableCount = 0;
$selectedTableId = !empty($selectedMessage['table_id']) ? (int) $selectedMessage['table_id'] : 0;
$selectedTableIds = $selectedTableId > 0 ? [$selectedTableId] : [];
$selectedTableLabel = 'No table assigned';

if ($selectedBookingId > 0) {
    $normalizeInboxAreaName = static function (string $value): string {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
    };

    $resolveInboxZoneKey = static function (string $name) use ($normalizeInboxAreaName): string {
        $normalized = $normalizeInboxAreaName($name);

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

    $inboxFloorBlueprints = [
        'stables'    => ['label' => 'Stables',    'tone' => 'amber',    'icon' => 'fa-horse',                 'x' => 148, 'y' => 12,  'width' => 274, 'height' => 150],
        'kookaburra' => ['label' => 'Kookaburra', 'tone' => 'green',    'icon' => 'fa-leaf',                  'x' => 24,  'y' => 52,  'width' => 104, 'height' => 350],
        'wisteria'   => ['label' => 'Wisteria',   'tone' => 'pink',     'icon' => 'fa-seedling',              'x' => 532, 'y' => 12,  'width' => 292, 'height' => 242],
        'schumack'   => ['label' => 'Schumack',   'tone' => 'blue',     'icon' => 'fa-anchor',                'x' => 532, 'y' => 272, 'width' => 294, 'height' => 128],
        'main-bar'   => ['label' => 'Main Bar',   'tone' => 'lavender', 'icon' => 'fa-martini-glass-citrus',  'x' => 142, 'y' => 186, 'width' => 372, 'height' => 216],
        'osf'        => ['label' => 'OSF',        'tone' => 'mocha',    'icon' => 'fa-tree',                  'x' => 20,  'y' => 416, 'width' => 820, 'height' => 160],
    ];

    $tableOptionsStmt = $pdo->query("
        SELECT rt.table_id,
               rt.table_number,
               rt.capacity,
               rt.area_id,
               rt.sort_order,
               rt.status,
               COALESCE(rt.reservable, 1) AS reservable,
               rt.layout_x,
               rt.layout_y,
               COALESCE(ta.name, 'Dining Room') AS area_name
        FROM restaurant_tables rt
        LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
        ORDER BY COALESCE(ta.display_order, 9999) ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
    ");
    $tableAssignmentRows = $tableOptionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $areaRows = $pdo->query("
        SELECT area_id, name, display_order, layout_x, layout_y, layout_width, layout_height, label_layout_x, label_layout_y
        FROM table_areas
        WHERE is_active = 1
        ORDER BY display_order ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $tableAssignmentAreasById = [];
    foreach ($areaRows as $area) {
        $areaId = (int) ($area['area_id'] ?? 0);
        $zoneKey = $resolveInboxZoneKey((string) ($area['name'] ?? ''));
        $blueprint = $inboxFloorBlueprints[$zoneKey] ?? $inboxFloorBlueprints['osf'];
        $zone = [
            'area_id' => $areaId,
            'label' => (string) ($area['name'] ?? $blueprint['label']),
            'tone' => $blueprint['tone'],
            'icon' => $blueprint['icon'],
            'zone_key' => $zoneKey,
            'x' => $area['layout_x'] !== null ? (int) $area['layout_x'] : (int) $blueprint['x'],
            'y' => $area['layout_y'] !== null ? (int) $area['layout_y'] : (int) $blueprint['y'],
            'width' => $area['layout_width'] !== null ? (int) $area['layout_width'] : (int) $blueprint['width'],
            'height' => $area['layout_height'] !== null ? (int) $area['layout_height'] : (int) $blueprint['height'],
        ];
        $defaultLabelOffset = $zoneKey === 'osf' ? 190 : 52;
        $zone['label_x'] = $area['label_layout_x'] !== null ? (int) $area['label_layout_x'] : min($zone['x'] + $zone['width'] - 28, $zone['x'] + $defaultLabelOffset);
        $zone['label_y'] = $area['label_layout_y'] !== null ? (int) $area['label_layout_y'] : $zone['y'] + 14;

        $tableAssignmentAreasById[$areaId] = $zone;
        $tableAssignmentFloorZones[] = $zone;
    }

    $tablesByAreaForLayout = [];
    foreach ($tableAssignmentRows as $tableRow) {
        $areaId = (int) ($tableRow['area_id'] ?? 0);
        $tablesByAreaForLayout[$areaId] ??= [];
        $tablesByAreaForLayout[$areaId][] = $tableRow;
    }

    $assignedTableStmt = $pdo->prepare("
        SELECT rt.table_id,
               rt.table_number,
               rt.capacity,
               COALESCE(ta.name, 'Dining Room') AS area_name
        FROM booking_table_assignments bta
        INNER JOIN restaurant_tables rt ON rt.table_id = bta.table_id
        LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
        WHERE bta.booking_id = ?
        ORDER BY COALESCE(ta.display_order, 9999) ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
    ");
    $assignedTableStmt->execute([$selectedBookingId]);
    $assignedTables = $assignedTableStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (empty($assignedTables) && $selectedTableId > 0) {
        $fallbackTableStmt = $pdo->prepare("
            SELECT rt.table_id,
                   rt.table_number,
                   rt.capacity,
                   COALESCE(ta.name, 'Dining Room') AS area_name
            FROM restaurant_tables rt
            LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
            WHERE rt.table_id = ?
            LIMIT 1
        ");
        $fallbackTableStmt->execute([$selectedTableId]);
        $fallbackTable = $fallbackTableStmt->fetch(PDO::FETCH_ASSOC);
        if ($fallbackTable) {
            $assignedTables = [$fallbackTable];
        }
    }

    if (!empty($assignedTables)) {
        $selectedTableIds = array_values(array_unique(array_map(static function (array $assignedTable): int {
            return (int) ($assignedTable['table_id'] ?? 0);
        }, $assignedTables)));
        $selectedTableIds = array_values(array_filter($selectedTableIds));
        $selectedTableId = $selectedTableIds[0] ?? 0;

        $selectedTableNumbers = array_map(static function (array $assignedTable): string {
            return (string) ($assignedTable['table_number'] ?? '');
        }, $assignedTables);
        $selectedTableNumbers = array_values(array_filter($selectedTableNumbers, static fn(string $value): bool => $value !== ''));

        $selectedAreaNames = array_map(static function (array $assignedTable): string {
            return (string) ($assignedTable['area_name'] ?? '');
        }, $assignedTables);
        $selectedAreaNames = array_values(array_unique(array_filter($selectedAreaNames, static fn(string $value): bool => $value !== '')));

        $selectedTableLabel = (count($selectedTableNumbers) > 1 ? 'Tables ' : 'Table ')
            . implode(', ', $selectedTableNumbers);
        if (!empty($selectedAreaNames)) {
            $selectedTableLabel .= ' - ' . implode(', ', $selectedAreaNames);
        }
    }

    $busyTableIds = [];
    $busyBookingsByTableId = [];
    $assignmentDate = trim((string) ($selectedMessage['booking_date'] ?? ''));
    $assignmentStart = trim((string) ($selectedMessage['start_time'] ?? '')) !== '' ? (string) $selectedMessage['start_time'] : '18:00:00';
    $assignmentEnd = trim((string) ($selectedMessage['end_time'] ?? '')) !== '' ? (string) $selectedMessage['end_time'] : '20:00:00';
    if ($assignmentDate !== '') {
        $busyStmt = $pdo->prepare("
            SELECT bta.table_id,
                   b.booking_id,
                   b.start_time,
                   b.number_of_guests,
                   b.special_request,
                   COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name
            FROM booking_table_assignments bta
            INNER JOIN bookings b ON b.booking_id = bta.booking_id
            LEFT JOIN users u ON b.user_id = u.user_id
            WHERE b.booking_date = ?
              AND b.booking_id != ?
              AND b.status IN ('pending', 'confirmed')
              AND b.start_time < ?
              AND b.end_time > ?
            ORDER BY b.start_time ASC, b.booking_id ASC
        ");
        $busyStmt->execute([$assignmentDate, $selectedBookingId, $assignmentEnd, $assignmentStart]);
        foreach ($busyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $busyRow) {
            $busyTableId = (int) ($busyRow['table_id'] ?? 0);
            if ($busyTableId < 1) {
                continue;
            }
            $busyTableIds[$busyTableId] = true;
            $busyBookingsByTableId[$busyTableId] ??= [];
            $busyBookingsByTableId[$busyTableId][] = $busyRow;
        }
    }

    foreach ($tableAssignmentRows as $tableRow) {
        $tableId = (int) ($tableRow['table_id'] ?? 0);
        $areaId = (int) ($tableRow['area_id'] ?? 0);
        $zone = $tableAssignmentAreasById[$areaId] ?? null;
        $areaTables = $tablesByAreaForLayout[$areaId] ?? [];
        $tableIndex = 0;
        foreach ($areaTables as $index => $areaTable) {
            if ((int) ($areaTable['table_id'] ?? 0) === $tableId) {
                $tableIndex = (int) $index;
                break;
            }
        }

        $zoneKey = $zone['zone_key'] ?? '';
        $layoutGrid = match ($zoneKey) {
            'kookaburra' => ['columns' => 1, 'gutter_x' => 0,  'gutter_y' => 88],
            'stables'    => ['columns' => 3, 'gutter_x' => 70, 'gutter_y' => 64],
            'wisteria',
            'schumack',
            'main-bar'   => ['columns' => 4, 'gutter_x' => 68, 'gutter_y' => 62],
            'osf'        => ['columns' => 9, 'gutter_x' => 88, 'gutter_y' => 72],
            default      => ['columns' => 3, 'gutter_x' => 70, 'gutter_y' => 66],
        };

        $layoutX = $tableRow['layout_x'] !== null ? (int) $tableRow['layout_x'] : null;
        $layoutY = $tableRow['layout_y'] !== null ? (int) $tableRow['layout_y'] : null;

        if (($layoutX === null || $layoutY === null) && $zone) {
            $columns = max(1, (int) $layoutGrid['columns']);
            $layoutX = min((int) $zone['x'] + (int) $zone['width'] - 40, (int) $zone['x'] + 34 + ($tableIndex % $columns) * (int) $layoutGrid['gutter_x']);
            $layoutY = min((int) $zone['y'] + (int) $zone['height'] - 40, (int) $zone['y'] + 34 + floor($tableIndex / $columns) * (int) $layoutGrid['gutter_y']);
        }

        $isSelected = in_array($tableId, $selectedTableIds, true);
        $tableStatus = strtolower(trim((string) ($tableRow['status'] ?? 'available')));
        $isUnreservable = (int) ($tableRow['reservable'] ?? 1) !== 1 || in_array($tableStatus, ['inactive', 'disabled'], true);
        $isBusy = !empty($busyTableIds[$tableId]) && !$isSelected;
        $isSelectable = !$isBusy && (!$isUnreservable || $isSelected);

        if ($isSelectable) {
            $tableAssignmentAvailableCount++;
        }

        $tableAssignmentFloorTables[] = array_merge($tableRow, [
            'layout_x' => $layoutX,
            'layout_y' => $layoutY,
            'tone' => $zone['tone'] ?? 'blue',
            'bookings' => $busyBookingsByTableId[$tableId] ?? [],
            'is_occupied' => $isBusy,
            'is_selected' => $isSelected,
            'is_busy' => $isBusy,
            'is_unreservable' => $isUnreservable,
            'is_selectable' => $isSelectable,
        ]);
    }
}

$flashMessage = $_SESSION['inbox_flash'] ?? null;
unset($_SESSION['inbox_flash']);

$buildInboxUrl = static function (array $overrides = []) use ($activeFolder, $selectedInboxId, $inboxPage): string {
    $params = ['folder' => $activeFolder];
    if ($inboxPage > 1) {
        $params['page'] = $inboxPage;
    }
    if ($selectedInboxId > 0) {
        $params['id'] = $selectedInboxId;
    }
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }
    return 'admin_inbox.php?' . http_build_query($params);
};

$folderLabels = [
    'requests'   => 'Requests',
    'unassigned' => 'Unassigned',
    'waitlist'   => 'Waitlist',
];

$styleVersion = (string) (@filemtime(__DIR__ . '/../../assets/css/style.css') ?: time());
$todayDate = date('Y-m-d');

$formatMessageDate = static function (?string $date): string {
    if (empty($date)) return 'Date TBC';
    $ts = strtotime($date);
    return $ts ? date('D, j M Y', $ts) : 'Date TBC';
};

$formatMessageTime = static function (?string $time): string {
    if (empty($time)) return 'Time TBC';
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : 'Time TBC';
};

$formatInboxBookingType = static function (array $message): string {
    if (empty($message['booking_id']) && ($message['type'] ?? '') === 'guest_message') {
        return 'Message';
    }

    $bookingType = normalizeBookingType($message['booking_type'] ?? 'normal');

    if ($bookingType === 'trivia') {
        return 'Trivia';
    }

    if ($bookingType === 'function') {
        return 'Function';
    }

    $startTime = (string) ($message['start_time'] ?? '');
    return $startTime >= '17:00:00' ? 'Dinner' : 'Lunch';
};

$statusBadgeMeta = static function (string $status): array {
    return match ($status) {
        'open'      => ['label' => 'Open',      'tone' => 'open'],
        'waiting'   => ['label' => 'Waiting',   'tone' => 'waiting'],
        'confirmed' => ['label' => 'Confirmed', 'tone' => 'confirmed'],
        'declined'  => ['label' => 'Declined',  'tone' => 'declined'],
        'resolved'  => ['label' => 'Resolved',  'tone' => 'resolved'],
        default     => ['label' => ucfirst($status), 'tone' => 'open'],
    };
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inbox | DineMate Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/../partials/admin-new-sidebar.php'; ?>

    <main class="main-content" aria-label="Inbox page">
        <header class="page-header admin-inbox-header">
            <h1 class="page-title">Inbox</h1>
            <div class="header-actions">
                <button class="primary-btn" type="button" data-admin-booking-create-open>
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    <span>Add Booking</span>
                </button>
                <?php include __DIR__ . '/../partials/admin-notification-dropdown.php'; ?>
            </div>
        </header>


        <div class="admin-inbox-layout">
            <!-- LEFT: list panel -->
            <section class="admin-inbox-list card" aria-label="Inbox messages">
                <nav class="admin-inbox-tabs" aria-label="Inbox folders">
                    <?php foreach ($folderLabels as $folderKey => $folderLabel): ?>
                        <?php $count = $folderCounts[$folderKey] ?? 0; ?>
                        <a class="admin-inbox-tab<?php echo $folderKey === $activeFolder ? ' is-active' : ''; ?>"
                           href="<?php echo htmlspecialchars($buildInboxUrl(['folder' => $folderKey, 'id' => null]), ENT_QUOTES, 'UTF-8'); ?>"
                           <?php echo $folderKey === $activeFolder ? 'aria-current="page"' : ''; ?>>
                            <span><?php echo htmlspecialchars($folderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($count > 0): ?>
                                <strong class="admin-inbox-tab-count"><?php echo number_format($count); ?></strong>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="admin-inbox-items" role="list">
                    <?php if (empty($inboxMessages)): ?>
                        <div class="admin-inbox-empty">
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <strong>No messages</strong>
                            <span>You're all caught up.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($inboxMessages as $message): ?>
                            <?php
                                $typeMeta = inboxTypeMeta((string) $message['type']);
                                $isSelected = (int) $message['inbox_id'] === $selectedInboxId;
                                $chip = inboxChipForBookingType((string) ($message['booking_type'] ?? 'normal'));
                                if ((string) $message['type'] === 'function_enquiry') {
                                    $chip = 'Function';
                                } elseif (in_array((string) $message['type'], ['booking_change', 'guest_message'], true)) {
                                    $chip = 'Trivia';
                                } elseif ((string) $message['type'] === 'cancellation') {
                                    $chip = 'Function';
                                }
                                $chipTone = strtolower($chip) === 'function' ? 'function' : 'trivia';
                                $relative = inboxFormatRelativeTime((string) ($message['received_at'] ?? ''));
                                $party = (int) ($message['party_size'] ?? $message['booking_guests'] ?? 0);
                            ?>
                            <a class="admin-inbox-item<?php echo $isSelected ? ' is-selected' : ''; ?><?php echo empty($message['is_read']) ? ' is-unread' : ''; ?>"
                               href="<?php echo htmlspecialchars($buildInboxUrl(['id' => (int) $message['inbox_id']]), ENT_QUOTES, 'UTF-8'); ?>"
                               role="listitem">
                                <span class="admin-inbox-item-icon tone-<?php echo htmlspecialchars($typeMeta['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="bi <?php echo htmlspecialchars($typeMeta['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                </span>
                                <div class="admin-inbox-item-body">
                                    <div class="admin-inbox-item-head">
                                        <strong><?php echo htmlspecialchars((string) ($message['subject'] ?? $typeMeta['label']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="admin-inbox-item-chip tone-<?php echo htmlspecialchars($chipTone, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($chip, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="admin-inbox-item-time"><?php echo htmlspecialchars($relative, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($party > 0): ?>
                                            <span class="admin-inbox-item-party">
                                                <i class="bi bi-person" aria-hidden="true"></i>
                                                <?php echo number_format($party); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="admin-inbox-item-name"><?php echo htmlspecialchars((string) ($message['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="admin-inbox-item-preview"><?php echo htmlspecialchars((string) ($message['preview'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <footer class="admin-inbox-list-footer">
                    <?php
                        $rangeStart = $inboxTotal === 0 ? 0 : $inboxOffset + 1;
                        $rangeEnd   = min($inboxTotal, $inboxOffset + count($inboxMessages));
                    ?>
                    <span>Showing <?php echo number_format($rangeStart); ?>&ndash;<?php echo number_format($rangeEnd); ?> of <?php echo number_format($inboxTotal); ?> <?php echo htmlspecialchars(strtolower($folderLabels[$activeFolder] ?? 'items'), ENT_QUOTES, 'UTF-8'); ?></span>

                    <?php if ($inboxTotalPages > 1): ?>
                        <nav class="admin-inbox-pagination" aria-label="Inbox pagination">
                            <?php
                                $prevDisabled = $inboxPage <= 1;
                                $nextDisabled = $inboxPage >= $inboxTotalPages;
                                $prevUrl = $buildInboxUrl(['page' => max(1, $inboxPage - 1), 'id' => null]);
                                $nextUrl = $buildInboxUrl(['page' => min($inboxTotalPages, $inboxPage + 1), 'id' => null]);

                                $windowStart = max(1, $inboxPage - 2);
                                $windowEnd   = min($inboxTotalPages, $windowStart + 4);
                                $windowStart = max(1, $windowEnd - 4);
                            ?>
                            <a class="admin-inbox-page-btn<?php echo $prevDisabled ? ' is-disabled' : ''; ?>"
                               href="<?php echo $prevDisabled ? '#' : htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8'); ?>"
                               aria-label="Previous page"
                               <?php echo $prevDisabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </a>

                            <?php if ($windowStart > 1): ?>
                                <a class="admin-inbox-page-btn" href="<?php echo htmlspecialchars($buildInboxUrl(['page' => 1, 'id' => null]), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                                <?php if ($windowStart > 2): ?>
                                    <span class="admin-inbox-page-ellipsis">&hellip;</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                                <?php if ($p === $inboxPage): ?>
                                    <span class="admin-inbox-page-btn is-current" aria-current="page"><?php echo $p; ?></span>
                                <?php else: ?>
                                    <a class="admin-inbox-page-btn" href="<?php echo htmlspecialchars($buildInboxUrl(['page' => $p, 'id' => null]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $p; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($windowEnd < $inboxTotalPages): ?>
                                <?php if ($windowEnd < $inboxTotalPages - 1): ?>
                                    <span class="admin-inbox-page-ellipsis">&hellip;</span>
                                <?php endif; ?>
                                <a class="admin-inbox-page-btn" href="<?php echo htmlspecialchars($buildInboxUrl(['page' => $inboxTotalPages, 'id' => null]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $inboxTotalPages; ?></a>
                            <?php endif; ?>

                            <a class="admin-inbox-page-btn<?php echo $nextDisabled ? ' is-disabled' : ''; ?>"
                               href="<?php echo $nextDisabled ? '#' : htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8'); ?>"
                               aria-label="Next page"
                               <?php echo $nextDisabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        </nav>
                    <?php endif; ?>
                </footer>
            </section>

            <!-- RIGHT: detail panel -->
            <section class="admin-inbox-detail card" aria-label="Selected message detail">
                <?php if ($selectedMessage === null): ?>
                    <div class="admin-inbox-empty">
                        <i class="bi bi-envelope-open" aria-hidden="true"></i>
                        <strong>Nothing to view</strong>
                        <span>Select a message from the list to view its details.</span>
                    </div>
                <?php else: ?>
                    <?php
                        $typeMeta = inboxTypeMeta((string) $selectedMessage['type']);
                        $statusMeta = $statusBadgeMeta((string) $selectedMessage['status']);
                        $relative = inboxFormatRelativeTime((string) ($selectedMessage['received_at'] ?? ''));
                    ?>
                    <header class="admin-inbox-detail-head">
                        <span class="admin-inbox-detail-icon tone-<?php echo htmlspecialchars($typeMeta['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="bi <?php echo htmlspecialchars($typeMeta['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                        </span>
                        <div class="admin-inbox-detail-head-text">
                            <h2><?php echo htmlspecialchars((string) ($selectedMessage['subject'] ?? $typeMeta['label']), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div class="admin-inbox-detail-head-meta">
                                <span><?php echo htmlspecialchars((string) ($selectedMessage['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <small>Received <?php echo htmlspecialchars($relative !== '' ? $relative : 'just now', ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                        </div>
                        <div class="admin-inbox-detail-head-actions">
                            <span class="admin-inbox-status-pill tone-<?php echo htmlspecialchars($statusMeta['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($statusMeta['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <button type="button" class="icon-btn admin-inbox-detail-menu" aria-label="More actions">
                                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                            </button>
                        </div>
                    </header>

                    <div class="admin-inbox-detail-grid">
                        <section class="admin-inbox-info-block" aria-labelledby="guest-info-title">
                            <h3 id="guest-info-title">Guest Information</h3>
                            <ul>
                                <li>
                                    <i class="bi bi-person" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars((string) ($selectedMessage['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                                <li>
                                    <i class="bi bi-telephone" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars(!empty($selectedMessage['guest_phone']) ? (string) $selectedMessage['guest_phone'] : '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                                <li>
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars(!empty($selectedMessage['guest_email']) ? (string) $selectedMessage['guest_email'] : '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                            </ul>
                        </section>

                        <section class="admin-inbox-info-block" aria-labelledby="booking-details-title">
                            <h3 id="booking-details-title">Booking Details</h3>
                            <div class="admin-inbox-detail-columns">
                                <ul>
                                    <li>
                                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                        <span><?php echo htmlspecialchars($formatMessageDate($selectedMessage['booking_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </li>
                                    <li>
                                        <i class="bi bi-clock" aria-hidden="true"></i>
                                        <span><?php echo htmlspecialchars($formatMessageTime($selectedMessage['start_time'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </li>
                                </ul>
                                <ul>
                                    <li>
                                        <i class="bi bi-people" aria-hidden="true"></i>
                                        <span><?php echo number_format((int) ($selectedMessage['party_size'] ?? $selectedMessage['booking_guests'] ?? 0)); ?> guests</span>
                                    </li>
                                    <li>
                                        <i class="bi bi-grid" aria-hidden="true"></i>
                                        <span><?php echo htmlspecialchars($formatInboxBookingType($selectedMessage), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </li>
                                </ul>
                            </div>
                        </section>
                    </div>

                    <section class="admin-inbox-availability" aria-labelledby="availability-title">
                        <h3 id="availability-title">Availability</h3>
                        <?php if (empty($areaAvailability)): ?>
                            <div class="admin-inbox-empty subtle">
                                <span>No area availability data — connect a booking to see options.</span>
                            </div>
                        <?php else: ?>
                            <div class="admin-inbox-availability-grid">
                                <?php foreach ($areaAvailability as $area): ?>
                                    <div class="admin-inbox-area-card tone-<?php echo htmlspecialchars($area['state'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="admin-inbox-area-card-head">
                                            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                                            <strong><?php echo htmlspecialchars($area['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </div>
                                        <span class="admin-inbox-area-card-meta"><?php echo htmlspecialchars($area['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if ($selectedBookingId > 0): ?>
                        <section class="admin-inbox-table-assign" aria-labelledby="assign-table-title">
                            <div class="admin-inbox-table-assign-head">
                                <h3 id="assign-table-title">Assign Table</h3>
                            </div>
                            <button type="button" class="admin-inbox-table-card" data-table-modal-open>
                                <span class="admin-inbox-table-card-icon">
                                    <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                                </span>
                                <span class="admin-inbox-table-card-copy">
                                    <strong><?php echo htmlspecialchars($selectedTableLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small>
                                        <?php echo number_format($tableAssignmentAvailableCount); ?>
                                        <?php echo $tableAssignmentAvailableCount === 1 ? 'table' : 'tables'; ?> selectable for this time
                                    </small>
                                </span>
                                <span class="admin-inbox-table-card-action">Open floor</span>
                            </button>
                        </section>
                    <?php endif; ?>

                    <div class="admin-inbox-messages-grid">
                        <section class="admin-inbox-info-block" aria-labelledby="guest-message-title">
                            <h3 id="guest-message-title">Guest Message</h3>
                            <div class="admin-inbox-message-bubble guest">
                                <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                                <p><?php echo nl2br(htmlspecialchars((string) ($selectedMessage['message'] ?? $selectedMessage['preview'] ?? 'No additional message from the guest.'), ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                        </section>

                        <section class="admin-inbox-info-block" aria-labelledby="menu-items-title">
                            <h3 id="menu-items-title">Selected Menu</h3>
                            <div class="admin-inbox-message-bubble menu">
                                <i class="bi bi-basket3" aria-hidden="true"></i>
                                <div class="admin-inbox-message-menu">
                                    <?php
                                    $menuItems = [];
                                    if (!empty($selectedMessage['menu_items'])) {
                                        $decodedMenuData = json_decode((string) $selectedMessage['menu_items'], true);
                                        if (is_array($decodedMenuData)) {
                                            $menuItems = $decodedMenuData;
                                        }
                                    }
                                    ?>

                                    <?php if (empty($menuItems)): ?>
                                        <p>No menu items selected.</p>
                                    <?php else: ?>
                                        <ul class="admin-inbox-menu-items">
                                            <?php foreach ($menuItems as $menuItem): ?>
                                                <?php
                                                $itemName = htmlspecialchars(trim((string) ($menuItem['name'] ?? 'Menu item')), ENT_QUOTES, 'UTF-8');
                                                $itemQty = max(0, (int) ($menuItem['qty'] ?? 0));
                                                $itemPriceRaw = trim((string) ($menuItem['price'] ?? ''));
                                                $itemPrice = $itemPriceRaw !== '' ? '$' . htmlspecialchars($itemPriceRaw, ENT_QUOTES, 'UTF-8') : 'Price unavailable';
                                                ?>
                                                <li>
                                                    <strong><?php echo $itemName; ?></strong>
                                                    <span><?php echo $itemQty; ?> × <?php echo $itemPrice; ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>

                        <section class="admin-inbox-info-block" aria-labelledby="staff-notes-title">
                            <h3 id="staff-notes-title">
                                <span>Staff Notes</span>
                                <span class="admin-inbox-notes-status" data-notes-status aria-live="polite"></span>
                            </h3>
                            <div class="admin-inbox-message-bubble notes">
                                <i class="bi bi-pin-angle" aria-hidden="true"></i>
                                <textarea
                                    rows="3"
                                    data-notes-input
                                    data-inbox-id="<?php echo (int) $selectedMessage['inbox_id']; ?>"
                                    data-folder="<?php echo htmlspecialchars($activeFolder, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Add an internal note for the team..."
                                ><?php echo htmlspecialchars((string) ($selectedMessage['staff_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </section>
                    </div>

                    <footer class="admin-inbox-detail-actions">
                        <form method="post" action="inbox-action.php" class="admin-inbox-action-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminActionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="inbox_id" value="<?php echo (int) $selectedMessage['inbox_id']; ?>">
                            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($activeFolder, ENT_QUOTES, 'UTF-8'); ?>">

                            <?php if (($selectedMessage['type'] ?? '') === 'guest_message' && $selectedBookingId <= 0): ?>
                                <button type="submit" name="action" value="contact" class="action-btn confirm">
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    Contact Guest
                                </button>
                                <button type="submit" name="action" value="archive" class="action-btn">
                                    <i class="bi bi-archive" aria-hidden="true"></i>
                                    Archive
                                </button>
                            <?php else: ?>
                                <button type="submit" name="action" value="confirm" class="action-btn confirm">
                                    <i class="bi bi-check2" aria-hidden="true"></i>
                                    Confirm
                                </button>
                                <button type="submit" name="action" value="contact" class="action-btn contact">
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    Contact Guest
                                </button>
                                <button type="submit" name="action" value="waitlist" class="action-btn waitlist">
                                    <i class="bi bi-people" aria-hidden="true"></i>
                                    Waitlist
                                </button>
                                <button type="submit" name="action" value="decline" class="action-btn decline" data-confirm="Decline this request?">
                                    <i class="bi bi-x" aria-hidden="true"></i>
                                    Decline
                                </button>
                            <?php endif; ?>
                        </form>
                    </footer>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php
$adminBookingCreateDefaultDate = $todayDate;
$adminBookingCreateMinDate = $todayDate;
$adminBookingCreateEndpoint = '../actions/create-booking.php';
include __DIR__ . '/../partials/admin-booking-create-modal.php';
?>

<?php if ($selectedBookingId > 0): ?>
    <div class="admin-inbox-table-modal" data-table-modal hidden>
        <div class="admin-inbox-table-modal-card" role="dialog" aria-modal="true" aria-labelledby="inbox-table-modal-title">
            <header class="admin-inbox-table-modal-head">
                <div>
                    <h2 id="inbox-table-modal-title">Select Table</h2>
                    <p>
                        <?php echo htmlspecialchars($formatMessageDate($selectedMessage['booking_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?>
                        at
                        <?php echo htmlspecialchars($formatMessageTime($selectedMessage['start_time'] ?? null), ENT_QUOTES, 'UTF-8'); ?>
                        for
                        <?php echo number_format((int) ($selectedMessage['party_size'] ?? $selectedMessage['booking_guests'] ?? 0)); ?>
                        guests
                    </p>
                </div>
                <button type="button" class="icon-btn admin-inbox-table-modal-close" data-table-modal-close aria-label="Close table selection">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>

            <form method="post" action="inbox-action.php" class="admin-inbox-table-form" data-table-assign-form>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($adminActionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="assign_table">
                <input type="hidden" name="inbox_id" value="<?php echo (int) $selectedMessage['inbox_id']; ?>">
                <input type="hidden" name="folder" value="<?php echo htmlspecialchars($activeFolder, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="table_id" value="<?php echo $selectedTableId > 0 ? (int) $selectedTableId : ''; ?>" data-table-selected-input>
                <span data-table-selected-fields hidden>
                    <?php foreach ($selectedTableIds as $selectedAssignedTableId): ?>
                        <input type="hidden" name="table_ids[]" value="<?php echo (int) $selectedAssignedTableId; ?>">
                    <?php endforeach; ?>
                </span>

                <div class="admin-inbox-table-modal-body">
                    <aside class="admin-inbox-table-modal-side" aria-label="Booking and selected table details">
                        <section class="admin-inbox-table-modal-section">
                            <h3>Booking Details</h3>
                            <dl class="admin-inbox-table-modal-details">
                                <div>
                                    <dt><i class="bi bi-person" aria-hidden="true"></i> Guest</dt>
                                    <dd><?php echo htmlspecialchars((string) ($selectedMessage['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></dd>
                                </div>
                                <div>
                                    <dt><i class="bi bi-calendar-event" aria-hidden="true"></i> Date</dt>
                                    <dd><?php echo htmlspecialchars($formatMessageDate($selectedMessage['booking_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></dd>
                                </div>
                                <div>
                                    <dt><i class="bi bi-clock" aria-hidden="true"></i> Time</dt>
                                    <dd><?php echo htmlspecialchars($formatMessageTime($selectedMessage['start_time'] ?? null), ENT_QUOTES, 'UTF-8'); ?></dd>
                                </div>
                                <div>
                                    <dt><i class="bi bi-people" aria-hidden="true"></i> Guests</dt>
                                    <dd><?php echo number_format((int) ($selectedMessage['party_size'] ?? $selectedMessage['booking_guests'] ?? 0)); ?></dd>
                                </div>
                                <div>
                                    <dt><i class="bi bi-grid" aria-hidden="true"></i> Type</dt>
                                    <dd><?php echo htmlspecialchars($formatInboxBookingType($selectedMessage), ENT_QUOTES, 'UTF-8'); ?></dd>
                                </div>
                            </dl>
                        </section>

                        <section class="admin-inbox-table-modal-section">
                            <div class="admin-inbox-table-modal-section-head">
                                <h3>Selected Tables</h3>
                                <span data-table-selected-count><?php echo number_format(count($selectedTableIds)); ?></span>
                            </div>
                            <div class="admin-inbox-table-modal-summary">
                                <span>Current selection</span>
                                <strong data-table-selected-label><?php echo htmlspecialchars($selectedTableLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <p>
                                <?php echo number_format($tableAssignmentAvailableCount); ?>
                                <?php echo $tableAssignmentAvailableCount === 1 ? 'table is' : 'tables are'; ?> selectable for this time.
                            </p>
                        </section>
                    </aside>

                    <?php if (empty($tableAssignmentFloorTables)): ?>
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
                                            <?php foreach ($tableAssignmentFloorZones as $zone): ?>
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
                                                    <i class="fa-solid <?php echo htmlspecialchars((string) ($zone['icon'] ?? 'fa-location-dot'), ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                                    <span><?php echo htmlspecialchars((string) $zone['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </button>
                                            <?php endforeach; ?>

                                            <?php foreach ($tableAssignmentFloorTables as $tableOption): ?>
                                                <?php
                                                $optionTableId = (int) ($tableOption['table_id'] ?? 0);
                                                if ($optionTableId < 1 || $tableOption['layout_x'] === null || $tableOption['layout_y'] === null) {
                                                    continue;
                                                }

                                                $tableNumber = (string) ($tableOption['table_number'] ?? '');
                                                $tableDisplayNumber = preg_replace('/^T/i', '', $tableNumber);
                                                $tableCapacity = (int) ($tableOption['capacity'] ?? 0);
                                                $tableBookings = $tableOption['bookings'] ?? [];
                                                $firstBooking = $tableBookings[0] ?? null;
                                                $isSelectedTable = !empty($tableOption['is_selected']);
                                                $isBusyTable = !empty($tableOption['is_busy']);
                                                $isUnreservableTable = !empty($tableOption['is_unreservable']);
                                                $isSelectableTable = !empty($tableOption['is_selectable']);
                                                $optionLabel = 'Table ' . $tableNumber . ' - ' . (string) ($tableOption['area_name'] ?? 'Dining Room');
                                                $bookingTooltip = 'Available';
                                                $bookingName = '-';
                                                $bookingTime = '-';
                                                $bookingNote = '';
                                                $bookingGuests = 0;

                                                if ($firstBooking) {
                                                    $bookingName = (string) ($firstBooking['customer_name'] ?? 'Guest');
                                                    $bookingNote = trim((string) ($firstBooking['special_request'] ?? ''));
                                                    $bookingGuests = (int) ($firstBooking['number_of_guests'] ?? 0);
                                                    $bookingTooltip = $bookingName;
                                                    if (!empty($firstBooking['start_time'])) {
                                                        $bookingTime = date('g:i a', strtotime((string) $firstBooking['start_time']));
                                                        $bookingTooltip .= ' at ' . date('g:i A', strtotime((string) $firstBooking['start_time']));
                                                    }
                                                } elseif ($isUnreservableTable) {
                                                    $bookingTooltip = 'Unavailable';
                                                }
                                                ?>
                                                <button
                                                    type="button"
                                                    class="home-floor-table tone-<?php echo htmlspecialchars((string) ($tableOption['tone'] ?? 'blue'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($tableOption['is_occupied']) ? ' is-occupied' : ''; ?><?php echo $isUnreservableTable ? ' is-unreservable' : ''; ?><?php echo $isSelectedTable ? ' is-selected' : ''; ?><?php echo $isBusyTable ? ' is-busy' : ''; ?>"
                                                    title="Table <?php echo htmlspecialchars($tableNumber, ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($bookingTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                                                    style="left: <?php echo (int) $tableOption['layout_x']; ?>px; top: <?php echo (int) $tableOption['layout_y']; ?>px;"
                                                    data-booking-edit-floor-table="<?php echo $optionTableId; ?>"
                                                    data-table-choice
                                                    data-table-id="<?php echo $optionTableId; ?>"
                                                    data-table-area-id="<?php echo (int) ($tableOption['area_id'] ?? 0); ?>"
                                                    data-table-label="<?php echo htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                                    aria-pressed="<?php echo $isSelectedTable ? 'true' : 'false'; ?>"
                                                    <?php echo !$isSelectableTable ? 'disabled aria-disabled="true"' : ''; ?>
                                                >
                                                    <span class="home-floor-table-shell">
                                                        <span class="home-floor-table-card">
                                                            <?php if ($firstBooking): ?>
                                                                <span class="home-floor-card-time"><?php echo htmlspecialchars($bookingTime, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <span class="home-floor-card-main"><?php echo htmlspecialchars($bookingName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <?php if ($bookingNote !== ''): ?>
                                                                    <span class="home-floor-card-note"><?php echo htmlspecialchars($bookingNote, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <?php endif; ?>
                                                                <span class="home-floor-card-corner"><i class="fa-solid fa-user-group" aria-hidden="true"></i><?php echo number_format($bookingGuests); ?></span>
                                                            <?php else: ?>
                                                                <span class="home-floor-card-number"><?php echo htmlspecialchars($tableDisplayNumber, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <span class="home-floor-card-corner"><i class="fa-solid fa-user-group" aria-hidden="true"></i><?php echo number_format($tableCapacity); ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <?php if (count($tableBookings) > 1): ?>
                                                            <span class="home-floor-booking-dot"><?php echo number_format(count($tableBookings)); ?></span>
                                                        <?php endif; ?>
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
                    <button type="button" class="action-btn" data-table-modal-close>Cancel</button>
                    <button type="submit" class="action-btn confirm" data-table-submit>
                        <i class="bi bi-check2" aria-hidden="true"></i>
                        <span data-table-submit-text><?php echo !empty($selectedTableIds) ? 'Update Tables' : 'Assign Table'; ?></span>
                    </button>
                </footer>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($flashMessage): ?>
    <div class="admin-toast-container" aria-live="polite" aria-atomic="true">
        <div class="admin-toast" role="status" data-toast data-auto-dismiss="4000">
            <span class="admin-toast-icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
            <span class="admin-toast-message"><?php echo htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" class="admin-toast-close" aria-label="Dismiss notification" data-toast-close>
                <i class="bi bi-x" aria-hidden="true"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<script>
(() => {
    const notesInput = document.querySelector('[data-notes-input]');
    const statusEl = document.querySelector('[data-notes-status]');
    if (!notesInput) return;

    let savedValue = notesInput.value;
    let saving = false;
    let pendingValue = null;
    let statusTimer = 0;

    const setStatus = (text, tone = '') => {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.dataset.tone = tone;
        if (statusTimer) clearTimeout(statusTimer);
        if (text && tone !== 'saving') {
            statusTimer = window.setTimeout(() => {
                statusEl.textContent = '';
                statusEl.dataset.tone = '';
            }, 1800);
        }
    };

    const save = async (value) => {
        if (saving) {
            pendingValue = value;
            return;
        }
        saving = true;
        setStatus('Saving…', 'saving');

        const body = new URLSearchParams({
            action: 'save_notes',
            inbox_id: notesInput.dataset.inboxId || '',
            folder:   notesInput.dataset.folder   || 'requests',
            staff_notes: value,
        });

        try {
            const response = await fetch('inbox-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                    'X-Requested-With': 'fetch',
                },
                body,
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('save failed');
            savedValue = value;
            setStatus('Saved', 'saved');
        } catch (err) {
            setStatus('Could not save', 'error');
        } finally {
            saving = false;
            if (pendingValue !== null && pendingValue !== savedValue) {
                const next = pendingValue;
                pendingValue = null;
                save(next);
            } else {
                pendingValue = null;
            }
        }
    };

    notesInput.addEventListener('blur', () => {
        const value = notesInput.value;
        if (value === savedValue) return;
        save(value);
    });

    notesInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            notesInput.value = savedValue;
            notesInput.blur();
        }
    });
})();

(() => {
    const modal = document.querySelector('[data-table-modal]');
    const openButton = document.querySelector('[data-table-modal-open]');
    if (!modal || !openButton) return;

    const form = modal.querySelector('[data-table-assign-form]');
    const selectedInput = modal.querySelector('[data-table-selected-input]');
    const selectedFields = modal.querySelector('[data-table-selected-fields]');
    const selectedLabel = modal.querySelector('[data-table-selected-label]');
    const selectedCount = modal.querySelector('[data-table-selected-count]');
    const submitText = modal.querySelector('[data-table-submit-text]');
    const choices = Array.from(modal.querySelectorAll('[data-table-choice]'));
    const areaChoices = Array.from(modal.querySelectorAll('[data-table-area-choice]'));
    const closeButtons = modal.querySelectorAll('[data-table-modal-close]');
    const clearButton = modal.querySelector('[data-table-clear]');
    const floorCanvas = modal.querySelector('.home-floor-canvas');
    const floorStage = modal.querySelector('.home-floor-stage');
    const floorViewport = modal.querySelector('.home-floor-viewport');

    const updateFloorLayoutScale = () => {
        if (!floorCanvas || !floorViewport) return;
        const layoutWidth = 860;
        const layoutHeight = 600;
        const viewportRect = floorViewport.getBoundingClientRect();
        const availableWidth = Math.max(0, viewportRect.width - 2);
        const availableHeight = Math.max(0, viewportRect.height - 2);
        const rawScale = Math.min(1, availableWidth / layoutWidth, availableHeight / layoutHeight);
        const scale = Number.isFinite(rawScale) && rawScale > 0 ? rawScale : 1;

        floorCanvas.style.width = `${layoutWidth}px`;
        floorCanvas.style.height = `${layoutHeight}px`;
        floorCanvas.style.transform = scale < 1 ? `scale(${scale})` : '';
        floorViewport.style.minHeight = '';

        if (floorStage) {
            floorStage.style.width = `${Math.ceil(layoutWidth * scale)}px`;
            floorStage.style.height = `${Math.ceil(layoutHeight * scale)}px`;
        }
    };

    const getSelectedChoices = () => choices.filter((choice) => choice.classList.contains('is-selected') && !choice.disabled);
    const getSelectableAreaTables = (areaId) => choices.filter((choice) => choice.dataset.tableAreaId === areaId && !choice.disabled);
    const getAreaLabel = (areaButton) => (areaButton.dataset.tableAreaLabel || areaButton.textContent || '').trim();

    const getSelectionSummaryLabels = (selectedChoices) => {
        const selectedSet = new Set(selectedChoices);
        const coveredChoices = new Set();
        const fullAreaLabels = [];
        const summaryLabels = [];

        areaChoices.forEach((areaButton) => {
            const areaTables = getSelectableAreaTables(areaButton.dataset.tableAreaId || '');
            if (areaTables.length === 0 || !areaTables.every((choice) => selectedSet.has(choice))) {
                return;
            }

            areaTables.forEach((choice) => coveredChoices.add(choice));
            const areaLabel = getAreaLabel(areaButton);
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
            if (coveredChoices.has(choice)) {
                return;
            }

            const tableLabel = choice.dataset.tableLabel || '';
            if (tableLabel !== '') {
                summaryLabels.push(tableLabel);
            }
        });

        return summaryLabels;
    };

    const updateAreaSelectionState = () => {
        areaChoices.forEach((areaButton) => {
            const areaId = areaButton.dataset.tableAreaId || '';
            const areaTables = getSelectableAreaTables(areaId);
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

    const updateSelectedTables = () => {
        const selectedChoices = getSelectedChoices();
        const selectedIds = selectedChoices
            .map((choice) => choice.dataset.tableId || '')
            .filter(Boolean);
        const selectedLabels = selectedChoices
            .map((choice) => choice.dataset.tableLabel || '')
            .filter(Boolean);
        const summaryLabels = getSelectionSummaryLabels(selectedChoices);

        if (selectedInput) {
            selectedInput.value = selectedIds[0] || '';
        }

        if (selectedFields) {
            selectedFields.replaceChildren();
            selectedIds.forEach((tableId) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'table_ids[]';
                input.value = tableId;
                selectedFields.appendChild(input);
            });
        }

        if (selectedLabel) {
            selectedLabel.textContent = summaryLabels.length ? summaryLabels.join(', ') : 'No table assigned';
        }

        if (selectedCount) {
            selectedCount.textContent = selectedLabels.length.toString();
        }

        if (submitText) {
            submitText.textContent = selectedLabels.length > 1
                ? `Assign ${selectedLabels.length} Tables`
                : selectedLabels.length === 1
                    ? 'Assign ' + selectedLabels[0].replace('Table ', 'T')
                    : 'Assign Table';
        }

        updateAreaSelectionState();
    };

    const toggleSelectedTable = (button) => {
        if (!button || button.disabled) return;
        const isSelected = button.classList.toggle('is-selected');
        button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        updateSelectedTables();
    };

    const openModal = () => {
        modal.hidden = false;
        document.body.classList.add('admin-inbox-modal-open');
        window.requestAnimationFrame(updateFloorLayoutScale);
        const selected = modal.querySelector('[data-table-choice].is-selected:not(:disabled)');
        window.setTimeout(() => (selected || modal.querySelector('[data-table-modal-close]'))?.focus(), 0);
    };

    const closeModal = () => {
        modal.hidden = true;
        document.body.classList.remove('admin-inbox-modal-open');
        openButton.focus();
    };

    openButton.addEventListener('click', openModal);
    window.addEventListener('resize', updateFloorLayoutScale);
    closeButtons.forEach((button) => button.addEventListener('click', closeModal));

    choices.forEach((button) => {
        button.addEventListener('click', () => toggleSelectedTable(button));
    });

    areaChoices.forEach((areaButton) => {
        areaButton.addEventListener('click', () => {
            const areaTables = getSelectableAreaTables(areaButton.dataset.tableAreaId || '');
            if (areaTables.length === 0) return;

            const shouldSelect = !areaTables.every((choice) => choice.classList.contains('is-selected'));
            areaTables.forEach((choice) => {
                choice.classList.toggle('is-selected', shouldSelect);
                choice.setAttribute('aria-pressed', shouldSelect ? 'true' : 'false');
            });
            updateSelectedTables();
        });
    });

    clearButton?.addEventListener('click', () => {
        choices.forEach((choice) => {
            choice.classList.remove('is-selected');
            choice.setAttribute('aria-pressed', 'false');
        });
        updateSelectedTables();
        if (form?.requestSubmit) {
            form.requestSubmit();
        } else {
            form?.submit();
        }
    });

    updateSelectedTables();

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();

(() => {
    document.querySelectorAll('form[action="inbox-action.php"] button[data-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (!window.confirm(button.dataset.confirm || 'Are you sure?')) {
                event.preventDefault();
            }
        });
    });
})();

(() => {
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
})();
</script>
</body>
</html>
