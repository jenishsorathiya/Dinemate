<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureBookingRequestColumns($pdo);
ensureTableAreasSchema($pdo);

$normalizeAreaName = static function (string $value): string {
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
};

$resolveServicePeriod = static function (?string $timeValue): string {
    if (!$timeValue) {
        return 'Dinner';
    }

    $hour = (int) date('G', strtotime((string) $timeValue));

    if ($hour < 16) {
        return 'Lunch';
    }

    if ($hour < 21) {
        return 'Dinner';
    }

    return 'Late Service';
};

$formatTopbarControls = static function (string $areaOptionsHtml): string {
    return <<<'HTML'
<div class="analytics-topbar-controls">
    <div class="analytics-range-group" role="group" aria-label="Period granularity selector">
        <button type="button" class="analytics-range-chip is-active" data-period="daily">Daily</button>
        <button type="button" class="analytics-range-chip" data-period="weekly">Weekly</button>
        <button type="button" class="analytics-range-chip" data-period="monthly">Monthly</button>
        <button type="button" class="analytics-range-chip" data-period="yearly">Yearly</button>
    </div>
    <div class="analytics-topbar-selects">
        <label class="analytics-topbar-select">
            <span class="analytics-topbar-select-icon"><i class="fa-solid fa-location-dot"></i></span>
            <select id="areaFilter" aria-label="Area filter">
                <option value="all">All areas</option>
                __AREA_OPTIONS__
            </select>
        </label>
        <div class="analytics-period-range" id="periodRangeWrap">
            <input type="date" id="periodStartDate" aria-label="Start date">
            <input type="date" id="periodEndDate" aria-label="End date">
            <input type="week" id="periodStartWeek" class="is-hidden" aria-label="Start week">
            <input type="week" id="periodEndWeek" class="is-hidden" aria-label="End week">
            <input type="month" id="periodStartMonth" class="is-hidden" aria-label="Start month">
            <input type="month" id="periodEndMonth" class="is-hidden" aria-label="End month">
            <input type="number" id="periodStartYear" class="is-hidden" aria-label="Start year" min="2000" max="2100" step="1" placeholder="2026">
            <span>to</span>
            <input type="number" id="periodEndYear" class="is-hidden" aria-label="End year" min="2000" max="2100" step="1" placeholder="2026">
        </div>
    </div>
</div>
HTML;
};

$bookingsStmt = $pdo->query(
    "SELECT
        b.booking_id,
        b.user_id,
        b.table_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.number_of_guests,
        b.status,
        b.created_at,
        COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS customer_name,
        COALESCE(NULLIF(b.customer_email, ''), u.email, CONCAT('guest-', b.booking_id, '@local.dinemate')) AS customer_email,
        COALESCE(ta.name, 'Unassigned') AS area_name,
        COALESCE(rt.table_number, '') AS table_number,
        COALESCE(rt.capacity, 0) AS table_capacity
     FROM bookings b
     LEFT JOIN users u ON b.user_id = u.user_id
     LEFT JOIN restaurant_tables rt ON b.table_id = rt.table_id
     LEFT JOIN table_areas ta ON rt.area_id = ta.area_id
     ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC"
);
$rawBookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$tablesStmt = $pdo->query(
    "SELECT
        rt.table_id,
        rt.table_number,
        rt.capacity,
        rt.status,
        COALESCE(ta.name, 'Unassigned') AS area_name
     FROM restaurant_tables rt
     LEFT JOIN table_areas ta ON rt.area_id = ta.area_id
     ORDER BY COALESCE(ta.display_order, 9999) ASC, ta.name ASC, rt.table_number + 0, rt.table_number ASC"
);
$rawTables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$bookingRows = [];
foreach ($rawBookings as $row) {
    $bookingDate = (string) ($row['booking_date'] ?? '');
    $startTime = (string) ($row['start_time'] ?? '12:00:00');
    $endTime = (string) ($row['end_time'] ?? '13:00:00');
    $startTimestamp = strtotime($bookingDate . ' ' . $startTime);
    $endTimestamp = strtotime($bookingDate . ' ' . $endTime);

    if ($startTimestamp === false) {
        continue;
    }

    if ($endTimestamp === false || $endTimestamp <= $startTimestamp) {
        $endTimestamp = $startTimestamp + 3600;
    }

    $durationMinutes = (int) round(($endTimestamp - $startTimestamp) / 60);
    if ($durationMinutes < 45) {
        $durationMinutes = 60;
    }

    $servicePeriod = $resolveServicePeriod($startTime);
    $guestCount = max(1, (int) ($row['number_of_guests'] ?? 0));
    $tableCapacity = max(0, (int) ($row['table_capacity'] ?? 0));
    $createdTimestamp = !empty($row['created_at']) ? strtotime((string) $row['created_at']) : false;
    $leadHours = $createdTimestamp !== false ? round(($startTimestamp - $createdTimestamp) / 3600, 1) : null;

    $bookingRows[] = [
        'booking_id' => (int) $row['booking_id'],
        'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
        'table_id' => $row['table_id'] !== null ? (int) $row['table_id'] : null,
        'booking_date' => $bookingDate,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'number_of_guests' => $guestCount,
        'status' => strtolower(trim((string) ($row['status'] ?? 'pending'))),
        'customer_name' => (string) $row['customer_name'],
        'customer_email' => strtolower(trim((string) $row['customer_email'])),
        'area_name' => (string) $row['area_name'],
        'table_number' => (string) $row['table_number'],
        'table_capacity' => $tableCapacity,
        'area_key' => $normalizeAreaName((string) ($row['area_name'] ?? '')),
        'service_period' => $servicePeriod,
        'duration_minutes' => $durationMinutes,
        'lead_hours' => $leadHours,
    ];
}

$tableRows = [];
foreach ($rawTables as $row) {
    $tableRows[] = [
        'table_id' => (int) $row['table_id'],
        'table_number' => (string) $row['table_number'],
        'capacity' => (int) ($row['capacity'] ?? 0),
        'status' => (string) ($row['status'] ?? 'available'),
        'area_name' => (string) ($row['area_name'] ?? 'Unassigned'),
        'area_key' => $normalizeAreaName((string) ($row['area_name'] ?? '')),
    ];
}

$areaOptions = [];
foreach ($tableRows as $table) {
    $areaName = trim((string) ($table['area_name'] ?? ''));
    if ($areaName === '') {
        continue;
    }

    $areaKey = $normalizeAreaName($areaName);
    if (!isset($areaOptions[$areaKey])) {
        $areaOptions[$areaKey] = $areaName;
    }
}

foreach ($bookingRows as $booking) {
    $areaName = trim((string) ($booking['area_name'] ?? ''));
    if ($areaName === '') {
        continue;
    }

    $areaKey = $normalizeAreaName($areaName);
    if (!isset($areaOptions[$areaKey])) {
        $areaOptions[$areaKey] = $areaName;
    }
}

$areaOptionsHtml = '';
foreach ($areaOptions as $areaKey => $areaName) {
    $areaOptionsHtml .= '<option value="' . htmlspecialchars($areaKey, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($areaName, ENT_QUOTES, 'UTF-8') . '</option>';
}

$todayBookingCount = 0;
foreach ($bookingRows as $booking) {
    if ($booking['booking_date'] === date('Y-m-d')) {
        $todayBookingCount++;
    }
}

$adminPageTitle = 'Analytics';
$adminPageIcon = 'fa-chart-line';
$adminNotificationCount = $todayBookingCount;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'dashboard';
$adminSidebarPathPrefix = '';
$adminTopbarCenterContent = str_replace('__AREA_OPTIONS__', $areaOptionsHtml, $formatTopbarControls($areaOptionsHtml));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --analytics-bg: var(--dm-bg);
            --analytics-surface: var(--dm-surface);
            --analytics-surface-strong: var(--dm-surface);
            --analytics-line: var(--dm-border);
            --analytics-text: var(--dm-text);
            --analytics-muted: var(--dm-text-muted);
            --analytics-soft: var(--dm-text-soft);
            --analytics-shadow: var(--dm-shadow-md);
            --analytics-shadow-soft: var(--dm-shadow-sm);
            --analytics-gold: var(--dm-pending-text);
            --analytics-sage: var(--dm-confirmed-text);
            --analytics-rose: var(--dm-danger-text);
            --analytics-blue: var(--dm-info-text);
            --analytics-ink: var(--dm-accent-dark);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--analytics-bg);
            color: var(--analytics-text);
            font-family: 'Inter', sans-serif;
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

        .topbar-center {
            flex: 1;
            justify-content: flex-end;
        }

        .analytics-topbar-controls {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }

        .analytics-range-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px;
            border-radius: 8px;
            background: rgba(248, 250, 252, 0.96);
            border: 1px solid var(--dm-border);
        }

        .analytics-range-chip {
            border: none;
            background: transparent;
            color: var(--dm-text-muted);
            min-height: 36px;
            padding: 0 14px;
            border-radius: 6px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .analytics-range-chip:hover,
        .analytics-range-chip:focus-visible {
            background: rgba(29, 40, 64, 0.08);
            color: var(--dm-accent-dark);
            outline: none;
        }

        .analytics-range-chip.is-active {
            background: var(--dm-accent-dark);
            color: var(--dm-surface);
        }

        .analytics-topbar-selects {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .analytics-topbar-select {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 44px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid var(--dm-border);
            background: var(--dm-surface);
        }

        .analytics-topbar-select-icon {
            color: var(--dm-text-soft);
            font-size: 14px;
        }

        .analytics-topbar-select select,
        .analytics-period-range input {
            border: none;
            background: transparent;
            font: inherit;
            color: var(--dm-accent-dark);
            outline: none;
        }

        .analytics-period-range {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 0 12px;
            border-radius: 8px;
            border: 1px solid var(--dm-border);
            background: var(--dm-surface);
            color: var(--dm-text-muted);
            font-size: 13px;
            font-weight: 600;
        }

        .analytics-period-range .is-hidden {
            display: none;
        }

        .analytics-main {
            flex: 1;
            overflow-y: auto;
            padding: 26px;
        }

        .analytics-shell {
            display: grid;
            gap: 22px;
            max-width: 1440px;
            margin: 0 auto;
        }

        .analytics-hero {
            position: relative;
            overflow: hidden;
            padding: 32px;
            border-radius: 12px;
            background: var(--dm-surface);
            border: 1px solid rgba(231, 236, 243, 0.95);
            box-shadow: 0 1px 3px rgba(15,23,42,0.08);
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(260px, 0.8fr);
            gap: 22px;
            align-items: end;
        }

        .analytics-hero::after {
            display: none;
        }

        .hero-eyebrow {
            display: none;
        }

        .hero-title {
            margin: 0;
            font-size: clamp(20px, 2.8vw, 28px);
            line-height: 1.02;
            letter-spacing: -0.02em;
        }

        .hero-subtitle {
            margin: 12px 0 0;
            max-width: 720px;
            color: var(--analytics-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .hero-overview {
            display: grid;
            gap: 12px;
            align-content: end;
        }

        .hero-mini-card {
            padding: 18px 20px;
            border-radius: 10px;
            background: var(--dm-surface-muted);
            border: 1px solid rgba(232, 236, 243, 0.9);
        }

        .hero-mini-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--analytics-soft);
        }

        .hero-mini-value {
            margin-top: 8px;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--analytics-ink);
        }

        .hero-mini-note {
            margin-top: 6px;
            color: var(--analytics-muted);
            font-size: 13px;
        }

        .analytics-content {
            display: grid;
            gap: 22px;
            transition: opacity 0.24s ease, transform 0.24s ease;
        }

        .analytics-content.is-refreshing {
            opacity: 0.7;
            transform: translateY(2px);
        }

        .analytics-section {
            display: grid;
            gap: 14px;
        }

        .section-heading {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .section-title-wrap h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .section-title-wrap p {
            margin: 6px 0 0;
            color: var(--analytics-muted);
            font-size: 14px;
        }

        .section-note {
            color: var(--analytics-soft);
            font-size: 13px;
            font-weight: 600;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
        }

        .kpi-card,
        .analytics-card {
            background: var(--analytics-surface-strong);
            border: 1px solid var(--analytics-line);
            border-radius: 10px;
            box-shadow: var(--analytics-shadow-soft);
            transition: box-shadow 0.22s ease, border-color 0.22s ease;
        }

        .kpi-card:hover,
        .analytics-card:hover {
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            border-color: var(--dm-border-strong);
        }

        .kpi-card {
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            display: none;
        }

        .kpi-label {
            color: var(--analytics-soft);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .kpi-value {
            margin-top: 12px;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--analytics-ink);
        }

        .kpi-meta {
            margin-top: 10px;
            color: var(--analytics-muted);
            font-size: 13px;
            line-height: 1.55;
        }

        .kpi-trend {
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 4px;
            background: var(--dm-surface-muted);
            color: var(--dm-text-muted);
            font-size: 11px;
            font-weight: 700;
        }

        .kpi-trend.positive {
            background: var(--dm-confirmed-bg);
            color: var(--dm-confirmed-text);
        }

        .kpi-trend.negative {
            background: var(--dm-danger-bg);
            color: var(--dm-danger-text);
        }

        .panel-grid-large {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(320px, 0.8fr);
            gap: 18px;
        }

        .panel-grid-half {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .analytics-card {
            padding: 22px;
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .card-title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .card-subtitle {
            margin: 6px 0 0;
            color: var(--analytics-muted);
            font-size: 13px;
        }

        .segmented-control {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px;
            border-radius: 8px;
            background: var(--dm-surface-muted);
            border: 1px solid var(--dm-border);
        }

        .segment-button {
            border: none;
            background: transparent;
            color: var(--dm-text-muted);
            min-height: 34px;
            padding: 0 12px;
            border-radius: 8px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .segment-button.is-active {
            background: var(--dm-surface);
            color: var(--dm-accent-dark);
            box-shadow: 0 2px 4px rgba(15, 23, 42, 0.06);
        }

        .chart-shell {
            position: relative;
            min-height: 320px;
        }

        .chart-shell.small {
            min-height: 220px;
        }

        .metrics-stack {
            display: grid;
            gap: 12px;
            margin-bottom: 18px;
        }

        .metric-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 8px;
            background: var(--dm-surface-muted);
            border: 1px solid var(--dm-border);
        }

        .metric-pill-label {
            color: var(--analytics-muted);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .metric-pill-value {
            color: var(--analytics-ink);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .utilisation-layout,
        .customer-layout,
        .cancellation-layout,
        .area-highlight-layout {
            display: grid;
            gap: 18px;
        }

        .utilisation-layout {
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.95fr);
        }

        .utilisation-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .sub-stat {
            padding: 16px;
            border-radius: 8px;
            background: var(--dm-surface-muted);
            border: 1px solid var(--dm-border);
        }

        .sub-stat-label {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--analytics-soft);
        }

        .sub-stat-value {
            margin-top: 10px;
            font-size: 24px;
            font-weight: 800;
            color: var(--analytics-ink);
        }

        .sub-stat-note {
            margin-top: 6px;
            color: var(--analytics-muted);
            font-size: 13px;
        }

        .heatmap-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
            gap: 10px;
        }

        .heatmap-cell {
            padding: 14px 12px;
            border-radius: 10px;
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: var(--dm-surface-muted);
        }

        .heatmap-cell:hover {
            box-shadow: 0 4px 8px rgba(15, 23, 42, 0.06);
        }

        .heatmap-cell-label {
            font-size: 13px;
            font-weight: 800;
            color: var(--analytics-ink);
        }

        .heatmap-cell-meta {
            margin-top: 5px;
            color: var(--analytics-muted);
            font-size: 12px;
        }

        .heatmap-bar {
            margin-top: 12px;
            height: 8px;
            border-radius: 4px;
            background: rgba(90, 136, 200, 0.14);
            overflow: hidden;
        }

        .heatmap-bar > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--dm-confirmed-text), var(--dm-info-text), var(--dm-pending-text));
        }

        .table-list {
            display: grid;
            gap: 10px;
        }

        .table-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 8px;
            background: var(--dm-surface-muted);
            border: 1px solid var(--dm-border);
        }

        .table-list-item strong {
            color: var(--analytics-ink);
            font-size: 14px;
        }

        .table-list-item span {
            color: var(--analytics-muted);
            font-size: 12px;
        }

        .insight-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .insight-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 6px;
            background: var(--dm-standby-bg);
            border: 1px solid var(--dm-standby-bg);
            color: var(--dm-pending-text);
            font-size: 12px;
            font-weight: 700;
        }

        .zone-table {
            display: grid;
            gap: 10px;
        }

        .zone-row {
            display: grid;
            grid-template-columns: minmax(120px, 1.1fr) repeat(4, minmax(0, 0.8fr));
            gap: 12px;
            align-items: center;
            padding: 14px 16px;
            border-radius: 8px;
            background: var(--dm-surface-muted);
            border: 1px solid var(--dm-border);
        }

        .zone-row.head {
            background: transparent;
            border: none;
            padding: 0 4px 4px;
            color: var(--analytics-soft);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .zone-name {
            display: grid;
            gap: 8px;
        }

        .zone-name strong {
            font-size: 14px;
            color: var(--analytics-ink);
        }

        .progress-track {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: var(--dm-border);
            overflow: hidden;
        }

        .progress-track > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--dm-confirmed-text), var(--dm-info-text));
        }

        .top-zone-card {
            padding: 22px;
            border-radius: 10px;
            background: var(--dm-accent-dark);
            color: var(--dm-surface);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
        }

        .top-zone-label {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.72);
        }

        .top-zone-name {
            margin-top: 10px;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .top-zone-copy {
            margin-top: 10px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 14px;
            line-height: 1.7;
        }

        .metric-grid-quad {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .area-highlight-layout {
            grid-template-columns: minmax(0, 1.1fr) minmax(240px, 0.85fr);
            gap: 16px;
        }

        .split-chart-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(220px, 0.8fr);
            gap: 16px;
            align-items: center;
        }

        .insights-list {
            display: grid;
            gap: 10px;
            margin-top: 18px;
        }

        .insight-list-item {
            display: flex;
            gap: 12px;
            align-items: start;
            padding: 14px 16px;
            border-radius: 8px;
            background: var(--dm-surface-muted);
            border: 1px solid var(--dm-border);
            color: var(--analytics-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .insight-list-item i {
            margin-top: 1px;
            color: var(--analytics-gold);
        }

        .customer-stat-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .customer-stat-card {
            padding: 16px;
            border-radius: 8px;
            background: var(--dm-surface-muted);
            border: 1px solid var(--dm-border);
        }

        .customer-stat-card strong {
            display: block;
            margin-top: 8px;
            color: var(--analytics-ink);
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .customer-stat-card span {
            color: var(--analytics-soft);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .operations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
        }

        .operation-card {
            padding: 18px;
            border-radius: 10px;
            background: var(--dm-surface);
            border: 1px solid var(--dm-border);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }

        .operation-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 6px;
            background: var(--dm-surface-muted);
            color: var(--dm-text-muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .operation-card h3 {
            margin: 14px 0 8px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .operation-card p {
            margin: 0;
            color: var(--analytics-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .empty-state-shell {
            display: grid;
            place-items: center;
            min-height: 460px;
            padding: 48px 24px;
            border-radius: 12px;
            border: 1px solid var(--analytics-line);
            background: var(--dm-surface);
            box-shadow: 0 1px 3px rgba(15,23,42,0.06);
            text-align: center;
        }

        .empty-state-shell[hidden] {
            display: none;
        }

        .empty-icon {
            width: 82px;
            height: 82px;
            border-radius: 10px;
            display: inline-grid;
            place-items: center;
            margin-bottom: 20px;
            background: rgba(31, 45, 77, 0.08);
            color: var(--analytics-ink);
            font-size: 34px;
        }

        .empty-state-shell h2 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .empty-state-shell p {
            margin: 10px 0 0;
            max-width: 520px;
            color: var(--analytics-muted);
            font-size: 15px;
            line-height: 1.7;
        }

        .section-empty {
            min-height: 220px;
            display: grid;
            place-items: center;
            text-align: center;
            color: var(--analytics-muted);
            border: 1px dashed var(--dm-border);
            border-radius: 10px;
            background: var(--dm-surface-muted);
            padding: 24px;
        }

        .section-empty i {
            display: block;
            margin-bottom: 10px;
            color: var(--analytics-soft);
            font-size: 22px;
        }

        @media (max-width: 1380px) {
            .kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1220px) {
            .analytics-hero,
            .panel-grid-large,
            .panel-grid-half,
            .utilisation-layout,
            .split-chart-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .utilisation-summary,
            .customer-stat-row,
            .metric-grid-quad,
            .area-highlight-layout {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 991px) {
            .analytics-main {
                padding: 18px;
            }

            .analytics-topbar-controls {
                justify-content: flex-start;
            }

            .kpi-grid,
            .customer-stat-row,
            .utilisation-summary,
            .metric-grid-quad,
            .area-highlight-layout {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .zone-row,
            .zone-row.head {
                grid-template-columns: minmax(0, 1fr);
            }

            .zone-row.head {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .analytics-hero,
            .analytics-card,
            .kpi-card,
            .empty-state-shell {
                border-radius: 10px;
            }

            .analytics-hero {
                padding: 24px;
            }

            .analytics-range-group {
                width: 100%;
                justify-content: space-between;
                overflow-x: auto;
            }

            .analytics-topbar-selects,
            .analytics-topbar-select,
            .analytics-period-range {
                width: 100%;
            }

            .kpi-grid,
            .customer-stat-row,
            .utilisation-summary,
            .metric-grid-quad,
            .area-highlight-layout {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>

</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../partials/admin-topbar.php'; ?>

        <main class="analytics-main">
            <div class="analytics-shell">
                <section class="analytics-hero">
                    <div>
                        <p class="hero-eyebrow"><i class="fa-solid fa-sparkles"></i> Premium operations overview</p>
                        <h1 class="hero-title">Analytics</h1>
                        <p class="hero-subtitle">Booking, table, and customer performance.</p>
                    </div>
                    <div class="hero-overview">
                        <div class="hero-mini-card">
                            <div class="hero-mini-label">Live focus</div>
                            <div class="hero-mini-value" id="heroFocusValue">Friday dinner</div>
                            <div class="hero-mini-note" id="heroFocusNote">Strong outdoor demand with compact party sizes.</div>
                        </div>
                        <div class="hero-mini-card">
                            <div class="hero-mini-label">Top zone</div>
                            <div class="hero-mini-value" id="heroZoneValue">Outdoor</div>
                            <div class="hero-mini-note" id="heroZoneNote">Highest occupancy and strongest booking demand this period.</div>
                        </div>
                    </div>
                </section>

                <section class="empty-state-shell" id="globalEmptyState" hidden>
                    <div>
                        <div class="empty-icon"><i class="fa-solid fa-chart-column"></i></div>
                        <h2>No analytics available</h2>
                        <p>Analytics will appear when data is available.</p>
                    </div>
                </section>

                <div class="analytics-content" id="analyticsContent">
                    <section class="analytics-section">
                        <div class="section-heading">
                            <div class="section-title-wrap">
                                <h2>KPI Summary</h2>
                                <p>Key booking and occupancy metrics.</p>
                            </div>
                            <div class="section-note" id="kpiSectionNote">Comparing the selected period against the previous one.</div>
                        </div>
                        <div class="kpi-grid">
                            <article class="kpi-card">
                                <div class="kpi-label">Total Bookings</div>
                                <div class="kpi-value" id="kpiTotalBookings">0</div>
                                <div class="kpi-trend" id="kpiTotalTrend">No comparison</div>
                                <div class="kpi-meta">Bookings in the selected period.</div>
                            </article>
                            <article class="kpi-card">
                                <div class="kpi-label">Occupancy Rate</div>
                                <div class="kpi-value" id="kpiOccupancyRate">0%</div>
                                <div class="kpi-trend" id="kpiOccupancyTrend">Average table fill rate</div>
                                <div class="kpi-meta">Average table fill rate.</div>
                            </article>
                            <article class="kpi-card">
                                <div class="kpi-label">Average Party Size</div>
                                <div class="kpi-value" id="kpiAvgPartySize">0 guests</div>
                                <div class="kpi-trend" id="kpiAvgPartyTrend">Guest mix</div>
                                <div class="kpi-meta">Average guest count per active booking.</div>
                            </article>
                            <article class="kpi-card">
                                <div class="kpi-label">No-show Rate</div>
                                <div class="kpi-value" id="kpiNoShowRate">0%</div>
                                <div class="kpi-trend" id="kpiNoShowTrend">Lower is better</div>
                                <div class="kpi-meta">Recorded no-show rate.</div>
                            </article>
                            <article class="kpi-card">
                                <div class="kpi-label">Average Dwell Time</div>
                                <div class="kpi-value" id="kpiDwellTime">0m</div>
                                <div class="kpi-trend" id="kpiDwellTrend">Table use duration</div>
                                <div class="kpi-meta">Average table use duration.</div>
                            </article>
                        </div>
                    </section>

                    <section class="analytics-section">
                        <div class="section-heading">
                            <div class="section-title-wrap">
                                <h2>Booking Trends</h2>
                                <p>Booking volume over time.</p>
                            </div>
                        </div>
                        <div class="panel-grid-large">
                            <article class="analytics-card">
                                <div class="card-header">
                                    <div>
                                        <h3 class="card-title">Booking Trends</h3>
                                        <p class="card-subtitle">Selected reporting period.</p>
                                    </div>
                                </div>
                                <div class="chart-shell">
                                    <canvas id="bookingTrendChart"></canvas>
                                </div>
                            </article>

                            <article class="analytics-card">
                                <div class="card-header">
                                    <div>
                                        <h3 class="card-title">Peak demand</h3>
                                        <p class="card-subtitle">Busiest booking periods.</p>
                                    </div>
                                </div>
                                <div class="metrics-stack">
                                    <div class="metric-pill"><div><div class="metric-pill-label">Peak booking day</div><div class="metric-pill-value" id="peakDayValue">-</div></div></div>
                                    <div class="metric-pill"><div><div class="metric-pill-label">Peak booking hour</div><div class="metric-pill-value" id="peakHourValue">-</div></div></div>
                                    <div class="metric-pill"><div><div class="metric-pill-label">Busiest service</div><div class="metric-pill-value" id="peakServiceValue">-</div></div></div>
                                </div>
                                <div class="chart-shell small">
                                    <canvas id="peakDemandChart"></canvas>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="analytics-section">
                        <div class="section-heading">
                            <div class="section-title-wrap">
                                <h2>Table Utilisation</h2>
                                <p>Table usage and turnover.</p>
                            </div>
                        </div>
                        <article class="analytics-card">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title">Table Utilisation</h3>
                                    <p class="card-subtitle">Table activity across the selected period.</p>
                                </div>
                            </div>
                            <div class="utilisation-summary">
                                <div class="sub-stat"><div class="sub-stat-label">Table turnover rate</div><div class="sub-stat-value" id="turnoverRateValue">0.0x</div><div class="sub-stat-note">Bookings per active table.</div></div>
                                <div class="sub-stat"><div class="sub-stat-label">Average idle time</div><div class="sub-stat-value" id="idleTimeValue">0m</div><div class="sub-stat-note">Average available time per table per day.</div></div>
                                <div class="sub-stat"><div class="sub-stat-label">Most used tables</div><div class="sub-stat-value" id="mostUsedTablesValue">-</div><div class="sub-stat-note">Highest booking volume this period.</div></div>
                            </div>
                            <div class="utilisation-layout">
                                <div>
                                    <div class="chart-shell"><canvas id="tableUsageChart"></canvas></div>
                                    <div class="insight-tags" id="utilisationInsightTags"></div>
                                </div>
                                <div>
                                    <div class="card-header" style="margin-bottom: 12px;"><div><h3 class="card-title" style="font-size: 16px;">Utilisation heatmap</h3><p class="card-subtitle">Relative table usage.</p></div></div>
                                    <div class="heatmap-grid" id="tableHeatmap"></div>
                                    <div class="card-header" style="margin: 18px 0 12px;"><div><h3 class="card-title" style="font-size: 16px;">Least used tables</h3></div></div>
                                    <div class="table-list" id="leastUsedTablesList"></div>
                                </div>
                            </div>
                        </article>
                    </section>

                    <section class="analytics-section">
                        <div class="section-heading">
                            <div class="section-title-wrap">
                                <h2>Zone Performance</h2>
                                <p>Performance by venue area.</p>
                            </div>
                        </div>
                        <div class="panel-grid-half">
                            <article class="analytics-card">
                                <div class="card-header"><div><h3 class="card-title">Zone Performance</h3><p class="card-subtitle">Bookings and occupancy by area.</p></div></div>
                                <div class="zone-table" id="zonePerformanceTable"></div>
                            </article>
                            <article class="analytics-card">
                                <div class="card-header"><div><h3 class="card-title">Area Highlights</h3><p class="card-subtitle">Top area performance indicators.</p></div></div>
                                <div class="area-highlight-layout">
                                    <div>
                                        <div class="metric-grid-quad">
                                            <div class="sub-stat"><div class="sub-stat-label">Top performing area</div><div class="sub-stat-value" id="topZoneName">-</div><div class="sub-stat-note" id="topZoneMetricNote">Highest occupancy in the selected period.</div></div>
                                            <div class="sub-stat"><div class="sub-stat-label">Busiest area</div><div class="sub-stat-value" id="busiestAreaValue">-</div><div class="sub-stat-note">Highest booking volume in the selected period.</div></div>
                                            <div class="sub-stat"><div class="sub-stat-label">Best turnover area</div><div class="sub-stat-value" id="bestTurnoverAreaValue">-</div><div class="sub-stat-note">Most efficient bookings per active table.</div></div>
                                            <div class="sub-stat"><div class="sub-stat-label">Area focus</div><div class="sub-stat-value" id="topAreaServiceValue">-</div><div class="sub-stat-note">Dominant service pattern in the leading area.</div></div>
                                        </div>
                                        <div class="chart-shell small"><canvas id="areaDemandChart"></canvas></div>
                                    </div>
                                    <div class="top-zone-card"><div class="top-zone-label">Top Performing Area</div><div class="top-zone-name" id="topZoneNameCard">-</div><div class="top-zone-copy" id="topZoneCopy">Best overall area performance for the selected period.</div></div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="analytics-section">
                        <div class="section-heading">
                            <div class="section-title-wrap">
                                <h2>Cancellations &amp; No-shows</h2>
                                <p>Cancellation and no-show activity.</p>
                            </div>
                        </div>
                        <div class="panel-grid-half">
                            <article class="analytics-card">
                                <div class="card-header"><div><h3 class="card-title">Cancellations &amp; No-shows</h3><p class="card-subtitle">Booking outcomes for the selected period.</p></div></div>
                                <div class="metric-grid-quad">
                                    <div class="sub-stat"><div class="sub-stat-label">Cancellation rate</div><div class="sub-stat-value" id="cancellationRateValue">0%</div><div class="sub-stat-note">Share of bookings cancelled before service.</div></div>
                                    <div class="sub-stat"><div class="sub-stat-label">No-show rate</div><div class="sub-stat-value" id="cancellationNoShowValue">0%</div><div class="sub-stat-note">Estimated from short lead times and service patterns.</div></div>
                                    <div class="sub-stat"><div class="sub-stat-label">Late cancellation count</div><div class="sub-stat-value" id="lateCancellationValue">0</div><div class="sub-stat-note">Late-change risk indicator for staffing and table flow.</div></div>
                                    <div class="sub-stat"><div class="sub-stat-label">Most affected day / time</div><div class="sub-stat-value" id="affectedSlotValue">-</div><div class="sub-stat-note">Highest combined cancellation and no-show pressure.</div></div>
                                </div>
                                <div class="chart-shell small"><canvas id="cancellationChart"></canvas></div>
                                <div class="insights-list" id="cancellationInsights"></div>
                            </article>
                            <article class="analytics-card">
                                <div class="card-header"><div><h3 class="card-title">Customer Insights</h3><p class="card-subtitle">Guest mix and booking patterns.</p></div></div>
                                <div class="customer-stat-row">
                                    <div class="customer-stat-card"><span>New vs returning</span><strong id="customerMixValue">0 / 0</strong></div>
                                    <div class="customer-stat-card"><span>Average lead time</span><strong id="leadTimeValue">0 days</strong></div>
                                    <div class="customer-stat-card"><span>Most common party size</span><strong id="partySizeModeValue">-</strong></div>
                                    <div class="customer-stat-card"><span>Repeat guest rate</span><strong id="repeatGuestRateValue">0%</strong></div>
                                </div>
                                <div class="split-chart-grid">
                                    <div class="chart-shell small"><canvas id="customerMixChart"></canvas></div>
                                    <div class="chart-shell small"><canvas id="partySizeChart"></canvas></div>
                                </div>
                                <div class="insight-tags" id="customerInsightTags"></div>
                            </article>
                        </div>
                    </section>

                    <section class="analytics-section">
                        <div class="section-heading">
                            <div class="section-title-wrap">
                                <h2>Operational Insights</h2>
                                <p>Actionable recommendations based on demand concentration, guest mix, and allocation efficiency.</p>
                            </div>
                        </div>
                        <div class="operations-grid" id="operationalInsightsGrid"></div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
const analyticsSource = {
    today: <?php echo json_encode(date('Y-m-d')); ?>,
    bookings: <?php echo json_encode($bookingRows, JSON_UNESCAPED_SLASHES); ?>,
    tables: <?php echo json_encode($tableRows, JSON_UNESCAPED_SLASHES); ?>,
    areas: <?php echo json_encode(array_values($areaOptions), JSON_UNESCAPED_SLASHES); ?>
};

const weekdayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const serviceOrder = ['Lunch', 'Dinner', 'Late Service'];
const analyticsState = {
    period: 'daily',
    area: 'all',
    startValue: null,
    endValue: null,
};

const charts = {};
const analyticsContent = document.getElementById('analyticsContent');
const globalEmptyState = document.getElementById('globalEmptyState');
const periodRangeWrap = document.getElementById('periodRangeWrap');
const periodStartDate = document.getElementById('periodStartDate');
const periodEndDate = document.getElementById('periodEndDate');
const periodStartWeek = document.getElementById('periodStartWeek');
const periodEndWeek = document.getElementById('periodEndWeek');
const periodStartMonth = document.getElementById('periodStartMonth');
const periodEndMonth = document.getElementById('periodEndMonth');
const periodStartYear = document.getElementById('periodStartYear');
const periodEndYear = document.getElementById('periodEndYear');
const areaFilter = document.getElementById('areaFilter');

const cardTextPlugin = {
    id: 'cardTextPlugin',
    afterDraw(chart) {
        const hasData = chart.data.datasets.some((dataset) => (dataset.data || []).some((value) => Number(value) > 0));
        if (hasData) {
            return;
        }

        const { ctx, chartArea } = chart;
        if (!chartArea) {
            return;
        }

        ctx.save();
        ctx.textAlign = 'center';
        ctx.fillStyle = 'var(--dm-text-soft)';
        ctx.font = '600 13px Inter';
        ctx.fillText('No data for this selection', (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2);
        ctx.restore();
    }
};

Chart.register(cardTextPlugin);

function parseDate(dateValue) {
    return new Date(`${dateValue}T00:00:00`);
}

function parseDateTime(dateValue, timeValue) {
    return new Date(`${dateValue}T${timeValue || '00:00:00'}`);
}

function cloneDate(date) {
    return new Date(date.getTime());
}

function startOfDay(date) {
    const clone = cloneDate(date);
    clone.setHours(0, 0, 0, 0);
    return clone;
}

function endOfDay(date) {
    const clone = cloneDate(date);
    clone.setHours(23, 59, 59, 999);
    return clone;
}

function addDays(date, dayCount) {
    const clone = cloneDate(date);
    clone.setDate(clone.getDate() + dayCount);
    return clone;
}

function startOfWeek(date) {
    return startOfDay(addDays(date, -((date.getDay() + 6) % 7)));
}

function endOfWeek(date) {
    return endOfDay(addDays(startOfWeek(date), 6));
}

function startOfMonthFromValue(value) {
    const [year, month] = String(value).split('-').map(Number);
    return new Date(year, month - 1, 1, 0, 0, 0, 0);
}

function endOfMonthFromValue(value) {
    const [year, month] = String(value).split('-').map(Number);
    return new Date(year, month, 0, 23, 59, 59, 999);
}

function startOfYearFromValue(value) {
    return new Date(Number(value), 0, 1, 0, 0, 0, 0);
}

function endOfYearFromValue(value) {
    return new Date(Number(value), 11, 31, 23, 59, 59, 999);
}

function parseWeekValue(value) {
    const [yearPart, weekPart] = String(value).split('-W');
    const year = Number(yearPart);
    const week = Number(weekPart);
    const januaryFourth = new Date(year, 0, 4);
    const firstWeekStart = startOfWeek(januaryFourth);
    return addDays(firstWeekStart, (week - 1) * 7);
}

function formatMonthValue(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function formatWeekValue(date) {
    const weekStart = startOfWeek(date);
    const januaryFourth = new Date(weekStart.getFullYear(), 0, 4);
    const firstWeekStart = startOfWeek(januaryFourth);
    const diffDays = Math.round((weekStart.getTime() - firstWeekStart.getTime()) / 86400000);
    const weekNumber = Math.floor(diffDays / 7) + 1;
    return `${weekStart.getFullYear()}-W${String(weekNumber).padStart(2, '0')}`;
}

function formatDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatNumber(value, digits = 0) {
    return new Intl.NumberFormat('en-AU', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number(value || 0));
}

function formatPercent(value, digits = 0) {
    return `${formatNumber(value, digits)}%`;
}

function formatCurrency(value, digits = 0) {
    return new Intl.NumberFormat('en-AU', {
        style: 'currency',
        currency: 'AUD',
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(Number(value || 0));
}

function formatDuration(totalMinutes) {
    const minutes = Math.max(0, Math.round(Number(totalMinutes || 0)));
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    if (hours <= 0) {
        return `${remainder}m`;
    }

    return `${hours}h ${String(remainder).padStart(2, '0')}m`;
}

function getSelectedRange() {
    const today = parseDate(analyticsSource.today);
    const period = analyticsState.period;

    if (period === 'weekly') {
        const startValue = analyticsState.startValue || formatWeekValue(today);
        const endValue = analyticsState.endValue || startValue;
        const startDate = parseWeekValue(startValue);
        const endDate = parseWeekValue(endValue);
        return {
            start: startOfWeek(startDate),
            end: endOfWeek(endDate),
            label: `${new Intl.DateTimeFormat('en-AU', { month: 'short', day: 'numeric' }).format(startOfWeek(startDate))} to ${new Intl.DateTimeFormat('en-AU', { month: 'short', day: 'numeric', year: 'numeric' }).format(endOfWeek(endDate))}`
        };
    }

    if (period === 'monthly') {
        const startValue = analyticsState.startValue || formatMonthValue(today);
        const endValue = analyticsState.endValue || startValue;
        return {
            start: startOfMonthFromValue(startValue),
            end: endOfMonthFromValue(endValue),
            label: `${new Intl.DateTimeFormat('en-AU', { month: 'long', year: 'numeric' }).format(startOfMonthFromValue(startValue))} to ${new Intl.DateTimeFormat('en-AU', { month: 'long', year: 'numeric' }).format(startOfMonthFromValue(endValue))}`
        };
    }

    if (period === 'yearly') {
        const startValue = analyticsState.startValue || String(today.getFullYear());
        const endValue = analyticsState.endValue || startValue;
        return {
            start: startOfYearFromValue(startValue),
            end: endOfYearFromValue(endValue),
            label: startValue === endValue ? String(startValue) : `${startValue} to ${endValue}`
        };
    }

    const startValue = analyticsState.startValue || analyticsSource.today;
    const endValue = analyticsState.endValue || startValue;
    return {
        start: startOfDay(parseDate(startValue)),
        end: endOfDay(parseDate(endValue)),
        label: startValue === endValue
            ? new Intl.DateTimeFormat('en-AU', { month: 'short', day: 'numeric', year: 'numeric' }).format(parseDate(startValue))
            : `${new Intl.DateTimeFormat('en-AU', { month: 'short', day: 'numeric' }).format(parseDate(startValue))} to ${new Intl.DateTimeFormat('en-AU', { month: 'short', day: 'numeric', year: 'numeric' }).format(parseDate(endValue))}`
    };
}

function matchesArea(booking) {
    if (analyticsState.area === 'all') {
        return true;
    }

    return String(booking.area_key || '') === analyticsState.area;
}

function filterBookingsByRange(bookings, range) {
    return bookings.filter((booking) => {
        const bookingDate = parseDateTime(booking.booking_date, booking.start_time);
        return bookingDate >= range.start && bookingDate <= range.end && matchesArea(booking);
    });
}

function filterTablesByArea(tables) {
    if (analyticsState.area === 'all') {
        return tables;
    }

    return tables.filter((table) => String(table.area_key || '') === analyticsState.area);
}

function getBookingWeightForNoShow(booking) {
    if (booking.status === 'no_show') {
        return 1;
    }

    if (booking.status === 'cancelled' || booking.status === 'completed') {
        return 0;
    }

    let weight = 0.028;
    const leadHours = booking.lead_hours === null ? 24 : Number(booking.lead_hours);
    const bookingDay = parseDate(booking.booking_date).getDay();
    const guestCount = Number(booking.number_of_guests || 0);

    if (leadHours <= 2) {
        weight += 0.05;
    } else if (leadHours <= 6) {
        weight += 0.03;
    } else if (leadHours <= 24) {
        weight += 0.015;
    }

    if (booking.service_period === 'Dinner') {
        weight += 0.012;
    }

    if (booking.service_period === 'Late Service') {
        weight += 0.008;
    }

    if (bookingDay === 5 || bookingDay === 6) {
        weight += 0.012;
    }

    if (guestCount <= 2) {
        weight += 0.01;
    }

    if (String(booking.area_name || '').toLowerCase().includes('osf')) {
        weight += 0.006;
    }

    return Math.min(weight, 0.16);
}

function getEstimatedNoShowMetrics(bookings) {
    const noShowBookings = bookings.filter((booking) => booking.status === 'no_show');
    let estimatedCount = 0;
    const byWeekday = {};
    const bySlot = {};

    noShowBookings.forEach((booking) => {
        const weight = getBookingWeightForNoShow(booking);
        estimatedCount += weight;

        const weekday = new Intl.DateTimeFormat('en-AU', { weekday: 'long' }).format(parseDate(booking.booking_date));
        const hourLabel = new Intl.DateTimeFormat('en-AU', { hour: 'numeric', minute: '2-digit' }).format(parseDateTime(booking.booking_date, booking.start_time));
        const slotKey = `${weekday} ${hourLabel}`;

        byWeekday[weekday] = (byWeekday[weekday] || 0) + weight;
        bySlot[slotKey] = (bySlot[slotKey] || 0) + weight;
    });

    return { count: Math.round(estimatedCount), byWeekday, bySlot };
}

function getPreviousRange(range) {
    const durationMs = range.end.getTime() - range.start.getTime();
    const previousEnd = addDays(range.start, -1);
    const previousStart = new Date(previousEnd.getTime() - durationMs);
    return { start: startOfDay(previousStart), end: endOfDay(previousEnd) };
}

function comparePeriods(currentValue, previousValue) {
    if (!previousValue) {
        return { value: null, text: 'No comparison', state: '' };
    }

    const delta = ((currentValue - previousValue) / previousValue) * 100;
    return {
        value: delta,
        text: `${delta >= 0 ? '+' : ''}${formatNumber(delta, 0)}% vs last period`,
        state: delta >= 0 ? 'positive' : 'negative',
    };
}

function getCustomerKey(booking) {
    if (booking.user_id) {
        return `user-${booking.user_id}`;
    }

    if (booking.customer_email) {
        return `email-${String(booking.customer_email).trim().toLowerCase()}`;
    }

    return `guest-${booking.booking_id}`;
}

function buildCustomerHistory(bookings) {
    const history = new Map();
    bookings.forEach((booking) => {
        const key = getCustomerKey(booking);
        const bookingDate = parseDateTime(booking.booking_date, booking.start_time);
        if (!history.has(key) || bookingDate < history.get(key)) {
            history.set(key, bookingDate);
        }
    });
    return history;
}

function buildTrendSeries(bookings, range, mode) {
    const labels = [];
    const values = [];
    const activeBookings = bookings.filter((booking) => !['cancelled', 'no_show'].includes(booking.status));

    if (mode === 'weekly') {
        const weekMap = new Map();
        activeBookings.forEach((booking) => {
            const date = parseDate(booking.booking_date);
            const weekStart = addDays(date, -((date.getDay() + 6) % 7));
            const key = formatDateKey(weekStart);
            weekMap.set(key, (weekMap.get(key) || 0) + 1);
        });
        Array.from(weekMap.keys()).sort().forEach((key) => {
            labels.push(`Week of ${new Intl.DateTimeFormat('en-AU', { month: 'short', day: 'numeric' }).format(parseDate(key))}`);
            values.push(weekMap.get(key));
        });
        return { labels, values };
    }

    if (mode === 'monthly') {
        const monthMap = new Map();
        activeBookings.forEach((booking) => {
            const key = booking.booking_date.slice(0, 7);
            monthMap.set(key, (monthMap.get(key) || 0) + 1);
        });
        Array.from(monthMap.keys()).sort().forEach((key) => {
            labels.push(new Intl.DateTimeFormat('en-AU', { month: 'short', year: 'numeric' }).format(parseDate(`${key}-01`)));
            values.push(monthMap.get(key));
        });
        return { labels, values };
    }

    if (mode === 'yearly') {
        const yearMap = new Map();
        activeBookings.forEach((booking) => {
            const key = booking.booking_date.slice(0, 4);
            yearMap.set(key, (yearMap.get(key) || 0) + 1);
        });
        Array.from(yearMap.keys()).sort().forEach((key) => {
            labels.push(key);
            values.push(yearMap.get(key));
        });
        return { labels, values };
    }

    for (let date = cloneDate(range.start); date <= range.end; date = addDays(date, 1)) {
        const key = formatDateKey(date);
        labels.push(new Intl.DateTimeFormat('en-AU', { month: 'short', day: 'numeric' }).format(date));
        values.push(activeBookings.filter((booking) => booking.booking_date === key).length);
    }

    return { labels, values };
}

function buildMetrics(filteredBookings, previousBookings, allBookings, filteredTables, range) {
    const activeBookings = filteredBookings.filter((booking) => !['cancelled', 'no_show'].includes(booking.status));
    const noShowEligibleBookings = filteredBookings.filter((booking) => booking.status !== 'cancelled');
    const bookingsWithTables = activeBookings.filter((booking) => booking.table_id && booking.table_capacity > 0);
    const estimatedNoShows = getEstimatedNoShowMetrics(filteredBookings);
    const totalGuests = activeBookings.reduce((sum, booking) => sum + Number(booking.number_of_guests || 0), 0);
    const occupancyRate = bookingsWithTables.length
        ? (bookingsWithTables.reduce((sum, booking) => sum + Math.min(booking.number_of_guests, booking.table_capacity) / booking.table_capacity, 0) / bookingsWithTables.length) * 100
        : 0;
    const avgPartySize = activeBookings.length ? totalGuests / activeBookings.length : 0;
    const dwellMinutes = activeBookings.length
        ? activeBookings.reduce((sum, booking) => sum + Number(booking.duration_minutes || 0) + (booking.service_period === 'Dinner' ? 18 : booking.service_period === 'Lunch' ? 10 : 14), 0) / activeBookings.length
        : 0;
    const noShowRate = noShowEligibleBookings.length ? (estimatedNoShows.count / noShowEligibleBookings.length) * 100 : 0;

    const priorActiveBookings = previousBookings.filter((booking) => !['cancelled', 'no_show'].includes(booking.status));
    const priorNoShowEligibleBookings = previousBookings.filter((booking) => booking.status !== 'cancelled');
    const priorWithTables = priorActiveBookings.filter((booking) => booking.table_id && booking.table_capacity > 0);
    const priorOccupancy = priorWithTables.length
        ? (priorWithTables.reduce((sum, booking) => sum + Math.min(booking.number_of_guests, booking.table_capacity) / booking.table_capacity, 0) / priorWithTables.length) * 100
        : 0;
    const priorAvgParty = priorActiveBookings.length ? priorActiveBookings.reduce((sum, booking) => sum + Number(booking.number_of_guests || 0), 0) / priorActiveBookings.length : 0;
    const priorDwell = priorActiveBookings.length
        ? priorActiveBookings.reduce((sum, booking) => sum + Number(booking.duration_minutes || 0) + (booking.service_period === 'Dinner' ? 18 : booking.service_period === 'Lunch' ? 10 : 14), 0) / priorActiveBookings.length
        : 0;
    const priorNoShow = priorNoShowEligibleBookings.length ? (getEstimatedNoShowMetrics(previousBookings).count / priorNoShowEligibleBookings.length) * 100 : 0;

    const weekdayCounts = weekdayOrder.reduce((accumulator, dayLabel) => {
        accumulator[dayLabel] = 0;
        return accumulator;
    }, {});
    const hourCounts = {};
    const serviceCounts = {};
    activeBookings.forEach((booking) => {
        const weekday = new Intl.DateTimeFormat('en-AU', { weekday: 'long' }).format(parseDate(booking.booking_date));
        const hourLabel = new Intl.DateTimeFormat('en-AU', { hour: 'numeric', minute: '2-digit' }).format(parseDateTime(booking.booking_date, booking.start_time));
        weekdayCounts[weekday] = (weekdayCounts[weekday] || 0) + 1;
        hourCounts[hourLabel] = (hourCounts[hourLabel] || 0) + 1;
        serviceCounts[booking.service_period] = (serviceCounts[booking.service_period] || 0) + 1;
    });

    const peakDay = Object.entries(weekdayCounts).sort((left, right) => right[1] - left[1])[0] || ['-', 0];
    const peakHour = Object.entries(hourCounts).sort((left, right) => right[1] - left[1])[0] || ['-', 0];
    const peakService = Object.entries(serviceCounts).sort((left, right) => right[1] - left[1])[0] || ['-', 0];

    const tableUsageMap = new Map();
    filteredTables.forEach((table) => {
        tableUsageMap.set(table.table_id, {
            table_id: table.table_id,
            table_number: table.table_number || 'Unassigned',
            area_name: table.area_name,
            capacity: table.capacity,
            bookings: 0,
            bookedMinutes: 0,
            avgGuests: 0,
        });
    });

    bookingsWithTables.forEach((booking) => {
        if (!tableUsageMap.has(booking.table_id)) {
            return;
        }

        const table = tableUsageMap.get(booking.table_id);
        table.bookings += 1;
        table.bookedMinutes += Number(booking.duration_minutes || 0) + (booking.service_period === 'Dinner' ? 18 : 10);
        table.avgGuests += Number(booking.number_of_guests || 0);
    });

    const tableUsage = Array.from(tableUsageMap.values()).map((table) => ({
        ...table,
        avgGuests: table.bookings ? table.avgGuests / table.bookings : 0,
        fillRate: table.bookings && table.capacity > 0 ? (table.avgGuests / table.bookings / table.capacity) * 100 : 0,
    })).sort((left, right) => right.bookings - left.bookings || right.bookedMinutes - left.bookedMinutes);

    const turnoverRate = filteredTables.length ? activeBookings.length / filteredTables.length : 0;
    const rangeDays = Math.max(1, Math.round((range.end.getTime() - range.start.getTime()) / 86400000) + 1);
    const operatingMinutesPerDay = 12 * 60;
    const idleMinutes = filteredTables.length
        ? ((filteredTables.length * rangeDays * operatingMinutesPerDay) - bookingsWithTables.reduce((sum, booking) => sum + Number(booking.duration_minutes || 0), 0)) / (filteredTables.length * rangeDays)
        : 0;

    const areaNames = Array.from(new Set(filteredTables.map((table) => table.area_name).concat(activeBookings.map((booking) => booking.area_name)).filter(Boolean)));
    const areaMetrics = areaNames.map((areaName) => {
        const areaBookings = activeBookings.filter((booking) => booking.area_name === areaName);
        const areaBookingsWithTables = areaBookings.filter((booking) => booking.table_capacity > 0);
        const areaTables = filteredTables.filter((table) => table.area_name === areaName);
        const occupancy = areaBookingsWithTables.length
            ? (areaBookingsWithTables.reduce((sum, booking) => sum + Math.min(booking.number_of_guests, booking.table_capacity) / booking.table_capacity, 0) / areaBookingsWithTables.length) * 100
            : 0;
        const avgPartySize = areaBookings.length
            ? areaBookings.reduce((sum, booking) => sum + Number(booking.number_of_guests || 0), 0) / areaBookings.length
            : 0;
        const turnover = areaTables.length ? areaBookings.length / areaTables.length : 0;
        const dominantService = Object.entries(areaBookings.reduce((accumulator, booking) => {
            accumulator[booking.service_period] = (accumulator[booking.service_period] || 0) + 1;
            return accumulator;
        }, {})).sort((left, right) => right[1] - left[1])[0] || ['-', 0];

        return {
            area: areaName,
            bookings: areaBookings.length,
            occupancy,
            avgPartySize,
            tablesUsed: new Set(areaBookings.filter((booking) => booking.table_id).map((booking) => booking.table_id)).size,
            turnover,
            dominantService: dominantService[0],
        };
    });

    const topArea = areaMetrics.slice().sort((left, right) => ((right.occupancy * 0.6) + (right.turnover * 40)) - ((left.occupancy * 0.6) + (left.turnover * 40)))[0] || null;
    const busiestArea = areaMetrics.slice().sort((left, right) => right.bookings - left.bookings)[0] || null;
    const bestTurnoverArea = areaMetrics.slice().sort((left, right) => right.turnover - left.turnover)[0] || null;
    const cancelledBookings = filteredBookings.filter((booking) => booking.status === 'cancelled');
    const noShowBookings = filteredBookings.filter((booking) => booking.status === 'no_show');
    const completedBookings = filteredBookings.filter((booking) => booking.status === 'completed');
    const cancellationRate = filteredBookings.length ? (cancelledBookings.length / filteredBookings.length) * 100 : 0;
    const lateCancellationCount = cancelledBookings.length;
    const affectedSlot = Object.entries(estimatedNoShows.bySlot).sort((left, right) => right[1] - left[1])[0] || ['-', 0];

    const customerHistory = buildCustomerHistory(allBookings);
    const uniqueCustomers = new Map();
    activeBookings.forEach((booking) => {
        const key = getCustomerKey(booking);
        if (!uniqueCustomers.has(key)) {
            uniqueCustomers.set(key, booking);
        }
    });

    let newCustomers = 0;
    let returningCustomers = 0;
    uniqueCustomers.forEach((booking, key) => {
        const firstVisit = customerHistory.get(key);
        if (firstVisit && firstVisit >= range.start && firstVisit <= range.end) {
            newCustomers += 1;
        } else {
            returningCustomers += 1;
        }
    });

    const repeatGuestRate = uniqueCustomers.size ? (returningCustomers / uniqueCustomers.size) * 100 : 0;
    const averageLeadHours = activeBookings.length ? activeBookings.reduce((sum, booking) => sum + Math.max(0, Number(booking.lead_hours || 0)), 0) / activeBookings.length : 0;
    const partyBuckets = { '2 guests': 0, '4 guests': 0, '6 guests': 0, '8+': 0 };
    const partyModeMap = {};
    activeBookings.forEach((booking) => {
        const guests = Number(booking.number_of_guests || 0);
        partyModeMap[guests] = (partyModeMap[guests] || 0) + 1;
        if (guests <= 2) {
            partyBuckets['2 guests'] += 1;
        } else if (guests <= 4) {
            partyBuckets['4 guests'] += 1;
        } else if (guests <= 6) {
            partyBuckets['6 guests'] += 1;
        } else {
            partyBuckets['8+'] += 1;
        }
    });

    const mostCommonParty = Object.entries(partyModeMap).sort((left, right) => right[1] - left[1])[0] || ['-', 0];
    const popularTime = Object.entries(hourCounts).sort((left, right) => right[1] - left[1])[0] || ['-', 0];
    const largeTableMismatchCount = bookingsWithTables.filter((booking) => booking.table_capacity >= 6 && booking.number_of_guests <= 3).length;
    const topAreaTables = topArea ? filteredTables.filter((table) => table.area_name === topArea.area).map((table) => table.table_id) : [];
    const topAreaBookings = topArea ? bookingsWithTables.filter((booking) => topAreaTables.includes(booking.table_id)) : [];
    const topAreaTurnover = topAreaTables.length ? topAreaBookings.length / topAreaTables.length : 0;

    return {
        currentRangeLabel: range.label,
        totalBookings: filteredBookings.length,
        totalBookingsComparison: comparePeriods(filteredBookings.length, previousBookings.length),
        occupancyRate,
        occupancyComparison: comparePeriods(occupancyRate, priorOccupancy),
        avgPartySize,
        avgPartyComparison: comparePeriods(avgPartySize, priorAvgParty),
        noShowRate,
        noShowComparison: comparePeriods(noShowRate, priorNoShow),
        dwellMinutes,
        dwellComparison: comparePeriods(dwellMinutes, priorDwell),
        peakDay: peakDay[0],
        peakHour: peakHour[0],
        peakService: peakService[0],
        weekdayCounts,
        turnoverRate,
        idleMinutes,
        tableUsage,
        mostUsedTables: tableUsage.slice(0, 3),
        leastUsedTables: tableUsage.slice().reverse().slice(0, 3).reverse(),
        areaMetrics,
        topArea,
        busiestArea,
        bestTurnoverArea,
        cancellationRate,
        lateCancellationCount,
        estimatedNoShowCount: estimatedNoShows.count,
        affectedSlot: affectedSlot[0],
        noShowByWeekday: weekdayOrder.map((day) => estimatedNoShows.byWeekday[day] || 0),
        cancelledByWeekday: weekdayOrder.map((day) => cancelledBookings.filter((booking) => new Intl.DateTimeFormat('en-AU', { weekday: 'long' }).format(parseDate(booking.booking_date)) === day).length),
        completedByWeekday: weekdayOrder.map((day) => completedBookings.filter((booking) => new Intl.DateTimeFormat('en-AU', { weekday: 'long' }).format(parseDate(booking.booking_date)) === day).length),
        newCustomers,
        returningCustomers,
        averageLeadHours,
        mostCommonParty: mostCommonParty[0],
        repeatGuestRate,
        partyBuckets,
        popularTime: popularTime[0],
        averageLeadDays: averageLeadHours / 24,
        largeTableMismatchCount,
        topAreaTurnover,
    };
}

function createOrUpdateChart(canvasId, config) {
    if (charts[canvasId]) {
        charts[canvasId].data = config.data;
        charts[canvasId].options = config.options;
        charts[canvasId].update();
        return charts[canvasId];
    }

    charts[canvasId] = new Chart(document.getElementById(canvasId), config);
    return charts[canvasId];
}

function lineChartConfig(labels, values) {
    return {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Bookings',
                data: values,
                borderColor: 'var(--dm-info-text)',
                backgroundColor: 'rgba(91, 133, 199, 0.14)',
                pointBackgroundColor: 'var(--dm-surface)',
                pointBorderColor: 'var(--dm-info-text)',
                borderWidth: 2.5,
                pointHoverRadius: 5,
                pointRadius: 3,
                fill: true,
                tension: 0.38,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'var(--dm-accent-dark)',
                    callbacks: { label(context) { return `Bookings: ${context.raw}`; } }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: 'var(--dm-text-soft)' } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: 'rgba(227, 233, 242, 0.9)' }, ticks: { color: 'var(--dm-text-soft)', precision: 0 } }
            }
        }
    };
}

function barChartConfig(labels, values, backgroundColor, horizontal = false, tooltipFormatter = null) {
    return {
        type: 'bar',
        data: { labels, datasets: [{ data: values, backgroundColor, borderRadius: 12, borderSkipped: false, maxBarThickness: horizontal ? 18 : 26 }] },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: tooltipFormatter ? { label: tooltipFormatter } : undefined }
            },
            scales: {
                x: { beginAtZero: true, border: { display: false }, grid: { display: horizontal, color: 'rgba(227, 233, 242, 0.88)' }, ticks: { color: 'var(--dm-text-soft)', precision: 0 } },
                y: { border: { display: false }, grid: { display: false }, ticks: { color: 'var(--dm-text-soft)' } }
            }
        }
    };
}

function donutChartConfig(labels, values, colors) {
    return {
        type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0, hoverOffset: 6 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 10, color: 'var(--dm-text-muted)' } }
            }
        }
    };
}

function stackedBarChartConfig(labels, completed, cancelled, noShow) {
    return {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Completed', data: completed, backgroundColor: 'var(--dm-confirmed-text)', borderRadius: 10, borderSkipped: false },
                { label: 'Cancelled', data: cancelled, backgroundColor: 'var(--dm-danger-text)', borderRadius: 10, borderSkipped: false },
                { label: 'No-show', data: noShow, backgroundColor: 'var(--dm-pending-text)', borderRadius: 10, borderSkipped: false }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', align: 'start', labels: { usePointStyle: true, pointStyle: 'circle', color: 'var(--dm-text-muted)' } } },
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: { color: 'var(--dm-text-soft)' } },
                y: { stacked: true, beginAtZero: true, border: { display: false }, grid: { color: 'rgba(227, 233, 242, 0.9)' }, ticks: { color: 'var(--dm-text-soft)', precision: 0 } }
            }
        }
    };
}

function setTrendElement(elementId, comparison) {
    const element = document.getElementById(elementId);
    element.className = `kpi-trend${comparison.state ? ` ${comparison.state}` : ''}`;
    element.textContent = comparison.text;
}

function renderZoneTable(metrics) {
    const container = document.getElementById('zonePerformanceTable');
    const maxBookings = Math.max(1, ...metrics.areaMetrics.map((area) => area.bookings));

    container.innerHTML = `
        <div class="zone-row head">
            <div>Area</div>
            <div>Bookings</div>
            <div>Occupancy</div>
            <div>Tables used</div>
            <div>Avg party size</div>
        </div>
        ${metrics.areaMetrics.map((area) => `
            <div class="zone-row">
                <div class="zone-name">
                    <strong>${area.area}</strong>
                    <div class="progress-track"><span style="width:${(area.bookings / maxBookings) * 100}%"></span></div>
                </div>
                <div>${formatNumber(area.bookings)}</div>
                <div>${formatPercent(area.occupancy, 0)}</div>
                <div>${formatNumber(area.tablesUsed)}</div>
                <div>${formatNumber(area.avgPartySize, 1)}</div>
            </div>
        `).join('')}
    `;
}

function renderHeatmap(metrics) {
    const container = document.getElementById('tableHeatmap');
    if (!metrics.tableUsage.length) {
        container.innerHTML = '<div class="section-empty"><div><i class="fa-regular fa-square"></i>No tables available for this filter.</div></div>';
        return;
    }

    const maxBookings = Math.max(1, ...metrics.tableUsage.map((table) => table.bookings));
    container.innerHTML = metrics.tableUsage.slice(0, 12).map((table) => `
        <div class="heatmap-cell">
            <div class="heatmap-cell-label">T${String(table.table_number || table.table_id).padStart(2, '0')}</div>
            <div class="heatmap-cell-meta">${formatNumber(table.bookings)} bookings</div>
            <div class="heatmap-bar"><span style="width:${(table.bookings / maxBookings) * 100}%"></span></div>
        </div>
    `).join('');
}

function renderLeastUsedTables(metrics) {
    const container = document.getElementById('leastUsedTablesList');
    const tables = metrics.leastUsedTables.length ? metrics.leastUsedTables : metrics.tableUsage.slice(-3);
    if (!tables.length) {
        container.innerHTML = '<div class="section-empty"><div><i class="fa-regular fa-clock"></i>No table utilisation data.</div></div>';
        return;
    }

    container.innerHTML = tables.map((table) => `
        <div class="table-list-item">
            <div><strong>T${table.table_number || table.table_id}</strong><span>${table.area_name}</span></div>
            <div><strong>${formatNumber(table.bookings)}</strong><span>bookings</span></div>
        </div>
    `).join('');
}

function renderInsightTags(metrics) {
    const tags = [];
    const underusedTable = metrics.tableUsage.slice().sort((left, right) => left.bookings - right.bookings)[0];
    if (underusedTable) {
        tags.push(`Table ${underusedTable.table_number || underusedTable.table_id} underused`);
    }
    if (metrics.largeTableMismatchCount > 0) {
        tags.push('Large tables are being assigned to small groups');
    }
    if (metrics.topArea && metrics.topAreaTurnover > metrics.turnoverRate) {
        tags.push(`${metrics.topArea.area} has the highest turnover`);
    }
    if (!tags.length) {
        tags.push('Utilisation is balanced across active tables');
    }
    document.getElementById('utilisationInsightTags').innerHTML = tags.map((tag) => `<span class="insight-tag"><i class="fa-solid fa-lightbulb"></i>${tag}</span>`).join('');
}

function renderCustomerTags(metrics) {
    const tags = [
        `${formatNumber(metrics.newCustomers)} new guests vs ${formatNumber(metrics.returningCustomers)} returning`,
        `${metrics.popularTime || '-'} is the most popular booking time`,
        `${formatNumber(metrics.averageLeadDays, 1)} days booked in advance on average`
    ];
    document.getElementById('customerInsightTags').innerHTML = tags.map((tag) => `<span class="insight-tag"><i class="fa-solid fa-user-group"></i>${tag}</span>`).join('');
}

function renderCancellationInsights(metrics) {
    const items = [
        `${metrics.affectedSlot} has the highest no-show count.`,
        'Reminder messages may reduce missed bookings.',
        metrics.lateCancellationCount > 0 ? 'Late cancellations increased this week.' : 'Late cancellations remain low relative to demand.'
    ];
    document.getElementById('cancellationInsights').innerHTML = items.map((item) => `<div class="insight-list-item"><i class="fa-solid fa-circle-exclamation"></i><span>${item}</span></div>`).join('');
}

function renderOperationalInsights(metrics) {
    const topArea = metrics.topArea ? metrics.topArea.area : 'Main Bar';
    const cards = [
        { label: 'Capacity', title: `${topArea} is leading this period.`, copy: `${topArea} has the highest combined occupancy and table efficiency.` },
        { label: 'Turnover', title: `${formatNumber(metrics.turnoverRate, 1)}x average turnover across active tables.`, copy: 'Turnover is strongest across the most frequently used table groups.' },
        { label: 'Peak periods', title: `${metrics.peakDay} at ${metrics.peakHour} is the busiest period.`, copy: `${metrics.peakService} service has the highest booking volume in the selected range.` },
        { label: 'Attendance risk', title: `No-show rate is ${formatPercent(metrics.noShowRate, 0)}.`, copy: 'Based on recorded booking outcomes.' }
    ];

    if (metrics.largeTableMismatchCount > 0) {
        cards.splice(2, 0, { label: 'Allocation', title: 'Large tables are frequently assigned to small groups.', copy: 'Review table allocation to improve capacity usage.' });
    }

    document.getElementById('operationalInsightsGrid').innerHTML = cards.slice(0, 5).map((card) => `
        <article class="operation-card">
            <div class="operation-pill"><i class="fa-solid fa-wand-magic-sparkles"></i>${card.label}</div>
            <h3>${card.title}</h3>
            <p>${card.copy}</p>
        </article>
    `).join('');
}

function renderAnalytics() {
    const range = getSelectedRange();
    const filteredBookings = filterBookingsByRange(analyticsSource.bookings, range);
    const previousBookings = filterBookingsByRange(analyticsSource.bookings, getPreviousRange(range));
    const filteredTables = filterTablesByArea(analyticsSource.tables);

    if (!analyticsSource.bookings.length) {
        analyticsContent.hidden = true;
        globalEmptyState.hidden = false;
        return;
    }

    if (!filteredBookings.length) {
        analyticsContent.hidden = true;
        globalEmptyState.hidden = false;
        return;
    }

    analyticsContent.hidden = false;
    globalEmptyState.hidden = true;
    analyticsContent.classList.add('is-refreshing');

    const metrics = buildMetrics(filteredBookings, previousBookings, analyticsSource.bookings, filteredTables, range);
    const trendSeries = buildTrendSeries(filteredBookings, range, analyticsState.period);

    document.getElementById('kpiSectionNote').textContent = `${metrics.currentRangeLabel} compared with the previous equivalent period.`;
    document.getElementById('kpiTotalBookings').textContent = formatNumber(metrics.totalBookings);
    document.getElementById('kpiOccupancyRate').textContent = formatPercent(metrics.occupancyRate, 0);
    document.getElementById('kpiAvgPartySize').textContent = `${formatNumber(metrics.avgPartySize, 1)} guests`;
    document.getElementById('kpiNoShowRate').textContent = formatPercent(metrics.noShowRate, 0);
    document.getElementById('kpiDwellTime').textContent = formatDuration(metrics.dwellMinutes);

    setTrendElement('kpiTotalTrend', metrics.totalBookingsComparison);
    setTrendElement('kpiOccupancyTrend', metrics.occupancyComparison);
    setTrendElement('kpiAvgPartyTrend', metrics.avgPartyComparison);
    setTrendElement('kpiNoShowTrend', metrics.noShowComparison);
    setTrendElement('kpiDwellTrend', metrics.dwellComparison);

    document.getElementById('peakDayValue').textContent = metrics.peakDay;
    document.getElementById('peakHourValue').textContent = metrics.peakHour;
    document.getElementById('peakServiceValue').textContent = metrics.peakService;
    document.getElementById('turnoverRateValue').textContent = `${formatNumber(metrics.turnoverRate, 1)}x`;
    document.getElementById('idleTimeValue').textContent = formatDuration(metrics.idleMinutes);
    document.getElementById('mostUsedTablesValue').textContent = metrics.mostUsedTables.map((table) => `T${table.table_number || table.table_id}`).join(', ') || '-';

    document.getElementById('topZoneName').textContent = metrics.topArea ? metrics.topArea.area : '-';
    document.getElementById('topZoneMetricNote').textContent = metrics.topArea ? `${formatPercent(metrics.topArea.occupancy, 0)} occupancy in the selected period.` : 'Highest occupancy in the selected period.';
    document.getElementById('busiestAreaValue').textContent = metrics.busiestArea ? metrics.busiestArea.area : '-';
    document.getElementById('bestTurnoverAreaValue').textContent = metrics.bestTurnoverArea ? metrics.bestTurnoverArea.area : '-';
    document.getElementById('topAreaServiceValue').textContent = metrics.topArea ? metrics.topArea.dominantService : '-';
    document.getElementById('topZoneNameCard').textContent = metrics.topArea ? metrics.topArea.area : '-';
    document.getElementById('topZoneCopy').textContent = metrics.topArea ? `${metrics.topArea.area} has the highest occupancy and the strongest ${String(metrics.topArea.dominantService || 'service').toLowerCase()} demand this period.` : 'Strong occupancy and consistent booking demand this period.';

    document.getElementById('cancellationRateValue').textContent = formatPercent(metrics.cancellationRate, 0);
    document.getElementById('cancellationNoShowValue').textContent = formatPercent(metrics.noShowRate, 0);
    document.getElementById('lateCancellationValue').textContent = formatNumber(metrics.lateCancellationCount);
    document.getElementById('affectedSlotValue').textContent = metrics.affectedSlot;

    document.getElementById('customerMixValue').textContent = `${formatNumber(metrics.newCustomers)} / ${formatNumber(metrics.returningCustomers)}`;
    document.getElementById('leadTimeValue').textContent = `${formatNumber(metrics.averageLeadDays, 1)} days`;
    document.getElementById('partySizeModeValue').textContent = metrics.mostCommonParty === '-' ? '-' : `${metrics.mostCommonParty} guests`;
    document.getElementById('repeatGuestRateValue').textContent = formatPercent(metrics.repeatGuestRate, 0);

    document.getElementById('heroFocusValue').textContent = `${metrics.peakDay} ${String(metrics.peakService || 'dinner').toLowerCase()}`;
    document.getElementById('heroFocusNote').textContent = `${metrics.peakHour} is the busiest arrival time in the selected range.`;
    document.getElementById('heroZoneValue').textContent = metrics.topArea ? metrics.topArea.area : 'Main Bar';
    document.getElementById('heroZoneNote').textContent = metrics.topArea ? `${metrics.topArea.area} is setting the pace for occupancy and booking demand.` : 'Area performance will appear as bookings are recorded.';

    createOrUpdateChart('bookingTrendChart', lineChartConfig(trendSeries.labels, trendSeries.values));
    createOrUpdateChart('peakDemandChart', barChartConfig(weekdayOrder, weekdayOrder.map((day) => metrics.weekdayCounts[day] || 0), 'rgba(200, 147, 61, 0.82)', false, (context) => `Bookings: ${context.raw}`));
    createOrUpdateChart('tableUsageChart', barChartConfig(metrics.tableUsage.slice(0, 6).map((table) => `T${table.table_number || table.table_id}`), metrics.tableUsage.slice(0, 6).map((table) => table.bookings), 'rgba(91, 133, 199, 0.82)', true, (context) => `Bookings: ${context.raw}`));
    createOrUpdateChart('areaDemandChart', barChartConfig(metrics.areaMetrics.map((area) => area.area), metrics.areaMetrics.map((area) => area.bookings), 'rgba(79, 139, 121, 0.82)', false, (context) => `Bookings: ${context.raw}`));
    createOrUpdateChart('cancellationChart', stackedBarChartConfig(weekdayOrder, metrics.completedByWeekday, metrics.cancelledByWeekday, metrics.noShowByWeekday));
    createOrUpdateChart('customerMixChart', donutChartConfig(['New', 'Returning'], [metrics.newCustomers, metrics.returningCustomers], ['var(--dm-pending-text)', 'var(--dm-info-text)']));
    createOrUpdateChart('partySizeChart', barChartConfig(Object.keys(metrics.partyBuckets), Object.values(metrics.partyBuckets), 'rgba(79, 139, 121, 0.82)', false, (context) => `Bookings: ${context.raw}`));

    renderZoneTable(metrics);
    renderHeatmap(metrics);
    renderLeastUsedTables(metrics);
    renderInsightTags(metrics);
    renderCustomerTags(metrics);
    renderCancellationInsights(metrics);
    renderOperationalInsights(metrics);

    requestAnimationFrame(() => {
        analyticsContent.classList.remove('is-refreshing');
    });
}

function syncPeriodInputs() {
    const inputGroups = [
        periodStartDate,
        periodEndDate,
        periodStartWeek,
        periodEndWeek,
        periodStartMonth,
        periodEndMonth,
        periodStartYear,
        periodEndYear,
    ];

    inputGroups.forEach((input) => input.classList.add('is-hidden'));

    if (analyticsState.period === 'weekly') {
        periodStartWeek.classList.remove('is-hidden');
        periodEndWeek.classList.remove('is-hidden');
        analyticsState.startValue = periodStartWeek.value;
        analyticsState.endValue = periodEndWeek.value;
        return;
    }

    if (analyticsState.period === 'monthly') {
        periodStartMonth.classList.remove('is-hidden');
        periodEndMonth.classList.remove('is-hidden');
        analyticsState.startValue = periodStartMonth.value;
        analyticsState.endValue = periodEndMonth.value;
        return;
    }

    if (analyticsState.period === 'yearly') {
        periodStartYear.classList.remove('is-hidden');
        periodEndYear.classList.remove('is-hidden');
        analyticsState.startValue = periodStartYear.value;
        analyticsState.endValue = periodEndYear.value;
        return;
    }

    periodStartDate.classList.remove('is-hidden');
    periodEndDate.classList.remove('is-hidden');
    analyticsState.startValue = periodStartDate.value;
    analyticsState.endValue = periodEndDate.value;
}

function applyDefaultPeriodValues() {
    const today = parseDate(analyticsSource.today);

    periodStartDate.value = analyticsSource.today;
    periodEndDate.value = analyticsSource.today;
    periodStartWeek.value = formatWeekValue(today);
    periodEndWeek.value = formatWeekValue(today);
    periodStartMonth.value = formatMonthValue(today);
    periodEndMonth.value = formatMonthValue(today);
    periodStartYear.value = String(today.getFullYear());
    periodEndYear.value = String(today.getFullYear());
    syncPeriodInputs();
}

document.querySelectorAll('[data-period]').forEach((button) => {
    button.addEventListener('click', () => {
        analyticsState.period = button.getAttribute('data-period');
        document.querySelectorAll('[data-period]').forEach((chip) => {
            chip.classList.toggle('is-active', chip === button);
        });
        syncPeriodInputs();
        renderAnalytics();
    });
});

[periodStartDate, periodEndDate, periodStartWeek, periodEndWeek, periodStartMonth, periodEndMonth, periodStartYear, periodEndYear].forEach((input) => {
    input.addEventListener('change', () => {
        syncPeriodInputs();
        renderAnalytics();
    });
});

areaFilter.addEventListener('change', () => {
    analyticsState.area = areaFilter.value;
    renderAnalytics();
});

applyDefaultPeriodValues();

renderAnalytics();
</script>
</body>
</html>



