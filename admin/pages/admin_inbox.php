<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureBookingRequestColumns($pdo);
ensureBookingTableAssignmentsTable($pdo);
ensureTableAreasSchema($pdo);
ensureInboxMessagesTable($pdo);

$adminNewSidebarActive = 'inbox';

$allowedFolders = ['requests', 'unassigned', 'waitlist'];
$activeFolder = strtolower(trim((string) ($_GET['folder'] ?? 'requests')));
if (!in_array($activeFolder, $allowedFolders, true)) {
    $activeFolder = 'requests';
}

$folderCounts = getInboxFolderCounts($pdo);
$totalInboxNotifications = array_sum($folderCounts);

$inboxPerPage = 6;
$inboxTotal = (int) ($folderCounts[$activeFolder] ?? 0);
$inboxTotalPages = max(1, (int) ceil($inboxTotal / $inboxPerPage));
$inboxPage = max(1, (int) ($_GET['page'] ?? 1));
if ($inboxPage > $inboxTotalPages) {
    $inboxPage = $inboxTotalPages;
}
$inboxOffset = ($inboxPage - 1) * $inboxPerPage;

$messageStmt = $pdo->prepare("
    SELECT im.*,
           b.booking_date,
           b.start_time,
           b.end_time,
           b.number_of_guests AS booking_guests,
           b.booking_type,
           b.status AS booking_status,
           b.table_id,
           b.special_request,
           (SELECT COUNT(*) FROM booking_table_assignments bta WHERE bta.booking_id = b.booking_id) AS assignment_count
    FROM inbox_messages im
    LEFT JOIN bookings b ON b.booking_id = im.booking_id
    WHERE im.folder = ?
      AND (b.booking_date IS NULL OR b.booking_date >= CURDATE())
    ORDER BY im.received_at DESC, im.inbox_id DESC
    LIMIT {$inboxPerPage} OFFSET {$inboxOffset}
");
$messageStmt->execute([$activeFolder]);
$inboxMessages = $messageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selectedInboxId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$selectedMessage = null;
if ($selectedInboxId > 0) {
    foreach ($inboxMessages as $row) {
        if ((int) $row['inbox_id'] === $selectedInboxId) {
            $selectedMessage = $row;
            break;
        }
    }

    if ($selectedMessage === null) {
        $loadStmt = $pdo->prepare("
            SELECT im.*, b.booking_date, b.start_time, b.end_time,
                   b.number_of_guests AS booking_guests, b.booking_type,
                   b.status AS booking_status, b.table_id, b.special_request
            FROM inbox_messages im
            LEFT JOIN bookings b ON b.booking_id = im.booking_id
            WHERE im.inbox_id = ?
            LIMIT 1
        ");
        $loadStmt->execute([$selectedInboxId]);
        $loadedRow = $loadStmt->fetch(PDO::FETCH_ASSOC);
        if ($loadedRow) {
            $selectedMessage = $loadedRow;
        }
    }
}
if ($selectedMessage === null && !empty($inboxMessages)) {
    $selectedMessage = $inboxMessages[0];
    $selectedInboxId = (int) $selectedMessage['inbox_id'];
}

if ($selectedMessage !== null && empty($selectedMessage['is_read'])) {
    $markRead = $pdo->prepare("UPDATE inbox_messages SET is_read = 1 WHERE inbox_id = ?");
    $markRead->execute([(int) $selectedMessage['inbox_id']]);
    $selectedMessage['is_read'] = 1;
}

$areaAvailability = [];
if ($selectedMessage !== null && !empty($selectedMessage['booking_date'])) {
    $areaAvailability = inboxAreaAvailability(
        $pdo,
        (string) $selectedMessage['booking_date'],
        (string) ($selectedMessage['start_time'] ?? '18:00:00'),
        (string) ($selectedMessage['end_time'] ?? '20:00:00')
    );
}

$flashMessage = $_SESSION['inbox_flash'] ?? null;
unset($_SESSION['inbox_flash']);

$buildInboxUrl = static function (array $overrides = []) use ($activeFolder, $selectedInboxId, $inboxPage): string {
    $params = ['folder' => $activeFolder];
    if ($inboxPage > 1) {
        $params['page'] = $inboxPage;
    }
    if ($selectedInboxId > 0) {
        $params['id'] = $selectedInboxId;
    }
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }
    return 'admin_inbox.php?' . http_build_query($params);
};

$folderLabels = [
    'requests'   => 'Requests',
    'unassigned' => 'Unassigned',
    'waitlist'   => 'Waitlist',
];

$styleVersion = (string) (@filemtime(__DIR__ . '/../../assets/css/style.css') ?: time());
$todayDate = date('Y-m-d');

$formatMessageDate = static function (?string $date): string {
    if (empty($date)) return 'Date TBC';
    $ts = strtotime($date);
    return $ts ? date('D, j M Y', $ts) : 'Date TBC';
};

$formatMessageTime = static function (?string $time): string {
    if (empty($time)) return 'Time TBC';
    $ts = strtotime($time);
    return $ts ? date('g:i A', $ts) : 'Time TBC';
};

$statusBadgeMeta = static function (string $status): array {
    return match ($status) {
        'open'      => ['label' => 'Open',      'tone' => 'open'],
        'waiting'   => ['label' => 'Waiting',   'tone' => 'waiting'],
        'confirmed' => ['label' => 'Confirmed', 'tone' => 'confirmed'],
        'declined'  => ['label' => 'Declined',  'tone' => 'declined'],
        'resolved'  => ['label' => 'Resolved',  'tone' => 'resolved'],
        default     => ['label' => ucfirst($status), 'tone' => 'open'],
    };
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inbox | DineMate Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/../partials/admin-new-sidebar.php'; ?>

    <main class="main-content" aria-label="Inbox page">
        <header class="page-header admin-inbox-header">
            <h1 class="page-title">Inbox</h1>
            <div class="header-actions">
                <a class="primary-btn" href="../timeline/timeline.php?date=<?php echo urlencode($todayDate); ?>#bookingList">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    <span>Add Booking</span>
                </a>
                <button type="button" class="icon-btn notification-btn" aria-label="Notifications">
                    <i class="bi bi-bell-fill" aria-hidden="true"></i>
                    <?php if ($totalInboxNotifications > 0): ?>
                        <span class="notification-badge"><?php echo htmlspecialchars((string) min($totalInboxNotifications, 99), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </header>


        <div class="admin-inbox-layout">
            <!-- LEFT: list panel -->
            <section class="admin-inbox-list card" aria-label="Inbox messages">
                <nav class="admin-inbox-tabs" aria-label="Inbox folders">
                    <?php foreach ($folderLabels as $folderKey => $folderLabel): ?>
                        <?php $count = $folderCounts[$folderKey] ?? 0; ?>
                        <a class="admin-inbox-tab<?php echo $folderKey === $activeFolder ? ' is-active' : ''; ?>"
                           href="<?php echo htmlspecialchars($buildInboxUrl(['folder' => $folderKey, 'id' => null]), ENT_QUOTES, 'UTF-8'); ?>"
                           <?php echo $folderKey === $activeFolder ? 'aria-current="page"' : ''; ?>>
                            <span><?php echo htmlspecialchars($folderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($count > 0): ?>
                                <strong class="admin-inbox-tab-count"><?php echo number_format($count); ?></strong>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="admin-inbox-items" role="list">
                    <?php if (empty($inboxMessages)): ?>
                        <div class="admin-inbox-empty">
                            <i class="bi bi-inbox" aria-hidden="true"></i>
                            <strong>No messages</strong>
                            <span>You're all caught up.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($inboxMessages as $message): ?>
                            <?php
                                $typeMeta = inboxTypeMeta((string) $message['type']);
                                $isSelected = (int) $message['inbox_id'] === $selectedInboxId;
                                $chip = inboxChipForBookingType((string) ($message['booking_type'] ?? 'normal'));
                                if ((string) $message['type'] === 'function_enquiry') {
                                    $chip = 'Function';
                                } elseif (in_array((string) $message['type'], ['booking_change', 'guest_message'], true)) {
                                    $chip = 'Trivia';
                                } elseif ((string) $message['type'] === 'cancellation') {
                                    $chip = 'Function';
                                }
                                $chipTone = strtolower($chip) === 'function' ? 'function' : 'trivia';
                                $relative = inboxFormatRelativeTime((string) ($message['received_at'] ?? ''));
                                $party = (int) ($message['party_size'] ?? $message['booking_guests'] ?? 0);
                            ?>
                            <a class="admin-inbox-item<?php echo $isSelected ? ' is-selected' : ''; ?><?php echo empty($message['is_read']) ? ' is-unread' : ''; ?>"
                               href="<?php echo htmlspecialchars($buildInboxUrl(['id' => (int) $message['inbox_id']]), ENT_QUOTES, 'UTF-8'); ?>"
                               role="listitem">
                                <span class="admin-inbox-item-icon tone-<?php echo htmlspecialchars($typeMeta['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="bi <?php echo htmlspecialchars($typeMeta['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                </span>
                                <div class="admin-inbox-item-body">
                                    <div class="admin-inbox-item-head">
                                        <strong><?php echo htmlspecialchars((string) ($message['subject'] ?? $typeMeta['label']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="admin-inbox-item-chip tone-<?php echo htmlspecialchars($chipTone, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($chip, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="admin-inbox-item-time"><?php echo htmlspecialchars($relative, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($party > 0): ?>
                                            <span class="admin-inbox-item-party">
                                                <i class="bi bi-person" aria-hidden="true"></i>
                                                <?php echo number_format($party); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="admin-inbox-item-name"><?php echo htmlspecialchars((string) ($message['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="admin-inbox-item-preview"><?php echo htmlspecialchars((string) ($message['preview'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <footer class="admin-inbox-list-footer">
                    <?php
                        $rangeStart = $inboxTotal === 0 ? 0 : $inboxOffset + 1;
                        $rangeEnd   = min($inboxTotal, $inboxOffset + count($inboxMessages));
                    ?>
                    <span>Showing <?php echo number_format($rangeStart); ?>&ndash;<?php echo number_format($rangeEnd); ?> of <?php echo number_format($inboxTotal); ?> <?php echo htmlspecialchars(strtolower($folderLabels[$activeFolder] ?? 'items'), ENT_QUOTES, 'UTF-8'); ?></span>

                    <?php if ($inboxTotalPages > 1): ?>
                        <nav class="admin-inbox-pagination" aria-label="Inbox pagination">
                            <?php
                                $prevDisabled = $inboxPage <= 1;
                                $nextDisabled = $inboxPage >= $inboxTotalPages;
                                $prevUrl = $buildInboxUrl(['page' => max(1, $inboxPage - 1), 'id' => null]);
                                $nextUrl = $buildInboxUrl(['page' => min($inboxTotalPages, $inboxPage + 1), 'id' => null]);

                                $windowStart = max(1, $inboxPage - 2);
                                $windowEnd   = min($inboxTotalPages, $windowStart + 4);
                                $windowStart = max(1, $windowEnd - 4);
                            ?>
                            <a class="admin-inbox-page-btn<?php echo $prevDisabled ? ' is-disabled' : ''; ?>"
                               href="<?php echo $prevDisabled ? '#' : htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8'); ?>"
                               aria-label="Previous page"
                               <?php echo $prevDisabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            </a>

                            <?php if ($windowStart > 1): ?>
                                <a class="admin-inbox-page-btn" href="<?php echo htmlspecialchars($buildInboxUrl(['page' => 1, 'id' => null]), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                                <?php if ($windowStart > 2): ?>
                                    <span class="admin-inbox-page-ellipsis">&hellip;</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
                                <?php if ($p === $inboxPage): ?>
                                    <span class="admin-inbox-page-btn is-current" aria-current="page"><?php echo $p; ?></span>
                                <?php else: ?>
                                    <a class="admin-inbox-page-btn" href="<?php echo htmlspecialchars($buildInboxUrl(['page' => $p, 'id' => null]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $p; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($windowEnd < $inboxTotalPages): ?>
                                <?php if ($windowEnd < $inboxTotalPages - 1): ?>
                                    <span class="admin-inbox-page-ellipsis">&hellip;</span>
                                <?php endif; ?>
                                <a class="admin-inbox-page-btn" href="<?php echo htmlspecialchars($buildInboxUrl(['page' => $inboxTotalPages, 'id' => null]), ENT_QUOTES, 'UTF-8'); ?>"><?php echo $inboxTotalPages; ?></a>
                            <?php endif; ?>

                            <a class="admin-inbox-page-btn<?php echo $nextDisabled ? ' is-disabled' : ''; ?>"
                               href="<?php echo $nextDisabled ? '#' : htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8'); ?>"
                               aria-label="Next page"
                               <?php echo $nextDisabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                                <i class="bi bi-chevron-right" aria-hidden="true"></i>
                            </a>
                        </nav>
                    <?php endif; ?>
                </footer>
            </section>

            <!-- RIGHT: detail panel -->
            <section class="admin-inbox-detail card" aria-label="Selected message detail">
                <?php if ($selectedMessage === null): ?>
                    <div class="admin-inbox-empty">
                        <i class="bi bi-envelope-open" aria-hidden="true"></i>
                        <strong>Nothing to view</strong>
                        <span>Select a message from the list to view its details.</span>
                    </div>
                <?php else: ?>
                    <?php
                        $typeMeta = inboxTypeMeta((string) $selectedMessage['type']);
                        $statusMeta = $statusBadgeMeta((string) $selectedMessage['status']);
                        $relative = inboxFormatRelativeTime((string) ($selectedMessage['received_at'] ?? ''));
                    ?>
                    <header class="admin-inbox-detail-head">
                        <span class="admin-inbox-detail-icon tone-<?php echo htmlspecialchars($typeMeta['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="bi <?php echo htmlspecialchars($typeMeta['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                        </span>
                        <div class="admin-inbox-detail-head-text">
                            <h2><?php echo htmlspecialchars((string) ($selectedMessage['subject'] ?? $typeMeta['label']), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div class="admin-inbox-detail-head-meta">
                                <span><?php echo htmlspecialchars((string) ($selectedMessage['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <small>Received <?php echo htmlspecialchars($relative !== '' ? $relative : 'just now', ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                        </div>
                        <div class="admin-inbox-detail-head-actions">
                            <span class="admin-inbox-status-pill tone-<?php echo htmlspecialchars($statusMeta['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($statusMeta['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <button type="button" class="icon-btn admin-inbox-detail-menu" aria-label="More actions">
                                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                            </button>
                        </div>
                    </header>

                    <div class="admin-inbox-detail-grid">
                        <section class="admin-inbox-info-block" aria-labelledby="guest-info-title">
                            <h3 id="guest-info-title">Guest Information</h3>
                            <ul>
                                <li>
                                    <i class="bi bi-person" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars((string) ($selectedMessage['guest_name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                                <li>
                                    <i class="bi bi-telephone" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars(!empty($selectedMessage['guest_phone']) ? (string) $selectedMessage['guest_phone'] : '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                                <li>
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    <span><?php echo htmlspecialchars(!empty($selectedMessage['guest_email']) ? (string) $selectedMessage['guest_email'] : '—', ENT_QUOTES, 'UTF-8'); ?></span>
                                </li>
                            </ul>
                        </section>

                        <section class="admin-inbox-info-block" aria-labelledby="booking-details-title">
                            <h3 id="booking-details-title">Booking Details</h3>
                            <div class="admin-inbox-detail-columns">
                                <ul>
                                    <li>
                                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                        <span><?php echo htmlspecialchars($formatMessageDate($selectedMessage['booking_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </li>
                                    <li>
                                        <i class="bi bi-clock" aria-hidden="true"></i>
                                        <span><?php echo htmlspecialchars($formatMessageTime($selectedMessage['start_time'] ?? null), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </li>
                                </ul>
                                <ul>
                                    <li>
                                        <i class="bi bi-people" aria-hidden="true"></i>
                                        <span><?php echo number_format((int) ($selectedMessage['party_size'] ?? $selectedMessage['booking_guests'] ?? 0)); ?> guests</span>
                                    </li>
                                    <li>
                                        <i class="bi bi-grid" aria-hidden="true"></i>
                                        <span><?php echo htmlspecialchars(ucfirst((string) ($selectedMessage['booking_type'] ?? 'Booking')), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </li>
                                </ul>
                            </div>
                        </section>
                    </div>

                    <section class="admin-inbox-availability" aria-labelledby="availability-title">
                        <h3 id="availability-title">Availability</h3>
                        <?php if (empty($areaAvailability)): ?>
                            <div class="admin-inbox-empty subtle">
                                <span>No area availability data — connect a booking to see options.</span>
                            </div>
                        <?php else: ?>
                            <div class="admin-inbox-availability-grid">
                                <?php foreach ($areaAvailability as $area): ?>
                                    <div class="admin-inbox-area-card tone-<?php echo htmlspecialchars($area['state'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="admin-inbox-area-card-head">
                                            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                                            <strong><?php echo htmlspecialchars($area['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </div>
                                        <span class="admin-inbox-area-card-meta"><?php echo htmlspecialchars($area['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <div class="admin-inbox-messages-grid">
                        <section class="admin-inbox-info-block" aria-labelledby="guest-message-title">
                            <h3 id="guest-message-title">Guest Message</h3>
                            <div class="admin-inbox-message-bubble guest">
                                <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                                <p><?php echo nl2br(htmlspecialchars((string) ($selectedMessage['message'] ?? $selectedMessage['preview'] ?? 'No additional message from the guest.'), ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                        </section>

                        <section class="admin-inbox-info-block" aria-labelledby="staff-notes-title">
                            <h3 id="staff-notes-title">
                                <span>Staff Notes</span>
                                <span class="admin-inbox-notes-status" data-notes-status aria-live="polite"></span>
                            </h3>
                            <div class="admin-inbox-message-bubble notes">
                                <i class="bi bi-pin-angle" aria-hidden="true"></i>
                                <textarea
                                    rows="3"
                                    data-notes-input
                                    data-inbox-id="<?php echo (int) $selectedMessage['inbox_id']; ?>"
                                    data-folder="<?php echo htmlspecialchars($activeFolder, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="Add an internal note for the team..."
                                ><?php echo htmlspecialchars((string) ($selectedMessage['staff_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                        </section>
                    </div>

                    <footer class="admin-inbox-detail-actions">
                        <form method="post" action="inbox-action.php" class="admin-inbox-action-form">
                            <input type="hidden" name="inbox_id" value="<?php echo (int) $selectedMessage['inbox_id']; ?>">
                            <input type="hidden" name="folder" value="<?php echo htmlspecialchars($activeFolder, ENT_QUOTES, 'UTF-8'); ?>">

                            <button type="submit" name="action" value="confirm" class="action-btn confirm">
                                <i class="bi bi-check2" aria-hidden="true"></i>
                                Confirm
                            </button>
                            <button type="submit" name="action" value="contact" class="action-btn contact">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                Contact Guest
                            </button>
                            <button type="submit" name="action" value="waitlist" class="action-btn waitlist">
                                <i class="bi bi-people" aria-hidden="true"></i>
                                Waitlist
                            </button>
                            <button type="submit" name="action" value="decline" class="action-btn decline" data-confirm="Decline this request?">
                                <i class="bi bi-x" aria-hidden="true"></i>
                                Decline
                            </button>
                        </form>
                    </footer>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<?php if ($flashMessage): ?>
    <div class="admin-toast-container" aria-live="polite" aria-atomic="true">
        <div class="admin-toast" role="status" data-toast data-auto-dismiss="4000">
            <span class="admin-toast-icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></span>
            <span class="admin-toast-message"><?php echo htmlspecialchars((string) $flashMessage, ENT_QUOTES, 'UTF-8'); ?></span>
            <button type="button" class="admin-toast-close" aria-label="Dismiss notification" data-toast-close>
                <i class="bi bi-x" aria-hidden="true"></i>
            </button>
        </div>
    </div>
<?php endif; ?>

<script>
(() => {
    const notesInput = document.querySelector('[data-notes-input]');
    const statusEl = document.querySelector('[data-notes-status]');
    if (!notesInput) return;

    let savedValue = notesInput.value;
    let saving = false;
    let pendingValue = null;
    let statusTimer = 0;

    const setStatus = (text, tone = '') => {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.dataset.tone = tone;
        if (statusTimer) clearTimeout(statusTimer);
        if (text && tone !== 'saving') {
            statusTimer = window.setTimeout(() => {
                statusEl.textContent = '';
                statusEl.dataset.tone = '';
            }, 1800);
        }
    };

    const save = async (value) => {
        if (saving) {
            pendingValue = value;
            return;
        }
        saving = true;
        setStatus('Saving…', 'saving');

        const body = new URLSearchParams({
            action: 'save_notes',
            inbox_id: notesInput.dataset.inboxId || '',
            folder:   notesInput.dataset.folder   || 'requests',
            staff_notes: value,
        });

        try {
            const response = await fetch('inbox-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                    'X-Requested-With': 'fetch',
                },
                body,
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('save failed');
            savedValue = value;
            setStatus('Saved', 'saved');
        } catch (err) {
            setStatus('Could not save', 'error');
        } finally {
            saving = false;
            if (pendingValue !== null && pendingValue !== savedValue) {
                const next = pendingValue;
                pendingValue = null;
                save(next);
            } else {
                pendingValue = null;
            }
        }
    };

    notesInput.addEventListener('blur', () => {
        const value = notesInput.value;
        if (value === savedValue) return;
        save(value);
    });

    notesInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            notesInput.value = savedValue;
            notesInput.blur();
        }
    });
})();

(() => {
    document.querySelectorAll('form[action="inbox-action.php"] button[data-confirm]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (!window.confirm(button.dataset.confirm || 'Are you sure?')) {
                event.preventDefault();
            }
        });
    });
})();

(() => {
    document.querySelectorAll('[data-toast]').forEach((toast) => {
        const dismiss = () => {
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 220);
        };

        toast.querySelector('[data-toast-close]')?.addEventListener('click', dismiss);

        const delay = parseInt(toast.dataset.autoDismiss || '0', 10);
        if (delay > 0) {
            window.setTimeout(dismiss, delay);
        }

        requestAnimationFrame(() => toast.classList.add('is-visible'));
    });
})();
</script>
</body>
</html>
