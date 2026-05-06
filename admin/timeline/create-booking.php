<?php
require_once "../../config/db.php";
require_once "../../includes/session-check.php";
require_once "../../includes/functions.php";

header('Content-Type: application/json');

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);

requireAdmin(['json' => true]);

$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$customerProfileId = (int) ($data['customer_profile_id'] ?? 0);
$customerEmail = trim($data['customer_email'] ?? '');
$customerPhone = trim($data['customer_phone'] ?? '');
$bookingDate = trim($data['booking_date'] ?? '');
$startTimeInput = trim($data['start_time'] ?? '');
$guestCount = (int)($data['number_of_guests'] ?? 0);
$bookingType = normalizeBookingType($data['booking_type'] ?? 'normal');
$specialRequest = trim($data['special_request'] ?? '');
$selectedTableIds = [];
if (isset($data['table_ids']) && is_array($data['table_ids'])) {
    foreach ($data['table_ids'] as $tableId) {
        $tableId = (int)$tableId;
        if ($tableId > 0 && !in_array($tableId, $selectedTableIds, true)) {
            $selectedTableIds[] = $tableId;
        }
    }
}
if (empty($selectedTableIds) && isset($data['table_id']) && $data['table_id'] !== '') {
    $selectedTableId = (int)$data['table_id'];
    if ($selectedTableId > 0) {
        $selectedTableIds[] = $selectedTableId;
    }
}

if($name === '' || $bookingDate === '' || $startTimeInput === '' || $guestCount < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All required fields must be provided']);
    exit();
}

if($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'If provided, email must be valid']);
    exit();
}

if($customerPhone !== '' && !preg_match('/^[0-9\s\-\(\)\+]+$/', $customerPhone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'If provided, phone number must be valid']);
    exit();
}

$startTimestamp = strtotime($startTimeInput);
if($startTimestamp === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking time']);
    exit();
}

$startTime = date('H:i:s', $startTimestamp);
$endTime = date('H:i:s', strtotime('+60 minutes', $startTimestamp));

if($startTime < '10:00:00' || $endTime > '22:00:00') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bookings must fit within restaurant hours 10:00 - 22:00']);
    exit();
}

$assignedTables = [];
$assignedTableNumbers = [];
if (!empty($selectedTableIds)) {
    $tablePlaceholders = implode(',', array_fill(0, count($selectedTableIds), '?'));
    $assignedTablesStmt = $pdo->prepare("SELECT table_id, table_number, capacity FROM restaurant_tables WHERE table_id IN ($tablePlaceholders)");
    $assignedTablesStmt->execute($selectedTableIds);
    $tableRows = $assignedTablesStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($tableRows) !== count($selectedTableIds)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Selected table not found']);
        exit();
    }

    $tableMap = [];
    foreach ($tableRows as $tableRow) {
        $tableMap[(int)$tableRow['table_id']] = $tableRow;
    }

    foreach ($selectedTableIds as $tableId) {
        $assignedTables[] = $tableMap[$tableId];
        $assignedTableNumbers[] = (string)$tableMap[$tableId]['table_number'];
    }

    $conflictStmt = $pdo->prepare("
        SELECT COUNT(*) as conflict_count
        FROM booking_table_assignments bta
        INNER JOIN bookings b ON b.booking_id = bta.booking_id
        WHERE bta.table_id = ?
        AND b.booking_date = ?
        AND b.status IN ('pending', 'confirmed')
        AND (
            (b.start_time < ? AND b.end_time > ?)
            OR (b.start_time >= ? AND b.start_time < ?)
            OR (b.end_time > ? AND b.end_time <= ?)
        )
    ");

    foreach ($selectedTableIds as $assignedTableId) {
        $conflictStmt->execute([
            $assignedTableId,
            $bookingDate,
            $endTime,
            $startTime,
            $startTime,
            $endTime,
            $startTime,
            $endTime,
        ]);

        $conflict = $conflictStmt->fetch(PDO::FETCH_ASSOC);
        if ((int)$conflict['conflict_count'] > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Assigned time conflicts with another booking at one of the assigned tables']);
            exit();
        }
    }
} else {
    $capacityStmt = $pdo->prepare("SELECT COUNT(*) FROM restaurant_tables WHERE status = 'available' AND capacity >= ?");
    $capacityStmt->execute([$guestCount]);
    if((int)$capacityStmt->fetchColumn() === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No available table can accommodate that many guests']);
        exit();
    }
}

try {
    if ($customerProfileId > 0) {
        $profileStmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
        $profileStmt->execute([$customerProfileId]);
        $existingProfile = $profileStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingProfile) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Selected customer profile was not found']);
            exit();
        }

        $profileName = $name !== '' ? $name : (string) ($existingProfile['name'] ?? 'Guest');
        $profileEmail = $customerEmail !== '' ? $customerEmail : (string) ($existingProfile['email'] ?? '');
        $profilePhone = $customerPhone !== '' ? $customerPhone : (string) ($existingProfile['phone'] ?? '');

        $customerProfileId = upsertCustomerProfile(
            $pdo,
            $profileName,
            $profileEmail !== '' ? $profileEmail : null,
            $profilePhone !== '' ? $profilePhone : null,
            isset($existingProfile['linked_user_id']) && $existingProfile['linked_user_id'] !== null ? (int) $existingProfile['linked_user_id'] : null
        );

        $name = $profileName;
        $customerEmail = $profileEmail;
        $customerPhone = $profilePhone;
    } else {
        $customerProfileId = upsertCustomerProfile(
            $pdo,
            $name,
            $customerEmail !== '' ? $customerEmail : null,
            $customerPhone !== '' ? $customerPhone : null,
            null
        );
    }

    $nextPlacementStatus = !empty($selectedTableIds) ? 'not_placed' : null;

    $bookingStmt = $pdo->prepare("INSERT INTO bookings (user_id, customer_profile_id, customer_name, customer_phone, customer_email, guest_access_token, table_id, booking_date, start_time, end_time, requested_start_time, requested_end_time, number_of_guests, booking_type, special_request, status, reservation_card_status, booking_source, created_by_user_id) VALUES (NULL, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, 'admin_manual', ?)");
    $bookingStmt->execute([
        $customerProfileId,
        $name,
        $customerPhone !== '' ? $customerPhone : null,
        $customerEmail !== '' ? $customerEmail : null,
        generateGuestAccessToken(),
        $bookingDate,
        $startTime,
        $endTime,
        $startTime,
        $endTime,
        $guestCount,
        $bookingType,
        $specialRequest !== '' ? $specialRequest : null,
        $nextPlacementStatus,
        (int) (getCurrentUserId() ?? 0) ?: null,
    ]);

    $bookingId = $pdo->lastInsertId();
    $assignedTableIds = syncBookingTableAssignments($pdo, $bookingId, $selectedTableIds);
    notifyBookingEvent($pdo, $bookingId, 'booking_confirmed');

    echo json_encode([
        'success' => true,
        'booking' => [
            'booking_id' => (int)$bookingId,
            'user_id' => null,
            'table_id' => $assignedTableIds[0] ?? null,
            'table_number' => $assignedTableNumbers[0] ?? null,
            'assigned_table_ids' => $assignedTableIds,
            'assigned_table_numbers' => $assignedTableNumbers,
            'booking_date' => $bookingDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'requested_start_time' => $startTime,
            'requested_end_time' => $endTime,
            'number_of_guests' => $guestCount,
            'booking_type' => $bookingType,
            'booking_type_label' => getBookingTypeLabel($bookingType),
            'special_request' => $specialRequest !== '' ? $specialRequest : null,
            'status' => 'confirmed',
            'reservation_card_status' => $nextPlacementStatus,
            'reservation_card_status_label' => $nextPlacementStatus !== null ? getBookingPlacementLabel($nextPlacementStatus) : null,
            'booking_source' => 'admin_manual',
            'booking_source_label' => getBookingSourceLabel('admin_manual'),
            'customer_name' => $name,
            'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
            'customer_email' => $customerEmail !== '' ? $customerEmail : null,
        ],
    ]);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not create booking']);
}
?>
