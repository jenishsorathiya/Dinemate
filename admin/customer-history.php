<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);

if (empty($_SESSION['customer_history_csrf'])) {
    $_SESSION['customer_history_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['customer_history_csrf'];

$redirectToCustomerHistory = static function (string $queryString = ''): void {
    $target = 'customer-history.php';
    if ($queryString !== '') {
        $target .= '?' . ltrim($queryString, '?');
    }
    header("Location: {$target}");
    exit();
};

$verifyCsrf = static function () use ($csrfToken, $redirectToCustomerHistory): void {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals($csrfToken, $submittedToken)) {
        setFlashMessage('error', 'Your session expired. Please try again.');
        $redirectToCustomerHistory((string) ($_POST['return_query'] ?? ''));
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verifyCsrf();

    $action = (string) ($_POST['action'] ?? '');
    $profileId = (int) ($_POST['customer_profile_id'] ?? 0);
    $returnQuery = (string) ($_POST['return_query'] ?? '');

    if ($profileId < 1) {
        setFlashMessage('error', 'A valid customer profile is required.');
        $redirectToCustomerHistory($returnQuery);
    }

    if ($action === 'link_account') {
        $linkUserId = (int) ($_POST['link_user_id'] ?? 0);
        if ($linkUserId < 1) {
            setFlashMessage('warning', 'Please choose a registered customer account to link.');
            $redirectToCustomerHistory($returnQuery);
        }

        $accountStmt = $pdo->prepare("
            SELECT user_id, name, email
            FROM users
            WHERE user_id = ?
              AND role = 'customer'
              AND email NOT LIKE '%@admin-booking.local'
              AND email NOT LIKE 'guest-%@local.dinemate'
            LIMIT 1
        ");
        $accountStmt->execute([$linkUserId]);
        $linkedAccount = $accountStmt->fetch(PDO::FETCH_ASSOC);

        if (!$linkedAccount) {
            setFlashMessage('warning', 'That account is not a valid registered customer.');
            $redirectToCustomerHistory($returnQuery);
        }

        $conflictStmt = $pdo->prepare("
            SELECT customer_profile_id, name
            FROM customer_profiles
            WHERE linked_user_id = ?
              AND customer_profile_id != ?
            LIMIT 1
        ");
        $conflictStmt->execute([$linkUserId, $profileId]);
        $conflictingProfile = $conflictStmt->fetch(PDO::FETCH_ASSOC);

        if ($conflictingProfile) {
            setFlashMessage('warning', 'That account is already linked to another customer profile.');
            $redirectToCustomerHistory($returnQuery);
        }

        $updateStmt = $pdo->prepare("UPDATE customer_profiles SET linked_user_id = ? WHERE customer_profile_id = ?");
        $updateStmt->execute([$linkUserId, $profileId]);

        setFlashMessage('success', 'Customer profile linked to the selected registered account.');
        $redirectToCustomerHistory($returnQuery);
    }

    if ($action === 'unlink_account') {
        $updateStmt = $pdo->prepare("UPDATE customer_profiles SET linked_user_id = NULL WHERE customer_profile_id = ?");
        $updateStmt->execute([$profileId]);
        setFlashMessage('success', 'Customer profile unlinked from the registered account.');
        $redirectToCustomerHistory($returnQuery);
    }

    setFlashMessage('warning', 'Unknown customer profile action.');
    $redirectToCustomerHistory($returnQuery);
}

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$selectedProfileId = (int) ($_GET['profile'] ?? 0);
$returnQuery = http_build_query(array_filter([
    'q' => $searchQuery !== '' ? $searchQuery : null,
    'profile' => $selectedProfileId > 0 ? $selectedProfileId : null,
]));

$profileSql = "
    SELECT
        cp.customer_profile_id,
        cp.name,
        cp.email,
        cp.phone,
        cp.linked_user_id,
        linked_user.name AS linked_user_name,
        linked_user.email AS linked_user_email,
        COUNT(b.booking_id) AS booking_count,
        MAX(b.booking_date) AS last_booking_date,
        SUM(CASE WHEN b.booking_source = 'admin_manual' THEN 1 ELSE 0 END) AS admin_booking_count,
        SUM(CASE WHEN b.booking_source = 'guest_web' THEN 1 ELSE 0 END) AS guest_booking_count,
        SUM(CASE WHEN b.booking_source = 'customer_account' THEN 1 ELSE 0 END) AS account_booking_count
    FROM customer_profiles cp
    LEFT JOIN users linked_user ON cp.linked_user_id = linked_user.user_id
    LEFT JOIN bookings b ON b.customer_profile_id = cp.customer_profile_id
    WHERE 1 = 1
";

$profileParams = [];
if ($searchQuery !== '') {
    $profileSql .= " AND (cp.name LIKE ? OR cp.email LIKE ? OR cp.phone LIKE ?)";
    $profileParams[] = '%' . $searchQuery . '%';
    $profileParams[] = '%' . $searchQuery . '%';
    $profileParams[] = '%' . $searchQuery . '%';
}

$profileSql .= "
    GROUP BY
        cp.customer_profile_id,
        cp.name,
        cp.email,
        cp.phone,
        cp.linked_user_id,
        linked_user.name,
        linked_user.email
    ORDER BY booking_count DESC, last_booking_date DESC, cp.name ASC
    LIMIT 150
";

$profilesStmt = $pdo->prepare($profileSql);
$profilesStmt->execute($profileParams);
$profiles = $profilesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$linkableUsersStmt = $pdo->query("
    SELECT user_id, name, email
    FROM users
    WHERE role = 'customer'
      AND email NOT LIKE '%@admin-booking.local'
      AND email NOT LIKE 'guest-%@local.dinemate'
    ORDER BY name ASC, email ASC, user_id ASC
");
$linkableUsers = $linkableUsersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalProfiles = count($profiles);
$profilesWithLinkedAccounts = 0;
$profilesWithAdminBookings = 0;
$profilesGuestOnly = 0;
$totalBookingsAcrossProfiles = 0;

foreach ($profiles as $profileRow) {
    $bookingCount = (int) ($profileRow['booking_count'] ?? 0);
    $adminBookingCount = (int) ($profileRow['admin_booking_count'] ?? 0);
    $guestBookingCount = (int) ($profileRow['guest_booking_count'] ?? 0);
    $accountBookingCount = (int) ($profileRow['account_booking_count'] ?? 0);

    $totalBookingsAcrossProfiles += $bookingCount;
    if (!empty($profileRow['linked_user_id'])) {
        $profilesWithLinkedAccounts++;
    }
    if ($adminBookingCount > 0) {
        $profilesWithAdminBookings++;
    }
    if ($guestBookingCount > 0 && $accountBookingCount === 0 && $adminBookingCount === 0) {
        $profilesGuestOnly++;
    }
}

$selectedProfile = null;
$selectedProfileBookings = [];

if ($selectedProfileId > 0) {
    $selectedProfileStmt = $pdo->prepare("
        SELECT
            cp.customer_profile_id,
            cp.name,
            cp.email,
            cp.phone,
            cp.linked_user_id,
            linked_user.name AS linked_user_name,
            linked_user.email AS linked_user_email
        FROM customer_profiles cp
        LEFT JOIN users linked_user ON cp.linked_user_id = linked_user.user_id
        WHERE cp.customer_profile_id = ?
        LIMIT 1
    ");
    $selectedProfileStmt->execute([$selectedProfileId]);
    $selectedProfile = $selectedProfileStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($selectedProfile) {
        $selectedBookingsStmt = $pdo->prepare("
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
        $selectedBookingsStmt->execute([$selectedProfileId]);
        $selectedProfileBookings = $selectedBookingsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$adminPageTitle = 'Customer History';
$adminPageIcon = 'fa-address-book';
$adminNotificationCount = $totalProfiles;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'customers';
$adminSidebarPathPrefix = '';
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer History | DineMate Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard-theme.css" rel="stylesheet">
    <style>
        :root { --page-bg: #f6f8fc; --surface: #ffffff; --line: #e5ebf4; --text-main: #172033; --text-muted: #6b768b; --shadow-soft: 0 12px 26px rgba(15, 23, 42, 0.05); --shadow-card: 0 24px 48px rgba(15, 23, 42, 0.08); --primary: #1f3c88; --accent: #d8a230; --success: #1f8f63; --danger: #c94b62; --warning: #c9831f; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: linear-gradient(180deg, #f5f7fb 0%, #eff4fb 100%); color: var(--text-main); }
        .admin-layout { display: flex; min-height: 100vh; }
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .main { flex: 1; overflow-y: auto; padding: 28px; }
        .page-shell { max-width: 1440px; margin: 0 auto; display: grid; gap: 22px; }
        .hero-card, .panel-card, .stat-card { background: var(--surface); border: 1px solid var(--line); border-radius: 24px; box-shadow: var(--shadow-soft); }
        .hero-card { padding: 28px; box-shadow: var(--shadow-card); background: radial-gradient(circle at top right, rgba(216, 162, 48, 0.18), transparent 28%), radial-gradient(circle at bottom left, rgba(31, 60, 136, 0.12), transparent 34%), #ffffff; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; color: var(--accent); font-size: 12px; font-weight: 800; letter-spacing: 0.14em; text-transform: uppercase; margin-bottom: 12px; }
        .hero-title { margin: 0; font-size: clamp(28px, 4vw, 40px); line-height: 1.05; letter-spacing: -0.04em; }
        .hero-copy { margin: 12px 0 0; color: var(--text-muted); max-width: 820px; font-size: 15px; line-height: 1.7; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .stat-card { padding: 20px; }
        .stat-label { color: var(--text-muted); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
        .stat-value { margin-top: 14px; font-size: 34px; font-weight: 800; letter-spacing: -0.05em; }
        .stat-meta { margin-top: 8px; color: var(--text-muted); font-size: 13px; }
        .layout-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(360px, 0.85fr); gap: 22px; align-items: start; }
        .panel-card { padding: 24px; }
        .panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .panel-title { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.03em; }
        .panel-subtitle { margin: 6px 0 0; color: var(--text-muted); font-size: 13px; }
        .inline-chip { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; background: #f7f9fc; border: 1px solid var(--line); color: #44506a; font-size: 12px; font-weight: 700; }
        .search-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; margin-bottom: 18px; }
        .search-input { min-height: 46px; border-radius: 14px; border: 1px solid var(--line); background: #fbfcfe; padding: 12px 14px; }
        .btn-primary-soft { border: 1px solid var(--primary); border-radius: 14px; min-height: 46px; padding: 0 16px; background: var(--primary); color: #fff; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
        .table-wrap { border: 1px solid var(--line); border-radius: 20px; overflow: hidden; background: #ffffff; }
        .table-custom { width: 100%; border-collapse: collapse; }
        .table-custom thead th { background: #f8fafd; color: #52607a; font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; padding: 16px 18px; border-bottom: 1px solid var(--line); white-space: nowrap; }
        .table-custom tbody td { padding: 18px; border-bottom: 1px solid #edf2f7; vertical-align: top; }
        .table-custom tbody tr:last-child td { border-bottom: 0; }
        .table-custom tbody tr:hover { background: #fbfcff; }
        .profile-name { font-weight: 700; }
        .profile-meta { display: block; margin-top: 6px; color: var(--text-muted); font-size: 12px; }
        .tiny-badge { display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #eef4ff; color: #315cba; }
        .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status-pill.pending { background: #fff7e6; color: #c9831f; }
        .status-pill.confirmed { background: #eaf2ff; color: #3052a3; }
        .status-pill.completed { background: #e9f7ef; color: #1f8f63; }
        .status-pill.cancelled { background: #fff0f3; color: #c94b62; }
        .status-pill.no-show { background: #f1efff; color: #5b56c2; }
        .history-list { display: grid; gap: 12px; }
        .history-card { border: 1px solid var(--line); border-radius: 18px; padding: 16px 18px; background: #fbfcff; }
        .history-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .history-card-title { margin: 0; font-size: 15px; font-weight: 800; }
        .history-card-meta { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 10px; color: var(--text-muted); font-size: 13px; }
        .history-card-note { margin-top: 10px; color: var(--text-muted); font-size: 12px; }
        .empty-state { padding: 28px 18px; text-align: center; color: var(--text-muted); font-size: 14px; border: 1px dashed var(--line); border-radius: 20px; background: #fbfcff; }
        .form-select, .btn-surface { min-height: 46px; border-radius: 14px; border: 1px solid var(--line); background: #fbfcfe; padding: 12px 14px; color: var(--text-main); }
        .btn-surface { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 13px; font-weight: 700; text-decoration: none; }
        .link-form { display: grid; gap: 12px; margin-top: 16px; }
        .link-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .alert { border-radius: 16px; border: 1px solid transparent; padding: 16px 18px; margin: 0; }
        @media (max-width: 1200px) { .stats-grid, .layout-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .main { padding: 18px; } .stats-grid, .search-row { grid-template-columns: 1fr; } .table-custom thead { display: none; } .table-custom, .table-custom tbody, .table-custom tr, .table-custom td { display: block; width: 100%; } .table-custom tbody tr { padding: 16px 16px 12px; border-bottom: 1px solid #edf2f7; } .table-custom tbody td { padding: 8px 0; border: 0; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/admin-sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/admin-topbar.php'; ?>
        <div class="main">
            <div class="page-shell">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'error' ? 'danger' : $flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <section class="hero-card">
                    <div class="eyebrow"><i class="fa-solid fa-address-book"></i> Customer Profiles</div>
                    <h1 class="hero-title">Track every real customer, even when they never create an account.</h1>
                    <p class="hero-copy">This view groups bookings into reusable customer profiles so phone bookings, email bookings, guest web bookings, and registered account bookings can be seen as one customer history whenever the details match.</p>
                </section>

                <section class="stats-grid">
                    <article class="stat-card"><div class="stat-label">Profiles</div><div class="stat-value"><?php echo number_format($totalProfiles); ?></div><div class="stat-meta">Customer identities currently visible in search results.</div></article>
                    <article class="stat-card"><div class="stat-label">Bookings</div><div class="stat-value"><?php echo number_format($totalBookingsAcrossProfiles); ?></div><div class="stat-meta">Bookings tied to the customer profiles in this result set.</div></article>
                    <article class="stat-card"><div class="stat-label">With Account</div><div class="stat-value"><?php echo number_format($profilesWithLinkedAccounts); ?></div><div class="stat-meta">Profiles already linked to a registered DineMate user.</div></article>
                    <article class="stat-card"><div class="stat-label">Admin Entered</div><div class="stat-value"><?php echo number_format($profilesWithAdminBookings); ?></div><div class="stat-meta">Profiles with at least one booking entered manually by admin.</div></article>
                </section>

                <section class="layout-grid">
                    <article class="panel-card">
                        <div class="panel-heading">
                            <div><h2 class="panel-title">Customer Search</h2><p class="panel-subtitle">Search by name, email, or phone to find customer histories outside registered accounts.</p></div>
                            <span class="inline-chip"><i class="fa-solid fa-users-viewfinder"></i> <?php echo number_format($totalProfiles); ?> matches</span>
                        </div>

                        <form method="GET" class="search-row">
                            <input type="text" name="q" class="search-input" placeholder="Search customer name, email, or phone..." value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn-primary-soft"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                                <a href="customer-history.php" class="btn-primary-soft" style="background:#fff;color:var(--text-main);border-color:var(--line);"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <div class="table-responsive">
                                <table class="table-custom">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Bookings</th>
                                            <th>Last Booking</th>
                                            <th>Channels</th>
                                            <th>History</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($profiles)): ?>
                                        <?php foreach ($profiles as $profile): ?>
                                            <?php $lastBookingTs = !empty($profile['last_booking_date']) ? strtotime((string) $profile['last_booking_date']) : false; ?>
                                            <tr>
                                                <td>
                                                    <div class="profile-name"><?php echo htmlspecialchars((string) $profile['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <span class="profile-meta"><?php echo htmlspecialchars((string) (($profile['email'] ?? '') !== '' ? $profile['email'] : 'No email'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="profile-meta"><?php echo htmlspecialchars((string) (($profile['phone'] ?? '') !== '' ? $profile['phone'] : 'No phone'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php if (!empty($profile['linked_user_id'])): ?>
                                                        <span class="profile-meta">Linked account: <?php echo htmlspecialchars((string) ($profile['linked_user_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="tiny-badge"><i class="fa-solid fa-calendar-check"></i><?php echo number_format((int) ($profile['booking_count'] ?? 0)); ?> bookings</span></td>
                                                <td><?php echo $lastBookingTs !== false ? htmlspecialchars(date('M d, Y', $lastBookingTs), ENT_QUOTES, 'UTF-8') : 'No bookings yet'; ?></td>
                                                <td>
                                                    <span class="profile-meta"><?php echo (int) ($profile['admin_booking_count'] ?? 0); ?> admin</span>
                                                    <span class="profile-meta"><?php echo (int) ($profile['guest_booking_count'] ?? 0); ?> guest web</span>
                                                    <span class="profile-meta"><?php echo (int) ($profile['account_booking_count'] ?? 0); ?> account</span>
                                                </td>
                                                <td><a class="btn-primary-soft" href="?<?php echo htmlspecialchars(http_build_query(['q' => $searchQuery, 'profile' => (int) $profile['customer_profile_id']]), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-clock-rotate-left"></i> View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5"><div class="empty-state">No customer profiles matched that search yet.</div></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </article>

                    <article class="panel-card">
                        <div class="panel-heading">
                            <div>
                                <h2 class="panel-title"><?php echo $selectedProfile ? htmlspecialchars((string) $selectedProfile['name'], ENT_QUOTES, 'UTF-8') : 'Customer History'; ?></h2>
                                <p class="panel-subtitle">
                                    <?php if ($selectedProfile): ?>
                                        Full booking history for this customer profile, including phone/email/admin-entered bookings.
                                    <?php else: ?>
                                        Choose a customer profile from the left to inspect the full booking history.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($selectedProfile): ?>
                                <span class="inline-chip"><i class="fa-solid fa-clock-rotate-left"></i> <?php echo number_format(count($selectedProfileBookings)); ?> bookings</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($selectedProfile): ?>
                            <div class="history-list">
                                <div class="history-card">
                                    <div class="history-card-title">Profile Details</div>
                                    <div class="history-card-meta">
                                        <span><?php echo htmlspecialchars((string) (($selectedProfile['email'] ?? '') !== '' ? $selectedProfile['email'] : 'No email'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo htmlspecialchars((string) (($selectedProfile['phone'] ?? '') !== '' ? $selectedProfile['phone'] : 'No phone'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo !empty($selectedProfile['linked_user_id']) ? htmlspecialchars('Linked account: ' . ((string) ($selectedProfile['linked_user_name'] ?? 'User')), ENT_QUOTES, 'UTF-8') : 'No linked account'; ?></span>
                                    </div>
                                    <div class="link-form">
                                        <div class="link-actions">
                                            <a
                                                href="timeline/new-dashboard.php?<?php echo htmlspecialchars(http_build_query([
                                                    'date' => date('Y-m-d'),
                                                    'prefill_customer_profile_id' => (int) $selectedProfile['customer_profile_id'],
                                                    'prefill_customer_name' => (string) ($selectedProfile['name'] ?? ''),
                                                    'prefill_customer_email' => (string) ($selectedProfile['email'] ?? ''),
                                                    'prefill_customer_phone' => (string) ($selectedProfile['phone'] ?? ''),
                                                ]), ENT_QUOTES, 'UTF-8'); ?>"
                                                class="btn-primary-soft"
                                            >
                                                <i class="fa-solid fa-calendar-plus"></i> Create Booking For This Customer
                                            </a>
                                        </div>
                                        <?php if (!empty($selectedProfile['linked_user_id'])): ?>
                                            <form method="POST" class="link-actions">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="unlink_account">
                                                <input type="hidden" name="customer_profile_id" value="<?php echo (int) $selectedProfile['customer_profile_id']; ?>">
                                                <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn-surface" onclick="return confirm('Unlink this customer profile from the registered account?');">
                                                    <i class="fa-solid fa-link-slash"></i> Unlink Account
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="link-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="link_account">
                                                <input type="hidden" name="customer_profile_id" value="<?php echo (int) $selectedProfile['customer_profile_id']; ?>">
                                                <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8'); ?>">
                                                <select name="link_user_id" class="form-select" required>
                                                    <option value="">Link to a registered customer account...</option>
                                                    <?php foreach ($linkableUsers as $linkableUser): ?>
                                                        <option value="<?php echo (int) $linkableUser['user_id']; ?>">
                                                            <?php echo htmlspecialchars((string) $linkableUser['name'] . ' • ' . (string) $linkableUser['email'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="link-actions">
                                                    <button type="submit" class="btn-primary-soft">
                                                        <i class="fa-solid fa-link"></i> Link Account
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($selectedProfileBookings)): ?>
                                    <?php foreach ($selectedProfileBookings as $booking): ?>
                                        <?php
                                        $sourceSummary = getBookingSourceLabel($booking['booking_source'] ?? '');
                                        if (($booking['booking_source'] ?? '') === 'admin_manual' && !empty($booking['created_by_name'])) {
                                            $sourceSummary .= ' by ' . $booking['created_by_name'];
                                        }
                                        $tableSummary = trim((string) ($booking['assigned_table_numbers'] ?? '')) !== ''
                                            ? 'Table ' . $booking['assigned_table_numbers']
                                            : 'No table recorded';
                                        $placementSummary = !empty($booking['reservation_card_status'])
                                            ? getBookingPlacementLabel($booking['reservation_card_status'])
                                            : '';
                                        ?>
                                        <article class="history-card">
                                            <div class="history-card-top">
                                                <div>
                                                    <h3 class="history-card-title"><?php echo htmlspecialchars(date('M d, Y', strtotime((string) $booking['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <div class="history-card-meta">
                                                        <span><?php echo htmlspecialchars(date('g:i A', strtotime((string) $booking['start_time'])) . ' - ' . date('g:i A', strtotime((string) $booking['end_time'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <span>P<?php echo (int) ($booking['number_of_guests'] ?? 0); ?></span>
                                                        <span><?php echo htmlspecialchars($tableSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <span><?php echo htmlspecialchars($sourceSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                </div>
                                                <span class="status-pill <?php echo htmlspecialchars(str_replace('_', '-', (string) ($booking['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars(getBookingStatusLabel($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </div>
                                            <?php if ($placementSummary !== ''): ?>
                                                <div class="history-card-note">Reservation card: <?php echo htmlspecialchars($placementSummary, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">This customer profile exists, but there are no bookings linked to it yet.</div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">Select a customer from the left to see a combined booking history across guest, phone/email, admin-entered, and registered-account bookings.</div>
                        <?php endif; ?>
                    </article>
                </section>
            </div>
        </div>
    </div>
</div>
</body>
</html>
