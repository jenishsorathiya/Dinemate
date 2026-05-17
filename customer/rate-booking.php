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

$csrfToken = csrfToken('booking_review');

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
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim((string) ($_POST['comment'] ?? ''));

    if (!verifyCsrfToken('booking_review')) {
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

<?php
$pageTitle = 'Rate Booking | DineMate';
$extraStylesheets = ['assets/css/pages/customer-rate-booking.css'];
$extraFooterScripts = ['assets/js/pages/customer-rate-booking.js'];
include '../includes/header.php';
?>


<div class="rate-booking-shell">
    <div class="review-panel">
        <h1>Share Your Visit Notes</h1>
        <p>Rate your visit and share anything you would like the restaurant team to know.</p>

        <?php if (!empty($errors)): ?>
            <div class="notification-box error">
                <ul class="rating-error-list">
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
                    <a href="my-bookings.php" class="btn-surface">Back to Reservations</a>
                </div>
            </form>
        <?php else: ?>
            <div class="review-meta">
                <span><strong>Review cannot be submitted for this booking.</strong></span>
            </div>
            <a href="my-bookings.php" class="btn-primary-solid">Back to Reservations</a>
        <?php endif; ?>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
