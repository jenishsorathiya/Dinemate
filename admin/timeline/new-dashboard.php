<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

// Check if user is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin-login.php');
    exit();
}

// Get all tables
$tables = $pdo->query("SELECT table_id, table_number, capacity FROM restaurant_tables ORDER BY table_number ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get selected date (default to today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get bookings for selected date
$stmt = $pdo->prepare("
    SELECT b.*, u.name as customer_name, t.table_number
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    LEFT JOIN restaurant_tables t ON b.table_id = t.table_id
    WHERE b.booking_date = ? AND b.status IN ('pending', 'confirmed')
    ORDER BY b.start_time ASC
");
$stmt->execute([$selectedDate]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert bookings to JS array
$bookingsJson = json_encode($bookings);
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

        .container-fluid {
            display: flex;
            height: 100vh;
        }

        /* SIDEBAR */
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
            overflow: hidden;
            transition: opacity 0.2s ease, max-width 0.25s ease;
        }

        .sidebar:hover .brand-label,
        .sidebar:hover .nav-label {
            opacity: 1;
            max-width: 180px;
        }

        .sidebar:not(:hover) h4 {
            justify-content: center;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #f4b400;
            color: #111827;
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* HEADER */
        .header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .header h2 {
            margin: 0;
            font-weight: 600;
            color: #1f2937;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 12px;
        }

        .calendar-nav button,
        .today-button {
            background: #f4b400;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .calendar-nav button:hover,
        .today-button:hover {
            background: #e0a800;
        }

        .calendar-nav button {
            width: 42px;
            min-width: 42px;
            padding: 8px 0;
            font-size: 18px;
            line-height: 1;
        }

        /* CONTENT AREA */
        .content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* LEFT PANEL - CALENDAR & TABLES */
        .left-panel {
            width: 260px;
            background: white;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            position: relative;
        }

        /* CALENDAR */
        .calendar-section {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .booking-list {
            padding: 15px;
            overflow-y: auto;
            max-height: calc(100vh - 250px);
        }

        .booking-item {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
            cursor: grab;
        }

        .booking-item strong {
            display: block;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .booking-item small {
            color: #6b7280;
        }

        .booking-item.dragging {
            opacity: 0.6;
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

        .calendar-section h6 {
            font-weight: 600;
            margin-bottom: 15px;
            color: #1f2937;
        }

        .calendar {
            flex: 1;
        }

        .calendar input {
            width: 100%;
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .today-button {
            width: 100%;
        }

        /* TABLES LIST */
        .tables-section {
            padding: 20px;
            flex: 1;
        }

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
            overflow: hidden;
        }

        .table-row {
            display: flex;
            position: relative;
            height: var(--timeline-row-height);
            border-bottom: 1px solid #e5e7eb;
            overflow: hidden;
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
            height: 32px;
            padding: 4px;
            border-radius: 4px;
            cursor: grab;
            transition: box-shadow 0.2s;
            font-size: 11px;
            font-weight: 600;
            color: white;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            user-select: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .resize-handle {
            width: 10px;
            height: 100%;
            cursor: ew-resize;
            position: absolute;
            top: 0;
            z-index: 21;
            background: rgba(255,255,255,0.3);
        }

        .left-handle {
            left: 0;
        }

        .right-handle {
            right: 0;
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
            <i class="fa fa-chart-line"></i><span class="nav-label">Dashboard</span>
        </a>
        <a href="new-dashboard.php" class="active">
            <i class="fa fa-calendar-days"></i><span class="nav-label">Timeline</span>
        </a>
        <a href="../menu-management.php">
            <i class="fa fa-utensils"></i><span class="nav-label">Menu Management</span>
        </a>
        <a href="../manage-bookings.php">
            <i class="fa fa-calendar-check"></i><span class="nav-label">Bookings</span>
        </a>
        <a href="../../auth/logout.php">
            <i class="fa fa-sign-out-alt"></i><span class="nav-label">Logout</span>
        </a>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- HEADER -->
        <div class="header">
            <h2><i class="fa fa-calendar-days"></i> Booking Timeline</h2>
        </div>

        <!-- CONTENT -->
        <div class="content">
            <!-- LEFT PANEL -->
            <div class="left-panel">
                <!-- CALENDAR -->
                <div class="calendar-section">
                    <h6>Select Date</h6>
                    <div class="calendar-nav">
                        <button type="button" onclick="previousDay()" aria-label="Previous day">&lt;</button>
                        <div class="calendar">
                            <input type="date" id="dateInput" value="<?php echo $selectedDate; ?>" onchange="changeDate()">
                        </div>
                        <button type="button" onclick="nextDay()" aria-label="Next day">&gt;</button>
                    </div>
                    <button type="button" class="today-button" onclick="todayDate()">Today</button>
                </div>

                <!-- BOOKINGS LIST -->
                <div class="tables-section">
                    <h6>Unassigned Bookings</h6>
                    <div class="booking-list" id="bookingList"></div>
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

<script>
    const BOOKING_DATA = <?php echo $bookingsJson; ?>;
    const TABLES = <?php echo json_encode($tables); ?>;
    const SELECTED_DATE = '<?php echo $selectedDate; ?>';
    const START_HOUR = 10; // 10 AM
    const END_HOUR = 23;   // 11 PM
    const INTERVAL_MINS = 30;

    // Initialize timeline on page load
    document.addEventListener('DOMContentLoaded', function() {
        renderTimeline();
        setCurrentTimeLine();

    });

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

    function populateBookingList() {
        const bookingList = document.getElementById('bookingList');
        if(!bookingList) return;

        const unassignedBookings = BOOKING_DATA.filter(booking => booking.table_id === null || booking.table_id === undefined || booking.table_id === '');

        if(unassignedBookings.length === 0) {
            bookingList.innerHTML = '<p>No unassigned bookings for this date.</p>';
            return;
        }

        bookingList.innerHTML = unassignedBookings.map(booking => {
            const timeRange = `${booking.start_time.substring(0,5)} - ${booking.end_time.substring(0,5)}`;
            const specialRequest = booking.special_request
                ? `<small>${booking.special_request}</small>`
                : '';
            return `
                <div class="booking-item draggable-booking" draggable="true" data-booking-id="${booking.booking_id}">
                    <strong>${booking.customer_name}</strong>
                    <small>${timeRange}</small><br>
                    <small>${booking.number_of_guests} guests</small><br>
                    ${specialRequest}
                </div>
            `;
        }).join('');
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

        // Render time headings (x-axis)
        const timeHeader = document.getElementById('timeHeader');
        timeHeader.innerHTML = timeSlots.map(time => 
            `<div class="time-slot">${time}</div>`
        ).join('');

        // Render table labels (y-axis)
        const tableLabels = document.getElementById('tableLabels');
        tableLabels.innerHTML = TABLES.map(table => 
            `<div class="table-label">T${table.table_number}</div>`
        ).join('') + `<div class="table-label add-table-row"><button class="add-table-inline-btn" id="addTableBtn" title="Add table">+</button></div>`;

        // Populate booking list on left
        populateBookingList();

        // Render timeline rows for each table
        const timelineGrid = document.getElementById('timelineGrid');
        let gridHTML = '';
        
        TABLES.forEach(table => {
            const tableBookings = BOOKING_DATA.filter(b => b.table_id !== null && b.table_id !== undefined && String(b.table_id) === String(table.table_id));

            let rowHTML = `<div class="table-row" data-table-id="${table.table_id}">`;
            
            timeSlots.forEach(time => {
                rowHTML += `<div class="time-cell" 
                    data-table-id="${table.table_id}" 
                    data-time="${time}"
                    ondrop="handleDrop(event)"
                    ondragover="handleDragOver(event)"></div>`;
            });
            
            tableBookings.forEach(booking => {
                rowHTML += renderBooking(booking, timeSlots);
            });
            
            rowHTML += '</div>';
            gridHTML += rowHTML;
        });

        gridHTML += `<div class="table-row add-table-row" aria-hidden="true">${timeSlots.map(() => `<div class="time-cell"></div>`).join('')}</div>`;
        
        timelineGrid.innerHTML = gridHTML;

        bindBookingDragHandlers();
        bindAddTableButton();
        setCurrentTimeLine();
    }

    // Render a single booking block
    function renderBooking(booking, timeSlots) {
        const bookingStart = booking.start_time.substring(0, 5); // Convert HH:MM:SS to HH:MM
        const bookingEnd = booking.end_time.substring(0, 5);
        const startIdx = timeSlots.indexOf(bookingStart);

        if(startIdx === -1) return '';

        const startDate = new Date(`2000-01-01 ${booking.start_time}`);
        const endDate = new Date(`2000-01-01 ${booking.end_time}`);
        const durationMins = (endDate - startDate) / (1000 * 60);
        const numSlots = Math.max(1, Math.ceil(durationMins / INTERVAL_MINS));

        const rowHeight = 40;
        const CELL_WIDTH = 80;
        const leftPosition = startIdx * CELL_WIDTH;
        const width = Math.max(numSlots * CELL_WIDTH, CELL_WIDTH);
        const topPosition = 4;
        const height = rowHeight - 8;

        const statusClass = booking.status === 'confirmed' ? 'success' : (booking.status === 'pending' ? 'pending' : 'info');

        return `<div class="booking-block ${statusClass}"
                    draggable="true"
                    data-booking-id="${booking.booking_id}"
                    data-table-id="${booking.table_id}"
                    data-table-number="T${booking.table_number}"
                    data-time="${booking.start_time}"
                    data-duration="${durationMins}"
                    data-customer="${booking.customer_name}"
                    style="left: ${leftPosition}px; top: ${topPosition}px; width: ${width}px; height: ${height}px;"
                    title="${booking.customer_name} - ${bookingStart} to ${bookingEnd}">
            <div class="resize-handle left-handle"></div>
            <span style="font-size: 11px;">${booking.customer_name}</span><br>
            <span style="font-size: 10px; opacity: 0.85;">${bookingStart} - ${bookingEnd}</span>
            <div class="resize-handle right-handle"></div>
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

            if(leftHandle) {
                leftHandle.addEventListener('mousedown', handleResizeStart.bind(null, 'left', booking));
            }
            if(rightHandle) {
                rightHandle.addEventListener('mousedown', handleResizeStart.bind(null, 'right', booking));
            }
        });
    }

    let draggedBooking = null;
    let resizing = null;
    let resizeData = null;

    function handleDragStart(e) {
        draggedBooking = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
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

        const startDate = new Date(`2000-01-01 ${booking.start_time}`);
        const endDate = new Date(`2000-01-01 ${booking.end_time}`);
        const durationMins = (endDate - startDate) / (1000 * 60);

        // Calculate new end time
        const newStartTime = targetTime + ':00';
        const newEndDate = new Date(`2000-01-01 ${newStartTime}`);
        newEndDate.setMinutes(newEndDate.getMinutes() + durationMins);
        const newEndTime = String(newEndDate.getHours()).padStart(2, '0') + ':' + String(newEndDate.getMinutes()).padStart(2, '0') + ':00';

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
                    const targetTable = TABLES.find(table => String(table.table_id) === String(targetTableId));
                    BOOKING_DATA[bookingIdx].table_id = String(targetTableId);
                    BOOKING_DATA[bookingIdx].table_number = targetTable ? targetTable.table_number : null;
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

        resizing = direction;
        const bookingId = bookingEl.dataset.bookingId;
        const booking = BOOKING_DATA.find(b => b.booking_id == bookingId);
        if(!booking) return;

        const startTime = booking.start_time;
        const endTime = booking.end_time;
        const durationMins = (new Date(`2000-01-01 ${endTime}`) - new Date(`2000-01-01 ${startTime}`)) / (1000*60);

        resizeData = {
            bookingId,
            bookingEl,
            startTime,
            endTime,
            durationMins,
            originalLeft: parseInt(bookingEl.style.left, 10),
            originalWidth: parseInt(bookingEl.style.width, 10),
            startX: e.clientX
        };

        document.addEventListener('mousemove', handleResizing);
        document.addEventListener('mouseup', handleResizeEnd);
    }

    function handleResizing(e) {
        if(!resizeData) return;

        const deltaX = e.clientX - resizeData.startX;
        const slotWidth = 80;
        const deltaSlots = Math.round(deltaX / slotWidth);

        let newStart = resizeData.startTime;
        let newEnd = resizeData.endTime;

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
        }

        // Quick visual change before save
        const startIdx = getTimeSlots().indexOf(newStart.substring(0,5));
        const endIdx = getTimeSlots().indexOf(newEnd.substring(0,5));
        if(startIdx >= 0 && endIdx > startIdx) {
            resizeData.bookingEl.style.left = `${startIdx * slotWidth}px`;
            resizeData.bookingEl.style.width = `${(endIdx - startIdx) * slotWidth}px`;
        }

        resizeData.editStartTime = newStart;
        resizeData.editEndTime = newEnd;
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
        const tableId = booking.table_id;

        fetch('update-booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                booking_id: bookingId,
                table_id: tableId,
                start_time: newStartTime,
                end_time: newEndTime
            })
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                booking.start_time = newStartTime;
                booking.end_time = newEndTime;
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
    function previousDay() {
        const date = new Date(SELECTED_DATE);
        date.setDate(date.getDate() - 1);
        window.location.href = `?date=${date.toISOString().split('T')[0]}`;
    }

    function nextDay() {
        const date = new Date(SELECTED_DATE);
        date.setDate(date.getDate() + 1);
        window.location.href = `?date=${date.toISOString().split('T')[0]}`;
    }

    function todayDate() {
        const today = new Date().toISOString().split('T')[0];
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
