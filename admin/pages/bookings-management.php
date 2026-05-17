<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();

ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);

// Ensure function-specific columns exist on bookings
function ensureFunctionBookingColumns(PDO $pdo): void {
    $col = static function (string $name) use ($pdo): bool {
        $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE " . $pdo->quote($name));
        return $stmt && $stmt->rowCount() > 0;
    };

    if (!$col('function_title')) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN function_title VARCHAR(150) NULL AFTER booking_type");
    }
    if (!$col('function_event_type')) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN function_event_type VARCHAR(60) NULL AFTER function_title");
    }
    if (!$col('function_area_label')) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN function_area_label VARCHAR(80) NULL AFTER function_event_type");
    }
    if (!$col('function_setup_notes')) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN function_setup_notes TEXT NULL AFTER function_area_label");
    }
    if (!$col('function_checklist')) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN function_checklist TEXT NULL AFTER function_setup_notes");
    }
    if (!$col('function_deposit_status')) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN function_deposit_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid' AFTER function_checklist");
    }
}
ensureFunctionBookingColumns($pdo);
$functionCsrfToken = csrfToken('admin_actions');

$checklistKeys = [
    'confirm_menu'       => 'Confirm menu',
    'special_requests'   => 'Special requests',
    'confirm_seating'    => 'Confirm seating / area',
    'send_confirmation'  => 'Send confirmation',
    'deposit_received'   => 'Deposit received',
    'final_call'         => 'Final call before event',
];

function decodeFunctionChecklist(?string $raw, array $keys): array {
    $state = array_fill_keys(array_keys($keys), false);
    if (!$raw) {
        return $state;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $state;
    }
    foreach ($state as $k => $v) {
        if (isset($decoded[$k])) {
            $state[$k] = (bool) $decoded[$k];
        }
    }
    return $state;
}

function eventTypeLabel(?string $value): string {
    $value = trim((string) $value);
    if ($value === '') return 'Function';
    return $value;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken('admin_actions', ['redirect' => appPath('admin/pages/bookings-management.php')]);

    $action = $_POST['action'] ?? '';
    $bookingId = (int) ($_POST['booking_id'] ?? 0);

    try {
        if ($action === 'create_function') {
            $title = trim((string) ($_POST['function_title'] ?? ''));
            $eventType = trim((string) ($_POST['event_type'] ?? ''));
            $contactName = trim((string) ($_POST['contact_name'] ?? ''));
            $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));
            $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
            $bookingDate = trim((string) ($_POST['booking_date'] ?? ''));
            $startTime = trim((string) ($_POST['start_time'] ?? ''));
            $endTime = trim((string) ($_POST['end_time'] ?? ''));
            $guests = max(1, (int) ($_POST['number_of_guests'] ?? 1));
            $areaLabel = trim((string) ($_POST['area_label'] ?? ''));
            $setupNotes = trim((string) ($_POST['setup_notes'] ?? ''));
            $customerNotes = trim((string) ($_POST['customer_notes'] ?? ''));
            $depositStatus = ($_POST['deposit_status'] ?? 'unpaid') === 'paid' ? 'paid' : 'unpaid';

            if ($title === '' || $contactName === '' || $bookingDate === '' || $startTime === '' || $endTime === '') {
                setFlashMessage('error', 'Please fill in all required function fields.');
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO bookings (
                        customer_name, customer_phone, customer_email,
                        booking_date, start_time, end_time,
                        number_of_guests, special_request, booking_type,
                        function_title, function_event_type, function_area_label,
                        function_setup_notes, function_checklist, function_deposit_status,
                        status, booking_source, created_by_user_id,
                        guest_access_token
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, 'function',
                        ?, ?, ?, ?, ?, ?, 'pending', 'admin_manual', ?, ?
                    )
                ");
                $insertStmt->execute([
                    $contactName, $contactPhone, $contactEmail,
                    $bookingDate, $startTime . ':00', $endTime . ':00',
                    $guests, $customerNotes,
                    $title, $eventType, $areaLabel,
                    $setupNotes, json_encode([]), $depositStatus,
                    (int) ($_SESSION['user_id'] ?? 0) ?: null,
                    generateGuestAccessToken(),
                ]);
                setFlashMessage('success', 'New function created.');
            }
        } elseif ($bookingId > 0) {
            if ($action === 'mark_confirmed') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ? AND booking_type = 'function'");
                $stmt->execute([$bookingId]);
                setFlashMessage('success', 'Function marked as confirmed.');
            } elseif ($action === 'toggle_checklist') {
                $key = (string) ($_POST['checklist_key'] ?? '');
                if (isset($checklistKeys[$key])) {
                    $row = $pdo->prepare("SELECT function_checklist FROM bookings WHERE booking_id = ?");
                    $row->execute([$bookingId]);
                    $current = decodeFunctionChecklist((string) $row->fetchColumn(), $checklistKeys);
                    $current[$key] = !$current[$key];
                    $upd = $pdo->prepare("UPDATE bookings SET function_checklist = ? WHERE booking_id = ?");
                    $upd->execute([json_encode($current), $bookingId]);
                }
            } elseif ($action === 'update_setup_notes') {
                $notes = trim((string) ($_POST['setup_notes'] ?? ''));
                $stmt = $pdo->prepare("UPDATE bookings SET function_setup_notes = ? WHERE booking_id = ?");
                $stmt->execute([$notes !== '' ? $notes : null, $bookingId]);
                setFlashMessage('success', 'Setup notes updated.');
            } elseif ($action === 'update_area') {
                $area = trim((string) ($_POST['area_label'] ?? ''));
                $stmt = $pdo->prepare("UPDATE bookings SET function_area_label = ? WHERE booking_id = ?");
                $stmt->execute([$area !== '' ? $area : null, $bookingId]);
                setFlashMessage('success', 'Area assigned.');
            } elseif ($action === 'delete_function') {
                $del = $pdo->prepare("DELETE FROM booking_table_assignments WHERE booking_id = ?");
                $del->execute([$bookingId]);
                $del = $pdo->prepare("DELETE FROM bookings WHERE booking_id = ? AND booking_type = 'function'");
                $del->execute([$bookingId]);
                setFlashMessage('success', 'Function deleted.');
                header('Location: bookings-management.php');
                exit();
            }
        }
    } catch (Throwable $e) {
        error_log('Function booking action failed: ' . $e->getMessage());
        setFlashMessage('error', 'Action failed. Please try again.');
    }

    $redirectTab = isset($_POST['tab']) ? '?tab=' . urlencode((string) $_POST['tab']) : '';
    $redirectFn = $bookingId > 0 ? ($redirectTab ? '&function=' . $bookingId : '?function=' . $bookingId) : '';
    header('Location: bookings-management.php' . $redirectTab . $redirectFn);
    exit();
}

$allowedTabs = ['upcoming', 'setup', 'confirmed', 'done'];
$activeTab = strtolower(trim((string) ($_GET['tab'] ?? 'upcoming')));
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'upcoming';
}

$today = date('Y-m-d');
$tabWhere = [
    'upcoming'  => "(b.booking_date >= '$today' AND b.status IN ('pending','confirmed'))",
    'setup'     => "(b.booking_date >= '$today' AND b.status IN ('pending','confirmed') AND (b.function_area_label IS NULL OR b.function_area_label = '' OR b.reservation_card_status = 'not_placed'))",
    'confirmed' => "(b.booking_date >= '$today' AND b.status = 'confirmed' AND b.function_area_label IS NOT NULL AND b.function_area_label <> '')",
    'done'      => "(b.status IN ('completed','cancelled','no_show') OR b.booking_date < '$today')",
];

$countSql = "
    SELECT
        SUM(CASE WHEN {$tabWhere['upcoming']} THEN 1 ELSE 0 END) AS upcoming_count,
        SUM(CASE WHEN {$tabWhere['setup']} THEN 1 ELSE 0 END) AS setup_count,
        SUM(CASE WHEN {$tabWhere['confirmed']} THEN 1 ELSE 0 END) AS confirmed_count,
        SUM(CASE WHEN {$tabWhere['done']} THEN 1 ELSE 0 END) AS done_count
    FROM bookings b
    WHERE b.booking_type = 'function'
";
$tabCounts = $pdo->query($countSql)->fetch(PDO::FETCH_ASSOC) ?: [];
$tabCounts = [
    'upcoming'  => (int) ($tabCounts['upcoming_count'] ?? 0),
    'setup'     => (int) ($tabCounts['setup_count'] ?? 0),
    'confirmed' => (int) ($tabCounts['confirmed_count'] ?? 0),
    'done'      => (int) ($tabCounts['done_count'] ?? 0),
];

$listSql = "
    SELECT b.booking_id, b.booking_date, b.start_time, b.end_time,
           b.number_of_guests, b.status, b.reservation_card_status,
           b.special_request, b.spend_amount,
           b.customer_name, b.customer_phone, b.customer_email,
           b.function_title, b.function_event_type, b.function_area_label,
           b.function_setup_notes, b.function_checklist, b.function_deposit_status
    FROM bookings b
    WHERE b.booking_type = 'function'
      AND {$tabWhere[$activeTab]}
    ORDER BY b.booking_date ASC, b.start_time ASC, b.booking_id ASC
";
$functions = $pdo->query($listSql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selectedFunctionId = (int) ($_GET['function'] ?? 0);
$selectedFunction = null;
foreach ($functions as $row) {
    if ((int) $row['booking_id'] === $selectedFunctionId) {
        $selectedFunction = $row;
        break;
    }
}
if ($selectedFunction === null && !empty($functions)) {
    $selectedFunction = $functions[0];
    $selectedFunctionId = (int) $selectedFunction['booking_id'];
}

$allAreasStmt = $pdo->query("SELECT name FROM table_areas WHERE is_active = 1 ORDER BY display_order ASC, name ASC");
$availableAreas = $allAreasStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

$pendingRequestsCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending' AND booking_date >= CURDATE()")->fetchColumn();
$flash = getFlashMessage();

$adminPageTitle = 'Functions';
$adminNewSidebarActive = 'functions';
$adminSidebarActive = 'functions';

function statusBadge(array $fn): array {
    $hasArea = trim((string) ($fn['function_area_label'] ?? '')) !== '';
    $status = strtolower((string) $fn['status']);

    if ($status === 'pending') {
        return ['Pending', 'badge-pending'];
    }
    if ($status === 'confirmed' && !$hasArea) {
        return ['Setup Needed', 'badge-setup'];
    }
    if ($status === 'confirmed') {
        return ['Confirmed', 'badge-confirmed'];
    }
    if ($status === 'completed') {
        return ['Completed', 'badge-confirmed'];
    }
    if ($status === 'cancelled') {
        return ['Cancelled', 'badge-cancelled'];
    }
    if ($status === 'no_show') {
        return ['No-show', 'badge-cancelled'];
    }
    return [ucfirst($status), 'badge-pending'];
}

function depositBadge(string $value): array {
    return $value === 'paid' ? ['Paid', 'badge-confirmed'] : ['Unpaid', 'badge-setup'];
}

function fmtDateShort(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('D, j M', $ts) : $date;
}

function fmtDateLong(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('D, j M Y', $ts) : $date;
}

function fmtTime(string $time): string {
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : $time;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../partials/admin-head.php'; ?>
    <?php include __DIR__ . '/../partials/admin-modernize.php'; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/pages/admin-functions.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../partials/admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="fn-page admin-ops">

            <?php if ($flash): ?>
                <div class="fn-alert fn-alert-<?php echo htmlspecialchars($flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="fn-topbar">
                <div>
                    <p class="admin-page-kicker">Event Desk</p>
                    <h1>Functions</h1>
                    <p>Plan group bookings from first enquiry through setup, confirmation, and service handover.</p>
                </div>
                <button type="button" class="fn-new-btn" onclick="document.getElementById('fnNewModal').classList.add('is-open')">
                    <i class="bi bi-plus-lg"></i> New Function
                </button>
            </div>

            <section class="ops-metric-grid" aria-label="Function summary">
                <div class="ops-metric">
                    <span>Upcoming</span>
                    <strong><?php echo number_format($tabCounts['upcoming']); ?></strong>
                    <small>Active function bookings</small>
                </div>
                <div class="ops-metric">
                    <span>Setup Needed</span>
                    <strong><?php echo number_format($tabCounts['setup']); ?></strong>
                    <small>Area or card needs attention</small>
                </div>
                <div class="ops-metric">
                    <span>Confirmed</span>
                    <strong><?php echo number_format($tabCounts['confirmed']); ?></strong>
                    <small>Ready for service prep</small>
                </div>
                <div class="ops-metric">
                    <span>Pending Bookings</span>
                    <strong><?php echo number_format($pendingRequestsCount); ?></strong>
                    <small>Open reservation requests</small>
                </div>
            </section>

            <nav class="fn-tabs" aria-label="Function tabs">
                <?php foreach (['upcoming' => 'Upcoming', 'setup' => 'Setup', 'confirmed' => 'Confirmed', 'done' => 'Done'] as $key => $label): ?>
                    <a class="fn-tab<?php echo $activeTab === $key ? ' is-active' : ''; ?>"
                       href="?tab=<?php echo $key; ?>">
                        <?php echo $label; ?> <span><?php echo number_format($tabCounts[$key] ?? 0); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if (empty($functions)): ?>
                <div class="fn-empty">
                    <i class="bi bi-calendar-event"></i>
                    No functions in this view. Click "New Function" to create one.
                </div>
            <?php else: ?>
            <div class="fn-grid">
                <div class="fn-list">
                    <?php foreach ($functions as $fn):
                        [$statusLabel, $statusClass] = statusBadge($fn);
                        $title = trim((string) ($fn['function_title'] ?? '')) !== ''
                            ? $fn['function_title']
                            : (eventTypeLabel($fn['function_event_type']) . ' Event');
                        $isSelected = ((int) $fn['booking_id'] === $selectedFunctionId);
                    ?>
                        <article class="fn-card<?php echo $isSelected ? ' is-selected' : ''; ?>">
                            <div class="fn-card-head">
                                <div>
                                    <h3 class="fn-card-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <div class="fn-card-meta">
                                        <div class="fn-card-meta-row">
                                            <i class="bi bi-calendar3"></i>
                                            <span><?php echo htmlspecialchars(fmtDateShort($fn['booking_date']), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span style="color:var(--fn-text-soft); margin: 0 4px;">•</span>
                                            <span><?php echo htmlspecialchars(fmtTime($fn['start_time']), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="fn-card-meta-row">
                                            <i class="bi bi-people"></i>
                                            <span><?php echo (int) $fn['number_of_guests']; ?> guests</span>
                                            <?php if (trim((string) ($fn['function_area_label'] ?? '')) !== ''): ?>
                                                <span style="color:var(--fn-text-soft); margin: 0 4px;">•</span>
                                                <span><?php echo htmlspecialchars($fn['function_area_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fn-card-meta-row">
                                            <i class="bi bi-person"></i>
                                            <span><?php echo htmlspecialchars((string) ($fn['customer_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (trim((string) ($fn['customer_phone'] ?? '')) !== ''): ?>
                                                <span style="color:var(--fn-text-soft); margin: 0 4px;">•</span>
                                                <span><?php echo htmlspecialchars($fn['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="fn-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                            </div>

                            <div class="fn-card-actions" onclick="event.stopPropagation();">
                                <a class="fn-btn" href="?tab=<?php echo $activeTab; ?>&function=<?php echo (int) $fn['booking_id']; ?>">
                                    <i class="bi bi-send"></i> View Details
                                </a>
                                <button type="button" class="fn-btn"
                                        onclick="event.preventDefault(); openAreaModal(<?php echo (int) $fn['booking_id']; ?>, '<?php echo htmlspecialchars(addslashes((string) ($fn['function_area_label'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>'); return false;">
                                    <i class="bi bi-geo-alt"></i> Assign Area
                                </button>
                                <?php if (strtolower($fn['status']) !== 'confirmed'): ?>
                                    <form method="POST" class="inline-form" onsubmit="event.stopPropagation();">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($functionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="mark_confirmed">
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $fn['booking_id']; ?>">
                                        <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                                        <button type="submit" class="fn-btn fn-btn-primary" onclick="event.stopPropagation();">
                                            <i class="bi bi-check2-circle"></i> Confirm
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="fn-btn fn-btn-success" style="cursor: default;">
                                        <i class="bi bi-check2-circle"></i> Confirmed
                                    </span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($selectedFunction !== null):
                    [$detailStatusLabel, $detailStatusClass] = statusBadge($selectedFunction);
                    [$depositLabel, $depositClass] = depositBadge((string) ($selectedFunction['function_deposit_status'] ?? 'unpaid'));
                    $checklist = decodeFunctionChecklist((string) ($selectedFunction['function_checklist'] ?? ''), $checklistKeys);
                    $setupNotesText = trim((string) ($selectedFunction['function_setup_notes'] ?? ''));
                    $customerNotesText = trim((string) ($selectedFunction['special_request'] ?? ''));
                    $detailTitle = trim((string) ($selectedFunction['function_title'] ?? '')) !== ''
                        ? $selectedFunction['function_title']
                        : (eventTypeLabel($selectedFunction['function_event_type']) . ' Event');
                ?>
                <aside class="fn-detail">
                    <h2>Function Details</h2>

                    <div class="fn-detail-grid">
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Event Type:</span>
                            <span class="fn-detail-value"><?php echo htmlspecialchars(eventTypeLabel($selectedFunction['function_event_type']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Contact Person:</span>
                            <span class="fn-detail-value"><?php echo htmlspecialchars((string) ($selectedFunction['customer_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Date:</span>
                            <span class="fn-detail-value"><?php echo htmlspecialchars(fmtDateLong($selectedFunction['booking_date']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Phone:</span>
                            <span class="fn-detail-value"><?php echo htmlspecialchars((string) ($selectedFunction['customer_phone'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Time:</span>
                            <span class="fn-detail-value"><?php echo htmlspecialchars(fmtTime($selectedFunction['start_time']), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Email:</span>
                            <span class="fn-detail-value"><?php echo htmlspecialchars((string) ($selectedFunction['customer_email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Guests:</span>
                            <span class="fn-detail-value"><?php echo (int) $selectedFunction['number_of_guests']; ?></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Deposit:</span>
                            <span><span class="fn-badge <?php echo $depositClass; ?>"><?php echo $depositLabel; ?></span></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Area / Room:</span>
                            <span class="fn-detail-value"><?php echo htmlspecialchars($selectedFunction['function_area_label'] ?: '—', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="fn-detail-row">
                            <span class="fn-detail-label">Status:</span>
                            <span><span class="fn-badge <?php echo $detailStatusClass; ?>"><?php echo $detailStatusLabel; ?></span></span>
                        </div>
                    </div>

                    <div class="fn-section">
                        <div class="fn-section-title">
                            <i class="bi bi-clipboard-check"></i> Setup Notes
                        </div>
                        <?php if ($setupNotesText !== ''): ?>
                            <div class="fn-setup-box">
                                <ul>
                                    <?php foreach (preg_split('/\r\n|\r|\n/', $setupNotesText) as $line):
                                        $line = trim($line);
                                        if ($line === '') continue;
                                    ?>
                                        <li><?php echo htmlspecialchars(ltrim($line, "•-* \t"), ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="fn-setup-empty">No setup notes yet.</div>
                        <?php endif; ?>
                    </div>

                    <div class="fn-section">
                        <div class="fn-section-title">
                            <i class="bi bi-chat-left-quote"></i> Customer Notes
                        </div>
                        <?php if ($customerNotesText !== ''): ?>
                            <div class="fn-notes-box">"<?php echo nl2br(htmlspecialchars($customerNotesText, ENT_QUOTES, 'UTF-8')); ?>"</div>
                        <?php else: ?>
                            <div class="fn-setup-empty">No customer notes.</div>
                        <?php endif; ?>
                    </div>

                    <div class="fn-section">
                        <div class="fn-section-title">
                            <i class="bi bi-list-check"></i> Internal Checklist
                        </div>
                        <div class="fn-checklist">
                            <?php foreach ($checklistKeys as $key => $label): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($functionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="toggle_checklist">
                                    <input type="hidden" name="booking_id" value="<?php echo (int) $selectedFunction['booking_id']; ?>">
                                    <input type="hidden" name="checklist_key" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                                    <label class="fn-check-item">
                                        <input type="checkbox"
                                               <?php echo $checklist[$key] ? 'checked' : ''; ?>
                                               onchange="this.form.submit();">
                                        <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="fn-detail-actions">
                        <?php if (strtolower($selectedFunction['status']) !== 'confirmed'): ?>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($functionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="mark_confirmed">
                                <input type="hidden" name="booking_id" value="<?php echo (int) $selectedFunction['booking_id']; ?>">
                                <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
                                <button type="submit" class="fn-btn fn-btn-success" style="width: 100%;">
                                    <i class="bi bi-check2"></i> Mark as Confirmed
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="fn-btn fn-btn-success" style="cursor: default; width: 100%;">
                                <i class="bi bi-check2"></i> Confirmed
                            </span>
                        <?php endif; ?>
                        <a class="fn-btn" href="mailto:<?php echo htmlspecialchars((string) ($selectedFunction['customer_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>?subject=<?php echo urlencode('Update on ' . $detailTitle); ?>">
                            <i class="bi bi-envelope"></i> Send Update
                        </a>
                        <button type="button" class="fn-btn" onclick="openEditModal(<?php echo (int) $selectedFunction['booking_id']; ?>)">
                            <i class="bi bi-pencil"></i> Edit Booking
                        </button>
                    </div>
                </aside>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Function Modal -->
<div class="fn-modal-backdrop" id="fnNewModal">
    <div class="fn-modal">
        <h3>New Function</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($functionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="create_function">
            <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
            <div class="fn-form-grid">
                <div class="fn-form-group full">
                    <label>Function Title *</label>
                    <input type="text" name="function_title" required placeholder="e.g. Birthday Dinner">
                </div>
                <div class="fn-form-group">
                    <label>Event Type</label>
                    <input type="text" name="event_type" placeholder="Birthday">
                </div>
                <div class="fn-form-group">
                    <label>Area / Room</label>
                    <input type="text" name="area_label" list="areaSuggestions" placeholder="Main Dining">
                    <datalist id="areaSuggestions">
                        <?php foreach ($availableAreas as $area): ?>
                            <option value="<?php echo htmlspecialchars($area, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="fn-form-group">
                    <label>Contact Name *</label>
                    <input type="text" name="contact_name" required>
                </div>
                <div class="fn-form-group">
                    <label>Contact Phone</label>
                    <input type="text" name="contact_phone">
                </div>
                <div class="fn-form-group full">
                    <label>Contact Email</label>
                    <input type="email" name="contact_email">
                </div>
                <div class="fn-form-group">
                    <label>Date *</label>
                    <input type="date" name="booking_date" required>
                </div>
                <div class="fn-form-group">
                    <label>Guests</label>
                    <input type="number" name="number_of_guests" min="1" value="10">
                </div>
                <div class="fn-form-group">
                    <label>Start Time *</label>
                    <input type="time" name="start_time" required value="19:30">
                </div>
                <div class="fn-form-group">
                    <label>End Time *</label>
                    <input type="time" name="end_time" required value="22:00">
                </div>
                <div class="fn-form-group">
                    <label>Deposit Status</label>
                    <select name="deposit_status">
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div class="fn-form-group full">
                    <label>Setup Notes (one item per line)</label>
                    <textarea name="setup_notes" rows="3" placeholder="2 long tables&#10;Cake fridge space required"></textarea>
                </div>
                <div class="fn-form-group full">
                    <label>Customer Notes</label>
                    <textarea name="customer_notes" rows="2"></textarea>
                </div>
            </div>
            <div class="fn-modal-actions">
                <button type="button" class="fn-btn" onclick="document.getElementById('fnNewModal').classList.remove('is-open')">Cancel</button>
                <button type="submit" class="fn-btn fn-btn-primary">Create Function</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Area Modal -->
<div class="fn-modal-backdrop" id="fnAreaModal">
    <div class="fn-modal">
        <h3>Assign Area</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($functionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="update_area">
            <input type="hidden" name="booking_id" id="areaModalBookingId" value="">
            <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
            <div class="fn-form-group full">
                <label>Area / Room</label>
                <input type="text" name="area_label" id="areaModalInput" list="areaSuggestions2" placeholder="Main Dining">
                <datalist id="areaSuggestions2">
                    <?php foreach ($availableAreas as $area): ?>
                        <option value="<?php echo htmlspecialchars($area, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="fn-modal-actions">
                <button type="button" class="fn-btn" onclick="document.getElementById('fnAreaModal').classList.remove('is-open')">Cancel</button>
                <button type="submit" class="fn-btn fn-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Booking Modal -->
<?php if ($selectedFunction !== null): ?>
<div class="fn-modal-backdrop" id="fnEditModal">
    <div class="fn-modal">
        <h3>Edit Function</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($functionCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="update_setup_notes">
            <input type="hidden" name="booking_id" value="<?php echo (int) $selectedFunction['booking_id']; ?>">
            <input type="hidden" name="tab" value="<?php echo $activeTab; ?>">
            <div class="fn-form-group full">
                <label>Setup Notes (one item per line)</label>
                <textarea name="setup_notes" rows="6"><?php echo htmlspecialchars((string) ($selectedFunction['function_setup_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="fn-modal-actions">
                <button type="button" class="fn-btn" onclick="document.getElementById('fnEditModal').classList.remove('is-open')">Cancel</button>
                <button type="submit" class="fn-btn fn-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    function openAreaModal(bookingId, currentArea) {
        document.getElementById('areaModalBookingId').value = bookingId;
        document.getElementById('areaModalInput').value = currentArea || '';
        document.getElementById('fnAreaModal').classList.add('is-open');
    }
    function openEditModal(bookingId) {
        var modal = document.getElementById('fnEditModal');
        if (modal) modal.classList.add('is-open');
    }
    document.querySelectorAll('.fn-modal-backdrop').forEach(function(m) {
        m.addEventListener('click', function(e) {
            if (e.target === m) m.classList.remove('is-open');
        });
    });
</script>
</body>
</html>
