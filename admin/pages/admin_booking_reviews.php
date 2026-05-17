<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureBookingRequestColumns($pdo);
ensureCustomerProfilesSchema($pdo);
ensureBookingReviewsSchema($pdo);

$adminNewSidebarActive = 'reviews';

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$ratingFilter = (int) ($_GET['rating'] ?? 0);
$search = trim((string) ($_GET['q'] ?? ''));
$selectedReviewId = max(0, (int) ($_GET['review_id'] ?? 0));

$whereClauses = ['1 = 1'];
$params = [];

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $whereClauses[] = 'b.booking_date >= ?';
    $params[] = $dateFrom;
} else {
    $dateFrom = '';
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $whereClauses[] = 'b.booking_date <= ?';
    $params[] = $dateTo;
} else {
    $dateTo = '';
}

if ($ratingFilter >= 1 && $ratingFilter <= 5) {
    $whereClauses[] = 'br.review_rating = ?';
    $params[] = $ratingFilter;
} else {
    $ratingFilter = 0;
}

if ($search !== '') {
    $whereClauses[] = "(
        COALESCE(cp.name, b.customer_name, '') LIKE ?
        OR COALESCE(cp.email, b.customer_email, '') LIKE ?
        OR COALESCE(cp.phone, b.customer_phone, '') LIKE ?
        OR COALESCE(br.review_comment, '') LIKE ?
    )";
    $searchLike = '%' . $search . '%';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike);
}

$query = "
    SELECT
        br.review_id,
        br.booking_id,
        br.review_rating,
        br.review_comment,
        br.reviewed_at,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.number_of_guests,
        b.status,
        b.booking_source,
        b.table_id,
        b.special_request,
        t.table_number,
        COALESCE(cp.name, b.customer_name, '') AS customer_name,
        COALESCE(cp.email, b.customer_email, '') AS customer_email,
        COALESCE(cp.phone, b.customer_phone, '') AS customer_phone,
        cp.seating_preference,
        cp.dietary_notes,
        creator.name AS created_by_name
    FROM booking_reviews br
    INNER JOIN bookings b ON b.booking_id = br.booking_id
    LEFT JOIN restaurant_tables t ON t.table_id = b.table_id
    LEFT JOIN customer_profiles cp ON b.customer_profile_id = cp.customer_profile_id
    LEFT JOIN users creator ON b.created_by_user_id = creator.user_id
    WHERE " . implode(' AND ', $whereClauses) . "
    ORDER BY br.reviewed_at DESC, br.review_id DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalReviews = count($reviews);
$averageRating = 0.0;
$ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$needsFollowUpCount = 0;
$positiveCount = 0;

foreach ($reviews as $review) {
    $rating = max(1, min(5, (int) ($review['review_rating'] ?? 0)));
    $ratingCounts[$rating]++;
    $averageRating += $rating;

    if ($rating <= 3) {
        $needsFollowUpCount++;
    }
    if ($rating >= 4) {
        $positiveCount++;
    }
}

$averageRating = $totalReviews > 0 ? round($averageRating / $totalReviews, 1) : 0.0;
$positiveShare = $totalReviews > 0 ? (int) round(($positiveCount / $totalReviews) * 100) : 0;

$selectedReview = null;
foreach ($reviews as $review) {
    if ((int) $review['review_id'] === $selectedReviewId) {
        $selectedReview = $review;
        break;
    }
}
if ($selectedReview === null && !empty($reviews)) {
    $selectedReview = $reviews[0];
    $selectedReviewId = (int) $selectedReview['review_id'];
}

$buildReviewUrl = static function (int $reviewId = 0) use ($dateFrom, $dateTo, $ratingFilter, $search): string {
    $query = [];
    if ($dateFrom !== '') {
        $query['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $query['date_to'] = $dateTo;
    }
    if ($ratingFilter > 0) {
        $query['rating'] = $ratingFilter;
    }
    if ($search !== '') {
        $query['q'] = $search;
    }
    if ($reviewId > 0) {
        $query['review_id'] = $reviewId;
    }

    return 'admin_booking_reviews.php' . (!empty($query) ? '?' . http_build_query($query) : '');
};

$formatDate = static function (?string $date, string $fallback = 'Not set'): string {
    if (empty($date)) {
        return $fallback;
    }
    $timestamp = strtotime($date);
    return $timestamp !== false ? date('D, j M Y', $timestamp) : $fallback;
};

$formatTime = static function (?string $time, string $fallback = 'Not set'): string {
    if (empty($time)) {
        return $fallback;
    }
    $timestamp = strtotime($time);
    return $timestamp !== false ? date('g:i A', $timestamp) : $fallback;
};

$ratingTone = static function (int $rating): string {
    if ($rating >= 4) {
        return 'positive';
    }
    if ($rating === 3) {
        return 'mixed';
    }
    return 'attention';
};

$customerInitials = static function (string $name): string {
    $name = trim($name);
    if ($name === '') {
        return 'G';
    }
    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts ?: [] as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials !== '' ? $initials : 'G';
};

$selectedRating = $selectedReview ? (int) ($selectedReview['review_rating'] ?? 0) : 0;
$selectedTone = $ratingTone($selectedRating);
$selectedFollowUps = [];
if ($selectedReview !== null) {
    if ($selectedRating <= 2) {
        $selectedFollowUps = [
            'Contact the guest and acknowledge the concern.',
            'Review the booking notes and table assignment for the shift.',
            'Flag the feedback for the duty manager before the next service.',
        ];
    } elseif ($selectedRating === 3) {
        $selectedFollowUps = [
            'Look for a specific improvement point in the comment.',
            'Check whether the same customer has repeat preferences.',
            'Record any service issue before closing the follow-up.',
        ];
    } else {
        $selectedFollowUps = [
            'Use the comment to reinforce what worked well.',
            'Keep seating and dietary preferences visible for future bookings.',
            'Consider a thank-you reply for returning customers.',
        ];
    }
}

$styleVersion = (string) (@filemtime(__DIR__ . '/../../assets/css/style.css') ?: time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Reviews | DineMate Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        .reviews-shell {
            display: grid;
            gap: 20px;
            padding: 32px;
        }

        .reviews-header {
            align-items: flex-start;
        }

        .review-summary-strip,
        .review-filters,
        .review-detail-panel,
        .review-empty-state {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        .review-summary-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px;
        }

        .review-summary-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--text-main);
            background: var(--surface-muted);
            font-size: 13px;
            font-weight: 700;
        }

        .review-summary-pill span {
            color: var(--text-muted);
            font-weight: 600;
        }

        .review-filters {
            padding: 14px;
        }

        .review-filter-grid {
            display: grid;
            grid-template-columns: minmax(220px, 1.2fr) repeat(3, minmax(150px, 0.7fr)) auto auto;
            gap: 12px;
            align-items: end;
        }

        .review-field {
            display: grid;
            gap: 6px;
        }

        .review-field label {
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .review-control {
            width: 100%;
            min-height: 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text-main);
            padding: 9px 11px;
            font: inherit;
            font-size: 13px;
        }

        .review-workbench {
            display: grid;
            grid-template-columns: minmax(320px, 480px) minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        .review-list {
            display: grid;
            gap: 12px;
        }

        .review-card {
            display: grid;
            gap: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            padding: 16px;
            color: var(--text-main);
            text-decoration: none;
            box-shadow: var(--shadow-sm);
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .review-card:hover,
        .review-card:focus {
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
            transform: translateY(-1px);
        }

        .review-card.is-selected {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(11, 94, 215, 0.12), var(--shadow-sm);
        }

        .review-card-top,
        .review-meta-row,
        .review-detail-header,
        .review-section-title,
        .review-detail-actions,
        .review-contact-line {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .review-card-top,
        .review-detail-header,
        .review-section-title,
        .review-detail-actions {
            justify-content: space-between;
        }

        .review-avatar {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-grid;
            place-items: center;
            background: var(--surface-muted);
            color: var(--text-main);
            border: 1px solid var(--border);
            font-weight: 800;
        }

        .review-card-name,
        .review-detail-name {
            margin: 0;
            color: var(--text-main);
            font-weight: 800;
        }

        .review-card-name {
            font-size: 15px;
        }

        .review-detail-name {
            font-size: 22px;
        }

        .review-muted,
        .review-card-copy,
        .review-detail-copy {
            color: var(--text-muted);
        }

        .review-card-copy {
            display: -webkit-box;
            overflow: hidden;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            margin: 0;
            line-height: 1.5;
        }

        .review-rating {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 13px;
            font-weight: 800;
        }

        .review-rating.positive {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .review-rating.mixed {
            background: var(--warning-bg);
            color: var(--warning-text);
        }

        .review-rating.attention {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        .review-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface-muted);
            color: var(--text-muted);
            padding: 6px 9px;
            font-size: 12px;
            font-weight: 700;
        }

        .review-detail-panel {
            position: sticky;
            top: 24px;
            display: grid;
            gap: 18px;
            padding: 20px;
        }

        .review-detail-section {
            display: grid;
            gap: 12px;
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }

        .review-detail-section:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .review-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .review-info-cell {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface-muted);
            padding: 12px;
        }

        .review-info-cell span {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .review-info-cell strong {
            color: var(--text-main);
            font-size: 14px;
        }

        .review-comment-box,
        .review-followup-list {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface-muted);
            padding: 14px;
        }

        .review-comment-box {
            color: var(--text-main);
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .review-followup-list {
            margin: 0;
            padding-left: 32px;
            color: var(--text-main);
            line-height: 1.7;
        }

        .review-empty-state {
            padding: 30px;
            text-align: center;
            color: var(--text-muted);
        }

        .review-empty-state h2 {
            margin: 0 0 8px;
            color: var(--text-main);
            font-size: 20px;
        }

        @media (max-width: 1180px) {
            .review-filter-grid,
            .review-workbench {
                grid-template-columns: 1fr;
            }

            .review-detail-panel {
                position: static;
            }
        }

        @media (max-width: 720px) {
            .reviews-shell {
                padding: 20px;
            }

            .review-filter-grid,
            .review-grid {
                grid-template-columns: 1fr;
            }

            .review-filter-grid .primary-btn,
            .review-filter-grid .secondary-btn,
            .review-detail-actions .primary-btn,
            .review-detail-actions .secondary-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/../partials/admin-new-sidebar.php'; ?>

    <main class="main-content" aria-label="Booking reviews page">
        <div class="reviews-shell">
            <header class="page-header reviews-header">
                <div>
                    <h1 class="page-title">Booking Reviews</h1>
                    <div class="page-subtitle">Read guest feedback, trace it back to the booking, and plan follow-up work.</div>
                </div>
                <div class="header-actions">
                    <a class="secondary-btn" href="customer-history.php">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <span>Customer History</span>
                    </a>
                    <a class="primary-btn" href="admin_bookings.php">
                        <i class="bi bi-calendar-check" aria-hidden="true"></i>
                        <span>Bookings</span>
                    </a>
                </div>
            </header>

            <section class="review-summary-strip" aria-label="Review summary">
                <div class="review-summary-pill">
                    <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                    <?php echo number_format($totalReviews); ?>
                    <span>shown</span>
                </div>
                <div class="review-summary-pill">
                    <i class="bi bi-star-fill" aria-hidden="true"></i>
                    <?php echo $totalReviews > 0 ? htmlspecialchars(number_format($averageRating, 1), ENT_QUOTES, 'UTF-8') : '-'; ?>
                    <span>average</span>
                </div>
                <div class="review-summary-pill">
                    <i class="bi bi-heart-pulse" aria-hidden="true"></i>
                    <?php echo number_format($positiveShare); ?>%
                    <span>4-5 stars</span>
                </div>
                <div class="review-summary-pill">
                    <i class="bi bi-flag" aria-hidden="true"></i>
                    <?php echo number_format($needsFollowUpCount); ?>
                    <span>need follow-up</span>
                </div>
            </section>

            <section class="review-filters" aria-label="Review filters">
                <form method="GET" class="review-filter-grid" novalidate>
                    <div class="review-field">
                        <label for="q">Search</label>
                        <input id="q" name="q" class="review-control" type="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Guest, email, phone, or comment">
                    </div>
                    <div class="review-field">
                        <label for="date_from">From</label>
                        <input id="date_from" name="date_from" class="review-control" type="date" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="review-field">
                        <label for="date_to">To</label>
                        <input id="date_to" name="date_to" class="review-control" type="date" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="review-field">
                        <label for="rating">Rating</label>
                        <select id="rating" name="rating" class="review-control">
                            <option value="0">All ratings</option>
                            <?php for ($ratingOption = 5; $ratingOption >= 1; $ratingOption--): ?>
                                <option value="<?php echo $ratingOption; ?>" <?php echo $ratingFilter === $ratingOption ? 'selected' : ''; ?>>
                                    <?php echo $ratingOption; ?> stars
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="primary-btn">
                        <i class="bi bi-funnel" aria-hidden="true"></i>
                        <span>Filter</span>
                    </button>
                    <a class="secondary-btn" href="admin_booking_reviews.php">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                        <span>Clear</span>
                    </a>
                </form>
            </section>

            <?php if (empty($reviews)): ?>
                <section class="review-empty-state">
                    <h2>No reviews match these filters</h2>
                    <p>Completed booking ratings will appear here after guests submit feedback.</p>
                </section>
            <?php else: ?>
                <section class="review-workbench" aria-label="Review workbench">
                    <div class="review-list">
                        <?php foreach ($reviews as $review): ?>
                            <?php
                            $reviewId = (int) ($review['review_id'] ?? 0);
                            $rating = (int) ($review['review_rating'] ?? 0);
                            $tone = $ratingTone($rating);
                            $customerName = trim((string) ($review['customer_name'] ?? ''));
                            $customerName = $customerName !== '' ? $customerName : 'Guest';
                            $isSelected = $reviewId === $selectedReviewId;
                            ?>
                            <a class="review-card<?php echo $isSelected ? ' is-selected' : ''; ?>" href="<?php echo htmlspecialchars($buildReviewUrl($reviewId), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isSelected ? 'aria-current="true"' : ''; ?>>
                                <div class="review-card-top">
                                    <div class="review-contact-line">
                                        <span class="review-avatar"><?php echo htmlspecialchars($customerInitials($customerName), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <div>
                                            <p class="review-card-name"><?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <span class="review-muted"><?php echo htmlspecialchars($formatDate((string) ($review['booking_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                    <span class="review-rating <?php echo htmlspecialchars($tone, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="bi bi-star-fill" aria-hidden="true"></i>
                                        <?php echo $rating; ?>/5
                                    </span>
                                </div>
                                <div class="review-meta-row">
                                    <span class="review-meta-chip"><i class="bi bi-clock" aria-hidden="true"></i><?php echo htmlspecialchars($formatTime((string) ($review['start_time'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="review-meta-chip"><i class="bi bi-people" aria-hidden="true"></i><?php echo (int) ($review['number_of_guests'] ?? 0); ?> guests</span>
                                    <span class="review-meta-chip"><i class="bi bi-table" aria-hidden="true"></i><?php echo !empty($review['table_number']) ? 'Table ' . htmlspecialchars((string) $review['table_number'], ENT_QUOTES, 'UTF-8') : 'No table'; ?></span>
                                </div>
                                <p class="review-card-copy"><?php echo htmlspecialchars(trim((string) ($review['review_comment'] ?? '')) !== '' ? (string) $review['review_comment'] : 'No comment was left with this rating.', ENT_QUOTES, 'UTF-8'); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($selectedReview !== null): ?>
                        <?php
                        $selectedCustomerName = trim((string) ($selectedReview['customer_name'] ?? ''));
                        $selectedCustomerName = $selectedCustomerName !== '' ? $selectedCustomerName : 'Guest';
                        $customerHistoryQuery = trim((string) ($selectedReview['customer_email'] ?? '')) !== ''
                            ? (string) $selectedReview['customer_email']
                            : $selectedCustomerName;
                        $bookingDayUrl = 'admin_bookings.php?date=' . urlencode((string) ($selectedReview['booking_date'] ?? ''));
                        $customerHistoryUrl = 'customer-history.php?q=' . urlencode($customerHistoryQuery);
                        ?>
                        <aside class="review-detail-panel" aria-label="Selected review detail">
                            <section class="review-detail-section">
                                <div class="review-detail-header">
                                    <div class="review-contact-line">
                                        <span class="review-avatar"><?php echo htmlspecialchars($customerInitials($selectedCustomerName), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <div>
                                            <h2 class="review-detail-name"><?php echo htmlspecialchars($selectedCustomerName, ENT_QUOTES, 'UTF-8'); ?></h2>
                                            <span class="review-muted">Reviewed <?php echo htmlspecialchars($formatDate((string) ($selectedReview['reviewed_at'] ?? ''), 'recently'), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                    <span class="review-rating <?php echo htmlspecialchars($selectedTone, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="bi bi-star-fill" aria-hidden="true"></i>
                                        <?php echo $selectedRating; ?>/5
                                    </span>
                                </div>
                                <div class="review-detail-actions">
                                    <a class="secondary-btn" href="<?php echo htmlspecialchars($customerHistoryUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="bi bi-person-lines-fill" aria-hidden="true"></i>
                                        <span>Customer</span>
                                    </a>
                                    <a class="primary-btn" href="<?php echo htmlspecialchars($bookingDayUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                                        <span>Booking Day</span>
                                    </a>
                                </div>
                            </section>

                            <section class="review-detail-section">
                                <div class="review-section-title">
                                    <h3>Booking Context</h3>
                                </div>
                                <div class="review-grid">
                                    <div class="review-info-cell">
                                        <span>Date</span>
                                        <strong><?php echo htmlspecialchars($formatDate((string) ($selectedReview['booking_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="review-info-cell">
                                        <span>Time</span>
                                        <strong><?php echo htmlspecialchars($formatTime((string) ($selectedReview['start_time'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($formatTime((string) ($selectedReview['end_time'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="review-info-cell">
                                        <span>Party</span>
                                        <strong><?php echo (int) ($selectedReview['number_of_guests'] ?? 0); ?> guests</strong>
                                    </div>
                                    <div class="review-info-cell">
                                        <span>Table</span>
                                        <strong><?php echo !empty($selectedReview['table_number']) ? 'Table ' . htmlspecialchars((string) $selectedReview['table_number'], ENT_QUOTES, 'UTF-8') : 'Not assigned'; ?></strong>
                                    </div>
                                    <div class="review-info-cell">
                                        <span>Status</span>
                                        <strong><?php echo htmlspecialchars(getBookingStatusLabel((string) ($selectedReview['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="review-info-cell">
                                        <span>Source</span>
                                        <strong><?php echo htmlspecialchars(getBookingSourceLabel((string) ($selectedReview['booking_source'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                </div>
                            </section>

                            <section class="review-detail-section">
                                <div class="review-section-title">
                                    <h3>Guest Feedback</h3>
                                </div>
                                <div class="review-comment-box"><?php echo nl2br(htmlspecialchars(trim((string) ($selectedReview['review_comment'] ?? '')) !== '' ? (string) $selectedReview['review_comment'] : 'No comment was left with this rating.', ENT_QUOTES, 'UTF-8')); ?></div>
                            </section>

                            <section class="review-detail-section">
                                <div class="review-section-title">
                                    <h3>Customer Notes</h3>
                                </div>
                                <div class="review-grid">
                                    <div class="review-info-cell">
                                        <span>Email</span>
                                        <strong><?php echo trim((string) ($selectedReview['customer_email'] ?? '')) !== '' ? htmlspecialchars((string) $selectedReview['customer_email'], ENT_QUOTES, 'UTF-8') : 'Not saved'; ?></strong>
                                    </div>
                                    <div class="review-info-cell">
                                        <span>Phone</span>
                                        <strong><?php echo trim((string) ($selectedReview['customer_phone'] ?? '')) !== '' ? htmlspecialchars((string) $selectedReview['customer_phone'], ENT_QUOTES, 'UTF-8') : 'Not saved'; ?></strong>
                                    </div>
                                    <div class="review-info-cell">
                                        <span>Seating</span>
                                        <strong><?php echo trim((string) ($selectedReview['seating_preference'] ?? '')) !== '' ? htmlspecialchars((string) $selectedReview['seating_preference'], ENT_QUOTES, 'UTF-8') : 'No preference'; ?></strong>
                                    </div>
                                    <div class="review-info-cell">
                                        <span>Dietary</span>
                                        <strong><?php echo trim((string) ($selectedReview['dietary_notes'] ?? '')) !== '' ? htmlspecialchars((string) $selectedReview['dietary_notes'], ENT_QUOTES, 'UTF-8') : 'None'; ?></strong>
                                    </div>
                                </div>
                            </section>

                            <section class="review-detail-section">
                                <div class="review-section-title">
                                    <h3>Follow-up</h3>
                                </div>
                                <ol class="review-followup-list">
                                    <?php foreach ($selectedFollowUps as $followUp): ?>
                                        <li><?php echo htmlspecialchars($followUp, ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            </section>
                        </aside>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
