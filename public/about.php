<?php
$pageTitle = 'About DineMate | Old Canberra Inn';
include __DIR__ . '/../includes/header.php';
?>

<main class="guest-main">
    <section class="guest-page-hero" style="--guest-hero-image: url('<?= htmlspecialchars(appPath('assets/images/editorial/dining-room.jpg'), ENT_QUOTES, 'UTF-8') ?>'); --guest-hero-position: center;">
        <div class="guest-hero-inner">
            <p class="guest-kicker">Our Story</p>
            <h1 class="guest-page-title">Heritage pub, easier reservations.</h1>
            <p class="guest-page-copy">DineMate helps guests plan a visit to Old Canberra Inn with less fuss and more confidence.</p>
        </div>
    </section>

    <section class="guest-section is-paper">
        <div class="guest-container guest-content-band">
            <div>
                <p class="guest-section-kicker"><i class="fa fa-landmark"></i> Old Canberra Inn</p>
                <h2 class="guest-section-title">Heritage stays at the centre.</h2>
                <p class="guest-section-copy">Established in 1857, Old Canberra Inn is part of Canberra's social fabric: a place for relaxed meals, local drinks, and familiar faces.</p>
                <p class="guest-section-copy">DineMate keeps the practical side simple, so guests can book, return, and keep their dining preferences close.</p>
            </div>
            <div class="guest-stat-list">
                <article>
                    <strong>1857</strong>
                    <span>Heritage venue</span>
                </article>
                <article>
                    <strong>Anytime</strong>
                    <span>Online table requests</span>
                </article>
                <article>
                    <strong>One place</strong>
                    <span>Bookings, preferences, and reviews</span>
                </article>
            </div>
        </div>
    </section>

    <section class="guest-section">
        <div class="guest-container">
            <div class="guest-section-heading">
                <p class="guest-section-kicker">Why DineMate</p>
                <h2 class="guest-section-title">A calmer way to plan a meal.</h2>
                <p class="guest-section-copy">Whether it is a quick lunch, a family table, or a catch-up over drinks, DineMate helps you share the details that make the visit smoother.</p>
            </div>
            <div class="guest-grid-3">
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-chair"></i></div>
                    <h3>Clear reservations</h3>
                    <p>Your date, time, party size, and notes stay easy to find.</p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-address-card"></i></div>
                    <h3>Guest profiles</h3>
                    <p>Save contact details, seating preferences, dietary notes, and reminders for next time.</p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-comments"></i></div>
                    <h3>Post-visit feedback</h3>
                    <p>After your visit, leave a quick rating or note for the restaurant team.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="guest-section is-green">
        <div class="guest-container guest-content-band">
            <div>
                <p class="guest-section-kicker">The DineMate Promise</p>
                <h2 class="guest-section-title">Book without losing the feeling of the place.</h2>
                <p class="guest-section-copy">The best restaurant plans should feel natural: choose a time, add the important details, and look forward to the meal.</p>
                <div class="guest-action-row">
                    <a class="guest-button" href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>">Book a Table</a>
                    <a class="guest-button-outline" href="<?= htmlspecialchars(appPath('public/contact.php'), ENT_QUOTES, 'UTF-8') ?>">Contact Team</a>
                </div>
            </div>
            <div class="guest-proof-list is-inverted">
                <article>
                    <span><i class="fa fa-calendar-check"></i></span>
                    <div>
                        <h3>Clear table requests</h3>
                        <p>Date, time, party size, and notes stay easy to understand.</p>
                    </div>
                </article>
                <article>
                    <span><i class="fa fa-heart"></i></span>
                    <div>
                        <h3>Thoughtful details</h3>
                        <p>Guest preferences stay close without turning the experience into admin.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../includes/footer.php"; ?>
