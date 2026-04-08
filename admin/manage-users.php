
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureUserAccountSchema($pdo);

$registeredUserFilterSql = "
    (
        users.role = 'admin'
        OR (
            users.role = 'customer'
            AND users.email NOT LIKE '%@admin-booking.local'
            AND users.email NOT LIKE 'guest-%@local.dinemate'
        )
    )
";

if (empty($_SESSION['manage_users_csrf'])) {
    $_SESSION['manage_users_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['manage_users_csrf'];
$allowedRoles = ['customer', 'admin'];
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

$redirectToManageUsers = static function (string $suffix = ''): void {
    header("Location: manage-users.php{$suffix}");
    exit();
};

$verifyCsrf = static function () use ($redirectToManageUsers, $csrfToken): void {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($csrfToken, $submittedToken)) {
        setFlashMessage('error', 'Your session expired. Please try again.');
        $redirectToManageUsers();
    }
};

$isPlaceholderUser = static function (string $email): bool {
    $email = strtolower(trim($email));
    return preg_match('/@admin-booking\.local$/', $email) === 1
        || preg_match('/^guest-.*@local\.dinemate$/', $email) === 1;
};

$findRegisteredUserById = static function (PDO $pdo, int $userId) use ($registeredUserFilterSql): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE user_id = ?
          AND {$registeredUserFilterSql}
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
};

$validateUserInput = static function (array $input, bool $passwordRequired = false) use ($allowedRoles, $isPlaceholderUser): array {
    $name = trim(sanitize($input['name'] ?? ''));
    $email = strtolower(trim(sanitize($input['email'] ?? '')));
    $phone = trim(sanitize($input['phone'] ?? ''));
    $role = trim(sanitize($input['role'] ?? 'customer'));
    $password = (string) ($input['password'] ?? '');

    $errors = [];

    if ($name === '') {
        $errors[] = 'Name is required.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (strlen($email) > 150) {
        $errors[] = 'Email address is too long.';
    } elseif ($isPlaceholderUser($email)) {
        $errors[] = 'That email pattern is reserved for booking placeholder accounts.';
    }

    if ($phone !== '') {
        if (!preg_match('/^[0-9\s\-\(\)\+]+$/', $phone)) {
            $errors[] = 'Phone number can only contain digits, spaces, hyphens, parentheses, and plus signs.';
        } elseif (strlen(preg_replace('/\D+/', '', $phone)) < 6 || strlen($phone) > 30) {
            $errors[] = 'Phone number must be at least 6 digits and no longer than 30 characters.';
        }
    }

    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Please choose a valid role.';
    }

    if ($passwordRequired) {
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif (strlen($password) > 255) {
            $errors[] = 'Password is too long.';
        }
    } elseif ($password !== '' && strlen($password) < 6) {
        $errors[] = 'If you set a new password, it must be at least 6 characters.';
    }

    return [[
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'role' => $role,
        'password' => $password,
    ], $errors];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add_user') {
        [$payload, $errors] = $validateUserInput($_POST, true);

        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$payload['email']]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'Email already exists.';
        }

        if (!empty($errors)) {
            $_SESSION['add_user_errors'] = $errors;
            $_SESSION['add_user_data'] = [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'role' => $payload['role'],
            ];
            $redirectToManageUsers('#add-user-form');
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $payload['name'],
            $payload['email'],
            $payload['phone'] !== '' ? $payload['phone'] : null,
            password_hash($payload['password'], PASSWORD_BCRYPT),
            $payload['role'],
        ]);

        setFlashMessage('success', 'User added successfully.');
        $redirectToManageUsers();
    }

    if ($action === 'edit_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $targetUser = $findRegisteredUserById($pdo, $userId);

        if (!$targetUser) {
            setFlashMessage('warning', 'That account is not a registered user.');
            $redirectToManageUsers();
        }

        [$payload, $errors] = $validateUserInput($_POST, false);

        if ($userId === $currentUserId && $payload['role'] !== 'admin') {
            $errors[] = 'You cannot remove your own admin privileges.';
        }

        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
        $stmt->execute([$payload['email'], $userId]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'Email already exists for another user.';
        }

        if (!empty($errors)) {
            $_SESSION['edit_user_errors'] = $errors;
            $redirectToManageUsers('?edit=' . $userId);
        }

        if ($payload['password'] !== '') {
            $stmt = $pdo->prepare("
                UPDATE users
                SET name = ?, email = ?, phone = ?, role = ?, password = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $payload['name'],
                $payload['email'],
                $payload['phone'] !== '' ? $payload['phone'] : null,
                $payload['role'],
                password_hash($payload['password'], PASSWORD_BCRYPT),
                $userId,
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users
                SET name = ?, email = ?, phone = ?, role = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $payload['name'],
                $payload['email'],
                $payload['phone'] !== '' ? $payload['phone'] : null,
                $payload['role'],
                $userId,
            ]);
        }

        setFlashMessage('success', 'User updated successfully.');
        $redirectToManageUsers();
    }
    if (in_array($action, ['promote_user', 'demote_user', 'delete_user', 'disable_user', 'enable_user'], true)) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $targetUser = $findRegisteredUserById($pdo, $userId);

        if (!$targetUser) {
            setFlashMessage('warning', 'That account is not a registered user.');
            $redirectToManageUsers();
        }

        if ($userId === $currentUserId) {
            if ($action === 'delete_user') {
                setFlashMessage('error', 'You cannot delete yourself.');
            } elseif ($action === 'demote_user') {
                setFlashMessage('error', 'You cannot demote yourself.');
            } elseif ($action === 'disable_user') {
                setFlashMessage('error', 'You cannot disable your own account.');
            } else {
                setFlashMessage('error', 'You cannot promote yourself.');
            }
            $redirectToManageUsers();
        }

        if ($action === 'promote_user') {
            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE user_id = ?");
            $stmt->execute([$userId]);
            setFlashMessage('success', 'User promoted to admin successfully.');
            $redirectToManageUsers();
        }

        if ($action === 'demote_user') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $adminCount = (int) $stmt->fetchColumn();

            if ($adminCount <= 1) {
                setFlashMessage('error', 'Cannot demote the last admin.');
                $redirectToManageUsers();
            }

            $stmt = $pdo->prepare("UPDATE users SET role = 'customer' WHERE user_id = ?");
            $stmt->execute([$userId]);
            setFlashMessage('success', 'User demoted to customer successfully.');
            $redirectToManageUsers();
        }

        if ($action === 'disable_user') {
            if (($targetUser['role'] ?? '') === 'admin') {
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND COALESCE(is_disabled, 0) = 0");
                $activeAdminCount = (int) $stmt->fetchColumn();
                if ($activeAdminCount <= 1) {
                    setFlashMessage('error', 'Cannot disable the last active admin.');
                    $redirectToManageUsers();
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET is_disabled = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            setFlashMessage('success', 'User account disabled successfully.');
            $redirectToManageUsers();
        }

        if ($action === 'enable_user') {
            $stmt = $pdo->prepare("UPDATE users SET is_disabled = 0 WHERE user_id = ?");
            $stmt->execute([$userId]);
            setFlashMessage('success', 'User account enabled successfully.');
            $redirectToManageUsers();
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $bookingCount = (int) $stmt->fetchColumn();

        if ($bookingCount > 0) {
            setFlashMessage('warning', "Cannot delete user with {$bookingCount} existing booking(s). Delete bookings first.");
            $redirectToManageUsers();
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        setFlashMessage('success', 'User deleted successfully.');
        $redirectToManageUsers();
    }
}

$userListStmt = $pdo->query("
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
    LEFT JOIN bookings b ON u.user_id = b.user_id
    WHERE (
        u.role = 'admin'
        OR (
            u.role = 'customer'
            AND u.email NOT LIKE '%@admin-booking.local'
            AND u.email NOT LIKE 'guest-%@local.dinemate'
        )
    )
    GROUP BY u.user_id, u.name, u.email, u.phone, u.role, u.is_disabled, u.created_at
    ORDER BY
        CASE WHEN u.role = 'admin' THEN 0 ELSE 1 END,
        u.created_at DESC,
        u.user_id DESC
");
$users = $userListStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$userBookingHistory = [];
$visibleUserIds = array_values(array_filter(array_map(static function (array $user): int {
    return (int) ($user['user_id'] ?? 0);
}, $users)));

if (!empty($visibleUserIds)) {
    $placeholders = implode(',', array_fill(0, count($visibleUserIds), '?'));
    $bookingHistoryStmt = $pdo->prepare("
        SELECT
            b.booking_id,
            b.user_id,
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
        LEFT JOIN booking_table_assignments bta ON b.booking_id = bta.booking_id
        LEFT JOIN restaurant_tables rt ON bta.table_id = rt.table_id
        LEFT JOIN users creator ON b.created_by_user_id = creator.user_id
        WHERE b.user_id IN ($placeholders)
          AND (
              b.booking_date < CURDATE()
              OR (b.booking_date = CURDATE() AND b.status IN ('completed', 'cancelled', 'no_show'))
          )
        GROUP BY
            b.booking_id,
            b.user_id,
            b.booking_date,
            b.start_time,
            b.end_time,
            b.number_of_guests,
            b.status,
            b.reservation_card_status
        ORDER BY b.booking_date DESC, b.start_time DESC, b.booking_id DESC
    ");
    $bookingHistoryStmt->execute($visibleUserIds);
    $bookingHistoryRows = $bookingHistoryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($bookingHistoryRows as $bookingRow) {
        $userId = (int) ($bookingRow['user_id'] ?? 0);
        if (!isset($userBookingHistory[$userId])) {
            $userBookingHistory[$userId] = [];
        }

        $userBookingHistory[$userId][] = [
            'booking_id' => (int) ($bookingRow['booking_id'] ?? 0),
            'booking_date' => (string) ($bookingRow['booking_date'] ?? ''),
            'start_time' => (string) ($bookingRow['start_time'] ?? ''),
            'end_time' => (string) ($bookingRow['end_time'] ?? ''),
            'number_of_guests' => (int) ($bookingRow['number_of_guests'] ?? 0),
            'status' => (string) ($bookingRow['status'] ?? ''),
            'status_label' => getBookingStatusLabel($bookingRow['status'] ?? ''),
            'booking_source' => (string) ($bookingRow['booking_source'] ?? ''),
            'booking_source_label' => getBookingSourceLabel($bookingRow['booking_source'] ?? ''),
            'created_by_name' => (string) ($bookingRow['created_by_name'] ?? ''),
            'reservation_card_status_label' => !empty($bookingRow['reservation_card_status']) ? getBookingPlacementLabel($bookingRow['reservation_card_status']) : '',
            'assigned_table_numbers' => (string) ($bookingRow['assigned_table_numbers'] ?? ''),
        ];
    }
}

$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int) ($_GET['edit'] ?? 0);
    $editUser = $findRegisteredUserById($pdo, $editId);

    if (!$editUser) {
        setFlashMessage('warning', 'That account is not a registered user.');
        $redirectToManageUsers();
    }
}

$totalUsers = count($users);
$adminCount = 0;
$customerCount = 0;
$usersWithBookings = 0;
$usersWithoutBookings = 0;
$recentUsers = 0;

foreach ($users as $user) {
    $isAdmin = ($user['role'] ?? '') === 'admin';
    if ($isAdmin) {
        $adminCount++;
    } else {
        $customerCount++;
    }

    if ((int) ($user['booking_count'] ?? 0) > 0) {
        $usersWithBookings++;
    } else {
        $usersWithoutBookings++;
    }

    if (!empty($user['created_at']) && strtotime((string) $user['created_at']) >= strtotime('-30 days')) {
        $recentUsers++;
    }
}

$flash = getFlashMessage();
$addUserErrors = $_SESSION['add_user_errors'] ?? [];
$addUserData = $_SESSION['add_user_data'] ?? [];
$editUserErrors = $_SESSION['edit_user_errors'] ?? [];
unset($_SESSION['add_user_errors'], $_SESSION['add_user_data'], $_SESSION['edit_user_errors']);

$adminPageTitle = 'Users';
$adminPageIcon = 'fa-users';
$adminNotificationCount = $totalUsers;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'users';
$adminSidebarPathPrefix = '';
$userBookingHistoryJson = json_encode($userBookingHistory, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users | DineMate Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard-theme.css" rel="stylesheet">
    <style>
        :root { --users-bg: linear-gradient(180deg, #f5f7fb 0%, #eff4fb 100%); --users-card: #ffffff; --users-line: #e5ebf4; --users-text: #172033; --users-muted: #6b768b; --users-shadow: 0 24px 48px rgba(15, 23, 42, 0.08); --users-shadow-soft: 0 12px 26px rgba(15, 23, 42, 0.05); --users-primary: #1f3c88; --users-accent: #d8a230; --users-success: #1f8f63; --users-danger: #c94b62; --users-warning: #c9831f; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--users-bg); color: var(--users-text); }
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .main { flex: 1; overflow-y: auto; padding: 28px; }
        .users-shell { max-width: 1400px; margin: 0 auto; display: grid; gap: 22px; }
        .hero-card, .panel-card, .stat-card { background: var(--users-card); border: 1px solid var(--users-line); border-radius: 24px; box-shadow: var(--users-shadow-soft); }
        .hero-card { padding: 28px; box-shadow: var(--users-shadow); background: radial-gradient(circle at top right, rgba(216, 162, 48, 0.18), transparent 28%), radial-gradient(circle at bottom left, rgba(31, 60, 136, 0.14), transparent 34%), #ffffff; }
        .hero-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.9fr); gap: 24px; align-items: end; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px; color: var(--users-accent); font-size: 12px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; }
        .hero-title { margin: 0; font-size: clamp(30px, 4vw, 42px); line-height: 1.03; letter-spacing: -0.04em; }
        .hero-copy { margin: 12px 0 0; max-width: 780px; color: var(--users-muted); font-size: 15px; line-height: 1.7; }
        .hero-note-card { padding: 20px; border-radius: 20px; background: rgba(248, 250, 253, 0.9); border: 1px solid var(--users-line); }
        .hero-note-card strong { display: block; font-size: 28px; letter-spacing: -0.04em; }
        .hero-note-card span { display: block; margin-top: 8px; color: var(--users-muted); font-size: 13px; line-height: 1.6; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 16px; }
        .stat-card { padding: 20px; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; left: 0; top: 0; width: 100%; height: 4px; background: linear-gradient(90deg, rgba(216, 162, 48, 0.95), rgba(31, 60, 136, 0.8)); }
        .stat-label { color: var(--users-muted); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .stat-value { margin-top: 14px; font-size: 34px; font-weight: 800; letter-spacing: -0.05em; }
        .stat-meta { margin-top: 8px; color: var(--users-muted); font-size: 13px; }
        .content-grid { display: grid; grid-template-columns: minmax(320px, 0.95fr) minmax(0, 1.65fr); gap: 22px; align-items: start; }
        .panel-card { padding: 24px; }
        .panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .panel-title { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.03em; }
        .panel-subtitle { margin: 6px 0 0; color: var(--users-muted); font-size: 13px; }
        .inline-chip { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; background: #f7f9fc; border: 1px solid var(--users-line); color: #44506a; font-size: 12px; font-weight: 700; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .form-field { display: grid; gap: 8px; }
        .form-field.full { grid-column: 1 / -1; }
        .form-label { font-size: 13px; font-weight: 700; color: #34405a; }
        .form-control, .form-select { min-height: 46px; border-radius: 14px; border: 1px solid var(--users-line); background: #fbfcfe; padding: 12px 14px; box-shadow: none; }
        .form-control:focus, .form-select:focus { border-color: #c99a32; box-shadow: 0 0 0 4px rgba(216, 162, 48, 0.14); background: #ffffff; }
        .form-help { color: var(--users-muted); font-size: 12px; line-height: 1.5; }
        .action-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        .btn-surface, .btn-primary-soft, .btn-danger-soft, .btn-warning-soft { border: 1px solid var(--users-line); border-radius: 14px; min-height: 42px; padding: 0 16px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; cursor: pointer; transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease; }
        .btn-surface:hover, .btn-primary-soft:hover, .btn-danger-soft:hover, .btn-warning-soft:hover { transform: translateY(-1px); box-shadow: var(--users-shadow-soft); }
        .btn-surface { background: #ffffff; color: #293750; }
        .btn-primary-soft { background: #1f3c88; border-color: #1f3c88; color: #ffffff; }
        .btn-danger-soft { background: #fff2f5; border-color: #ffd6df; color: var(--users-danger); }
        .btn-warning-soft { background: #fff8ec; border-color: #f4dfb1; color: var(--users-warning); }
        .alert { border-radius: 16px; border: 1px solid transparent; padding: 16px 18px; margin: 0; }
        .alert ul { margin: 0; padding-left: 18px; }
        .table-toolbar { display: grid; grid-template-columns: minmax(0, 1.1fr) repeat(3, minmax(160px, 0.45fr)); gap: 12px; margin-bottom: 18px; }
        .toolbar-field { position: relative; }
        .toolbar-field i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #8a96ab; font-size: 13px; }
        .toolbar-field input { padding-left: 38px; }
        .table-wrap { border: 1px solid var(--users-line); border-radius: 20px; overflow: hidden; background: #ffffff; }
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom thead th { background: #f8fafd; color: #52607a; font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 16px 18px; border-bottom: 1px solid var(--users-line); white-space: nowrap; }
        .table-custom tbody td { padding: 18px; border-bottom: 1px solid #edf2f7; vertical-align: top; }
        .table-custom tbody tr:last-child td { border-bottom: 0; }
        .table-custom tbody tr:hover { background: #fbfcff; }
        .user-name { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-weight: 700; }
        .user-meta { display: block; margin-top: 6px; color: var(--users-muted); font-size: 12px; }
        .tiny-badge, .role-badge, .booking-badge, .account-badge { display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .tiny-badge { background: #eaf1ff; color: #3658a5; }
        .role-badge.role-admin { background: #edf2f7; color: #4a556a; }
        .role-badge.role-customer { background: #e9f7ef; color: #1f8f63; }
        .booking-badge.has-bookings { background: #eef4ff; color: #315cba; }
        .booking-badge.no-bookings { background: #f5f7fb; color: #6c768d; }
        .account-badge.active { background: #e9f7ef; color: #1f8f63; }
        .account-badge.disabled { background: #fff0f3; color: #c94b62; }
        .booking-badge.booking-trigger { border: 0; cursor: pointer; }
        .booking-badge.booking-trigger:hover { transform: translateY(-1px); box-shadow: var(--users-shadow-soft); }
        .table-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .table-actions form, .table-actions a { margin: 0; }
        .btn-table { min-height: 36px; padding: 0 12px; border-radius: 12px; font-size: 12px; }
        .results-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 14px; color: var(--users-muted); font-size: 13px; }
        .results-empty { display: none; padding: 28px 18px; text-align: center; color: var(--users-muted); font-size: 14px; }
        .modal-content { border: 0; border-radius: 24px; box-shadow: var(--users-shadow); }
        .modal-header, .modal-footer { border: 0; padding: 22px 24px 0; }
        .modal-body { padding: 22px 24px 24px; }
        .modal-footer { padding: 0 24px 24px; }
        .history-list { display: grid; gap: 12px; }
        .history-card { border: 1px solid var(--users-line); border-radius: 18px; padding: 16px 18px; background: #fbfcff; }
        .history-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .history-card-title { margin: 0; font-size: 15px; font-weight: 800; }
        .history-card-meta { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 10px; color: var(--users-muted); font-size: 13px; }
        .history-card-note { margin-top: 10px; color: var(--users-muted); font-size: 12px; }
        .history-empty { padding: 18px; border: 1px dashed var(--users-line); border-radius: 18px; background: #fbfcff; color: var(--users-muted); text-align: center; font-size: 14px; }
        @media (max-width: 1200px) { .stats-grid, .table-toolbar { grid-template-columns: repeat(2, minmax(0, 1fr)); } .content-grid, .hero-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .main { padding: 18px; } .stats-grid, .form-grid, .table-toolbar { grid-template-columns: 1fr; } .panel-heading, .results-footer { align-items: flex-start; flex-direction: column; } .table-custom thead { display: none; } .table-custom, .table-custom tbody, .table-custom tr, .table-custom td { display: block; width: 100%; } .table-custom tbody tr { padding: 16px 16px 12px; border-bottom: 1px solid #edf2f7; } .table-custom tbody td { padding: 8px 0; border: 0; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/admin-sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/admin-topbar.php'; ?>
        <div class="main">
            <div class="users-shell">
                <section class="hero-card">
                    <div class="hero-grid">
                        <div>
                            <div class="eyebrow"><i class="fa-solid fa-user-shield"></i> Account Management</div>
                            <h1 class="hero-title">Registered users only, with the admin tools this page was missing.</h1>
                            <p class="hero-copy">This view now excludes booking placeholder accounts, highlights the real customer and admin base, and uses safer account actions with hashed passwords for new admin-created users.</p>
                        </div>
                        <div class="hero-note-card"><strong><?php echo number_format($totalUsers); ?></strong><span>Visible registered accounts across customers and admins. Booking-only placeholder users are excluded from this page.</span></div>
                    </div>
                </section>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'error' ? 'danger' : $flash['type'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <section class="stats-grid">
                    <article class="stat-card"><div class="stat-label">Registered</div><div class="stat-value"><?php echo number_format($totalUsers); ?></div><div class="stat-meta">All visible customer and admin accounts.</div></article>
                    <article class="stat-card"><div class="stat-label">Customers</div><div class="stat-value"><?php echo number_format($customerCount); ?></div><div class="stat-meta">Real registered customer accounts only.</div></article>
                    <article class="stat-card"><div class="stat-label">Admins</div><div class="stat-value"><?php echo number_format($adminCount); ?></div><div class="stat-meta">Accounts with staff-level access.</div></article>
                    <article class="stat-card"><div class="stat-label">With Bookings</div><div class="stat-value"><?php echo number_format($usersWithBookings); ?></div><div class="stat-meta">Registered users who have placed bookings.</div></article>
                    <article class="stat-card"><div class="stat-label">Last 30 Days</div><div class="stat-value"><?php echo number_format($recentUsers); ?></div><div class="stat-meta">Fresh registrations and recently created accounts.</div></article>
                </section>

                <section class="content-grid">
                    <article class="panel-card" id="add-user-form">
                        <div class="panel-heading">
                            <div><h2 class="panel-title">Add New User</h2><p class="panel-subtitle">Create a customer or admin account manually. Passwords are now stored securely using hashing.</p></div>
                            <span class="inline-chip"><i class="fa-solid fa-lock"></i> Secure password storage</span>
                        </div>

                        <?php if (!empty($addUserErrors)): ?>
                            <div class="alert alert-danger mb-3"><ul><?php foreach ($addUserErrors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="add_user">
                            <div class="form-grid">
                                <div class="form-field"><label class="form-label" for="add-name">Full Name</label><input type="text" id="add-name" name="name" class="form-control" maxlength="100" value="<?php echo htmlspecialchars((string) ($addUserData['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                                <div class="form-field"><label class="form-label" for="add-email">Email</label><input type="email" id="add-email" name="email" class="form-control" maxlength="150" value="<?php echo htmlspecialchars((string) ($addUserData['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                                <div class="form-field"><label class="form-label" for="add-phone">Phone</label><input type="text" id="add-phone" name="phone" class="form-control" maxlength="30" value="<?php echo htmlspecialchars((string) ($addUserData['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                                <div class="form-field"><label class="form-label" for="add-role">Role</label><select id="add-role" name="role" class="form-select"><option value="customer" <?php echo (($addUserData['role'] ?? 'customer') === 'customer') ? 'selected' : ''; ?>>Customer</option><option value="admin" <?php echo (($addUserData['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option></select></div>
                                <div class="form-field full"><label class="form-label" for="add-password">Password</label><input type="password" id="add-password" name="password" class="form-control" minlength="6" maxlength="255" required><div class="form-help">Use at least 6 characters. This password will be hashed before it is saved.</div></div>
                            </div>
                            <div class="action-row"><button type="submit" class="btn-primary-soft"><i class="fa-solid fa-user-plus"></i> Create User</button><button type="reset" class="btn-surface"><i class="fa-solid fa-rotate-left"></i> Reset</button></div>
                        </form>
                    </article>

                    <article class="panel-card">
                        <div class="panel-heading">
                            <div><h2 class="panel-title">Registered Users</h2><p class="panel-subtitle">Search, filter, and manage customers and admins without mixing in booking placeholder records.</p></div>
                            <span class="inline-chip"><i class="fa-solid fa-users"></i> <?php echo number_format($totalUsers); ?> visible accounts</span>
                        </div>

                        <div class="table-toolbar">
                            <div class="toolbar-field"><i class="fa-solid fa-magnifying-glass"></i><input type="text" id="searchInput" class="form-control" placeholder="Search name, email, or phone..."></div>
                            <div><select id="roleFilter" class="form-select"><option value="all">All roles</option><option value="admin">Admins</option><option value="customer">Customers</option></select></div>
                            <div><select id="bookingFilter" class="form-select"><option value="all">All booking states</option><option value="with-bookings">With bookings</option><option value="no-bookings">No bookings</option></select></div>
                            <div><select id="sortFilter" class="form-select"><option value="recent">Newest first</option><option value="oldest">Oldest first</option><option value="name-asc">Name A-Z</option><option value="bookings-desc">Most bookings</option></select></div>
                        </div>

                        <div class="table-wrap"><div class="table-responsive"><table class="table-custom" id="usersTable"><thead><tr><th>User</th><th>Role</th><th>Bookings</th><th>Joined</th><th>Last Booking</th><th>Actions</th></tr></thead><tbody>
                                        <?php foreach ($users as $user): ?>
                                            <?php
                                            $bookingCount = (int) ($user['booking_count'] ?? 0);
                                            $isSelf = (int) $user['user_id'] === $currentUserId;
                                            $isDisabled = (int) ($user['is_disabled'] ?? 0) === 1;
                                            $joinedTs = !empty($user['created_at']) ? strtotime((string) $user['created_at']) : false;
                                            $lastBookingTs = !empty($user['last_booking_date']) ? strtotime((string) $user['last_booking_date']) : false;
                                            ?>
                                            <tr data-role="<?php echo htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8'); ?>" data-bookings="<?php echo $bookingCount; ?>" data-name="<?php echo htmlspecialchars(strtolower((string) ($user['name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" data-email="<?php echo htmlspecialchars(strtolower((string) ($user['email'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" data-phone="<?php echo htmlspecialchars(strtolower((string) ($user['phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>" data-created="<?php echo $joinedTs !== false ? (int) $joinedTs : 0; ?>">
                                                <td>
                                                    <div class="user-name">
                                                        <span><?php echo htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php if ($isSelf): ?><span class="tiny-badge"><i class="fa-solid fa-user-check"></i> You</span><?php endif; ?>
                                                        <span class="account-badge <?php echo $isDisabled ? 'disabled' : 'active'; ?>">
                                                            <i class="fa-solid fa-<?php echo $isDisabled ? 'ban' : 'circle-check'; ?>"></i>
                                                            <?php echo $isDisabled ? 'Disabled' : 'Active'; ?>
                                                        </span>
                                                    </div>
                                                    <span class="user-meta"><?php echo htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="user-meta"><?php echo htmlspecialchars((string) (($user['phone'] ?? '') !== '' ? $user['phone'] : 'No phone number'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </td>
                                                <td><span class="role-badge role-<?php echo htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-<?php echo $user['role'] === 'admin' ? 'crown' : 'user'; ?>"></i><?php echo htmlspecialchars(ucfirst((string) $user['role']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td>
                                                    <?php if ($bookingCount > 0): ?>
                                                        <button type="button" class="booking-badge has-bookings booking-trigger" data-user-id="<?php echo (int) $user['user_id']; ?>" data-user-name="<?php echo htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <i class="fa-solid fa-calendar-check"></i><?php echo number_format($bookingCount) . ' booking' . ($bookingCount === 1 ? '' : 's'); ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="booking-badge no-bookings"><i class="fa-solid fa-calendar-check"></i>No bookings</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $joinedTs !== false ? htmlspecialchars(date('M d, Y', $joinedTs), ENT_QUOTES, 'UTF-8') : '-'; ?><span class="user-meta"><?php echo $joinedTs !== false ? htmlspecialchars(date('g:i A', $joinedTs), ENT_QUOTES, 'UTF-8') : ''; ?></span></td>
                                                <td><?php echo $lastBookingTs !== false ? htmlspecialchars(date('M d, Y', $lastBookingTs), ENT_QUOTES, 'UTF-8') : 'No booking yet'; ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                        <?php if ($isSelf): ?>
                                                            <button type="button" class="btn-surface btn-table" onclick="alert('You cannot edit yourself here. Use your profile page instead.')"><i class="fa-solid fa-user-gear"></i> Profile</button>
                                                        <?php else: ?>
                                                            <a href="?edit=<?php echo (int) $user['user_id']; ?>" class="btn-surface btn-table"><i class="fa-solid fa-pen"></i> Edit</a>
                                                            <?php if ($user['role'] === 'customer'): ?>
                                                                <form method="POST" action="" onsubmit="return confirm('Promote this user to admin?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="promote_user"><input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>"><button type="submit" class="btn-primary-soft btn-table"><i class="fa-solid fa-arrow-up"></i> Promote</button></form>
                                                            <?php else: ?>
                                                                <form method="POST" action="" onsubmit="return confirm('Demote this admin to customer?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="demote_user"><input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>"><button type="submit" class="btn-warning-soft btn-table"><i class="fa-solid fa-arrow-down"></i> Demote</button></form>
                                                            <?php endif; ?>
                                                            <?php if ($isDisabled): ?>
                                                                <form method="POST" action="" onsubmit="return confirm('Enable this user account?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="enable_user"><input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>"><button type="submit" class="btn-primary-soft btn-table"><i class="fa-solid fa-user-check"></i> Enable</button></form>
                                                            <?php else: ?>
                                                                <form method="POST" action="" onsubmit="return confirm('Disable this user account? They will not be able to log in.');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="disable_user"><input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>"><button type="submit" class="btn-warning-soft btn-table"><i class="fa-solid fa-user-slash"></i> Disable</button></form>
                                                            <?php endif; ?>
                                                            <?php if ($bookingCount === 0): ?>
                                                                <form method="POST" action="" onsubmit="return confirm('Delete this user? This action cannot be undone.');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?php echo (int) $user['user_id']; ?>"><button type="submit" class="btn-danger-soft btn-table"><i class="fa-solid fa-trash"></i> Delete</button></form>
                                                            <?php else: ?>
                                                                <button type="button" class="btn-surface btn-table" disabled title="Cannot delete a user with bookings."><i class="fa-solid fa-lock"></i> Protected</button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody></table><div class="results-empty" id="emptyState">No users match the current filters.</div></div></div>

                        <div class="results-footer"><span id="resultsCount"><?php echo number_format($totalUsers); ?> users shown</span><span><?php echo number_format($usersWithoutBookings); ?> users currently have no bookings.</span></div>
                    </article>
                </section>
            </div>
        </div>
    </div>
</div>

<?php if ($editUser): ?>
    <div class="modal show d-block" tabindex="-1" style="background: rgba(15, 23, 42, 0.55);">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h2 class="panel-title mb-1">Edit User</h2><p class="panel-subtitle mb-0">Update account details, role, or optionally reset this password.</p></div>
                    <button type="button" class="btn-close" aria-label="Close" onclick="window.location.href='manage-users.php'"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($editUserErrors)): ?>
                        <div class="alert alert-danger mb-3"><ul><?php foreach ($editUserErrors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" value="<?php echo (int) $editUser['user_id']; ?>">

                        <div class="form-grid">
                            <div class="form-field"><label class="form-label" for="edit-name">Full Name</label><input type="text" id="edit-name" name="name" class="form-control" maxlength="100" value="<?php echo htmlspecialchars((string) $editUser['name'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                            <div class="form-field"><label class="form-label" for="edit-email">Email</label><input type="email" id="edit-email" name="email" class="form-control" maxlength="150" value="<?php echo htmlspecialchars((string) $editUser['email'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                            <div class="form-field"><label class="form-label" for="edit-phone">Phone</label><input type="text" id="edit-phone" name="phone" class="form-control" maxlength="30" value="<?php echo htmlspecialchars((string) ($editUser['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="form-field"><label class="form-label" for="edit-role">Role</label><select id="edit-role" name="role" class="form-select"><option value="customer" <?php echo ($editUser['role'] === 'customer') ? 'selected' : ''; ?>>Customer</option><option value="admin" <?php echo ($editUser['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option></select></div>
                            <div class="form-field full"><label class="form-label" for="edit-password">Reset Password</label><input type="password" id="edit-password" name="password" class="form-control" minlength="6" maxlength="255" placeholder="Leave blank to keep the current password"><div class="form-help">Only fill this in if you want to change the user’s password. The new password will be hashed before it is saved.</div></div>
                        </div>

                        <div class="modal-footer px-0 pb-0"><button type="button" class="btn-surface" onclick="window.location.href='manage-users.php'">Cancel</button><button type="submit" class="btn-primary-soft"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
<div class="modal fade" id="bookingHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="panel-title mb-1" id="bookingHistoryModalTitle">Booking History</h2>
                    <p class="panel-subtitle mb-0" id="bookingHistoryModalSubtitle">Past bookings for this user.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="bookingHistoryList" class="history-list"></div>
            </div>
        </div>
    </div>
</div>
<script>
    const USER_BOOKING_HISTORY = <?php echo $userBookingHistoryJson ?: '{}'; ?>;
    const usersTableBody = document.querySelector('#usersTable tbody');
    const searchInput = document.getElementById('searchInput');
    const roleFilter = document.getElementById('roleFilter');
    const bookingFilter = document.getElementById('bookingFilter');
    const sortFilter = document.getElementById('sortFilter');
    const resultsCount = document.getElementById('resultsCount');
    const emptyState = document.getElementById('emptyState');
    const userRows = Array.from(usersTableBody.querySelectorAll('tr'));
    const bookingHistoryModalElement = document.getElementById('bookingHistoryModal');
    const bookingHistoryList = document.getElementById('bookingHistoryList');
    const bookingHistoryModalTitle = document.getElementById('bookingHistoryModalTitle');
    const bookingHistoryModalSubtitle = document.getElementById('bookingHistoryModalSubtitle');
    let bookingHistoryModal = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatHistoryDate(dateValue) {
        if (!dateValue) return 'Unknown date';
        const parsed = new Date(`${dateValue}T00:00:00`);
        return Number.isNaN(parsed.getTime()) ? dateValue : parsed.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatHistoryTime(timeValue) {
        if (!timeValue) return '';
        const parsed = new Date(`1970-01-01T${timeValue}`);
        return Number.isNaN(parsed.getTime()) ? timeValue : parsed.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    }

    function renderBookingHistory(userId, userName) {
        if (!bookingHistoryModal || !bookingHistoryList || !bookingHistoryModalTitle || !bookingHistoryModalSubtitle) {
            if (!bookingHistoryModalElement || !bookingHistoryList || !bookingHistoryModalTitle || !bookingHistoryModalSubtitle || typeof bootstrap === 'undefined') {
                return;
            }

            bookingHistoryModal = new bootstrap.Modal(bookingHistoryModalElement);
        }

        const bookings = Array.isArray(USER_BOOKING_HISTORY[String(userId)]) ? USER_BOOKING_HISTORY[String(userId)] : [];
        bookingHistoryModalTitle.textContent = `${userName} Booking History`;
        bookingHistoryModalSubtitle.textContent = bookings.length
            ? `${bookings.length} past booking${bookings.length === 1 ? '' : 's'} recorded for this registered user.`
            : 'No past bookings recorded for this registered user.';

        if (!bookings.length) {
            bookingHistoryList.innerHTML = `<div class="history-empty">No past bookings recorded for this user yet.</div>`;
            bookingHistoryModal.show();
            return;
        }

        bookingHistoryList.innerHTML = bookings.map((booking) => {
            const tableSummary = booking.assigned_table_numbers ? `Table ${escapeHtml(booking.assigned_table_numbers)}` : 'No table recorded';
            const placementSummary = booking.reservation_card_status_label ? escapeHtml(booking.reservation_card_status_label) : '';
            const sourceSummary = booking.booking_source === 'admin_manual' && booking.created_by_name
                ? `${escapeHtml(booking.booking_source_label)} by ${escapeHtml(booking.created_by_name)}`
                : escapeHtml(booking.booking_source_label || '');
            const statusClass = String(booking.status || '').replace('_', '-');

            return `
                <article class="history-card">
                    <div class="history-card-top">
                        <div>
                            <h3 class="history-card-title">${escapeHtml(formatHistoryDate(booking.booking_date))}</h3>
                            <div class="history-card-meta">
                                <span>${escapeHtml(formatHistoryTime(booking.start_time))} - ${escapeHtml(formatHistoryTime(booking.end_time))}</span>
                                <span>P${escapeHtml(booking.number_of_guests)}</span>
                                <span>${tableSummary}</span>
                                ${sourceSummary ? `<span>${sourceSummary}</span>` : ''}
                            </div>
                        </div>
                        <span class="booking-badge ${escapeHtml(statusClass === 'completed' ? 'has-bookings' : 'no-bookings')}">
                            ${escapeHtml(booking.status_label || booking.status)}
                        </span>
                    </div>
                    ${placementSummary ? `<div class="history-card-note">Reservation card: ${placementSummary}</div>` : ''}
                </article>
            `;
        }).join('');

        bookingHistoryModal.show();
    }

    function applyFilters() {
        const searchValue = searchInput.value.trim().toLowerCase();
        const roleValue = roleFilter.value;
        const bookingValue = bookingFilter.value;
        const sortValue = sortFilter.value;
        const rows = userRows.slice();

        rows.sort((left, right) => {
            if (sortValue === 'oldest') return Number(left.dataset.created || 0) - Number(right.dataset.created || 0);
            if (sortValue === 'name-asc') return String(left.dataset.name || '').localeCompare(String(right.dataset.name || ''));
            if (sortValue === 'bookings-desc') return Number(right.dataset.bookings || 0) - Number(left.dataset.bookings || 0);
            return Number(right.dataset.created || 0) - Number(left.dataset.created || 0);
        });

        rows.forEach((row) => usersTableBody.appendChild(row));

        let visibleCount = 0;
        rows.forEach((row) => {
            const haystack = [row.dataset.name, row.dataset.email, row.dataset.phone].join(' ');
            const rowRole = row.dataset.role || '';
            const rowBookings = Number(row.dataset.bookings || 0);
            const matchesSearch = searchValue === '' || haystack.includes(searchValue);
            const matchesRole = roleValue === 'all' || rowRole === roleValue;
            const matchesBookings = bookingValue === 'all' || (bookingValue === 'with-bookings' && rowBookings > 0) || (bookingValue === 'no-bookings' && rowBookings === 0);
            const isVisible = matchesSearch && matchesRole && matchesBookings;
            row.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount += 1;
        });

        resultsCount.textContent = `${visibleCount} user${visibleCount === 1 ? '' : 's'} shown`;
        emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    [searchInput, roleFilter, bookingFilter, sortFilter].forEach((element) => {
        element.addEventListener('input', applyFilters);
        element.addEventListener('change', applyFilters);
    });

    document.querySelectorAll('.booking-trigger').forEach((button) => {
        button.addEventListener('click', () => {
            renderBookingHistory(button.dataset.userId || '', button.dataset.userName || 'User');
        });
    });

    setTimeout(() => {
        document.querySelectorAll('.alert').forEach((alert) => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 3500);

    applyFilters();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
