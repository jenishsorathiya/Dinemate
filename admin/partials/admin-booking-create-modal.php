<?php
$adminBookingCreateDefaultDate = $adminBookingCreateDefaultDate ?? date('Y-m-d');
$adminBookingCreateMinDate = $adminBookingCreateMinDate ?? date('Y-m-d');
$adminBookingCreateEndpoint = $adminBookingCreateEndpoint ?? '../actions/create-booking.php';
?>
<div class="admin-modal" data-admin-booking-create-modal hidden>
    <div class="admin-modal-card admin-booking-create-modal-card" role="dialog" aria-modal="true" aria-labelledby="admin-booking-create-title">
        <header class="admin-modal-head">
            <div>
                <h2 id="admin-booking-create-title">Add Booking</h2>
                <p>Create a staff booking without leaving the current admin screen.</p>
            </div>
            <button class="icon-btn admin-modal-close" type="button" data-admin-modal-close aria-label="Close add booking modal">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>

        <form class="admin-modal-form" data-admin-booking-create-form data-create-endpoint="<?php echo htmlspecialchars($adminBookingCreateEndpoint, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="admin-modal-grid">
                <label>
                    <span>Guest name</span>
                    <input type="text" name="name" autocomplete="name" required>
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="customer_email" autocomplete="email">
                </label>
                <label>
                    <span>Phone</span>
                    <input type="tel" name="customer_phone" autocomplete="tel">
                </label>
                <label>
                    <span>Date</span>
                    <input type="date" name="booking_date" value="<?php echo htmlspecialchars((string) $adminBookingCreateDefaultDate, ENT_QUOTES, 'UTF-8'); ?>" min="<?php echo htmlspecialchars((string) $adminBookingCreateMinDate, ENT_QUOTES, 'UTF-8'); ?>" required>
                </label>
                <label>
                    <span>Start time</span>
                    <input type="time" name="start_time" value="18:00" min="10:00" max="21:00" required>
                </label>
                <label>
                    <span>Guests</span>
                    <input type="number" name="number_of_guests" value="2" min="1" max="120" required>
                </label>
                <label>
                    <span>Type</span>
                    <select name="booking_type">
                        <option value="normal">Standard booking</option>
                        <option value="trivia">Trivia</option>
                        <option value="function">Function enquiry</option>
                    </select>
                </label>
                <label class="admin-modal-field-wide">
                    <span>Notes</span>
                    <textarea name="special_request" rows="3" placeholder="Dietary notes, celebration details, seating preference..."></textarea>
                </label>
            </div>

            <p class="admin-modal-message" data-admin-booking-create-message hidden></p>

            <footer class="admin-modal-actions">
                <button class="secondary-btn" type="button" data-admin-modal-close>Cancel</button>
                <button class="primary-btn" type="submit">
                    <i class="bi bi-check2" aria-hidden="true"></i>
                    <span>Create Booking</span>
                </button>
            </footer>
        </form>
    </div>
</div>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/pages/admin-dashboard.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
