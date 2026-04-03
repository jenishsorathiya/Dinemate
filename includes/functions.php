<?php
// existing functions
function redirect($location) {
    header("Location: $location");
    exit();
}

function sanitize($data) {
    return htmlspecialchars(trim($data));
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
        redirect("/Dinemate/auth/login.php");
    }
}

// Require admin access (redirects if not admin)
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect("/Dinemate/auth/login.php");
    }
}

// Require customer access (redirects if not customer)
function requireCustomer() {
    requireLogin();
    if (!isCustomer()) {
        redirect("/Dinemate/auth/login.php");
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

// Display flash message as HTML
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        echo "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

function ensureBookingRequestColumns($pdo) {
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

    if (!$requestedStartExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN requested_start_time TIME DEFAULT NULL AFTER end_time");
    }

    if (!$requestedEndExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN requested_end_time TIME DEFAULT NULL AFTER requested_start_time");
    }

    if (!$nameOverrideExists) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN customer_name_override VARCHAR(100) DEFAULT NULL AFTER user_id");
    }

    if ($startTimeExists) {
        $pdo->exec("UPDATE bookings SET requested_start_time = start_time WHERE requested_start_time IS NULL AND start_time IS NOT NULL");
    }

    if ($endTimeExists) {
        $pdo->exec("UPDATE bookings SET requested_end_time = end_time WHERE requested_end_time IS NULL AND end_time IS NOT NULL");
    }
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

    $areaIdStmt = $pdo->query("SHOW COLUMNS FROM restaurant_tables LIKE 'area_id'");
    if ($areaIdStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN area_id INT NULL AFTER table_id");
    }

    $sortOrderStmt = $pdo->query("SHOW COLUMNS FROM restaurant_tables LIKE 'sort_order'");
    if ($sortOrderStmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE restaurant_tables ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER capacity");
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