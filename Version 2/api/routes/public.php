<?php
declare(strict_types=1);

function api_create_booking(PDO $pdo, array $input, ?array $actor = null, array $options = []): array
{
    ensureBookingRequestColumns($pdo);
    ensureBookingTableAssignmentsTable($pdo);

    $requireContactDetails = !array_key_exists('require_contact_details', $options) || !empty($options['require_contact_details']);
    $customerName = trim((string) ($input['customer_name'] ?? ($actor['name'] ?? '')));
    $customerEmail = trim((string) ($input['customer_email'] ?? ($actor['email'] ?? '')));
    $customerPhone = trim((string) ($input['customer_phone'] ?? ($actor['phone'] ?? '')));
    $bookingDate = trim((string) ($input['booking_date'] ?? ''));
    $startTimeRaw = trim((string) ($input['start_time'] ?? ''));
    $guests = (int) ($input['number_of_guests'] ?? 0);
    $specialRequest = trim((string) ($input['special_request'] ?? ''));

    if ($customerName === '' || $bookingDate === '' || $startTimeRaw === '' || $guests < 1) {
        api_error('All booking fields are required.', 422);
    }

    if ($requireContactDetails && ($customerEmail === '' || $customerPhone === '')) {
        api_error('All booking fields are required.', 422);
    }

    if (strlen($customerName) < 2 || strlen($customerName) > 100) {
        api_error('Name must be between 2 and 100 characters.', 422);
    }

    if (!api_validate_date($bookingDate)) {
        api_error('Booking date must be in YYYY-MM-DD format.', 422);
    }

    if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        api_error('Invalid email address.', 422);
    }

    if (strlen($customerEmail) > 100) {
        api_error('Email address is too long.', 422);
    }

    if ($customerPhone !== '' && !preg_match("/^[0-9\\s\\-\\(\\)\\+]+$/", $customerPhone)) {
        api_error('Invalid phone number.', 422);
    }

    $phoneDigits = preg_replace('/\D+/', '', $customerPhone);
    if ($customerPhone !== '' && (strlen((string) $phoneDigits) < 6 || strlen($customerPhone) > 30)) {
        api_error('Phone number must include at least 6 digits and be no longer than 30 chars.', 422);
    }

    $startTime = api_normalize_time($startTimeRaw);
    if ($startTime === null) {
        api_error('Invalid start time.', 422);
    }

    $endTime = date('H:i:s', strtotime($startTime . ' +60 minutes'));
    $windowError = api_validate_booking_window($startTime, $endTime);
    if ($windowError !== null) {
        api_error($windowError, 422);
    }

    if (!api_check_capacity($pdo, $guests)) {
        api_error('No available table can accommodate this party size right now.', 422);
    }

    $userId = array_key_exists('user_id', $options)
        ? (($options['user_id'] !== null) ? (int) $options['user_id'] : null)
        : ($actor !== null ? (int) $actor['user_id'] : null);
    $bookingSource = isset($options['booking_source'])
        ? (string) $options['booking_source']
        : ($actor !== null ? 'customer_account' : 'guest_web');
    $createdByUserId = array_key_exists('created_by_user_id', $options)
        ? (($options['created_by_user_id'] !== null) ? (int) $options['created_by_user_id'] : null)
        : ($actor !== null ? (int) $actor['user_id'] : null);
    $customerProfileId = upsertCustomerProfile(
        $pdo,
        $customerName,
        $customerEmail !== '' ? $customerEmail : null,
        $customerPhone !== '' ? $customerPhone : null,
        $userId
    );
    $token = generateGuestAccessToken();

    $stmt = $pdo->prepare("
        INSERT INTO bookings
            (user_id, customer_profile_id, customer_name, customer_phone, customer_email, guest_access_token, table_id, booking_date, start_time, end_time, requested_start_time, requested_end_time, number_of_guests, special_request, status, booking_source, created_by_user_id)
        VALUES
            (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([
        $userId,
        $customerProfileId,
        $customerName,
        $customerPhone !== '' ? $customerPhone : null,
        $customerEmail !== '' ? $customerEmail : null,
        $token,
        $bookingDate,
        $startTime,
        $endTime,
        $startTime,
        $endTime,
        $guests,
        $specialRequest !== '' ? $specialRequest : null,
        $bookingSource,
        $createdByUserId,
    ]);

    $bookingId = (int) $pdo->lastInsertId();

    return [
        'booking_id' => $bookingId,
        'booking_date' => $bookingDate,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'number_of_guests' => $guests,
        'status' => 'pending',
        'status_label' => getBookingStatusLabel('pending'),
        'customer_name' => $customerName,
        'customer_email' => $customerEmail !== '' ? $customerEmail : null,
        'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
        'special_request' => $specialRequest !== '' ? $specialRequest : null,
        'guest_access_token' => $token,
        'booking_source' => $bookingSource,
    ];
}

function api_route_public(PDO $pdo, string $method, string $path): bool
{
    if ($path === '/v1/health' && $method === 'GET') {
        api_response([
            'success' => true,
            'service' => 'dinemate-api',
            'version' => 'v1',
            'timestamp' => gmdate('c'),
        ]);
    }

    if ($path === '/v1/menu' && $method === 'GET') {
        $stmt = $pdo->query("SELECT id, name, description, price, category, image, dietary_info, is_available FROM menu_items WHERE is_available = 1 ORDER BY category ASC, name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        api_response([
            'success' => true,
            'items' => array_map(static function (array $row): array {
                return [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'description' => $row['description'] !== null ? (string) $row['description'] : null,
                    'price' => (float) $row['price'],
                    'category' => (string) $row['category'],
                    'image' => $row['image'] !== null ? (string) $row['image'] : null,
                    'dietary_info' => $row['dietary_info'] !== null ? (string) $row['dietary_info'] : null,
                ];
            }, $rows),
        ]);
    }

    if ($path === '/v1/public/areas' && $method === 'GET') {
        ensureTableAreasSchema($pdo);
        $stmt = $pdo->query("SELECT area_id, name, display_order, table_number_start, table_number_end FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        api_response(['success' => true, 'areas' => $areas]);
    }

    if ($path === '/v1/bookings' && $method === 'POST') {
        $actor = api_get_current_user($pdo);
        if ($actor !== null && ($actor['role'] ?? '') !== 'customer') {
            $actor = null;
        }

        $booking = api_create_booking($pdo, api_read_json_body(), $actor);
        api_response(['success' => true, 'booking' => $booking], 201);
    }

    if (preg_match('#^/v1/bookings/(\d+)/confirmation$#', $path, $matches) && $method === 'GET') {
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        $guestToken = trim((string) ($_GET['token'] ?? ''));
        if ($bookingId < 1) {
            api_error('A valid booking id is required.', 422);
        }

        $actor = api_get_current_user($pdo);
        $isLoggedInCustomer = $actor !== null && ($actor['role'] ?? '') === 'customer';

        if ($isLoggedInCustomer) {
            $stmt = $pdo->prepare("
                SELECT b.*,
                       GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
                FROM bookings b
                LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
                LEFT JOIN restaurant_tables rt ON rt.table_id = bta.table_id
                WHERE b.booking_id = ?
                  AND (b.user_id = ? OR b.guest_access_token = ?)
                GROUP BY b.booking_id
                LIMIT 1
            ");
            $stmt->execute([$bookingId, (int) $actor['user_id'], $guestToken]);
        } else {
            if ($guestToken === '') {
                api_error('A valid booking token is required.', 422);
            }
            $stmt = $pdo->prepare("
                SELECT b.*,
                       GROUP_CONCAT(DISTINCT rt.table_number ORDER BY rt.table_number + 0, rt.table_number SEPARATOR ', ') AS assigned_table_numbers
                FROM bookings b
                LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
                LEFT JOIN restaurant_tables rt ON rt.table_id = bta.table_id
                WHERE b.booking_id = ? AND b.guest_access_token = ?
                GROUP BY b.booking_id
                LIMIT 1
            ");
            $stmt->execute([$bookingId, $guestToken]);
        }

        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            api_error('Booking confirmation not found.', 404);
        }

        $response = api_shape_booking_payload($booking);
        $response['booking_source_label'] = getBookingSourceLabel((string) ($booking['booking_source'] ?? ''));
        $response['reservation_card_status'] = $booking['reservation_card_status'] ?? null;
        $response['reservation_card_status_label'] = getBookingPlacementLabel((string) ($booking['reservation_card_status'] ?? 'not_placed'));
        $response['assigned_table_numbers'] = trim((string) ($booking['assigned_table_numbers'] ?? ''));
        $response['table_summary'] = $response['assigned_table_numbers'] !== '' ? ('Table ' . $response['assigned_table_numbers']) : 'To be assigned by staff';
        $response['guest_access_token'] = (string) ($booking['guest_access_token'] ?? '');

        api_response(['success' => true, 'booking' => $response]);
    }

    if ($path === '/v1/public/table-availability' && $method === 'GET') {
        $date = trim((string) ($_GET['date'] ?? ''));
        $startTimeRaw = trim((string) ($_GET['start_time'] ?? '12:00'));
        $guests = max(1, (int) ($_GET['guests'] ?? 2));

        if (!api_validate_date($date)) {
            api_error('date query param must be YYYY-MM-DD', 422);
        }

        $startTime = api_normalize_time($startTimeRaw);
        if ($startTime === null) {
            api_error('Invalid start_time', 422);
        }

        $endTime = date('H:i:s', strtotime($startTime . ' +60 minutes'));
        $stmt = $pdo->prepare("
            SELECT rt.table_id, rt.table_number, rt.capacity, rt.area_id, ta.name AS area_name
            FROM restaurant_tables rt
            LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
            WHERE rt.status = 'available'
              AND rt.reservable = 1
              AND rt.capacity >= ?
              AND rt.table_id NOT IN (
                SELECT DISTINCT bta.table_id
                FROM booking_table_assignments bta
                INNER JOIN bookings b ON b.booking_id = bta.booking_id
                WHERE b.booking_date = ?
                  AND b.status IN ('pending', 'confirmed')
                  AND (
                    (b.start_time < ? AND b.end_time > ?)
                    OR (b.start_time >= ? AND b.start_time < ?)
                    OR (b.end_time > ? AND b.end_time <= ?)
                  )
              )
            ORDER BY rt.capacity ASC, rt.table_number + 0, rt.table_number ASC
        ");
        $stmt->execute([$guests, $date, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        api_response(['success' => true, 'tables' => $tables]);
    }

    return false;
}
