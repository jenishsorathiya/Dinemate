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

    if ($action === 'save_notes') {
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $updateStmt = $pdo->prepare("UPDATE customer_profiles SET notes = ? WHERE customer_profile_id = ?");
        $updateStmt->execute([$notes !== '' ? $notes : null, $profileId]);
        setFlashMessage('success', 'Customer notes updated successfully.');
        $redirectToCustomerHistory($returnQuery);
    }

    if ($action === 'merge_profile') {
        $targetProfileId = (int) ($_POST['merge_target_profile_id'] ?? 0);
        if ($targetProfileId < 1 || $targetProfileId === $profileId) {
            setFlashMessage('warning', 'Please choose a different target customer profile.');
            $redirectToCustomerHistory($returnQuery);
        }

        $profileLookupStmt = $pdo->prepare("SELECT * FROM customer_profiles WHERE customer_profile_id = ? LIMIT 1");
        $profileLookupStmt->execute([$profileId]);
        $sourceProfile = $profileLookupStmt->fetch(PDO::FETCH_ASSOC);

        $profileLookupStmt->execute([$targetProfileId]);
        $targetProfile = $profileLookupStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sourceProfile || !$targetProfile) {
            setFlashMessage('warning', 'One of the customer profiles could not be found.');
            $redirectToCustomerHistory($returnQuery);
        }

        if (!empty($sourceProfile['linked_user_id']) && !empty($targetProfile['linked_user_id']) && (int) $sourceProfile['linked_user_id'] !== (int) $targetProfile['linked_user_id']) {
            setFlashMessage('warning', 'Cannot merge profiles that are linked to different registered accounts.');
            $redirectToCustomerHistory($returnQuery);
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

        $bookingUpdateStmt = $pdo->prepare("UPDATE bookings SET customer_profile_id = ? WHERE customer_profile_id = ?");
        $bookingUpdateStmt->execute([$targetProfileId, $profileId]);

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
        $deleteProfileStmt->execute([$profileId]);

        $pdo->commit();

        parse_str($returnQuery, $returnParams);
        $returnParams['profile'] = $targetProfileId;
        setFlashMessage('success', 'Customer profiles merged successfully.');
        $redirectToCustomerHistory(http_build_query($returnParams));
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
        SUM(COALESCE(b.spend_amount, 0.00)) AS total_spend,
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

$topSpendersStmt = $pdo->query("
    SELECT
        cp.customer_profile_id,
        cp.name,
        cp.email,
        cp.phone,
        COUNT(b.booking_id) AS booking_count,
        SUM(COALESCE(b.spend_amount, 0.00)) AS total_spend
    FROM customer_profiles cp
    LEFT JOIN bookings b ON b.customer_profile_id = cp.customer_profile_id
    GROUP BY cp.customer_profile_id, cp.name, cp.email, cp.phone
    ORDER BY total_spend DESC, booking_count DESC, cp.name ASC
    LIMIT 10
");
$topSpenders = $topSpendersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
$totalSpendAcrossProfiles = 0.0;

foreach ($profiles as $profileRow) {
    $bookingCount = (int) ($profileRow['booking_count'] ?? 0);
    $adminBookingCount = (int) ($profileRow['admin_booking_count'] ?? 0);
    $guestBookingCount = (int) ($profileRow['guest_booking_count'] ?? 0);
    $accountBookingCount = (int) ($profileRow['account_booking_count'] ?? 0);
    $profileSpend = (float) ($profileRow['total_spend'] ?? 0.0);

    $totalBookingsAcrossProfiles += $bookingCount;
    $totalSpendAcrossProfiles += $profileSpend;
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
            cp.notes,
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
                b.spend_amount,
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
                b.spend_amount,
                creator.name
            ORDER BY b.booking_date DESC, b.start_time DESC, b.booking_id DESC
        ");
        $selectedBookingsStmt->execute([$selectedProfileId]);
        $selectedProfileBookings = $selectedBookingsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$selectedProfileTotalSpend = 0.0;
if (!empty($selectedProfileBookings)) {
    foreach ($selectedProfileBookings as $bookingRow) {
        $selectedProfileTotalSpend += (float) ($bookingRow['spend_amount'] ?? 0.0);
    }
}

$adminPageTitle = 'Guests';
$adminPageIcon = 'fa-address-book';
$adminNotificationCount = $totalProfiles;
$adminProfileName = $_SESSION['name'] ?? 'Admin';
$adminSidebarActive = 'guests';
$adminSidebarPathPrefix = '';
$flash = getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <?php include __DIR__ . '/../partials/admin-modernize.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/pages/admin-customer-history.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>
    <div class="main-content">
        <div class="main">
            <div class="admin-workspace admin-ops page-shell">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'error' ? 'danger' : $flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <header class="admin-page-heading">
                    <div>
                        <p class="admin-page-kicker">Guest Memory</p>
                        <h1 class="admin-page-title">Guests</h1>
                        <p class="admin-page-copy">Keep guest profiles, account links, notes, and booking history clean for service follow-up.</p>
                    </div>
                    <div class="admin-actions">
                        <span class="admin-chip"><?php echo number_format($totalProfiles); ?> profiles</span>
                        <span class="admin-chip is-success"><?php echo number_format($profilesWithLinkedAccounts); ?> linked</span>
                        <span class="admin-chip is-primary"><?php echo number_format($totalBookingsAcrossProfiles); ?> bookings</span>
                    </div>
                </header>

                <section class="ops-metric-grid" aria-label="Guest summary">
                    <div class="ops-metric">
                        <span>Profiles</span>
                        <strong><?php echo number_format($totalProfiles); ?></strong>
                        <small>Visible customer records</small>
                    </div>
                    <div class="ops-metric">
                        <span>Linked</span>
                        <strong><?php echo number_format($profilesWithLinkedAccounts); ?></strong>
                        <small>Connected to accounts</small>
                    </div>
                    <div class="ops-metric">
                        <span>Bookings</span>
                        <strong><?php echo number_format($totalBookingsAcrossProfiles); ?></strong>
                        <small>Across visible profiles</small>
                    </div>
                    <div class="ops-metric">
                        <span>Spend</span>
                        <strong>$<?php echo number_format($totalSpendAcrossProfiles, 0); ?></strong>
                        <small>Recorded profile spend</small>
                    </div>
                </section>

                <div class="admin-command-bar">
                    <div class="admin-command-group">
                        <span class="admin-command-note">Visible profile spend: $<?php echo number_format($totalSpendAcrossProfiles, 2); ?></span>
                        <span class="admin-command-note"><?php echo number_format($profilesWithAdminBookings); ?> profiles have admin-entered bookings</span>
                    </div>
                    <?php if (!empty($topSpenders[0])): ?>
                        <div class="admin-command-group">
                            <span class="admin-chip is-primary">Top guest: <?php echo htmlspecialchars((string) $topSpenders[0]['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <section class="admin-split-layout layout-grid">
                    <article class="admin-panel panel-card">
                        <div class="admin-panel-header panel-heading">
                            <div><h2 class="admin-panel-title panel-title">Guest Directory</h2><p class="admin-panel-copy panel-subtitle">Search by name, email, or phone.</p></div>
                            <span class="admin-chip"><i class="fa-solid fa-users-viewfinder"></i> <?php echo number_format($totalProfiles); ?> matches</span>
                        </div>

                        <form method="GET" class="search-row">
                            <input type="text" name="q" class="search-input" placeholder="Search customer name, email, or phone..." value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn-primary-soft"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                                <a href="customer-history.php" class="btn-primary-soft" style="background:var(--dm-surface);color:var(--text-main);border-color:var(--line);"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <div class="table-responsive">
                                <table class="table-custom">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Bookings</th>
                                            <th>Spend</th>
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
                                                    <span class="profile-meta"><?php echo htmlspecialchars((string) (($profile['email'] ?? '') !== '' ? $profile['email'] : '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="profile-meta"><?php echo htmlspecialchars((string) (($profile['phone'] ?? '') !== '' ? $profile['phone'] : '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php if (!empty($profile['linked_user_id'])): ?>
                                                        <span class="profile-meta">Linked account: <?php echo htmlspecialchars((string) ($profile['linked_user_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="tiny-badge"><i class="fa-solid fa-calendar-check"></i><?php echo number_format((int) ($profile['booking_count'] ?? 0)); ?> bookings</span></td>
                                                <td>$<?php echo number_format((float) ($profile['total_spend'] ?? 0.0), 2); ?></td>
                                                <td><?php echo $lastBookingTs !== false ? htmlspecialchars(date('M d, Y', $lastBookingTs), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                                <td>
                                                    <span class="profile-meta"><?php echo (int) ($profile['admin_booking_count'] ?? 0); ?> admin</span>
                                                    <span class="profile-meta"><?php echo (int) ($profile['guest_booking_count'] ?? 0); ?> guest web</span>
                                                    <span class="profile-meta"><?php echo (int) ($profile['account_booking_count'] ?? 0); ?> account</span>
                                                </td>
                                                <td><a class="btn-primary-soft" href="?<?php echo htmlspecialchars(http_build_query(['q' => $searchQuery, 'profile' => (int) $profile['customer_profile_id']]), ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-clock-rotate-left"></i> View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5"><div class="empty-state">No customer profiles matched your search.</div></td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </article>

                    <article class="admin-panel panel-card">
                        <div class="admin-panel-header panel-heading">
                            <div>
                                <h2 class="admin-panel-title panel-title"><?php echo $selectedProfile ? htmlspecialchars((string) $selectedProfile['name'], ENT_QUOTES, 'UTF-8') : 'Customer History'; ?></h2>
                                <p class="admin-panel-copy panel-subtitle">
                                    <?php if ($selectedProfile): ?>
                                        Booking history for this customer profile.
                                    <?php else: ?>
                                        Select a customer profile to view booking history.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($selectedProfile): ?>
                                <span class="admin-chip"><i class="fa-solid fa-clock-rotate-left"></i> <?php echo number_format(count($selectedProfileBookings)); ?> bookings</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($selectedProfile): ?>
                            <div class="history-list">
                                <div class="history-card">
                                    <div class="history-card-title">Profile Details</div>
                                    <div class="history-card-meta">
                                        <span><?php echo htmlspecialchars((string) (($selectedProfile['email'] ?? '') !== '' ? $selectedProfile['email'] : '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo htmlspecialchars((string) (($selectedProfile['phone'] ?? '') !== '' ? $selectedProfile['phone'] : '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span>Total spend: $<?php echo number_format($selectedProfileTotalSpend, 2); ?></span>
                                        <span><?php echo !empty($selectedProfile['linked_user_id']) ? htmlspecialchars('Linked account: ' . ((string) ($selectedProfile['linked_user_name'] ?? 'User')), ENT_QUOTES, 'UTF-8') : 'No linked account'; ?></span>
                                    </div>
                                    <div class="link-form">
                                        <div class="link-actions">
                                            <button
                                                type="button"
                                                class="btn-primary-soft"
                                                data-admin-booking-create-open
                                                data-create-date="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-create-name="<?php echo htmlspecialchars((string) ($selectedProfile['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-create-email="<?php echo htmlspecialchars((string) ($selectedProfile['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                data-create-phone="<?php echo htmlspecialchars((string) ($selectedProfile['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            >
                                                <i class="fa-solid fa-calendar-plus"></i> Create Booking For This Customer
                                            </button>
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

                                <div class="history-card">
                                    <div class="history-card-title">Customer Notes</div>
                                    <form method="POST" class="link-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="save_notes">
                                        <input type="hidden" name="customer_profile_id" value="<?php echo (int) $selectedProfile['customer_profile_id']; ?>">
                                        <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8'); ?>">
                                        <textarea name="notes" class="form-control" placeholder="Add internal notes."><?php echo htmlspecialchars((string) ($selectedProfile['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        <div class="link-actions">
                                            <button type="submit" class="btn-primary-soft">
                                                <i class="fa-solid fa-floppy-disk"></i> Save Notes
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <div class="history-card">
                                    <div class="history-card-title">Merge Customer Profile</div>
                                    <div class="history-card-note">Merge duplicate customer profiles.</div>
                                    <form method="POST" class="link-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="merge_profile">
                                        <input type="hidden" name="customer_profile_id" value="<?php echo (int) $selectedProfile['customer_profile_id']; ?>">
                                        <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($returnQuery, ENT_QUOTES, 'UTF-8'); ?>">
                                        <select name="merge_target_profile_id" class="form-select" required>
                                            <option value="">Merge this profile into...</option>
                                            <?php foreach ($profiles as $mergeCandidate): ?>
                                                <?php if ((int) $mergeCandidate['customer_profile_id'] === (int) $selectedProfile['customer_profile_id']) { continue; } ?>
                                                <option value="<?php echo (int) $mergeCandidate['customer_profile_id']; ?>">
                                                    <?php echo htmlspecialchars((string) $mergeCandidate['name'] . ' • ' . ((string) (($mergeCandidate['email'] ?? '') !== '' ? $mergeCandidate['email'] : (($mergeCandidate['phone'] ?? '') !== '' ? $mergeCandidate['phone'] : 'No contact'))), ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="link-actions">
                                            <button type="submit" class="btn-surface" onclick="return confirm('Merge this customer profile into the selected target profile? This will move bookings to the target profile and remove the current profile.');">
                                                <i class="fa-solid fa-code-merge"></i> Merge Profile
                                            </button>
                                        </div>
                                    </form>
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
                                            : 'Unassigned';
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
                                                <span class="status-tag <?php echo htmlspecialchars((string) ($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars(getBookingStatusLabel($booking['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </div>
                                            <?php if ($placementSummary !== ''): ?>
                                                <div class="history-card-note">Reservation card: <?php echo htmlspecialchars($placementSummary, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">No bookings are linked to this profile.</div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">Select a customer profile to view booking history.</div>
                        <?php endif; ?>
                    </article>
                </section>
            </div>
        </div>
    </div>
</div>
<?php
$adminBookingCreateDefaultDate = date('Y-m-d');
$adminBookingCreateMinDate = date('Y-m-d');
$adminBookingCreateEndpoint = '../actions/create-booking.php';
include __DIR__ . '/../partials/admin-booking-create-modal.php';
?>
</body>
</html>



