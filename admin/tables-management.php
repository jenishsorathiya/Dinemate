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

$normalizeTableShape = static function (string $value): string {
    $shape = strtolower(trim($value));

    $aliases = [
        'auto' => 'auto',
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

    return $aliases[$shape] ?? 'auto';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_GET['action'] ?? '') === 'save_layout')) {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);
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
        $tableShape = $normalizeTableShape((string) ($tableRow['table_shape'] ?? 'auto'));

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
            'table_shape' => $tableShape,
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
             SET table_number = ?, capacity = ?, area_id = ?, sort_order = ?, reservable = ?, layout_x = ?, layout_y = ?, table_shape = ?
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
                $tableRow['table_shape'],
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
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
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
            COALESCE(rt.table_shape, 'auto') AS table_shape,
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

$tablesPayload = array_map(static function (array $row) use ($normalizeTableShape): array {
    return [
        'table_id' => (int) $row['table_id'],
        'table_number' => (string) $row['table_number'],
        'capacity' => (int) $row['capacity'],
        'area_id' => (int) $row['area_id'],
        'sort_order' => (int) $row['sort_order'],
        'reservable' => (int) $row['reservable'],
        'layout_x' => $row['layout_x'] !== null ? (int) $row['layout_x'] : null,
        'layout_y' => $row['layout_y'] !== null ? (int) $row['layout_y'] : null,
        'table_shape' => $normalizeTableShape((string) ($row['table_shape'] ?: 'auto')),
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Table Operations (Visual Layout)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg: #f4f6fb;
            --bg-soft: #f8f9fd;
            --card: #ffffff;
            --line: #e6ebf3;
            --text: #1b2740;
            --muted: #6d7891;
            --shadow: 0 24px 44px rgba(28, 39, 74, 0.08);
            --shadow-soft: 0 10px 26px rgba(34, 48, 88, 0.06);
            --nav-accent: #ffbf45;
            --primary: #162544;
            --success: #22b573;
            --danger: #ef5f70;
            --lavender: #8b73ee;
            --green: #7ecf91;
            --blue: #5fa9f3;
            --amber: #ffbd67;
            --pink: #ff77b7;
            --radius: 22px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
        }

        body {
            overflow-x: hidden;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .visual-shell {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100vw;
            overflow-x: clip;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            width: calc(100vw - 96px);
            max-width: calc(100vw - 96px);
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .visual-main {
            width: min(100%, 1240px);
            padding: 16px;
            max-width: 100%;
            margin: 0 auto;
        }

        .page-stack,
        .page-stack > *,
        .panel,
        .metrics-grid,
        .area-overview-grid,
        .editor-grid,
        .inventory-panel {
            max-width: 100%;
        }

        .page-stack {
            display: grid;
            gap: 16px;
            min-width: 0;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-width: 0;
        }

        .page-header > :first-child {
            flex: 1 1 auto;
            min-width: 0;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .page-subtitle {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex: 0 1 auto;
            min-width: 0;
        }

        .button {
            height: 40px;
            border-radius: 12px;
            padding: 0 16px;
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--text);
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
            box-shadow: var(--shadow-soft);
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button-primary {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        .button-accent {
            background: #ffbf45;
            color: #1d2434;
            border-color: transparent;
        }

        .button-danger {
            background: rgba(239, 95, 112, 0.1);
            color: var(--danger);
            border-color: rgba(239, 95, 112, 0.18);
            box-shadow: none;
        }

        .button-ghost {
            background: #f8fafe;
            box-shadow: none;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            min-width: 0;
        }

        .metric-card,
        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow-soft);
        }

        .metric-card {
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .metric-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 16px;
        }

        .metric-icon.lavender { background: rgba(139, 115, 238, 0.12); color: var(--lavender); }
        .metric-icon.green { background: rgba(126, 207, 145, 0.14); color: #2d9250; }
        .metric-icon.blue { background: rgba(95, 169, 243, 0.12); color: #3573b8; }
        .metric-icon.amber { background: rgba(255, 191, 69, 0.18); color: #c7861f; }

        .metric-label {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
        }

        .metric-value {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px 0;
        }

        .section-title {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .section-note {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .area-overview-body {
            padding: 10px 16px 14px;
        }

        .area-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            width: 100%;
            min-width: 0;
        }

        .area-card {
            border: 1px solid var(--line);
            background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
            border-radius: 16px;
            padding: 10px;
            display: grid;
            gap: 8px;
            min-height: 120px;
            min-width: 0;
            max-width: none;
            flex: initial;
        }

        .area-card .button {
            width: 100%;
            justify-content: center;
            height: 32px;
            border-radius: 10px;
            font-size: 11px;
            box-shadow: none;
        }

        .area-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .area-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-weight: 800;
            font-size: 12px;
        }

        .swatch {
            width: 24px;
            height: 24px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            font-size: 11px;
            font-weight: 700;
        }

        .tone-lavender { background: rgba(139, 115, 238, 0.12); color: var(--lavender); }
        .tone-green { background: rgba(126, 207, 145, 0.15); color: #2d9250; }
        .tone-blue { background: rgba(95, 169, 243, 0.14); color: #3573b8; }
        .tone-amber { background: rgba(255, 191, 69, 0.18); color: #c7861f; }
        .tone-pink { background: rgba(255, 119, 183, 0.16); color: #d14d8f; }

        .area-dots {
            color: #9aa5bd;
            font-weight: 700;
            letter-spacing: 0.2em;
        }

        .area-stats {
            display: grid;
            gap: 2px;
            color: var(--muted);
            font-size: 11px;
        }

        .area-stats strong {
            color: var(--text);
            font-weight: 800;
            margin-right: 6px;
        }

        .range-pill {
            width: fit-content;
            padding: 4px 8px;
            border-radius: 999px;
            background: #f4f7fc;
            border: 1px solid var(--line);
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
        }

        .editor-grid {
            display: grid;
            grid-template-columns: minmax(210px, 250px) minmax(0, 1fr) minmax(240px, 290px);
            gap: 14px;
            align-items: stretch;
            min-width: 0;
        }

        .details-panel {
            position: static;
            min-width: 0;
        }

        .panel-body {
            padding: 14px 16px 16px;
        }

        .tools-panel,
        .canvas-panel,
        .details-panel {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 470px;
        }

        .tools-panel,
        .details-panel {
            position: static;
        }

        .tools-stack,
        .details-stack {
            display: grid;
            gap: 14px;
        }

        .tools-stack {
            grid-template-rows: auto auto 1fr;
            height: 100%;
        }

        .section-list {
            display: grid;
            gap: 10px;
        }

        .section-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 11px;
            background: #fbfcff;
            border: 1px solid var(--line);
            border-radius: 12px;
            transition: border-color 0.16s ease, box-shadow 0.16s ease, background 0.16s ease;
        }

        .section-item.active {
            background: #ffffff;
            border-color: rgba(22, 37, 68, 0.28);
            box-shadow: 0 10px 20px rgba(22, 37, 68, 0.08);
        }

        .section-item-main {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .section-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .section-name {
            font-weight: 700;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .section-meta {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }

        .canvas-panel {
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .canvas-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 14px 16px 0;
        }

        .toolbar-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .mini-button {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--muted);
            cursor: pointer;
            display: grid;
            place-items: center;
            box-shadow: var(--shadow-soft);
        }

        .canvas-stage {
            flex: 1;
            padding: 12px 14px 14px;
        }

        .canvas-frame {
            position: relative;
            height: 100%;
            min-height: 470px;
            border-radius: 18px;
            border: 1px solid #dfe5ef;
            overflow: hidden;
            background:
                linear-gradient(90deg, rgba(80, 92, 118, 0.04) 1px, transparent 1px),
                linear-gradient(0deg, rgba(80, 92, 118, 0.04) 1px, transparent 1px),
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.95), rgba(244, 246, 250, 0.96));
            background-size: 24px 24px, 24px 24px, 100% 100%;
        }

        .canvas-frame::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.12), rgba(24, 35, 57, 0.02));
            pointer-events: none;
        }

        .canvas-overlay-right {
            position: absolute;
            z-index: 3;
            display: flex;
            gap: 8px;
        }

        .canvas-overlay-right {
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            flex-direction: column;
        }

        .canvas-toolbar-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .edit-group {
            justify-content: flex-end;
        }

        .toolbar-action {
            box-shadow: none;
        }

        .is-hidden {
            display: none;
        }

        .overlay-chip {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            border: 1px solid rgba(213, 220, 232, 0.95);
            background: rgba(255, 255, 255, 0.94);
            color: #586785;
            display: grid;
            place-items: center;
            box-shadow: 0 10px 18px rgba(26, 39, 66, 0.12);
            cursor: pointer;
            font-size: 12px;
        }

        .canvas-surface {
            position: absolute;
            inset: 0;
            transform-origin: top left;
            will-change: transform;
        }

        .zone {
            position: absolute;
            border-radius: 22px;
            border: 2px dashed rgba(163, 176, 196, 0.58);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.84), rgba(247, 250, 255, 0.74));
            padding: 18px 16px 14px;
            cursor: grab;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .canvas-frame:not(.edit-mode) .zone {
            cursor: pointer;
        }

        .zone:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 22px rgba(17, 17, 17, 0.08);
            border-color: rgba(114, 132, 159, 0.7);
        }

        .zone.active {
            box-shadow: 0 0 0 5px rgba(22, 37, 68, 0.12), 0 16px 30px rgba(17, 17, 17, 0.1);
        }

        .zone.dragging {
            cursor: grabbing;
            box-shadow: 0 18px 28px rgba(17, 17, 17, 0.14);
        }

        .zone.resizing {
            cursor: nwse-resize;
            box-shadow: 0 18px 28px rgba(17, 17, 17, 0.14);
        }

        .zone-label {
            position: absolute;
            left: 50%;
            top: 14px;
            transform: translateX(-50%);
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(191, 201, 218, 0.9);
            color: #111111;
            text-align: center;
            pointer-events: auto;
            box-shadow: 0 8px 20px rgba(55, 72, 105, 0.1);
            backdrop-filter: blur(6px);
            white-space: nowrap;
        }

        .canvas-frame.edit-mode .zone-label {
            cursor: grab;
        }

        .zone.dragging .zone-label {
            cursor: grabbing;
        }

        .zone[data-zone-key="kookaburra"] .zone-label {
            font-size: 13px;
        }

        .zone[data-zone-key="osf"] .zone-label,
        .zone[data-zone-key="wisteria"] .zone-label,
        .zone[data-zone-key="schumack"] .zone-label,
        .zone[data-zone-key="main-bar"] .zone-label {
            font-size: 14px;
        }

        .zone:focus-visible {
            outline: 3px solid rgba(22, 37, 68, 0.28);
            outline-offset: 4px;
        }

        .zone-resize-handle {
            position: absolute;
            right: -10px;
            bottom: -10px;
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 3px solid #111111;
            background: #ffffff;
            cursor: nwse-resize;
            box-shadow: 0 8px 16px rgba(17, 17, 17, 0.12);
        }

        .canvas-frame:not(.edit-mode) .zone-resize-handle {
            display: none;
        }

        .zone.zone-lavender { background: linear-gradient(180deg, rgba(139, 115, 238, 0.08), rgba(139, 115, 238, 0.03)); }
        .zone.zone-lavender .zone-label { color: var(--lavender); }
        .zone.zone-green { background: linear-gradient(180deg, rgba(126, 207, 145, 0.1), rgba(126, 207, 145, 0.04)); }
        .zone.zone-green .zone-label { color: #2d9250; }
        .zone.zone-blue { background: linear-gradient(180deg, rgba(95, 169, 243, 0.09), rgba(95, 169, 243, 0.04)); }
        .zone.zone-blue .zone-label { color: #3573b8; }
        .zone.zone-amber { background: linear-gradient(180deg, rgba(255, 191, 69, 0.1), rgba(255, 191, 69, 0.04)); }
        .zone.zone-amber .zone-label { color: #c7861f; }
        .zone.zone-pink { background: linear-gradient(180deg, rgba(255, 119, 183, 0.09), rgba(255, 119, 183, 0.04)); }
        .zone.zone-pink .zone-label { color: #d14d8f; }

        .table-item {
            position: absolute;
            border: none;
            cursor: grab;
            display: grid;
            place-items: center;
            font-weight: 800;
            color: #2d3a59;
            user-select: none;
            background: transparent;
            box-shadow: none;
            transition: transform 0.14s ease;
            padding: 0;
        }

        .canvas-frame:not(.edit-mode) .table-item {
            cursor: pointer;
        }

        .table-shell {
            position: relative;
            display: grid;
            place-items: center;
            width: 100%;
            height: 100%;
        }

        .table-top {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 2px;
            width: 100%;
            height: 100%;
            border-radius: inherit;
            border: 2px solid currentColor;
            box-shadow: 0 8px 18px rgba(59, 72, 98, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.95);
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 255, 0.92));
        }

        .table-top::before,
        .table-top::after {
            content: '';
            position: absolute;
            background: inherit;
            border: 2px solid currentColor;
            border-radius: inherit;
            opacity: 0.72;
            z-index: -1;
        }

        .table-top::before {
            inset: 4px -6px;
        }

        .table-top::after {
            inset: -4px 6px;
        }

        .table-label,
        .table-capacity {
            position: relative;
            z-index: 3;
        }

        .table-capacity {
            font-size: 10px;
            font-weight: 700;
            color: #51607e;
        }

        .table-label {
            font-size: 12px;
            line-height: 1;
        }

        .table-chair {
            position: absolute;
            z-index: 1;
            background: rgba(255, 255, 255, 0.98);
            border: 2px solid rgba(121, 136, 164, 0.45);
            box-shadow: 0 4px 10px rgba(59, 72, 98, 0.12);
        }

        .table-item:hover {
            transform: translate(-50%, -50%) scale(1.02);
        }

        .table-item.selected {
            filter: drop-shadow(0 14px 24px rgba(22, 37, 68, 0.24));
        }

        .table-item.selected .table-top {
            box-shadow: 0 18px 28px rgba(22, 37, 68, 0.2), 0 0 0 4px rgba(22, 37, 68, 0.08);
        }

        .table-item.dragging {
            cursor: grabbing;
            filter: drop-shadow(0 18px 30px rgba(22, 37, 68, 0.24));
        }

        .table-circle {
            width: 66px;
            height: 66px;
        }

        .table-circle .table-top {
            width: 46px;
            height: 46px;
            border-radius: 50%;
        }

        .table-square {
            width: 70px;
            height: 70px;
        }

        .table-square .table-top {
            width: 48px;
            height: 48px;
            border-radius: 14px;
        }

        .table-rect-horizontal {
            width: 92px;
            height: 62px;
        }

        .table-rect-horizontal .table-top {
            width: 62px;
            height: 40px;
            border-radius: 14px;
        }

        .table-rect-vertical {
            width: 62px;
            height: 92px;
        }

        .table-rect-vertical .table-top {
            width: 40px;
            height: 62px;
            border-radius: 14px;
        }

        .table-circle .table-chair,
        .table-square .table-chair {
            width: 12px;
            height: 9px;
            border-radius: 999px;
        }

        .table-rect-horizontal .table-chair,
        .table-rect-vertical .table-chair {
            width: 10px;
            height: 16px;
            border-radius: 8px;
        }

        .table-chair-top { top: 7px; left: 50%; transform: translateX(-50%); }
        .table-chair-bottom { bottom: 7px; left: 50%; transform: translateX(-50%); }
        .table-chair-left { left: 7px; top: 50%; transform: translateY(-50%) rotate(90deg); }
        .table-chair-right { right: 7px; top: 50%; transform: translateY(-50%) rotate(90deg); }
        .table-chair-top-left { top: 10px; left: 10px; transform: rotate(-28deg); }
        .table-chair-top-right { top: 10px; right: 10px; transform: rotate(28deg); }
        .table-chair-bottom-left { bottom: 10px; left: 10px; transform: rotate(28deg); }
        .table-chair-bottom-right { bottom: 10px; right: 10px; transform: rotate(-28deg); }

        .table-circle .table-chair-top-left,
        .table-circle .table-chair-top-right,
        .table-circle .table-chair-bottom-left,
        .table-circle .table-chair-bottom-right,
        .table-square .table-chair-top,
        .table-square .table-chair-bottom,
        .table-square .table-chair-left,
        .table-square .table-chair-right,
        .table-rect-horizontal .table-chair-top-left,
        .table-rect-horizontal .table-chair-top-right,
        .table-rect-horizontal .table-chair-bottom-left,
        .table-rect-horizontal .table-chair-bottom-right,
        .table-rect-vertical .table-chair-top-left,
        .table-rect-vertical .table-chair-top-right,
        .table-rect-vertical .table-chair-bottom-left,
        .table-rect-vertical .table-chair-bottom-right {
            display: none;
        }

        .table-tone-lavender { color: rgba(139, 115, 238, 0.42); }
        .table-tone-lavender .table-top { background: linear-gradient(180deg, #f7f2ff, #f0e8ff); }
        .table-tone-green { color: rgba(126, 207, 145, 0.52); }
        .table-tone-green .table-top { background: linear-gradient(180deg, #f1fdef, #e2f7e4); }
        .table-tone-blue { color: rgba(95, 169, 243, 0.5); }
        .table-tone-blue .table-top { background: linear-gradient(180deg, #f2f8ff, #ddecfb); }
        .table-tone-amber { color: rgba(255, 191, 69, 0.6); }
        .table-tone-amber .table-top { background: linear-gradient(180deg, #fff7eb, #ffe8cb); }
        .table-tone-pink { color: rgba(255, 119, 183, 0.46); }
        .table-tone-pink .table-top { background: linear-gradient(180deg, #fff1f7, #ffe4ef); }

        .decor {
            position: absolute;
            pointer-events: none;
        }

        .plant {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: radial-gradient(circle at 50% 50%, rgba(118, 172, 111, 0.9), rgba(63, 126, 81, 0.95));
            box-shadow: 0 10px 18px rgba(48, 86, 55, 0.18);
        }

        .plant::after {
            content: '';
            position: absolute;
            inset: 12px;
            border-radius: 50%;
            background: radial-gradient(circle at 40% 35%, rgba(164, 224, 154, 0.95), rgba(74, 144, 89, 0.95));
        }

        .bar-counter {
            width: 184px;
            height: 118px;
            border-radius: 54px 18px 18px 24px;
            background: linear-gradient(180deg, #f7f8fb, #d8dde8);
            border: 1px solid #c8d1df;
            box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.7), 0 18px 30px rgba(98, 108, 126, 0.14);
        }

        .bar-counter::after {
            content: '';
            position: absolute;
            left: 18px;
            top: 26px;
            right: 44px;
            bottom: 18px;
            border-radius: 40px 12px 12px 18px;
            border: 2px solid rgba(108, 121, 145, 0.45);
        }

        .stools {
            display: flex;
            gap: 10px;
            position: absolute;
            left: 20px;
            bottom: 22px;
        }

        .stools span {
            display: block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #8a96a8;
            box-shadow: 0 3px 6px rgba(40, 51, 74, 0.16);
        }

        .details-empty {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100%;
            padding: 28px 18px 22px;
            color: var(--muted);
            font-size: 13px;
        }

        .details-empty::before {
            content: '\f247';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            display: inline-grid;
            place-items: center;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #f5f8fe;
            color: #6980ad;
            margin-bottom: 12px;
        }

        .details-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 16px 6px;
        }

        .details-title {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
        }

        .details-close {
            background: transparent;
            border: 0;
            color: var(--muted);
            cursor: pointer;
            font-size: 18px;
        }

        .field-grid {
            display: grid;
            gap: 14px;
        }

        .details-panel .panel-body,
        .details-panel form,
        .details-stack {
            height: 100%;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .field input,
        .field select {
            width: 100%;
            height: 42px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fbfcff;
            padding: 0 12px;
            color: var(--text);
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: #b9c8e2;
            background: #ffffff;
        }

        .split-field {
            display: grid;
            grid-template-columns: 1fr 72px;
            gap: 10px;
            align-items: end;
        }

        .suffix-pill {
            height: 42px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: #f6f8fc;
            border: 1px solid var(--line);
            color: var(--muted);
            font-weight: 800;
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 14px;
            background: #f8fbff;
            border: 1px solid var(--line);
        }

        .switch {
            position: relative;
            width: 52px;
            height: 30px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            inset: 0;
            border-radius: 999px;
            background: #cdd6e4;
            cursor: pointer;
            transition: background 0.16s ease;
        }

        .slider::before {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            left: 4px;
            top: 4px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 4px 10px rgba(30, 43, 66, 0.16);
            transition: transform 0.16s ease;
        }

        .switch input:checked + .slider {
            background: var(--success);
        }

        .switch input:checked + .slider::before {
            transform: translateX(22px);
        }

        .inventory-panel {
            overflow: hidden;
            min-width: 0;
        }

        .inventory-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 14px 16px 0;
            flex-wrap: wrap;
            min-width: 0;
        }

        .inventory-toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            min-width: 0;
        }

        .search-wrap,
        .filter-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            height: 38px;
            border: 1px solid var(--line);
            background: #ffffff;
            border-radius: 12px;
            padding: 0 12px;
        }

        .search-wrap input,
        .filter-wrap select {
            border: 0;
            background: transparent;
            outline: none;
            min-width: 140px;
            color: var(--text);
        }

        .inventory-table-wrap {
            padding: 10px 16px 4px;
            overflow-x: auto;
            min-width: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        th,
        td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid #edf1f7;
            font-size: 13px;
        }

        th {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        td strong {
            font-size: 14px;
        }

        .area-badge,
        .status-pill,
        .position-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .area-badge {
            background: #f4f7fc;
            border: 1px solid var(--line);
            color: #46526d;
        }

        .status-pill.yes {
            background: rgba(34, 181, 115, 0.12);
            color: #228756;
        }

        .status-pill.no {
            background: rgba(239, 95, 112, 0.12);
            color: #bf4453;
        }

        .position-pill {
            background: #f7f9fd;
            color: var(--muted);
            border: 1px solid var(--line);
        }

        .inventory-actions {
            display: flex;
            gap: 8px;
        }

        .icon-action {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--muted);
            cursor: pointer;
        }

        .icon-action.delete {
            color: var(--danger);
        }

        .inventory-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 16px 16px;
            color: var(--muted);
            font-size: 12px;
            flex-wrap: wrap;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-pill {
            min-width: 30px;
            height: 30px;
            padding: 0 10px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #ffffff;
            color: var(--muted);
            font-weight: 700;
            cursor: pointer;
        }

        .page-pill.active {
            background: #ffbf45;
            border-color: #ffbf45;
            color: #1f2534;
        }

        .page-pill.icon {
            width: 30px;
            min-width: 30px;
            padding: 0;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(16, 22, 38, 0.48);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 1000;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal-card {
            width: min(460px, 100%);
            background: #ffffff;
            border-radius: 24px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .modal-header,
        .modal-footer {
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .modal-header {
            border-bottom: 1px solid var(--line);
        }

        .modal-footer {
            border-top: 1px solid var(--line);
            justify-content: flex-end;
            background: #fbfcff;
        }

        .modal-body {
            padding: 18px 20px;
            display: grid;
            gap: 14px;
        }

        .toast {
            position: fixed;
            right: 24px;
            bottom: 24px;
            padding: 14px 16px;
            background: #162544;
            color: #ffffff;
            border-radius: 16px;
            box-shadow: var(--shadow);
            opacity: 0;
            pointer-events: none;
            transform: translateY(10px);
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 1100;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        @media (min-width: 1600px) {
            .editor-grid {
                grid-template-columns: minmax(220px, 260px) minmax(0, 1fr) minmax(250px, 300px);
            }

            .details-panel {
                grid-column: auto;
            }

            .tools-panel,
            .details-panel {
                position: sticky;
            }
        }

        @media (max-width: 1320px) {
            .visual-main {
                max-width: 1140px;
            }

            .editor-grid {
                grid-template-columns: minmax(190px, 220px) minmax(0, 1fr) minmax(220px, 250px);
            }

            .area-overview-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        @media (max-width: 1180px) {
            .editor-grid {
                grid-template-columns: 1fr;
            }

            .details-panel {
                grid-column: 1 / -1;
            }

            .tools-panel,
            .details-panel {
                position: static;
            }
        }

        @media (max-width: 1080px) {
            .metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .visual-main {
                max-width: 100%;
            }
        }

        @media (max-width: 991px) {
            .main-content {
                width: 100%;
                max-width: 100%;
            }

            .visual-main {
                padding: 14px;
            }

            .page-header,
            .inventory-toolbar,
            .section-head,
            .canvas-toolbar,
            .inventory-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .editor-grid {
                grid-template-columns: 1fr;
            }

            .inventory-toolbar-right,
            .header-actions {
                width: 100%;
            }

            .inventory-toolbar-right > *,
            .header-actions > * {
                flex: 1 1 auto;
                justify-content: center;
            }

            .search-wrap input,
            .filter-wrap select {
                min-width: 0;
                width: 100%;
            }

            .area-card {
                min-width: 0;
                flex-basis: auto;
            }
        }
    </style>
</head>
<body>
    <div class="visual-shell">
        <?php require __DIR__ . '/admin-sidebar.php'; ?>

        <div class="main-content">
            <?php require __DIR__ . '/admin-topbar.php'; ?>

            <main class="visual-main">
                <div class="page-stack">
                <header class="page-header">
                    <div>
                        <h1 class="page-title">Table Operations</h1>
                        <p class="page-subtitle">Configure your venue layout, table setup, and section structure.</p>
                    </div>
                    <div class="header-actions">
                        <button class="button" id="headerAddTable" type="button"><i class="fa-solid fa-plus"></i> Add Table</button>
                    </div>
                </header>

                <section class="metrics-grid" id="metricsGrid"></section>

                <section class="panel">
                    <div class="section-head">
                        <div>
                            <h2 class="section-title">Area Overview</h2>
                            <p class="section-note">Manage your venue sections and review table distribution at a glance.</p>
                        </div>
                        <button class="button button-ghost" id="addAreaButton" type="button"><i class="fa-solid fa-plus"></i> Add Area</button>
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
                                <p class="section-note">Interactive seating map with realistic zones, drag positioning, and quick edits.</p>
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
                    <div class="field">
                        <label for="modalTableShape">Shape</label>
                        <select id="modalTableShape" name="table_shape">
                            <option value="auto">Automatic</option>
                            <option value="circle">Circle</option>
                            <option value="square">Square</option>
                            <option value="rect-horizontal">Rectangle Horizontal</option>
                            <option value="rect-vertical">Rectangle Vertical</option>
                        </select>
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
        const TABLE_PADDING_X = 34;
        const TABLE_PADDING_Y = 30;

        const zoneBlueprints = [
            { key: 'stables', label: 'Stables', tone: 'amber', x: 42, y: 16, width: 166, height: 92 },
            { key: 'kookaburra', label: 'Kookaburra', tone: 'green', x: 42, y: 126, width: 112, height: 138 },
            { key: 'wisteria', label: 'Wisteria', tone: 'pink', x: 478, y: 14, width: 176, height: 156 },
            { key: 'schumack', label: 'Schumack', tone: 'blue', x: 478, y: 194, width: 188, height: 118 },
            { key: 'main-bar', label: 'Main Bar', tone: 'lavender', x: 176, y: 190, width: 316, height: 184 },
            { key: 'osf', label: 'OSF', tone: 'green', x: 42, y: 392, width: 644, height: 192 }
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
            const resolved = {
                ...blueprint,
                x: area && area.layout_x !== null ? Number(area.layout_x) : blueprint.x,
                y: area && area.layout_y !== null ? Number(area.layout_y) : blueprint.y,
                width: area && area.layout_width !== null ? Number(area.layout_width) : blueprint.width,
                height: area && area.layout_height !== null ? Number(area.layout_height) : blueprint.height,
            };

            if (resolved.key === 'osf') {
                const bottomGap = BASE_CANVAS_HEIGHT - (resolved.y + resolved.height);

                if (bottomGap > 72) {
                    resolved.height = Math.max(MIN_AREA_HEIGHT, BASE_CANVAS_HEIGHT - resolved.y - 16);
                }
            }

            return resolved;
        }

        function resolveAreaLabelLayout(area, zone) {
            const labelX = area && area.label_layout_x !== null
                ? Number(area.label_layout_x)
                : Math.round(zone.x + (zone.width / 2));
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

        function normalizeTableShape(value) {
            const shape = String(value || 'auto').trim().toLowerCase();

            if (shape === 'circle' || shape === 'square' || shape === 'rect-horizontal' || shape === 'rect-vertical') {
                return shape;
            }

            if (shape === 'rect' || shape === 'rectangle' || shape === 'rect-h' || shape === 'horizontal') {
                return 'rect-horizontal';
            }

            if (shape === 'rect-v' || shape === 'vertical') {
                return 'rect-vertical';
            }

            return 'auto';
        }

        function resolveShape(table) {
            const explicitShape = normalizeTableShape(table.table_shape);

            if (explicitShape !== 'auto') {
                return explicitShape;
            }

            const capacity = Number(table.capacity || 0);

            if (capacity <= 2) {
                return 'circle';
            }

            if (capacity <= 4) {
                return 'square';
            }

            return capacity >= 8 ? 'rect-horizontal' : 'rect-vertical';
        }

        function getShapeOptions(selectedShape) {
            const currentShape = normalizeTableShape(selectedShape);

            return `
                <option value="auto" ${currentShape === 'auto' ? 'selected' : ''}>Automatic</option>
                <option value="circle" ${currentShape === 'circle' ? 'selected' : ''}>Circle</option>
                <option value="square" ${currentShape === 'square' ? 'selected' : ''}>Square</option>
                <option value="rect-horizontal" ${currentShape === 'rect-horizontal' ? 'selected' : ''}>Rectangle Horizontal</option>
                <option value="rect-vertical" ${currentShape === 'rect-vertical' ? 'selected' : ''}>Rectangle Vertical</option>
            `;
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

                    const columns = zone.key === 'osf' ? 4 : 2;
                    const gutterX = zone.key === 'osf' ? 90 : 82;
                    const gutterY = zone.key === 'osf' ? 68 : 82;
                    const offsetX = 42 + (index % columns) * gutterX;
                    const offsetY = 42 + Math.floor(index / columns) * gutterY;

                    table.layout_x = Math.min(zone.x + zone.width - 54, zone.x + offsetX);
                    table.layout_y = Math.min(zone.y + zone.height - 54, zone.y + offsetY);
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

            metricGrid.innerHTML = cards.map((card) => `
                <article class="metric-card">
                    <div class="metric-icon ${card.tone}"><i class="fa-solid ${card.icon}"></i></div>
                    <div>
                        <p class="metric-label">${card.label}</p>
                        <p class="metric-value">${card.value}</p>
                    </div>
                </article>
            `).join('');
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
                    <div class="zone-label" data-area-drag-handle="true" style="left:${resolveAreaLabelLayout(area, zone).x}px; top:${resolveAreaLabelLayout(area, zone).y}px; transform:translate(-50%, 0);">${zone.label}</div>
                    ${area && state.isEditMode ? '<div class="zone-resize-handle" data-area-resize-handle="true" aria-hidden="true"></div>' : ''}
                </div>
            `;
            }).join('');

            const tableHtml = state.tables.map((table) => {
                const area = getAreaById(table.area_id);
                const tone = getToneForArea(area);
                const shape = resolveShape(table);
                const isSelected = Number(state.selectedTableId) === Number(table.table_id);
                const x = Number(table.layout_x || 0);
                const y = Number(table.layout_y || 0);

                return `
                    <button
                        class="table-item table-${shape} table-tone-${tone}${isSelected ? ' selected' : ''}"
                        type="button"
                        data-table-id="${Number(table.table_id)}"
                        style="left:${x}px; top:${y}px; transform: translate(-50%, -50%);"
                    >
                        <span class="table-shell">
                            <span class="table-chair table-chair-top"></span>
                            <span class="table-chair table-chair-bottom"></span>
                            <span class="table-chair table-chair-left"></span>
                            <span class="table-chair table-chair-right"></span>
                            <span class="table-chair table-chair-top-left"></span>
                            <span class="table-chair table-chair-top-right"></span>
                            <span class="table-chair table-chair-bottom-left"></span>
                            <span class="table-chair table-chair-bottom-right"></span>
                            <span class="table-top">
                                <span class="table-label">T${escapeHtml(table.table_number)}</span>
                                <span class="table-capacity">${Number(table.capacity)}p</span>
                            </span>
                        </span>
                    </button>
                `;
            }).join('');

            canvasSurface.innerHTML = zoneHtml + tableHtml;

            canvasSurface.querySelectorAll('[data-area-id]').forEach((zone) => {
                const dragHandle = zone.querySelector('[data-area-drag-handle]');
                if (dragHandle) {
                    dragHandle.addEventListener('pointerdown', handleAreaDragStart);
                }

                const handle = zone.querySelector('[data-area-resize-handle]');
                if (handle) {
                    handle.addEventListener('pointerdown', handleAreaResizeStart);
                }

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
                        <div class="field">
                            <label for="detailShape">Shape</label>
                            <select id="detailShape">
                                ${getShapeOptions(table.table_shape)}
                            </select>
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
                lavender: '#8b73ee',
                green: '#54b96d',
                blue: '#5fa9f3',
                amber: '#ffbd67',
                pink: '#ff77b7',
            };
            return colors[tone] || '#5fa9f3';
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

            const zoneElement = event.currentTarget.closest('[data-area-id]');
            const areaId = Number(zoneElement?.dataset.areaId || 0);
            const area = getAreaById(areaId);
            const zone = getZoneByAreaId(areaId);
            const labelPosition = resolveAreaLabelLayout(area, zone);

            if (!area || !zone || !zoneElement) {
                return;
            }

            const rect = canvasSurface.getBoundingClientRect();
            const currentScale = getCanvasEffectiveScale();

            state.dragging = {
                type: 'area-label',
                areaId,
                pointerId: event.pointerId,
                offsetX: (event.clientX - rect.left) / currentScale - labelPosition.x,
                offsetY: (event.clientY - rect.top) / currentScale - labelPosition.y,
                didMove: false,
            };

            zoneElement.setPointerCapture(event.pointerId);
            zoneElement.classList.add('dragging');
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
                const zone = getZoneByAreaId(state.dragging.areaId);
                if (!area || !zone) {
                    return;
                }

                const rect = canvasSurface.getBoundingClientRect();
                const currentScale = getCanvasEffectiveScale();
                const unclampedX = ((event.clientX - rect.left) / currentScale) - state.dragging.offsetX;
                const unclampedY = ((event.clientY - rect.top) / currentScale) - state.dragging.offsetY;
                const nextX = Math.max(zone.x + 42, Math.min(zone.x + zone.width - 42, Math.round(unclampedX)));
                const nextY = Math.max(zone.y + 8, Math.min(zone.y + zone.height - 38, Math.round(unclampedY)));

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
                const requestedWidth = state.dragging.startWidth + (pointerX - state.dragging.startPointerX);
                const requestedHeight = state.dragging.startHeight + (pointerY - state.dragging.startPointerY);
                const nextWidth = Math.max(MIN_AREA_WIDTH, Math.min(BASE_CANVAS_WIDTH - state.dragging.startX - 16, Math.round(requestedWidth)));
                const nextHeight = Math.max(MIN_AREA_HEIGHT, Math.min(BASE_CANVAS_HEIGHT - state.dragging.startY - 16, Math.round(requestedHeight)));

                if (nextWidth === (area.layout_width ?? state.dragging.startWidth) && nextHeight === (area.layout_height ?? state.dragging.startHeight)) {
                    return;
                }

                area.layout_width = nextWidth;
                area.layout_height = nextHeight;
                state.dragging.didMove = true;

                state.dragging.tableAnchors.forEach((anchor) => {
                    anchor.table.layout_x = state.dragging.startX + (anchor.relativeX * nextWidth);
                    anchor.table.layout_y = state.dragging.startY + (anchor.relativeY * nextHeight);
                    clampTableWithinZone(anchor.table, {
                        x: state.dragging.startX,
                        y: state.dragging.startY,
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

            table.layout_x = Math.max(48, Math.min(BASE_CANVAS_WIDTH - 16, Math.round(nextX)));
            table.layout_y = Math.max(48, Math.min(BASE_CANVAS_HEIGHT - 16, Math.round(nextY)));
            renderCanvas();
            renderInventory();
        }

        function handlePointerUp() {
            if (state.dragging && (state.dragging.type === 'area-label' || state.dragging.type === 'area-resize') && state.dragging.didMove) {
                state.justManipulatedAreaId = state.dragging.areaId;
                showToast('Area updated. Click Save Layout to keep the new size and position.');
            }

            state.dragging = null;
            canvasSurface.querySelectorAll('.table-item.dragging, .zone.dragging').forEach((element) => element.classList.remove('dragging'));
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
                            table_shape: table.table_shape || 'auto',
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
                table_shape: document.getElementById('detailShape').value,
            };

            try {
                const response = await fetch('timeline/update-table.php', {
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
                const response = await fetch('timeline/delete-table.php', {
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
            const columns = zone.key === 'osf' ? 4 : 2;
            const layoutX = Math.min(zone.x + zone.width - 54, zone.x + 42 + (sameAreaTables % columns) * (zone.key === 'osf' ? 90 : 82));
            const layoutY = Math.min(zone.y + zone.height - 54, zone.y + 42 + Math.floor(sameAreaTables / columns) * (zone.key === 'osf' ? 68 : 82));

            const payload = {
                table_number: document.getElementById('modalTableNumber').value.trim().replace(/^T/i, ''),
                capacity: Number(document.getElementById('modalTableCapacity').value),
                area_id: areaId,
                reservable: document.getElementById('modalTableReservable').checked ? 1 : 0,
                sort_order: (sameAreaTables + 1) * 10,
                layout_x: layoutX,
                layout_y: layoutY,
                table_shape: document.getElementById('modalTableShape').value,
            };

            try {
                const response = await fetch('timeline/create-table.php', {
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
                    table_shape: result.table_shape || payload.table_shape,
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

            const endpoint = areaId ? 'timeline/update-area.php' : 'timeline/create-area.php';
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
                const response = await fetch('timeline/delete-area.php', {
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
            toast.style.background = isError ? '#8f2130' : '#162544';
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
