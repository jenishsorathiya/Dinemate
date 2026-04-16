<?php
declare(strict_types=1);

function api_route_customer(PDO $pdo, string $method, string $path): bool
{
    if ($path === '/v1/bookings/my' && $method === 'GET') {
        $user = api_require_user($pdo, 'customer');
        ensureBookingRequestColumns($pdo);

        $bookings = getCustomerPortalBookings($pdo, (int) $user['user_id']);
        $payload = array_map(static fn(array $booking): array => api_shape_booking_payload($booking), $bookings);
        api_response(['success' => true, 'bookings' => $payload]);
    }

    if ($path === '/v1/customer/profile' && $method === 'GET') {
        $user = api_require_user($pdo, 'customer');
        ensureCustomerProfilesSchema($pdo);
        $profile = ensureCustomerProfileForUser($pdo, (int) $user['user_id']);

        api_response([
            'success' => true,
            'profile' => $profile,
        ]);
    }

    if ($path === '/v1/customer/profile' && $method === 'PATCH') {
        $user = api_require_user($pdo, 'customer');
        ensureCustomerProfilesSchema($pdo);
        ensureUserAccountSchema($pdo);
        $input = api_read_json_body();

        $name = trim((string) ($input['name'] ?? $user['name']));
        $email = trim((string) ($input['email'] ?? $user['email']));
        $phone = trim((string) ($input['phone'] ?? $user['phone']));
        $dietary = trim((string) ($input['dietary_notes'] ?? ''));
        $seating = trim((string) ($input['seating_preference'] ?? ''));
        $preferredTime = trim((string) ($input['preferred_booking_time'] ?? ''));
        $customerNotes = trim((string) ($input['customer_notes'] ?? $input['notes'] ?? ''));
        $emailRemindersEnabled = array_key_exists('email_reminders_enabled', $input) ? (int) !empty($input['email_reminders_enabled']) : 1;
        $smsRemindersEnabled = array_key_exists('sms_reminders_enabled', $input) ? (int) !empty($input['sms_reminders_enabled']) : 0;

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match("/^[0-9\\s\\-\\(\\)\\+]+$/", $phone)) {
            api_error('Invalid profile payload.', 422);
        }

        $emailCheckStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
        $emailCheckStmt->execute([$email, (int) $user['user_id']]);
        if ($emailCheckStmt->fetch(PDO::FETCH_ASSOC)) {
            api_error('That email address is already in use.', 409);
        }

        $profile = ensureCustomerProfileForUser($pdo, (int) $user['user_id']);
        $profileId = (int) ($profile['customer_profile_id'] ?? 0);
        if ($profileId < 1) {
            api_error('Unable to load customer profile.', 500);
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET name = ?, email = ?, phone = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$name, $email, $phone, (int) $user['user_id']]);

        $profileUpdate = $pdo->prepare("
            UPDATE customer_profiles
            SET name = ?, email = ?, phone = ?, normalized_email = ?, normalized_phone = ?, dietary_notes = ?, seating_preference = ?, preferred_booking_time = ?, notes = ?, email_reminders_enabled = ?, sms_reminders_enabled = ?
            WHERE customer_profile_id = ?
        ");
        $profileUpdate->execute([
            $name,
            $email,
            $phone,
            normalizeCustomerProfileEmail($email),
            normalizeCustomerProfilePhone($phone),
            $dietary !== '' ? $dietary : null,
            $seating !== '' ? $seating : null,
            $preferredTime !== '' ? $preferredTime : null,
            $customerNotes !== '' ? $customerNotes : null,
            $emailRemindersEnabled,
            $smsRemindersEnabled,
            $profileId,
        ]);

        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;

        $fetchStmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
        $fetchStmt->execute([$profileId]);
        api_response(['success' => true, 'profile' => $fetchStmt->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($path === '/v1/customer/profile/password' && $method === 'POST') {
        $user = api_require_user($pdo, 'customer');
        ensureUserAccountSchema($pdo);
        $input = api_read_json_body();

        $currentPassword = (string) ($input['current_password'] ?? '');
        $newPassword = (string) ($input['new_password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            api_error('All password fields are required.', 422);
        }
        if (strlen($newPassword) < 8) {
            api_error('New password must be at least 8 characters long.', 422);
        }
        if ($newPassword !== $confirmPassword) {
            api_error('New password and confirmation do not match.', 422);
        }

        $userStmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
        $userStmt->execute([(int) $user['user_id']]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$userRow || !password_verify($currentPassword, (string) ($userRow['password'] ?? ''))) {
            api_error('Current password is incorrect.', 401);
        }

        $passwordUpdateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ? AND role = 'customer'");
        $passwordUpdateStmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['user_id']]);

        api_response(['success' => true]);
    }

    if (preg_match('#^/v1/customer/bookings/(\d+)$#', $path, $matches) && $method === 'PATCH') {
        $user = api_require_user($pdo, 'customer');
        ensureBookingRequestColumns($pdo);
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        $input = api_read_json_body();

        $date = trim((string) ($input['booking_date'] ?? ''));
        $startTime = api_normalize_time((string) ($input['start_time'] ?? ''));
        $endTime = api_normalize_time((string) ($input['end_time'] ?? ''));
        $guests = (int) ($input['number_of_guests'] ?? 0);
        $special = trim((string) ($input['special_request'] ?? ''));

        if (!api_validate_date($date) || $startTime === null || $endTime === null || $guests < 1) {
            api_error('Invalid booking update payload.', 422);
        }

        $windowError = api_validate_booking_window($startTime, $endTime);
        if ($windowError !== null) {
            api_error($windowError, 422);
        }

        if (!api_check_capacity($pdo, $guests)) {
            api_error('No table capacity available for this guest count.', 422);
        }

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET booking_date = ?,
                start_time = ?,
                end_time = ?,
                requested_start_time = ?,
                requested_end_time = ?,
                number_of_guests = ?,
                special_request = ?,
                status = 'pending',
                reservation_card_status = NULL
            WHERE booking_id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([
            $date,
            $startTime,
            $endTime,
            $startTime,
            $endTime,
            $guests,
            $special !== '' ? $special : null,
            $bookingId,
            (int) $user['user_id'],
        ]);

        if ($stmt->rowCount() === 0) {
            api_error('Booking not found or cannot be modified.', 404);
        }

        syncBookingTableAssignments($pdo, $bookingId, []);

        $fetchStmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? LIMIT 1");
        $fetchStmt->execute([$bookingId]);
        $booking = $fetchStmt->fetch(PDO::FETCH_ASSOC);
        api_response(['success' => true, 'booking' => api_shape_booking_payload($booking ?: [])]);
    }

    if (preg_match('#^/v1/customer/bookings/(\d+)/cancel$#', $path, $matches) && $method === 'POST') {
        $user = api_require_user($pdo, 'customer');
        ensureBookingTableAssignmentsTable($pdo);

        $bookingId = (int) $matches[1];
        $pdo->beginTransaction();
        try {
            $checkStmt = $pdo->prepare("SELECT booking_id FROM bookings WHERE booking_id = ? AND user_id = ? AND status IN ('pending', 'confirmed') LIMIT 1");
            $checkStmt->execute([$bookingId, (int) $user['user_id']]);
            if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->rollBack();
                api_error('Booking not found or cannot be cancelled.', 404);
            }

            syncBookingTableAssignments($pdo, $bookingId, []);

            $updateStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', reservation_card_status = NULL WHERE booking_id = ? AND user_id = ?");
            $updateStmt->execute([$bookingId, (int) $user['user_id']]);
            $pdo->commit();
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            api_error('Failed to cancel booking.', 500);
        }

        api_response(['success' => true, 'booking_id' => $bookingId, 'status' => 'cancelled']);
    }

    return false;
}
