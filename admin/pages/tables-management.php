<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureTableAreasSchema($pdo);
ensureBookingTableAssignmentsTable($pdo);

$normalizeAreaName = static function (string $value): string {
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
};

$displayAreaName = static function (string $name) use ($normalizeAreaName): string {
    $normalized = $normalizeAreaName($name);
    $aliases = [
        'osf' => 'OSF Patio',
    ];

    return $aliases[$normalized] ?? $name;
};

$resolveZoneKey = static function (string $name) use ($normalizeAreaName): string {
    $normalized = $normalizeAreaName($name);

    if (in_array($normalized, ['osf', 'osfpatio', 'outsidepatio'], true)) {
        return 'osf';
    }

    if (in_array($normalized, ['kookaburra', 'kookabura'], true)) {
        return 'kookaburra';
    }

    if (in_array($normalized, ['mainbar', 'bararea', 'bar'], true)) {
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

    if (in_array($normalized, ['maindining', 'dining'], true)) {
        return 'main-bar';
    }

    return 'osf';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_GET['action'] ?? '') === 'save_layout')) {
    header('Content-Type: application/json');
    requireValidCsrfToken('admin_actions', ['json' => true]);

    $data = readJsonRequestPayload(['json' => true]);
    $payloadTables = isset($data['tables']) && is_array($data['tables']) ? $data['tables'] : [];
    $payloadAreas = isset($data['areas']) && is_array($data['areas']) ? $data['areas'] : [];

    if (empty($payloadTables)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No tables were supplied']);
        exit();
    }

    $normalizedTables = [];
    $normalizedAreas = [];
    $tableKeys = [];
    $areaIds = [];

    foreach ($payloadTables as $tableRow) {
        $tableId = (int) ($tableRow['table_id'] ?? 0);
        $tableNumber = trim((string) ($tableRow['table_number'] ?? ''));
        $capacity = (int) ($tableRow['capacity'] ?? 0);
        $areaId = (int) ($tableRow['area_id'] ?? 0);
        $sortOrder = (int) ($tableRow['sort_order'] ?? 0);
        $reservable = !empty($tableRow['reservable']) ? 1 : 0;
        $layoutX = isset($tableRow['layout_x']) && $tableRow['layout_x'] !== '' ? (int) $tableRow['layout_x'] : null;
        $layoutY = isset($tableRow['layout_y']) && $tableRow['layout_y'] !== '' ? (int) $tableRow['layout_y'] : null;

        if ($tableId < 1 || $tableNumber === '' || $capacity < 1 || $areaId < 1 || $sortOrder < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Each table must include a valid id, table number, capacity, area, and sort order']);
            exit();
        }

        $compoundKey = strtolower($tableNumber) . '::' . $areaId;
        if (isset($tableKeys[$compoundKey])) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Duplicate table numbers were found in the same area']);
            exit();
        }

        $tableKeys[$compoundKey] = true;
        $areaIds[$areaId] = true;
        $normalizedTables[] = [
            'table_id' => $tableId,
            'table_number' => $tableNumber,
            'capacity' => $capacity,
            'area_id' => $areaId,
            'sort_order' => $sortOrder,
            'reservable' => $reservable,
            'layout_x' => $layoutX,
            'layout_y' => $layoutY,
        ];
    }

    foreach ($payloadAreas as $areaRow) {
        $areaId = (int) ($areaRow['area_id'] ?? 0);
        $layoutX = isset($areaRow['layout_x']) && $areaRow['layout_x'] !== '' ? (int) $areaRow['layout_x'] : null;
        $layoutY = isset($areaRow['layout_y']) && $areaRow['layout_y'] !== '' ? (int) $areaRow['layout_y'] : null;
        $layoutWidth = isset($areaRow['layout_width']) && $areaRow['layout_width'] !== '' ? (int) $areaRow['layout_width'] : null;
        $layoutHeight = isset($areaRow['layout_height']) && $areaRow['layout_height'] !== '' ? (int) $areaRow['layout_height'] : null;
        $labelLayoutX = isset($areaRow['label_layout_x']) && $areaRow['label_layout_x'] !== '' ? (int) $areaRow['label_layout_x'] : null;
        $labelLayoutY = isset($areaRow['label_layout_y']) && $areaRow['label_layout_y'] !== '' ? (int) $areaRow['label_layout_y'] : null;

        if ($areaId < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Each area must include a valid id']);
            exit();
        }

        if (($layoutWidth !== null && $layoutWidth < 60) || ($layoutHeight !== null && $layoutHeight < 60)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Area dimensions must be at least 60 pixels']);
            exit();
        }

        $areaIds[$areaId] = true;
        $normalizedAreas[$areaId] = [
            'area_id' => $areaId,
            'layout_x' => $layoutX,
            'layout_y' => $layoutY,
            'layout_width' => $layoutWidth,
            'layout_height' => $layoutHeight,
            'label_layout_x' => $labelLayoutX,
            'label_layout_y' => $labelLayoutY,
        ];
    }

    try {
        $tableIds = array_map(static function (array $tableRow): int {
            return (int) $tableRow['table_id'];
        }, $normalizedTables);

        $tablePlaceholders = implode(',', array_fill(0, count($tableIds), '?'));
        $existingTableStmt = $pdo->prepare("SELECT table_id FROM restaurant_tables WHERE table_id IN ($tablePlaceholders)");
        $existingTableStmt->execute($tableIds);
        $existingTableIds = array_map('intval', $existingTableStmt->fetchAll(PDO::FETCH_COLUMN));
        sort($existingTableIds);

        $expectedTableIds = $tableIds;
        sort($expectedTableIds);

        if ($existingTableIds !== $expectedTableIds) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'One or more tables no longer exist']);
            exit();
        }

        $areaIdList = array_keys($areaIds);
        $areaPlaceholders = implode(',', array_fill(0, count($areaIdList), '?'));
        $areaStmt = $pdo->prepare("SELECT area_id FROM table_areas WHERE is_active = 1 AND area_id IN ($areaPlaceholders)");
        $areaStmt->execute($areaIdList);
        $existingAreaIds = array_map('intval', $areaStmt->fetchAll(PDO::FETCH_COLUMN));
        sort($existingAreaIds);
        sort($areaIdList);

        if ($existingAreaIds !== $areaIdList) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'One or more selected areas are invalid']);
            exit();
        }

        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare(
            "UPDATE restaurant_tables
             SET table_number = ?, capacity = ?, area_id = ?, sort_order = ?, reservable = ?, layout_x = ?, layout_y = ?
             WHERE table_id = ?"
        );

        $updateAreaLayoutStmt = $pdo->prepare(
            "UPDATE table_areas
                             SET layout_x = ?, layout_y = ?, layout_width = ?, layout_height = ?, label_layout_x = ?, label_layout_y = ?
             WHERE area_id = ?"
        );

        foreach ($normalizedTables as $tableRow) {
            $duplicateStmt = $pdo->prepare("SELECT table_id FROM restaurant_tables WHERE table_number = ? AND area_id = ? AND table_id != ? LIMIT 1");
            $duplicateStmt->execute([$tableRow['table_number'], $tableRow['area_id'], $tableRow['table_id']]);

            if ($duplicateStmt->fetchColumn()) {
                throw new RuntimeException('Table number already exists in one of the selected areas');
            }

            $updateStmt->execute([
                $tableRow['table_number'],
                $tableRow['capacity'],
                $tableRow['area_id'],
                $tableRow['sort_order'],
                $tableRow['reservable'],
                $tableRow['layout_x'],
                $tableRow['layout_y'],
                $tableRow['table_id'],
            ]);
        }

        foreach ($normalizedAreas as $areaRow) {
            $updateAreaLayoutStmt->execute([
                $areaRow['layout_x'],
                $areaRow['layout_y'],
                $areaRow['layout_width'],
                $areaRow['layout_height'],
                $areaRow['label_layout_x'],
                $areaRow['label_layout_y'],
                $areaRow['area_id'],
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        error_log('Save table layout failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Unable to save layout. Please try again.']);
    }

    exit();
}

$tablesStmt = $pdo->query(
    "SELECT rt.table_id,
            rt.table_number,
            rt.capacity,
            rt.area_id,
            rt.sort_order,
            COALESCE(rt.reservable, 1) AS reservable,
            rt.layout_x,
            rt.layout_y,
            COALESCE(ta.name, 'Unassigned') AS area_name,
            COALESCE(ta.display_order, 9999) AS area_display_order,
            ta.table_number_start,
            ta.table_number_end
     FROM restaurant_tables rt
     LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
     ORDER BY COALESCE(ta.display_order, 9999) ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC"
);
$tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$areasStmt = $pdo->query(
    "SELECT area_id, name, display_order, table_number_start, table_number_end, layout_x, layout_y, layout_width, layout_height, label_layout_x, label_layout_y, is_active
     FROM table_areas
     WHERE is_active = 1
     ORDER BY display_order ASC, name ASC"
);
$areas = $areasStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$tablesByArea = [];
$totalSeats = 0;
$unassignedTables = 0;

foreach ($tables as $table) {
    $areaId = (int) ($table['area_id'] ?? 0);
    if (!isset($tablesByArea[$areaId])) {
        $tablesByArea[$areaId] = [];
    }
    $tablesByArea[$areaId][] = $table;
    $totalSeats += (int) ($table['capacity'] ?? 0);
    if ($table['layout_x'] === null || $table['layout_y'] === null) {
        $unassignedTables++;
    }
}

$areaPayload = [];
foreach ($areas as $area) {
    $areaId = (int) $area['area_id'];
    $areaTables = $tablesByArea[$areaId] ?? [];
    $tableCount = count($areaTables);
    $totalAreaSeats = array_sum(array_map(static function (array $row): int {
        return (int) ($row['capacity'] ?? 0);
    }, $areaTables));

    $rangeLabel = 'Manual layout';
    if ($area['table_number_start'] !== null && $area['table_number_end'] !== null) {
        $rangeLabel = 'T' . (int) $area['table_number_start'] . ' - T' . (int) $area['table_number_end'];
    } elseif ($tableCount > 0) {
        $firstTableRow = reset($areaTables);
        $lastTableRow = end($areaTables);
        $firstTable = (string) ($firstTableRow['table_number'] ?? '');
        $lastTable = (string) ($lastTableRow['table_number'] ?? '');
        if ($firstTable !== '') {
            $rangeLabel = 'T' . $firstTable . ($firstTable !== $lastTable ? ' - T' . $lastTable : '');
        }
    }

    $areaPayload[] = [
        'area_id' => $areaId,
        'name' => $area['name'],
        'display_name' => $displayAreaName((string) $area['name']),
        'display_order' => (int) $area['display_order'],
        'table_number_start' => $area['table_number_start'] !== null ? (int) $area['table_number_start'] : null,
        'table_number_end' => $area['table_number_end'] !== null ? (int) $area['table_number_end'] : null,
        'layout_x' => $area['layout_x'] !== null ? (int) $area['layout_x'] : null,
        'layout_y' => $area['layout_y'] !== null ? (int) $area['layout_y'] : null,
        'layout_width' => $area['layout_width'] !== null ? (int) $area['layout_width'] : null,
        'layout_height' => $area['layout_height'] !== null ? (int) $area['layout_height'] : null,
        'label_layout_x' => $area['label_layout_x'] !== null ? (int) $area['label_layout_x'] : null,
        'label_layout_y' => $area['label_layout_y'] !== null ? (int) $area['label_layout_y'] : null,
        'table_count' => $tableCount,
        'total_seats' => $totalAreaSeats,
        'range_label' => $rangeLabel,
        'zone_key' => $resolveZoneKey((string) $area['name']),
    ];
}

$tablesPayload = array_map(static function (array $row): array {
    return [
        'table_id' => (int) $row['table_id'],
        'table_number' => (string) $row['table_number'],
        'capacity' => (int) $row['capacity'],
        'area_id' => (int) $row['area_id'],
        'sort_order' => (int) $row['sort_order'],
        'reservable' => (int) $row['reservable'],
        'layout_x' => $row['layout_x'] !== null ? (int) $row['layout_x'] : null,
        'layout_y' => $row['layout_y'] !== null ? (int) $row['layout_y'] : null,
        'area_name' => (string) $row['area_name'],
        'area_display_order' => (int) $row['area_display_order'],
    ];
}, $tables);

$pagePayload = [
    'areas' => $areaPayload,
    'tables' => $tablesPayload,
    'metrics' => [
        'total_tables' => count($tablesPayload),
        'total_seats' => $totalSeats,
        'active_areas' => count($areaPayload),
        'unassigned_tables' => $unassignedTables,
    ],
    'admin_name' => $_SESSION['name'] ?? 'Admin',
];

$adminPageTitle = 'Table Operations';
$adminPageIcon = 'fa-chair';
$adminNotificationCount = $unassignedTables;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'tables';
$adminSidebarPathPrefix = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php include __DIR__ . '/../partials/admin-modernize.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/pages/admin-tables.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="visual-shell">
        <?php require __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <div class="main-content">
            <main class="visual-main">
                <div class="page-stack admin-ops">
                <header class="admin-page-heading page-header">
                    <div>
                        <p class="admin-page-kicker">Floor Control</p>
                        <h1 class="admin-page-title page-title">Table Operations</h1>
                        <p class="admin-page-copy page-subtitle">Maintain venue areas, reservable capacity, and the interactive seating map used by staff.</p>
                    </div>
                    <div class="admin-actions header-actions">
                        <button class="primary-btn" id="headerAddTable" type="button">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            <span>Add Table</span>
                        </button>
                    </div>
                </header>

                <section class="admin-command-bar" id="metricsGrid"></section>

                <section class="admin-panel panel">
                    <div class="admin-panel-header section-head">
                        <div>
                            <h2 class="admin-panel-title section-title">Area Overview</h2>
                            <p class="admin-panel-copy section-note">Review and manage venue areas.</p>
                        </div>
                        <button class="secondary-btn" id="addAreaButton" type="button">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            <span>Add Area</span>
                        </button>
                    </div>
                    <div class="area-overview-body">
                        <div class="area-overview-grid" id="areaOverviewGrid"></div>
                    </div>
                </section>

                <section class="editor-grid">
                    <aside class="panel tools-panel">
                        <div class="panel-body tools-stack">
                            <div>
                                <h2 class="section-title">Layout Tools</h2>
                                <p class="section-note">Drag tables onto the floor plan and tune each section.</p>
                            </div>
                            <button class="button button-accent" id="toolsAddTable" type="button"><i class="fa-solid fa-plus"></i> Add Table</button>
                            <div>
                                <div class="section-title" style="font-size:16px; margin-bottom: 10px;">Sections</div>
                                <div class="section-list" id="sectionList"></div>
                            </div>
                        </div>
                    </aside>

                    <section class="panel canvas-panel">
                        <div class="canvas-toolbar">
                            <div>
                                <h2 class="section-title">Visual Floor Plan</h2>
                                <p class="section-note">Interactive seating map.</p>
                            </div>
                            <div class="canvas-toolbar-actions">
                                <div class="toolbar-group zoom-group">
                                    <button class="mini-button" id="zoomOutButton" type="button"><i class="fa-solid fa-minus"></i></button>
                                    <button class="mini-button" id="resetViewButton" type="button"><i class="fa-solid fa-expand"></i></button>
                                    <button class="mini-button" id="zoomInButton" type="button"><i class="fa-solid fa-plus"></i></button>
                                </div>
                                <div class="toolbar-group edit-group">
                                    <button class="button button-ghost toolbar-action" id="enterEditModeButton" type="button"><i class="fa-solid fa-pen"></i> Edit Layout</button>
                                    <button class="button button-primary toolbar-action is-hidden" id="saveEditLayoutButton" type="button"><i class="fa-regular fa-floppy-disk"></i> Save Layout</button>
                                    <button class="button button-ghost toolbar-action is-hidden" id="cancelEditModeButton" type="button"><i class="fa-solid fa-xmark"></i> Cancel</button>
                                </div>
                            </div>
                        </div>
                        <div class="canvas-stage">
                            <div class="canvas-frame" id="canvasFrame">
                                <div class="canvas-overlay-right">
                                    <button class="overlay-chip" id="zoomInFloating" type="button" title="Zoom in"><i class="fa-solid fa-plus"></i></button>
                                    <button class="overlay-chip" id="resetViewFloating" type="button" title="Fit view"><i class="fa-solid fa-expand"></i></button>
                                    <button class="overlay-chip" id="zoomOutFloating" type="button" title="Zoom out"><i class="fa-solid fa-minus"></i></button>
                                </div>
                                <div class="canvas-surface" id="canvasSurface"></div>
                            </div>
                        </div>
                    </section>

                    <aside class="panel details-panel" id="detailsPanel"></aside>
                </section>

                <section class="panel inventory-panel">
                    <div class="section-head">
                        <div>
                            <h2 class="section-title">Table Inventory</h2>
                            <p class="section-note">View and manage all tables in your venue.</p>
                        </div>
                    </div>
                    <div class="inventory-toolbar">
                        <div class="search-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input id="inventorySearch" type="search" placeholder="Search tables...">
                        </div>
                        <div class="inventory-toolbar-right">
                            <div class="filter-wrap">
                                <i class="fa-solid fa-filter"></i>
                                <select id="inventoryFilter"></select>
                            </div>
                            <button class="button button-ghost" id="exportInventoryButton" type="button"><i class="fa-solid fa-arrow-up-right-from-square"></i> Export</button>
                        </div>
                    </div>
                    <div class="inventory-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Table ID</th>
                                    <th>Capacity</th>
                                    <th>Area</th>
                                    <th>Reservable</th>
                                    <th>Position (x, y)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="inventoryBody"></tbody>
                        </table>
                    </div>
                    <div class="inventory-footer">
                        <div id="inventorySummary">Showing 0 tables</div>
                        <div class="pagination" id="pagination"></div>
                    </div>
                </section>
                </div>
            </main>
        </div>
    </div>

    <div class="modal-backdrop" id="tableModal">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h3 class="section-title" id="tableModalTitle" style="font-size: 22px; margin: 0;">Add Table</h3>
                    <p class="section-note" style="margin-top: 6px;">Create a new table and place it directly on the layout.</p>
                </div>
                <button class="details-close" type="button" data-close-modal="tableModal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="tableModalForm">
                <div class="modal-body">
                    <div class="field">
                        <label for="modalTableNumber">Table Name</label>
                        <input id="modalTableNumber" name="table_number" type="text" placeholder="T12" required>
                    </div>
                    <div class="field">
                        <label for="modalTableCapacity">Capacity</label>
                        <input id="modalTableCapacity" name="capacity" type="number" min="1" value="4" required>
                    </div>
                    <div class="field">
                        <label for="modalTableArea">Area</label>
                        <select id="modalTableArea" name="area_id"></select>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div style="font-weight: 800;">Reservable</div>
                            <div class="section-note" style="margin: 4px 0 0;">Allow this table to be assigned to bookings.</div>
                        </div>
                        <label class="switch">
                            <input id="modalTableReservable" type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="button button-ghost" type="button" data-close-modal="tableModal">Cancel</button>
                    <button class="button button-primary" type="submit">Create Table</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="areaModal">
        <div class="modal-card">
            <div class="modal-header">
                <div>
                    <h3 class="section-title" id="areaModalTitle" style="font-size: 22px; margin: 0;">Manage Area</h3>
                    <p class="section-note" style="margin-top: 6px;">Create or update a seating section and its numbered range.</p>
                </div>
                <button class="details-close" type="button" data-close-modal="areaModal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="areaModalForm">
                <div class="modal-body">
                    <input type="hidden" id="modalAreaId">
                    <div class="field">
                        <label for="modalAreaName">Area Name</label>
                        <input id="modalAreaName" type="text" placeholder="OSF Patio" required>
                    </div>
                    <div class="field-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                        <div class="field">
                            <label for="modalAreaStart">Table Start</label>
                            <input id="modalAreaStart" type="number" min="1" placeholder="1">
                        </div>
                        <div class="field">
                            <label for="modalAreaEnd">Table End</label>
                            <input id="modalAreaEnd" type="number" min="1" placeholder="10">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="justify-content: space-between;">
                    <button class="button button-danger" id="deleteAreaButton" type="button" style="visibility: hidden;">Delete Area</button>
                    <div style="display:flex; gap:12px;">
                        <button class="button button-ghost" type="button" data-close-modal="areaModal">Cancel</button>
                        <button class="button button-primary" type="submit">Save Area</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        const initialData = <?php echo json_encode($pagePayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const BASE_CANVAS_WIDTH = 860;
        const BASE_CANVAS_HEIGHT = 600;
        const VIEWPORT_PADDING = 26;
        const MIN_VIEW_SCALE = 0.58;
        const MAX_VIEW_SCALE = 2.4;
        const MIN_AREA_WIDTH = 84;
        const MIN_AREA_HEIGHT = 70;
        const TABLE_PADDING_X = 24;
        const TABLE_PADDING_Y = 22;
        const TABLE_DRAG_EDGE_PADDING = 40;
        const TABLE_DEFAULT_OFFSET = 34;
        const TABLE_DEFAULT_COLUMNS = 2;
        const TABLE_DEFAULT_GUTTER_X = 70;
        const TABLE_DEFAULT_GUTTER_Y = 68;
        const TABLE_OSF_COLUMNS = 4;
        const TABLE_OSF_GUTTER_X = 76;
        const TABLE_OSF_GUTTER_Y = 60;
        const AREA_LABEL_CANVAS_PADDING = 10;
        const AREA_CANVAS_PADDING = 16;

        const zoneBlueprints = [
            { key: 'stables', label: 'Stables', tone: 'amber', icon: 'fa-horse', x: 148, y: 12, width: 274, height: 150 },
            { key: 'kookaburra', label: 'Kookaburra', tone: 'green', icon: 'fa-leaf', x: 24, y: 52, width: 104, height: 350 },
            { key: 'wisteria', label: 'Wisteria', tone: 'pink', icon: 'fa-seedling', x: 532, y: 12, width: 292, height: 242 },
            { key: 'schumack', label: 'Schumack', tone: 'blue', icon: 'fa-anchor', x: 532, y: 272, width: 294, height: 128 },
            { key: 'main-bar', label: 'Main Bar', tone: 'lavender', icon: 'fa-martini-glass-citrus', x: 142, y: 186, width: 372, height: 216 },
            { key: 'osf', label: 'OSF', tone: 'mocha', icon: 'fa-tree', x: 20, y: 416, width: 820, height: 160 }
        ];

        const state = {
            areas: structuredClone(initialData.areas || []),
            tables: structuredClone(initialData.tables || []),
            selectedTableId: null,
            search: '',
            filterAreaId: 'all',
            currentPage: 1,
            perPage: 5,
            zoom: 1,
            panX: 0,
            panY: 0,
            dragging: null,
            modalAreaId: null,
            selectedAreaId: null,
            justManipulatedAreaId: null,
            isEditMode: false,
            layoutSnapshot: null,
        };

        const preferredAreaOrder = ['Stables', 'Kookaburra', 'Wisteria', 'Schumack', 'Main Bar', 'OSF', 'OSF Patio'];

        const metricGrid = document.getElementById('metricsGrid');
        const areaOverviewGrid = document.getElementById('areaOverviewGrid');
        const sectionList = document.getElementById('sectionList');
        const canvasFrame = document.getElementById('canvasFrame');
        const canvasSurface = document.getElementById('canvasSurface');
        const detailsPanel = document.getElementById('detailsPanel');
        const inventoryBody = document.getElementById('inventoryBody');
        const inventorySummary = document.getElementById('inventorySummary');
        const pagination = document.getElementById('pagination');
        const inventorySearch = document.getElementById('inventorySearch');
        const inventoryFilter = document.getElementById('inventoryFilter');
        const tableModal = document.getElementById('tableModal');
        const areaModal = document.getElementById('areaModal');
        const tableModalForm = document.getElementById('tableModalForm');
        const areaModalForm = document.getElementById('areaModalForm');
        const deleteAreaButton = document.getElementById('deleteAreaButton');
        const toast = document.getElementById('toast');

        function getAreaById(areaId) {
            return state.areas.find((area) => Number(area.area_id) === Number(areaId)) || null;
        }

        function getZoneBlueprint(zoneKey) {
            return zoneBlueprints.find((zone) => zone.key === zoneKey) || null;
        }

        function resolveZoneLayout(blueprint, area = null) {
            return {
                ...blueprint,
                x: area && area.layout_x !== null ? Number(area.layout_x) : blueprint.x,
                y: area && area.layout_y !== null ? Number(area.layout_y) : blueprint.y,
                width: area && area.layout_width !== null ? Number(area.layout_width) : blueprint.width,
                height: area && area.layout_height !== null ? Number(area.layout_height) : blueprint.height,
            };
        }

        function getAreaResizeHandleDirections() {
            return ['n', 'e', 's', 'w', 'ne', 'nw', 'se', 'sw'];
        }

        function resolveAreaLabelLayout(area, zone) {
            const labelX = area && area.label_layout_x !== null
                ? Number(area.label_layout_x)
                : Math.min(zone.x + zone.width - 28, zone.x + (zone.key === 'osf' ? 190 : 52));
            const labelY = area && area.label_layout_y !== null
                ? Number(area.label_layout_y)
                : zone.y + 14;

            return { x: labelX, y: labelY };
        }

        function getAreaByZoneKey(zoneKey) {
            return state.areas.find((area) => area.zone_key === zoneKey) || null;
        }

        function getZoneByAreaId(areaId) {
            const area = getAreaById(areaId);
            if (!area) {
                return null;
            }

            const blueprint = getZoneBlueprint(area.zone_key);
            if (!blueprint) {
                return null;
            }

            return resolveZoneLayout(blueprint, area);
        }

        function getRenderedZones() {
            return zoneBlueprints.map((blueprint) => {
                const area = getAreaByZoneKey(blueprint.key);

                return {
                    area_id: area ? Number(area.area_id) : null,
                    ...resolveZoneLayout(blueprint, area),
                };
            });
        }

        function getTableLayoutConfig(zone) {
            switch (zone?.key) {
                case 'kookaburra':
                    return { columns: 1, gutterX: 0, gutterY: 88 };
                case 'stables':
                    return { columns: 3, gutterX: 70, gutterY: 64 };
                case 'wisteria':
                case 'schumack':
                case 'main-bar':
                    return { columns: 4, gutterX: 68, gutterY: 62 };
                case 'osf':
                    return { columns: 9, gutterX: 88, gutterY: 72 };
                default:
                    return {
                        columns: TABLE_DEFAULT_COLUMNS,
                        gutterX: TABLE_DEFAULT_GUTTER_X,
                        gutterY: TABLE_DEFAULT_GUTTER_Y,
                    };
            }
        }

        function clampTableWithinZone(table, zone) {
            table.layout_x = Math.max(
                zone.x + TABLE_PADDING_X,
                Math.min(zone.x + zone.width - TABLE_PADDING_X, Math.round(Number(table.layout_x)))
            );
            table.layout_y = Math.max(
                zone.y + TABLE_PADDING_Y,
                Math.min(zone.y + zone.height - TABLE_PADDING_Y, Math.round(Number(table.layout_y)))
            );
        }

        function createLayoutSnapshot() {
            return {
                areas: state.areas.map((area) => ({
                    area_id: Number(area.area_id),
                    layout_x: area.layout_x,
                    layout_y: area.layout_y,
                    layout_width: area.layout_width,
                    layout_height: area.layout_height,
                    label_layout_x: area.label_layout_x,
                    label_layout_y: area.label_layout_y,
                })),
                tables: state.tables.map((table) => ({
                    table_id: Number(table.table_id),
                    layout_x: table.layout_x,
                    layout_y: table.layout_y,
                })),
            };
        }

        function restoreLayoutSnapshot(snapshot) {
            if (!snapshot) {
                return;
            }

            snapshot.areas.forEach((savedArea) => {
                const area = getAreaById(savedArea.area_id);
                if (!area) {
                    return;
                }

                area.layout_x = savedArea.layout_x;
                area.layout_y = savedArea.layout_y;
                area.layout_width = savedArea.layout_width;
                area.layout_height = savedArea.layout_height;
                area.label_layout_x = savedArea.label_layout_x;
                area.label_layout_y = savedArea.label_layout_y;
            });

            snapshot.tables.forEach((savedTable) => {
                const table = state.tables.find((item) => Number(item.table_id) === Number(savedTable.table_id));
                if (!table) {
                    return;
                }

                table.layout_x = savedTable.layout_x;
                table.layout_y = savedTable.layout_y;
            });
        }

        function updateEditModeUI() {
            document.getElementById('enterEditModeButton').classList.toggle('is-hidden', state.isEditMode);
            document.getElementById('saveEditLayoutButton').classList.toggle('is-hidden', !state.isEditMode);
            document.getElementById('cancelEditModeButton').classList.toggle('is-hidden', !state.isEditMode);
            canvasFrame.classList.toggle('edit-mode', state.isEditMode);
        }

        function enterEditMode() {
            if (state.isEditMode) {
                return;
            }

            state.layoutSnapshot = createLayoutSnapshot();
            state.isEditMode = true;
            updateEditModeUI();
            renderCanvas();
            showToast('Edit mode enabled. Drag tables or areas, then save or cancel.');
        }

        function cancelEditMode() {
            if (!state.isEditMode) {
                return;
            }

            state.dragging = null;
            restoreLayoutSnapshot(state.layoutSnapshot);
            state.layoutSnapshot = null;
            state.isEditMode = false;
            state.justManipulatedAreaId = null;
            updateEditModeUI();
            renderAll();
            showToast('Layout changes discarded.');
        }

        function getToneForArea(area) {
            const zone = getZoneBlueprint(area?.zone_key || '');
            return zone ? zone.tone : 'blue';
        }

        function getSortedAreas() {
            return [...state.areas].sort((left, right) => {
                const leftIndex = preferredAreaOrder.indexOf(left.display_name);
                const rightIndex = preferredAreaOrder.indexOf(right.display_name);

                if (leftIndex !== -1 || rightIndex !== -1) {
                    return (leftIndex === -1 ? 999 : leftIndex) - (rightIndex === -1 ? 999 : rightIndex);
                }

                return Number(left.display_order) - Number(right.display_order);
            });
        }

        function getViewportRect() {
            return {
                width: canvasFrame.clientWidth || BASE_CANVAS_WIDTH,
                height: canvasFrame.clientHeight || BASE_CANVAS_HEIGHT,
            };
        }

        function clampScale(scale) {
            return Math.max(MIN_VIEW_SCALE, Math.min(MAX_VIEW_SCALE, Number(scale)));
        }

        function clampPan(scale, panX, panY) {
            const viewport = getViewportRect();
            const scaledWidth = BASE_CANVAS_WIDTH * scale;
            const scaledHeight = BASE_CANVAS_HEIGHT * scale;

            let nextPanX = Number(panX);
            let nextPanY = Number(panY);

            if (scaledWidth <= viewport.width - VIEWPORT_PADDING * 2) {
                nextPanX = Math.round((viewport.width - scaledWidth) / 2);
            } else {
                const minPanX = Math.round(viewport.width - scaledWidth - VIEWPORT_PADDING);
                const maxPanX = VIEWPORT_PADDING;
                nextPanX = Math.min(maxPanX, Math.max(minPanX, nextPanX));
            }

            if (scaledHeight <= viewport.height - VIEWPORT_PADDING * 2) {
                nextPanY = VIEWPORT_PADDING;
            } else {
                const minPanY = Math.round(viewport.height - scaledHeight - VIEWPORT_PADDING);
                const maxPanY = VIEWPORT_PADDING;
                nextPanY = Math.min(maxPanY, Math.max(minPanY, nextPanY));
            }

            return { panX: nextPanX, panY: nextPanY };
        }

        function setViewport(scale, panX, panY) {
            const nextScale = clampScale(scale);
            const nextPan = clampPan(nextScale, panX, panY);
            state.zoom = Number(nextScale.toFixed(3));
            state.panX = nextPan.panX;
            state.panY = nextPan.panY;
        }

        function getCanvasEffectiveScale() {
            return clampScale(state.zoom);
        }

        function getCanvasFitScale() {
            const viewport = getViewportRect();
            const widthScale = (viewport.width - VIEWPORT_PADDING * 2) / BASE_CANVAS_WIDTH;
            return clampScale(widthScale);
        }

        function centerViewportOnRect(rect, scale) {
            const viewport = getViewportRect();
            const targetScale = clampScale(scale);
            const panX = Math.round((viewport.width / 2) - ((rect.x + (rect.width / 2)) * targetScale));
            const panY = Math.round((viewport.height / 2) - ((rect.y + (rect.height / 2)) * targetScale));
            setViewport(targetScale, panX, panY);
        }

        function updateCanvasViewport() {
            const effectiveScale = getCanvasEffectiveScale();
            canvasSurface.style.width = `${BASE_CANVAS_WIDTH}px`;
            canvasSurface.style.height = `${BASE_CANVAS_HEIGHT}px`;
            canvasSurface.style.transform = `translate(${state.panX}px, ${state.panY}px) scale(${effectiveScale})`;
        }

        function seedMissingPositions() {
            const areaBuckets = new Map();

            getSortedAreas().forEach((area) => {
                areaBuckets.set(area.area_id, state.tables.filter((table) => Number(table.area_id) === Number(area.area_id)));
            });

            areaBuckets.forEach((tables, areaId) => {
                const area = getAreaById(areaId);
                const zone = getZoneByAreaId(areaId) || zoneBlueprints[0];

                tables.sort((left, right) => Number(left.sort_order) - Number(right.sort_order)).forEach((table, index) => {
                    if (table.layout_x !== null && table.layout_y !== null) {
                        return;
                    }

                    const layoutConfig = getTableLayoutConfig(zone);
                    const offsetX = TABLE_DEFAULT_OFFSET + (index % layoutConfig.columns) * layoutConfig.gutterX;
                    const offsetY = TABLE_DEFAULT_OFFSET + Math.floor(index / layoutConfig.columns) * layoutConfig.gutterY;

                    table.layout_x = Math.min(zone.x + zone.width - TABLE_DRAG_EDGE_PADDING, zone.x + offsetX);
                    table.layout_y = Math.min(zone.y + zone.height - TABLE_DRAG_EDGE_PADDING, zone.y + offsetY);
                });
            });
        }

        function getMetrics() {
            return {
                total_tables: state.tables.length,
                total_seats: state.tables.reduce((sum, table) => sum + Number(table.capacity || 0), 0),
                active_areas: state.areas.length,
                unassigned_tables: state.tables.filter((table) => table.layout_x === null || table.layout_y === null).length,
            };
        }

        function renderMetrics() {
            const metrics = getMetrics();
            const cards = [
                { label: 'Total Tables', value: metrics.total_tables, icon: 'fa-chair', tone: 'blue' },
                { label: 'Total Seats', value: metrics.total_seats, icon: 'fa-users', tone: 'lavender' },
                { label: 'Active Areas', value: metrics.active_areas, icon: 'fa-circle-notch', tone: 'green' },
                { label: 'Unassigned Tables', value: metrics.unassigned_tables, icon: 'fa-table-cells-large', tone: 'amber' },
            ];

            metricGrid.innerHTML = `
                <div class="admin-command-group">
                    ${cards.map((card) => `
                        <span class="admin-chip ${card.tone === 'amber' && Number(card.value) > 0 ? 'is-warning' : 'is-primary'}">
                            <i class="fa-solid ${card.icon}" aria-hidden="true"></i>
                            ${card.value} ${card.label}
                        </span>
                    `).join('')}
                </div>
                <div class="admin-command-group">
                    <span class="admin-command-note">Use Edit Layout to move tables, then Save Layout to sync the floor plan.</span>
                </div>
            `;
        }

        function renderAreaOverview() {
            areaOverviewGrid.innerHTML = getSortedAreas().map((area) => {
                const tone = getToneForArea(area);
                const relatedTables = state.tables.filter((table) => Number(table.area_id) === Number(area.area_id));
                const seats = relatedTables.reduce((sum, table) => sum + Number(table.capacity || 0), 0);

                return `
                    <article class="area-card">
                        <div class="area-card-top">
                            <div class="area-chip">
                                <span class="swatch tone-${tone}"><i class="fa-solid fa-seedling"></i></span>
                                <span>${escapeHtml(area.display_name)}</span>
                            </div>
                            <span class="area-dots">...</span>
                        </div>
                        <div class="area-stats">
                            <div><strong>${relatedTables.length}</strong> Tables</div>
                            <div><strong>${seats}</strong> Seats</div>
                        </div>
                        <div class="range-pill">${escapeHtml(area.range_label || 'Manual layout')}</div>
                        <div class="section-note" style="margin:0; font-size:12px;">Section ${escapeHtml(area.display_name)} ready for service</div>
                        <button class="button button-ghost" type="button" onclick="openAreaModal(${Number(area.area_id)})">Manage Area</button>
                    </article>
                `;
            }).join('');
        }

        function renderSectionList() {
            sectionList.innerHTML = getSortedAreas().map((area) => {
                const tone = getToneForArea(area);
                const count = state.tables.filter((table) => Number(table.area_id) === Number(area.area_id)).length;

                return `
                    <div class="section-item${Number(state.selectedAreaId) === Number(area.area_id) ? ' active' : ''}">
                        <div class="section-item-main">
                            <span class="section-dot" style="background:${toneToColor(tone)}"></span>
                            <div>
                                <div class="section-name">${escapeHtml(area.display_name)}</div>
                                <div class="section-meta">${count} tables</div>
                            </div>
                        </div>
                        <button class="mini-button" type="button" onclick="focusArea(${Number(area.area_id)})"><i class="fa-regular fa-eye"></i></button>
                    </div>
                `;
            }).join('');
        }

        function renderCanvas() {
            updateCanvasViewport();
            canvasFrame.classList.toggle('edit-mode', state.isEditMode);
            const zoneHtml = getRenderedZones().map((zone) => {
                const area = getAreaByZoneKey(zone.key);
                const isActive = area && Number(state.selectedAreaId) === Number(area.area_id);

                return `
                <div
                    class="zone zone-${zone.tone}${isActive ? ' active' : ''}"
                    data-zone-key="${zone.key}"
                    ${area ? `data-area-id="${Number(area.area_id)}" tabindex="0" role="button" aria-label="Focus ${escapeAttribute(zone.label)} area"` : ''}
                    style="left:${zone.x}px; top:${zone.y}px; width:${zone.width}px; height:${zone.height}px;"
                >
                    ${area && state.isEditMode ? getAreaResizeHandleDirections().map((direction) => `<div class="zone-resize-handle" data-area-resize-handle="true" data-resize-direction="${direction}" aria-hidden="true"></div>`).join('') : ''}
                </div>
            `;
            }).join('');

            const labelHtml = getRenderedZones().map((zone) => {
                const area = getAreaByZoneKey(zone.key);
                const labelPosition = resolveAreaLabelLayout(area, zone);
                const areaAttrs = area
                    ? `data-area-label-id="${Number(area.area_id)}" tabindex="0" role="button" aria-label="Focus ${escapeAttribute(zone.label)} area"`
                    : '';

                return `
                    <div
                        class="zone-label tone-${zone.tone}"
                        data-zone-key="${zone.key}"
                        ${areaAttrs}
                        style="left:${labelPosition.x}px; top:${labelPosition.y}px; transform:translate(-50%, 0);"
                    ><i class="fa-solid ${zone.icon || 'fa-location-dot'}" aria-hidden="true"></i><span>${zone.label}</span></div>
                `;
            }).join('');

            const tableHtml = state.tables.map((table) => {
                const area = getAreaById(table.area_id);
                const tone = getToneForArea(area);
                const isSelected = Number(state.selectedTableId) === Number(table.table_id);
                const x = Number(table.layout_x || 0);
                const y = Number(table.layout_y || 0);
                const displayNumber = String(table.table_number || '').replace(/^T/i, '');

                return `
                    <button
                        class="table-item table-card table-tone-${tone}${isSelected ? ' selected' : ''}"
                        type="button"
                        data-table-id="${Number(table.table_id)}"
                        style="left:${x}px; top:${y}px; transform: translate(-50%, -50%);"
                    >
                        <span class="table-shell">
                            <span class="table-top">
                                <span class="table-label">${escapeHtml(displayNumber)}</span>
                                <span class="table-capacity"><i class="fa-solid fa-user-group" aria-hidden="true"></i>${Number(table.capacity)}</span>
                            </span>
                        </span>
                    </button>
                `;
            }).join('');

            canvasSurface.innerHTML = zoneHtml + labelHtml + tableHtml;

            canvasSurface.querySelectorAll('.zone[data-area-id]').forEach((zone) => {
                zone.querySelectorAll('[data-area-resize-handle]').forEach((handle) => {
                    handle.addEventListener('pointerdown', handleAreaResizeStart);
                });

                zone.addEventListener('click', () => {
                    if (Number(state.justManipulatedAreaId) === Number(zone.dataset.areaId)) {
                        state.justManipulatedAreaId = null;
                        return;
                    }

                    focusArea(Number(zone.dataset.areaId));
                });

                zone.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        focusArea(Number(zone.dataset.areaId));
                    }
                });
            });

            canvasSurface.querySelectorAll('[data-area-label-id]').forEach((label) => {
                label.addEventListener('click', (event) => {
                    const areaId = Number(label.dataset.areaLabelId);

                    if (Number(state.justManipulatedAreaId) === areaId) {
                        state.justManipulatedAreaId = null;
                        return;
                    }

                    event.stopPropagation();
                    focusArea(areaId);
                });

                label.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        focusArea(Number(label.dataset.areaLabelId));
                    }
                });

                if (state.isEditMode) {
                    label.addEventListener('pointerdown', handleAreaDragStart);
                }
            });

            canvasSurface.querySelectorAll('[data-table-id]').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    selectTable(Number(button.dataset.tableId));
                });

                button.addEventListener('pointerdown', handleDragStart);
            });
        }

        function renderDetails() {
            const table = state.tables.find((item) => Number(item.table_id) === Number(state.selectedTableId));
            if (!table) {
                detailsPanel.innerHTML = `
                    <div class="details-empty">
                        <h2 class="section-title" style="margin:0 0 8px; font-size:22px;">Table Details</h2>
                        <p class="section-note">Select a table on the floor plan or from the inventory to edit its settings.</p>
                    </div>
                `;
                return;
            }

            const areaOptions = getSortedAreas().map((area) => `
                <option value="${Number(area.area_id)}" ${Number(area.area_id) === Number(table.area_id) ? 'selected' : ''}>${escapeHtml(area.display_name)}</option>
            `).join('');

            detailsPanel.innerHTML = `
                <div class="details-top">
                    <div>
                        <div class="section-note" style="margin:0;">Table</div>
                        <h2 class="details-title">T${escapeHtml(table.table_number)}</h2>
                    </div>
                    <button class="details-close" type="button" id="clearSelectionButton"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="panel-body details-stack">
                    <div class="field-grid">
                        <div class="field">
                            <label for="detailTableName">Table Name</label>
                            <input id="detailTableName" type="text" value="${escapeAttribute(table.table_number)}">
                        </div>
                        <div class="field split-field">
                            <div>
                                <label for="detailCapacity">Capacity</label>
                                <input id="detailCapacity" type="number" min="1" value="${Number(table.capacity)}">
                            </div>
                            <div class="suffix-pill">pax</div>
                        </div>
                        <div class="field">
                            <label for="detailArea">Area</label>
                            <select id="detailArea">${areaOptions}</select>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div style="font-weight:800;">Reservable</div>
                            <div class="section-note" style="margin:4px 0 0;">Available for online and staff bookings.</div>
                        </div>
                        <label class="switch">
                            <input id="detailReservable" type="checkbox" ${Number(table.reservable) ? 'checked' : ''}>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div style="display:grid; gap:10px;">
                        <button class="button button-primary" type="button" id="updateTableButton">Update Table</button>
                        <button class="button button-danger" type="button" id="deleteTableButton">Delete Table</button>
                    </div>
                </div>
            `;

            document.getElementById('clearSelectionButton').addEventListener('click', () => {
                state.selectedTableId = null;
                renderDetails();
                renderCanvas();
            });

            document.getElementById('updateTableButton').addEventListener('click', updateSelectedTable);
            document.getElementById('deleteTableButton').addEventListener('click', () => deleteTable(table.table_id));
        }

        function getFilteredTables() {
            const search = state.search.trim().toLowerCase();
            return state.tables.filter((table) => {
                const area = getAreaById(table.area_id);
                const matchesSearch = search === '' || (`t${table.table_number}`.toLowerCase().includes(search) || (area?.display_name || '').toLowerCase().includes(search));
                const matchesFilter = state.filterAreaId === 'all' || Number(state.filterAreaId) === Number(table.area_id);
                return matchesSearch && matchesFilter;
            });
        }

        function renderInventory() {
            const filtered = getFilteredTables();
            const totalPages = Math.max(1, Math.ceil(filtered.length / state.perPage));
            if (state.currentPage > totalPages) {
                state.currentPage = totalPages;
            }

            const startIndex = (state.currentPage - 1) * state.perPage;
            const visible = filtered.slice(startIndex, startIndex + state.perPage);

            inventoryBody.innerHTML = visible.map((table) => {
                const area = getAreaById(table.area_id);
                const tone = getToneForArea(area);
                const x = table.layout_x === null ? '-' : Number(table.layout_x);
                const y = table.layout_y === null ? '-' : Number(table.layout_y);

                return `
                    <tr>
                        <td><strong>T${escapeHtml(table.table_number)}</strong></td>
                        <td>${Number(table.capacity)} seats</td>
                        <td><span class="area-badge"><span class="section-dot" style="background:${toneToColor(tone)}"></span>${escapeHtml(area?.display_name || 'Unassigned')}</span></td>
                        <td><span class="status-pill ${Number(table.reservable) ? 'yes' : 'no'}">${Number(table.reservable) ? 'Yes' : 'No'}</span></td>
                        <td><span class="position-pill">${x}, ${y}</span></td>
                        <td>
                            <div class="inventory-actions">
                                <button class="icon-action" type="button" onclick="selectTable(${Number(table.table_id)})"><i class="fa-solid fa-pen"></i></button>
                                <button class="icon-action delete" type="button" onclick="deleteTable(${Number(table.table_id)})"><i class="fa-regular fa-trash-can"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('') || '<tr><td colspan="6" style="text-align:center; color: var(--muted);">No tables found.</td></tr>';

            if (filtered.length === 0) {
                inventorySummary.textContent = 'Showing 0 tables';
            } else {
                const endIndex = Math.min(startIndex + visible.length, filtered.length);
                inventorySummary.textContent = `Showing ${startIndex + 1} to ${endIndex} of ${filtered.length} tables`;
            }

            const pageItems = [];
            pageItems.push(`<button class="page-pill icon" type="button" onclick="goToPage(${Math.max(1, state.currentPage - 1)})"><i class="fa-solid fa-angle-left"></i></button>`);

            const visiblePages = totalPages <= 5
                ? Array.from({ length: totalPages }, (_, index) => index + 1)
                : [1, 2, 3, '...', totalPages];

            visiblePages.forEach((page) => {
                if (page === '...') {
                    pageItems.push('<span class="page-pill" style="cursor:default;">...</span>');
                    return;
                }

                pageItems.push(`<button class="page-pill${page === state.currentPage ? ' active' : ''}" type="button" onclick="goToPage(${page})">${page}</button>`);
            });

            pageItems.push(`<button class="page-pill icon" type="button" onclick="goToPage(${Math.min(totalPages, state.currentPage + 1)})"><i class="fa-solid fa-angle-right"></i></button>`);
            pagination.innerHTML = pageItems.join('');
        }

        function renderFilterOptions() {
            const options = ['<option value="all">All Areas</option>'].concat(getSortedAreas().map((area) => `<option value="${Number(area.area_id)}">${escapeHtml(area.display_name)}</option>`));
            inventoryFilter.innerHTML = options.join('');
            inventoryFilter.value = state.filterAreaId;

            document.getElementById('modalTableArea').innerHTML = getSortedAreas().map((area) => `
                <option value="${Number(area.area_id)}">${escapeHtml(area.display_name)}</option>
            `).join('');
        }

        function selectTable(tableId) {
            const table = state.tables.find((item) => Number(item.table_id) === Number(tableId));
            state.selectedTableId = Number(tableId);
            state.selectedAreaId = table ? Number(table.area_id) : state.selectedAreaId;
            renderCanvas();
            renderSectionList();
            renderDetails();
        }

        function zoomToArea(areaId) {
            const zone = getZoneByAreaId(areaId);
            if (!zone) {
                resetView(false);
                return;
            }

            const viewport = getViewportRect();
            const widthScale = (viewport.width - VIEWPORT_PADDING * 3) / zone.width;
            const heightScale = (viewport.height - VIEWPORT_PADDING * 3) / zone.height;
            centerViewportOnRect(zone, Math.min(widthScale, heightScale));
        }

        function focusArea(areaId) {
            state.filterAreaId = String(areaId);
            state.selectedAreaId = Number(areaId);
            inventoryFilter.value = state.filterAreaId;
            state.currentPage = 1;
            zoomToArea(areaId);
            renderInventory();
            renderSectionList();
            renderCanvas();

            const table = state.tables.find((row) => Number(row.area_id) === Number(areaId));
            if (table) {
                selectTable(table.table_id);
            }
        }

        function toneToColor(tone) {
            const colors = {
                lavender: 'var(--dm-lavender)',
                green: 'var(--dm-confirmed-text)',
                blue: 'var(--dm-info-strong)',
                amber: '#986d32',
                pink: 'var(--dm-danger-text)',
                mocha: '#7a6b59',
            };
            return colors[tone] || 'var(--dm-info-strong)';
        }

        function handleDragStart(event) {
            if (!state.isEditMode) {
                return;
            }

            const tableId = Number(event.currentTarget.dataset.tableId);
            const table = state.tables.find((item) => Number(item.table_id) === tableId);
            if (!table) {
                return;
            }

            const rect = canvasSurface.getBoundingClientRect();
            const currentScale = getCanvasEffectiveScale();
            state.dragging = {
                type: 'table',
                tableId,
                pointerId: event.pointerId,
                offsetX: (event.clientX - rect.left) / currentScale - Number(table.layout_x),
                offsetY: (event.clientY - rect.top) / currentScale - Number(table.layout_y),
            };

            event.currentTarget.setPointerCapture(event.pointerId);
            event.currentTarget.classList.add('dragging');
            selectTable(tableId);
        }

        function handleAreaDragStart(event) {
            if (!state.isEditMode) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (event.target.closest('[data-area-resize-handle]')) {
                return;
            }

            const labelElement = event.currentTarget;
            const areaId = Number(labelElement?.dataset.areaLabelId || 0);
            const area = getAreaById(areaId);
            const zone = getZoneByAreaId(areaId);
            const labelPosition = resolveAreaLabelLayout(area, zone);

            if (!area || !zone || !labelElement) {
                return;
            }

            const rect = canvasSurface.getBoundingClientRect();
            const currentScale = getCanvasEffectiveScale();
            const labelRect = labelElement.getBoundingClientRect();

            state.dragging = {
                type: 'area-label',
                areaId,
                pointerId: event.pointerId,
                offsetX: (event.clientX - rect.left) / currentScale - labelPosition.x,
                offsetY: (event.clientY - rect.top) / currentScale - labelPosition.y,
                labelWidth: labelRect.width / currentScale,
                labelHeight: labelRect.height / currentScale,
                didMove: false,
            };

            labelElement.setPointerCapture(event.pointerId);
            labelElement.classList.add('dragging');
            state.selectedAreaId = areaId;
            renderSectionList();
        }

        function handleAreaResizeStart(event) {
            if (!state.isEditMode) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const zoneElement = event.currentTarget.closest('[data-area-id]');
            const areaId = Number(zoneElement?.dataset.areaId || 0);
            const area = getAreaById(areaId);
            const zone = getZoneByAreaId(areaId);

            if (!area || !zone || !zoneElement) {
                return;
            }

            const rect = canvasSurface.getBoundingClientRect();
            const currentScale = getCanvasEffectiveScale();
            const pointerX = (event.clientX - rect.left) / currentScale;
            const pointerY = (event.clientY - rect.top) / currentScale;
            const direction = String(event.currentTarget.dataset.resizeDirection || 'se').toLowerCase();
            const tableAnchors = state.tables
                .filter((table) => Number(table.area_id) === Number(areaId) && table.layout_x !== null && table.layout_y !== null)
                .map((table) => ({
                    table,
                    relativeX: zone.width > 0 ? (Number(table.layout_x) - zone.x) / zone.width : 0.5,
                    relativeY: zone.height > 0 ? (Number(table.layout_y) - zone.y) / zone.height : 0.5,
                }));

            state.dragging = {
                type: 'area-resize',
                areaId,
                pointerId: event.pointerId,
                startPointerX: pointerX,
                startPointerY: pointerY,
                startX: zone.x,
                startY: zone.y,
                startWidth: zone.width,
                startHeight: zone.height,
                direction,
                tableAnchors,
                didMove: false,
            };

            zoneElement.setPointerCapture(event.pointerId);
            zoneElement.classList.add('resizing');
            state.selectedAreaId = areaId;
            renderSectionList();
        }

        function handlePointerMove(event) {
            if (!state.dragging) {
                return;
            }

            if (state.dragging.type === 'area-label') {
                const area = getAreaById(state.dragging.areaId);
                if (!area) {
                    return;
                }

                const rect = canvasSurface.getBoundingClientRect();
                const currentScale = getCanvasEffectiveScale();
                const unclampedX = ((event.clientX - rect.left) / currentScale) - state.dragging.offsetX;
                const unclampedY = ((event.clientY - rect.top) / currentScale) - state.dragging.offsetY;
                const halfLabelWidth = Math.max(18, state.dragging.labelWidth / 2);
                const nextX = Math.max(
                    AREA_LABEL_CANVAS_PADDING + halfLabelWidth,
                    Math.min(BASE_CANVAS_WIDTH - AREA_LABEL_CANVAS_PADDING - halfLabelWidth, Math.round(unclampedX))
                );
                const nextY = Math.max(
                    AREA_LABEL_CANVAS_PADDING,
                    Math.min(BASE_CANVAS_HEIGHT - AREA_LABEL_CANVAS_PADDING - state.dragging.labelHeight, Math.round(unclampedY))
                );

                if (nextX === area.label_layout_x && nextY === area.label_layout_y) {
                    return;
                }

                area.label_layout_x = nextX;
                area.label_layout_y = nextY;
                state.dragging.didMove = true;

                renderCanvas();
                return;
            }

            if (state.dragging.type === 'area-resize') {
                const area = getAreaById(state.dragging.areaId);
                if (!area) {
                    return;
                }

                const rect = canvasSurface.getBoundingClientRect();
                const currentScale = getCanvasEffectiveScale();
                const pointerX = (event.clientX - rect.left) / currentScale;
                const pointerY = (event.clientY - rect.top) / currentScale;
                const deltaX = pointerX - state.dragging.startPointerX;
                const deltaY = pointerY - state.dragging.startPointerY;
                const direction = state.dragging.direction;
                let nextX = state.dragging.startX;
                let nextY = state.dragging.startY;
                let nextWidth = state.dragging.startWidth;
                let nextHeight = state.dragging.startHeight;

                if (direction.includes('e')) {
                    nextWidth = state.dragging.startWidth + deltaX;
                }

                if (direction.includes('s')) {
                    nextHeight = state.dragging.startHeight + deltaY;
                }

                if (direction.includes('w')) {
                    nextX = state.dragging.startX + deltaX;
                    nextWidth = state.dragging.startWidth - deltaX;
                }

                if (direction.includes('n')) {
                    nextY = state.dragging.startY + deltaY;
                    nextHeight = state.dragging.startHeight - deltaY;
                }

                if (nextWidth < MIN_AREA_WIDTH) {
                    if (direction.includes('w')) {
                        nextX -= MIN_AREA_WIDTH - nextWidth;
                    }
                    nextWidth = MIN_AREA_WIDTH;
                }

                if (nextHeight < MIN_AREA_HEIGHT) {
                    if (direction.includes('n')) {
                        nextY -= MIN_AREA_HEIGHT - nextHeight;
                    }
                    nextHeight = MIN_AREA_HEIGHT;
                }

                if (nextX < AREA_CANVAS_PADDING) {
                    if (direction.includes('w')) {
                        nextWidth -= AREA_CANVAS_PADDING - nextX;
                    }
                    nextX = AREA_CANVAS_PADDING;
                }

                if (nextY < AREA_CANVAS_PADDING) {
                    if (direction.includes('n')) {
                        nextHeight -= AREA_CANVAS_PADDING - nextY;
                    }
                    nextY = AREA_CANVAS_PADDING;
                }

                const maxWidth = BASE_CANVAS_WIDTH - AREA_CANVAS_PADDING - nextX;
                const maxHeight = BASE_CANVAS_HEIGHT - AREA_CANVAS_PADDING - nextY;
                nextWidth = Math.max(MIN_AREA_WIDTH, Math.min(maxWidth, nextWidth));
                nextHeight = Math.max(MIN_AREA_HEIGHT, Math.min(maxHeight, nextHeight));

                nextX = Math.round(nextX);
                nextY = Math.round(nextY);
                nextWidth = Math.round(nextWidth);
                nextHeight = Math.round(nextHeight);

                if (
                    nextX === (area.layout_x ?? state.dragging.startX)
                    && nextY === (area.layout_y ?? state.dragging.startY)
                    && nextWidth === (area.layout_width ?? state.dragging.startWidth)
                    && nextHeight === (area.layout_height ?? state.dragging.startHeight)
                ) {
                    return;
                }

                area.layout_x = nextX;
                area.layout_y = nextY;
                area.layout_width = nextWidth;
                area.layout_height = nextHeight;
                state.dragging.didMove = true;

                state.dragging.tableAnchors.forEach((anchor) => {
                    anchor.table.layout_x = nextX + (anchor.relativeX * nextWidth);
                    anchor.table.layout_y = nextY + (anchor.relativeY * nextHeight);
                    clampTableWithinZone(anchor.table, {
                        x: nextX,
                        y: nextY,
                        width: nextWidth,
                        height: nextHeight,
                    });
                });

                renderCanvas();
                renderInventory();
                return;
            }

            const table = state.tables.find((item) => Number(item.table_id) === Number(state.dragging.tableId));
            if (!table) {
                return;
            }

            const rect = canvasSurface.getBoundingClientRect();
            const currentScale = getCanvasEffectiveScale();
            const nextX = ((event.clientX - rect.left) / currentScale) - state.dragging.offsetX;
            const nextY = ((event.clientY - rect.top) / currentScale) - state.dragging.offsetY;

            table.layout_x = Math.max(TABLE_DRAG_EDGE_PADDING, Math.min(BASE_CANVAS_WIDTH - 16, Math.round(nextX)));
            table.layout_y = Math.max(TABLE_DRAG_EDGE_PADDING, Math.min(BASE_CANVAS_HEIGHT - 16, Math.round(nextY)));
            renderCanvas();
            renderInventory();
        }

        function handlePointerUp() {
            if (state.dragging && (state.dragging.type === 'area-label' || state.dragging.type === 'area-resize') && state.dragging.didMove) {
                state.justManipulatedAreaId = state.dragging.areaId;
                showToast('Area updated. Click Save Layout to keep the new size and position.');
            }

            state.dragging = null;
            canvasSurface.querySelectorAll('.table-item.dragging, .zone.dragging, .zone-label.dragging').forEach((element) => element.classList.remove('dragging'));
            canvasSurface.querySelectorAll('.zone.resizing').forEach((element) => element.classList.remove('resizing'));
        }

        async function saveLayout(leaveEditMode = false) {
            try {
                const response = await fetch('tables-management.php?action=save_layout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        areas: state.areas.map((area) => ({
                            area_id: Number(area.area_id),
                            layout_x: area.layout_x,
                            layout_y: area.layout_y,
                            layout_width: area.layout_width,
                            layout_height: area.layout_height,
                            label_layout_x: area.label_layout_x,
                            label_layout_y: area.label_layout_y,
                        })),
                        tables: state.tables.map((table) => ({
                            table_id: Number(table.table_id),
                            table_number: String(table.table_number),
                            capacity: Number(table.capacity),
                            area_id: Number(table.area_id),
                            sort_order: Number(table.sort_order || 10),
                            reservable: Number(table.reservable) ? 1 : 0,
                            layout_x: table.layout_x,
                            layout_y: table.layout_y,
                        })),
                    }),
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Unable to save layout');
                }

                state.layoutSnapshot = createLayoutSnapshot();
                if (leaveEditMode) {
                    state.isEditMode = false;
                    state.dragging = null;
                    state.justManipulatedAreaId = null;
                    updateEditModeUI();
                    renderCanvas();
                }

                showToast('Layout saved successfully.');
            } catch (error) {
                showToast(error.message || 'Unable to save layout.', true);
            }
        }

        async function updateSelectedTable() {
            const table = state.tables.find((item) => Number(item.table_id) === Number(state.selectedTableId));
            if (!table) {
                return;
            }

            const payload = {
                table_id: Number(table.table_id),
                table_number: document.getElementById('detailTableName').value.trim().replace(/^T/i, ''),
                capacity: Number(document.getElementById('detailCapacity').value),
                area_id: Number(document.getElementById('detailArea').value),
                sort_order: Number(table.sort_order || 10),
                reservable: document.getElementById('detailReservable').checked ? 1 : 0,
                layout_x: table.layout_x,
                layout_y: table.layout_y,
            };

            try {
                const response = await fetch('../actions/update-table.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Unable to update table');
                }

                const nextTable = result.table;
                const index = state.tables.findIndex((item) => Number(item.table_id) === Number(nextTable.table_id));
                if (index !== -1) {
                    state.tables[index] = { ...state.tables[index], ...nextTable };
                }

                renderAll();
                showToast(`Table T${nextTable.table_number} updated.`);
            } catch (error) {
                showToast(error.message || 'Unable to update table.', true);
            }
        }

        async function deleteTable(tableId) {
            const table = state.tables.find((item) => Number(item.table_id) === Number(tableId));
            if (!table) {
                return;
            }

            if (!window.confirm(`Delete table T${table.table_number}?`)) {
                return;
            }

            try {
                const response = await fetch('../actions/delete-table.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ table_id: Number(tableId) }),
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Unable to delete table');
                }

                state.tables = state.tables.filter((item) => Number(item.table_id) !== Number(tableId));
                if (Number(state.selectedTableId) === Number(tableId)) {
                    state.selectedTableId = null;
                }

                renderAll();
                showToast(`Table T${table.table_number} deleted.`);
            } catch (error) {
                showToast(error.message || 'Unable to delete table.', true);
            }
        }

        function openTableModal() {
            tableModalForm.reset();
            document.getElementById('modalTableCapacity').value = 4;
            document.getElementById('modalTableReservable').checked = true;
            renderFilterOptions();
            if (state.selectedTableId) {
                const selected = state.tables.find((item) => Number(item.table_id) === Number(state.selectedTableId));
                if (selected) {
                    document.getElementById('modalTableArea').value = String(selected.area_id);
                }
            }
            tableModal.classList.add('open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        async function createTable(event) {
            event.preventDefault();

            const areaId = Number(document.getElementById('modalTableArea').value);
            const area = getAreaById(areaId);
            const zone = getZoneByAreaId(areaId) || zoneBlueprints[0];
            const sameAreaTables = state.tables.filter((table) => Number(table.area_id) === areaId).length;
            const layoutConfig = getTableLayoutConfig(zone);
            const layoutX = Math.min(zone.x + zone.width - TABLE_DRAG_EDGE_PADDING, zone.x + TABLE_DEFAULT_OFFSET + (sameAreaTables % layoutConfig.columns) * layoutConfig.gutterX);
            const layoutY = Math.min(zone.y + zone.height - TABLE_DRAG_EDGE_PADDING, zone.y + TABLE_DEFAULT_OFFSET + Math.floor(sameAreaTables / layoutConfig.columns) * layoutConfig.gutterY);

            const payload = {
                table_number: document.getElementById('modalTableNumber').value.trim().replace(/^T/i, ''),
                capacity: Number(document.getElementById('modalTableCapacity').value),
                area_id: areaId,
                reservable: document.getElementById('modalTableReservable').checked ? 1 : 0,
                sort_order: (sameAreaTables + 1) * 10,
                layout_x: layoutX,
                layout_y: layoutY,
            };

            try {
                const response = await fetch('../actions/create-table.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Unable to create table');
                }

                state.tables.push({
                    table_id: Number(result.table_id),
                    table_number: String(result.table_number),
                    capacity: Number(result.capacity),
                    area_id: Number(result.area_id),
                    sort_order: Number(result.sort_order),
                    reservable: Number(result.reservable),
                    layout_x: result.layout_x === null ? layoutX : Number(result.layout_x),
                    layout_y: result.layout_y === null ? layoutY : Number(result.layout_y),
                    area_name: result.area_name || area.name,
                    area_display_order: Number(result.area_display_order || area.display_order || 0),
                });

                closeModal('tableModal');
                state.selectedTableId = Number(result.table_id);
                renderAll();
                showToast(`Table T${result.table_number} created.`);
            } catch (error) {
                showToast(error.message || 'Unable to create table.', true);
            }
        }

        function openAreaModal(areaId = null) {
            state.modalAreaId = areaId;
            areaModalForm.reset();
            deleteAreaButton.style.visibility = areaId ? 'visible' : 'hidden';
            document.getElementById('areaModalTitle').textContent = areaId ? 'Manage Area' : 'Add Area';

            if (areaId) {
                const area = getAreaById(areaId);
                if (!area) {
                    return;
                }
                document.getElementById('modalAreaId').value = String(area.area_id);
                document.getElementById('modalAreaName').value = area.name;
                document.getElementById('modalAreaStart').value = area.table_number_start ?? '';
                document.getElementById('modalAreaEnd').value = area.table_number_end ?? '';
            } else {
                document.getElementById('modalAreaId').value = '';
            }

            areaModal.classList.add('open');
        }

        async function submitArea(event) {
            event.preventDefault();

            const areaId = document.getElementById('modalAreaId').value;
            const payload = {
                name: document.getElementById('modalAreaName').value.trim(),
                table_number_start: document.getElementById('modalAreaStart').value || null,
                table_number_end: document.getElementById('modalAreaEnd').value || null,
            };

            const endpoint = areaId ? '../actions/update-area.php' : '../actions/create-area.php';
            if (areaId) {
                payload.area_id = Number(areaId);
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Unable to save area');
                }

                window.location.reload();
            } catch (error) {
                showToast(error.message || 'Unable to save area.', true);
            }
        }

        async function removeArea() {
            const areaId = Number(document.getElementById('modalAreaId').value || 0);
            if (!areaId) {
                return;
            }

            const area = getAreaById(areaId);
            if (!area || !window.confirm(`Delete ${area.display_name}?`)) {
                return;
            }

            try {
                const response = await fetch('../actions/delete-area.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ area_id: areaId }),
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.error || 'Unable to delete area');
                }

                window.location.reload();
            } catch (error) {
                showToast(error.message || 'Unable to delete area.', true);
            }
        }

        function goToPage(page) {
            state.currentPage = Number(page);
            renderInventory();
        }

        function changeScale(delta) {
            const viewport = getViewportRect();
            const currentScale = getCanvasEffectiveScale();
            const nextScale = clampScale(currentScale + delta);
            const centerX = (viewport.width / 2 - state.panX) / currentScale;
            const centerY = (viewport.height / 2 - state.panY) / currentScale;
            const nextPanX = Math.round((viewport.width / 2) - (centerX * nextScale));
            const nextPanY = Math.round((viewport.height / 2) - (centerY * nextScale));
            setViewport(nextScale, nextPanX, nextPanY);
            renderCanvas();
        }

        function resetView(shouldRender = true) {
            const scale = getCanvasFitScale();
            centerViewportOnRect({ x: 0, y: 0, width: BASE_CANVAS_WIDTH, height: BASE_CANVAS_HEIGHT }, scale);

            if (state.filterAreaId === 'all') {
                state.selectedAreaId = null;
            }

            if (shouldRender) {
                renderCanvas();
                renderSectionList();
            }
        }

        function exportInventory() {
            const rows = getFilteredTables().map((table) => {
                const area = getAreaById(table.area_id);
                return [
                    `T${table.table_number}`,
                    table.capacity,
                    area?.display_name || 'Unassigned',
                    Number(table.reservable) ? 'Yes' : 'No',
                    table.layout_x ?? '',
                    table.layout_y ?? '',
                ].join(',');
            });

            const csv = ['Table ID,Capacity,Area,Reservable,Layout X,Layout Y'].concat(rows).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'table-operations-inventory.csv';
            link.click();
            URL.revokeObjectURL(url);
        }

        function showToast(message, isError = false) {
            toast.textContent = message;
            toast.style.background = isError ? 'var(--dm-danger-text)' : 'var(--dm-accent-dark)';
            toast.classList.add('show');
            window.clearTimeout(showToast.timeoutId);
            showToast.timeoutId = window.setTimeout(() => toast.classList.remove('show'), 2600);
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function escapeAttribute(value) {
            return escapeHtml(value);
        }

        function renderAll() {
            seedMissingPositions();
            renderMetrics();
            renderAreaOverview();
            renderSectionList();
            renderFilterOptions();
            renderCanvas();
            renderDetails();
            renderInventory();
        }

        document.getElementById('headerAddTable').addEventListener('click', openTableModal);
        document.getElementById('toolsAddTable').addEventListener('click', openTableModal);
        document.getElementById('addAreaButton').addEventListener('click', () => openAreaModal());
        document.getElementById('enterEditModeButton').addEventListener('click', enterEditMode);
        document.getElementById('saveEditLayoutButton').addEventListener('click', () => saveLayout(true));
        document.getElementById('cancelEditModeButton').addEventListener('click', cancelEditMode);
        document.getElementById('zoomInButton').addEventListener('click', () => changeScale(0.05));
        document.getElementById('zoomOutButton').addEventListener('click', () => changeScale(-0.05));
        document.getElementById('resetViewButton').addEventListener('click', resetView);
        document.getElementById('zoomInFloating').addEventListener('click', () => changeScale(0.05));
        document.getElementById('zoomOutFloating').addEventListener('click', () => changeScale(-0.05));
        document.getElementById('resetViewFloating').addEventListener('click', resetView);
        document.getElementById('exportInventoryButton').addEventListener('click', exportInventory);
        inventorySearch.addEventListener('input', (event) => {
            state.search = event.target.value;
            state.currentPage = 1;
            renderInventory();
        });
        inventoryFilter.addEventListener('change', (event) => {
            state.filterAreaId = event.target.value;
            state.currentPage = 1;
            if (state.filterAreaId === 'all') {
                state.selectedAreaId = null;
                resetView(false);
                renderSectionList();
                renderCanvas();
            } else {
                focusArea(Number(state.filterAreaId));
            }
            renderInventory();
        });
        tableModalForm.addEventListener('submit', createTable);
        areaModalForm.addEventListener('submit', submitArea);
        deleteAreaButton.addEventListener('click', removeArea);
        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => closeModal(button.dataset.closeModal));
        });

        window.addEventListener('pointermove', handlePointerMove);
        window.addEventListener('pointerup', handlePointerUp);
        window.addEventListener('pointercancel', handlePointerUp);
        window.addEventListener('resize', renderCanvas);

        window.selectTable = selectTable;
        window.deleteTable = deleteTable;
        window.goToPage = goToPage;
        window.focusArea = focusArea;
        window.openAreaModal = openAreaModal;

        seedMissingPositions();
        if (state.tables[0]) {
            state.selectedTableId = Number(state.tables[0].table_id);
            state.selectedAreaId = Number(state.tables[0].area_id);
        }
        updateEditModeUI();
        resetView(false);
        renderAll();
    </script>
</body>
</html>


