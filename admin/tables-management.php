<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureTableAreasSchema($pdo);
ensureBookingTableAssignmentsTable($pdo);

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
            COALESCE(ta.name, 'No Area') AS area_name,
            COALESCE(ta.display_order, 9999) AS area_display_order,
            ta.table_number_start,
            ta.table_number_end
     FROM restaurant_tables rt
     LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
     ORDER BY COALESCE(ta.display_order, 9999) ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC"
);
$tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$areasStmt = $pdo->query(
    "SELECT area_id, name, display_order, table_number_start, table_number_end, is_active
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
        $unassignedTables += 1;
    }
}

$areaCards = [];
foreach ($areas as $index => $area) {
    $areaId = (int) $area['area_id'];
    $areaTables = $tablesByArea[$areaId] ?? [];
    $placedCount = count(array_filter($areaTables, static function ($tableRow) {
        return $tableRow['layout_x'] !== null && $tableRow['layout_y'] !== null;
    }));
    $tableNumbers = array_map(static function ($tableRow) {
        return (string) $tableRow['table_number'];
    }, $areaTables);

    $rangeLabel = 'Manual layout';
    if ($area['table_number_start'] !== null && $area['table_number_end'] !== null) {
        $rangeLabel = 'T' . $area['table_number_start'] . ' - T' . $area['table_number_end'];
    } elseif (!empty($tableNumbers)) {
        $firstTable = reset($tableNumbers);
        $lastTable = end($tableNumbers);
        if ($firstTable !== false && $lastTable !== false) {
            $rangeLabel = 'T' . $firstTable . ($firstTable !== $lastTable ? ' - T' . $lastTable : '');
        }
    }

    $areaCards[] = [
        'area_id' => $areaId,
        'name' => $area['name'],
        'display_order' => (int) $area['display_order'],
        'table_number_start' => $area['table_number_start'] !== null ? (int) $area['table_number_start'] : null,
        'table_number_end' => $area['table_number_end'] !== null ? (int) $area['table_number_end'] : null,
        'table_count' => count($areaTables),
        'placed_count' => $placedCount,
        'total_seats' => array_sum(array_map(static function ($tableRow) {
            return (int) $tableRow['capacity'];
        }, $areaTables)),
        'range_label' => $rangeLabel,
        'tone_index' => $index % 6,
    ];
}

$tablesForJs = array_map(static function ($tableRow) {
    return [
        'table_id' => (int) $tableRow['table_id'],
        'table_number' => (string) $tableRow['table_number'],
        'capacity' => (int) $tableRow['capacity'],
        'area_id' => (int) $tableRow['area_id'],
        'sort_order' => (int) $tableRow['sort_order'],
        'reservable' => (int) $tableRow['reservable'],
        'layout_x' => $tableRow['layout_x'] !== null ? (int) $tableRow['layout_x'] : null,
        'layout_y' => $tableRow['layout_y'] !== null ? (int) $tableRow['layout_y'] : null,
        'table_shape' => $tableRow['table_shape'] ?: 'auto',
        'area_name' => (string) $tableRow['area_name'],
        'area_display_order' => (int) $tableRow['area_display_order'],
        'is_unplaced' => $tableRow['layout_x'] === null || $tableRow['layout_y'] === null,
    ];
}, $tables);

$adminPageTitle = 'Table Operations';
$adminPageIcon = 'fa-chair';
$adminNotificationCount = $unassignedTables;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'tables';
$adminSidebarPathPrefix = '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Table Operations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard-theme.css" rel="stylesheet">
    <style>
        :root {
            --ops-bg: #f7f8fa;
            --ops-surface: #ffffff;
            --ops-border: #e4e9f2;
            --ops-border-strong: #d8e0ee;
            --ops-text: #1d2940;
            --ops-muted: #6d7890;
            --ops-blue: #2d5fd3;
            --ops-blue-deep: #14203a;
            --ops-shadow: 0 10px 30px rgba(20, 31, 56, 0.06);
            --ops-shadow-soft: 0 6px 16px rgba(20, 31, 56, 0.05);
            --canvas-width: 980px;
            --canvas-height: 560px;
            --grid-size: 16px;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--ops-bg);
            color: var(--ops-text);
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        .page-shell {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .ops-card {
            background: var(--ops-surface);
            border: 1px solid var(--ops-border);
            border-radius: 18px;
            box-shadow: var(--ops-shadow);
        }

        .page-header {
            padding: 18px 20px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .page-header-main {
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .page-icon {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(180deg, #edf3ff, #e3ecff);
            color: var(--ops-blue);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: inset 0 0 0 1px rgba(45, 95, 211, 0.08);
        }

        .page-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 4px;
            color: #1a2846;
        }

        .page-subtitle {
            margin: 0;
            color: var(--ops-muted);
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .ops-button {
            border: 1px solid var(--ops-border-strong);
            background: #ffffff;
            color: var(--ops-text);
            min-height: 40px;
            border-radius: 10px;
            padding: 10px 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.18s ease;
            box-shadow: var(--ops-shadow-soft);
            text-decoration: none;
        }

        .ops-button:hover {
            transform: translateY(-1px);
            background: #f8faff;
            color: var(--ops-text);
        }

        .ops-button.primary {
            background: linear-gradient(180deg, #2f63d8, #2452bf);
            border-color: #2452bf;
            color: #ffffff;
        }

        .ops-button.primary:hover {
            background: linear-gradient(180deg, #2859c8, #1f49ab);
            color: #ffffff;
        }

        .ops-button.danger {
            background: #fff1f2;
            border-color: #ffd6db;
            color: #d24f64;
        }

        .ops-button.danger:hover {
            background: #ffe7ea;
            color: #c64258;
        }

        .ops-button.ghost {
            background: #f8faff;
        }

        .ops-button.small {
            min-height: 32px;
            padding: 7px 12px;
            font-size: 12px;
            border-radius: 9px;
            box-shadow: none;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .metric-card {
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .metric-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }

        .metric-icon.tables { background: #edf3ff; color: #3365d9; }
        .metric-icon.seats { background: #eef8ff; color: #0d84d8; }
        .metric-icon.areas { background: #f3f0ff; color: #7b5ce1; }
        .metric-icon.unassigned { background: #f9f1ff; color: #8a5cf0; }

        .metric-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--ops-muted);
            font-weight: 700;
            margin-bottom: 3px;
        }

        .metric-value {
            font-size: 30px;
            line-height: 1;
            font-weight: 700;
            color: #182440;
            margin-bottom: 4px;
        }

        .metric-note {
            margin: 0;
            font-size: 12px;
            color: var(--ops-muted);
        }

        .section-card {
            padding: 16px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .section-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #1a2846;
        }

        .area-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
        }

        .area-card {
            border: 1px solid var(--ops-border);
            border-radius: 14px;
            padding: 14px 14px 10px;
            background: #ffffff;
            box-shadow: var(--ops-shadow-soft);
        }

        .area-card-summary {
            margin-left: auto;
            text-align: right;
        }

        .area-utilization {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 22px;
            border-radius: 999px;
            padding: 0 8px;
            background: #f4f7fd;
            color: #5f6d8a;
            font-size: 10px;
            font-weight: 700;
        }

        .area-card-top {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .area-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }

        .area-name {
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 4px;
            color: #1a2846;
        }

        .area-meta {
            margin: 0;
            font-size: 12px;
            color: var(--ops-muted);
            line-height: 1.45;
        }

        .area-range {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            border-radius: 999px;
            padding: 0 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .area-manage {
            margin-top: 10px;
            width: 100%;
        }

        .editor-grid {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr) 280px;
            gap: 14px;
            align-items: start;
        }

        .tools-card,
        .editor-card,
        .inspector-card,
        .inventory-card {
            padding: 14px;
        }

        .tools-card,
        .inspector-card {
            position: sticky;
            top: 18px;
        }

        .inspector-card {
            min-height: 620px;
            display: flex;
            flex-direction: column;
        }

        .inspector-subtitle {
            margin: 4px 0 0;
            font-size: 11px;
            color: var(--ops-muted);
        }

        .inspector-form {
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1;
        }

        .inspector-form[hidden] {
            display: none !important;
        }

        .inspector-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
            padding-top: 10px;
        }

        .tools-stack {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .stack-title {
            margin: 0 0 10px;
            font-size: 12px;
            font-weight: 700;
            color: var(--ops-text);
        }

        .section-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .section-chip {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--ops-border);
            border-radius: 10px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 9px;
            justify-content: flex-start;
            font-size: 12px;
            font-weight: 600;
            color: #33415f;
            text-align: left;
            transition: 0.18s ease;
        }

        .section-chip:hover,
        .section-chip.active {
            border-color: #c6d5f3;
            background: #f7faff;
        }

        .chip-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .toggle-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 12px;
            color: #4a5877;
        }

        .switch {
            position: relative;
            width: 34px;
            height: 20px;
            display: inline-flex;
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
            background: #d9e2f1;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .slider::before {
            content: '';
            position: absolute;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            left: 3px;
            top: 3px;
            background: #ffffff;
            transition: 0.18s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .switch input:checked + .slider {
            background: #3869db;
        }

        .switch input:checked + .slider::before {
            transform: translateX(14px);
        }

        .helper-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .helper-list li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 11px;
            color: #61708d;
        }

        .helper-list i {
            color: #8da4da;
            margin-top: 2px;
        }

        .editor-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .editor-tools {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dirty-pill {
            min-height: 28px;
            border-radius: 999px;
            padding: 0 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            background: #eef2ff;
            color: #3952b7;
            border: 1px solid #d8e2ff;
        }

        .dirty-pill.clean {
            background: #eef9f1;
            color: #1f8d52;
            border-color: #d8efdf;
        }

        .canvas-shell {
            border: 1px solid var(--ops-border-strong);
            border-radius: 16px;
            background: linear-gradient(180deg, #fcfdff, #f5f8fc);
            padding: 10px;
            overflow: auto;
        }

        .layout-canvas {
            width: var(--canvas-width);
            height: var(--canvas-height);
            position: relative;
            border-radius: 14px;
            background:
                radial-gradient(circle at 12% 16%, rgba(207, 221, 240, 0.18) 0, rgba(207, 221, 240, 0.02) 20%, transparent 30%),
                linear-gradient(180deg, #ffffff, #fbfcfe);
            overflow: hidden;
            box-shadow: inset 0 0 0 1px #e5ebf5;
        }

        .layout-canvas.show-grid {
            background-image:
                linear-gradient(rgba(226, 233, 245, 0.65) 1px, transparent 1px),
                linear-gradient(90deg, rgba(226, 233, 245, 0.65) 1px, transparent 1px);
            background-size: var(--grid-size) var(--grid-size);
        }

        .zone-card {
            position: absolute;
            border-radius: 14px;
            border: 1.5px dashed var(--zone-border, #b5c9ff);
            background: var(--zone-bg, rgba(77, 125, 240, 0.05));
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.55);
        }

        .zone-title {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--zone-text, #3b62c4);
            white-space: nowrap;
        }

        .canvas-decor {
            position: absolute;
            z-index: 1;
            pointer-events: none;
        }

        .decor-plant {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: radial-gradient(circle at 50% 50%, rgba(110, 178, 125, 0.22), rgba(110, 178, 125, 0.08) 55%, transparent 56%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #68a96f;
            font-size: 16px;
        }

        .bar-counter {
            position: absolute;
            height: 36px;
            border-radius: 12px;
            border: 1px solid #e2e6ed;
            background: repeating-linear-gradient(135deg, #f5f7fa 0, #f5f7fa 6px, #eef2f6 6px, #eef2f6 12px);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6d7890;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            z-index: 2;
        }

        .layout-table {
            position: absolute;
            transform: translate(-50%, -50%);
            border: 1px solid rgba(55, 96, 190, 0.45);
            box-shadow: 0 10px 22px rgba(58, 88, 170, 0.14);
            cursor: grab;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--table-bg, #dbe8ff);
            color: var(--table-text, #2345a2);
            user-select: none;
            padding: 10px;
            z-index: 3;
            transition: box-shadow 0.18s ease;
        }

        .layout-table.circle {
            width: 66px;
            height: 66px;
            border-radius: 999px;
        }

        .layout-table.rect {
            min-width: 86px;
            height: 40px;
            border-radius: 11px;
        }

        .layout-table.rect.large {
            min-width: 118px;
            height: 46px;
        }

        .layout-table:hover,
        .layout-table.selected {
            box-shadow: 0 14px 28px rgba(44, 83, 179, 0.22);
            z-index: 6;
        }

        .layout-table.dragging {
            cursor: grabbing;
            box-shadow: 0 16px 32px rgba(44, 83, 179, 0.28);
            z-index: 10;
        }

        .layout-table.unplaced {
            outline: 2px dashed rgba(250, 165, 0, 0.7);
            outline-offset: 2px;
        }

        .layout-table.nonreservable {
            opacity: 0.72;
        }

        .table-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            line-height: 1;
            pointer-events: none;
        }

        .table-name {
            font-size: 12px;
            font-weight: 700;
        }

        .table-capacity {
            font-size: 10px;
            font-weight: 600;
            opacity: 0.85;
        }

        .canvas-tip {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #edf1f7;
            text-align: center;
            font-size: 11px;
            color: #69758b;
        }

        .canvas-tip i {
            color: #f3b439;
        }

        .inventory-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .inventory-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .inventory-table-wrap {
            overflow: auto;
            border: 1px solid var(--ops-border);
            border-radius: 14px;
        }

        .inventory-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 12px 12px;
            border-bottom: 1px solid #edf1f7;
            text-align: left;
            font-size: 12px;
            vertical-align: middle;
        }

        .inventory-table th {
            font-size: 10px;
            color: #7b879d;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: #fbfcff;
            font-weight: 700;
        }

        .inventory-table tr:last-child td {
            border-bottom: none;
        }

        .search-input,
        .filter-select,
        .form-select,
        .form-number,
        .form-input {
            min-height: 38px;
            border-radius: 10px;
            border: 1px solid var(--ops-border-strong);
            background: #ffffff;
            color: var(--ops-text);
            padding: 8px 12px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .search-input:focus,
        .filter-select:focus,
        .form-select:focus,
        .form-number:focus,
        .form-input:focus {
            border-color: #adc1ef;
            box-shadow: 0 0 0 4px rgba(45, 95, 211, 0.12);
        }

        .search-input {
            width: 190px;
        }

        .position-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            background: #f4f7fd;
            color: #60708f;
            font-size: 10px;
            font-weight: 700;
        }

        .area-badge,
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 22px;
            padding: 0 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
        }

        .status-badge.yes {
            background: #eaf8ef;
            color: #1a8d53;
        }

        .status-badge.no {
            background: #fff0f2;
            color: #c74b61;
        }

        .inventory-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .inventory-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .pager {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .page-link-button {
            width: 26px;
            height: 26px;
            border-radius: 8px;
            border: 1px solid var(--ops-border-strong);
            background: #ffffff;
            color: #56637d;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .page-link-button.active {
            background: #2f63d8;
            border-color: #2f63d8;
            color: #ffffff;
        }

        .drawer-backdrop,
        .modal-backdrop-custom {
            position: fixed;
            inset: 0;
            background: rgba(21, 30, 49, 0.3);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1200;
        }

        .drawer-backdrop.visible,
        .modal-backdrop-custom.visible {
            opacity: 1;
            pointer-events: auto;
        }

        .editor-drawer {
            position: fixed;
            top: 0;
            right: 0;
            width: min(420px, 100%);
            height: 100vh;
            background: #ffffff;
            box-shadow: -18px 0 48px rgba(14, 21, 38, 0.16);
            transform: translateX(100%);
            transition: transform 0.24s ease;
            z-index: 1201;
            display: flex;
            flex-direction: column;
        }

        .editor-drawer.visible {
            transform: translateX(0);
        }

        .drawer-header,
        .drawer-body,
        .drawer-footer,
        .modal-panel {
            padding: 18px;
        }

        .drawer-header {
            border-bottom: 1px solid var(--ops-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .drawer-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .drawer-subtitle {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--ops-muted);
        }

        .drawer-body {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .drawer-footer {
            border-top: 1px solid var(--ops-border);
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #44516c;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid var(--ops-border);
            border-radius: 12px;
            background: #fbfcff;
        }

        .drawer-meta {
            padding: 12px;
            border-radius: 12px;
            background: #f7f9fc;
            border: 1px solid #e8edf5;
            font-size: 12px;
            color: var(--ops-muted);
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .modal-shell {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1301;
        }

        .modal-shell.visible {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-panel {
            width: min(480px, 100%);
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid var(--ops-border);
            box-shadow: 0 24px 60px rgba(16, 25, 45, 0.18);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .modal-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .modal-subtitle {
            margin: 4px 0 0;
            font-size: 12px;
            color: var(--ops-muted);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .close-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            border: 1px solid var(--ops-border);
            background: #ffffff;
            color: #58657f;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .empty-state {
            border: 1px dashed #d7dfec;
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            color: var(--ops-muted);
            background: #fbfcff;
            font-size: 13px;
        }

        .inline-message {
            display: none;
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .inline-message.visible {
            display: block;
        }

        .inline-message.success {
            background: #eaf8ef;
            color: #1d8a52;
            border: 1px solid #d4ecd9;
        }

        .inline-message.error {
            background: #fff0f2;
            color: #c74b61;
            border: 1px solid #ffd8de;
        }

        @media (max-width: 1280px) {
            .area-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 991px) {
            .editor-grid {
                grid-template-columns: 1fr;
            }

            .tools-card {
                position: static;
            }

            .inspector-card {
                position: static;
                min-height: auto;
            }

            .area-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 640px) {
            .page-shell {
                padding: 16px;
            }

            .metrics-grid,
            .area-grid,
            .form-grid,
            .drawer-meta {
                grid-template-columns: 1fr;
            }

            .search-input {
                width: 100%;
            }

            .inventory-controls {
                width: 100%;
            }

            .inventory-controls > * {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/admin-sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/admin-topbar.php'; ?>

        <div class="page-shell">
            <section class="ops-card page-header">
                <div class="page-header-main">
                    <div class="page-icon"><i class="fa-solid fa-chair"></i></div>
                    <div>
                        <h1 class="page-title">Table Operations</h1>
                        <p class="page-subtitle">Configure your venue layout, table setup, and section structure</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button type="button" class="ops-button" id="openAddTableButton"><i class="fa-solid fa-plus"></i> Add Table</button>
                    <button type="button" class="ops-button primary" id="saveLayoutButton"><i class="fa-solid fa-floppy-disk"></i> Save Layout</button>
                </div>
            </section>

            <section class="metrics-grid" id="metricsGrid"></section>

            <section class="ops-card section-card">
                <div class="section-header">
                    <h2 class="section-title">Area Overview</h2>
                    <button type="button" class="ops-button small" id="openAddAreaButton"><i class="fa-solid fa-plus"></i> Add Area</button>
                </div>
                <div class="area-grid" id="areaOverviewGrid"></div>
            </section>

            <section class="editor-grid">
                <aside class="ops-card tools-card">
                    <div class="tools-stack">
                        <div>
                            <h3 class="stack-title">Layout Tools</h3>
                            <button type="button" class="ops-button primary w-100" id="sidebarAddTableButton"><i class="fa-solid fa-plus"></i> Add Table to Layout</button>
                        </div>
                        <div>
                            <h3 class="stack-title">Sections</h3>
                            <div class="section-list" id="sectionList"></div>
                        </div>
                        <div>
                            <h3 class="stack-title">Quick Settings</h3>
                            <div class="toggle-list">
                                <label class="toggle-row">
                                    <span>Show Grid</span>
                                    <span class="switch"><input type="checkbox" id="toggleGrid" checked><span class="slider"></span></span>
                                </label>
                                <label class="toggle-row">
                                    <span>Snap to Grid</span>
                                    <span class="switch"><input type="checkbox" id="toggleSnap" checked><span class="slider"></span></span>
                                </label>
                                <label class="toggle-row">
                                    <span>Show Labels</span>
                                    <span class="switch"><input type="checkbox" id="toggleLabels" checked><span class="slider"></span></span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <h3 class="stack-title">How to Use</h3>
                            <ul class="helper-list">
                                <li><i class="fa-solid fa-hand-pointer"></i><span>Drag tables to reposition them inside the layout canvas.</span></li>
                                <li><i class="fa-solid fa-pen-to-square"></i><span>Click a table to edit its details, capacity, area, and reservable state.</span></li>
                                <li><i class="fa-solid fa-floppy-disk"></i><span>Save your layout when you are done so positions are written back to the database.</span></li>
                            </ul>
                        </div>
                    </div>
                </aside>

                <div class="ops-card editor-card">
                    <div class="editor-header">
                        <h2 class="section-title">Visual Layout Editor</h2>
                        <div class="editor-tools">
                            <span class="dirty-pill" id="dirtyPill">Unsaved changes</span>
                            <button type="button" class="ops-button small ghost" id="fitViewButton"><i class="fa-solid fa-up-right-and-down-left-from-center"></i> Fit View</button>
                            <button type="button" class="ops-button small ghost" id="resetLayoutButton"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                        </div>
                    </div>
                    <div class="inline-message" id="layoutMessage"></div>
                    <div class="canvas-shell" id="canvasShell">
                        <div class="layout-canvas show-grid" id="layoutCanvas"></div>
                    </div>
                    <div class="canvas-tip"><i class="fa-solid fa-lightbulb"></i> Tip: Click any table to edit. Drag to move. Changes are saved when you click Save Layout.</div>
                </div>

                <aside class="ops-card inspector-card" id="tableInspectorPanel">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title">Table Details</h2>
                            <p class="inspector-subtitle">Select a table to edit its settings.</p>
                        </div>
                        <button type="button" class="close-icon" id="clearSelectionButton" hidden><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="empty-state" id="inspectorEmpty">Click a table on the floor plan to edit its name, capacity, area, and reservable state.</div>
                    <form class="inspector-form" id="tableDrawerForm" hidden>
                        <div class="drawer-meta" id="drawerMeta"></div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="drawerTableNumber">Table Name</label>
                                <input type="text" class="form-input" id="drawerTableNumber" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="drawerTableCapacity">Capacity</label>
                                <input type="number" min="1" class="form-number" id="drawerTableCapacity" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="drawerTableArea">Area</label>
                                <select class="form-select" id="drawerTableArea" required></select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="drawerTableShape">Shape</label>
                                <select class="form-select" id="drawerTableShape">
                                    <option value="auto">Auto</option>
                                    <option value="circle">Round</option>
                                    <option value="rect">Rectangle</option>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label class="form-label">Availability</label>
                                <label class="checkbox-row"><input type="checkbox" id="drawerTableReservable"> <span>Reservable for bookings</span></label>
                            </div>
                        </div>
                        <div class="inspector-actions">
                            <button type="submit" class="ops-button primary w-100">Apply Changes</button>
                            <button type="button" class="ops-button danger w-100" id="deleteTableButton"><i class="fa-solid fa-trash-can"></i> Delete Table</button>
                        </div>
                    </form>
                </aside>
            </section>

            <section class="ops-card inventory-card">
                <div class="section-header">
                    <h2 class="section-title">Table Inventory</h2>
                </div>
                <div class="inventory-toolbar">
                    <div class="inventory-controls">
                        <input type="search" class="search-input" id="inventorySearch" placeholder="Search tables...">
                        <select class="filter-select" id="inventoryAreaFilter"></select>
                    </div>
                    <div class="inventory-controls">
                        <button type="button" class="ops-button small" id="exportInventoryButton"><i class="fa-solid fa-download"></i> Export</button>
                    </div>
                </div>
                <div class="inventory-table-wrap">
                    <table class="inventory-table">
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
                        <tbody id="inventoryTableBody"></tbody>
                    </table>
                </div>
                <div class="inventory-footer">
                    <div id="inventorySummary" class="page-subtitle"></div>
                    <div class="pager" id="inventoryPager"></div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal-backdrop-custom" id="modalBackdrop"></div>

<div class="modal-shell" id="tableModalShell">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Add Table</h3>
                <p class="modal-subtitle">Create a new table and place it into the visual layout.</p>
            </div>
            <button type="button" class="close-icon" data-close-modal="table"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="inline-message" id="tableModalMessage"></div>
        <form id="addTableForm">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="newTableNumber">Table Name</label>
                    <input type="text" class="form-input" id="newTableNumber" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="newTableCapacity">Capacity</label>
                    <input type="number" min="1" class="form-number" id="newTableCapacity" value="4" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="newTableArea">Area</label>
                    <select class="form-select" id="newTableArea" required></select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="newTableShape">Shape</label>
                    <select class="form-select" id="newTableShape">
                        <option value="auto">Auto</option>
                        <option value="circle">Circle</option>
                        <option value="rect">Rectangle</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label class="form-label">Availability</label>
                    <label class="checkbox-row"><input type="checkbox" id="newTableReservable" checked> <span>Reservable for bookings</span></label>
                </div>
            </div>
        </form>
        <div class="modal-actions">
            <button type="button" class="ops-button" data-close-modal="table">Cancel</button>
            <button type="submit" form="addTableForm" class="ops-button primary">Create Table</button>
        </div>
    </div>
</div>

<div class="modal-shell" id="areaModalShell">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="areaModalTitle">Add Area</h3>
                <p class="modal-subtitle">Create or update an area used in the venue layout.</p>
            </div>
            <button type="button" class="close-icon" data-close-modal="area"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="inline-message" id="areaModalMessage"></div>
        <form id="areaForm">
            <input type="hidden" id="areaFormId">
            <div class="form-grid">
                <div class="form-group full">
                    <label class="form-label" for="areaName">Area Name</label>
                    <input type="text" class="form-input" id="areaName" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="areaStart">Table Range Start</label>
                    <input type="number" min="1" class="form-number" id="areaStart">
                </div>
                <div class="form-group">
                    <label class="form-label" for="areaEnd">Table Range End</label>
                    <input type="number" min="1" class="form-number" id="areaEnd">
                </div>
            </div>
        </form>
        <div class="modal-actions" id="areaModalActions">
            <button type="button" class="ops-button" data-close-modal="area">Cancel</button>
            <button type="submit" form="areaForm" class="ops-button primary">Save Area</button>
        </div>
    </div>
</div>

<script>
const AREA_TONES = [
    { icon: 'fa-chair', cardIconBg: '#edf3ff', cardIconColor: '#3c6ddd', rangeBg: '#edf3ff', rangeColor: '#3c6ddd', zoneBg: 'rgba(76, 124, 239, 0.08)', zoneBorder: '#8fb0ff', zoneText: '#4b6fcb', tableBg: '#dbe8ff', tableText: '#2c4ea9', areaBadgeBg: '#edf3ff', areaBadgeColor: '#3c6ddd' },
    { icon: 'fa-champagne-glasses', cardIconBg: '#f3efff', cardIconColor: '#7f63e4', rangeBg: '#f3efff', rangeColor: '#7f63e4', zoneBg: 'rgba(136, 98, 237, 0.08)', zoneBorder: '#c6b4ff', zoneText: '#7a5fdd', tableBg: '#e4dbff', tableText: '#6c4ad2', areaBadgeBg: '#f3efff', areaBadgeColor: '#7f63e4' },
    { icon: 'fa-tree', cardIconBg: '#e8fbf0', cardIconColor: '#28a86a', rangeBg: '#ebfaf1', rangeColor: '#23955e', zoneBg: 'rgba(42, 181, 111, 0.08)', zoneBorder: '#97ddb8', zoneText: '#27995f', tableBg: '#ccf3db', tableText: '#1f8d58', areaBadgeBg: '#e8fbf0', areaBadgeColor: '#27995f' },
    { icon: 'fa-martini-glass-citrus', cardIconBg: '#fff2e8', cardIconColor: '#f08433', rangeBg: '#fff2e8', rangeColor: '#df7a2d', zoneBg: 'rgba(240, 132, 51, 0.08)', zoneBorder: '#ffc798', zoneText: '#dc7b32', tableBg: '#ffe2c8', tableText: '#d46d21', areaBadgeBg: '#fff2e8', areaBadgeColor: '#dc7b32' },
    { icon: 'fa-umbrella-beach', cardIconBg: '#ebfbff', cardIconColor: '#25a4cf', rangeBg: '#ebfbff', rangeColor: '#1998c5', zoneBg: 'rgba(35, 169, 211, 0.08)', zoneBorder: '#9fdff3', zoneText: '#2296be', tableBg: '#d2f2fb', tableText: '#188bb1', areaBadgeBg: '#ebfbff', areaBadgeColor: '#1e93bd' },
    { icon: 'fa-star', cardIconBg: '#fff8e8', cardIconColor: '#c99518', rangeBg: '#fff8e8', rangeColor: '#b17f10', zoneBg: 'rgba(219, 172, 39, 0.08)', zoneBorder: '#f2d489', zoneText: '#af7d11', tableBg: '#ffeeb8', tableText: '#9d6b03', areaBadgeBg: '#fff8e8', areaBadgeColor: '#a87407' }
];

const AREA_ZONE_PRESETS = [
    { key: 'patio', x: 28, y: 22, width: 924, height: 82 },
    { key: 'main', x: 38, y: 122, width: 372, height: 188 },
    { key: 'wisteria', x: 426, y: 122, width: 226, height: 152 },
    { key: 'stables', x: 38, y: 334, width: 292, height: 102 },
    { key: 'bar', x: 348, y: 334, width: 330, height: 102 },
    { key: 'kookaburra', x: 694, y: 122, width: 226, height: 152 },
    { key: 'fallback-1', x: 694, y: 22, width: 226, height: 82 },
    { key: 'fallback-2', x: 694, y: 334, width: 226, height: 102 }
];

let areas = <?php echo json_encode($areaCards, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let tables = <?php echo json_encode($tablesForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

let activeSectionFilter = 'all';
let selectedTableId = null;
let inventorySearch = '';
let inventoryAreaFilter = 'all';
let inventoryPage = 1;
let dirty = tables.some(table => table.layout_x === null || table.layout_y === null);
let dragState = null;
let showGrid = true;
let snapToGrid = true;
let showLabels = true;
let editingAreaId = null;

const inventoryPageSize = 7;
const metricsGrid = document.getElementById('metricsGrid');
const areaOverviewGrid = document.getElementById('areaOverviewGrid');
const sectionList = document.getElementById('sectionList');
const layoutCanvas = document.getElementById('layoutCanvas');
const canvasShell = document.getElementById('canvasShell');
const dirtyPill = document.getElementById('dirtyPill');
const layoutMessage = document.getElementById('layoutMessage');
const inventoryBody = document.getElementById('inventoryTableBody');
const inventorySummary = document.getElementById('inventorySummary');
const inventoryPager = document.getElementById('inventoryPager');
const inventorySearchInput = document.getElementById('inventorySearch');
const inventoryAreaFilterSelect = document.getElementById('inventoryAreaFilter');
const saveLayoutButton = document.getElementById('saveLayoutButton');
const inspectorPanel = document.getElementById('tableInspectorPanel');
const inspectorEmpty = document.getElementById('inspectorEmpty');
const clearSelectionButton = document.getElementById('clearSelectionButton');
const drawerMeta = document.getElementById('drawerMeta');
const drawerTableNumber = document.getElementById('drawerTableNumber');
const drawerTableCapacity = document.getElementById('drawerTableCapacity');
const drawerTableArea = document.getElementById('drawerTableArea');
const drawerTableShape = document.getElementById('drawerTableShape');
const drawerTableReservable = document.getElementById('drawerTableReservable');
const tableModalShell = document.getElementById('tableModalShell');
const areaModalShell = document.getElementById('areaModalShell');
const modalBackdrop = document.getElementById('modalBackdrop');
const tableModalMessage = document.getElementById('tableModalMessage');
const areaModalMessage = document.getElementById('areaModalMessage');

function escapeHtml(value) {
    return `${value ?? ''}`
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatTableLabel(tableNumber) {
    const rawValue = `${tableNumber ?? ''}`.trim();
    if (rawValue === '') {
        return 'Table';
    }
    return /^t/i.test(rawValue) ? rawValue.toUpperCase() : `T${rawValue}`;
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function snapValue(value) {
    if (!snapToGrid) {
        return value;
    }
    return Math.round(value / 16) * 16;
}

function showInlineMessage(element, text, type = 'success') {
    element.className = `inline-message visible ${type}`;
    element.textContent = text;
}

function clearInlineMessage(element) {
    element.className = 'inline-message';
    element.textContent = '';
}

function sortAreas(left, right) {
    const orderDiff = Number(left.display_order || 0) - Number(right.display_order || 0);
    if (orderDiff !== 0) {
        return orderDiff;
    }
    return `${left.name || ''}`.localeCompare(`${right.name || ''}`, undefined, { sensitivity: 'base', numeric: true });
}

function sortTables(left, right) {
    const areaOrderDiff = Number(left.area_display_order || 0) - Number(right.area_display_order || 0);
    if (areaOrderDiff !== 0) {
        return areaOrderDiff;
    }
    const sortDiff = Number(left.sort_order || 0) - Number(right.sort_order || 0);
    if (sortDiff !== 0) {
        return sortDiff;
    }
    return `${left.table_number || ''}`.localeCompare(`${right.table_number || ''}`, undefined, { sensitivity: 'base', numeric: true });
}

function getOrderedAreas() {
    return [...areas].sort(sortAreas);
}

function getAreaById(areaId) {
    return areas.find(area => Number(area.area_id) === Number(areaId)) || null;
}

function getTableById(tableId) {
    return tables.find(table => Number(table.table_id) === Number(tableId)) || null;
}

function getAreaTone(areaId) {
    const index = getOrderedAreas().findIndex(area => Number(area.area_id) === Number(areaId));
    return AREA_TONES[(index >= 0 ? index : 0) % AREA_TONES.length];
}

function getZoneKeyForArea(area) {
    const name = `${area.name || ''}`.toLowerCase();
    if (name.includes('osf') || name.includes('patio')) return 'patio';
    if (name.includes('main') || name.includes('dining')) return 'main';
    if (name.includes('wisteria')) return 'wisteria';
    if (name.includes('stable')) return 'stables';
    if (name.includes('bar')) return 'bar';
    if (name.includes('kookaburra')) return 'kookaburra';
    return '';
}

function buildAreaZones() {
    const usedPresetKeys = new Set();
    return getOrderedAreas().map(area => {
        let preset = AREA_ZONE_PRESETS.find(item => item.key === getZoneKeyForArea(area) && !usedPresetKeys.has(item.key));
        if (!preset) {
            preset = AREA_ZONE_PRESETS.find(item => !usedPresetKeys.has(item.key)) || AREA_ZONE_PRESETS[AREA_ZONE_PRESETS.length - 1];
        }
        usedPresetKeys.add(preset.key);
        return {
            area_id: Number(area.area_id),
            x: preset.x,
            y: preset.y,
            width: preset.width,
            height: preset.height,
            tone: getAreaTone(area.area_id),
        };
    });
}

function getZoneForArea(areaId) {
    return buildAreaZones().find(zone => Number(zone.area_id) === Number(areaId)) || null;
}

function getAreaTables(areaId, excludingTableId = null) {
    return tables
        .filter(table => Number(table.area_id) === Number(areaId) && Number(table.table_id) !== Number(excludingTableId || 0))
        .sort(sortTables);
}

function getDefaultPosition(areaId, excludingTableId = null) {
    const zone = getZoneForArea(areaId);
    const existingTables = getAreaTables(areaId, excludingTableId);
    if (!zone) {
        return { x: 120, y: 120 };
    }
    const columns = Math.max(1, Math.floor((zone.width - 48) / 120));
    const index = existingTables.length;
    const column = index % columns;
    const row = Math.floor(index / columns);
    return {
        x: clamp(zone.x + 56 + (column * 110), zone.x + 42, zone.x + zone.width - 42),
        y: clamp(zone.y + 50 + (row * 82), zone.y + 42, zone.y + zone.height - 42),
    };
}

function getNormalizedTablePosition(table) {
    const fallback = getDefaultPosition(table.area_id, table.table_id);
    return {
        x: table.layout_x === null ? fallback.x : Number(table.layout_x),
        y: table.layout_y === null ? fallback.y : Number(table.layout_y),
    };
}

function getDerivedShape(table) {
    if (table.table_shape === 'circle' || table.table_shape === 'rect') {
        return table.table_shape;
    }
    return Number(table.capacity || 0) <= 4 ? 'circle' : 'rect';
}

function getTableDimensions(table) {
    const shape = getDerivedShape(table);
    if (shape === 'circle') {
        return { width: 66, height: 66, className: 'circle' };
    }
    if (Number(table.capacity || 0) >= 8) {
        return { width: 118, height: 46, className: 'rect large' };
    }
    return { width: 90, height: 40, className: 'rect' };
}

function buildAreaRangeLabel(areaId) {
    const area = getAreaById(areaId);
    if (!area) {
        return 'Manual layout';
    }
    if (area.table_number_start !== null && area.table_number_end !== null) {
        return `T${area.table_number_start} - T${area.table_number_end}`;
    }
    const areaTables = getAreaTables(areaId);
    if (!areaTables.length) {
        return 'Manual layout';
    }
    const firstTable = areaTables[0].table_number;
    const lastTable = areaTables[areaTables.length - 1].table_number;
    return `T${firstTable}${firstTable !== lastTable ? ` - T${lastTable}` : ''}`;
}

function recomputeSortOrders() {
    getOrderedAreas().forEach(area => {
        const areaTables = tables
            .filter(table => Number(table.area_id) === Number(area.area_id))
            .sort((left, right) => {
                const leftPos = getNormalizedTablePosition(left);
                const rightPos = getNormalizedTablePosition(right);
                if (leftPos.y !== rightPos.y) {
                    return leftPos.y - rightPos.y;
                }
                return leftPos.x - rightPos.x;
            });

        areaTables.forEach((table, index) => {
            table.sort_order = (index + 1) * 10;
            table.area_display_order = Number(area.display_order || 0);
            table.area_name = area.name;
        });
    });
}

function updateDirtyPill() {
    const hasUnplaced = tables.some(table => table.layout_x === null || table.layout_y === null || table.is_unplaced);
    const isDirty = dirty || hasUnplaced;
    dirtyPill.className = `dirty-pill${isDirty ? '' : ' clean'}`;
    dirtyPill.textContent = isDirty ? 'Unsaved changes' : 'Layout saved';
}

function renderMetrics() {
    const totalTables = tables.length;
    const totalSeats = tables.reduce((sum, table) => sum + Number(table.capacity || 0), 0);
    const activeAreas = areas.length;
    const unplaced = tables.filter(table => table.layout_x === null || table.layout_y === null || table.is_unplaced).length;

    const cards = [
        { iconClass: 'tables', icon: 'fa-chair', label: 'Total Tables', value: totalTables, note: `${activeAreas} active areas` },
        { iconClass: 'seats', icon: 'fa-users', label: 'Total Seats', value: totalSeats, note: 'Venue capacity' },
        { iconClass: 'areas', icon: 'fa-border-all', label: 'Active Areas', value: activeAreas, note: 'Venue sections' },
        { iconClass: 'unassigned', icon: 'fa-circle-dot', label: 'Unassigned Tables', value: unplaced, note: 'Not placed in layout' }
    ];

    metricsGrid.innerHTML = cards.map(card => `
        <div class="ops-card metric-card">
            <div class="metric-icon ${card.iconClass}"><i class="fa-solid ${card.icon}"></i></div>
            <div>
                <div class="metric-label">${card.label}</div>
                <div class="metric-value">${card.value}</div>
                <p class="metric-note">${card.note}</p>
            </div>
        </div>
    `).join('');
}

function renderAreaOverview() {
    if (!areas.length) {
        areaOverviewGrid.innerHTML = '<div class="empty-state">No active areas are configured yet.</div>';
        return;
    }

    areaOverviewGrid.innerHTML = getOrderedAreas().map(area => {
        const tone = getAreaTone(area.area_id);
        const areaTables = getAreaTables(area.area_id);
        const placedCount = areaTables.filter(table => table.layout_x !== null && table.layout_y !== null && !table.is_unplaced).length;
        const totalSeats = areaTables.reduce((sum, table) => sum + Number(table.capacity || 0), 0);
        return `
            <div class="area-card">
                <div class="area-card-top">
                    <div class="area-icon" style="background:${tone.cardIconBg};color:${tone.cardIconColor};"><i class="fa-solid ${tone.icon}"></i></div>
                    <div>
                        <h3 class="area-name">${escapeHtml(area.name)}</h3>
                        <p class="area-meta">${areaTables.length} Tables</p>
                        <p class="area-meta">${totalSeats} Seats</p>
                    </div>
                    <div class="area-card-summary">
                        <span class="area-utilization">${areaTables.length ? Math.round((placedCount / areaTables.length) * 100) : 0}% placed</span>
                    </div>
                </div>
                <span class="area-range" style="background:${tone.rangeBg};color:${tone.rangeColor};">${escapeHtml(area.range_label)}</span>
                <button type="button" class="ops-button small area-manage" onclick="openAreaModal(${Number(area.area_id)})">Manage Area</button>
            </div>
        `;
    }).join('');
}

function renderSectionList() {
    const allSections = [{ area_id: 'all', name: 'All Areas' }, ...getOrderedAreas()];
    sectionList.innerHTML = allSections.map(section => {
        const area = Number(section.area_id) ? getAreaById(section.area_id) : null;
        const tone = area ? getAreaTone(area.area_id) : AREA_TONES[0];
        const activeClass = String(activeSectionFilter) === String(section.area_id) ? ' active' : '';
        return `
            <button type="button" class="section-chip${activeClass}" onclick="setSectionFilter('${section.area_id}')">
                <span class="chip-dot" style="background:${tone.cardIconColor};"></span>
                <span>${escapeHtml(section.name)}</span>
            </button>
        `;
    }).join('');
}

function renderCanvas() {
    const zones = buildAreaZones();
    const barZone = zones.find(zone => {
        const area = getAreaById(zone.area_id);
        return area && /bar/i.test(area.name);
    });
    const decorMarkup = `
        <div class="canvas-decor decor-plant" style="left:18px;top:110px;"><i class="fa-solid fa-seedling"></i></div>
        <div class="canvas-decor decor-plant" style="right:24px;top:18px;"><i class="fa-solid fa-seedling"></i></div>
        <div class="canvas-decor decor-plant" style="right:42px;bottom:54px;"><i class="fa-solid fa-seedling"></i></div>
        ${barZone ? `<div class="bar-counter" style="left:${barZone.x + 46}px;top:${barZone.y + barZone.height - 44}px;width:${Math.max(140, barZone.width - 92)}px;">Bar Counter</div>` : ''}
    `;
    const zoneMarkup = zones
        .filter(zone => activeSectionFilter === 'all' || String(activeSectionFilter) === String(zone.area_id))
        .map(zone => {
            const area = getAreaById(zone.area_id);
            return `
                <div class="zone-card" style="left:${zone.x}px;top:${zone.y}px;width:${zone.width}px;height:${zone.height}px;--zone-bg:${zone.tone.zoneBg};--zone-border:${zone.tone.zoneBorder};--zone-text:${zone.tone.zoneText};">
                    <div class="zone-title">${escapeHtml(area ? area.name : 'Area')}</div>
                </div>
            `;
        }).join('');

    const tableMarkup = [...tables]
        .filter(table => activeSectionFilter === 'all' || String(activeSectionFilter) === String(table.area_id))
        .sort(sortTables)
        .map(table => {
            const tone = getAreaTone(table.area_id);
            const position = getNormalizedTablePosition(table);
            const dims = getTableDimensions(table);
            const shape = getDerivedShape(table);
            const classes = `${dims.className}${selectedTableId === Number(table.table_id) ? ' selected' : ''}${table.is_unplaced ? ' unplaced' : ''}${Number(table.reservable) ? '' : ' nonreservable'}`;
            return `
                <button type="button" class="layout-table ${classes}" data-table-id="${Number(table.table_id)}" style="left:${position.x}px;top:${position.y}px;--table-bg:${tone.tableBg};--table-text:${tone.tableText};width:${shape === 'circle' ? dims.width + 'px' : 'auto'};">
                    <span class="table-label"${showLabels ? '' : ' style="display:none;"'}>
                        <span class="table-name">${escapeHtml(formatTableLabel(table.table_number))}</span>
                        <span class="table-capacity">${Number(table.capacity)}p</span>
                    </span>
                </button>
            `;
        }).join('');

    layoutCanvas.classList.toggle('show-grid', showGrid);
    layoutCanvas.innerHTML = `${decorMarkup}${zoneMarkup}${tableMarkup}`;

    layoutCanvas.querySelectorAll('.layout-table').forEach(button => {
        button.addEventListener('mousedown', handleTableMouseDown);
        button.addEventListener('click', function () {
            if (dragState && dragState.didMove) {
                return;
            }
            openDrawer(Number(button.dataset.tableId));
        });
    });
}

function renderInventoryFilterOptions() {
    inventoryAreaFilterSelect.innerHTML = ['<option value="all">All Areas</option>']
        .concat(getOrderedAreas().map(area => `<option value="${Number(area.area_id)}">${escapeHtml(area.name)}</option>`))
        .join('');
    inventoryAreaFilterSelect.value = inventoryAreaFilter;
}

function getFilteredInventoryRows() {
    const searchTerm = inventorySearch.trim().toLowerCase();
    return [...tables]
        .filter(table => inventoryAreaFilter === 'all' || String(table.area_id) === String(inventoryAreaFilter))
        .filter(table => {
            if (!searchTerm) {
                return true;
            }
            const area = getAreaById(table.area_id);
            return [formatTableLabel(table.table_number), area ? area.name : '', `${table.capacity}`].join(' ').toLowerCase().includes(searchTerm);
        })
        .sort(sortTables);
}

function renderInventory() {
    renderInventoryFilterOptions();
    const filteredRows = getFilteredInventoryRows();
    const pageCount = Math.max(1, Math.ceil(filteredRows.length / inventoryPageSize));
    inventoryPage = clamp(inventoryPage, 1, pageCount);

    const startIndex = (inventoryPage - 1) * inventoryPageSize;
    const visibleRows = filteredRows.slice(startIndex, startIndex + inventoryPageSize);

    if (!visibleRows.length) {
        inventoryBody.innerHTML = '<tr><td colspan="6"><div class="empty-state">No tables match the current filter.</div></td></tr>';
    } else {
        inventoryBody.innerHTML = visibleRows.map(table => {
            const area = getAreaById(table.area_id);
            const tone = getAreaTone(table.area_id);
            const position = getNormalizedTablePosition(table);
            return `
                <tr>
                    <td>${escapeHtml(formatTableLabel(table.table_number))}</td>
                    <td>${Number(table.capacity)} seats</td>
                    <td><span class="area-badge" style="background:${tone.areaBadgeBg};color:${tone.areaBadgeColor};">${escapeHtml(area ? area.name : table.area_name)}</span></td>
                    <td><span class="status-badge ${Number(table.reservable) ? 'yes' : 'no'}">${Number(table.reservable) ? 'Yes' : 'No'}</span></td>
                    <td><span class="position-badge">${Math.round(position.x)}, ${Math.round(position.y)}</span></td>
                    <td>
                        <div class="inventory-actions">
                            <button type="button" class="ops-button small" onclick="openDrawer(${Number(table.table_id)})"><i class="fa-solid fa-pen"></i> Edit</button>
                            <button type="button" class="ops-button small" onclick="deleteTable(${Number(table.table_id)})"><i class="fa-solid fa-trash-can"></i> Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    inventorySummary.textContent = filteredRows.length
        ? `Showing ${startIndex + 1}-${Math.min(startIndex + inventoryPageSize, filteredRows.length)} of ${filteredRows.length} tables`
        : 'Showing 0 tables';

    inventoryPager.innerHTML = Array.from({ length: pageCount }, (_, index) => index + 1)
        .map(pageNumber => `<button type="button" class="page-link-button${pageNumber === inventoryPage ? ' active' : ''}" onclick="setInventoryPage(${pageNumber})">${pageNumber}</button>`)
        .join('');
}

function updateDrawerAreaOptions() {
    drawerTableArea.innerHTML = getOrderedAreas().map(area => `<option value="${Number(area.area_id)}">${escapeHtml(area.name)}</option>`).join('');
}

function updateAddTableAreaOptions() {
    document.getElementById('newTableArea').innerHTML = getOrderedAreas().map(area => `<option value="${Number(area.area_id)}">${escapeHtml(area.name)}</option>`).join('');
}

function rerenderAll() {
    renderMetrics();
    renderAreaOverview();
    renderSectionList();
    renderCanvas();
    renderInventory();
    renderInspector();
    updateDrawerAreaOptions();
    updateAddTableAreaOptions();
    updateDirtyPill();
}

function setSectionFilter(areaId) {
    activeSectionFilter = areaId;
    renderSectionList();
    renderCanvas();
}

function setInventoryPage(pageNumber) {
    inventoryPage = pageNumber;
    renderInventory();
}

function renderInspector() {
    const table = getTableById(selectedTableId);
    if (!table) {
        inspectorEmpty.hidden = false;
        document.getElementById('tableDrawerForm').hidden = true;
        clearSelectionButton.hidden = true;
        return;
    }

    const area = getAreaById(table.area_id);
    const position = getNormalizedTablePosition(table);
    inspectorEmpty.hidden = true;
    document.getElementById('tableDrawerForm').hidden = false;
    clearSelectionButton.hidden = false;
    drawerMeta.innerHTML = `
        <div><strong>Table</strong><br>${escapeHtml(formatTableLabel(table.table_number))}</div>
        <div><strong>Position</strong><br>${Math.round(position.x)}, ${Math.round(position.y)}</div>
        <div><strong>Area</strong><br>${escapeHtml(area ? area.name : table.area_name)}</div>
        <div><strong>Status</strong><br>${Number(table.reservable) ? 'Reservable' : 'Hidden from bookings'}</div>
    `;
    drawerTableNumber.value = table.table_number;
    drawerTableCapacity.value = table.capacity;
    drawerTableArea.value = String(table.area_id);
    drawerTableShape.value = table.table_shape || 'auto';
    drawerTableReservable.checked = Boolean(Number(table.reservable));
}

function openDrawer(tableId) {
    const table = getTableById(tableId);
    if (!table) {
        return;
    }
    selectedTableId = tableId;
    renderInspector();
    renderCanvas();
}

function closeDrawer() {
    selectedTableId = null;
    renderInspector();
    renderCanvas();
}

function applyDrawerChanges(event) {
    event.preventDefault();
    const table = getTableById(selectedTableId);
    if (!table) {
        return;
    }
    const previousAreaId = Number(table.area_id);
    const nextAreaId = Number(drawerTableArea.value);
    table.table_number = drawerTableNumber.value.trim();
    table.capacity = Math.max(1, Number(drawerTableCapacity.value) || 1);
    table.area_id = nextAreaId;
    table.reservable = drawerTableReservable.checked ? 1 : 0;
    table.table_shape = drawerTableShape.value;
    const area = getAreaById(nextAreaId);
    if (area) {
        table.area_name = area.name;
        table.area_display_order = Number(area.display_order || 0);
    }
    if (previousAreaId !== nextAreaId) {
        const nextPosition = getDefaultPosition(nextAreaId, table.table_id);
        table.layout_x = nextPosition.x;
        table.layout_y = nextPosition.y;
    }
    table.is_unplaced = false;
    dirty = true;
    recomputeSortOrders();
    closeDrawer();
    rerenderAll();
}

function openModal(type) {
    modalBackdrop.classList.add('visible');
    if (type === 'table') {
        clearInlineMessage(tableModalMessage);
        tableModalShell.classList.add('visible');
    }
    if (type === 'area') {
        clearInlineMessage(areaModalMessage);
        areaModalShell.classList.add('visible');
    }
}

function closeModal(type) {
    if (type === 'table') {
        tableModalShell.classList.remove('visible');
    }
    if (type === 'area') {
        areaModalShell.classList.remove('visible');
    }
    if (!tableModalShell.classList.contains('visible') && !areaModalShell.classList.contains('visible')) {
        modalBackdrop.classList.remove('visible');
    }
}

function openAddTableModal() {
    document.getElementById('addTableForm').reset();
    updateAddTableAreaOptions();
    const firstArea = getOrderedAreas()[0];
    if (firstArea) {
        document.getElementById('newTableArea').value = String(firstArea.area_id);
    }
    document.getElementById('newTableCapacity').value = 4;
    document.getElementById('newTableReservable').checked = true;
    openModal('table');
}

function openAreaModal(areaId = null) {
    editingAreaId = areaId;
    document.getElementById('areaForm').reset();
    document.getElementById('areaFormId').value = areaId ? String(areaId) : '';
    document.getElementById('areaModalTitle').textContent = areaId ? 'Manage Area' : 'Add Area';
    if (areaId) {
        const area = getAreaById(areaId);
        if (area) {
            document.getElementById('areaName').value = area.name;
            document.getElementById('areaStart').value = area.table_number_start ?? '';
            document.getElementById('areaEnd').value = area.table_number_end ?? '';
        }
    }
    const actionsMarkup = areaId
        ? `<button type="button" class="ops-button" onclick="deleteArea(${Number(areaId)})"><i class="fa-solid fa-trash-can"></i> Delete Area</button>`
        : '';
    document.getElementById('areaModalActions').innerHTML = `${actionsMarkup}<button type="button" class="ops-button" data-close-modal="area">Cancel</button><button type="submit" form="areaForm" class="ops-button primary">Save Area</button>`;
    document.querySelectorAll('[data-close-modal="area"]').forEach(button => button.addEventListener('click', function () { closeModal('area'); }));
    openModal('area');
}

function findZoneAtPoint(x, y) {
    return buildAreaZones().find(zone => x >= zone.x && x <= zone.x + zone.width && y >= zone.y && y <= zone.y + zone.height) || null;
}

function handleTableMouseDown(event) {
    const button = event.currentTarget;
    const tableId = Number(button.dataset.tableId);
    const table = getTableById(tableId);
    if (!table) {
        return;
    }
    const currentPosition = getNormalizedTablePosition(table);
    dragState = {
        tableId,
        startX: event.clientX,
        startY: event.clientY,
        originX: currentPosition.x,
        originY: currentPosition.y,
        didMove: false,
    };
    button.classList.add('dragging');
    window.addEventListener('mousemove', handleTableMouseMove);
    window.addEventListener('mouseup', handleTableMouseUp);
}

function handleTableMouseMove(event) {
    if (!dragState) {
        return;
    }
    const table = getTableById(dragState.tableId);
    if (!table) {
        return;
    }
    const deltaX = event.clientX - dragState.startX;
    const deltaY = event.clientY - dragState.startY;
    if (!dragState.didMove && Math.abs(deltaX) + Math.abs(deltaY) > 4) {
        dragState.didMove = true;
    }
    const dims = getTableDimensions(table);
    table.layout_x = snapValue(clamp(dragState.originX + deltaX, dims.width / 2, layoutCanvas.clientWidth - dims.width / 2));
    table.layout_y = snapValue(clamp(dragState.originY + deltaY, dims.height / 2, layoutCanvas.clientHeight - dims.height / 2));
    table.is_unplaced = false;
    renderCanvas();
}

function handleTableMouseUp() {
    if (!dragState) {
        return;
    }
    const button = layoutCanvas.querySelector(`.layout-table[data-table-id="${dragState.tableId}"]`);
    if (button) {
        button.classList.remove('dragging');
    }
    const table = getTableById(dragState.tableId);
    if (table && dragState.didMove) {
        const position = getNormalizedTablePosition(table);
        const targetZone = findZoneAtPoint(position.x, position.y) || getZoneForArea(table.area_id);
        if (targetZone) {
            table.area_id = Number(targetZone.area_id);
            const area = getAreaById(targetZone.area_id);
            if (area) {
                table.area_name = area.name;
                table.area_display_order = Number(area.display_order || 0);
            }
            table.is_unplaced = false;
        }
        dirty = true;
        recomputeSortOrders();
        rerenderAll();
    }
    window.removeEventListener('mousemove', handleTableMouseMove);
    window.removeEventListener('mouseup', handleTableMouseUp);
    dragState = null;
}

async function createTable(event) {
    event.preventDefault();
    clearInlineMessage(tableModalMessage);
    const areaId = Number(document.getElementById('newTableArea').value);
    const nextPosition = getDefaultPosition(areaId);
    try {
        const response = await fetch('timeline/create-table.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                table_number: document.getElementById('newTableNumber').value.trim(),
                capacity: Number(document.getElementById('newTableCapacity').value),
                area_id: areaId,
                reservable: document.getElementById('newTableReservable').checked ? 1 : 0,
                table_shape: document.getElementById('newTableShape').value,
                layout_x: nextPosition.x,
                layout_y: nextPosition.y
            })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Failed to create table');
        }
        const area = getAreaById(data.area_id);
        tables.push({
            table_id: Number(data.table_id),
            table_number: `${data.table_number}`,
            capacity: Number(data.capacity),
            area_id: Number(data.area_id),
            sort_order: Number(data.sort_order),
            reservable: Number(data.reservable),
            layout_x: data.layout_x !== null ? Number(data.layout_x) : null,
            layout_y: data.layout_y !== null ? Number(data.layout_y) : null,
            table_shape: data.table_shape || 'auto',
            area_name: area ? area.name : data.area_name,
            area_display_order: Number(data.area_display_order || 0),
            is_unplaced: false,
        });
        if (area) {
            area.table_count += 1;
            area.total_seats += Number(data.capacity);
            area.range_label = buildAreaRangeLabel(area.area_id);
        }
        dirty = true;
        closeModal('table');
        recomputeSortOrders();
        rerenderAll();
    } catch (error) {
        showInlineMessage(tableModalMessage, error.message, 'error');
    }
}

async function saveArea(event) {
    event.preventDefault();
    clearInlineMessage(areaModalMessage);
    const payload = {
        name: document.getElementById('areaName').value.trim(),
        table_number_start: document.getElementById('areaStart').value,
        table_number_end: document.getElementById('areaEnd').value,
    };
    const endpoint = editingAreaId ? 'timeline/update-area.php' : 'timeline/create-area.php';
    if (editingAreaId) {
        payload.area_id = Number(editingAreaId);
    }
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Failed to save area');
        }
        window.location.reload();
    } catch (error) {
        showInlineMessage(areaModalMessage, error.message, 'error');
    }
}

async function deleteArea(areaId) {
    if (!window.confirm('Delete this area and remove its tables?')) {
        return;
    }
    try {
        const response = await fetch('timeline/delete-area.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ area_id: areaId })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Failed to delete area');
        }
        window.location.reload();
    } catch (error) {
        showInlineMessage(areaModalMessage, error.message, 'error');
    }
}

async function deleteTable(tableId) {
    if (!window.confirm('Delete this table from the layout?')) {
        return;
    }
    try {
        const response = await fetch('timeline/delete-table.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ table_id: tableId })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Failed to delete table');
        }
        const deletedTable = getTableById(tableId);
        const deletedAreaId = deletedTable ? Number(deletedTable.area_id) : Number(data.area_id || 0);
        tables = tables.filter(table => Number(table.table_id) !== Number(tableId));
        const area = getAreaById(deletedAreaId);
        if (area && deletedTable) {
            area.table_count = Math.max(0, Number(area.table_count) - 1);
            area.total_seats = Math.max(0, Number(area.total_seats) - Number(deletedTable.capacity || 0));
            area.range_label = buildAreaRangeLabel(deletedAreaId);
        }
        dirty = true;
        if (selectedTableId === Number(tableId)) {
            closeDrawer();
        }
        rerenderAll();
    } catch (error) {
        showInlineMessage(layoutMessage, error.message, 'error');
    }
}

async function saveLayout() {
    clearInlineMessage(layoutMessage);
    saveLayoutButton.disabled = true;
    saveLayoutButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    recomputeSortOrders();
    try {
        const response = await fetch('save-table-layout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tables: tables.map(table => ({
                    table_id: Number(table.table_id),
                    table_number: table.table_number,
                    capacity: Number(table.capacity),
                    area_id: Number(table.area_id),
                    sort_order: Number(table.sort_order || 10),
                    reservable: Number(table.reservable) ? 1 : 0,
                    layout_x: table.layout_x,
                    layout_y: table.layout_y,
                    table_shape: table.table_shape || 'auto',
                }))
            })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Failed to save layout');
        }
        tables.forEach(table => {
            table.is_unplaced = false;
        });
        dirty = false;
        showInlineMessage(layoutMessage, 'Layout saved successfully.', 'success');
        rerenderAll();
    } catch (error) {
        dirty = true;
        showInlineMessage(layoutMessage, error.message, 'error');
    } finally {
        saveLayoutButton.disabled = false;
        saveLayoutButton.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Layout';
    }
}

function resetLayout() {
    tables.forEach(table => {
        const nextPosition = getDefaultPosition(table.area_id, table.table_id);
        table.layout_x = nextPosition.x;
        table.layout_y = nextPosition.y;
        table.is_unplaced = false;
    });
    dirty = true;
    recomputeSortOrders();
    rerenderAll();
}

function fitView() {
    canvasShell.scrollTo({ top: 0, left: 0, behavior: 'smooth' });
}

function exportInventory() {
    const rows = getFilteredInventoryRows();
    const csv = [
        ['Table', 'Capacity', 'Area', 'Reservable'],
        ...rows.map(table => {
            const area = getAreaById(table.area_id);
            return [formatTableLabel(table.table_number), `${table.capacity}`, area ? area.name : table.area_name, Number(table.reservable) ? 'Yes' : 'No'];
        })
    ].map(columns => columns.map(value => `"${`${value}`.replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'table-inventory.csv';
    link.click();
    URL.revokeObjectURL(url);
}

document.getElementById('tableDrawerForm').addEventListener('submit', applyDrawerChanges);
document.getElementById('addTableForm').addEventListener('submit', createTable);
document.getElementById('areaForm').addEventListener('submit', saveArea);
document.getElementById('saveLayoutButton').addEventListener('click', saveLayout);
document.getElementById('openAddTableButton').addEventListener('click', openAddTableModal);
document.getElementById('sidebarAddTableButton').addEventListener('click', openAddTableModal);
document.getElementById('openAddAreaButton').addEventListener('click', function () { openAreaModal(null); });
document.getElementById('fitViewButton').addEventListener('click', fitView);
document.getElementById('resetLayoutButton').addEventListener('click', resetLayout);
clearSelectionButton.addEventListener('click', closeDrawer);
document.getElementById('deleteTableButton').addEventListener('click', function () {
    if (selectedTableId) {
        deleteTable(selectedTableId);
    }
});
document.getElementById('exportInventoryButton').addEventListener('click', exportInventory);
document.getElementById('toggleGrid').addEventListener('change', function (event) {
    showGrid = event.target.checked;
    renderCanvas();
});
document.getElementById('toggleSnap').addEventListener('change', function (event) {
    snapToGrid = event.target.checked;
});
document.getElementById('toggleLabels').addEventListener('change', function (event) {
    showLabels = event.target.checked;
    renderCanvas();
});
inventorySearchInput.addEventListener('input', function (event) {
    inventorySearch = event.target.value;
    inventoryPage = 1;
    renderInventory();
});
inventoryAreaFilterSelect.addEventListener('change', function (event) {
    inventoryAreaFilter = event.target.value;
    inventoryPage = 1;
    renderInventory();
});
modalBackdrop.addEventListener('click', function () {
    closeModal('table');
    closeModal('area');
});
document.querySelectorAll('[data-close-modal="table"]').forEach(button => button.addEventListener('click', function () { closeModal('table'); }));
document.querySelectorAll('[data-close-modal="area"]').forEach(button => button.addEventListener('click', function () { closeModal('area'); }));

rerenderAll();
</script>
</body>
</html>