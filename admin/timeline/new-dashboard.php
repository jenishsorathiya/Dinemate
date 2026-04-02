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
$tables = $pdo->query("SELECT table_id, table_number, capacity FROM restaurant_tables ORDER BY table_number ASC")->fetchAll(PDO::FETCH_ASSOC);

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);

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
    SELECT b.*, COALESCE(b.customer_name_override, u.name) as customer_name,
           GROUP_CONCAT(DISTINCT bta.table_id ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ',') AS assigned_table_ids,
           GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ',') AS assigned_table_numbers
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
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
            padding: 10px 6px 12px 6px;
            border-bottom: 1px solid #e5e7eb;
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
            padding-top: 12px;
        }

        /* LEFT PANEL - CALENDAR & TABLES */
        .left-panel {
            width: 260px;
            background: white;
            border-top: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .booking-list {
            padding: 12px 14px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }

        .booking-list-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
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
            transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        }

        .booking-list-tab.active {
            background: #111827;
            border-color: #111827;
            color: #ffffff;
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

        .tables-section {
            padding: 0 16px 16px 16px;
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
            background: white;
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
            margin-bottom: 6px;
        }

        .booking-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .booking-detail-grid .full-width {
            grid-column: 1 / -1;
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
            border-top: 1px solid #e5e7eb;
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
                        <button type="button" class="booking-list-tab active" id="standbyTabBtn" onclick="switchBookingListTab('standby')">Standby</button>
                        <button type="button" class="booking-list-tab" id="bookingsTabBtn" onclick="switchBookingListTab('bookings')">Bookings</button>
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
    <div class="booking-modal-card">
        <div class="booking-modal-header">
            <h5><i class="fa fa-calendar-plus"></i> Add a Booking</h5>
            <button type="button" class="booking-modal-close" id="closeBookingModalBtn" aria-label="Close">&times;</button>
        </div>
        <div class="modal-error" id="bookingModalError"></div>
        <form id="adminBookingForm">
            <div class="modal-form-group">
                <label for="adminBookingName">Name</label>
                <input type="text" id="adminBookingName" required>
            </div>
            <div class="modal-form-group">
                <label for="adminBookingDate">Date</label>
                <input type="date" id="adminBookingDate" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
            </div>
            <div class="modal-form-group">
                <label for="adminBookingTime">Time</label>
                <input type="time" id="adminBookingTime" min="10:00" max="21:00" step="1800" value="12:00" required>
                <div class="modal-helper-text">Admin-created bookings are added as a 60-minute pending booking on the selected date.</div>
            </div>
            <div class="modal-form-group">
                <label for="adminBookingGuests">Number of People</label>
                <input type="number" id="adminBookingGuests" min="1" required>
            </div>
            <div class="modal-form-group">
                <label for="adminBookingNotes">Notes</label>
                <textarea id="adminBookingNotes" placeholder="Optional notes"></textarea>
            </div>
            <div class="booking-modal-actions">
                <button type="button" class="booking-modal-cancel" id="cancelBookingModalBtn">Cancel</button>
                <button type="submit" class="booking-modal-submit" id="submitAdminBookingBtn">Create Booking</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop-custom" id="bookingDetailsModal">
    <div class="booking-modal-card">
        <div class="booking-modal-header">
            <div>
                <h5><i class="fa fa-clipboard-list"></i> Booking Details</h5>
                <div class="booking-meta-chip" id="bookingDetailsMeta"></div>
            </div>
        </div>
        <div class="modal-error" id="bookingDetailsError"></div>
        <form id="bookingDetailsForm">
            <input type="hidden" id="bookingDetailsId">
            <div class="booking-detail-topbar">
                <button type="button" class="booking-modal-danger-small" id="cancelBookingActionBtn">Cancel Booking</button>
            </div>
            <div class="booking-detail-grid">
                <div class="modal-form-group full-width">
                    <label for="bookingDetailsName">Name</label>
                    <input type="text" id="bookingDetailsName" required>
                </div>
                <div class="modal-form-group">
                    <label for="bookingRequestedStart">Requested Start</label>
                    <input type="time" id="bookingRequestedStart" min="10:00" max="21:30" step="1800" required>
                </div>
                <div class="modal-form-group">
                    <label for="bookingRequestedEnd">Requested End</label>
                    <input type="time" id="bookingRequestedEnd" min="10:30" max="22:00" step="1800" required>
                </div>
                <div class="modal-form-group">
                    <label for="bookingAssignedStart">Assigned Start</label>
                    <input type="time" id="bookingAssignedStart" min="10:00" max="21:30" step="1800" required>
                </div>
                <div class="modal-form-group">
                    <label for="bookingAssignedEnd">Assigned End</label>
                    <input type="time" id="bookingAssignedEnd" min="10:30" max="22:00" step="1800" required>
                </div>
                <div class="modal-form-group full-width">
                    <label for="bookingDetailsGuests">Number of People</label>
                    <input type="number" id="bookingDetailsGuests" min="1" required>
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
                <h5><i class="fa fa-chair"></i> Table Details</h5>
                <div class="booking-meta-chip" id="tableDetailsMeta"></div>
            </div>
            <button type="button" class="booking-modal-close" id="closeTableDetailsModalBtn" aria-label="Close">&times;</button>
        </div>
        <div class="modal-error" id="tableDetailsError"></div>
        <form id="tableDetailsForm">
            <input type="hidden" id="tableDetailsId">
            <div class="modal-form-group">
                <label for="tableDetailsNumber">Table</label>
                <input type="text" id="tableDetailsNumber" readonly>
            </div>
            <div class="modal-form-group">
                <label for="tableDetailsCapacity">Capacity</label>
                <input type="number" id="tableDetailsCapacity" min="1" required>
            </div>
            <div class="booking-modal-actions">
                <button type="button" class="booking-modal-cancel" id="cancelTableDetailsBtn">Close</button>
                <button type="submit" class="booking-modal-submit" id="saveTableDetailsBtn">Save Capacity</button>
            </div>
        </form>
    </div>
</div>

<script>
    const BOOKING_DATA = <?php echo $bookingsJson; ?>;
    const TABLES = <?php echo json_encode($tables); ?>;
    const SELECTED_DATE = '<?php echo $selectedDate; ?>';
    const START_HOUR = 10; // 10 AM
    const END_HOUR = 23;   // 11 PM
    const INTERVAL_MINS = 30;
    const CELL_WIDTH = 80;
    const ROW_HEIGHT = 40;
    let activeBookingListTab = 'standby';

    // Initialize timeline on page load
    document.addEventListener('DOMContentLoaded', function() {
        if (redirectToLocalCurrentDateIfNeeded()) {
            return;
        }

        syncCurrentDateState();
        bindBookingModal();
        bindBookingDetailsModal();
        bindTableDetailsModal();
        renderTimeline();
        setCurrentTimeLine();

    });

    function bindBookingModal() {
        const modal = document.getElementById('bookingModal');
        const openBtn = document.getElementById('openBookingModalBtn');
        const closeBtn = document.getElementById('closeBookingModalBtn');
        const cancelBtn = document.getElementById('cancelBookingModalBtn');
        const form = document.getElementById('adminBookingForm');
        const errorBox = document.getElementById('bookingModalError');
        const submitBtn = document.getElementById('submitAdminBookingBtn');

        if(!modal || !openBtn || !form) return;

        function openModal() {
            modal.classList.add('open');
            document.body.classList.add('modal-open');
            errorBox.style.display = 'none';
            form.reset();
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

        modal.addEventListener('click', function(e) {
            if(e.target === modal) {
                closeModal();
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const payload = {
                name: document.getElementById('adminBookingName').value.trim(),
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
        const addTableBtn = document.getElementById('addTableBtn');
        if(!addTableBtn) return;

        addTableBtn.addEventListener('click', function() {
            fetch('create-table.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    auto: true
                })
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    TABLES.push({
                        table_id: data.table_id,
                        table_number: data.table_number,
                        capacity: data.capacity
                    });
                    renderTimeline();
                } else {
                    console.error('Could not add table:', data.error || 'Unknown error');
                }
            })
            .catch(err => {
                console.error(err);
                console.error('Error adding table:', err.message);
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

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const payload = {
                booking_id: document.getElementById('bookingDetailsId').value,
                customer_name: document.getElementById('bookingDetailsName').value.trim(),
                requested_start_time: document.getElementById('bookingRequestedStart').value,
                requested_end_time: document.getElementById('bookingRequestedEnd').value,
                start_time: document.getElementById('bookingAssignedStart').value,
                end_time: document.getElementById('bookingAssignedEnd').value,
                number_of_guests: document.getElementById('bookingDetailsGuests').value,
                special_request: document.getElementById('bookingDetailsNotes').value.trim(),
            };

            errorBox.style.display = 'none';
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

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
                closeModal();
            })
            .catch(error => {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            });
        });
    }

    function bindTableDetailsModal() {
        const modal = document.getElementById('tableDetailsModal');
        const closeBtn = document.getElementById('closeTableDetailsModalBtn');
        const cancelBtn = document.getElementById('cancelTableDetailsBtn');
        const form = document.getElementById('tableDetailsForm');
        const errorBox = document.getElementById('tableDetailsError');
        const saveBtn = document.getElementById('saveTableDetailsBtn');

        if(!modal || !form) return;

        function closeModal() {
            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
            errorBox.style.display = 'none';
        }

        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if(e.target === modal) {
                closeModal();
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const payload = {
                table_id: document.getElementById('tableDetailsId').value,
                capacity: document.getElementById('tableDetailsCapacity').value,
            };

            errorBox.style.display = 'none';
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            fetch('update-table.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async response => {
                const data = await response.json();
                if(!response.ok || !data.success) {
                    throw new Error(data.error || 'Failed to update table');
                }
                return data;
            })
            .then(data => {
                const tableIdx = TABLES.findIndex(table => String(table.table_id) === String(data.table.table_id));
                if(tableIdx !== -1) {
                    TABLES[tableIdx] = {
                        ...TABLES[tableIdx],
                        ...data.table,
                    };
                }
                renderTimeline();
                closeModal();
            })
            .catch(error => {
                errorBox.textContent = error.message;
                errorBox.style.display = 'block';
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Capacity';
            });
        });
    }

    function openTableDetails(tableId) {
        const table = TABLES.find(item => String(item.table_id) === String(tableId));
        if(!table) return;

        document.getElementById('tableDetailsId').value = table.table_id;
        document.getElementById('tableDetailsNumber').value = `T${table.table_number}`;
        document.getElementById('tableDetailsCapacity').value = table.capacity;
        document.getElementById('tableDetailsMeta').textContent = `Current capacity: ${table.capacity} seats`;
        document.getElementById('tableDetailsError').style.display = 'none';
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

        const modal = document.getElementById('bookingDetailsModal');
        document.getElementById('bookingDetailsId').value = booking.booking_id;
        document.getElementById('bookingDetailsName').value = booking.customer_name || '';
        document.getElementById('bookingRequestedStart').value = getRequestedStartTime(booking).substring(0, 5);
        document.getElementById('bookingRequestedEnd').value = getRequestedEndTime(booking).substring(0, 5);
        document.getElementById('bookingAssignedStart').value = booking.start_time.substring(0, 5);
        document.getElementById('bookingAssignedEnd').value = booking.end_time.substring(0, 5);
        document.getElementById('bookingDetailsGuests').value = booking.number_of_guests;
        document.getElementById('bookingDetailsNotes').value = booking.special_request || '';
        document.getElementById('bookingDetailsMeta').textContent = `${booking.booking_date} • ${tableLabel} • ${booking.status}`;

        const errorBox = document.getElementById('bookingDetailsError');
        errorBox.style.display = 'none';
        modal.classList.add('open');
        document.body.classList.add('modal-open');
    }

    function switchBookingListTab(tabName) {
        activeBookingListTab = tabName === 'bookings' ? 'bookings' : 'standby';

        const standbyTabBtn = document.getElementById('standbyTabBtn');
        const bookingsTabBtn = document.getElementById('bookingsTabBtn');

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

        const standbyBookings = BOOKING_DATA
            .filter(booking => getAssignedTableIds(booking).length === 0)
            .sort((left, right) => `${left.start_time}`.localeCompare(`${right.start_time}`));

        const assignedBookings = BOOKING_DATA
            .filter(booking => getAssignedTableIds(booking).length > 0)
            .sort((left, right) => `${left.start_time}`.localeCompare(`${right.start_time}`));

        const isStandbyTab = activeBookingListTab === 'standby';
        const visibleBookings = isStandbyTab ? standbyBookings : assignedBookings;
        const emptyMessage = isStandbyTab
            ? 'No unassigned bookings for this date.'
            : 'No assigned bookings for this date.';

        if(visibleBookings.length === 0) {
            bookingList.innerHTML = `<p>${emptyMessage}</p>`;
            return;
        }

        bookingList.innerHTML = visibleBookings.map(booking => {
            const startTime = formatDisplayTime(booking.start_time);
            const noteIcon = booking.special_request
                ? `<span class="booking-item-note-icon" title="${booking.special_request.replace(/"/g, '&quot;')}"><i class="fa-solid fa-note-sticky"></i></span>`
                : '';
            const tableNumbers = getAssignedTableNumbers(booking);
            const rightSideText = isStandbyTab
                ? `<span class="booking-item-meta">P${booking.number_of_guests}</span>`
                : `<span class="booking-item-table">${tableNumbers.map(tableNumber => `T${tableNumber}`).join(', ')}</span>`;
            const bottomRowRight = isStandbyTab
                ? ''
                : `<span class="booking-item-bottom-right">P${booking.number_of_guests}</span>`;
            const draggableAttributes = isStandbyTab ? 'draggable="true"' : 'draggable="false"';
            const draggableClass = isStandbyTab ? ' draggable-booking' : '';
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

    function getTableIndexMap() {
        return TABLES.reduce((map, table, index) => {
            map[String(table.table_id)] = index;
            return map;
        }, {});
    }

    function getBookingSpanTableIds(startTableId, spanCount) {
        const startIndex = TABLES.findIndex(table => String(table.table_id) === String(startTableId));
        if(startIndex === -1) return [];
        return TABLES.slice(startIndex, startIndex + spanCount).map(table => Number(table.table_id));
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
        const tableIndexMap = getTableIndexMap();

        // Render time headings (x-axis)
        const timeHeader = document.getElementById('timeHeader');
        timeHeader.innerHTML = timeSlots.map(time => 
            `<div class="time-slot">${formatDisplayTime(time)}</div>`
        ).join('');

        // Render table labels (y-axis)
        const tableLabels = document.getElementById('tableLabels');
        tableLabels.innerHTML = TABLES.map(table => 
            `<div class="table-label clickable" onclick="openTableDetails(${table.table_id})" title="Edit table capacity">T${table.table_number}</div>`
        ).join('') + `<div class="table-label add-table-row"><button class="add-table-inline-btn" id="addTableBtn" title="Add table">+</button></div>`;

        // Populate booking list on left
        populateBookingList();

        // Render timeline rows for each table
        const timelineGrid = document.getElementById('timelineGrid');
        let gridHTML = '';
        let bookingHTML = '';
        
        TABLES.forEach(table => {
            let rowHTML = `<div class="table-row" data-table-id="${table.table_id}">`;
            
            timeSlots.forEach(time => {
                rowHTML += `<div class="time-cell" 
                    data-table-id="${table.table_id}" 
                    data-time="${time}"
                    ondrop="handleDrop(event)"
                    ondragover="handleDragOver(event)"></div>`;
            });
            
            rowHTML += '</div>';
            gridHTML += rowHTML;
        });

        bookingHTML = BOOKING_DATA
            .filter(booking => getAssignedTableIds(booking).length > 0)
            .map(booking => renderBooking(booking, timeSlots, tableIndexMap))
            .join('');

        gridHTML += `<div class="table-row add-table-row" aria-hidden="true">${timeSlots.map(() => `<div class="time-cell"></div>`).join('')}</div>`;
        gridHTML += bookingHTML;
        
        timelineGrid.innerHTML = gridHTML;

        bindBookingDragHandlers();
        bindAddTableButton();
        setCurrentTimeLine();
    }

    // Render a single booking block
    function renderBooking(booking, timeSlots, tableIndexMap) {
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
        const startIdx = timeSlots.indexOf(bookingStart);
        const firstAssignedTableId = assignedTableIds[0];
        const startRowIndex = tableIndexMap[String(firstAssignedTableId)];

        if(startIdx === -1 || startRowIndex === undefined) return '';

        const startDate = new Date(`2000-01-01 ${booking.start_time}`);
        const endDate = new Date(`2000-01-01 ${booking.end_time}`);
        const durationMins = (endDate - startDate) / (1000 * 60);
        const numSlots = Math.max(1, Math.ceil(durationMins / INTERVAL_MINS));
        const rowSpan = Math.max(1, assignedTableIds.length);

        const leftPosition = startIdx * CELL_WIDTH;
        const width = Math.max(numSlots * CELL_WIDTH, CELL_WIDTH);
        const topPosition = startRowIndex * ROW_HEIGHT;
        const height = rowSpan * ROW_HEIGHT;

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
        const titleText = rescheduledClass
            ? `${booking.customer_name} | ${guestCountText} | ${bookingStartLabel} - ${bookingEndLabel} | Requested ${requestedStartLabel} - ${requestedEndLabel} | Scheduled ${bookingStartLabel} - ${bookingEndLabel}`
            : `${booking.customer_name} | ${guestCountText} | ${bookingStartLabel} - ${bookingEndLabel}`;

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
            const tableIndexMap = getTableIndexMap();
            const currentTopIndex = tableIndexMap[String(resizeData.assignedTableIds[0])];
            const currentBottomIndex = currentTopIndex + resizeData.assignedTableIds.length - 1;

            if(currentTopIndex !== undefined) {
                let nextTopIndex = currentTopIndex;
                let nextBottomIndex = currentBottomIndex;

                if(resizing === 'top') {
                    nextTopIndex = Math.max(0, Math.min(currentBottomIndex, currentTopIndex + deltaRows));
                } else {
                    nextBottomIndex = Math.min(TABLES.length - 1, Math.max(currentTopIndex, currentBottomIndex + deltaRows));
                }

                if(nextBottomIndex >= nextTopIndex) {
                    newAssignedTableIds = TABLES.slice(nextTopIndex, nextBottomIndex + 1).map(table => Number(table.table_id));
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
            const tableIndexMap = getTableIndexMap();
            const topIndex = tableIndexMap[String(newAssignedTableIds[0])];
            if(topIndex !== undefined) {
                resizeData.bookingEl.style.top = `${topIndex * ROW_HEIGHT}px`;
                resizeData.bookingEl.style.height = `${newAssignedTableIds.length * ROW_HEIGHT}px`;
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
