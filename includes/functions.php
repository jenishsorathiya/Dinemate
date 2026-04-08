<?php
// existing functions
function redirect($location) {
    header("Location: $location");
    exit();
}

function appPath($path = '') {
    $documentRootPath = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projectRootPath = realpath(__DIR__ . '/..') ?: '';
    $basePath = '';

    if ($documentRootPath !== '' && $projectRootPath !== '') {
        $normalizedDocumentRoot = str_replace('\\', '/', $documentRootPath);
        $normalizedProjectRoot = str_replace('\\', '/', $projectRootPath);
        if (strpos($normalizedProjectRoot, $normalizedDocumentRoot) === 0) {
            $basePath = str_replace('\\', '/', substr($normalizedProjectRoot, strlen($normalizedDocumentRoot)));
        }
    }

    $basePath = rtrim($basePath, '/');
    return ($basePath !== '' ? $basePath : '') . '/' . ltrim($path, '/');
}

function sanitize($data) {
    return htmlspecialchars(trim($data));
}

function storeUserSession(array $user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $user['role'] ?? null;
    $_SESSION['name'] = $user['name'] ?? 'User';
    $_SESSION['email'] = $user['email'] ?? null;
    $_SESSION['logged_in'] = true;
}

function consumeRedirectUrl() {
    $redirectUrl = $_SESSION['redirect_url'] ?? null;
    unset($_SESSION['redirect_url']);
    return $redirectUrl;
}

function getDefaultRedirectForRole($role) {
    if ($role === 'admin') {
        return appPath('admin/timeline/new-dashboard.php');
    }

    if ($role === 'customer') {
        return appPath('bookings/dashboard.php');
    }

    return appPath('index.php');
}

function getPostLoginRedirect($role) {
    $redirectUrl = consumeRedirectUrl();
    if (!empty($redirectUrl)) {
        return $redirectUrl;
    }

    return getDefaultRedirectForRole($role);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

// ===== NEW FUNCTIONS TO ADD =====

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user name
function getCurrentUserName() {
    return $_SESSION['name'] ?? 'Guest';
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Require login (redirects if not logged in)
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect(appPath('auth/login.php'));
    }
}

function requireRole($role, array $options = []) {
    $jsonResponse = !empty($options['json']);

    if (!isLoggedIn()) {
        if ($jsonResponse) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }

        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect(appPath('auth/login.php'));
    }

    if (getCurrentUserRole() !== $role) {
        if ($jsonResponse) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }

        redirect(getDefaultRedirectForRole(getCurrentUserRole()));
    }
}

// Require admin access (redirects if not admin)
function requireAdmin(array $options = []) {
    requireRole('admin', $options);
}

// Require customer access (redirects if not customer)
function requireCustomer(array $options = []) {
    requireRole('customer', $options);
}

function ensureUserAccountSchema($pdo) {
    $disabledStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_disabled'");
    if ($disabledStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_disabled TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
    }
}

// Set flash message (temporary message that disappears after one page load)
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,  // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

// Get flash message and clear it
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function getBookingStatuses() {
    return ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
}

function getBookingActiveStatuses() {
    return ['pending', 'confirmed'];
}

function getBookingCompletedStatuses() {
    return ['completed', 'cancelled', 'no_show'];
}

function getBookingStatusLabel($status) {
    $normalizedStatus = strtolower(trim((string) $status));
    $labels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No-show',
    ];

    return $labels[$normalizedStatus] ?? ucfirst($normalizedStatus ?: 'pending');
}

function getBookingPlacementStatuses() {
    return ['not_placed', 'placed'];
}

function getBookingPlacementLabel($status) {
    $normalizedStatus = strtolower(trim((string) $status));
    $labels = [
        'not_placed' => 'Not placed',
        'placed' => 'Placed',
    ];

    return $labels[$normalizedStatus] ?? 'Not placed';
}

function getBookingSources() {
    return ['customer_account', 'guest_web', 'admin_manual'];
}

function getBookingSourceLabel($source) {
    $normalizedSource = strtolower(trim((string) $source));
    $labels = [
        'customer_account' => 'Customer account',
        'guest_web' => 'Guest web booking',
        'admin_manual' => 'Entered by admin',
    ];

    return $labels[$normalizedSource] ?? 'Unknown source';
}

function normalizeCustomerProfileEmail($email) {
    return strtolower(trim((string) $email));
}

function normalizeCustomerProfilePhone($phone) {
    return preg_replace('/\D+/', '', (string) $phone);
}

function ensureCustomerProfilesSchema($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customer_profiles (
            customer_profile_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            linked_user_id INT NULL DEFAULT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NULL DEFAULT NULL,
            phone VARCHAR(30) NULL DEFAULT NULL,
            notes TEXT NULL DEFAULT NULL,
            normalized_email VARCHAR(100) NULL DEFAULT NULL,
            normalized_phone VARCHAR(30) NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_customer_profiles_linked_user_id (linked_user_id),
            KEY idx_customer_profiles_normalized_email (normalized_email),
            KEY idx_customer_profiles_normalized_phone (normalized_phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $notesStmt = $pdo->query("SHOW COLUMNS FROM customer_profiles LIKE 'notes'");
    if ($notesStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE customer_profiles ADD COLUMN notes TEXT NULL DEFAULT NULL AFTER phone");
    }

    $dietaryNotesStmt = $pdo->query("SHOW COLUMNS FROM customer_profiles LIKE 'dietary_notes'");
    if ($dietaryNotesStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE customer_profiles ADD COLUMN dietary_notes TEXT NULL DEFAULT NULL AFTER notes");
    }

    $seatingPreferenceStmt = $pdo->query("SHOW COLUMNS FROM customer_profiles LIKE 'seating_preference'");
    if ($seatingPreferenceStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE customer_profiles ADD COLUMN seating_preference VARCHAR(50) NULL DEFAULT NULL AFTER dietary_notes");
    }

    $preferredTimeStmt = $pdo->query("SHOW COLUMNS FROM customer_profiles LIKE 'preferred_booking_time'");
    if ($preferredTimeStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE customer_profiles ADD COLUMN preferred_booking_time VARCHAR(30) NULL DEFAULT NULL AFTER seating_preference");
    }

    $emailReminderStmt = $pdo->query("SHOW COLUMNS FROM customer_profiles LIKE 'email_reminders_enabled'");
    if ($emailReminderStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE customer_profiles ADD COLUMN email_reminders_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER preferred_booking_time");
    }

    $smsReminderStmt = $pdo->query("SHOW COLUMNS FROM customer_profiles LIKE 'sms_reminders_enabled'");
    if ($smsReminderStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE customer_profiles ADD COLUMN sms_reminders_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER email_reminders_enabled");
    }

    $profileIdStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'customer_profile_id'");
    if ($profileIdStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN customer_profile_id INT NULL DEFAULT NULL AFTER user_id");
    }
}

function getCustomerProfileByUserId($pdo, $userId) {
    $userId = (int) $userId;
    if ($userId < 1) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE linked_user_id = ? ORDER BY customer_profile_id ASC LIMIT 1");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    return $profile ?: null;
}

function findMatchingCustomerProfile($pdo, $name, $email = null, $phone = null, $linkedUserId = null) {
    $normalizedEmail = normalizeCustomerProfileEmail($email);
    $normalizedPhone = normalizeCustomerProfilePhone($phone);

    if ($linkedUserId !== null && (int) $linkedUserId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE linked_user_id = ? ORDER BY customer_profile_id ASC LIMIT 1");
        $stmt->execute([(int) $linkedUserId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            return $profile;
        }
    }

    if ($normalizedEmail !== '') {
        $stmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE normalized_email = ? ORDER BY customer_profile_id ASC LIMIT 1");
        $stmt->execute([$normalizedEmail]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            return $profile;
        }
    }

    if ($normalizedPhone !== '') {
        $stmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE normalized_phone = ? ORDER BY customer_profile_id ASC LIMIT 1");
        $stmt->execute([$normalizedPhone]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            return $profile;
        }
    }

    return null;
}

function upsertCustomerProfile($pdo, $name, $email = null, $phone = null, $linkedUserId = null) {
    $trimmedName = trim((string) $name);
    $trimmedEmail = trim((string) ($email ?? ''));
    $trimmedPhone = trim((string) ($phone ?? ''));
    $normalizedEmail = normalizeCustomerProfileEmail($trimmedEmail);
    $normalizedPhone = normalizeCustomerProfilePhone($trimmedPhone);

    if ($trimmedName === '' && $normalizedEmail === '' && $normalizedPhone === '') {
        return null;
    }

    $profile = findMatchingCustomerProfile(
        $pdo,
        $trimmedName,
        $trimmedEmail !== '' ? $trimmedEmail : null,
        $trimmedPhone !== '' ? $trimmedPhone : null,
        $linkedUserId
    );

    if ($profile) {
        $profileId = (int) $profile['customer_profile_id'];
        $nextName = $trimmedName !== '' ? $trimmedName : (string) ($profile['name'] ?? 'Guest');
        $nextEmail = $trimmedEmail !== '' ? $trimmedEmail : (string) ($profile['email'] ?? '');
        $nextPhone = $trimmedPhone !== '' ? $trimmedPhone : (string) ($profile['phone'] ?? '');
        $nextLinkedUserId = ($linkedUserId !== null && (int) $linkedUserId > 0)
            ? (int) $linkedUserId
            : ((isset($profile['linked_user_id']) && $profile['linked_user_id'] !== null) ? (int) $profile['linked_user_id'] : null);

        $stmt = $pdo->prepare("
            UPDATE customer_profiles
            SET linked_user_id = ?,
                name = ?,
                email = ?,
                phone = ?,
                normalized_email = ?,
                normalized_phone = ?
            WHERE customer_profile_id = ?
        ");
        $stmt->execute([
            $nextLinkedUserId,
            $nextName !== '' ? $nextName : 'Guest',
            $nextEmail !== '' ? $nextEmail : null,
            $nextPhone !== '' ? $nextPhone : null,
            $nextEmail !== '' ? normalizeCustomerProfileEmail($nextEmail) : null,
            $nextPhone !== '' ? normalizeCustomerProfilePhone($nextPhone) : null,
            $profileId,
        ]);

        return $profileId;
    }

    $stmt = $pdo->prepare("
        INSERT INTO customer_profiles (linked_user_id, name, email, phone, normalized_email, normalized_phone)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        ($linkedUserId !== null && (int) $linkedUserId > 0) ? (int) $linkedUserId : null,
        $trimmedName !== '' ? $trimmedName : 'Guest',
        $trimmedEmail !== '' ? $trimmedEmail : null,
        $trimmedPhone !== '' ? $trimmedPhone : null,
        $normalizedEmail !== '' ? $normalizedEmail : null,
        $normalizedPhone !== '' ? $normalizedPhone : null,
    ]);

    return (int) $pdo->lastInsertId();
}

function ensureCustomerProfileForUser($pdo, $userId) {
    $userId = (int) $userId;
    if ($userId < 1) {
        return null;
    }

    ensureCustomerProfilesSchema($pdo);

    $userStmt = $pdo->prepare("SELECT user_id, name, email, phone FROM users WHERE user_id = ? AND role = 'customer' LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return null;
    }

    $profileId = upsertCustomerProfile(
        $pdo,
        (string) ($user['name'] ?? 'Customer'),
        (string) ($user['email'] ?? ''),
        (string) ($user['phone'] ?? ''),
        $userId
    );

    if ($profileId === null) {
        return null;
    }

    $profileStmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
    $profileStmt->execute([$profileId]);

    return $profileStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getCustomerPortalBookings($pdo, $userId) {
    $userId = (int) $userId;
    if ($userId < 1) {
        return [];
    }

    $profile = ensureCustomerProfileForUser($pdo, $userId);
    $profileId = $profile ? (int) ($profile['customer_profile_id'] ?? 0) : 0;

    $sql = "
        SELECT
            b.*,
            t.table_number,
            cp.name AS profile_name,
            cp.email AS profile_email,
            cp.phone AS profile_phone,
            creator.name AS created_by_name
        FROM bookings b
        LEFT JOIN restaurant_tables t ON b.table_id = t.table_id
        LEFT JOIN customer_profiles cp ON b.customer_profile_id = cp.customer_profile_id
        LEFT JOIN users creator ON b.created_by_user_id = creator.user_id
        WHERE b.user_id = ?
    ";
    $params = [$userId];

    if ($profileId > 0) {
        $sql .= " OR b.customer_profile_id = ?";
        $params[] = $profileId;
    }

    $sql .= " ORDER BY b.booking_date DESC, COALESCE(b.start_time, '00:00:00') DESC, b.booking_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function ensureBookingStatusSchema($pdo) {
    $statusStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'status'");
    $statusColumn = $statusStmt->fetch(PDO::FETCH_ASSOC);

    if (!$statusColumn) {
        return;
    }

    $statusType = strtolower((string) ($statusColumn['Type'] ?? ''));
    if (strpos($statusType, 'enum(') !== 0) {
        return;
    }

    $normalizedType = str_replace(['`', '"', ' '], '', $statusType);
    $expectedType = "enum('pending','confirmed','completed','cancelled','no_show')";

    if ($normalizedType !== $expectedType) {
        $pdo->exec("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending','confirmed','completed','cancelled','no_show') DEFAULT 'confirmed'");
    }
}

// Display flash message as HTML
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        // Use switch for broader PHP version compatibility (avoid PHP 8 match())
        switch ($type) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
                $alertClass = 'alert-danger';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
            default:
                $alertClass = 'alert-info';
                break;
        }
        echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

function ensureBookingRequestColumns($pdo) {
    ensureBookingStatusSchema($pdo);
    ensureUserAccountSchema($pdo);
    ensureCustomerProfilesSchema($pdo);

    $startTimeStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'start_time'");
    $startTimeExists = $startTimeStmt->rowCount() > 0;

    $endTimeStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'end_time'");
    $endTimeExists = $endTimeStmt->rowCount() > 0;

    $requestedStartStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'requested_start_time'");
    $requestedStartExists = $requestedStartStmt->rowCount() > 0;

    $requestedEndStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'requested_end_time'");
    $requestedEndExists = $requestedEndStmt->rowCount() > 0;

    $nameOverrideStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'customer_name_override'");
    $nameOverrideExists = $nameOverrideStmt->rowCount() > 0;

    $customerNameStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'customer_name'");
    $customerNameExists = $customerNameStmt->rowCount() > 0;

    $customerPhoneStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'customer_phone'");
    $customerPhoneExists = $customerPhoneStmt->rowCount() > 0;

    $customerEmailStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'customer_email'");
    $customerEmailExists = $customerEmailStmt->rowCount() > 0;

    $guestTokenStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'guest_access_token'");
    $guestTokenExists = $guestTokenStmt->rowCount() > 0;

    $placementStatusStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'reservation_card_status'");
    $placementStatusColumn = $placementStatusStmt->fetch(PDO::FETCH_ASSOC);

    $bookingSourceStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'booking_source'");
    $bookingSourceColumn = $bookingSourceStmt->fetch(PDO::FETCH_ASSOC);

    $createdByStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'created_by_user_id'");
    $createdByColumn = $createdByStmt->fetch(PDO::FETCH_ASSOC);

    // Handle legacy booking_time column which some older DBs still have
    $bookingTimeStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'booking_time'");
    $bookingTimeColumn = $bookingTimeStmt->fetch(PDO::FETCH_ASSOC);

    $userIdStmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'user_id'");
    $userIdColumn = $userIdStmt->fetch(PDO::FETCH_ASSOC);

    if (!$requestedStartExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN requested_start_time TIME DEFAULT NULL AFTER end_time");
    }

    if (!$requestedEndExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN requested_end_time TIME DEFAULT NULL AFTER requested_start_time");
    }

    if (!$nameOverrideExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN customer_name_override VARCHAR(100) DEFAULT NULL AFTER user_id");
    }

    if (!$customerNameExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN customer_name VARCHAR(100) DEFAULT NULL AFTER user_id");
    }

    if (!$customerPhoneExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN customer_phone VARCHAR(30) DEFAULT NULL AFTER customer_name");
    }

    if (!$customerEmailExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN customer_email VARCHAR(100) DEFAULT NULL AFTER customer_phone");
    }

    if (!$guestTokenExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN guest_access_token VARCHAR(64) DEFAULT NULL AFTER customer_email");
        $pdo->exec("CREATE UNIQUE INDEX idx_bookings_guest_access_token ON bookings (guest_access_token)");
    }

    if (!$placementStatusColumn) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN reservation_card_status ENUM('not_placed', 'placed') DEFAULT NULL AFTER status");
    } else {
        $placementType = strtolower((string) ($placementStatusColumn['Type'] ?? ''));
        $normalizedPlacementType = str_replace(['`', '"', ' '], '', $placementType);
        $expectedPlacementType = "enum('not_placed','placed')";
        if (strpos($placementType, 'enum(') === 0 && $normalizedPlacementType !== $expectedPlacementType) {
            $pdo->exec("ALTER TABLE bookings MODIFY COLUMN reservation_card_status ENUM('not_placed', 'placed') DEFAULT NULL");
        }
    }

    if (!$bookingSourceColumn) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN booking_source ENUM('customer_account', 'guest_web', 'admin_manual') DEFAULT NULL AFTER reservation_card_status");
    } else {
        $bookingSourceType = strtolower((string) ($bookingSourceColumn['Type'] ?? ''));
        $normalizedBookingSourceType = str_replace(['`', '"', ' '], '', $bookingSourceType);
        $expectedBookingSourceType = "enum('customer_account','guest_web','admin_manual')";
        if (strpos($bookingSourceType, 'enum(') === 0 && $normalizedBookingSourceType !== $expectedBookingSourceType) {
            $pdo->exec("ALTER TABLE bookings MODIFY COLUMN booking_source ENUM('customer_account', 'guest_web', 'admin_manual') DEFAULT NULL");
        }
    }

    if (!$createdByColumn) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN created_by_user_id INT NULL AFTER booking_source");
    }

    // If booking_time exists but is NOT NULL without a default, make it nullable to avoid insert failures
    if ($bookingTimeColumn && isset($bookingTimeColumn['Null']) && $bookingTimeColumn['Null'] !== 'YES') {
        // Use the same column type but allow NULL and default NULL
        $type = $bookingTimeColumn['Type'] ?? 'VARCHAR(255)';
        try {
            $pdo->exec("ALTER TABLE bookings MODIFY COLUMN booking_time {$type} NULL DEFAULT NULL");
        } catch (Exception $e) {
            // If modify fails, log but continue — we don't want setup to fatally break here
            error_log('Could not modify booking_time column: ' . $e->getMessage());
        }
    }

    if ($userIdColumn && $userIdColumn['Null'] !== 'YES') {
        $pdo->exec("ALTER TABLE bookings MODIFY COLUMN user_id {$userIdColumn['Type']} NULL");
    }

    if ($startTimeExists) {
        $pdo->exec("UPDATE bookings SET requested_start_time = start_time WHERE requested_start_time IS NULL AND start_time IS NOT NULL");
    }

    if ($endTimeExists) {
        $pdo->exec("UPDATE bookings SET requested_end_time = end_time WHERE requested_end_time IS NULL AND end_time IS NOT NULL");
    }

    $pdo->exec("
        UPDATE bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        SET b.customer_name = COALESCE(NULLIF(b.customer_name, ''), NULLIF(b.customer_name_override, ''), u.name)
        WHERE b.customer_name IS NULL OR b.customer_name = ''
    ");

    $pdo->exec("
        UPDATE bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        SET b.customer_phone = COALESCE(NULLIF(b.customer_phone, ''), u.phone)
        WHERE b.customer_phone IS NULL OR b.customer_phone = ''
    ");

    $pdo->exec("
        UPDATE bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        SET b.customer_email = COALESCE(NULLIF(b.customer_email, ''), u.email)
        WHERE b.customer_email IS NULL OR b.customer_email = ''
    ");

    $missingTokenIds = $pdo->query("SELECT booking_id FROM bookings WHERE guest_access_token IS NULL OR guest_access_token = ''")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($missingTokenIds)) {
        $tokenUpdateStmt = $pdo->prepare("UPDATE bookings SET guest_access_token = ? WHERE booking_id = ?");
        foreach ($missingTokenIds as $bookingId) {
            $tokenUpdateStmt->execute([generateGuestAccessToken(), $bookingId]);
        }
    }

    $missingProfileRowsStmt = $pdo->query("
        SELECT
            b.booking_id,
            b.user_id,
            COALESCE(NULLIF(b.customer_name_override, ''), NULLIF(b.customer_name, ''), u.name, 'Guest') AS profile_name,
            COALESCE(NULLIF(b.customer_email, ''), u.email, '') AS profile_email,
            COALESCE(NULLIF(b.customer_phone, ''), u.phone, '') AS profile_phone
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.customer_profile_id IS NULL
        ORDER BY b.booking_id ASC
    ");
    $missingProfileRows = $missingProfileRowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!empty($missingProfileRows)) {
        $profileUpdateStmt = $pdo->prepare("UPDATE bookings SET customer_profile_id = ? WHERE booking_id = ?");
        foreach ($missingProfileRows as $row) {
            $profileId = upsertCustomerProfile(
                $pdo,
                (string) ($row['profile_name'] ?? 'Guest'),
                (string) ($row['profile_email'] ?? ''),
                (string) ($row['profile_phone'] ?? ''),
                $row['user_id'] !== null ? (int) $row['user_id'] : null
            );
            if ($profileId !== null) {
                $profileUpdateStmt->execute([$profileId, (int) $row['booking_id']]);
            }
        }
    }

    $pdo->exec("
        UPDATE bookings
        SET booking_source = CASE
            WHEN booking_source IS NOT NULL THEN booking_source
            WHEN created_by_user_id IS NOT NULL THEN 'admin_manual'
            WHEN user_id IS NOT NULL THEN 'customer_account'
            WHEN guest_access_token IS NOT NULL AND guest_access_token <> '' THEN 'guest_web'
            ELSE 'admin_manual'
        END
        WHERE booking_source IS NULL
    ");

    $pdo->exec("
        UPDATE bookings b
        LEFT JOIN (
            SELECT DISTINCT booking_id
            FROM booking_table_assignments
        ) assigned ON assigned.booking_id = b.booking_id
        SET b.reservation_card_status = 'not_placed'
        WHERE b.reservation_card_status IS NULL
          AND b.status IN ('pending', 'confirmed')
          AND (b.table_id IS NOT NULL OR assigned.booking_id IS NOT NULL)
    ");

    $pdo->exec("
        UPDATE bookings b
        LEFT JOIN (
            SELECT DISTINCT booking_id
            FROM booking_table_assignments
        ) assigned ON assigned.booking_id = b.booking_id
        SET b.reservation_card_status = NULL
        WHERE b.status = 'cancelled'
           OR (b.table_id IS NULL AND assigned.booking_id IS NULL)
    ");
}

function generateGuestAccessToken() {
    return bin2hex(random_bytes(16));
}

function ensureBookingTableAssignmentsTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS booking_table_assignments (
            booking_id INT NOT NULL,
            table_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (booking_id, table_id),
            KEY idx_bta_table_id (table_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        INSERT IGNORE INTO booking_table_assignments (booking_id, table_id)
        SELECT booking_id, table_id
        FROM bookings
        WHERE table_id IS NOT NULL
    ");
}

function ensureTableAreasSchema($pdo) {
    $pdo->exec(" 
        CREATE TABLE IF NOT EXISTS table_areas (
            area_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            table_number_start INT NULL DEFAULT NULL,
            table_number_end INT NULL DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $startStmt = $pdo->query("SHOW COLUMNS FROM table_areas LIKE 'table_number_start'");
    if ($startStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE table_areas ADD COLUMN table_number_start INT NULL DEFAULT NULL AFTER display_order");
    }

    $endStmt = $pdo->query("SHOW COLUMNS FROM table_areas LIKE 'table_number_end'");
    if ($endStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE table_areas ADD COLUMN table_number_end INT NULL DEFAULT NULL AFTER table_number_start");
    }

    $areaLayoutXStmt = $pdo->query("SHOW COLUMNS FROM table_areas LIKE 'layout_x'");
    if ($areaLayoutXStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE table_areas ADD COLUMN layout_x INT NULL DEFAULT NULL AFTER table_number_end");
    }

    $areaLayoutYStmt = $pdo->query("SHOW COLUMNS FROM table_areas LIKE 'layout_y'");
    if ($areaLayoutYStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE table_areas ADD COLUMN layout_y INT NULL DEFAULT NULL AFTER layout_x");
    }

    $areaLayoutWidthStmt = $pdo->query("SHOW COLUMNS FROM table_areas LIKE 'layout_width'");
    if ($areaLayoutWidthStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE table_areas ADD COLUMN layout_width INT NULL DEFAULT NULL AFTER layout_y");
    }

    $areaLayoutHeightStmt = $pdo->query("SHOW COLUMNS FROM table_areas LIKE 'layout_height'");
    if ($areaLayoutHeightStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE table_areas ADD COLUMN layout_height INT NULL DEFAULT NULL AFTER layout_width");
    }

    $areaLabelXStmt = $pdo->query("SHOW COLUMNS FROM table_areas LIKE 'label_layout_x'");
    if ($areaLabelXStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE table_areas ADD COLUMN label_layout_x INT NULL DEFAULT NULL AFTER layout_height");
    }

    $areaLabelYStmt = $pdo->query("SHOW COLUMNS FROM table_areas LIKE 'label_layout_y'");
    if ($areaLabelYStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE table_areas ADD COLUMN label_layout_y INT NULL DEFAULT NULL AFTER label_layout_x");
    }

    $areaIdStmt = $pdo->query("SHOW COLUMNS FROM restaurant_tables LIKE 'area_id'");
    if ($areaIdStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN area_id INT NULL AFTER table_id");
    }

    $sortOrderStmt = $pdo->query("SHOW COLUMNS FROM restaurant_tables LIKE 'sort_order'");
    if ($sortOrderStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER capacity");
    }

    $reservableStmt = $pdo->query("SHOW COLUMNS FROM restaurant_tables LIKE 'reservable'");
    if ($reservableStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN reservable TINYINT(1) NOT NULL DEFAULT 1 AFTER status");
    }

    $layoutXStmt = $pdo->query("SHOW COLUMNS FROM restaurant_tables LIKE 'layout_x'");
    if ($layoutXStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN layout_x INT NULL DEFAULT NULL AFTER reservable");
    }

    $layoutYStmt = $pdo->query("SHOW COLUMNS FROM restaurant_tables LIKE 'layout_y'");
    if ($layoutYStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN layout_y INT NULL DEFAULT NULL AFTER layout_x");
    }

    $tableShapeStmt = $pdo->query("SHOW COLUMNS FROM restaurant_tables LIKE 'table_shape'");
    if ($tableShapeStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN table_shape VARCHAR(20) NOT NULL DEFAULT 'auto' AFTER layout_y");
    }

    $defaultAreaStmt = $pdo->query("SELECT area_id FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, area_id ASC LIMIT 1");
    $defaultAreaId = (int) $defaultAreaStmt->fetchColumn();

    if ($defaultAreaId <= 0) {
        $nextDisplayOrder = (int) $pdo->query("SELECT COALESCE(MAX(display_order), 0) + 10 FROM table_areas")->fetchColumn();
        $insertAreaStmt = $pdo->prepare("INSERT INTO table_areas (name, display_order, table_number_start, table_number_end, is_active) VALUES (?, ?, NULL, NULL, 1)");
        $insertAreaStmt->execute(['Main Floor', $nextDisplayOrder]);
        $defaultAreaId = (int) $pdo->lastInsertId();
    }

    $assignAreaStmt = $pdo->prepare("UPDATE restaurant_tables SET area_id = ? WHERE area_id IS NULL OR area_id = 0");
    $assignAreaStmt->execute([$defaultAreaId]);

    $tablesStmt = $pdo->query("SELECT table_id, area_id, sort_order FROM restaurant_tables ORDER BY area_id, CAST(table_number AS UNSIGNED), table_number, table_id");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC);

    $sortCounters = [];
    $sortUpdateStmt = $pdo->prepare("UPDATE restaurant_tables SET sort_order = ? WHERE table_id = ?");
    foreach ($tables as $tableRow) {
        $tableId = (int) $tableRow['table_id'];
        $areaId = (int) $tableRow['area_id'];

        if (!isset($sortCounters[$areaId])) {
            $sortCounters[$areaId] = 10;
        }

        $currentSortOrder = (int) ($tableRow['sort_order'] ?? 0);
        if ($currentSortOrder <= 0) {
            $sortUpdateStmt->execute([$sortCounters[$areaId], $tableId]);
        }

        $sortCounters[$areaId] += 10;
    }
}

function syncBookingTableAssignments($pdo, $bookingId, $tableIds) {
    $normalizedIds = [];
    foreach ($tableIds as $tableId) {
        $tableId = (int)$tableId;
        if ($tableId > 0 && !in_array($tableId, $normalizedIds, true)) {
            $normalizedIds[] = $tableId;
        }
    }

    $deleteStmt = $pdo->prepare("DELETE FROM booking_table_assignments WHERE booking_id = ?");
    $deleteStmt->execute([$bookingId]);

    if (!empty($normalizedIds)) {
        $insertStmt = $pdo->prepare("INSERT INTO booking_table_assignments (booking_id, table_id) VALUES (?, ?)");
        foreach ($normalizedIds as $tableId) {
            $insertStmt->execute([$bookingId, $tableId]);
        }
    }

    $primaryTableId = !empty($normalizedIds) ? $normalizedIds[0] : null;
    $updateStmt = $pdo->prepare("UPDATE bookings SET table_id = ? WHERE booking_id = ?");
    $updateStmt->execute([$primaryTableId, $bookingId]);

    return $normalizedIds;
}

function removeTablesAndUnassignBookings($pdo, $tableIds) {
    $normalizedTableIds = [];
    foreach ($tableIds as $tableId) {
        $tableId = (int) $tableId;
        if ($tableId > 0 && !in_array($tableId, $normalizedTableIds, true)) {
            $normalizedTableIds[] = $tableId;
        }
    }

    if (empty($normalizedTableIds)) {
        return [
            'deleted_table_ids' => [],
            'affected_booking_ids' => [],
        ];
    }

    $placeholders = implode(',', array_fill(0, count($normalizedTableIds), '?'));

    $bookingStmt = $pdo->prepare("SELECT DISTINCT booking_id FROM booking_table_assignments WHERE table_id IN ($placeholders)");
    $bookingStmt->execute($normalizedTableIds);
    $affectedBookingIds = array_map('intval', $bookingStmt->fetchAll(PDO::FETCH_COLUMN));

    $remainingAssignmentStmt = $pdo->prepare("SELECT table_id FROM booking_table_assignments WHERE booking_id = ? AND table_id NOT IN ($placeholders) ORDER BY created_at ASC, table_id ASC");
    foreach ($affectedBookingIds as $bookingId) {
        $remainingAssignmentStmt->execute(array_merge([$bookingId], $normalizedTableIds));
        $remainingTableIds = array_map('intval', $remainingAssignmentStmt->fetchAll(PDO::FETCH_COLUMN));
        syncBookingTableAssignments($pdo, $bookingId, $remainingTableIds);
    }

    $deleteAssignmentsStmt = $pdo->prepare("DELETE FROM booking_table_assignments WHERE table_id IN ($placeholders)");
    $deleteAssignmentsStmt->execute($normalizedTableIds);

    $deleteTablesStmt = $pdo->prepare("DELETE FROM restaurant_tables WHERE table_id IN ($placeholders)");
    $deleteTablesStmt->execute($normalizedTableIds);

    return [
        'deleted_table_ids' => $normalizedTableIds,
        'affected_booking_ids' => $affectedBookingIds,
    ];
}

function getAreaTablesForResponse($pdo, $areaId) {
    $stmt = $pdo->prepare(" 
        SELECT rt.table_id, rt.table_number, rt.capacity, rt.area_id, rt.sort_order,
               ta.name AS area_name, ta.display_order AS area_display_order
        FROM restaurant_tables rt
        LEFT JOIN table_areas ta ON ta.area_id = rt.area_id
        WHERE rt.area_id = ?
        ORDER BY rt.sort_order ASC, rt.table_number + 0, rt.table_number ASC
    ");
    $stmt->execute([(int) $areaId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function syncAreaNumberedTables($pdo, $areaId, $tableNumberStart, $tableNumberEnd, $defaultCapacity = 8) {
    $areaId = (int) $areaId;
    $tableNumberStart = $tableNumberStart !== null ? (int) $tableNumberStart : null;
    $tableNumberEnd = $tableNumberEnd !== null ? (int) $tableNumberEnd : null;

    if ($areaId < 1 || $tableNumberStart === null || $tableNumberEnd === null) {
        return [
            'created_tables' => [],
            'deleted_table_ids' => [],
            'affected_booking_ids' => [],
            'area_tables' => getAreaTablesForResponse($pdo, $areaId),
        ];
    }

    $existingStmt = $pdo->prepare("SELECT table_id, table_number, capacity FROM restaurant_tables WHERE area_id = ? ORDER BY table_number + 0, table_number ASC, table_id ASC");
    $existingStmt->execute([$areaId]);
    $existingTables = $existingStmt->fetchAll(PDO::FETCH_ASSOC);

    $targetNumbers = range($tableNumberStart, $tableNumberEnd);
    $targetLookup = array_fill_keys($targetNumbers, true);
    $existingByNumber = [];
    $tablesToDelete = [];

    foreach ($existingTables as $tableRow) {
        $numericTableNumber = filter_var($tableRow['table_number'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($numericTableNumber === false || !isset($targetLookup[$numericTableNumber])) {
            $tablesToDelete[] = (int) $tableRow['table_id'];
            continue;
        }

        $existingByNumber[$numericTableNumber] = $tableRow;
    }

    $removalResult = removeTablesAndUnassignBookings($pdo, $tablesToDelete);

    $insertStmt = $pdo->prepare("INSERT INTO restaurant_tables (area_id, table_number, capacity, sort_order, status) VALUES (?, ?, ?, ?, 'available')");
    $createdTableIds = [];
    $sortOrder = 10;
    foreach ($targetNumbers as $targetNumber) {
        if (!isset($existingByNumber[$targetNumber])) {
            $insertStmt->execute([$areaId, (string) $targetNumber, (int) $defaultCapacity, $sortOrder]);
            $createdTableIds[] = (int) $pdo->lastInsertId();
        }
        $sortOrder += 10;
    }

    $areaTables = getAreaTablesForResponse($pdo, $areaId);
    $sortUpdateStmt = $pdo->prepare("UPDATE restaurant_tables SET sort_order = ? WHERE table_id = ?");
    $sortOrder = 10;
    foreach ($areaTables as $tableRow) {
        $sortUpdateStmt->execute([$sortOrder, (int) $tableRow['table_id']]);
        $sortOrder += 10;
    }

    $areaTables = getAreaTablesForResponse($pdo, $areaId);
    $createdTableIdLookup = array_fill_keys($createdTableIds, true);
    $createdTables = array_values(array_filter($areaTables, static function ($tableRow) use ($createdTableIdLookup) {
        return isset($createdTableIdLookup[(int) $tableRow['table_id']]);
    }));

    return [
        'created_tables' => $createdTables,
        'deleted_table_ids' => $removalResult['deleted_table_ids'],
        'affected_booking_ids' => $removalResult['affected_booking_ids'],
        'area_tables' => $areaTables,
    ];
}

// Logout function
function logout() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
}
?>
