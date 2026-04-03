<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";

// Check if user is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin-login.php');
    exit();
}

// Get all tables
ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);

$areas = $pdo->query("SELECT area_id, name, display_order, table_number_start, table_number_end, is_active FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

$tables = $pdo->query(" 
    SELECT rt.table_id, rt.table_number, rt.capacity, rt.area_id, rt.sort_order,
           ta.name AS area_name, ta.display_order AS area_display_order
    FROM restaurant_tables rt
    LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
    ORDER BY ta.display_order ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get selected date (default to today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedDayName = date('l', strtotime($selectedDate));
$isCurrentDate = ($selectedDate === date('Y-m-d'));
$selectedDayLabel = $isCurrentDate ? 'Today' : $selectedDayName;
$selectedDateDisplay = date('j F, Y', strtotime($selectedDate));
$selectedYearDisplay = date('Y', strtotime($selectedDate));
$selectedShortDateDisplay = date('D, M d', strtotime($selectedDate));

// Get bookings for selected date
$stmt = $pdo->prepare("
    SELECT b.*, COALESCE(b.customer_name_override, b.customer_name, u.name) as customer_name,
           GROUP_CONCAT(DISTINCT bta.table_id ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ',') AS assigned_table_ids,
           GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ',') AS assigned_table_numbers
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
    LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
    WHERE b.booking_date = ? AND b.status IN ('pending', 'confirmed')
    GROUP BY b.booking_id
    ORDER BY b.start_time ASC
");
$stmt->execute([$selectedDate]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bookingStats = [
    'total_bookings' => count($bookings),
    'total_people' => 0,
    'lunch_bookings' => 0,
    'lunch_people' => 0,
    'dinner_bookings' => 0,
    'dinner_people' => 0,
];

foreach ($bookings as $statBooking) {
    $guestCount = (int) ($statBooking['number_of_guests'] ?? 0);
    $startTime = isset($statBooking['start_time']) ? substr((string) $statBooking['start_time'], 0, 8) : '';

    $bookingStats['total_people'] += $guestCount;

    if ($startTime >= '12:00:00' && $startTime < '17:00:00') {
        $bookingStats['lunch_bookings']++;
        $bookingStats['lunch_people'] += $guestCount;
    } elseif ($startTime >= '17:00:00') {
        $bookingStats['dinner_bookings']++;
        $bookingStats['dinner_people'] += $guestCount;
    }
}

foreach ($bookings as &$booking) {
    $assignedTableIds = [];
    if (!empty($booking['assigned_table_ids'])) {
        $assignedTableIds = array_values(array_filter(array_map('intval', explode(',', $booking['assigned_table_ids']))));
    }

    $assignedTableNumbers = [];
    if (!empty($booking['assigned_table_numbers'])) {
        $assignedTableNumbers = array_values(array_filter(array_map('trim', explode(',', $booking['assigned_table_numbers'])), 'strlen'));
    }

    $booking['assigned_table_ids'] = $assignedTableIds;
    $booking['assigned_table_numbers'] = $assignedTableNumbers;
    $booking['table_id'] = !empty($assignedTableIds) ? $assignedTableIds[0] : null;
    $booking['table_number'] = !empty($assignedTableNumbers) ? $assignedTableNumbers[0] : null;
}
unset($booking);

// Convert bookings to JS array
$bookingsJson = json_encode($bookings);

$adminPageTitle = 'Timeline';
$adminPageIcon = 'fa-calendar-days';
$adminNotificationCount = (int) $bookingStats['total_bookings'];
$adminProfileName = $_SESSION['name'] ?? 'Admin';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineMate - Timeline Booking Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fb;
        }

        body.modal-open {
            overflow: hidden;
        }

        .container-fluid {
            display: flex;
            height: 100vh;
        }

        .sidebar {
            width: 88px;
            background: #111827;
            color: white;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: width 0.25s ease;
            overflow-x: hidden;
            flex-shrink: 0;
        }

        .sidebar:hover {
            width: 260px;
        }

        .sidebar h4 {
            color: #f4b400;
            margin-bottom: 30px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 14px;
            white-space: nowrap;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 15px;
            color: #ddd;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: background 0.2s ease, color 0.2s ease, justify-content 0.2s ease;
            white-space: nowrap;
        }

        .sidebar:hover a {
            justify-content: flex-start;
        }

        .sidebar h4 i,
        .sidebar a i {
            width: 24px;
            min-width: 24px;
            text-align: center;
            font-size: 20px;
        }

        .brand-label,
        .nav-label {
            opacity: 0;
            max-width: 0;
            margin-left: 0;
            overflow: hidden;
            transition: opacity 0.2s ease, max-width 0.25s ease, margin-left 0.25s ease;
        }

        .sidebar:hover .brand-label,
        .sidebar:hover .nav-label {
            opacity: 1;
            max-width: 180px;
            margin-left: 12px;
        }

        .sidebar:not(:hover) h4 {
            justify-content: center;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #f4b400;
            color: #111827;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .topbar {
            background: white;
            min-height: 78px;
            padding: 0 30px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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

        .topbar-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: #f4b400;
            font-size: 28px;
            font-weight: 700;
            white-space: nowrap;
        }

        .topbar-brand-label {
            font-size: 18px;
            line-height: 1;
        }

        .topbar-page {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            color: #111827;
        }

        .topbar-page i {
            color: #111827;
            font-size: 20px;
        }

        .topbar-page-title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #1f2937;
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
            border: none;
            border-radius: 14px;
            background: #f9fafb;
            color: #111827;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .topbar-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            background: #ef4444;
            color: white;
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
            border-radius: 16px;
            background: #f9fafb;
        }

        .topbar-profile-icon {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            background: #111827;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .topbar-profile-name {
            color: #374151;
            font-weight: 600;
        }

        .timeline-panel-tools {
            display: flex;
            flex-direction: column;
            gap: 0;
            margin-bottom: 14px;
            padding: 12px 14px;
            border: 1px solid #e6ebf2;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }

        .timeline-date-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas:
                'year action'
                'date nav';
            gap: 4px 14px;
            align-items: end;
        }

        .timeline-date-year {
            grid-area: year;
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0;
            min-width: 0;
        }

        .timeline-date-primary {
            grid-area: date;
            color: #1f2937;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .timeline-date-nav-row {
            grid-area: nav;
            display: inline-flex;
            align-items: center;
            justify-self: end;
            gap: 8px;
        }

        .timeline-date-nav,
        .timeline-date-picker-trigger {
            border: none;
            background: transparent;
            color: #374151;
            width: 14px;
            min-width: 14px;
            height: 14px;
            padding: 0;
            font-size: 13px;
            line-height: 1;
            font-weight: 800;
            cursor: pointer;
        }

        .timeline-date-picker-trigger i {
            font-size: 13px;
            line-height: 1;
        }

        .timeline-date-nav:hover,
        .timeline-date-picker-trigger:hover {
            color: #111827;
        }

        .calendar {
            display: none;
        }

        /* CONTENT AREA */
        .content {
            display: flex;
            flex: 1;
            overflow: hidden;
            gap: 16px;
            padding: 16px;
            min-height: 0;
        }

        /* LEFT PANEL - CALENDAR & TABLES */
        .left-panel {
            width: 260px;
            background: white;
            border: 1px solid #e6ebf2;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
        }

        .booking-list {
            padding: 12px 14px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }

        .booking-list-empty {
            margin: 0;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 500;
            font-family: 'Poppins', sans-serif;
            color: #6b7280;
            letter-spacing: 0.01em;
        }

        .booking-list-tabs {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }

        .booking-list-tabs-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .booking-list-tab {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #374151;
            border-radius: 10px;
            padding: 9px 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .booking-list-tab.active {
            background: #111827;
            border-color: #111827;
            color: #ffffff;
        }

        .booking-list-tab.pending-span {
            width: 100%;
        }

        .booking-list-tab.pending-span.has-pending {
            background: linear-gradient(135deg, #fff1f2, #ffe4e6);
            border-color: #fb7185;
            color: #be123c;
            box-shadow: 0 0 0 1px rgba(251, 113, 133, 0.2), 0 10px 22px rgba(244, 63, 94, 0.16);
            animation: pendingPulse 1.8s ease-in-out infinite;
        }

        .booking-list-tab.pending-span.has-pending.active {
            background: linear-gradient(135deg, #e11d48, #be123c);
            border-color: #be123c;
            color: #ffffff;
            box-shadow: 0 0 0 1px rgba(225, 29, 72, 0.28), 0 12px 24px rgba(190, 24, 93, 0.28);
        }

        @keyframes pendingPulse {
            0%, 100% {
                box-shadow: 0 0 0 1px rgba(251, 113, 133, 0.2), 0 10px 22px rgba(244, 63, 94, 0.16);
            }
            50% {
                box-shadow: 0 0 0 1px rgba(251, 113, 133, 0.35), 0 14px 28px rgba(244, 63, 94, 0.24);
            }
        }

        .booking-item {
            margin-bottom: 8px;
            min-height: var(--timeline-row-height);
            padding: 5px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
            cursor: grab;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .booking-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            min-width: 0;
        }

        .booking-item-top-left,
        .booking-item-top-right {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
        }

        .booking-item-top-right {
            flex-shrink: 0;
        }

        .booking-item-time {
            font-size: 11px;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
        }

        .booking-item-name {
            margin-top: 1px;
            font-size: 10px;
            font-weight: 600;
            color: #111827;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
            display: block;
        }

        .booking-item-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            min-width: 0;
        }

        .booking-item-bottom-right {
            color: #6b7280;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .booking-item-meta {
            color: #6b7280;
            font-size: 10px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .booking-item-table {
            color: #374151;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .booking-item-note-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 7px;
            flex-shrink: 0;
        }

        .booking-item.dragging {
            opacity: 0.6;
        }

        .booking-item-action-btn {
            border: 1px solid #c7d2fe;
            background: #eef2ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            flex-shrink: 0;
        }

        .booking-item-action-btn:hover {
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .booking-item-action-btn:disabled {
            opacity: 0.6;
            cursor: wait;
        }

        .tables-section {
            padding: 16px 16px 16px 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .add-booking-button {
            width: 100%;
            border: none;
            border-radius: 12px;
            background: #111827;
            color: #fff;
            padding: 9px 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: background 0.2s ease, transform 0.2s ease;
            font-size: 12px;
        }

        .add-booking-button:hover {
            background: #1f2937;
            transform: translateY(-1px);
        }

        .stats-card {
            margin-top: 12px;
            padding: 0;
            border: 1px solid #e6ebf2;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .stats-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .stats-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #ffffff;
            border-bottom: 1px solid #e8edf4;
        }

        .stats-item:last-child {
            border-bottom: none;
        }

        .stats-item-label {
            font-size: 10px;
            font-weight: 700;
            color: #7a8597;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            width: 52px;
            flex-shrink: 0;
        }

        .stats-item-value {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
            flex: 1;
            min-width: 0;
        }

        .stats-item-bookings,
        .stats-item-people {
            white-space: nowrap;
        }

        .stats-item-bookings {
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stats-item-people {
            font-size: 12px;
            flex-shrink: 0;
        }

        .left-panel-footer {
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .modal-backdrop-custom {
            position: fixed;
            inset: 0;
            background: rgba(17, 24, 39, 0.62);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 2000;
            backdrop-filter: blur(6px);
        }

        .modal-backdrop-custom.open {
            display: flex;
        }

        .booking-modal-card {
            width: min(100%, 460px);
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 30px 100px rgba(0, 0, 0, 0.28);
            padding: 26px;
            border: 1px solid rgba(229, 231, 235, 0.9);
            transform: translateY(18px) scale(0.98);
            opacity: 0;
            transition: transform 0.22s ease, opacity 0.22s ease;
        }

        .modal-backdrop-custom.open .booking-modal-card {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .booking-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e5e7eb;
        }

        .booking-modal-header h5 {
            margin: 0;
            font-size: 22px;
            color: #111827;
        }

        .booking-modal-close {
            border: none;
            background: #f3f4f6;
            width: 38px;
            height: 38px;
            border-radius: 999px;
            font-size: 22px;
            line-height: 1;
            color: #6b7280;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .booking-modal-close:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .modal-form-group {
            margin-bottom: 14px;
        }

        .modal-form-group label {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .modal-form-group input,
        .modal-form-group select,
        .modal-form-group textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 10px 12px;
            font: inherit;
        }

        .modal-form-group textarea {
            min-height: 96px;
            resize: vertical;
        }

        .modal-helper-text {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
        }

        .modal-error {
            display: none;
            margin-bottom: 14px;
            border-radius: 10px;
            padding: 10px 12px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 14px;
        }

        .booking-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .booking-modal-actions button {
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .booking-modal-cancel {
            background: #e5e7eb;
            color: #111827;
        }

        .booking-modal-submit {
            background: #f4b400;
            color: #111827;
        }

        .booking-modal-danger {
            background: #dc2626;
            color: #ffffff;
        }

        .booking-modal-danger:hover {
            background: #b91c1c;
        }

        .booking-modal-danger-small {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .booking-modal-danger-small:hover {
            background: #fecaca;
        }

        .booking-detail-topbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-bottom: 10px;
        }

        .booking-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .booking-detail-grid .full-width {
            grid-column: 1 / -1;
        }

        .booking-details-card {
            width: min(100%, 560px);
            padding: 20px;
        }

        .booking-details-card .booking-modal-header {
            margin-bottom: 12px;
            padding-bottom: 12px;
        }

        .booking-details-card .booking-modal-header h5 {
            font-size: 18px;
        }

        .booking-details-card .booking-meta-chip {
            margin-top: 8px;
        }

        .booking-details-card .modal-form-group {
            margin-bottom: 0;
        }

        .booking-details-card .modal-form-group label {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .booking-details-card .modal-form-group input,
        .booking-details-card .modal-form-group textarea {
            padding: 9px 11px;
        }

        .booking-create-card {
            width: min(100%, 560px);
            padding: 20px;
        }

        .booking-create-card .booking-modal-header {
            margin-bottom: 12px;
            padding-bottom: 12px;
        }

        .booking-create-card .booking-modal-header h5 {
            font-size: 18px;
        }

        .booking-create-card .modal-form-group {
            margin-bottom: 0;
        }

        .booking-create-card .modal-form-group label {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .booking-create-card .modal-form-group input,
        .booking-create-card .modal-form-group textarea {
            padding: 9px 11px;
        }

        .booking-inline-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px dashed #d1d5db;
            background: #f9fafb;
            color: #374151;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.18s ease, border-color 0.18s ease, color 0.18s ease;
        }

        .booking-inline-trigger:hover {
            background: #f3f4f6;
            border-color: #cbd5e1;
            color: #111827;
        }

        .booking-inline-trigger.is-active {
            border-style: solid;
            background: #eef2ff;
            border-color: #c7d2fe;
            color: #3730a3;
        }

        .is-hidden {
            display: none !important;
        }

        .booking-time-pair {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 8px;
            align-items: center;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 6px 10px;
            background: #ffffff;
        }

        .booking-time-pair input {
            border: none !important;
            padding: 4px 0 !important;
            background: transparent;
            min-width: 0;
            box-shadow: none;
        }

        .booking-time-pair-separator {
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .booking-readonly-input {
            background: #f8fafc;
            color: #334155;
            font-weight: 600;
        }

        .booking-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 600;
        }

        .table-label.clickable {
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .table-label.clickable:hover {
            background: #fff8db;
            color: #b45309;
        }

        @media (max-width: 520px) {
            .booking-detail-grid {
                grid-template-columns: 1fr;
            }

            .booking-time-pair {
                grid-template-columns: 1fr;
            }

            .booking-time-pair-separator {
                display: none;
            }
        }

        .add-table-row {
            background: #eff6ff;
        }

        .add-table-inline-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: #2563eb;
            color: white;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            font-size: 18px;
            line-height: 1;
        }

        .add-table-inline-btn:hover {
            background: #1d4ed8;
        }

        .calendar input {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            background: white;
        }

        .today-button {
            grid-area: action;
            justify-self: end;
            align-self: end;
            border: none;
            background: transparent;
            width: auto;
            min-width: 0;
            padding: 0;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            white-space: nowrap;
        }

        .today-button.is-hidden {
            visibility: hidden;
            pointer-events: none;
        }

        .today-button:hover {
            color: #111827;
        }

        @media (max-width: 1100px) {
            .topbar {
                flex-wrap: wrap;
            }

            .topbar-left,
            .topbar-right {
                flex: 1 1 100%;
            }

            .topbar-left,
            .topbar-right {
                justify-content: space-between;
            }

            .timeline-date-card {
                grid-template-columns: 1fr;
                grid-template-areas:
                    'year'
                    'date'
                    'action'
                    'nav';
                gap: 6px;
            }

            .today-button,
            .timeline-date-nav-row {
                justify-self: start;
            }

            .timeline-toolbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .area-filter-bar {
                justify-content: flex-start;
            }
        }

        /* TABLES LIST */
        .tables-section h6 {
            font-weight: 600;
            margin-bottom: 15px;
            color: #1f2937;
        }

        .table-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 500px;
            overflow-y: auto;
        }

        .table-item {
            padding: 10px 12px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 13px;
            font-weight: 500;
        }

        .table-item:hover {
            background: #e5e7eb;
            border-color: #d1d5db;
        }

        .table-item.selected {
            background: #f4b400;
            color: white;
            border-color: #f4b400;
        }

        /* TIMELINE AREA */
        .timeline-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: white;
            border: 1px solid #e6ebf2;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            min-width: 0;
        }

        .timeline-toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid #e8edf4;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            overflow: hidden;
        }

        .area-filter-bar {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            flex: 1;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
        }

        .area-filter-chip {
            border: 1px solid #dbe3ef;
            background: #ffffff;
            color: #334155;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
            flex-shrink: 0;
        }

        .area-filter-chip:hover {
            border-color: #c3d0e3;
            transform: translateY(-1px);
        }

        .area-filter-chip.dragging {
            opacity: 0.45;
            transform: scale(0.98);
        }

        .area-filter-chip.drop-before {
            box-shadow: inset 3px 0 0 #2563eb;
        }

        .area-filter-chip.drop-after {
            box-shadow: inset -3px 0 0 #2563eb;
        }

        .area-filter-chip.active {
            background: #111827;
            border-color: #111827;
            color: #ffffff;
        }

        .area-filter-chip.secondary {
            background: #f8fafc;
            color: #2563eb;
            border-color: #cfe0ff;
        }

        .area-filter-add-btn {
            width: 38px;
            height: 38px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 600;
            line-height: 1;
            flex-shrink: 0;
        }

        .area-filter-chip-count {
            color: inherit;
            opacity: 0.75;
            margin-left: 4px;
        }

        .timeline-scroll-wrapper {
            flex: 1;
            min-height: 0;
            overflow-x: auto;
            overflow-y: auto;
            width: 100%;
        }

        /* TIME HEADER */
        .time-header {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            background: #f9fafb;
            position: sticky;
            top: 0;
            z-index: 10;
            align-items: center;
            overflow: hidden;
            min-width: max-content;
        }

        .time-header-spacer {
            width: 80px;
            min-width: 80px;
            border-right: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #374151;
            background: #f9fafb;
        }

        .time-slots {
            display: flex;
            flex: 1;
            overflow: hidden;
            min-height: 40px;
            min-width: max-content;
        }

        :root {
            --timeline-row-height: 40px;
        }

        .time-slot {
            min-width: 80px;
            padding: 8px 5px;
            text-align: center;
            border-right: 1px solid #e5e7eb;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            background: #f9fafb;
            height: 40px;
            line-height: 24px;
        }

        /* TIMELINE CONTENT */
        .timeline-content {
            display: flex;
            flex: 1;
            overflow: visible;
            position: relative;
            min-width: max-content;
        }

        .table-labels {
            width: 80px;
            min-width: 80px;
            border-right: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            position: sticky;
            left: 0;
            z-index: 5;
            overflow: hidden;
        }

        .table-label {
            height: var(--timeline-row-height);
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            font-weight: 700;
            color: #1f2937;
            background: #fff;
        }

        .table-label-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            width: 100%;
            min-width: 0;
            padding: 0 6px;
        }

        .table-label-area-pill {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .area-label-row,
        .area-divider-row {
            height: 28px;
            min-height: 28px;
            border-bottom: 1px solid #e5e7eb;
        }

        .area-label-row {
            align-items: center;
            justify-content: flex-start;
            background: #f8fafc;
            color: #0f172a;
            padding: 0 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .area-divider-row {
            display: flex;
            position: relative;
            min-width: max-content;
            background: #f8fafc;
        }

        .area-divider-row::after {
            content: attr(data-area-name);
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
            pointer-events: none;
        }

        .area-divider-cell {
            min-width: 80px;
            border-right: 1px solid #eef2f7;
            background: transparent;
        }

        .timeline-empty-state {
            padding: 28px 24px;
            color: #64748b;
            font-size: 13px;
            font-weight: 500;
        }

        .timeline-grid {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: max-content;
            background: #fff;
            overflow: visible;
            position: relative;
        }

        .table-row {
            display: flex;
            position: relative;
            height: var(--timeline-row-height);
            border-bottom: 1px solid #e5e7eb;
            min-width: max-content;
        }

        .table-label.add-table-row,
        .table-row.add-table-row {
            margin-top: auto;
        }

        .time-cell {
            min-width: 80px;
            border-right: 1px solid #f3f4f6;
            position: relative;
            background: #fff;
            height: 100%;
        }

        .time-cell:hover {
            background: #f9fafb;
        }

        .booking-block {
            position: absolute;
            top: 4px;
            min-height: 32px;
            padding: 5px 8px;
            border-radius: 4px;
            cursor: grab;
            transition: box-shadow 0.2s;
            font-size: 11px;
            font-weight: 600;
            color: white;
            overflow: hidden;
            text-overflow: ellipsis;
            user-select: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            z-index: 20;
            display: flex;
            align-items: stretch;
            justify-content: space-between;
            flex-direction: column;
        }

        .booking-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1px;
            height: 100%;
            min-height: 0;
            overflow: hidden;
            position: relative;
            z-index: 22;
            pointer-events: auto;
        }

        .booking-top,
        .booking-top-left,
        .booking-top-right,
        .booking-name-row {
            min-width: 0;
        }

        .booking-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            min-width: 0;
        }

        .booking-top-left,
        .booking-top-right {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
        }

        .booking-top-right {
            flex-shrink: 0;
        }

        .booking-name-row {
            display: flex;
            align-items: center;
            min-width: 0;
        }

        .booking-title,
        .booking-time-text,
        .booking-name-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
        }

        .booking-time-text {
            font-size: 10px;
            font-weight: 700;
            opacity: 0.95;
            text-align: left;
        }

        .booking-meta-inline {
            font-size: 10px;
            font-weight: 700;
            opacity: 0.9;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .booking-name-text {
            font-size: 11px;
            font-weight: 700;
            line-height: 1.1;
        }

        .booking-note-btn {
            border: none;
            background: rgba(255, 255, 255, 0.18);
            color: inherit;
            width: 12px;
            height: 12px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
        }

        .booking-note-btn:hover {
            background: rgba(255, 255, 255, 0.28);
        }

        .booking-note-btn i {
            font-size: 7px;
        }

        .resize-handle {
            width: 8px;
            height: 100%;
            cursor: ew-resize;
            position: absolute;
            top: 0;
            z-index: 21;
            background: transparent;
        }

        .left-handle {
            left: 0;
        }

        .right-handle {
            right: 0;
        }

        .top-handle,
        .bottom-handle {
            left: 0;
            right: 0;
            width: 100%;
            height: 8px;
            cursor: ns-resize;
        }

        .top-handle {
            top: 0;
        }

        .bottom-handle {
            top: auto;
            bottom: 0;
        }

        .booking-block:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 21;
        }

        .booking-block.dragging {
            opacity: 0.7;
            cursor: grabbing;
        }

        .booking-block.success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        .booking-block.pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .booking-block.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .booking-block.over-capacity {
            background: linear-gradient(135deg, #f59e0b, #ea580c);
        }

        .booking-block.rescheduled {
            outline: 2px dashed rgba(255,255,255,0.7);
            outline-offset: -2px;
        }

        .current-time-line {
            position: absolute;
            width: 2px;
            background: #ef4444;
            top: 0;
            bottom: 0;
            z-index: 19;
            opacity: 0.7;
            pointer-events: none;
        }

        /* SCROLLBAR STYLING */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4><i class="fa fa-utensils"></i><span class="brand-label">DineMate</span></h4>
        <a href="../dashboard.php">
            <i class="fa fa-chart-line"></i><span class="nav-label">Analytics</span>
        </a>
        <a href="new-dashboard.php" class="active">
            <i class="fa fa-calendar-days"></i><span class="nav-label">Timeline</span>
        </a>
        <a href="../menu-management.php">
            <i class="fa fa-utensils"></i><span class="nav-label">Menu</span>
        </a>
        <a href="../manage-users.php">
            <i class="fa fa-users"></i><span class="nav-label">Users</span>
        </a>
        <a href="../../auth/logout.php">
            <i class="fa fa-sign-out-alt"></i><span class="nav-label">Logout</span>
        </a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <?php include __DIR__ . '/../admin-topbar.php'; ?>

        <!-- CONTENT -->
        <div class="content">
            <!-- LEFT PANEL -->
            <div class="left-panel">
                <!-- BOOKINGS LIST -->
                <div class="tables-section">
                    <div class="timeline-panel-tools">
                        <div class="timeline-date-card">
                            <div class="timeline-date-year" id="timelineDateYear"><?php echo htmlspecialchars($selectedYearDisplay); ?></div>
                            <div class="timeline-date-primary" id="timelineDatePrimary"><?php echo htmlspecialchars($selectedShortDateDisplay); ?></div>
                            <button type="button" class="today-button<?php echo $isCurrentDate ? ' is-hidden' : ''; ?>" id="todayButton" onclick="todayDate()">View Today</button>
                            <div class="timeline-date-nav-row">
                                <button type="button" class="timeline-date-nav" onclick="previousDay()" aria-label="Previous day"><i class="fa fa-chevron-left"></i></button>
                                <button type="button" class="timeline-date-picker-trigger" onclick="openTimelineDatePicker()" aria-label="Select date"><i class="fa fa-calendar-alt"></i></button>
                                <button type="button" class="timeline-date-nav" onclick="nextDay()" aria-label="Next day"><i class="fa fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="calendar">
                            <input type="date" id="dateInput" value="<?php echo $selectedDate; ?>" onchange="changeDate()">
                        </div>
                    </div>
                    <div class="booking-list-tabs">
                        <button type="button" class="booking-list-tab pending-span active" id="pendingTabBtn" onclick="switchBookingListTab('pending')">Pending</button>
                        <div class="booking-list-tabs-row">
                            <button type="button" class="booking-list-tab" id="standbyTabBtn" onclick="switchBookingListTab('standby')">Standby</button>
                            <button type="button" class="booking-list-tab" id="bookingsTabBtn" onclick="switchBookingListTab('bookings')">Bookings</button>
                        </div>
                    </div>
                    <div class="booking-list" id="bookingList"></div>
                    <div class="left-panel-footer">
                        <div class="stats-card">
                            <div class="stats-list">
                                <div class="stats-item">
                                    <span class="stats-item-label">Total</span>
                                    <span class="stats-item-value"><span class="stats-item-bookings"><?php echo $bookingStats['total_bookings']; ?> Bookings</span><span class="stats-item-people">P<?php echo $bookingStats['total_people']; ?></span></span>
                                </div>
                                <div class="stats-item">
                                    <span class="stats-item-label">Lunch</span>
                                    <span class="stats-item-value"><span class="stats-item-bookings"><?php echo $bookingStats['lunch_bookings']; ?> Bookings</span><span class="stats-item-people">P<?php echo $bookingStats['lunch_people']; ?></span></span>
                                </div>
                                <div class="stats-item">
                                    <span class="stats-item-label">Dinner</span>
                                    <span class="stats-item-value"><span class="stats-item-bookings"><?php echo $bookingStats['dinner_bookings']; ?> Bookings</span><span class="stats-item-people">P<?php echo $bookingStats['dinner_people']; ?></span></span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="add-booking-button" id="openBookingModalBtn">
                            <i class="fa fa-plus"></i>
                            Add a Booking
                        </button>
                    </div>
                </div>
            </div>

            <!-- TIMELINE -->
            <div class="timeline-area">
                <div class="timeline-toolbar">
                    <div class="area-filter-bar" id="areaFilterBar"></div>
                </div>
                <div class="timeline-scroll-wrapper" id="timelineScrollWrapper">
                    <!-- TIME HEADER -->
                    <div class="time-header">
                        <div class="time-header-spacer">Tables</div>
                        <div class="time-slots" id="timeHeader"></div>
                    </div>

                    <!-- TIMELINE CONTENT -->
                    <div class="timeline-content">
                        <div class="table-labels" id="tableLabels"></div>
                        <div class="timeline-grid" id="timelineGrid"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-backdrop-custom" id="bookingModal">
    <div class="booking-modal-card booking-details-card booking-create-card">
        <div class="booking-modal-header">
            <h5><i class="fa fa-calendar-plus"></i> Add a Booking</h5>
            <button type="button" class="booking-modal-close" id="closeBookingModalBtn" aria-label="Close">&times;</button>
        </div>
        <div class="modal-error" id="bookingModalError"></div>
        <form id="adminBookingForm">
            <div class="booking-detail-grid">
                <div class="modal-form-group">
                    <label for="adminBookingName">Name</label>
                    <input type="text" id="adminBookingName" required>
                </div>
                <div class="modal-form-group">
                    <label for="adminBookingGuests">Number of People</label>
                    <input type="number" id="adminBookingGuests" min="1" required>
                </div>
                <div class="modal-form-group">
                    <label for="adminBookingDate">Date</label>
                    <input type="date" id="adminBookingDate" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
                </div>
                <div class="modal-form-group">
                    <label for="adminBookingTime">Time</label>
                    <input type="time" id="adminBookingTime" min="10:00" max="21:00" step="1800" value="12:00" required>
                    <div class="modal-helper-text">Creates a 60-minute pending booking.</div>
                </div>
                <div class="modal-form-group full-width">
                    <button type="button" class="booking-inline-trigger" id="toggleAdminBookingPhoneBtn">
                        <i class="fa fa-phone"></i>
                        <span id="toggleAdminBookingPhoneLabel">Add Phone Number</span>
                    </button>
                </div>
                <div class="modal-form-group full-width is-hidden" id="adminBookingPhoneGroup">
                    <label for="adminBookingPhone">Phone</label>
                    <input type="text" id="adminBookingPhone" placeholder="Optional phone number">
                </div>
                <div class="modal-form-group full-width">
                    <label for="adminBookingNotes">Notes</label>
                    <textarea id="adminBookingNotes" placeholder="Optional notes"></textarea>
                </div>
            </div>
            <div class="booking-modal-actions">
                <button type="button" class="booking-modal-cancel" id="cancelBookingModalBtn">Cancel</button>
                <button type="submit" class="booking-modal-submit" id="submitAdminBookingBtn">Create Booking</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop-custom" id="bookingDetailsModal">
    <div class="booking-modal-card booking-details-card">
        <div class="booking-modal-header">
            <div>
                <h5><i class="fa fa-clipboard-list"></i> Booking Details</h5>
                <div class="booking-meta-chip" id="bookingDetailsMeta"></div>
            </div>
            <button type="button" class="booking-modal-close" id="closeBookingDetailsModalBtn" aria-label="Close">&times;</button>
        </div>
        <div class="modal-error" id="bookingDetailsError"></div>
        <form id="bookingDetailsForm">
            <input type="hidden" id="bookingDetailsId">
            <input type="hidden" id="bookingDetailsAction" value="save">
            <div class="booking-detail-topbar">
                <button type="button" class="booking-modal-danger-small" id="cancelBookingActionBtn">Cancel Booking</button>
            </div>
            <div class="booking-detail-grid">
                <div class="modal-form-group">
                    <label for="bookingDetailsName">Name</label>
                    <input type="text" id="bookingDetailsName" required>
                </div>
                <div class="modal-form-group">
                    <label for="bookingDetailsGuests">Party Size</label>
                    <input type="number" id="bookingDetailsGuests" min="1" required>
                </div>
                <div class="modal-form-group full-width">
                    <label>Requested Time</label>
                    <div class="booking-time-pair">
                        <input type="time" id="bookingRequestedStart" min="10:00" max="21:30" step="1800" required>
                        <span class="booking-time-pair-separator">to</span>
                        <input type="time" id="bookingRequestedEnd" min="10:30" max="22:00" step="1800" required>
                    </div>
                </div>
                <div class="modal-form-group full-width">
                    <label>Assigned Time</label>
                    <div class="booking-time-pair">
                        <input type="time" id="bookingAssignedStart" min="10:00" max="21:30" step="1800" required>
                        <span class="booking-time-pair-separator">to</span>
                        <input type="time" id="bookingAssignedEnd" min="10:30" max="22:00" step="1800" required>
                    </div>
                </div>
                <div class="modal-form-group full-width">
                    <label for="bookingDetailsTable">Table</label>
                    <select id="bookingDetailsTable">
                        <option value="">Unassigned</option>
                    </select>
                </div>
                <div class="modal-form-group full-width">
                    <label for="bookingDetailsNotes">Notes</label>
                    <textarea id="bookingDetailsNotes" placeholder="Optional notes"></textarea>
                </div>
            </div>
            <div class="booking-modal-actions">
                <button type="button" class="booking-modal-cancel" id="cancelBookingDetailsBtn">Close</button>
                <button type="submit" class="booking-modal-submit" id="saveBookingDetailsBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop-custom" id="tableDetailsModal">
    <div class="booking-modal-card">
        <div class="booking-modal-header">
            <div>
                <h5 id="tableDetailsTitle"><i class="fa fa-chair"></i> Table Details</h5>
                <div class="booking-meta-chip" id="tableDetailsMeta"></div>
            </div>
            <button type="button" class="booking-modal-close" id="closeTableDetailsModalBtn" aria-label="Close">&times;</button>
        </div>
        <div class="modal-error" id="tableDetailsError"></div>
        <form id="tableDetailsForm">
            <input type="hidden" id="tableDetailsMode" value="edit">
            <input type="hidden" id="tableDetailsId">
            <div class="modal-form-group">
                <label for="tableDetailsNumber">Table</label>
                <input type="text" id="tableDetailsNumber" required>
                <div class="modal-helper-text" id="tableDetailsNumberHelp">Use a short table number like 1, 12, or A3.</div>
            </div>
            <div class="modal-form-group">
                <label for="tableDetailsCapacity">Capacity</label>
                <input type="number" id="tableDetailsCapacity" min="1" required>
            </div>
            <div class="modal-form-group">
                <label for="tableDetailsArea">Area</label>
                <select id="tableDetailsArea" required></select>
                <div class="modal-helper-text">Move this table into a venue section like Patio, Bar, or Dining Room.</div>
            </div>
            <div class="modal-form-group">
                <label for="tableDetailsSortOrder">Order In Area</label>
                <input type="number" id="tableDetailsSortOrder" min="1" step="1" required>
            </div>
            <div class="booking-modal-actions">
                <button type="button" class="booking-modal-danger-small" id="deleteTableBtn">Delete Table</button>
                <button type="button" class="booking-modal-cancel" id="cancelTableDetailsBtn">Close</button>
                <button type="submit" class="booking-modal-submit" id="saveTableDetailsBtn">Save Table</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop-custom" id="areaDetailsModal">
    <div class="booking-modal-card">
        <div class="booking-modal-header">
            <div>
                <h5 id="areaDetailsTitle"><i class="fa fa-layer-group"></i> Area Details</h5>
                <div class="booking-meta-chip" id="areaDetailsMeta"></div>
            </div>
            <button type="button" class="booking-modal-close" id="closeAreaDetailsModalBtn" aria-label="Close">&times;</button>
        </div>
        <div class="modal-error" id="areaDetailsError"></div>
        <form id="areaDetailsForm">
            <input type="hidden" id="areaDetailsMode" value="edit">
            <input type="hidden" id="areaDetailsId">
            <div class="modal-form-group">
                <label for="areaDetailsName">Area Name</label>
                <input type="text" id="areaDetailsName" required>
            </div>
            <div class="modal-form-group">
                <label for="areaDetailsStartNumber">Table Number Starts At</label>
                <input type="number" id="areaDetailsStartNumber" min="1" step="1" placeholder="Optional">
                <div class="modal-helper-text">Use this if tables in this area should begin from a specific number, like 20.</div>
            </div>
            <div class="modal-form-group">
                <label for="areaDetailsEndNumber">Table Number Ends At</label>
                <input type="number" id="areaDetailsEndNumber" min="1" step="1" placeholder="Optional">
                <div class="modal-helper-text">Optional upper bound. The + button will stop once the area reaches this number.</div>
            </div>
            <div class="booking-modal-actions">
                <button type="button" class="booking-modal-danger-small" id="deleteAreaBtn">Delete Area</button>
                <button type="button" class="booking-modal-cancel" id="cancelAreaDetailsBtn">Close</button>
                <button type="submit" class="booking-modal-submit" id="saveAreaDetailsBtn">Save Area</button>
            </div>
        </form>
    </div>
</div>

<script>
    const BOOKING_DATA = <?php echo $bookingsJson; ?>;
    const TABLES = <?php echo json_encode($tables); ?>;
    const AREAS = <?php echo json_encode($areas); ?>;
    const SELECTED_DATE = '<?php echo $selectedDate; ?>';
    const START_HOUR = 10; // 10 AM
    const END_HOUR = 23;   // 11 PM
    const INTERVAL_MINS = 30;
    const CELL_WIDTH = 80;
    const ROW_HEIGHT = 40;
    const AREA_HEADER_HEIGHT = 28;
    let activeBookingListTab = 'pending';
    let activeAreaFilter = 'all';
    let suppressAreaChipClick = false;
    let draggedAreaChipId = null;

    // Initialize timeline on page load
    document.addEventListener('DOMContentLoaded', function() {
        if (redirectToLocalCurrentDateIfNeeded()) {
            return;
        }

        syncCurrentDateState();
        bindBookingModal();
        bindBookingDetailsModal();
        bindTableDetailsModal();
        bindAreaDetailsModal();
        renderTimeline();
        setCurrentTimeLine();

    });

    function sortAreas(left, right) {
        const leftOrder = Number(left.display_order || 0);
        const rightOrder = Number(right.display_order || 0);
        if(leftOrder !== rightOrder) {
            return leftOrder - rightOrder;
        }
        return `${left.name || ''}`.localeCompare(`${right.name || ''}`, undefined, { numeric: true, sensitivity: 'base' });
    }

    function sortTables(left, right) {
        const areaOrderDiff = Number(left.area_display_order || 0) - Number(right.area_display_order || 0);
        if(areaOrderDiff !== 0) {
            return areaOrderDiff;
        }

        const areaNameDiff = `${left.area_name || ''}`.localeCompare(`${right.area_name || ''}`, undefined, { sensitivity: 'base' });
        if(areaNameDiff !== 0) {
            return areaNameDiff;
        }

        const sortOrderDiff = Number(left.sort_order || 0) - Number(right.sort_order || 0);
        if(sortOrderDiff !== 0) {
            return sortOrderDiff;
        }

        return `${left.table_number || ''}`.localeCompare(`${right.table_number || ''}`, undefined, { numeric: true, sensitivity: 'base' });
    }

    function getAreaById(areaId) {
        return AREAS.find(area => String(area.area_id) === String(areaId)) || null;
    }

    function reconcileDeletedTables(deletedTableIds) {
        const normalizedDeletedTableIds = Array.isArray(deletedTableIds) ? deletedTableIds.map(Number) : [];
        if(!normalizedDeletedTableIds.length) {
            return;
        }

        const deletedTableIdSet = new Set(normalizedDeletedTableIds);

        for(let index = TABLES.length - 1; index >= 0; index -= 1) {
            if(deletedTableIdSet.has(Number(TABLES[index].table_id))) {
                TABLES.splice(index, 1);
            }
        }

        BOOKING_DATA.forEach(booking => {
            const nextAssignedTableIds = getAssignedTableIds(booking).filter(tableId => !deletedTableIdSet.has(Number(tableId)));
            const nextAssignedTables = nextAssignedTableIds
                .map(tableId => getTableById(tableId))
                .filter(Boolean);

            booking.assigned_table_ids = nextAssignedTableIds;
            booking.assigned_table_numbers = nextAssignedTables.map(table => String(table.table_number));
            booking.table_id = nextAssignedTableIds.length ? Number(nextAssignedTableIds[0]) : null;
            booking.table_number = nextAssignedTables.length ? String(nextAssignedTables[0].table_number) : null;
        });
    }

    function getTableById(tableId) {
        return TABLES.find(table => String(table.table_id) === String(tableId)) || null;
    }

    function getSortedAreas() {
        return [...AREAS].sort(sortAreas);
    }

    function getVisibleTables() {
        const sortedTables = [...TABLES].sort(sortTables);
        if(activeAreaFilter === 'all') {
            return sortedTables;
        }
        return sortedTables.filter(table => String(table.area_id) === String(activeAreaFilter));
    }

    function timesOverlap(startA, endA, startB, endB) {
        return startA < endB && endA > startB;
    }

    function getAvailableTablesForBooking(bookingId, bookingDate, assignedStartTime, assignedEndTime) {
        if(!bookingDate || !assignedStartTime || !assignedEndTime) {
            return [...TABLES].sort(sortTables);
        }

        const blockedTableIds = new Set();

        BOOKING_DATA.forEach(booking => {
            if(String(booking.booking_id) === String(bookingId)) {
                return;
            }

            const bookingStatus = String(booking.status || '').toLowerCase();
            if(!['pending', 'confirmed'].includes(bookingStatus)) {
                return;
            }

            if(String(booking.booking_date) !== String(bookingDate)) {
                return;
            }

            if(!timesOverlap(
                assignedStartTime,
                assignedEndTime,
                String(booking.start_time || ''),
                String(booking.end_time || '')
            )) {
                return;
            }

            getAssignedTableIds(booking).forEach(tableId => {
                blockedTableIds.add(String(tableId));
            });
        });

        return [...TABLES]
            .filter(table => !blockedTableIds.has(String(table.table_id)))
            .sort(sortTables);
    }

    function refreshBookingTableOptions(selectedTableId = null, bookingId = null, bookingDate = null, assignedStartTime = null, assignedEndTime = null) {
        const tableSelect = document.getElementById('bookingDetailsTable');
        if(!tableSelect) {
            return;
        }

        const options = ['<option value="">Unassigned</option>'];
        const availableTables = getAvailableTablesForBooking(bookingId, bookingDate, assignedStartTime, assignedEndTime);
        const selectedTable = selectedTableId ? getTableById(selectedTableId) : null;

        if(selectedTable && !availableTables.some(table => String(table.table_id) === String(selectedTable.table_id))) {
            availableTables.unshift(selectedTable);
        }

        availableTables.forEach(table => {
            const isSelected = selectedTableId !== null && selectedTableId !== undefined && String(selectedTableId) === String(table.table_id);
            options.push(`<option value="${table.table_id}"${isSelected ? ' selected' : ''}>${getAreaNameForTable(table)} • T${table.table_number} • ${table.capacity} seats</option>`);
        });

        tableSelect.innerHTML = options.join('');
    }

    function refreshBookingTableOptionsForCurrentForm() {
        const bookingId = document.getElementById('bookingDetailsId').value;
        const booking = BOOKING_DATA.find(item => String(item.booking_id) === String(bookingId));
        const tableSelect = document.getElementById('bookingDetailsTable');
        if(!booking || !tableSelect) {
            return;
        }

        refreshBookingTableOptions(
            tableSelect.value,
            booking.booking_id,
            booking.booking_date,
            `${document.getElementById('bookingAssignedStart').value}:00`,
            `${document.getElementById('bookingAssignedEnd').value}:00`
        );
    }

    function getAreaNameForTable(table) {
        return table && table.area_name ? table.area_name : 'Main Floor';
    }

    function getBookingAreaNames(booking) {
        const areaNames = [];
        getAssignedTableIds(booking).forEach(tableId => {
            const table = getTableById(tableId);
            const areaName = getAreaNameForTable(table);
            if(areaName && !areaNames.includes(areaName)) {
                areaNames.push(areaName);
            }
        });
        return areaNames;
    }

    function formatAreaNameList(areaNames) {
        if(!Array.isArray(areaNames) || !areaNames.length) {
            return '';
        }
        if(areaNames.length === 1) {
            return areaNames[0];
        }
        if(areaNames.length === 2) {
            return `${areaNames[0]} & ${areaNames[1]}`;
        }
        return `${areaNames.slice(0, -1).join(', ')} & ${areaNames[areaNames.length - 1]}`;
    }

    function getBookingAssignmentSummary(booking) {
        const assignedTableIds = getAssignedTableIds(booking);
        if(!assignedTableIds.length) {
            return '';
        }

        const assignedTables = assignedTableIds
            .map(tableId => getTableById(tableId))
            .filter(Boolean);

        if(!assignedTables.length) {
            return '';
        }

        const assignedTableIdSet = new Set(assignedTables.map(table => String(table.table_id)));
        const groupedByArea = assignedTables.reduce((groups, table) => {
            const areaKey = String(table.area_id || '0');
            if(!groups[areaKey]) {
                groups[areaKey] = [];
            }
            groups[areaKey].push(table);
            return groups;
        }, {});

        const wholeAreaLabels = [];
        const partialTableLabels = [];

        Object.keys(groupedByArea)
            .sort((leftAreaId, rightAreaId) => {
                const leftArea = getAreaById(leftAreaId);
                const rightArea = getAreaById(rightAreaId);
                return sortAreas(leftArea || { display_order: 0, name: '' }, rightArea || { display_order: 0, name: '' });
            })
            .forEach(areaId => {
                const areaTables = TABLES
                    .filter(table => String(table.area_id) === String(areaId))
                    .sort(sortTables);
                const assignedAreaTables = groupedByArea[areaId].sort(sortTables);
                const isWholeArea = areaTables.length > 0 && areaTables.every(table => assignedTableIdSet.has(String(table.table_id)));

                if(isWholeArea) {
                    const areaName = getAreaById(areaId)?.name || getAreaNameForTable(assignedAreaTables[0]);
                    wholeAreaLabels.push(areaName);
                    return;
                }

                assignedAreaTables.forEach(table => {
                    partialTableLabels.push(`T${table.table_number}`);
                });
            });

        if(wholeAreaLabels.length && !partialTableLabels.length) {
            return formatAreaNameList(wholeAreaLabels);
        }

        if(wholeAreaLabels.length && partialTableLabels.length) {
            return `${formatAreaNameList(wholeAreaLabels)} • ${partialTableLabels.join(', ')}`;
        }

        const areaNames = getBookingAreaNames(booking);
        return `${areaNames.length ? `${areaNames.join(', ')} • ` : ''}${partialTableLabels.join(', ')}`;
    }

    function getBookingDetailsMetaSummary(booking) {
        const rawStatusLabel = String(booking.status || 'pending');
        const statusLabel = rawStatusLabel.charAt(0).toUpperCase() + rawStatusLabel.slice(1);
        const assignmentSummary = getBookingAssignmentSummary(booking);

        if(!getAssignedTableIds(booking).length || !assignmentSummary) {
            return `Unassigned • ${statusLabel}`;
        }

        return `${assignmentSummary} • ${statusLabel}`;
    }

    function getBookedPeopleCountForArea(areaId = 'all') {
        return BOOKING_DATA.reduce((total, booking) => {
            const guestCount = Number(booking.number_of_guests || 0);
            if(guestCount <= 0) {
                return total;
            }

            const assignedAreaIds = [...new Set(
                getAssignedTableIds(booking)
                    .map(tableId => getTableById(tableId))
                    .filter(Boolean)
                    .map(table => String(table.area_id))
            )];

            if(!assignedAreaIds.length) {
                return total;
            }

            if(areaId === 'all') {
                return total + guestCount;
            }

            return assignedAreaIds.includes(String(areaId)) ? total + guestCount : total;
        }, 0);
    }

    function buildVisibleTableLayout() {
        const visibleTables = getVisibleTables();
        const rows = [];
        const tableTopMap = {};
        let activeAreaKey = null;
        let currentTop = 0;

        visibleTables.forEach(table => {
            const nextAreaKey = String(table.area_id || '');
            if(nextAreaKey !== activeAreaKey) {
                rows.push({
                    type: 'area',
                    area_id: table.area_id,
                    area_name: getAreaNameForTable(table),
                    top: currentTop,
                });
                currentTop += AREA_HEADER_HEIGHT;
                activeAreaKey = nextAreaKey;
            }

            tableTopMap[String(table.table_id)] = currentTop;
            rows.push({
                type: 'table',
                table,
                top: currentTop,
            });
            currentTop += ROW_HEIGHT;
        });

        rows.push({ type: 'add', top: currentTop });
        currentTop += ROW_HEIGHT;

        return {
            visibleTables,
            rows,
            tableTopMap,
            totalHeight: currentTop,
        };
    }

    function renderAreaFilters() {
        const areaFilterBar = document.getElementById('areaFilterBar');
        if(!areaFilterBar) return;

        const areaButtons = getSortedAreas().map(area => {
            const bookedPeopleCount = getBookedPeopleCountForArea(area.area_id);
            const isActive = String(activeAreaFilter) === String(area.area_id);
            return `<button type="button" class="area-filter-chip${isActive ? ' active' : ''}" data-area-id="${area.area_id}" draggable="true" onclick="handleAreaChipClick(${area.area_id})">${area.name}<span class="area-filter-chip-count">${bookedPeopleCount}</span></button>`;
        }).join('');

        const totalBookedPeople = getBookedPeopleCountForArea('all');
        areaFilterBar.innerHTML = `
            <button type="button" class="area-filter-chip${activeAreaFilter === 'all' ? ' active' : ''}" onclick="setAreaFilter('all')">Full View<span class="area-filter-chip-count">${totalBookedPeople}</span></button>
            ${areaButtons}
            <button type="button" class="area-filter-chip secondary area-filter-add-btn" onclick="openAreaDetails()" aria-label="Add area" title="Add area">+</button>
        `;
        bindAreaFilterDragHandlers();
    }

    function handleAreaChipClick(areaId) {
        if(suppressAreaChipClick) {
            suppressAreaChipClick = false;
            return;
        }

        if(String(activeAreaFilter) === String(areaId)) {
            openAreaDetails(areaId);
            return;
        }

        setAreaFilter(areaId);
    }

    function clearAreaChipDropIndicators() {
        document.querySelectorAll('.area-filter-chip.drop-before, .area-filter-chip.drop-after').forEach(chip => {
            chip.classList.remove('drop-before', 'drop-after');
        });
    }

    function persistAreaOrder(orderedAreaIds) {
        return fetch('update-area-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ area_ids: orderedAreaIds })
        })
        .then(async response => {
            const data = await response.json();
            if(!response.ok || !data.success) {
                throw new Error(data.error || 'Failed to save area order');
            }
            return data;
        })
        .then(data => {
            if(Array.isArray(data.areas)) {
                AREAS.length = 0;
                data.areas.forEach(area => {
                    AREAS.push(area);
                });

                const areaOrderMap = AREAS.reduce((map, area) => {
                    map[String(area.area_id)] = Number(area.display_order || 0);
                    return map;
                }, {});

                TABLES.forEach(table => {
                    table.area_display_order = areaOrderMap[String(table.area_id)] ?? Number(table.area_display_order || 0);
                });
            }

            renderTimeline();
            return true;
        });
    }

    function bindAreaFilterDragHandlers() {
        const draggableAreaChips = document.querySelectorAll('.area-filter-chip[data-area-id]');
        if(!draggableAreaChips.length) {
            return;
        }

        draggableAreaChips.forEach(chip => {
            chip.addEventListener('dragstart', function(event) {
                draggedAreaChipId = chip.dataset.areaId;
                suppressAreaChipClick = false;
                chip.classList.add('dragging');
                if(event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', draggedAreaChipId);
                }
            });

            chip.addEventListener('dragover', function(event) {
                if(!draggedAreaChipId || draggedAreaChipId === chip.dataset.areaId) {
                    return;
                }

                event.preventDefault();
                clearAreaChipDropIndicators();
                const bounds = chip.getBoundingClientRect();
                const insertBefore = event.clientX < bounds.left + (bounds.width / 2);
                chip.classList.add(insertBefore ? 'drop-before' : 'drop-after');
            });

            chip.addEventListener('dragleave', function() {
                chip.classList.remove('drop-before', 'drop-after');
            });

            chip.addEventListener('drop', function(event) {
                event.preventDefault();
                const sourceAreaId = draggedAreaChipId || (event.dataTransfer ? event.dataTransfer.getData('text/plain') : '');
                const targetAreaId = chip.dataset.areaId;
                clearAreaChipDropIndicators();

                if(!sourceAreaId || !targetAreaId || sourceAreaId === targetAreaId) {
                    return;
                }

                const orderedAreas = getSortedAreas();
                const sourceIndex = orderedAreas.findIndex(area => String(area.area_id) === String(sourceAreaId));
                const targetIndex = orderedAreas.findIndex(area => String(area.area_id) === String(targetAreaId));
                if(sourceIndex === -1 || targetIndex === -1) {
                    return;
                }

                const bounds = chip.getBoundingClientRect();
                const insertBefore = event.clientX < bounds.left + (bounds.width / 2);
                const [movedArea] = orderedAreas.splice(sourceIndex, 1);
                const adjustedTargetIndex = sourceIndex < targetIndex ? targetIndex - 1 : targetIndex;
                const nextIndex = insertBefore ? adjustedTargetIndex : adjustedTargetIndex + 1;
                orderedAreas.splice(nextIndex, 0, movedArea);

                suppressAreaChipClick = true;
                persistAreaOrder(orderedAreas.map(area => Number(area.area_id))).catch(error => {
                    window.alert(error.message);
                    renderTimeline();
                });
            });

            chip.addEventListener('dragend', function() {
                chip.classList.remove('dragging', 'drop-before', 'drop-after');
                clearAreaChipDropIndicators();
                draggedAreaChipId = null;
            });
        });
    }

    function setAreaFilter(areaId) {
        activeAreaFilter = areaId === 'all' ? 'all' : String(areaId);
        renderTimeline();
    }

    function refreshAreaSelectOptions(selectedAreaId) {
        const select = document.getElementById('tableDetailsArea');
        if(!select) return;

        const options = getSortedAreas().map(area => {
            const isSelected = String(selectedAreaId) === String(area.area_id);
            return `<option value="${area.area_id}"${isSelected ? ' selected' : ''}>${area.name}</option>`;
        }).join('');

        select.innerHTML = options;
    }

    function getNextSortOrderForArea(areaId) {
        const areaTables = TABLES.filter(table => String(table.area_id) === String(areaId));
        if(!areaTables.length) {
            return 10;
        }

        const maxSortOrder = areaTables.reduce((highest, table) => {
            return Math.max(highest, Number(table.sort_order || 0));
        }, 0);

        return Math.max(10, maxSortOrder + 10);
    }

    function getNextTableNumberForArea(areaId) {
        const area = getAreaById(areaId);
        const areaTables = TABLES.filter(table => String(table.area_id) === String(areaId));
        if(!areaTables.length) {
            return String(area && area.table_number_start !== null ? area.table_number_start : 1);
        }

        const maxTableNumber = areaTables.reduce((highest, table) => {
            const numericValue = parseInt(String(table.table_number || ''), 10);
            if(Number.isNaN(numericValue)) {
                return highest;
            }
            return Math.max(highest, numericValue);
        }, 0);

        const nextNumber = Math.max(1, maxTableNumber + 1);
        if(area && area.table_number_end !== null && nextNumber > Number(area.table_number_end)) {
            return String(area.table_number_end);
        }

        return String(nextNumber);
    }

    function openCreateTableModal(preferredAreaId) {
        const fallbackArea = getSortedAreas()[0] || null;
        const areaId = preferredAreaId || (fallbackArea ? Number(fallbackArea.area_id) : 0);
        const modal = document.getElementById('tableDetailsModal');
        if(!modal) return;

        document.getElementById('tableDetailsMode').value = 'create';
        document.getElementById('tableDetailsId').value = '';
        document.getElementById('tableDetailsTitle').innerHTML = '<i class="fa fa-plus"></i> Create Table';
        document.getElementById('tableDetailsNumber').value = getNextTableNumberForArea(areaId);
        document.getElementById('tableDetailsNumber').readOnly = false;
        document.getElementById('tableDetailsCapacity').value = '8';
        refreshAreaSelectOptions(areaId);
        document.getElementById('tableDetailsArea').value = String(areaId);
        document.getElementById('tableDetailsSortOrder').value = getNextSortOrderForArea(areaId);
        document.getElementById('tableDetailsMeta').textContent = areaId ? `New table in ${getAreaById(areaId)?.name || 'selected area'}` : 'New table';
        document.getElementById('tableDetailsError').style.display = 'none';
        document.getElementById('saveTableDetailsBtn').textContent = 'Create Table';
        document.getElementById('deleteTableBtn').style.display = 'none';
        modal.classList.add('open');
        document.body.classList.add('modal-open');

        requestAnimationFrame(() => {
            document.getElementById('tableDetailsNumber').focus();
        });
    }

    function showCreateTableModalError(message, preferredAreaId) {
        openCreateTableModal(preferredAreaId);
        const errorBox = document.getElementById('tableDetailsError');
        if(errorBox && message) {
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        }
    }

    function openAreaDetails(areaId = null) {
        const modal = document.getElementById('areaDetailsModal');
        if(!modal) return;

        const isEditMode = areaId !== null && areaId !== undefined;
        const area = isEditMode ? getAreaById(areaId) : null;

        document.getElementById('areaDetailsMode').value = isEditMode ? 'edit' : 'create';
        document.getElementById('areaDetailsId').value = isEditMode ? area.area_id : '';
        document.getElementById('areaDetailsTitle').innerHTML = isEditMode ? '<i class="fa fa-layer-group"></i> Edit Area' : '<i class="fa fa-plus"></i> Create Area';
        document.getElementById('areaDetailsName').value = isEditMode ? area.name : '';
        document.getElementById('areaDetailsStartNumber').value = isEditMode && area.table_number_start !== null ? area.table_number_start : '';
        document.getElementById('areaDetailsEndNumber').value = isEditMode && area.table_number_end !== null ? area.table_number_end : '';
        document.getElementById('areaDetailsMeta').textContent = isEditMode ? `${TABLES.filter(table => String(table.area_id) === String(area.area_id)).length} tables in this area` : 'Create a new area and set its numbering range';
        document.getElementById('areaDetailsError').style.display = 'none';
        document.getElementById('saveAreaDetailsBtn').textContent = isEditMode ? 'Save Area' : 'Create Area';
        document.getElementById('deleteAreaBtn').style.display = isEditMode ? 'inline-flex' : 'none';
        modal.classList.add('open');
        document.body.classList.add('modal-open');

        requestAnimationFrame(() => {
            document.getElementById('areaDetailsName').focus();
        });
    }

    function deleteAreaById(areaId, options = {}) {
        const normalizedAreaId = String(areaId || '').trim();
        const errorBox = options.errorBox || null;
        const onSuccess = typeof options.onSuccess === 'function' ? options.onSuccess : null;
        const areaName = getAreaById(normalizedAreaId)?.name || 'this area';

        if(!normalizedAreaId) {
            return Promise.resolve(false);
        }

        if(!window.confirm(`Delete area ${areaName}? Its tables will be removed and any affected bookings will become unassigned.`)) {
            return Promise.resolve(false);
        }

        if(errorBox) {
            errorBox.style.display = 'none';
        }

        return fetch('delete-area.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ area_id: normalizedAreaId })
        })
        .then(async response => {
            const data = await response.json();
            if(!response.ok || !data.success) {
                throw new Error(data.error || 'Failed to delete area');
            }
            return data;
        })
        .then(data => {
            reconcileDeletedTables(data.deleted_table_ids);

            const areaIdx = AREAS.findIndex(area => String(area.area_id) === String(data.area_id));
            if(areaIdx !== -1) {
                AREAS.splice(areaIdx, 1);
            }

            if(String(activeAreaFilter) === String(data.area_id)) {
                activeAreaFilter = 'all';
            }

            renderTimeline();
            if(onSuccess) {
                onSuccess(data);
            }
            return true;
        })
        .catch(error => {
            if(errorBox) {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
            } else {
                window.alert(error.message);
            }
            return false;
        });
    }

    function bindAreaDetailsModal() {
        const modal = document.getElementById('areaDetailsModal');
        const closeBtn = document.getElementById('closeAreaDetailsModalBtn');
        const cancelBtn = document.getElementById('cancelAreaDetailsBtn');
        const deleteBtn = document.getElementById('deleteAreaBtn');
        const form = document.getElementById('areaDetailsForm');
        const errorBox = document.getElementById('areaDetailsError');
        const saveBtn = document.getElementById('saveAreaDetailsBtn');

        if(!modal || !form) return;

        function closeModal() {
            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
            errorBox.style.display = 'none';
        }

        if(closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        if(cancelBtn) {
            cancelBtn.addEventListener('click', closeModal);
        }
        if(deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                const areaId = document.getElementById('areaDetailsId').value;
                if(!areaId) {
                    return;
                }

                deleteBtn.disabled = true;
                deleteAreaById(areaId, {
                    errorBox,
                    onSuccess: function() {
                        closeModal();
                    }
                }).finally(() => {
                    deleteBtn.disabled = false;
                });
            });
        }

        modal.addEventListener('click', function(e) {
            if(e.target === modal) {
                closeModal();
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const mode = document.getElementById('areaDetailsMode').value;
            const payload = {
                area_id: document.getElementById('areaDetailsId').value,
                name: document.getElementById('areaDetailsName').value.trim(),
                table_number_start: document.getElementById('areaDetailsStartNumber').value,
                table_number_end: document.getElementById('areaDetailsEndNumber').value,
            };

            errorBox.style.display = 'none';
            saveBtn.disabled = true;
            saveBtn.textContent = mode === 'create' ? 'Creating...' : 'Saving...';

            fetch(mode === 'create' ? 'create-area.php' : 'update-area.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async response => {
                const data = await response.json();
                if(!response.ok || !data.success) {
                    throw new Error(data.error || `Failed to ${mode === 'create' ? 'create' : 'update'} area`);
                }
                return data;
            })
            .then(data => {
                reconcileDeletedTables(data.deleted_table_ids);

                const areaIdx = AREAS.findIndex(area => String(area.area_id) === String(data.area.area_id));
                if(areaIdx !== -1) {
                    AREAS[areaIdx] = {
                        ...AREAS[areaIdx],
                        ...data.area,
                    };
                } else {
                    AREAS.push(data.area);
                }

                for(let index = TABLES.length - 1; index >= 0; index -= 1) {
                    if(String(TABLES[index].area_id) === String(data.area.area_id)) {
                        TABLES.splice(index, 1);
                    }
                }

                if(Array.isArray(data.area_tables)) {
                    data.area_tables.forEach(table => {
                        TABLES.push(table);
                    });
                }

                activeAreaFilter = String(data.area.area_id);
                refreshAreaSelectOptions(data.area.area_id);
                renderTimeline();
                closeModal();
            })
            .catch(error => {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = mode === 'create' ? 'Create Area' : 'Save Area';
            });
        });
    }

    function bindBookingModal() {
        const modal = document.getElementById('bookingModal');
        const openBtn = document.getElementById('openBookingModalBtn');
        const closeBtn = document.getElementById('closeBookingModalBtn');
        const cancelBtn = document.getElementById('cancelBookingModalBtn');
        const togglePhoneBtn = document.getElementById('toggleAdminBookingPhoneBtn');
        const togglePhoneLabel = document.getElementById('toggleAdminBookingPhoneLabel');
        const phoneGroup = document.getElementById('adminBookingPhoneGroup');
        const phoneInput = document.getElementById('adminBookingPhone');
        const form = document.getElementById('adminBookingForm');
        const errorBox = document.getElementById('bookingModalError');
        const submitBtn = document.getElementById('submitAdminBookingBtn');

        if(!modal || !openBtn || !form) return;

        function setPhoneVisibility(visible) {
            if(!phoneGroup || !togglePhoneBtn || !togglePhoneLabel || !phoneInput) {
                return;
            }

            phoneGroup.classList.toggle('is-hidden', !visible);
            togglePhoneBtn.classList.toggle('is-active', visible);
            togglePhoneLabel.textContent = visible ? 'Remove Phone Number' : 'Add Phone Number';

            if(!visible) {
                phoneInput.value = '';
            }
        }

        function openModal() {
            modal.classList.add('open');
            document.body.classList.add('modal-open');
            errorBox.style.display = 'none';
            form.reset();
            setPhoneVisibility(false);
            document.getElementById('adminBookingDate').value = SELECTED_DATE;
            document.getElementById('adminBookingTime').value = '12:00';
            requestAnimationFrame(() => {
                document.getElementById('adminBookingName').focus();
            });
        }

        function closeModal() {
            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
            errorBox.style.display = 'none';
        }

        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        if(togglePhoneBtn) {
            togglePhoneBtn.addEventListener('click', function() {
                const shouldShow = phoneGroup ? phoneGroup.classList.contains('is-hidden') : false;
                setPhoneVisibility(shouldShow);
                if(shouldShow && phoneInput) {
                    requestAnimationFrame(() => phoneInput.focus());
                }
            });
        }

        modal.addEventListener('click', function(e) {
            if(e.target === modal) {
                closeModal();
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const payload = {
                name: document.getElementById('adminBookingName').value.trim(),
                customer_email: '',
                customer_phone: phoneInput ? phoneInput.value.trim() : '',
                booking_date: document.getElementById('adminBookingDate').value,
                start_time: document.getElementById('adminBookingTime').value,
                number_of_guests: document.getElementById('adminBookingGuests').value,
                special_request: document.getElementById('adminBookingNotes').value.trim(),
            };

            errorBox.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            fetch('create-booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async response => {
                const data = await response.json();
                if(!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to create booking');
                }
                return data;
            })
            .then(data => {
                if(data.booking.booking_date === SELECTED_DATE) {
                    BOOKING_DATA.push(data.booking);
                    renderTimeline();
                }
                closeModal();
            })
            .catch(error => {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Booking';
            });
        });
    }

    function bindAddTableButton() {
        const addTableButtons = document.querySelectorAll('[data-add-table-trigger="true"]');
        if(!addTableButtons.length) return;

        addTableButtons.forEach(addTableBtn => {
            addTableBtn.addEventListener('click', function() {
                const fallbackArea = getSortedAreas()[0] || null;
                const buttonAreaId = Number(addTableBtn.dataset.areaId || 0);
                const targetAreaId = buttonAreaId > 0
                    ? buttonAreaId
                    : activeAreaFilter === 'all'
                        ? (fallbackArea ? Number(fallbackArea.area_id) : 0)
                        : Number(activeAreaFilter);

                if(targetAreaId < 1) {
                    showCreateTableModalError('Create an area before adding tables.', null);
                    return;
                }

                const targetAreaTableCount = TABLES.filter(table => String(table.area_id) === String(targetAreaId)).length;
                const noTablesAtAll = TABLES.length === 0;

                if(noTablesAtAll || targetAreaTableCount === 0) {
                    openCreateTableModal(targetAreaId);
                    return;
                }

                fetch('create-table.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        auto: true,
                        area_id: targetAreaId
                    })
                })
                .then(async response => {
                    const data = await response.json();
                    if(!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not add table');
                    }
                    return data;
                })
                .then(data => {
                    TABLES.push({
                        table_id: data.table_id,
                        table_number: data.table_number,
                        capacity: data.capacity,
                        area_id: data.area_id,
                        area_name: data.area_name,
                        area_display_order: data.area_display_order,
                        sort_order: data.sort_order,
                    });
                    activeAreaFilter = String(data.area_id);
                    renderTimeline();
                })
                .catch(err => {
                    console.error(err);
                    showCreateTableModalError(err.message || 'Could not add table.', targetAreaId);
                });
            });
        });
    }

    function bindBookingDetailsModal() {
        const modal = document.getElementById('bookingDetailsModal');
        const closeBtn = document.getElementById('closeBookingDetailsModalBtn');
        const cancelBtn = document.getElementById('cancelBookingDetailsBtn');
        const cancelBookingActionBtn = document.getElementById('cancelBookingActionBtn');
        const form = document.getElementById('bookingDetailsForm');
        const errorBox = document.getElementById('bookingDetailsError');
        const saveBtn = document.getElementById('saveBookingDetailsBtn');
        const assignedStartInput = document.getElementById('bookingAssignedStart');
        const assignedEndInput = document.getElementById('bookingAssignedEnd');

        if(!modal || !form) return;

        function closeModal() {
            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
            errorBox.style.display = 'none';
        }

        if(closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        if(cancelBtn) {
            cancelBtn.addEventListener('click', closeModal);
        }
        cancelBookingActionBtn.addEventListener('click', function() {
            const bookingId = document.getElementById('bookingDetailsId').value;
            if(!bookingId) {
                return;
            }

            const booking = BOOKING_DATA.find(item => item.booking_id == bookingId);
            const bookingName = booking ? booking.customer_name : 'this booking';
            if(!window.confirm(`Cancel ${bookingName}?`)) {
                return;
            }

            errorBox.style.display = 'none';
            cancelBookingActionBtn.disabled = true;
            cancelBookingActionBtn.textContent = 'Cancelling...';

            fetch('cancel-booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    booking_id: bookingId,
                })
            })
            .then(async response => {
                const data = await response.json();
                if(!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to cancel booking');
                }
                return data;
            })
            .then(data => {
                const bookingIdx = BOOKING_DATA.findIndex(item => item.booking_id == data.booking_id);
                if(bookingIdx !== -1) {
                    BOOKING_DATA.splice(bookingIdx, 1);
                }
                renderTimeline();
                closeModal();
            })
            .catch(error => {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
            })
            .finally(() => {
                cancelBookingActionBtn.disabled = false;
                cancelBookingActionBtn.textContent = 'Cancel Booking';
            });
        });
        modal.addEventListener('click', function(e) {
            if(e.target === modal) {
                closeModal();
            }
        });

        if(assignedStartInput) {
            assignedStartInput.addEventListener('change', refreshBookingTableOptionsForCurrentForm);
        }
        if(assignedEndInput) {
            assignedEndInput.addEventListener('change', refreshBookingTableOptionsForCurrentForm);
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const action = document.getElementById('bookingDetailsAction').value || 'save';
            const selectedTableId = document.getElementById('bookingDetailsTable').value;

            const payload = {
                booking_id: document.getElementById('bookingDetailsId').value,
                customer_name: document.getElementById('bookingDetailsName').value.trim(),
                requested_start_time: document.getElementById('bookingRequestedStart').value,
                requested_end_time: document.getElementById('bookingRequestedEnd').value,
                start_time: document.getElementById('bookingAssignedStart').value,
                end_time: document.getElementById('bookingAssignedEnd').value,
                number_of_guests: document.getElementById('bookingDetailsGuests').value,
                special_request: document.getElementById('bookingDetailsNotes').value.trim(),
                table_id: selectedTableId,
                confirm_booking: action === 'confirm',
            };

            errorBox.style.display = 'none';
            saveBtn.disabled = true;
            saveBtn.textContent = action === 'confirm' ? 'Confirming...' : 'Saving...';

            fetch('update-booking-details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async response => {
                const data = await response.json();
                if(!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to update booking');
                }
                return data;
            })
            .then(data => {
                const bookingIdx = BOOKING_DATA.findIndex(booking => booking.booking_id == data.booking.booking_id);
                if(bookingIdx !== -1) {
                    BOOKING_DATA[bookingIdx] = {
                        ...BOOKING_DATA[bookingIdx],
                        ...data.booking,
                    };
                }
                renderTimeline();
                if(action === 'confirm') {
                    if(typeof window.refreshAdminPendingBookings === 'function') {
                        window.refreshAdminPendingBookings();
                    }
                    document.dispatchEvent(new CustomEvent('admin-pending-bookings-changed'));
                }
                const updatedBooking = bookingIdx !== -1 ? BOOKING_DATA[bookingIdx] : data.booking;
                closeModal();

                if(action === 'confirm') {
                    if(updatedBooking && getAssignedTableIds(updatedBooking).length === 0) {
                        switchBookingListTab('standby');
                    } else {
                        switchBookingListTab('bookings');
                    }
                }
            })
            .catch(error => {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = document.getElementById('bookingDetailsAction').value === 'confirm' ? 'Confirm Booking' : 'Save Changes';
            });
        });
    }

    function bindTableDetailsModal() {
        const modal = document.getElementById('tableDetailsModal');
        const closeBtn = document.getElementById('closeTableDetailsModalBtn');
        const cancelBtn = document.getElementById('cancelTableDetailsBtn');
        const deleteTableBtn = document.getElementById('deleteTableBtn');
        const form = document.getElementById('tableDetailsForm');
        const errorBox = document.getElementById('tableDetailsError');
        const saveBtn = document.getElementById('saveTableDetailsBtn');
        const areaSelect = document.getElementById('tableDetailsArea');
        const sortOrderInput = document.getElementById('tableDetailsSortOrder');
        const modeInput = document.getElementById('tableDetailsMode');

        if(!modal || !form) return;

        function closeModal() {
            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
            errorBox.style.display = 'none';
        }

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);

        if(areaSelect && sortOrderInput) {
            areaSelect.addEventListener('change', function() {
                if(modeInput.value === 'create') {
                    sortOrderInput.value = getNextSortOrderForArea(areaSelect.value);
                    document.getElementById('tableDetailsNumber').value = getNextTableNumberForArea(areaSelect.value);
                }
            });
        }

        if(deleteTableBtn) {
            deleteTableBtn.addEventListener('click', function() {
                const tableId = document.getElementById('tableDetailsId').value;
                const tableNumber = document.getElementById('tableDetailsNumber').value;
                if(!tableId) {
                    return;
                }

                if(!window.confirm(`Delete table ${tableNumber}?`)) {
                    return;
                }

                errorBox.style.display = 'none';
                deleteTableBtn.disabled = true;

                fetch('delete-table.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ table_id: tableId })
                })
                .then(async response => {
                    const data = await response.json();
                    if(!response.ok || !data.success) {
                        throw new Error(data.error || 'Failed to delete table');
                    }
                    return data;
                })
                .then(data => {
                    const tableIdx = TABLES.findIndex(table => String(table.table_id) === String(data.table_id));
                    if(tableIdx !== -1) {
                        TABLES.splice(tableIdx, 1);
                    }

                    renderTimeline();
                    closeModal();
                })
                .catch(error => {
                    errorBox.textContent = error.message;
                    errorBox.style.display = 'block';
                })
                .finally(() => {
                    deleteTableBtn.disabled = false;
                });
            });
        }

        modal.addEventListener('click', function(e) {
            if(e.target === modal) {
                closeModal();
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const mode = document.getElementById('tableDetailsMode').value;
            const payload = {
                table_id: document.getElementById('tableDetailsId').value,
                table_number: document.getElementById('tableDetailsNumber').value.trim(),
                capacity: document.getElementById('tableDetailsCapacity').value,
                area_id: document.getElementById('tableDetailsArea').value,
                sort_order: document.getElementById('tableDetailsSortOrder').value,
            };

            errorBox.style.display = 'none';
            saveBtn.disabled = true;
            saveBtn.textContent = mode === 'create' ? 'Creating...' : 'Saving...';

            fetch(mode === 'create' ? 'create-table.php' : 'update-table.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(mode === 'create' ? {
                    auto: false,
                    table_number: payload.table_number,
                    capacity: payload.capacity,
                    area_id: payload.area_id,
                    sort_order: payload.sort_order,
                } : payload)
            })
            .then(async response => {
                const data = await response.json();
                if(!response.ok || !data.success) {
                    throw new Error(data.error || `Failed to ${mode === 'create' ? 'create' : 'update'} table`);
                }
                return data;
            })
            .then(data => {
                const tableData = mode === 'create'
                    ? {
                        table_id: data.table_id,
                        table_number: data.table_number,
                        capacity: data.capacity,
                        area_id: data.area_id,
                        area_name: data.area_name,
                        area_display_order: data.area_display_order,
                        sort_order: data.sort_order,
                    }
                    : data.table;

                const tableIdx = TABLES.findIndex(table => String(table.table_id) === String(tableData.table_id));
                if(tableIdx !== -1) {
                    TABLES[tableIdx] = {
                        ...TABLES[tableIdx],
                        ...tableData,
                    };
                } else {
                    TABLES.push(tableData);
                }

                activeAreaFilter = String(tableData.area_id);
                renderTimeline();
                closeModal();
            })
            .catch(error => {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = mode === 'create' ? 'Create Table' : 'Save Table';
            });
        });
    }

    function openTableDetails(tableId) {
        const table = TABLES.find(item => String(item.table_id) === String(tableId));
        if(!table) return;

        document.getElementById('tableDetailsMode').value = 'edit';
        document.getElementById('tableDetailsId').value = table.table_id;
        document.getElementById('tableDetailsTitle').innerHTML = '<i class="fa fa-chair"></i> Table Details';
        document.getElementById('tableDetailsNumber').value = `T${table.table_number}`;
        document.getElementById('tableDetailsNumber').readOnly = true;
        document.getElementById('tableDetailsCapacity').value = table.capacity;
        refreshAreaSelectOptions(table.area_id);
        document.getElementById('tableDetailsArea').value = table.area_id;
        document.getElementById('tableDetailsSortOrder').value = table.sort_order || 10;
        document.getElementById('tableDetailsMeta').textContent = `${getAreaNameForTable(table)} • ${table.capacity} seats`;
        document.getElementById('tableDetailsError').style.display = 'none';
        document.getElementById('saveTableDetailsBtn').textContent = 'Save Table';
        document.getElementById('deleteTableBtn').style.display = 'inline-flex';
        document.getElementById('tableDetailsModal').classList.add('open');
        document.body.classList.add('modal-open');
    }

    function openBookingDetails(bookingId) {
        const booking = BOOKING_DATA.find(item => item.booking_id == bookingId);
        if(!booking) return;

        const assignedTableNumbers = getAssignedTableNumbers(booking);
        const tableLabel = assignedTableNumbers.length
            ? `Tables ${assignedTableNumbers.join(', ')}`
            : 'Unassigned';
        const isPending = String(booking.status || '').toLowerCase() === 'pending';

        const modal = document.getElementById('bookingDetailsModal');
        document.getElementById('bookingDetailsId').value = booking.booking_id;
        document.getElementById('bookingDetailsAction').value = isPending ? 'confirm' : 'save';
        document.getElementById('bookingDetailsName').value = booking.customer_name || '';
        document.getElementById('bookingRequestedStart').value = getRequestedStartTime(booking).substring(0, 5);
        document.getElementById('bookingRequestedEnd').value = getRequestedEndTime(booking).substring(0, 5);
        document.getElementById('bookingAssignedStart').value = booking.start_time.substring(0, 5);
        document.getElementById('bookingAssignedEnd').value = booking.end_time.substring(0, 5);
        document.getElementById('bookingDetailsGuests').value = booking.number_of_guests;
        refreshBookingTableOptions(
            booking.table_id,
            booking.booking_id,
            booking.booking_date,
            booking.start_time,
            booking.end_time
        );
        document.getElementById('bookingDetailsNotes').value = booking.special_request || '';
        document.getElementById('bookingDetailsMeta').textContent = getBookingDetailsMetaSummary(booking);
        document.getElementById('saveBookingDetailsBtn').textContent = isPending ? 'Confirm Booking' : 'Save Changes';

        const errorBox = document.getElementById('bookingDetailsError');
        errorBox.style.display = 'none';
        modal.classList.add('open');
        document.body.classList.add('modal-open');
    }

    function switchBookingListTab(tabName) {
        activeBookingListTab = ['pending', 'standby', 'bookings'].includes(tabName) ? tabName : 'pending';

        const pendingTabBtn = document.getElementById('pendingTabBtn');
        const standbyTabBtn = document.getElementById('standbyTabBtn');
        const bookingsTabBtn = document.getElementById('bookingsTabBtn');

        if(pendingTabBtn) {
            pendingTabBtn.classList.toggle('active', activeBookingListTab === 'pending');
        }
        if(standbyTabBtn) {
            standbyTabBtn.classList.toggle('active', activeBookingListTab === 'standby');
        }
        if(bookingsTabBtn) {
            bookingsTabBtn.classList.toggle('active', activeBookingListTab === 'bookings');
        }

        populateBookingList();
    }

    function populateBookingList() {
        const bookingList = document.getElementById('bookingList');
        if(!bookingList) return;

        const pendingBookings = BOOKING_DATA
            .filter(booking => String(booking.status || '').toLowerCase() === 'pending')
            .filter(booking => {
                if(activeAreaFilter === 'all') {
                    return true;
                }

                const assignedTableIds = getAssignedTableIds(booking);
                if(!assignedTableIds.length) {
                    return true;
                }

                return assignedTableIds.some(tableId => {
                    const table = getTableById(tableId);
                    return table && String(table.area_id) === String(activeAreaFilter);
                });
            })
            .sort((left, right) => `${left.start_time}`.localeCompare(`${right.start_time}`));

        const pendingTabBtn = document.getElementById('pendingTabBtn');
        if(pendingTabBtn) {
            pendingTabBtn.style.display = pendingBookings.length ? 'inline-flex' : 'none';
            pendingTabBtn.classList.toggle('has-pending', pendingBookings.length > 0);
        }

        if(activeBookingListTab === 'pending' && !pendingBookings.length) {
            activeBookingListTab = 'standby';
            switchBookingListTab('standby');
            return;
        }

        const standbyBookings = BOOKING_DATA
            .filter(booking => String(booking.status || '').toLowerCase() !== 'pending')
            .filter(booking => getAssignedTableIds(booking).length === 0)
            .sort((left, right) => `${left.start_time}`.localeCompare(`${right.start_time}`));

        const assignedBookings = BOOKING_DATA
            .filter(booking => getAssignedTableIds(booking).length > 0)
            .filter(booking => {
                if(activeAreaFilter === 'all') {
                    return true;
                }
                return getAssignedTableIds(booking).some(tableId => {
                    const table = getTableById(tableId);
                    return table && String(table.area_id) === String(activeAreaFilter);
                });
            })
            .sort((left, right) => `${left.start_time}`.localeCompare(`${right.start_time}`));

        const isPendingTab = activeBookingListTab === 'pending';
        const isStandbyTab = activeBookingListTab === 'standby';
        const visibleBookings = isPendingTab
            ? pendingBookings
            : isStandbyTab
                ? standbyBookings
                : assignedBookings;
        const emptyMessage = isPendingTab
            ? 'No pending bookings for this date.'
            : isStandbyTab
                ? 'No unassigned bookings for this date.'
                : 'No assigned bookings for this date.';

        if(visibleBookings.length === 0) {
            bookingList.innerHTML = `<p class="booking-list-empty">${emptyMessage}</p>`;
            return;
        }

        bookingList.innerHTML = visibleBookings.map(booking => {
            const startTime = formatDisplayTime(booking.start_time);
            const noteIcon = booking.special_request
                ? `<span class="booking-item-note-icon" title="${booking.special_request.replace(/"/g, '&quot;')}"><i class="fa-solid fa-note-sticky"></i></span>`
                : '';
            const assignmentSummary = getBookingAssignmentSummary(booking);
            const pendingActionButton = isPendingTab
                ? `<button type="button" class="booking-item-action-btn" onclick="confirmPendingBooking(${booking.booking_id}, event)">Confirm</button>`
                : '';
            const rightSideText = isPendingTab || isStandbyTab
                ? `${pendingActionButton}<span class="booking-item-meta">P${booking.number_of_guests}</span>`
                : `<span class="booking-item-table">${assignmentSummary}</span>`;
            const bottomRowRight = isPendingTab || isStandbyTab
                ? ''
                : `<span class="booking-item-bottom-right">P${booking.number_of_guests}</span>`;
            const canDragFromList = isPendingTab || isStandbyTab;
            const draggableAttributes = canDragFromList ? 'draggable="true"' : 'draggable="false"';
            const draggableClass = canDragFromList ? ' draggable-booking' : '';
            return `
                <div class="booking-item${draggableClass}" ${draggableAttributes} data-booking-id="${booking.booking_id}" onclick="handleBookingClick(event, ${booking.booking_id})">
                    <div class="booking-item-top">
                        <span class="booking-item-top-left">
                            <span class="booking-item-time">${startTime}</span>
                            ${noteIcon}
                        </span>
                        <span class="booking-item-top-right">
                            ${rightSideText}
                        </span>
                    </div>
                    <div class="booking-item-bottom">
                        <span class="booking-item-name">${booking.customer_name}</span>
                        ${bottomRowRight}
                    </div>
                </div>
            `;
        }).join('');
    }

    function confirmPendingBooking(bookingId, event) {
        if(event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const button = event ? event.currentTarget : null;
        if(button) {
            button.disabled = true;
        }

        fetch('confirm-pending-booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ booking_id: bookingId })
        })
        .then(async response => {
            const data = await response.json();
            if(!response.ok || !data.success) {
                throw new Error(data.error || 'Could not confirm booking');
            }
            return data;
        })
        .then(data => {
            const bookingIdx = BOOKING_DATA.findIndex(booking => String(booking.booking_id) === String(data.booking_id));
            if(bookingIdx !== -1) {
                BOOKING_DATA[bookingIdx].status = data.status || 'confirmed';
                BOOKING_DATA[bookingIdx].table_id = data.table_id !== null ? Number(data.table_id) : null;
                BOOKING_DATA[bookingIdx].table_number = data.table_number || null;
                BOOKING_DATA[bookingIdx].assigned_table_ids = Array.isArray(data.assigned_table_ids) ? data.assigned_table_ids.map(Number) : [];
                BOOKING_DATA[bookingIdx].assigned_table_numbers = Array.isArray(data.assigned_table_numbers) ? data.assigned_table_numbers : [];
            }

            renderTimeline();
            if(typeof window.refreshAdminPendingBookings === 'function') {
                window.refreshAdminPendingBookings();
            }
            document.dispatchEvent(new CustomEvent('admin-pending-bookings-changed'));

            const updatedBooking = bookingIdx !== -1 ? BOOKING_DATA[bookingIdx] : null;
            if(updatedBooking && getAssignedTableIds(updatedBooking).length === 0) {
                switchBookingListTab('standby');
                return;
            }

            populateBookingList();
        })
        .catch(error => {
            window.alert(error.message);
            if(button) {
                button.disabled = false;
            }
        });
    }

    function getRequestedStartTime(booking) {
        return booking.requested_start_time || booking.start_time;
    }

    function getRequestedEndTime(booking) {
        return booking.requested_end_time || booking.end_time;
    }

    function getAssignedTableIds(booking) {
        if(Array.isArray(booking.assigned_table_ids)) {
            return booking.assigned_table_ids.map(Number).filter(Boolean);
        }
        if(booking.table_id !== null && booking.table_id !== undefined && booking.table_id !== '') {
            return [Number(booking.table_id)];
        }
        return [];
    }

    function getAssignedTableNumbers(booking) {
        if(Array.isArray(booking.assigned_table_numbers) && booking.assigned_table_numbers.length) {
            return booking.assigned_table_numbers;
        }
        if(booking.table_number !== null && booking.table_number !== undefined && booking.table_number !== '') {
            return [String(booking.table_number)];
        }
        return [];
    }

    function getTableIndexMap(sourceTables = getVisibleTables()) {
        return sourceTables.reduce((map, table, index) => {
            map[String(table.table_id)] = index;
            return map;
        }, {});
    }

    function getBookingSpanTableIds(startTableId, spanCount) {
        const visibleTables = getVisibleTables();
        const startIndex = visibleTables.findIndex(table => String(table.table_id) === String(startTableId));
        if(startIndex === -1) return [];
        return visibleTables.slice(startIndex, startIndex + spanCount).map(table => Number(table.table_id));
    }

    function getAssignedCapacity(booking) {
        const assignedTableIds = getAssignedTableIds(booking);
        if(!assignedTableIds.length) {
            return 0;
        }

        return assignedTableIds.reduce((total, tableId) => {
            const table = TABLES.find(item => String(item.table_id) === String(tableId));
            return total + (table ? Number(table.capacity || 0) : 0);
        }, 0);
    }

    function formatDisplayTime(timeValue) {
        if(!timeValue) {
            return '';
        }

        const timePart = String(timeValue).substring(0, 5);
        const [hourString, minuteString] = timePart.split(':');
        const hour = Number(hourString);
        const minute = Number(minuteString);

        if(Number.isNaN(hour) || Number.isNaN(minute)) {
            return timePart;
        }

        const suffix = hour >= 12 ? 'PM' : 'AM';
        const normalizedHour = hour % 12 || 12;
        return `${normalizedHour}:${String(minute).padStart(2, '0')}${suffix}`;
    }

    function formatTimeRange(startTime, endTime) {
        return `${formatDisplayTime(startTime)} - ${formatDisplayTime(endTime)}`;
    }

    function confirmScheduledTimeChange(booking, newStartTime, newEndTime) {
        const currentStart = booking.start_time.substring(0, 5);
        const nextStart = newStartTime.substring(0, 5);

        if(currentStart === nextStart) {
            return true;
        }

        const currentRange = formatTimeRange(booking.start_time, booking.end_time);
        const newRange = formatTimeRange(newStartTime, newEndTime);
        const requestedRange = formatTimeRange(getRequestedStartTime(booking), getRequestedEndTime(booking));
        return window.confirm(
            `Change scheduled time for ${booking.customer_name}?\n\nRequested time: ${requestedRange}\nCurrent scheduled time: ${currentRange}\nNew scheduled time: ${newRange}`
        );
    }

    // Generate time slots (30-minute intervals)
    function getTimeSlots() {
        const slots = [];
        for(let h = START_HOUR; h <= END_HOUR; h++) {
            for(let m = 0; m < 60; m += INTERVAL_MINS) {
                const time = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
                slots.push(time);
            }
        }
        return slots;
    }

    // Render the entire timeline
    function renderTimeline() {
        const timeSlots = getTimeSlots();
        const layout = buildVisibleTableLayout();

        // Render time headings (x-axis)
        const timeHeader = document.getElementById('timeHeader');
        timeHeader.innerHTML = timeSlots.map(time => 
            `<div class="time-slot">${formatDisplayTime(time)}</div>`
        ).join('');

        renderAreaFilters();

        // Render table labels (y-axis)
        const tableLabels = document.getElementById('tableLabels');
        tableLabels.innerHTML = layout.rows.map(row => {
            if(row.type === 'area') {
                return `<div class="table-label area-label-row">${row.area_name}</div>`;
            }
            if(row.type === 'table') {
                return `<div class="table-label clickable" onclick="openTableDetails(${row.table.table_id})" title="Edit table details">
                    <span class="table-label-inner">
                        <span>T${row.table.table_number}</span>
                    </span>
                </div>`;
            }
            const areaIdAttr = row.area_id ? ` data-area-id="${row.area_id}"` : '';
            const addTitle = row.area_name ? `Add table to ${row.area_name}` : 'Add table';
            return `<div class="table-label add-table-row"><button type="button" class="add-table-inline-btn" data-add-table-trigger="true"${areaIdAttr} title="${addTitle}">+</button></div>`;
        }).join('');

        // Populate booking list on left
        populateBookingList();

        // Render timeline rows for each table
        const timelineGrid = document.getElementById('timelineGrid');
        let gridHTML = '';
        let bookingHTML = '';

        if(layout.visibleTables.length === 0) {
            const emptyLabel = activeAreaFilter === 'all'
                ? 'No tables found yet. Add your first table to start using the timeline.'
                : 'No tables found for this area yet. Add one to start organizing this section.';
            const emptyAreaId = activeAreaFilter === 'all' ? (getSortedAreas()[0]?.area_id || '') : activeAreaFilter;
            timelineGrid.innerHTML = `<div class="timeline-empty-state">${emptyLabel}<div style="margin-top:12px;"><button type="button" class="add-table-inline-btn" data-add-table-trigger="true" data-area-id="${emptyAreaId}" title="Add table">+</button></div></div>`;
            bindAddTableButton();
            return;
        }

        layout.rows.forEach(row => {
            if(row.type === 'area') {
                gridHTML += `<div class="area-divider-row" data-area-name="${row.area_name.replace(/"/g, '&quot;')}">${timeSlots.map(() => `<div class="area-divider-cell"></div>`).join('')}</div>`;
                return;
            }

            if(row.type === 'table') {
                let rowHTML = `<div class="table-row" data-table-id="${row.table.table_id}">`;
                timeSlots.forEach(time => {
                    rowHTML += `<div class="time-cell" 
                        data-table-id="${row.table.table_id}" 
                        data-time="${time}"
                        ondrop="handleDrop(event)"
                        ondragover="handleDragOver(event)"></div>`;
                });
                rowHTML += '</div>';
                gridHTML += rowHTML;
                return;
            }

            gridHTML += `<div class="table-row add-table-row" aria-hidden="true">${timeSlots.map(() => `<div class="time-cell"></div>`).join('')}</div>`;
        });

        bookingHTML = BOOKING_DATA
            .filter(booking => getAssignedTableIds(booking).length > 0)
            .map(booking => renderBooking(booking, timeSlots, layout.tableTopMap))
            .join('');

        gridHTML += bookingHTML;

        timelineGrid.innerHTML = gridHTML;
        timelineGrid.style.minHeight = `${layout.totalHeight}px`;

        bindBookingDragHandlers();
        bindAddTableButton();
        setCurrentTimeLine();
    }

    function getBookingRenderSegments(booking, sourceTables = getVisibleTables()) {
        const assignedTableIdSet = new Set(getAssignedTableIds(booking).map(tableId => String(tableId)));
        const visibleAssignedTables = sourceTables.filter(table => assignedTableIdSet.has(String(table.table_id)));

        if(!visibleAssignedTables.length) {
            return [];
        }

        return visibleAssignedTables.reduce((segments, table, index) => {
            const previousTable = index > 0 ? visibleAssignedTables[index - 1] : null;
            const previousSourceIndex = previousTable ? sourceTables.findIndex(item => String(item.table_id) === String(previousTable.table_id)) : -1;
            const currentSourceIndex = sourceTables.findIndex(item => String(item.table_id) === String(table.table_id));
            const isSameArea = previousTable && String(previousTable.area_id) === String(table.area_id);
            const isAdjacent = previousSourceIndex !== -1 && currentSourceIndex === previousSourceIndex + 1;

            if(!segments.length || !isSameArea || !isAdjacent) {
                segments.push({
                    area_id: table.area_id,
                    table_ids: [Number(table.table_id)],
                    tables: [table],
                });
                return segments;
            }

            const activeSegment = segments[segments.length - 1];
            activeSegment.table_ids.push(Number(table.table_id));
            activeSegment.tables.push(table);
            return segments;
        }, []);
    }

    // Render a single booking block
    function renderBooking(booking, timeSlots, tableTopMap) {
        const bookingStart = booking.start_time.substring(0, 5); // Convert HH:MM:SS to HH:MM
        const bookingEnd = booking.end_time.substring(0, 5);
        const requestedStart = getRequestedStartTime(booking).substring(0, 5);
        const requestedEnd = getRequestedEndTime(booking).substring(0, 5);
        const bookingStartLabel = formatDisplayTime(booking.start_time);
        const bookingEndLabel = formatDisplayTime(booking.end_time);
        const requestedStartLabel = formatDisplayTime(getRequestedStartTime(booking));
        const requestedEndLabel = formatDisplayTime(getRequestedEndTime(booking));
        const assignedTableIds = getAssignedTableIds(booking);
        const assignedTableNumbers = getAssignedTableNumbers(booking);
        const assignedAreaNames = getBookingAreaNames(booking);
        const startIdx = timeSlots.indexOf(bookingStart);
        const visibleSegments = getBookingRenderSegments(booking);

        if(startIdx === -1 || !visibleSegments.length) return '';

        const startDate = new Date(`2000-01-01 ${booking.start_time}`);
        const endDate = new Date(`2000-01-01 ${booking.end_time}`);
        const durationMins = (endDate - startDate) / (1000 * 60);
        const numSlots = Math.max(1, Math.ceil(durationMins / INTERVAL_MINS));

        const leftPosition = startIdx * CELL_WIDTH;
        const width = Math.max(numSlots * CELL_WIDTH, CELL_WIDTH);

        const statusClass = booking.status === 'confirmed' ? 'success' : (booking.status === 'pending' ? 'pending' : 'info');
        const overCapacityClass = getAssignedCapacity(booking) < Number(booking.number_of_guests || 0) ? 'over-capacity' : '';
        const rescheduledClass = requestedStart !== bookingStart ? 'rescheduled' : '';
        const guestCountText = `P${booking.number_of_guests}`;
        const visibleTimeText = bookingStartLabel;
        const hasSpecialNote = booking.special_request && booking.special_request.trim() !== '';
        const showTime = width >= 88;
        const showGuestCount = width >= 132;
        const showNoteButton = hasSpecialNote && width >= 156;
        const noteButtonHtml = showNoteButton
            ? `<button type="button" class="booking-note-btn" title="${booking.special_request.replace(/"/g, '&quot;')}" onclick="event.stopPropagation(); openBookingDetails(${booking.booking_id});" draggable="false"><i class="fa-solid fa-note-sticky"></i></button>`
            : '';
        const areaText = assignedAreaNames.length ? ` | ${assignedAreaNames.join(', ')}` : '';
        const titleText = rescheduledClass
            ? `${booking.customer_name} | ${guestCountText}${areaText} | ${bookingStartLabel} - ${bookingEndLabel} | Requested ${requestedStartLabel} - ${requestedEndLabel} | Scheduled ${bookingStartLabel} - ${bookingEndLabel}`
            : `${booking.customer_name} | ${guestCountText}${areaText} | ${bookingStartLabel} - ${bookingEndLabel}`;

        return visibleSegments.map(segment => {
            const firstSegmentTableId = segment.table_ids[0];
            const lastSegmentTableId = segment.table_ids[segment.table_ids.length - 1];
            const topPosition = tableTopMap[String(firstSegmentTableId)];
            const bottomPosition = tableTopMap[String(lastSegmentTableId)] ?? topPosition;

            if(topPosition === undefined) {
                return '';
            }

            const rowSpan = Math.max(1, segment.table_ids.length);
            const height = Math.max(ROW_HEIGHT, (bottomPosition - topPosition) + ROW_HEIGHT);

            return `<div class="booking-block ${statusClass} ${overCapacityClass} ${rescheduledClass}"
                        draggable="true"
                        data-booking-id="${booking.booking_id}"
                        data-table-id="${booking.table_id ?? ''}"
                        data-table-ids="${assignedTableIds.join(',')}"
                        data-table-number="${assignedTableNumbers.length ? 'T' + assignedTableNumbers[0] : ''}"
                        data-time="${booking.start_time}"
                        data-duration="${durationMins}"
                        data-customer="${booking.customer_name}"
                        data-row-span="${rowSpan}"
                        onclick="handleBookingClick(event, ${booking.booking_id})"
                        style="left: ${leftPosition}px; top: ${topPosition}px; width: ${width}px; height: ${height}px;"
                        title="${titleText}">
                <div class="resize-handle top-handle"></div>
                <div class="resize-handle left-handle"></div>
                <div class="booking-content">
                    <div class="booking-top">
                        <span class="booking-top-left">
                            ${showTime ? `<span class="booking-time-text">${visibleTimeText}</span>` : ''}
                            ${noteButtonHtml}
                        </span>
                        <span class="booking-top-right">
                            ${showGuestCount ? `<span class="booking-meta-inline">${guestCountText}</span>` : ''}
                        </span>
                    </div>
                    <div class="booking-name-row">
                        <span class="booking-name-text">${booking.customer_name}</span>
                    </div>
                </div>
                <div class="resize-handle right-handle"></div>
                <div class="resize-handle bottom-handle"></div>
            </div>`;
        }).join('');
    }

    // Drag handlers
    function bindBookingDragHandlers() {
        const bookings = document.querySelectorAll('.booking-block, .draggable-booking');
        bookings.forEach(booking => {
            booking.addEventListener('dragstart', handleDragStart);
            booking.addEventListener('dragend', handleDragEnd);

            const leftHandle = booking.querySelector('.left-handle');
            const rightHandle = booking.querySelector('.right-handle');
            const topHandle = booking.querySelector('.top-handle');
            const bottomHandle = booking.querySelector('.bottom-handle');

            if(leftHandle) {
                leftHandle.addEventListener('mousedown', handleResizeStart.bind(null, 'left', booking));
            }
            if(rightHandle) {
                rightHandle.addEventListener('mousedown', handleResizeStart.bind(null, 'right', booking));
            }
            if(topHandle) {
                topHandle.addEventListener('mousedown', handleResizeStart.bind(null, 'top', booking));
            }
            if(bottomHandle) {
                bottomHandle.addEventListener('mousedown', handleResizeStart.bind(null, 'bottom', booking));
            }
        });
    }

    let draggedBooking = null;
    let resizing = null;
    let resizeData = null;
    let suppressBookingClick = false;

    function handleBookingClick(event, bookingId) {
        if(suppressBookingClick) {
            suppressBookingClick = false;
            return;
        }

        if(event.target.closest('.resize-handle')) {
            return;
        }

        openBookingDetails(bookingId);
    }

    function handleDragStart(e) {
        draggedBooking = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        suppressBookingClick = true;
    }

    function handleDragEnd(e) {
        if(draggedBooking) draggedBooking.classList.remove('dragging');
        draggedBooking = null;
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    }

    function handleDrop(e) {
        e.preventDefault();
        if(!draggedBooking) return;

        const targetCell = e.target.closest('.time-cell');
        if(!targetCell) return;

        const targetTableId = targetCell.dataset.tableId;
        const targetTime = targetCell.dataset.time;
        const bookingId = draggedBooking.dataset.bookingId;
        
        if(!targetTableId || !targetTime) return;

        // Get booking duration (start_time and end_time from data attributes)
        const currentStartTime = draggedBooking.dataset.time;
        
        // Find the booking in BOOKING_DATA to get duration
        const booking = BOOKING_DATA.find(b => b.booking_id == bookingId);
        if(!booking) return;

        const currentAssignedTableIds = getAssignedTableIds(booking);
        const rowSpan = Math.max(1, currentAssignedTableIds.length || Number(draggedBooking.dataset.rowSpan) || 1);
        const nextTableIds = getBookingSpanTableIds(targetTableId, rowSpan);
        if(nextTableIds.length !== rowSpan) {
            alert('Not enough adjacent tables to keep this booking span.');
            return;
        }

        const startDate = new Date(`2000-01-01 ${booking.start_time}`);
        const endDate = new Date(`2000-01-01 ${booking.end_time}`);
        const durationMins = (endDate - startDate) / (1000 * 60);

        // Calculate new end time
        const newStartTime = targetTime + ':00';
        const newEndDate = new Date(`2000-01-01 ${newStartTime}`);
        newEndDate.setMinutes(newEndDate.getMinutes() + durationMins);
        const newEndTime = String(newEndDate.getHours()).padStart(2, '0') + ':' + String(newEndDate.getMinutes()).padStart(2, '0') + ':00';

        if(!confirmScheduledTimeChange(booking, newStartTime, newEndTime)) {
            return;
        }

        // Show loading state
        draggedBooking.style.opacity = '0.5';

        // Send AJAX request
        fetch('update-booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                booking_id: bookingId,
                table_id: targetTableId,
                table_ids: nextTableIds,
                start_time: newStartTime,
                end_time: newEndTime
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                console.log('Booking updated successfully');
                // Update the booking in memory
                const bookingIdx = BOOKING_DATA.findIndex(b => b.booking_id == bookingId);
                if(bookingIdx !== -1) {
                    BOOKING_DATA[bookingIdx].table_id = data.table_id !== null ? Number(data.table_id) : null;
                    BOOKING_DATA[bookingIdx].table_number = data.table_number || null;
                    BOOKING_DATA[bookingIdx].assigned_table_ids = Array.isArray(data.table_ids) ? data.table_ids.map(Number) : [];
                    BOOKING_DATA[bookingIdx].assigned_table_numbers = Array.isArray(data.table_numbers) ? data.table_numbers : [];
                    BOOKING_DATA[bookingIdx].status = data.status || 'confirmed';
                    BOOKING_DATA[bookingIdx].start_time = newStartTime;
                    BOOKING_DATA[bookingIdx].end_time = newEndTime;
                }
                // Re-render timeline
                renderTimeline();
            } else {
                alert('Error: ' + (data.error || 'Failed to update booking'));
                draggedBooking.style.opacity = '1';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to update booking: ' + error.message);
            draggedBooking.style.opacity = '1';
        });
    }

    // Resize handlers
    function handleResizeStart(direction, bookingEl, e) {
        e.stopPropagation();
        e.preventDefault();
        suppressBookingClick = true;

        resizing = direction;
        const bookingId = bookingEl.dataset.bookingId;
        const booking = BOOKING_DATA.find(b => b.booking_id == bookingId);
        if(!booking) return;

        const startTime = booking.start_time;
        const endTime = booking.end_time;
        const durationMins = (new Date(`2000-01-01 ${endTime}`) - new Date(`2000-01-01 ${startTime}`)) / (1000*60);
        const assignedTableIds = getAssignedTableIds(booking);

        resizeData = {
            bookingId,
            bookingEl,
            startTime,
            endTime,
            durationMins,
            assignedTableIds,
            originalLeft: parseInt(bookingEl.style.left, 10),
            originalTop: parseInt(bookingEl.style.top, 10),
            originalHeight: parseInt(bookingEl.style.height, 10),
            originalWidth: parseInt(bookingEl.style.width, 10),
            startX: e.clientX,
            startY: e.clientY
        };

        document.addEventListener('mousemove', handleResizing);
        document.addEventListener('mouseup', handleResizeEnd);
    }

    function handleResizing(e) {
        if(!resizeData) return;

        const deltaX = e.clientX - resizeData.startX;
        const deltaY = e.clientY - resizeData.startY;
        const deltaSlots = Math.round(deltaX / CELL_WIDTH);
        const deltaRows = Math.round(deltaY / ROW_HEIGHT);

        let newStart = resizeData.startTime;
        let newEnd = resizeData.endTime;
        let newAssignedTableIds = resizeData.assignedTableIds.slice();

        if(resizing === 'left') {
            const baseDate = new Date(`2000-01-01 ${resizeData.startTime}`);
            baseDate.setMinutes(baseDate.getMinutes() + deltaSlots * INTERVAL_MINS);
            if(baseDate < new Date(`2000-01-01 ${resizeData.endTime}`) && baseDate >= new Date(`2000-01-01 ${START_HOUR.toString().padStart(2, '0')}:00:00`)) {
                newStart = `${String(baseDate.getHours()).padStart(2,'0')}:${String(baseDate.getMinutes()).padStart(2,'0')}:00`;
            }
        } else if(resizing === 'right') {
            const baseDate = new Date(`2000-01-01 ${resizeData.endTime}`);
            baseDate.setMinutes(baseDate.getMinutes() + deltaSlots * INTERVAL_MINS);
            if(baseDate > new Date(`2000-01-01 ${resizeData.startTime}`) && baseDate <= new Date(`2000-01-01 ${END_HOUR.toString().padStart(2,'0')}:00:00`)) {
                newEnd = `${String(baseDate.getHours()).padStart(2,'0')}:${String(baseDate.getMinutes()).padStart(2,'0')}:00`;
            }
        } else if(resizing === 'top' || resizing === 'bottom') {
            const visibleTables = getVisibleTables();
            const tableIndexMap = getTableIndexMap(visibleTables);
            const currentTopIndex = tableIndexMap[String(resizeData.assignedTableIds[0])];
            const currentBottomIndex = currentTopIndex + resizeData.assignedTableIds.length - 1;

            if(currentTopIndex !== undefined) {
                let nextTopIndex = currentTopIndex;
                let nextBottomIndex = currentBottomIndex;

                if(resizing === 'top') {
                    nextTopIndex = Math.max(0, Math.min(currentBottomIndex, currentTopIndex + deltaRows));
                } else {
                    nextBottomIndex = Math.min(visibleTables.length - 1, Math.max(currentTopIndex, currentBottomIndex + deltaRows));
                }

                if(nextBottomIndex >= nextTopIndex) {
                    newAssignedTableIds = visibleTables.slice(nextTopIndex, nextBottomIndex + 1).map(table => Number(table.table_id));
                }
            }
        }

        // Quick visual change before save
        const startIdx = getTimeSlots().indexOf(newStart.substring(0,5));
        const endIdx = getTimeSlots().indexOf(newEnd.substring(0,5));
        if(startIdx >= 0 && endIdx > startIdx) {
            resizeData.bookingEl.style.left = `${startIdx * CELL_WIDTH}px`;
            resizeData.bookingEl.style.width = `${(endIdx - startIdx) * CELL_WIDTH}px`;
        }

        if(newAssignedTableIds.length) {
            const layout = buildVisibleTableLayout();
            const topPosition = layout.tableTopMap[String(newAssignedTableIds[0])];
            const bottomPosition = layout.tableTopMap[String(newAssignedTableIds[newAssignedTableIds.length - 1])];
            if(topPosition !== undefined) {
                resizeData.bookingEl.style.top = `${topPosition}px`;
                resizeData.bookingEl.style.height = `${Math.max(ROW_HEIGHT, (bottomPosition - topPosition) + ROW_HEIGHT)}px`;
                resizeData.bookingEl.dataset.rowSpan = String(newAssignedTableIds.length);
            }
        }

        resizeData.editStartTime = newStart;
        resizeData.editEndTime = newEnd;
        resizeData.editTableIds = newAssignedTableIds;
    }

    function handleResizeEnd() {
        if(!resizeData) return;

        document.removeEventListener('mousemove', handleResizing);
        document.removeEventListener('mouseup', handleResizeEnd);

        // Persist the updated duration
        const {bookingId, bookingEl, editStartTime, editEndTime, bookingEl: bookingHTML} = resizeData;
        const booking = BOOKING_DATA.find(b => b.booking_id == bookingId);
        if(!booking || !editStartTime || !editEndTime) {
            resizeData = null;
            resizing = null;
            return;
        }

        const newStartTime = editStartTime;
        const newEndTime = editEndTime;
        const tableIds = resizeData.editTableIds && resizeData.editTableIds.length
            ? resizeData.editTableIds
            : getAssignedTableIds(booking);

        if(!confirmScheduledTimeChange(booking, newStartTime, newEndTime)) {
            renderTimeline();
            resizing = null;
            resizeData = null;
            return;
        }

        fetch('update-booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                booking_id: bookingId,
                table_id: tableIds[0] || null,
                table_ids: tableIds,
                start_time: newStartTime,
                end_time: newEndTime
            })
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                booking.start_time = newStartTime;
                booking.end_time = newEndTime;
                booking.table_id = data.table_id !== null ? Number(data.table_id) : null;
                booking.table_number = data.table_number || null;
                booking.assigned_table_ids = Array.isArray(data.table_ids) ? data.table_ids.map(Number) : [];
                booking.assigned_table_numbers = Array.isArray(data.table_numbers) ? data.table_numbers : [];
                renderTimeline();
            } else {
                alert('Not updated: ' + (data.error || 'Conflict or invalid'));                
                renderTimeline();
            }
        })
        .catch(err => {
            alert('Error updating booking: ' + err.message);
            renderTimeline();
        })
        .finally(() => {
            resizing = null;
            resizeData = null;
        });
    }

    // Navigation functions
    function parseLocalDate(dateString) {
        const [year, month, day] = dateString.split('-').map(Number);
        return new Date(year, month - 1, day);
    }

    function formatLocalDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function getDayLabel(dateString) {
        const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const date = parseLocalDate(dateString);
        return dayNames[date.getDay()];
    }

    function formatShortDisplayDate(dateString) {
        const date = parseLocalDate(dateString);
        const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${weekdayNames[date.getDay()]}, ${monthNames[date.getMonth()]} ${String(date.getDate()).padStart(2, '0')}`;
    }

    function openTimelineDatePicker() {
        const dateInput = document.getElementById('dateInput');
        if(!dateInput) return;

        if(typeof dateInput.showPicker === 'function') {
            dateInput.showPicker();
            return;
        }

        dateInput.focus();
        dateInput.click();
    }

    function syncCurrentDateState() {
        const timelineDateYear = document.getElementById('timelineDateYear');
        const timelineDatePrimary = document.getElementById('timelineDatePrimary');
        const todayButton = document.getElementById('todayButton');

        if (timelineDateYear) {
            timelineDateYear.textContent = parseLocalDate(SELECTED_DATE).getFullYear();
        }

        if (timelineDatePrimary) {
            timelineDatePrimary.textContent = formatShortDisplayDate(SELECTED_DATE);
        }

        if (todayButton) {
            todayButton.classList.toggle('is-hidden', SELECTED_DATE === formatLocalDate(new Date()));
        }
    }

    function redirectToLocalCurrentDateIfNeeded() {
        const url = new URL(window.location.href);
        const hasExplicitDate = url.searchParams.has('date');
        const localCurrentDate = formatLocalDate(new Date());

        if (!hasExplicitDate && SELECTED_DATE !== localCurrentDate) {
            url.searchParams.set('date', localCurrentDate);
            window.location.replace(url.toString());
            return true;
        }

        return false;
    }

    function previousDay() {
        const date = parseLocalDate(SELECTED_DATE);
        date.setDate(date.getDate() - 1);
        window.location.href = `?date=${formatLocalDate(date)}`;
    }

    function nextDay() {
        const date = parseLocalDate(SELECTED_DATE);
        date.setDate(date.getDate() + 1);
        window.location.href = `?date=${formatLocalDate(date)}`;
    }

    function todayDate() {
        const today = formatLocalDate(new Date());
        window.location.href = `?date=${today}`;
    }

    function changeDate() {
        const date = document.getElementById('dateInput').value;
        if(date) window.location.href = `?date=${date}`;
    }

    // Set current time line indicator
    function setCurrentTimeLine() {
        // Remove old line if exists
        const oldLine = document.querySelector('.current-time-line');
        if(oldLine) oldLine.remove();

        const now = new Date();
        const currentHour = now.getHours();
        const currentMin = now.getMinutes();

        if (currentHour >= START_HOUR && currentHour <= END_HOUR) {
            const timeSlots = getTimeSlots();
            const cellWidth = 80;
            let position = 0;

            for (let i = 0; i < timeSlots.length; i++) {
                const [h, m] = timeSlots[i].split(':').map(Number);
                if (h === currentHour && m <= currentMin) {
                    position = i * cellWidth + ((currentMin % INTERVAL_MINS) / INTERVAL_MINS) * cellWidth;
                }
            }

            const line = document.createElement('div');
            line.className = 'current-time-line';
            line.style.left = `${80 + position}px`;
            line.style.top = '0';
            line.style.bottom = '0';
            document.querySelector('.timeline-content').appendChild(line);
        }
    }
</script>

</body>
</html>
