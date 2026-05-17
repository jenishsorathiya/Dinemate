<?php
require_once "../config/db.php";
require_once "../includes/functions.php";
require_once "../includes/session-check.php";

requireCustomer();
ensureBookingRequestColumns($pdo);
ensureBookingReviewsSchema($pdo);

$userId = (int) getCurrentUserId();
$customerProfile = ensureCustomerProfileForUser($pdo, $userId);
$profileId = $customerProfile ? (int) ($customerProfile['customer_profile_id'] ?? 0) : 0;

$bookingId = max(0, intval($_GET['id'] ?? $_POST['id'] ?? 0));
$errors = [];
$successMessage = '';
$booking = null;
$existingReview = null;

if (empty($_SESSION['rate_booking_csrf_token'])) {
    $_SESSION['rate_booking_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['rate_booking_csrf_token'];

if ($bookingId > 0) {
    $stmt = $pdo->prepare(
        "SELECT b.* FROM bookings b WHERE b.booking_id = ? AND (b.user_id = ? OR (b.customer_profile_id = ? AND ? > 0)) LIMIT 1"
    );
    $stmt->execute([$bookingId, $userId, $profileId, $profileId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$booking) {
    $errors[] = 'Booking not found.';
} elseif (strtolower((string) ($booking['status'] ?? '')) !== 'completed') {
    $errors[] = 'Reviews can only be submitted for completed bookings.';
}

if ($booking) {
    $existingReview = getBookingReviewByBookingId($pdo, $bookingId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if ($submittedToken === '' || !hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a rating from 1 to 5.';
    }

    if (empty($errors)) {
        if ($existingReview) {
            $updateStmt = $pdo->prepare("UPDATE booking_reviews SET review_rating = ?, review_comment = ?, reviewed_at = CURRENT_TIMESTAMP WHERE booking_id = ?");
            $updateStmt->execute([$rating, $comment !== '' ? $comment : null, $bookingId]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO booking_reviews (booking_id, review_rating, review_comment) VALUES (?, ?, ?)");
            $insertStmt->execute([$bookingId, $rating, $comment !== '' ? $comment : null]);
        }

        $successMessage = 'Thank you for your review. Your feedback has been saved.';
        $existingReview = getBookingReviewByBookingId($pdo, $bookingId);
    }
}

$bookingDate = $booking ? date('D, j M Y', strtotime((string) ($booking['booking_date'] ?? ''))) : '';
$bookingTime = $booking ? sprintf('%s - %s', date('g:i A', strtotime((string) ($booking['start_time'] ?? '00:00:00'))), date('g:i A', strtotime((string) ($booking['end_time'] ?? '00:00:00')))) : '';
$statusLabel = $booking ? getBookingStatusLabel(strtolower((string) ($booking['status'] ?? 'pending'))) : '';
?>

<?php include "../includes/header.php"; ?>

<style>
.rate-booking-shell {
    margin: 118px auto 84px;
    max-width: 940px;
    padding: 0 20px;
}

.review-panel {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
}

.review-panel h1 {
    margin-top: 0;
    font-size: 32px;
}

.review-panel p {
    color: var(--dm-text-muted);
    margin-bottom: 18px;
}

.review-meta {
    display: grid;
    gap: 14px;
    margin-bottom: 22px;
}

.review-meta span {
    color: var(--dm-text);
    font-size: 14px;
}

.review-form {
    display: grid;
    gap: 20px;
}

.rating-options {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.rating-option {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 54px;
    height: 48px;
    padding: 0 12px;
    border: 1px solid var(--dm-border);
    border-radius: 12px;
    background: var(--dm-surface);
    color: var(--dm-text);
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.rating-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.rating-option i {
    font-size: 12px;
}

.rating-option:has(input:checked),
.rating-option.selected,
.rating-option:hover {
    border-color: #4A7C59;
    background: rgba(74, 124, 89, 0.1);
}

.review-comment {
    width: 100%;
    min-height: 140px;
    padding: 14px;
    border: 1px solid var(--dm-border);
    border-radius: 12px;
    background: var(--dm-surface);
    color: var(--dm-text);
    resize: vertical;
    font-family: inherit;
    font-size: 14px;
}

.review-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.review-actions .btn-primary-solid,
.review-actions .btn-surface {
    min-width: 160px;
}

.notification-box {
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid transparent;
    margin-bottom: 20px;
}

.notification-box.success {
    background: #ebf6ec;
    border-color: #bbd9bc;
    color: #2a5d2d;
}

.notification-box.error {
    background: #fbeaea;
    border-color: #ebb5b5;
    color: #8b2727;
}
</style>

<div class="rate-booking-shell">
    <div class="review-panel">
        <h1>Rate Your Dining Experience</h1>
        <p>Share your rating and comments for the completed reservation.</p>

        <?php if (!empty($errors)): ?>
            <div class="notification-box error">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($successMessage !== ''): ?>
            <div class="notification-box success">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($booking): ?>
            <div class="review-meta">
                <span><strong>Booking:</strong> <?php echo htmlspecialchars($bookingDate, ENT_QUOTES, 'UTF-8'); ?> at <?php echo htmlspecialchars($bookingTime, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><strong>Status:</strong> <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><strong>Guests:</strong> <?php echo (int) ($booking['number_of_guests'] ?? 0); ?></span>
                <span><strong>Table:</strong> <?php echo !empty($booking['table_id']) ? 'Assigned' : 'Pending assignment'; ?></span>
            </div>

            <form method="POST" class="review-form">
                <input type="hidden" name="id" value="<?php echo (int) $bookingId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <strong>Your rating</strong>
                    <div class="rating-options" role="radiogroup" aria-label="Your rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php
                            $isSelected = $existingReview && (int) ($existingReview['review_rating'] ?? 0) === $i;
                            $ratingInputId = 'rating-' . $i;
                            ?>
                            <label class="rating-option<?php echo $isSelected ? ' selected' : ''; ?>">
                                <input id="<?php echo htmlspecialchars($ratingInputId, ENT_QUOTES, 'UTF-8'); ?>" type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $isSelected ? 'checked' : ''; ?> required>
                                <span><?php echo $i; ?></span>
                                <i class="fa fa-star" aria-hidden="true"></i>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <div>
                    <label for="comment"><strong>Comments</strong></label>
                    <textarea id="comment" name="comment" class="review-comment" placeholder="Share what you enjoyed or what we can improve."><?php echo htmlspecialchars($existingReview['review_comment'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="review-actions">
                    <button type="submit" class="btn-primary-solid"><i class="fa fa-star"></i> Submit Review</button>
                    <a href="my-bookings.php" class="btn-surface">Back to My Bookings</a>
                </div>
            </form>
        <?php else: ?>
            <div class="review-meta">
                <span><strong>Review cannot be submitted for this booking.</strong></span>
            </div>
            <a href="my-bookings.php" class="btn-primary-solid">Back to My Bookings</a>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.rating-option input').forEach(function (input) {
    input.addEventListener('change', function () {
        document.querySelectorAll('.rating-option').forEach(function (option) {
            option.classList.toggle('selected', Boolean(option.querySelector('input:checked')));
        });
    });
});
</script>

<?php include "../includes/footer.php"; ?>
