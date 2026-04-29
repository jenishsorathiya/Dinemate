<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);

$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || strtotime($selectedDate) === false) {
    $selectedDate = date('Y-m-d');
}

$bookingsStmt = $pdo->prepare("
    SELECT b.booking_id,
           b.booking_date,
           b.start_time,
           b.end_time,
           b.requested_start_time,
           b.requested_end_time,
           b.number_of_guests,
           b.status,
           b.reservation_card_status,
           b.special_request,
           COALESCE(NULLIF(b.customer_email, ''), u.email, '') AS customer_email,
           COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name,
           COALESCE(
               GROUP_CONCAT(DISTINCT assigned_tables.table_number ORDER BY assigned_tables.table_number + 0, assigned_tables.table_number SEPARATOR ', '),
               direct_table.table_number
           ) AS assigned_table_numbers,
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
      AND b.status IN ('pending', 'confirmed', 'completed', 'no_show')
    GROUP BY b.booking_id
    ORDER BY b.start_time ASC, b.booking_id ASC
");
$bookingsStmt->execute([$selectedDate]);
$bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$bookingEditTables = $pdo->query("
    SELECT rt.table_id, rt.table_number, rt.capacity, COALESCE(ta.name, 'Dining room') AS area_name
    FROM restaurant_tables rt
    LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
    ORDER BY ta.display_order ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$adminPageTitle = 'Home';
$adminPageIcon = 'fa-house';
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'home';
$adminSidebarPathPrefix = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <style>
        .home-dashboard {
            display: grid;
            gap: 20px;
        }

        .home-panel-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .home-date-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .home-date-input {
            height: 38px;
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-sm);
            background: var(--dm-surface);
            color: var(--dm-text);
            padding: 0 10px;
            font-size: 13px;
        }

        .home-icon-button,
        .home-add-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            border-radius: var(--dm-radius-sm);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid var(--dm-border);
            cursor: pointer;
        }

        .home-icon-button {
            width: 38px;
            color: var(--dm-text);
            background: var(--dm-surface-muted);
        }

        .home-add-button {
            padding: 0 14px;
            color: var(--dm-surface);
            background: var(--dm-primary);
            border-color: var(--dm-primary);
        }

        .home-content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }

        .home-panel {
            background: var(--dm-surface);
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-md);
            overflow: hidden;
        }

        .home-bookings-panel {
            background: transparent;
            border: none;
            border-radius: 0;
            overflow: visible;
        }

        .home-bookings-panel .home-panel-header {
            min-height: 38px;
            padding: 0 0 14px;
            border-bottom: none;
        }

        .home-bookings-panel .home-booking-list {
            background: var(--dm-surface);
            border: 1px solid var(--dm-border);
            border-radius: var(--dm-radius-md);
            overflow: hidden;
        }

        .home-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 71px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--dm-border);
        }

        .home-panel-heading {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }

        .home-panel-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--dm-text);
        }

        .home-booking-count {
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .home-booking-list {
            display: grid;
        }

        .home-booking-item {
            display: grid;
            grid-template-columns: 32% 42% 22%;
            column-gap: 2%;
            row-gap: 10px;
            align-items: center;
            padding: 14px 18px;
            color: var(--dm-text);
            text-decoration: none;
            border-bottom: 1px solid var(--dm-border);
            cursor: pointer;
        }

        .home-booking-item:last-child {
            border-bottom: none;
        }

        .home-booking-item:hover {
            background: var(--dm-surface-muted);
        }

        .home-booking-left,
        .home-booking-middle,
        .home-booking-right {
            display: flex;
            align-items: center;
        }

        .home-booking-left {
            gap: 16px;
            min-width: 0;
        }

        .home-booking-middle {
            display: grid;
            grid-template-columns: 38% 62%;
            gap: 18px;
            align-items: center;
            min-width: 0;
        }

        .home-booking-right {
            display: grid;
            grid-template-columns: max-content;
            justify-content: end;
            white-space: nowrap;
        }

        .home-booking-time {
            font-size: 13px;
            font-weight: 800;
            color: var(--dm-text);
            flex: 0 0 86px;
        }

        .home-booking-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--dm-text);
            overflow-wrap: anywhere;
        }

        .home-booking-meta {
            margin-top: 3px;
            color: var(--dm-text-muted);
            font-size: 12px;
        }

        .home-booking-table {
            color: var(--dm-text);
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .home-booking-note {
            color: var(--dm-text-muted);
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .home-status {
            text-transform: capitalize;
            cursor: pointer;
        }

        .home-status.pending {
            cursor: pointer;
        }

        .home-status.is-saving {
            opacity: 0.65;
            pointer-events: none;
        }

        .home-empty {
            padding: 28px 18px;
            color: var(--dm-text-muted);
            font-size: 13px;
            text-align: center;
        }

        @media (max-width: 991px) {
            .home-panel-header,
            .home-panel-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .home-panel-actions,
            .home-date-form,
            .home-add-button {
                width: 100%;
            }

            .home-date-input {
                flex: 1;
            }

            .home-content-grid {
                grid-template-columns: 1fr;
            }

            .home-booking-item {
                grid-template-columns: 34% 40% 22%;
                column-gap: 2%;
            }
        }

        @media (max-width: 640px) {
            .home-booking-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .home-booking-left {
                align-items: flex-start;
            }

            .home-booking-time {
                flex: 0 0 72px;
            }

            .home-booking-middle {
                grid-template-columns: 35% 65%;
                gap: 10px;
                justify-content: stretch;
            }

            .home-booking-right {
                justify-content: flex-start;
            }

            .home-status {
                width: fit-content;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <div class="main-content">
            <main class="admin-container home-dashboard">
                <div class="home-content-grid">
                    <section class="home-panel home-bookings-panel">
                        <div class="home-panel-header">
                            <div class="home-panel-heading">
                                <h1 class="home-panel-title">Bookings</h1>
                                <span class="home-booking-count"><?php echo count($bookings); ?> bookings</span>
                            </div>
                            <div class="home-panel-actions">
                                <form class="home-date-form" method="GET" action="home.php">
                                    <input class="home-date-input" type="date" name="date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button class="home-icon-button" type="submit" aria-label="View date"><i class="fa fa-arrow-right"></i></button>
                                </form>
                                <a class="home-add-button" href="../timeline/timeline.php?date=<?php echo urlencode($selectedDate); ?>#bookingList">
                                    <i class="fa fa-plus"></i>
                                    <span>Add Booking</span>
                                </a>
                            </div>
                        </div>

                        <div class="home-booking-list">
                            <?php if (empty($bookings)): ?>
                                <div class="home-empty">No bookings for this date.</div>
                            <?php else: ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <?php
                                        $bookingStatus = strtolower((string) ($booking['status'] ?? ''));
                                        $placementStatus = strtolower((string) ($booking['reservation_card_status'] ?? ''));
                                        if (!in_array($placementStatus, getBookingPlacementStatuses(), true)) {
                                            $placementStatus = 'not_placed';
                                        }
                                        $statusIsPlacement = $bookingStatus === 'confirmed';
                                        $statusCanConfirm = $bookingStatus === 'pending';
                                        $displayStatusClass = $statusIsPlacement ? $placementStatus : 'pending';
                                        $displayChipClass = $statusIsPlacement
                                            ? ($placementStatus === 'placed' ? 'ui-chip-success' : 'ui-chip-warning')
                                            : 'ui-chip-danger';
                                        $displayStatusLabel = $statusIsPlacement ? getBookingPlacementLabel($placementStatus) : 'Pending';
                                        $bookingNote = trim((string) ($booking['special_request'] ?? ''));
                                        $tableText = $booking['assigned_table_numbers'] ? 'Table ' . $booking['assigned_table_numbers'] : 'No table';
                                        $assignedTableIds = array_values(array_filter(array_map('intval', explode(',', (string) ($booking['assigned_table_ids'] ?? '')))));
                                        $primaryTableId = $assignedTableIds[0] ?? '';
                                        $startTimeValue = substr((string) ($booking['start_time'] ?? ''), 0, 5);
                                        $endTimeValue = substr((string) ($booking['end_time'] ?? ''), 0, 5);
                                        $requestedStartValue = substr((string) (($booking['requested_start_time'] ?? '') ?: ($booking['start_time'] ?? '')), 0, 5);
                                        $requestedEndValue = substr((string) (($booking['requested_end_time'] ?? '') ?: ($booking['end_time'] ?? '')), 0, 5);
                                    ?>
                                    <div
                                        class="home-booking-item"
                                        data-booking-row
                                        data-booking-id="<?php echo (int) $booking['booking_id']; ?>"
                                        data-customer-name="<?php echo htmlspecialchars((string) $booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-booking-date="<?php echo htmlspecialchars((string) $booking['booking_date'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-start-time="<?php echo htmlspecialchars($startTimeValue, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-end-time="<?php echo htmlspecialchars($endTimeValue, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-requested-start-time="<?php echo htmlspecialchars($requestedStartValue, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-requested-end-time="<?php echo htmlspecialchars($requestedEndValue, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-number-of-guests="<?php echo (int) $booking['number_of_guests']; ?>"
                                        data-special-request="<?php echo htmlspecialchars($bookingNote, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-table-id="<?php echo htmlspecialchars((string) $primaryTableId, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-status="<?php echo htmlspecialchars($bookingStatus, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-customer-email="<?php echo htmlspecialchars((string) ($booking['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <div class="home-booking-left">
                                            <div class="home-booking-time">
                                                <?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['start_time'])), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div>
                                                <div class="home-booking-name"><?php echo htmlspecialchars((string) $booking['customer_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="home-booking-meta">
                                                    <?php echo (int) $booking['number_of_guests']; ?> guests
                                                </div>
                                            </div>
                                        </div>
                                        <div class="home-booking-middle">
                                            <div class="home-booking-table"><?php echo htmlspecialchars($tableText, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php if ($bookingNote !== ''): ?>
                                                <div class="home-booking-note"><?php echo htmlspecialchars($bookingNote, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="home-booking-right">
                                            <span
                                                class="home-status ui-chip <?php echo htmlspecialchars($displayChipClass, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($displayStatusClass, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php if ($statusIsPlacement): ?>
                                                    role="button"
                                                    tabindex="0"
                                                    data-placement-status
                                                    data-booking-id="<?php echo (int) $booking['booking_id']; ?>"
                                                    data-current-status="<?php echo htmlspecialchars($placementStatus, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php elseif ($statusCanConfirm): ?>
                                                    role="button"
                                                    tabindex="0"
                                                    data-confirm-pending
                                                    data-booking-id="<?php echo (int) $booking['booking_id']; ?>"
                                                <?php endif; ?>
                                            >
                                                <?php echo htmlspecialchars($displayStatusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <?php
    $bookingEditModalId = 'homeBookingEditModal';
    $bookingEditFormId = 'homeBookingEditForm';
    $bookingEditTitle = 'Edit Booking';
    include __DIR__ . '/../../includes/components/booking-editing-modal.php';
    ?>

    <script>
        const bookingEditModal = document.getElementById('homeBookingEditModal');
        const bookingEditForm = document.getElementById('homeBookingEditForm');
        const bookingEditError = bookingEditModal.querySelector('[data-booking-edit-error]');
        const bookingEditSave = bookingEditModal.querySelector('[data-booking-edit-save]');
        const bookingEditFields = {
            id: bookingEditModal.querySelector('[data-booking-edit-id]'),
            date: bookingEditModal.querySelector('[data-booking-edit-date]'),
            name: bookingEditModal.querySelector('[data-booking-edit-name]'),
            start: bookingEditModal.querySelector('[data-booking-edit-start]'),
            end: bookingEditModal.querySelector('[data-booking-edit-end]'),
            guests: bookingEditModal.querySelector('[data-booking-edit-guests]'),
            table: bookingEditModal.querySelector('[data-booking-edit-table]'),
            notes: bookingEditModal.querySelector('[data-booking-edit-notes]'),
            status: bookingEditModal.querySelector('[data-booking-edit-status]'),
            email: bookingEditModal.querySelector('[data-booking-edit-email]'),
            delete: bookingEditModal.querySelector('[data-booking-edit-delete]')
        };
        let activeBookingRow = null;

        const setBookingEditError = (message = '') => {
            bookingEditError.textContent = message;
            bookingEditError.classList.toggle('is-visible', Boolean(message));
        };

        const openBookingEditModal = (row) => {
            activeBookingRow = row;
            bookingEditFields.id.value = row.dataset.bookingId || '';
            bookingEditFields.date.value = row.dataset.bookingDate || '';
            bookingEditFields.name.value = row.dataset.customerName || '';
            bookingEditFields.start.value = row.dataset.startTime || '';
            bookingEditFields.end.value = row.dataset.endTime || '';
            bookingEditFields.guests.value = row.dataset.numberOfGuests || '1';
            bookingEditFields.table.value = row.dataset.tableId || '';
            bookingEditFields.notes.value = row.dataset.specialRequest || '';
            bookingEditFields.status.value = row.dataset.status || 'pending';
            bookingEditFields.email.value = row.dataset.customerEmail || '';
            setBookingEditError('');
            bookingEditModal.hidden = false;
            bookingEditFields.name.focus();
        };

        const closeBookingEditModal = () => {
            bookingEditModal.hidden = true;
            activeBookingRow = null;
            setBookingEditError('');
        };

        document.querySelectorAll('[data-booking-row]').forEach((row) => {
            row.addEventListener('click', () => openBookingEditModal(row));
        });

        bookingEditModal.querySelectorAll('[data-booking-edit-close], [data-booking-edit-cancel]').forEach((button) => {
            button.addEventListener('click', closeBookingEditModal);
        });

        bookingEditModal.addEventListener('click', (event) => {
            if (event.target === bookingEditModal) {
                closeBookingEditModal();
            }
        });

        const formatTimeLabel = (value) => {
            if (!value) return '';
            const [hours, minutes] = String(value).split(':');
            const date = new Date();
            date.setHours(Number(hours || 0), Number(minutes || 0), 0, 0);
            return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        };

        const addMinutes = (timeValue, minutesToAdd) => {
            const [hours, minutes] = String(timeValue || '12:00').split(':').map(Number);
            const date = new Date();
            date.setHours(hours || 12, minutes || 0, 0, 0);
            date.setMinutes(date.getMinutes() + minutesToAdd);
            return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
        };

        const getDurationMinutes = (startValue, endValue) => {
            const [startHours, startMinutes] = String(startValue || '').split(':').map(Number);
            const [endHours, endMinutes] = String(endValue || '').split(':').map(Number);
            const startTotal = (startHours * 60) + (startMinutes || 0);
            const endTotal = (endHours * 60) + (endMinutes || 0);
            return endTotal > startTotal ? endTotal - startTotal : 60;
        };

        bookingEditForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!activeBookingRow) return;

            bookingEditSave.disabled = true;
            setBookingEditError('');

            const selectedTableOption = bookingEditFields.table.options[bookingEditFields.table.selectedIndex];
            const durationMinutes = getDurationMinutes(activeBookingRow.dataset.startTime, activeBookingRow.dataset.endTime);
            const nextEndTime = addMinutes(bookingEditFields.start.value, durationMinutes);
            const payload = {
                booking_id: bookingEditFields.id.value,
                customer_name: bookingEditFields.name.value.trim(),
                customer_email: bookingEditFields.email.value.trim(),
                booking_date: bookingEditFields.date.value,
                status: bookingEditFields.status.value,
                requested_start_time: bookingEditFields.start.value,
                requested_end_time: nextEndTime,
                start_time: bookingEditFields.start.value,
                end_time: nextEndTime,
                number_of_guests: bookingEditFields.guests.value,
                special_request: bookingEditFields.notes.value.trim(),
                table_id: bookingEditFields.table.value
            };

            try {
                const response = await fetch('../timeline/update-booking-details.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Could not save booking');
                }

                const booking = data.booking || {};
                const nextName = booking.customer_name || payload.customer_name;
                const nextGuests = booking.number_of_guests || payload.number_of_guests;
                const nextStart = String(booking.start_time || payload.start_time).slice(0, 5);
                const nextEnd = String(booking.end_time || payload.end_time).slice(0, 5);
                const nextNote = booking.special_request || payload.special_request || '';
                const nextDate = booking.booking_date || payload.booking_date;
                const nextStatus = booking.status || payload.status;
                const nextEmail = booking.customer_email || payload.customer_email || '';
                const nextTableId = Array.isArray(booking.assigned_table_ids) && booking.assigned_table_ids.length
                    ? String(booking.assigned_table_ids[0])
                    : bookingEditFields.table.value;
                const nextTableLabel = selectedTableOption?.dataset.label || 'No table';

                activeBookingRow.dataset.customerName = nextName;
                activeBookingRow.dataset.startTime = nextStart;
                activeBookingRow.dataset.endTime = nextEnd;
                activeBookingRow.dataset.bookingDate = nextDate;
                activeBookingRow.dataset.status = nextStatus;
                activeBookingRow.dataset.customerEmail = nextEmail;
                activeBookingRow.dataset.numberOfGuests = String(nextGuests);
                activeBookingRow.dataset.specialRequest = nextNote;
                activeBookingRow.dataset.tableId = nextTableId;

                activeBookingRow.querySelector('.home-booking-time').textContent = formatTimeLabel(nextStart);
                activeBookingRow.querySelector('.home-booking-name').textContent = nextName;
                activeBookingRow.querySelector('.home-booking-meta').textContent = `${nextGuests} guests`;
                activeBookingRow.querySelector('.home-booking-table').textContent = nextTableLabel;

                const middleSection = activeBookingRow.querySelector('.home-booking-middle');
                let noteElement = activeBookingRow.querySelector('.home-booking-note');
                if (nextNote) {
                    if (!noteElement) {
                        noteElement = document.createElement('div');
                        noteElement.className = 'home-booking-note';
                        middleSection.appendChild(noteElement);
                    }
                    noteElement.textContent = nextNote;
                } else if (noteElement) {
                    noteElement.remove();
                }

                closeBookingEditModal();
                window.location.reload();
            } catch (error) {
                setBookingEditError(error.message);
            } finally {
                bookingEditSave.disabled = false;
            }
        });

        bookingEditFields.delete.addEventListener('click', async () => {
            if (!activeBookingRow || !confirm('Delete this booking?')) {
                return;
            }

            bookingEditFields.delete.disabled = true;
            setBookingEditError('');

            try {
                const response = await fetch('../timeline/cancel-booking.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ booking_id: bookingEditFields.id.value })
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'Could not delete booking');
                }

                activeBookingRow.remove();
                closeBookingEditModal();
            } catch (error) {
                setBookingEditError(error.message);
            } finally {
                bookingEditFields.delete.disabled = false;
            }
        });

        const bindPlacementToggle = (statusChip) => {
            const togglePlacement = async (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (statusChip.classList.contains('is-saving')) {
                    return;
                }

                const nextStatus = statusChip.dataset.currentStatus === 'placed' ? 'not_placed' : 'placed';

                statusChip.classList.add('is-saving');

                try {
                    const response = await fetch('../timeline/update-placement-status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            booking_id: statusChip.dataset.bookingId,
                            reservation_card_status: nextStatus
                        })
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not update placement status');
                    }

                    statusChip.dataset.currentStatus = data.reservation_card_status || nextStatus;
                    statusChip.classList.toggle('placed', statusChip.dataset.currentStatus === 'placed');
                    statusChip.classList.toggle('not_placed', statusChip.dataset.currentStatus === 'not_placed');
                    statusChip.classList.toggle('ui-chip-success', statusChip.dataset.currentStatus === 'placed');
                    statusChip.classList.toggle('ui-chip-warning', statusChip.dataset.currentStatus === 'not_placed');
                    statusChip.textContent = data.reservation_card_status_label || (statusChip.dataset.currentStatus === 'placed' ? 'Placed' : 'Not placed');
                } catch (error) {
                    alert(error.message);
                } finally {
                    statusChip.classList.remove('is-saving');
                }
            };

            statusChip.addEventListener('click', togglePlacement);
            statusChip.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    togglePlacement(event);
                }
            });
        };

        document.querySelectorAll('[data-placement-status]').forEach(bindPlacementToggle);

        document.querySelectorAll('[data-confirm-pending]').forEach((statusChip) => {
            const confirmPending = async (event) => {
                if (!statusChip.hasAttribute('data-confirm-pending')) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (statusChip.classList.contains('is-saving')) {
                    return;
                }

                statusChip.classList.add('is-saving');

                try {
                    const response = await fetch('../timeline/confirm-pending-booking.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            booking_id: statusChip.dataset.bookingId
                        })
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Could not confirm booking');
                    }

                    const placementStatus = data.reservation_card_status || 'not_placed';
                    const placementLabel = data.reservation_card_status_label || 'Not placed';

                    statusChip.removeAttribute('data-confirm-pending');
                    statusChip.setAttribute('data-placement-status', '');
                    statusChip.dataset.currentStatus = placementStatus;
                    statusChip.classList.remove('pending', 'ui-chip-danger');
                    statusChip.classList.toggle('placed', placementStatus === 'placed');
                    statusChip.classList.toggle('not_placed', placementStatus === 'not_placed');
                    statusChip.classList.toggle('ui-chip-success', placementStatus === 'placed');
                    statusChip.classList.toggle('ui-chip-warning', placementStatus === 'not_placed');
                    statusChip.textContent = placementLabel;

                    bindPlacementToggle(statusChip);
                } catch (error) {
                    alert(error.message);
                } finally {
                    statusChip.classList.remove('is-saving');
                }
            };

            statusChip.addEventListener('click', confirmPending);
            statusChip.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    confirmPending(event);
                }
            });
        });
    </script>
</body>
</html>
