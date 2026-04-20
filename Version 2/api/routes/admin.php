<?php
declare(strict_types=1);

function api_is_reserved_placeholder_email(string $email): bool
{
    $normalized = strtolower(trim($email));
    return preg_match('/@admin-booking\.local$/', $normalized) === 1
        || preg_match('/^guest-.*@local\.dinemate$/', $normalized) === 1;
}

function api_validate_admin_user_payload(array $input, bool $passwordRequired = false): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = trim((string) ($input['phone'] ?? ''));
    $role = strtolower(trim((string) ($input['role'] ?? 'customer')));
    $password = (string) ($input['password'] ?? '');

    if ($name === '' || strlen($name) < 2 || strlen($name) > 100) {
        api_error('Name must be between 2 and 100 characters.', 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        api_error('Please provide a valid email address.', 422);
    }
    if (api_is_reserved_placeholder_email($email)) {
        api_error('That email pattern is reserved for placeholder accounts.', 422);
    }
    if ($phone !== '') {
        if (!preg_match('/^[0-9\\s\\-\\(\\)\\+]+$/', $phone)) {
            api_error('Phone number format is invalid.', 422);
        }
        $digitsOnly = preg_replace('/\D+/', '', $phone);
        if (strlen((string) $digitsOnly) < 6 || strlen($phone) > 30) {
            api_error('Phone number must include at least 6 digits and be no longer than 30 chars.', 422);
        }
    }
    if (!in_array($role, ['customer', 'admin'], true)) {
        api_error('Role must be customer or admin.', 422);
    }

    if ($passwordRequired && strlen($password) < 6) {
        api_error('Password must be at least 6 characters.', 422);
    }
    if ($password !== '' && strlen($password) < 6) {
        api_error('Password must be at least 6 characters.', 422);
    }
    if (strlen($password) > 255) {
        api_error('Password is too long.', 422);
    }

    return [
        'name' => $name,
        'email' => $email,
        'phone' => $phone !== '' ? $phone : null,
        'role' => $role,
        'password' => $password,
    ];
}

function api_route_admin(PDO $pdo, string $method, string $path): bool
{
    if ($path === '/v1/admin/areas/order' && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);

        $input = api_read_json_body();
        $areaIds = isset($input['area_ids']) && is_array($input['area_ids']) ? $input['area_ids'] : [];
        if (empty($areaIds)) {
            api_error('A valid area_ids order is required.', 422);
        }

        $normalizedAreaIds = [];
        foreach ($areaIds as $areaId) {
            $areaId = (int) $areaId;
            if ($areaId > 0 && !in_array($areaId, $normalizedAreaIds, true)) {
                $normalizedAreaIds[] = $areaId;
            }
        }
        if (empty($normalizedAreaIds)) {
            api_error('A valid area order is required.', 422);
        }

        $placeholders = implode(',', array_fill(0, count($normalizedAreaIds), '?'));
        $checkStmt = $pdo->prepare("SELECT area_id FROM table_areas WHERE is_active = 1 AND area_id IN ($placeholders)");
        $checkStmt->execute($normalizedAreaIds);
        $existingAreaIds = array_map('intval', $checkStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        sort($existingAreaIds);

        $activeAreaIds = array_map('intval', $pdo->query("SELECT area_id FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, area_id ASC")->fetchAll(PDO::FETCH_COLUMN));
        $sortedNormalizedAreaIds = $normalizedAreaIds;
        sort($sortedNormalizedAreaIds);

        if ($existingAreaIds !== $sortedNormalizedAreaIds || count($activeAreaIds) !== count($normalizedAreaIds)) {
            api_error('Area order must include each active area exactly once.', 400);
        }

        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare("UPDATE table_areas SET display_order = ? WHERE area_id = ?");
            foreach ($normalizedAreaIds as $index => $areaId) {
                $updateStmt->execute([($index + 1) * 10, $areaId]);
            }
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Could not update area order.', 500);
        }

        $areasStmt = $pdo->query("SELECT area_id, name, display_order, table_number_start, table_number_end, is_active FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
        api_response(['success' => true, 'areas' => $areasStmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($path === '/v1/admin/pending-bookings' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);

        $pendingStmt = $pdo->query("
            SELECT b.booking_id,
                   b.booking_date,
                   b.start_time,
                   b.number_of_guests,
                   COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name,
                   COALESCE(ta.name, '') AS area_name,
                   GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.user_id
            LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
            LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
            LEFT JOIN table_areas ta ON rt.area_id = ta.area_id
            WHERE b.status = 'pending'
            GROUP BY b.booking_id
            ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC
            LIMIT 100
        ");
        $rows = $pendingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        api_response(['success' => true, 'bookings' => $rows]);
    }

    if ($path === '/v1/admin/timeline' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);
        ensureTableAreasSchema($pdo);

        $selectedDate = trim((string) ($_GET['date'] ?? date('Y-m-d')));
        if (!api_validate_date($selectedDate)) {
            api_error('date query param must be YYYY-MM-DD', 422);
        }

        $areas = $pdo->query("SELECT area_id, name, display_order, table_number_start, table_number_end, is_active FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $tables = $pdo->query("
            SELECT rt.table_id, rt.table_number, rt.capacity, rt.area_id, rt.sort_order, rt.status, rt.reservable,
                   ta.name AS area_name, ta.display_order AS area_display_order
            FROM restaurant_tables rt
            LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
            ORDER BY ta.display_order ASC, ta.name ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $bookingsStmt = $pdo->prepare("
            SELECT b.*,
                   COALESCE(b.customer_name_override, b.customer_name, u.name) AS customer_name,
                   GROUP_CONCAT(DISTINCT bta.table_id ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ',') AS assigned_table_ids,
                   GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ',') AS assigned_table_numbers
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.user_id
            LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
            LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
            WHERE b.booking_date = ? AND b.status IN ('pending', 'confirmed', 'completed', 'no_show')
            GROUP BY b.booking_id
            ORDER BY b.start_time ASC, b.booking_id ASC
        ");
        $bookingsStmt->execute([$selectedDate]);
        $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($bookings as &$booking) {
            $booking['assigned_table_ids'] = !empty($booking['assigned_table_ids'])
                ? array_map('intval', explode(',', (string) $booking['assigned_table_ids']))
                : [];
            $booking['assigned_table_numbers'] = !empty($booking['assigned_table_numbers'])
                ? array_map('trim', explode(',', (string) $booking['assigned_table_numbers']))
                : [];
        }
        unset($booking);

        api_response([
            'success' => true,
            'date' => $selectedDate,
            'areas' => $areas,
            'tables' => $tables,
            'bookings' => $bookings,
        ]);
    }

    if ($path === '/v1/admin/bookings' && $method === 'POST') {
        $admin = api_require_user($pdo, 'admin');
        $input = api_read_json_body();
        $booking = api_create_booking(
            $pdo,
            $input,
            null,
            [
                'user_id' => null,
                'booking_source' => 'admin_manual',
                'created_by_user_id' => (int) $admin['user_id'],
                'require_contact_details' => false,
            ]
        );
        api_response(['success' => true, 'booking' => $booking], 201);
    }

    if (preg_match('#^/v1/admin/bookings/(\d+)/schedule$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        $input = api_read_json_body();

        $startTime = api_normalize_time((string) ($input['start_time'] ?? ''));
        $endTime = api_normalize_time((string) ($input['end_time'] ?? ''));
        $tableIds = isset($input['table_ids']) && is_array($input['table_ids']) ? $input['table_ids'] : [];
        $normalizedTableIds = [];
        foreach ($tableIds as $tableId) {
            $tableId = (int) $tableId;
            if ($tableId > 0 && !in_array($tableId, $normalizedTableIds, true)) {
                $normalizedTableIds[] = $tableId;
            }
        }
        if (empty($normalizedTableIds) && isset($input['table_id'])) {
            $fallback = (int) $input['table_id'];
            if ($fallback > 0) {
                $normalizedTableIds[] = $fallback;
            }
        }

        if ($startTime === null || $endTime === null || empty($normalizedTableIds)) {
            api_error('start_time, end_time and table assignment are required.', 422);
        }

        $windowError = api_validate_booking_window($startTime, $endTime, 30, null);
        if ($windowError !== null) {
            api_error($windowError, 422);
        }

        $bookingStmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? LIMIT 1");
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            api_error('Booking not found.', 404);
        }

        $tablePlaceholders = implode(',', array_fill(0, count($normalizedTableIds), '?'));
        $tableStmt = $pdo->prepare("SELECT table_id, table_number, capacity FROM restaurant_tables WHERE table_id IN ($tablePlaceholders)");
        $tableStmt->execute($normalizedTableIds);
        $tableRows = $tableStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($tableRows) !== count($normalizedTableIds)) {
            api_error('One or more target tables do not exist.', 404);
        }

        $conflictStmt = $pdo->prepare("
            SELECT COUNT(*) AS conflict_count
            FROM booking_table_assignments bta
            INNER JOIN bookings b ON b.booking_id = bta.booking_id
            WHERE bta.table_id = ?
              AND b.booking_date = ?
              AND b.booking_id != ?
              AND b.status IN ('pending', 'confirmed')
              AND (
                (b.start_time < ? AND b.end_time > ?)
                OR (b.start_time >= ? AND b.start_time < ?)
                OR (b.end_time > ? AND b.end_time <= ?)
              )
        ");
        foreach ($normalizedTableIds as $tableId) {
            $conflictStmt->execute([$tableId, (string) $booking['booking_date'], $bookingId, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
            $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);
            if ((int) ($conflict['conflict_count'] ?? 0) > 0) {
                api_error('Time conflict exists for one of the selected tables.', 409);
            }
        }

        $nextPlacementStatus = 'not_placed';
        $updateStmt = $pdo->prepare("
            UPDATE bookings
            SET start_time = ?, end_time = ?, status = 'confirmed', reservation_card_status = ?
            WHERE booking_id = ?
        ");
        $updateStmt->execute([$startTime, $endTime, $nextPlacementStatus, $bookingId]);
        $normalizedTableIds = syncBookingTableAssignments($pdo, $bookingId, $normalizedTableIds);

        $tableMap = [];
        foreach ($tableRows as $row) {
            $tableMap[(int) $row['table_id']] = $row;
        }
        $orderedNumbers = [];
        foreach ($normalizedTableIds as $tableId) {
            $orderedNumbers[] = (string) ($tableMap[$tableId]['table_number'] ?? '');
        }

        api_response([
            'success' => true,
            'booking_id' => $bookingId,
            'table_id' => $normalizedTableIds[0] ?? null,
            'table_number' => $orderedNumbers[0] ?? null,
            'table_ids' => $normalizedTableIds,
            'table_numbers' => $orderedNumbers,
            'status' => 'confirmed',
            'reservation_card_status' => $nextPlacementStatus,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    if (preg_match('#^/v1/admin/bookings/(\d+)/status$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        $input = api_read_json_body();
        $nextStatus = strtolower(trim((string) ($input['status'] ?? '')));
        $allowed = ['completed', 'cancelled', 'no_show'];
        if (!in_array($nextStatus, $allowed, true)) {
            api_error('Invalid status transition.', 422);
        }

        $pdo->beginTransaction();
        try {
            $bookingStmt = $pdo->prepare("SELECT booking_id, status, reservation_card_status FROM bookings WHERE booking_id = ? LIMIT 1");
            $bookingStmt->execute([$bookingId]);
            $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                $pdo->rollBack();
                api_error('Booking not found.', 404);
            }

            if (strtolower((string) ($booking['status'] ?? '')) !== 'confirmed') {
                $pdo->rollBack();
                api_error('Only confirmed bookings can be completed/cancelled/no-show.', 409);
            }

            if ($nextStatus === 'cancelled') {
                $pdo->prepare("DELETE FROM booking_table_assignments WHERE booking_id = ?")->execute([$bookingId]);
                $pdo->prepare("UPDATE bookings SET status = ?, table_id = NULL, reservation_card_status = NULL WHERE booking_id = ?")
                    ->execute([$nextStatus, $bookingId]);
            } else {
                $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?")->execute([$nextStatus, $bookingId]);
            }
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Could not update booking status.', 500);
        }

        api_response([
            'success' => true,
            'booking_id' => $bookingId,
            'status' => $nextStatus,
            'status_label' => getBookingStatusLabel($nextStatus),
        ]);
    }

    if (preg_match('#^/v1/admin/bookings/(\d+)/confirm-pending$#', $path, $matches) && $method === 'POST') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        if ($bookingId < 1) {
            api_error('A valid booking is required.', 422);
        }

        $stmt = $pdo->prepare("SELECT booking_id, status FROM bookings WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            api_error('Booking not found.', 404);
        }

        $assignedTablesStmt = $pdo->prepare("SELECT rt.table_id, rt.table_number FROM booking_table_assignments bta INNER JOIN restaurant_tables rt ON rt.table_id = bta.table_id WHERE bta.booking_id = ? ORDER BY rt.table_number + 0, rt.table_number ASC");
        $assignedTablesStmt->execute([$bookingId]);
        $assignedTables = $assignedTablesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $assignedTableIds = array_map(static fn($row): int => (int) $row['table_id'], $assignedTables);
        $assignedTableNumbers = array_map(static fn($row): string => (string) $row['table_number'], $assignedTables);

        $nextPlacementStatus = !empty($assignedTableIds) ? 'not_placed' : null;
        $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', reservation_card_status = ? WHERE booking_id = ?");
        $updateStmt->execute([$nextPlacementStatus, $bookingId]);

        api_response([
            'success' => true,
            'booking_id' => $bookingId,
            'status' => 'confirmed',
            'table_id' => $assignedTableIds[0] ?? null,
            'table_number' => $assignedTableNumbers[0] ?? null,
            'assigned_table_ids' => $assignedTableIds,
            'assigned_table_numbers' => $assignedTableNumbers,
            'reservation_card_status' => $nextPlacementStatus,
            'reservation_card_status_label' => $nextPlacementStatus !== null ? getBookingPlacementLabel($nextPlacementStatus) : null,
        ]);
    }

    if (preg_match('#^/v1/admin/bookings/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        if ($bookingId < 1) {
            api_error('A valid booking is required.', 422);
        }

        $pdo->beginTransaction();
        try {
            $checkStmt = $pdo->prepare("SELECT booking_id FROM bookings WHERE booking_id = ? LIMIT 1");
            $checkStmt->execute([$bookingId]);
            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                api_error('Booking not found.', 404);
            }

            $pdo->prepare("DELETE FROM booking_table_assignments WHERE booking_id = ?")->execute([$bookingId]);
            $pdo->prepare("UPDATE bookings SET status = 'cancelled', table_id = NULL, reservation_card_status = NULL WHERE booking_id = ?")->execute([$bookingId]);
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Could not cancel booking.', 500);
        }

        api_response(['success' => true, 'booking_id' => $bookingId]);
    }

    if (preg_match('#^/v1/admin/bookings/(\d+)/details$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        $input = api_read_json_body();
        $customerName = trim((string) ($input['customer_name'] ?? ''));
        $requestedStart = api_normalize_time((string) ($input['requested_start_time'] ?? ''));
        $requestedEnd = api_normalize_time((string) ($input['requested_end_time'] ?? ''));
        $assignedStart = api_normalize_time((string) ($input['start_time'] ?? ''));
        $assignedEnd = api_normalize_time((string) ($input['end_time'] ?? ''));
        $guestCount = (int) ($input['number_of_guests'] ?? 0);
        $specialRequest = trim((string) ($input['special_request'] ?? ''));
        $selectedTableId = isset($input['table_id']) && $input['table_id'] !== '' ? (int) $input['table_id'] : null;
        $confirmBooking = !empty($input['confirm_booking']);

        if ($bookingId < 1 || $customerName === '' || !$requestedStart || !$requestedEnd || !$assignedStart || !$assignedEnd || $guestCount < 1) {
            api_error('All required fields must be provided.', 422);
        }

        $requestedTimeError = api_validate_booking_window($requestedStart, $requestedEnd, 30, null);
        if ($requestedTimeError !== null) {
            api_error('Requested time: ' . $requestedTimeError, 422);
        }
        $assignedTimeError = api_validate_booking_window($assignedStart, $assignedEnd, 30, null);
        if ($assignedTimeError !== null) {
            api_error('Assigned time: ' . $assignedTimeError, 422);
        }

        $bookingStmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ?");
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            api_error('Booking not found.', 404);
        }

        $nextAssignedTableIds = $selectedTableId !== null && $selectedTableId > 0 ? [$selectedTableId] : [];
        $assignedTables = [];
        $assignedTableNumbers = [];
        if (!empty($nextAssignedTableIds)) {
            $placeholders = implode(',', array_fill(0, count($nextAssignedTableIds), '?'));
            $assignedTablesStmt = $pdo->prepare("SELECT table_id, table_number, capacity FROM restaurant_tables WHERE table_id IN ($placeholders)");
            $assignedTablesStmt->execute($nextAssignedTableIds);
            $tableRows = $assignedTablesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (count($tableRows) !== count($nextAssignedTableIds)) {
                api_error('Selected table not found.', 404);
            }
            $tableMap = [];
            foreach ($tableRows as $row) {
                $tableMap[(int) $row['table_id']] = $row;
            }
            foreach ($nextAssignedTableIds as $tableId) {
                $assignedTables[] = $tableMap[$tableId];
                $assignedTableNumbers[] = (string) $tableMap[$tableId]['table_number'];
            }
        }

        if (!empty($nextAssignedTableIds)) {
            $conflictStmt = $pdo->prepare("
                SELECT COUNT(*) as conflict_count
                FROM booking_table_assignments bta
                INNER JOIN bookings b ON b.booking_id = bta.booking_id
                WHERE bta.table_id = ?
                  AND b.booking_date = ?
                  AND b.booking_id != ?
                  AND b.status IN ('pending', 'confirmed')
                  AND (
                    (b.start_time < ? AND b.end_time > ?)
                    OR (b.start_time >= ? AND b.start_time < ?)
                    OR (b.end_time > ? AND b.end_time <= ?)
                  )
            ");

            foreach ($nextAssignedTableIds as $tableId) {
                $conflictStmt->execute([
                    $tableId,
                    $booking['booking_date'],
                    $bookingId,
                    $assignedEnd,
                    $assignedStart,
                    $assignedStart,
                    $assignedEnd,
                    $assignedStart,
                    $assignedEnd,
                ]);
                $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);
                if ((int) ($conflict['conflict_count'] ?? 0) > 0) {
                    api_error('Assigned time conflicts with another booking at one of the assigned tables.', 409);
                }
            }
        }

        $nextStatus = $confirmBooking ? 'confirmed' : (string) ($booking['status'] ?? 'pending');
        if (empty($nextAssignedTableIds)) {
            $nextPlacementStatus = null;
        } elseif (in_array($nextStatus, ['pending', 'confirmed'], true)) {
            $nextPlacementStatus = 'not_placed';
        } else {
            $currentPlacementStatus = strtolower((string) ($booking['reservation_card_status'] ?? ''));
            $nextPlacementStatus = in_array($currentPlacementStatus, getBookingPlacementStatuses(), true) ? $currentPlacementStatus : 'not_placed';
        }

        $nextCustomerProfileId = upsertCustomerProfile(
            $pdo,
            $customerName,
            (string) ($booking['customer_email'] ?? ''),
            (string) ($booking['customer_phone'] ?? ''),
            $booking['user_id'] !== null ? (int) $booking['user_id'] : null
        );

        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare("
                UPDATE bookings
                SET customer_name_override = ?,
                    requested_start_time = ?,
                    requested_end_time = ?,
                    start_time = ?,
                    end_time = ?,
                    number_of_guests = ?,
                    special_request = ?,
                    status = ?,
                    reservation_card_status = ?,
                    customer_profile_id = ?
                WHERE booking_id = ?
            ");
            $updateStmt->execute([
                $customerName,
                $requestedStart,
                $requestedEnd,
                $assignedStart,
                $assignedEnd,
                $guestCount,
                $specialRequest !== '' ? $specialRequest : null,
                $nextStatus,
                $nextPlacementStatus,
                $nextCustomerProfileId,
                $bookingId,
            ]);

            $assignedTableIds = syncBookingTableAssignments($pdo, $bookingId, $nextAssignedTableIds);
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Could not update booking details.', 500);
        }

        api_response([
            'success' => true,
            'booking' => [
                'booking_id' => $bookingId,
                'table_id' => !empty($assignedTableIds) ? $assignedTableIds[0] : null,
                'table_number' => $assignedTableNumbers[0] ?? null,
                'assigned_table_ids' => $assignedTableIds,
                'assigned_table_numbers' => $assignedTableNumbers,
                'booking_date' => (string) $booking['booking_date'],
                'start_time' => $assignedStart,
                'end_time' => $assignedEnd,
                'requested_start_time' => $requestedStart,
                'requested_end_time' => $requestedEnd,
                'number_of_guests' => $guestCount,
                'special_request' => $specialRequest !== '' ? $specialRequest : null,
                'status' => $nextStatus,
                'reservation_card_status' => $nextPlacementStatus,
                'customer_name' => $customerName,
                'customer_name_override' => $customerName,
            ],
        ]);
    }

    if (preg_match('#^/v1/admin/bookings/(\d+)/placement$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        $input = api_read_json_body();
        $nextPlacementStatus = strtolower(trim((string) ($input['reservation_card_status'] ?? '')));
        if ($bookingId < 1 || !in_array($nextPlacementStatus, getBookingPlacementStatuses(), true)) {
            api_error('A valid booking and placement status are required.', 422);
        }

        $bookingStmt = $pdo->prepare("
            SELECT b.booking_id, b.status,
                   GROUP_CONCAT(DISTINCT bta.table_id ORDER BY bta.table_id SEPARATOR ',') AS assigned_table_ids
            FROM bookings b
            LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
            WHERE b.booking_id = ?
            GROUP BY b.booking_id
            LIMIT 1
        ");
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            api_error('Booking not found.', 404);
        }

        $bookingStatus = strtolower((string) ($booking['status'] ?? ''));
        if ($bookingStatus === 'cancelled') {
            api_error('Cancelled bookings do not have a placement state.', 409);
        }
        $hasAssignedTables = trim((string) ($booking['assigned_table_ids'] ?? '')) !== '';
        if (!$hasAssignedTables) {
            api_error('Assign a table before updating placement.', 409);
        }

        $updateStmt = $pdo->prepare("UPDATE bookings SET reservation_card_status = ? WHERE booking_id = ?");
        $updateStmt->execute([$nextPlacementStatus, $bookingId]);
        api_response([
            'success' => true,
            'booking_id' => $bookingId,
            'reservation_card_status' => $nextPlacementStatus,
            'reservation_card_status_label' => getBookingPlacementLabel($nextPlacementStatus),
        ]);
    }

    if ($path === '/v1/admin/areas' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);
        $rows = $pdo->query("SELECT area_id, name, display_order, table_number_start, table_number_end, is_active FROM table_areas ORDER BY display_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        api_response(['success' => true, 'areas' => $rows]);
    }

    if ($path === '/v1/admin/areas' && $method === 'POST') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $input = api_read_json_body();
        $name = trim((string) ($input['name'] ?? ''));
        $start = isset($input['table_number_start']) && $input['table_number_start'] !== '' ? (int) $input['table_number_start'] : null;
        $end = isset($input['table_number_end']) && $input['table_number_end'] !== '' ? (int) $input['table_number_end'] : null;

        if ($name === '') {
            api_error('Area name is required.', 422);
        }
        if ($start !== null && $start < 1) {
            api_error('table_number_start must be at least 1.', 422);
        }
        if ($end !== null && $end < 1) {
            api_error('table_number_end must be at least 1.', 422);
        }
        if ($start !== null && $end !== null && $end < $start) {
            api_error('table_number_end must be greater than or equal to start.', 422);
        }

        $exists = $pdo->prepare("SELECT area_id FROM table_areas WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $exists->execute([$name]);
        if ($exists->fetch(PDO::FETCH_ASSOC)) {
            api_error('Area already exists.', 409);
        }

        $displayOrder = (int) $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 10 FROM table_areas")->fetchColumn();
        if ($displayOrder < 10) {
            $displayOrder = 10;
        }

        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare("INSERT INTO table_areas (name, display_order, table_number_start, table_number_end, is_active) VALUES (?, ?, ?, ?, 1)");
            $insertStmt->execute([$name, $displayOrder, $start, $end]);
            $areaId = (int) $pdo->lastInsertId();
            $syncResult = syncAreaNumberedTables($pdo, $areaId, $start, $end);
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Could not create area.', 500);
        }

        api_response([
            'success' => true,
            'area' => [
                'area_id' => $areaId,
                'name' => $name,
                'display_order' => $displayOrder,
                'table_number_start' => $start,
                'table_number_end' => $end,
                'is_active' => 1,
            ],
            'sync' => $syncResult,
        ], 201);
    }

    if (preg_match('#^/v1/admin/areas/(\d+)$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);

        $areaId = (int) $matches[1];
        $input = api_read_json_body();
        $name = trim((string) ($input['name'] ?? ''));
        $tableNumberStart = isset($input['table_number_start']) && $input['table_number_start'] !== '' ? (int) $input['table_number_start'] : null;
        $tableNumberEnd = isset($input['table_number_end']) && $input['table_number_end'] !== '' ? (int) $input['table_number_end'] : null;

        if ($areaId < 1 || $name === '') {
            api_error('A valid area and name are required.', 422);
        }
        if (strlen($name) > 100) {
            api_error('Area name must be 100 characters or fewer.', 422);
        }
        if ($tableNumberStart !== null && $tableNumberStart < 1) {
            api_error('Area start number must be at least 1.', 422);
        }
        if ($tableNumberEnd !== null && $tableNumberEnd < 1) {
            api_error('Area end number must be at least 1.', 422);
        }
        if ($tableNumberStart !== null && $tableNumberEnd !== null && $tableNumberEnd < $tableNumberStart) {
            api_error('Area end number must be greater than or equal to the start number.', 422);
        }

        $areaStmt = $pdo->prepare("SELECT area_id, display_order FROM table_areas WHERE area_id = ? AND is_active = 1");
        $areaStmt->execute([$areaId]);
        $existingArea = $areaStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingArea) {
            api_error('Area not found.', 404);
        }

        $duplicateStmt = $pdo->prepare("SELECT area_id FROM table_areas WHERE LOWER(name) = LOWER(?) AND area_id != ? LIMIT 1");
        $duplicateStmt->execute([$name, $areaId]);
        if ($duplicateStmt->fetchColumn()) {
            api_error('An area with that name already exists.', 409);
        }

        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare("UPDATE table_areas SET name = ?, table_number_start = ?, table_number_end = ? WHERE area_id = ?");
            $updateStmt->execute([$name, $tableNumberStart, $tableNumberEnd, $areaId]);
            $syncResult = syncAreaNumberedTables($pdo, $areaId, $tableNumberStart, $tableNumberEnd);
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Could not update area.', 500);
        }

        api_response([
            'success' => true,
            'area' => [
                'area_id' => $areaId,
                'name' => $name,
                'display_order' => (int) $existingArea['display_order'],
                'table_number_start' => $tableNumberStart,
                'table_number_end' => $tableNumberEnd,
                'is_active' => 1,
            ],
            'area_tables' => $syncResult['area_tables'],
            'deleted_table_ids' => $syncResult['deleted_table_ids'],
            'affected_booking_ids' => $syncResult['affected_booking_ids'],
        ]);
    }

    if (preg_match('#^/v1/admin/areas/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $areaId = (int) $matches[1];
        $input = ['area_id' => $areaId];
        if ($areaId < 1) {
            api_error('Invalid area id.', 422);
        }

        $areaStmt = $pdo->prepare("SELECT area_id, name FROM table_areas WHERE area_id = ? AND is_active = 1");
        $areaStmt->execute([$input['area_id']]);
        $area = $areaStmt->fetch(PDO::FETCH_ASSOC);
        if (!$area) {
            api_error('Area not found.', 404);
        }

        $activeAreaCount = (int) $pdo->query("SELECT COUNT(*) FROM table_areas WHERE is_active = 1")->fetchColumn();
        if ($activeAreaCount <= 1) {
            api_error('At least one area must remain.', 409);
        }

        $tableStmt = $pdo->prepare("SELECT table_id FROM restaurant_tables WHERE area_id = ?");
        $tableStmt->execute([(int) $area['area_id']]);
        $tableIds = array_map('intval', $tableStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $pdo->beginTransaction();
        try {
            if (!empty($tableIds)) {
                $placeholders = implode(',', array_fill(0, count($tableIds), '?'));
                $bookingStmt = $pdo->prepare("SELECT DISTINCT booking_id FROM booking_table_assignments WHERE table_id IN ($placeholders)");
                $bookingStmt->execute($tableIds);
                $bookingIds = array_map('intval', $bookingStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

                $remainingStmt = $pdo->prepare("SELECT table_id FROM booking_table_assignments WHERE booking_id = ? AND table_id NOT IN ($placeholders)");
                foreach ($bookingIds as $bookingId) {
                    $remainingStmt->execute(array_merge([$bookingId], $tableIds));
                    $remainingTableIds = array_map('intval', $remainingStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
                    syncBookingTableAssignments($pdo, $bookingId, $remainingTableIds);
                }

                $pdo->prepare("DELETE FROM booking_table_assignments WHERE table_id IN ($placeholders)")->execute($tableIds);
                $pdo->prepare("DELETE FROM restaurant_tables WHERE table_id IN ($placeholders)")->execute($tableIds);
            }
            $pdo->prepare("DELETE FROM table_areas WHERE area_id = ?")->execute([(int) $area['area_id']]);
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Could not delete area.', 500);
        }

        api_response(['success' => true, 'area_id' => (int) $area['area_id']]);
    }

    if ($path === '/v1/admin/tables' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);
        $areaId = isset($_GET['area_id']) ? (int) $_GET['area_id'] : null;

        if ($areaId !== null && $areaId > 0) {
            $stmt = $pdo->prepare("
                SELECT rt.table_id, rt.table_number, rt.capacity, rt.area_id, rt.sort_order, rt.status, rt.reservable, rt.layout_x, rt.layout_y, rt.table_shape, ta.name AS area_name
                FROM restaurant_tables rt
                LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
                WHERE rt.area_id = ?
                ORDER BY rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
            ");
            $stmt->execute([$areaId]);
        } else {
            $stmt = $pdo->query("
                SELECT rt.table_id, rt.table_number, rt.capacity, rt.area_id, rt.sort_order, rt.status, rt.reservable, rt.layout_x, rt.layout_y, rt.table_shape, ta.name AS area_name
                FROM restaurant_tables rt
                LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
                ORDER BY ta.display_order ASC, rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
            ");
        }

        api_response(['success' => true, 'tables' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($path === '/v1/admin/tables' && $method === 'POST') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);

        $input = api_read_json_body();
        $isAuto = isset($input['auto']) && $input['auto'] === true;
        $requestedAreaId = (int) ($input['area_id'] ?? 0);
        $requestedSortOrder = (int) ($input['sort_order'] ?? 0);
        $requestedReservable = array_key_exists('reservable', $input) ? (int) !empty($input['reservable']) : 1;
        $requestedLayoutX = isset($input['layout_x']) && $input['layout_x'] !== '' ? (int) $input['layout_x'] : null;
        $requestedLayoutY = isset($input['layout_y']) && $input['layout_y'] !== '' ? (int) $input['layout_y'] : null;
        $requestedShape = strtolower(trim((string) ($input['table_shape'] ?? 'auto')));
        $shapeAliases = [
            'auto' => 'auto', 'circle' => 'circle', 'square' => 'square',
            'rect' => 'rect-horizontal', 'rectangle' => 'rect-horizontal', 'rect-h' => 'rect-horizontal',
            'horizontal' => 'rect-horizontal', 'rect-horizontal' => 'rect-horizontal',
            'rect-v' => 'rect-vertical', 'vertical' => 'rect-vertical', 'rect-vertical' => 'rect-vertical',
        ];
        $tableShape = $shapeAliases[$requestedShape] ?? 'auto';

        $defaultAreaStmt = $pdo->query("SELECT area_id FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, name ASC LIMIT 1");
        $defaultAreaId = (int) $defaultAreaStmt->fetchColumn();
        $areaId = $requestedAreaId > 0 ? $requestedAreaId : $defaultAreaId;
        if ($areaId < 1) {
            api_error('A valid area is required.', 422);
        }

        $areaStmt = $pdo->prepare("SELECT area_id, name, display_order, table_number_start, table_number_end FROM table_areas WHERE area_id = ? AND is_active = 1");
        $areaStmt->execute([$areaId]);
        $area = $areaStmt->fetch(PDO::FETCH_ASSOC);
        if (!$area) {
            api_error('Selected area not found.', 404);
        }

        if ($isAuto) {
            $maxStmt = $pdo->prepare("SELECT MAX(CAST(table_number AS UNSIGNED)) FROM restaurant_tables WHERE area_id = ?");
            $maxStmt->execute([$areaId]);
            $maxTableNumber = (int) $maxStmt->fetchColumn();
            $areaStartNumber = $area['table_number_start'] !== null ? (int) $area['table_number_start'] : null;
            $areaEndNumber = $area['table_number_end'] !== null ? (int) $area['table_number_end'] : null;
            $nextNumber = $maxTableNumber > 0 ? $maxTableNumber + 1 : ($areaStartNumber ?: 1);
            if ($areaEndNumber !== null && $nextNumber > $areaEndNumber) {
                api_error('This area has reached its configured end table number.', 409);
            }
            $tableNumber = (string) $nextNumber;
            $capacity = 8;
        } else {
            $tableNumber = trim((string) ($input['table_number'] ?? ''));
            $capacity = (int) ($input['capacity'] ?? 0);
            if ($tableNumber === '' || $capacity < 1) {
                api_error('table_number and positive capacity are required.', 422);
            }
        }

        $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE table_number = ? AND area_id = ?");
        $dupStmt->execute([$tableNumber, $areaId]);
        if ((int) $dupStmt->fetchColumn() > 0) {
            api_error('Table number already exists in this area.', 409);
        }

        if ($requestedSortOrder < 1) {
            $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM restaurant_tables WHERE area_id = ?");
            $sortStmt->execute([$areaId]);
            $requestedSortOrder = (int) $sortStmt->fetchColumn();
            if ($requestedSortOrder < 1) {
                $requestedSortOrder = 10;
            }
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO restaurant_tables (area_id, table_number, capacity, sort_order, status, reservable, layout_x, layout_y, table_shape)
            VALUES (?, ?, ?, ?, 'available', ?, ?, ?, ?)
        ");
        $insertStmt->execute([$areaId, $tableNumber, $capacity, $requestedSortOrder, $requestedReservable, $requestedLayoutX, $requestedLayoutY, $tableShape]);

        api_response([
            'success' => true,
            'table' => [
                'table_id' => (int) $pdo->lastInsertId(),
                'table_number' => $tableNumber,
                'capacity' => $capacity,
                'area_id' => (int) $area['area_id'],
                'area_name' => (string) $area['name'],
                'area_display_order' => (int) $area['display_order'],
                'sort_order' => $requestedSortOrder,
                'reservable' => $requestedReservable,
                'layout_x' => $requestedLayoutX,
                'layout_y' => $requestedLayoutY,
                'table_shape' => $tableShape,
            ],
        ], 201);
    }

    if (preg_match('#^/v1/admin/tables/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $tableId = (int) $matches[1];
        $tableStmt = $pdo->prepare("SELECT table_id, table_number, area_id FROM restaurant_tables WHERE table_id = ?");
        $tableStmt->execute([$tableId]);
        $table = $tableStmt->fetch(PDO::FETCH_ASSOC);
        if (!$table) {
            api_error('Table not found.', 404);
        }

        $bookingCheck = $pdo->prepare("
            SELECT COUNT(*)
            FROM booking_table_assignments bta
            INNER JOIN bookings b ON b.booking_id = bta.booking_id
            WHERE bta.table_id = ?
              AND b.status IN ('pending', 'confirmed')
        ");
        $bookingCheck->execute([$tableId]);
        if ((int) $bookingCheck->fetchColumn() > 0) {
            api_error('Table has active bookings and cannot be deleted.', 409);
        }

        $pdo->prepare("DELETE FROM booking_table_assignments WHERE table_id = ?")->execute([$tableId]);
        $pdo->prepare("DELETE FROM restaurant_tables WHERE table_id = ?")->execute([$tableId]);

        api_response(['success' => true, 'table_id' => $tableId, 'table_number' => (string) $table['table_number']]);
    }

    if (preg_match('#^/v1/admin/tables/(\d+)$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureTableAreasSchema($pdo);

        $tableId = (int) $matches[1];
        $input = api_read_json_body();
        $capacity = (int) ($input['capacity'] ?? 0);
        $areaId = (int) ($input['area_id'] ?? 0);
        $sortOrder = (int) ($input['sort_order'] ?? 0);
        $reservable = array_key_exists('reservable', $input) ? (int) !empty($input['reservable']) : 1;
        $layoutX = isset($input['layout_x']) && $input['layout_x'] !== '' ? (int) $input['layout_x'] : null;
        $layoutY = isset($input['layout_y']) && $input['layout_y'] !== '' ? (int) $input['layout_y'] : null;

        $shapeAliases = [
            'auto' => 'auto', 'circle' => 'circle', 'square' => 'square',
            'rect' => 'rect-horizontal', 'rectangle' => 'rect-horizontal', 'rect-h' => 'rect-horizontal',
            'horizontal' => 'rect-horizontal', 'rect-horizontal' => 'rect-horizontal',
            'rect-v' => 'rect-vertical', 'vertical' => 'rect-vertical', 'rect-vertical' => 'rect-vertical',
        ];
        $requestedShape = strtolower(trim((string) ($input['table_shape'] ?? 'auto')));
        $tableShape = $shapeAliases[$requestedShape] ?? 'auto';

        if ($tableId < 1 || $capacity < 1 || $areaId < 1 || $sortOrder < 1) {
            api_error('Valid table, capacity, area, and sort order are required.', 422);
        }

        $currentTableStmt = $pdo->prepare("SELECT table_id, table_number FROM restaurant_tables WHERE table_id = ?");
        $currentTableStmt->execute([$tableId]);
        $currentTable = $currentTableStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentTable) {
            api_error('Table not found.', 404);
        }

        $tableNumber = isset($input['table_number']) ? trim((string) $input['table_number']) : (string) $currentTable['table_number'];
        if ($tableNumber === '') {
            api_error('Table number is required.', 422);
        }

        $areaStmt = $pdo->prepare("SELECT area_id, name, display_order FROM table_areas WHERE area_id = ? AND is_active = 1");
        $areaStmt->execute([$areaId]);
        $area = $areaStmt->fetch(PDO::FETCH_ASSOC);
        if (!$area) {
            api_error('Area not found.', 404);
        }

        $duplicateStmt = $pdo->prepare("SELECT table_id FROM restaurant_tables WHERE table_number = ? AND area_id = ? AND table_id != ? LIMIT 1");
        $duplicateStmt->execute([$tableNumber, $areaId, $tableId]);
        if ($duplicateStmt->fetchColumn()) {
            api_error('Table number already exists in this area.', 409);
        }

        $stmt = $pdo->prepare("UPDATE restaurant_tables SET table_number = ?, capacity = ?, area_id = ?, sort_order = ?, reservable = ?, layout_x = ?, layout_y = ?, table_shape = ? WHERE table_id = ?");
        $stmt->execute([$tableNumber, $capacity, $areaId, $sortOrder, $reservable, $layoutX, $layoutY, $tableShape, $tableId]);

        $tableStmt = $pdo->prepare("SELECT rt.table_number, rt.capacity, rt.area_id, rt.sort_order, rt.reservable, rt.layout_x, rt.layout_y, rt.table_shape, ta.name AS area_name, ta.display_order AS area_display_order FROM restaurant_tables rt LEFT JOIN table_areas ta ON ta.area_id = rt.area_id WHERE rt.table_id = ?");
        $tableStmt->execute([$tableId]);
        $table = $tableStmt->fetch(PDO::FETCH_ASSOC);
        if (!$table) {
            api_error('Table not found.', 404);
        }

        api_response([
            'success' => true,
            'table' => [
                'table_id' => $tableId,
                'table_number' => (string) $table['table_number'],
                'capacity' => (int) $table['capacity'],
                'area_id' => (int) $table['area_id'],
                'area_name' => (string) $table['area_name'],
                'area_display_order' => (int) $table['area_display_order'],
                'sort_order' => (int) $table['sort_order'],
                'reservable' => (int) $table['reservable'],
                'layout_x' => $table['layout_x'] !== null ? (int) $table['layout_x'] : null,
                'layout_y' => $table['layout_y'] !== null ? (int) $table['layout_y'] : null,
                'table_shape' => $shapeAliases[strtolower((string) ($table['table_shape'] ?: 'auto'))] ?? 'auto',
            ],
        ]);
    }

    if ($path === '/v1/admin/menu-items' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        $stmt = $pdo->query("
            SELECT id, name, description, price, category, image, dietary_info, is_available
            FROM menu_items
            ORDER BY category ASC, name ASC
        ");
        api_response(['success' => true, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($path === '/v1/admin/menu-items' && $method === 'POST') {
        api_require_user($pdo, 'admin');
        $input = api_read_json_body();
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $price = (float) ($input['price'] ?? 0);
        $category = trim((string) ($input['category'] ?? ''));
        $image = trim((string) ($input['image'] ?? ''));
        $dietaryInfo = trim((string) ($input['dietary_info'] ?? ''));
        $isAvailable = !empty($input['is_available']) ? 1 : 0;

        if ($name === '' || $category === '' || $price <= 0) {
            api_error('name, category, and positive price are required.', 422);
        }

        $stmt = $pdo->prepare("
            INSERT INTO menu_items (name, description, price, category, image, dietary_info, is_available)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $description !== '' ? $description : null,
            $price,
            $category,
            $image !== '' ? $image : null,
            $dietaryInfo !== '' ? $dietaryInfo : null,
            $isAvailable,
        ]);

        api_response(['success' => true, 'id' => (int) $pdo->lastInsertId()], 201);
    }

    if (preg_match('#^/v1/admin/menu-items/(\d+)$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        $itemId = (int) $matches[1];
        $input = api_read_json_body();
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $price = (float) ($input['price'] ?? 0);
        $category = trim((string) ($input['category'] ?? ''));
        $image = trim((string) ($input['image'] ?? ''));
        $dietaryInfo = trim((string) ($input['dietary_info'] ?? ''));
        $isAvailable = !empty($input['is_available']) ? 1 : 0;

        if ($name === '' || $category === '' || $price <= 0) {
            api_error('name, category, and positive price are required.', 422);
        }

        $stmt = $pdo->prepare("
            UPDATE menu_items
            SET name = ?, description = ?, price = ?, category = ?, image = ?, dietary_info = ?, is_available = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name,
            $description !== '' ? $description : null,
            $price,
            $category,
            $image !== '' ? $image : null,
            $dietaryInfo !== '' ? $dietaryInfo : null,
            $isAvailable,
            $itemId,
        ]);

        $existsStmt = $pdo->prepare("SELECT id FROM menu_items WHERE id = ? LIMIT 1");
        $existsStmt->execute([$itemId]);
        if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
            api_error('Menu item not found.', 404);
        }

        api_response(['success' => true, 'id' => $itemId]);
    }

    if (preg_match('#^/v1/admin/menu-items/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        api_require_user($pdo, 'admin');
        $itemId = (int) $matches[1];
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$itemId]);
        if ($stmt->rowCount() === 0) {
            api_error('Menu item not found.', 404);
        }
        api_response(['success' => true, 'id' => $itemId]);
    }

    if ($path === '/v1/admin/users' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureUserAccountSchema($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $stmt = $pdo->query("
            SELECT
                u.user_id,
                u.name,
                u.email,
                u.phone,
                u.role,
                COALESCE(u.is_disabled, 0) AS is_disabled,
                u.created_at,
                COUNT(b.booking_id) AS booking_count,
                MAX(b.booking_date) AS last_booking_date
            FROM users u
            LEFT JOIN bookings b ON b.user_id = u.user_id
            WHERE (
                u.role = 'admin'
                OR (
                    u.role = 'customer'
                    AND u.email NOT LIKE '%@admin-booking.local'
                    AND u.email NOT LIKE 'guest-%@local.dinemate'
                )
            )
            GROUP BY u.user_id, u.name, u.email, u.phone, u.role, u.is_disabled, u.created_at
            ORDER BY CASE WHEN u.role = 'admin' THEN 0 ELSE 1 END, u.created_at DESC, u.user_id DESC
        ");
        api_response(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($path === '/v1/admin/users' && $method === 'POST') {
        api_require_user($pdo, 'admin');
        ensureUserAccountSchema($pdo);

        $payload = api_validate_admin_user_payload(api_read_json_body(), true);
        $existsStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $existsStmt->execute([$payload['email']]);
        if ($existsStmt->fetchColumn()) {
            api_error('Email already exists.', 409);
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role, is_disabled, created_at)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $insertStmt->execute([
            $payload['name'],
            $payload['email'],
            $payload['phone'],
            password_hash($payload['password'], PASSWORD_BCRYPT),
            $payload['role'],
        ]);

        api_response(['success' => true, 'user_id' => (int) $pdo->lastInsertId()], 201);
    }

    if (preg_match('#^/v1/admin/users/(\d+)$#', $path, $matches) && $method === 'PATCH') {
        $admin = api_require_user($pdo, 'admin');
        ensureUserAccountSchema($pdo);

        $userId = (int) $matches[1];
        $existingStmt = $pdo->prepare("SELECT user_id, name, email, phone, role, is_disabled FROM users WHERE user_id = ? LIMIT 1");
        $existingStmt->execute([$userId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            api_error('User not found.', 404);
        }

        $input = api_read_json_body();
        $nextName = array_key_exists('name', $input) ? trim((string) $input['name']) : (string) $existing['name'];
        $nextEmail = array_key_exists('email', $input) ? strtolower(trim((string) $input['email'])) : (string) $existing['email'];
        $nextPhone = array_key_exists('phone', $input) ? trim((string) $input['phone']) : (string) ($existing['phone'] ?? '');
        $nextRole = array_key_exists('role', $input) ? strtolower(trim((string) $input['role'])) : (string) $existing['role'];
        $password = array_key_exists('password', $input) ? (string) $input['password'] : '';
        $nextIsDisabled = array_key_exists('is_disabled', $input) ? (int) !empty($input['is_disabled']) : (int) ($existing['is_disabled'] ?? 0);

        $validated = api_validate_admin_user_payload([
            'name' => $nextName,
            'email' => $nextEmail,
            'phone' => $nextPhone,
            'role' => $nextRole,
            'password' => $password,
        ], false);

        $isSelf = ((int) $admin['user_id']) === $userId;
        if ($isSelf && $validated['role'] !== 'admin') {
            api_error('You cannot remove your own admin privileges.', 409);
        }
        if ($isSelf && $nextIsDisabled === 1) {
            api_error('You cannot disable your own account.', 409);
        }

        if ($validated['email'] !== strtolower((string) $existing['email'])) {
            $dupEmailStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
            $dupEmailStmt->execute([$validated['email'], $userId]);
            if ($dupEmailStmt->fetchColumn()) {
                api_error('Email already exists for another user.', 409);
            }
        }

        if ((string) $existing['role'] === 'admin' && $validated['role'] !== 'admin') {
            $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ((int) $adminCountStmt->fetchColumn() <= 1) {
                api_error('Cannot demote the last admin.', 409);
            }
        }

        if ((string) $existing['role'] === 'admin' && $nextIsDisabled === 1 && (int) ($existing['is_disabled'] ?? 0) !== 1) {
            $activeAdminStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND COALESCE(is_disabled, 0) = 0");
            if ((int) $activeAdminStmt->fetchColumn() <= 1) {
                api_error('Cannot disable the last active admin.', 409);
            }
        }

        if ($password !== '') {
            $updateStmt = $pdo->prepare("
                UPDATE users
                SET name = ?, email = ?, phone = ?, role = ?, is_disabled = ?, password = ?
                WHERE user_id = ?
            ");
            $updateStmt->execute([
                $validated['name'],
                $validated['email'],
                $validated['phone'],
                $validated['role'],
                $nextIsDisabled,
                password_hash($password, PASSWORD_BCRYPT),
                $userId,
            ]);
        } else {
            $updateStmt = $pdo->prepare("
                UPDATE users
                SET name = ?, email = ?, phone = ?, role = ?, is_disabled = ?
                WHERE user_id = ?
            ");
            $updateStmt->execute([
                $validated['name'],
                $validated['email'],
                $validated['phone'],
                $validated['role'],
                $nextIsDisabled,
                $userId,
            ]);
        }

        api_response([
            'success' => true,
            'user_id' => $userId,
            'role' => $validated['role'],
            'is_disabled' => $nextIsDisabled,
        ]);
    }

    if (preg_match('#^/v1/admin/users/(\d+)$#', $path, $matches) && $method === 'DELETE') {
        $admin = api_require_user($pdo, 'admin');
        ensureUserAccountSchema($pdo);

        $userId = (int) $matches[1];
        if ((int) $admin['user_id'] === $userId) {
            api_error('You cannot delete your own account.', 409);
        }

        $targetStmt = $pdo->prepare("SELECT user_id, role FROM users WHERE user_id = ? LIMIT 1");
        $targetStmt->execute([$userId]);
        $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            api_error('User not found.', 404);
        }

        if ((string) $target['role'] === 'admin') {
            $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ((int) $adminCountStmt->fetchColumn() <= 1) {
                api_error('Cannot delete the last admin.', 409);
            }
        }

        $bookingStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
        $bookingStmt->execute([$userId]);
        $bookingCount = (int) $bookingStmt->fetchColumn();
        if ($bookingCount > 0) {
            api_error("Cannot delete user with {$bookingCount} existing booking(s).", 409);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $deleteStmt->execute([$userId]);
        api_response(['success' => true, 'user_id' => $userId]);
    }

    if ($path === '/v1/admin/analytics/overview' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);
        ensureTableAreasSchema($pdo);

        $dateFrom = trim((string) ($_GET['date_from'] ?? date('Y-m-01')));
        $dateTo = trim((string) ($_GET['date_to'] ?? date('Y-m-d')));
        if (!api_validate_date($dateFrom) || !api_validate_date($dateTo)) {
            api_error('date_from and date_to must be YYYY-MM-DD.', 422);
        }
        $areaIdRaw = trim((string) ($_GET['area_id'] ?? 'all'));
        $areaId = ($areaIdRaw === '' || strtolower($areaIdRaw) === 'all') ? null : (int) $areaIdRaw;
        if ($areaId !== null && $areaId < 1) {
            api_error('area_id must be "all" or a valid area id.', 422);
        }

        $areaFilter = '';
        $areaParams = [];
        if ($areaId !== null) {
            $areaFilter = "
                AND EXISTS (
                    SELECT 1
                    FROM booking_table_assignments bta2
                    INNER JOIN restaurant_tables rt2 ON rt2.table_id = bta2.table_id
                    WHERE bta2.booking_id = b.booking_id
                      AND rt2.area_id = ?
                )
            ";
            $areaParams[] = $areaId;
        }

        $summaryStmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_bookings,
                SUM(number_of_guests) AS total_guests,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS no_show_count
            FROM bookings b
            WHERE booking_date BETWEEN ? AND ?
            {$areaFilter}
        ");
        $summaryStmt->execute(array_merge([$dateFrom, $dateTo], $areaParams));
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $topTablesAreaFilter = $areaId !== null ? "AND rt.area_id = ?" : "";
        $topTablesStmt = $pdo->prepare("
            SELECT rt.table_id, rt.table_number, COUNT(*) AS booking_count
            FROM booking_table_assignments bta
            INNER JOIN bookings b ON b.booking_id = bta.booking_id
            INNER JOIN restaurant_tables rt ON rt.table_id = bta.table_id
            WHERE b.booking_date BETWEEN ? AND ?
            {$topTablesAreaFilter}
            GROUP BY rt.table_id, rt.table_number
            ORDER BY booking_count DESC, rt.table_number + 0
            LIMIT 10
        ");
        $topTablesStmt->execute(array_merge([$dateFrom, $dateTo], $areaParams));

        $peakHoursStmt = $pdo->prepare("
            SELECT TIME_FORMAT(start_time, '%H:00') AS hour_slot, COUNT(*) AS booking_count, SUM(number_of_guests) AS guests
            FROM bookings b
            WHERE booking_date BETWEEN ? AND ?
            {$areaFilter}
            GROUP BY TIME_FORMAT(start_time, '%H:00')
            ORDER BY booking_count DESC
            LIMIT 8
        ");
        $peakHoursStmt->execute(array_merge([$dateFrom, $dateTo], $areaParams));

        api_response([
            'success' => true,
            'summary' => $summary,
            'top_tables' => $topTablesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'peak_hours' => $peakHoursStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ]);
    }

    if ($path === '/v1/admin/customer-history' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureCustomerProfilesSchema($pdo);
        ensureBookingRequestColumns($pdo);

        $limit = max(10, min(500, (int) ($_GET['limit'] ?? 100)));
        $search = trim((string) ($_GET['search'] ?? ''));

        if ($search !== '') {
            $stmt = $pdo->prepare("
                SELECT cp.customer_profile_id, cp.name, cp.email, cp.phone, cp.created_at,
                       COUNT(b.booking_id) AS total_bookings,
                       MAX(b.booking_date) AS last_booking_date
                FROM customer_profiles cp
                LEFT JOIN bookings b ON b.customer_profile_id = cp.customer_profile_id
                WHERE cp.name LIKE ? OR cp.email LIKE ? OR cp.phone LIKE ?
                GROUP BY cp.customer_profile_id
                ORDER BY last_booking_date DESC, cp.name ASC
                LIMIT {$limit}
            ");
            $query = '%' . $search . '%';
            $stmt->execute([$query, $query, $query]);
        } else {
            $stmt = $pdo->query("
                SELECT cp.customer_profile_id, cp.name, cp.email, cp.phone, cp.created_at,
                       COUNT(b.booking_id) AS total_bookings,
                       MAX(b.booking_date) AS last_booking_date
                FROM customer_profiles cp
                LEFT JOIN bookings b ON b.customer_profile_id = cp.customer_profile_id
                GROUP BY cp.customer_profile_id
                ORDER BY last_booking_date DESC, cp.name ASC
                LIMIT {$limit}
            ");
        }

        api_response(['success' => true, 'customers' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if ($path === '/v1/admin/customer-history/linkable-users' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureUserAccountSchema($pdo);

        $stmt = $pdo->query("
            SELECT user_id, name, email
            FROM users
            WHERE role = 'customer'
              AND email NOT LIKE '%@admin-booking.local'
              AND email NOT LIKE 'guest-%@local.dinemate'
            ORDER BY name ASC, email ASC, user_id ASC
        ");
        api_response(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }

    if (preg_match('#^/v1/admin/customer-history/(\d+)$#', $path, $matches) && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureCustomerProfilesSchema($pdo);
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $profileId = (int) $matches[1];
        if ($profileId < 1) {
            api_error('A valid customer profile is required.', 422);
        }

        $profileStmt = $pdo->prepare("
            SELECT
                cp.customer_profile_id,
                cp.name,
                cp.email,
                cp.phone,
                cp.notes,
                cp.linked_user_id,
                linked_user.name AS linked_user_name,
                linked_user.email AS linked_user_email
            FROM customer_profiles cp
            LEFT JOIN users linked_user ON cp.linked_user_id = linked_user.user_id
            WHERE cp.customer_profile_id = ?
            LIMIT 1
        ");
        $profileStmt->execute([$profileId]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) {
            api_error('Customer profile not found.', 404);
        }

        $bookingsStmt = $pdo->prepare("
            SELECT
                b.booking_id,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.number_of_guests,
                b.status,
                b.booking_source,
                b.reservation_card_status,
                creator.name AS created_by_name,
                GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
            FROM bookings b
            LEFT JOIN users creator ON b.created_by_user_id = creator.user_id
            LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
            LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
            WHERE b.customer_profile_id = ?
            GROUP BY
                b.booking_id,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.number_of_guests,
                b.status,
                b.booking_source,
                b.reservation_card_status,
                creator.name
            ORDER BY b.booking_date DESC, b.start_time DESC, b.booking_id DESC
        ");
        $bookingsStmt->execute([$profileId]);

        api_response([
            'success' => true,
            'profile' => $profile,
            'bookings' => $bookingsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ]);
    }

    if (preg_match('#^/v1/admin/customer-history/(\d+)/link-account$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureCustomerProfilesSchema($pdo);
        ensureUserAccountSchema($pdo);

        $profileId = (int) $matches[1];
        $input = api_read_json_body();
        $linkUserId = (int) ($input['link_user_id'] ?? 0);
        if ($profileId < 1 || $linkUserId < 1) {
            api_error('A valid profile and customer account are required.', 422);
        }

        $profileStmt = $pdo->prepare("SELECT customer_profile_id FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
        $profileStmt->execute([$profileId]);
        if (!$profileStmt->fetch(PDO::FETCH_ASSOC)) {
            api_error('Customer profile not found.', 404);
        }

        $accountStmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE user_id = ?
              AND role = 'customer'
              AND email NOT LIKE '%@admin-booking.local'
              AND email NOT LIKE 'guest-%@local.dinemate'
            LIMIT 1
        ");
        $accountStmt->execute([$linkUserId]);
        if (!$accountStmt->fetch(PDO::FETCH_ASSOC)) {
            api_error('That account is not a valid registered customer.', 404);
        }

        $conflictStmt = $pdo->prepare("
            SELECT customer_profile_id
            FROM customer_profiles
            WHERE linked_user_id = ? AND customer_profile_id != ?
            LIMIT 1
        ");
        $conflictStmt->execute([$linkUserId, $profileId]);
        if ($conflictStmt->fetchColumn()) {
            api_error('That account is already linked to another customer profile.', 409);
        }

        $updateStmt = $pdo->prepare("UPDATE customer_profiles SET linked_user_id = ? WHERE customer_profile_id = ?");
        $updateStmt->execute([$linkUserId, $profileId]);
        api_response(['success' => true, 'customer_profile_id' => $profileId, 'linked_user_id' => $linkUserId]);
    }

    if (preg_match('#^/v1/admin/customer-history/(\d+)/unlink-account$#', $path, $matches) && $method === 'POST') {
        api_require_user($pdo, 'admin');
        ensureCustomerProfilesSchema($pdo);

        $profileId = (int) $matches[1];
        if ($profileId < 1) {
            api_error('A valid customer profile is required.', 422);
        }
        $updateStmt = $pdo->prepare("UPDATE customer_profiles SET linked_user_id = NULL WHERE customer_profile_id = ?");
        $updateStmt->execute([$profileId]);
        if ($updateStmt->rowCount() < 1) {
            $existsStmt = $pdo->prepare("SELECT customer_profile_id FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
            $existsStmt->execute([$profileId]);
            if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
                api_error('Customer profile not found.', 404);
            }
        }

        api_response(['success' => true, 'customer_profile_id' => $profileId, 'linked_user_id' => null]);
    }

    if (preg_match('#^/v1/admin/customer-history/(\d+)/notes$#', $path, $matches) && $method === 'PATCH') {
        api_require_user($pdo, 'admin');
        ensureCustomerProfilesSchema($pdo);

        $profileId = (int) $matches[1];
        $notes = trim((string) (api_read_json_body()['notes'] ?? ''));
        if ($profileId < 1) {
            api_error('A valid customer profile is required.', 422);
        }

        $updateStmt = $pdo->prepare("UPDATE customer_profiles SET notes = ? WHERE customer_profile_id = ?");
        $updateStmt->execute([$notes !== '' ? $notes : null, $profileId]);
        if ($updateStmt->rowCount() < 1) {
            $existsStmt = $pdo->prepare("SELECT customer_profile_id FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
            $existsStmt->execute([$profileId]);
            if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
                api_error('Customer profile not found.', 404);
            }
        }

        api_response(['success' => true, 'customer_profile_id' => $profileId, 'notes' => $notes !== '' ? $notes : null]);
    }

    if (preg_match('#^/v1/admin/customer-history/(\d+)/merge$#', $path, $matches) && $method === 'POST') {
        api_require_user($pdo, 'admin');
        ensureCustomerProfilesSchema($pdo);
        ensureBookingRequestColumns($pdo);

        $sourceProfileId = (int) $matches[1];
        $input = api_read_json_body();
        $targetProfileId = (int) ($input['target_profile_id'] ?? 0);
        if ($sourceProfileId < 1 || $targetProfileId < 1 || $sourceProfileId === $targetProfileId) {
            api_error('Source and target profiles must be valid and different.', 422);
        }

        $profileLookupStmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
        $profileLookupStmt->execute([$sourceProfileId]);
        $sourceProfile = $profileLookupStmt->fetch(PDO::FETCH_ASSOC);
        $profileLookupStmt->execute([$targetProfileId]);
        $targetProfile = $profileLookupStmt->fetch(PDO::FETCH_ASSOC);
        if (!$sourceProfile || !$targetProfile) {
            api_error('One of the customer profiles could not be found.', 404);
        }

        if (!empty($sourceProfile['linked_user_id']) && !empty($targetProfile['linked_user_id']) && (int) $sourceProfile['linked_user_id'] !== (int) $targetProfile['linked_user_id']) {
            api_error('Cannot merge profiles linked to different registered accounts.', 409);
        }

        $mergedLinkedUserId = !empty($targetProfile['linked_user_id'])
            ? (int) $targetProfile['linked_user_id']
            : (!empty($sourceProfile['linked_user_id']) ? (int) $sourceProfile['linked_user_id'] : null);
        $mergedName = trim((string) ($targetProfile['name'] ?? '')) !== '' ? (string) $targetProfile['name'] : (string) ($sourceProfile['name'] ?? 'Guest');
        $mergedEmail = trim((string) ($targetProfile['email'] ?? '')) !== '' ? (string) $targetProfile['email'] : (string) ($sourceProfile['email'] ?? '');
        $mergedPhone = trim((string) ($targetProfile['phone'] ?? '')) !== '' ? (string) $targetProfile['phone'] : (string) ($sourceProfile['phone'] ?? '');
        $sourceNotes = trim((string) ($sourceProfile['notes'] ?? ''));
        $targetNotes = trim((string) ($targetProfile['notes'] ?? ''));
        $mergedNotes = $targetNotes;
        if ($sourceNotes !== '') {
            $mergedNotes = $targetNotes !== '' ? ($targetNotes . "\n\n" . $sourceNotes) : $sourceNotes;
        }

        $pdo->beginTransaction();
        try {
            $bookingUpdateStmt = $pdo->prepare("UPDATE bookings SET customer_profile_id = ? WHERE customer_profile_id = ?");
            $bookingUpdateStmt->execute([$targetProfileId, $sourceProfileId]);

            $profileUpdateStmt = $pdo->prepare("
                UPDATE customer_profiles
                SET linked_user_id = ?,
                    name = ?,
                    email = ?,
                    phone = ?,
                    notes = ?,
                    normalized_email = ?,
                    normalized_phone = ?
                WHERE customer_profile_id = ?
            ");
            $profileUpdateStmt->execute([
                $mergedLinkedUserId,
                $mergedName !== '' ? $mergedName : 'Guest',
                $mergedEmail !== '' ? $mergedEmail : null,
                $mergedPhone !== '' ? $mergedPhone : null,
                $mergedNotes !== '' ? $mergedNotes : null,
                $mergedEmail !== '' ? normalizeCustomerProfileEmail($mergedEmail) : null,
                $mergedPhone !== '' ? normalizeCustomerProfilePhone($mergedPhone) : null,
                $targetProfileId,
            ]);

            $deleteProfileStmt = $pdo->prepare("DELETE FROM customer_profiles WHERE customer_profile_id = ?");
            $deleteProfileStmt->execute([$sourceProfileId]);
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Could not merge customer profiles.', 500);
        }

        api_response([
            'success' => true,
            'source_profile_id' => $sourceProfileId,
            'target_profile_id' => $targetProfileId,
        ]);
    }

    if ($path === '/v1/admin/bookings' && $method === 'GET') {
        api_require_user($pdo, 'admin');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $status = trim((string) ($_GET['status'] ?? ''));
        $date = trim((string) ($_GET['date'] ?? ''));

        $conditions = [];
        $params = [];

        if ($status !== '') {
            $conditions[] = 'b.status = ?';
            $params[] = $status;
        }

        if ($date !== '') {
            if (!api_validate_date($date)) {
                api_error('date must be YYYY-MM-DD', 422);
            }
            $conditions[] = 'b.booking_date = ?';
            $params[] = $date;
        }

        $where = !empty($conditions) ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $sql = "
            SELECT b.*,
                   COALESCE(b.customer_name_override, b.customer_name, u.name, 'Guest') AS customer_name,
                   GROUP_CONCAT(DISTINCT bta.table_id ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ',') AS assigned_table_ids,
                   GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ',') AS assigned_table_numbers
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.user_id
            LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
            LEFT JOIN restaurant_tables rt ON rt.table_id = bta.table_id
            {$where}
            GROUP BY b.booking_id
            ORDER BY b.booking_date DESC, COALESCE(b.start_time, '00:00:00') DESC, b.booking_id DESC
            LIMIT 500
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['assigned_table_ids'] = !empty($row['assigned_table_ids']) ? array_map('intval', explode(',', (string) $row['assigned_table_ids'])) : [];
            $row['assigned_table_numbers'] = !empty($row['assigned_table_numbers']) ? array_map('trim', explode(',', (string) $row['assigned_table_numbers'])) : [];
        }
        unset($row);

        api_response(['success' => true, 'bookings' => $rows]);
    }

    return false;
}
