<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/config/db.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/functions.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/Dinemate/includes/session-check.php";

requireAdmin();
ensureBookingRequestColumns($pdo);
ensureBookingReviewsSchema($pdo);

$adminSidebarActive = 'reviews';
$adminNewSidebarActive = 'reviews';

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$ratingFilter = intval($_GET['rating'] ?? 0);

$whereClauses = ['1 = 1'];
$params = [];

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $whereClauses[] = 'b.booking_date >= ?';
    $params[] = $dateFrom;
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $whereClauses[] = 'b.booking_date <= ?';
    $params[] = $dateTo;
}

if ($ratingFilter >= 1 && $ratingFilter <= 5) {
    $whereClauses[] = 'br.review_rating = ?';
    $params[] = $ratingFilter;
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
        t.table_number,
        COALESCE(cp.name, b.customer_name, '') AS customer_name,
        COALESCE(cp.email, b.customer_email, '') AS customer_email,
        COALESCE(cp.phone, b.customer_phone, '') AS customer_phone,
        creator.name AS created_by_name
    FROM booking_reviews br
    INNER JOIN bookings b ON b.booking_id = br.booking_id
    LEFT JOIN restaurant_tables t ON t.table_id = b.table_id
    LEFT JOIN customer_profiles cp ON b.customer_profile_id = cp.customer_profile_id
    LEFT JOIN users creator ON b.created_by_user_id = creator.user_id
    WHERE " . implode(' AND ', $whereClauses) . "
    ORDER BY br.reviewed_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalReviews = count($reviews);
$averageRating = $totalReviews > 0 ? round(array_sum(array_column($reviews, 'review_rating')) / $totalReviews, 1) : 0;
$reviewedThisWeek = 0;
$weekStart = strtotime('monday this week');
foreach ($reviews as $review) {
    $reviewedAt = strtotime((string) $review['reviewed_at']);
    if ($reviewedAt !== false && $reviewedAt >= $weekStart) {
        $reviewedThisWeek++;
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
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo htmlspecialchars($styleVersion, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        .content-shell {
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px 24px 40px;
            display: grid;
            gap: 24px;
        }

        .panel-card {
            padding: 22px;
            border-radius: 18px;
            border: 1px solid var(--dm-border);
            background: var(--dm-surface);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }

        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 0 6px;
        }

        .page-heading h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
        }

        .page-heading p {
            margin: 10px 0 0;
            color: var(--dm-text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 16px;
        }

        .kpi-card {
            border: 1px solid var(--dm-border);
            border-radius: 16px;
            background: var(--dm-surface);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
        }

        .kpi-copy {
            display: grid;
            gap: 6px;
        }

        .kpi-value {
            color: var(--dm-text);
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }

        .kpi-label {
            margin: 0;
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .kpi-subtext {
            color: var(--dm-text-muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 18px;
            color: var(--dm-accent-dark);
            background: rgba(59, 130, 246, 0.12);
        }

        .filter-panel {
            display: grid;
            grid-template-columns: repeat(4, minmax(220px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: grid;
            gap: 8px;
        }

        .filter-group label {
            color: var(--dm-text-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .filter-control {
            width: 100%;
            min-height: 44px;
            border-radius: 14px;
            border: 1px solid var(--dm-border);
            background: var(--dm-surface);
            color: var(--dm-text);
            padding: 12px 14px;
            font-size: 14px;
        }

        .filter-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-surface,
        .btn-primary-solid {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            border-radius: 14px;
            padding: 0 18px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            border: 1px solid transparent;
            transition: opacity 0.15s ease, transform 0.15s ease;
            cursor: pointer;
        }

        .btn-primary-solid {
            background: var(--dm-accent-dark);
            color: var(--dm-surface);
            border-color: var(--dm-accent-dark);
        }

        .btn-primary-solid:hover,
        .btn-primary-solid:focus {
            opacity: 0.92;
        }

        .btn-surface {
            background: var(--dm-surface);
            color: var(--dm-text);
            border-color: var(--dm-border);
        }

        .filter-actions .btn-primary-solid,
        .filter-actions .btn-surface {
            min-width: 140px;
        }

        .table-wrap {
            border: 1px solid var(--dm-border);
            border-radius: 20px;
            overflow: hidden;
            background: var(--dm-surface);
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
            table-layout: fixed;
        }

        .table-custom th,
        .table-custom td {
            padding: 18px 16px;
            border-bottom: 1px solid var(--dm-border);
            text-align: left;
            vertical-align: top;
        }

        .table-custom thead th {
            background: rgba(248, 250, 252, 0.95);
            color: var(--dm-text);
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .table-custom tbody tr:hover {
            background: rgba(247, 248, 250, 0.96);
        }

        .rating-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 224, 94, 0.2);
            color: #7f5c00;
            font-weight: 700;
            font-size: 13px;
        }

        .review-comment-cell {
            max-width: 320px;
            white-space: pre-wrap;
            word-break: break-word;
            color: var(--dm-text);
        }

        .booking-meta-list {
            display: grid;
            gap: 8px;
            color: var(--dm-text-muted);
            font-size: 13px;
        }

        .booking-meta-list span {
            display: block;
        }

        .empty-state {
            padding: 40px 24px;
            text-align: center;
            color: var(--dm-text-muted);
        }

        @media (max-width: 1080px) {
            .kpi-grid,
            .filter-panel {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }

        @media (max-width: 720px) {
            .kpi-grid,
            .filter-panel {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                justify-content: stretch;
            }

            .filter-actions .btn-primary-solid,
            .filter-actions .btn-surface {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <?php include __DIR__ . '/../partials/admin-new-sidebar.php'; ?>

        <main class="main-content" aria-label="Booking reviews page">
            <div class="content-shell">
                <section class="page-heading">
                    <div>
                        <h1>Booking Reviews</h1>
                        <p>Review history with booking details, customer information, and rating filters.</p>
                    </div>
                </section>

                <section class="kpi-grid">
                    <div class="kpi-card">
                        <div class="kpi-copy">
                            <div class="kpi-label">Total reviews</div>
                            <div class="kpi-value"><?php echo number_format($totalReviews); ?></div>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-chat-square-dots"></i></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-copy">
                            <div class="kpi-label">Average rating</div>
                            <div class="kpi-value"><?php echo $totalReviews > 0 ? htmlspecialchars(number_format($averageRating, 1), ENT_QUOTES, 'UTF-8') : '-'; ?></div>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-star-fill"></i></div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-copy">
                            <div class="kpi-label">Reviewed this week</div>
                            <div class="kpi-value"><?php echo number_format($reviewedThisWeek); ?></div>
                        </div>
                        <div class="kpi-icon"><i class="bi bi-calendar-check"></i></div>
                    </div>
                </section>

                <section class="panel-card">
                    <form method="GET" novalidate>
                        <div class="filter-panel">
                            <div class="filter-group">
                                <label for="date_from">Booking date from</label>
                                <input type="date" id="date_from" name="date_from" class="filter-control" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="date_to">Booking date to</label>
                                <input type="date" id="date_to" name="date_to" class="filter-control" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="rating">Rating</label>
                                <select id="rating" name="rating" class="filter-control">
                                    <option value="0">All ratings</option>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $ratingFilter === $i ? 'selected' : ''; ?>><?php echo $i; ?> / 5</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn-primary-solid">Apply filters</button>
                                <a href="admin_booking_reviews.php" class="btn-surface">Clear filters</a>
                            </div>
                        </div>
                    </form>
                </section>

                <?php if (empty($reviews)): ?>
                <div class="panel-card empty-state">
                    <p>No reviews were found for the selected criteria.</p>
                </div>
            <?php else: ?>
                <section class="panel-card">
                    <div class="table-wrap">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Review</th>
                                    <th>Booking</th>
                                    <th>Customer</th>
                                    <th>Booking details</th>
                                    <th>Comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>
                                        <div class="rating-pill"><?php echo (int) $review['review_rating']; ?> / 5</div>
                                        <div style="margin-top: 8px; color: var(--dm-text-muted); font-size: 13px;">Reviewed <?php echo htmlspecialchars(date('j M Y, g:i A', strtotime((string) $review['reviewed_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(date('j M Y', strtotime((string) $review['booking_date'])), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <?php echo htmlspecialchars(date('g:i A', strtotime((string) $review['start_time'])), ENT_QUOTES, 'UTF-8'); ?>
                                        - <?php echo htmlspecialchars(date('g:i A', strtotime((string) $review['end_time'])), ENT_QUOTES, 'UTF-8'); ?><br>
                                        <span class="booking-meta-list">
                                            <span>Status: <?php echo htmlspecialchars(getBookingStatusLabel($review['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span>Source: <?php echo htmlspecialchars(getBookingSourceLabel($review['booking_source']), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span>Guests: <?php echo (int) $review['number_of_guests']; ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars((string) $review['customer_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <?php if ($review['customer_email'] !== ''): ?>
                                            <span><?php echo htmlspecialchars((string) $review['customer_email'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                        <?php endif; ?>
                                        <?php if ($review['customer_phone'] !== ''): ?>
                                            <span><?php echo htmlspecialchars((string) $review['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                        <?php endif; ?>
                                        <?php if ($review['created_by_name'] !== ''): ?>
                                            <span>Created by: <?php echo htmlspecialchars((string) $review['created_by_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="booking-meta-list">
                                            <span>Table: <?php echo !empty($review['table_number']) ? 'Table ' . htmlspecialchars((string) $review['table_number'], ENT_QUOTES, 'UTF-8') : 'Not assigned'; ?></span>
                                            <span>Booking ID: <?php echo (int) $review['booking_id']; ?></span>
                                        </span>
                                    </td>
                                    <td class="review-comment-cell">
                                        <?php echo nl2br(htmlspecialchars((string) $review['review_comment'], ENT_QUOTES, 'UTF-8')); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
