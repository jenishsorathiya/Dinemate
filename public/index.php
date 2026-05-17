<?php
$pageTitle = 'DineMate | Old Canberra Inn Reservations';
include __DIR__ . '/../includes/header.php';
?>

<main class="guest-main">
    <section class="guest-hero" style="--guest-hero-image: url('<?= htmlspecialchars(appPath('assets/images/editorial/guest-hero.jpg'), ENT_QUOTES, 'UTF-8') ?>'); --guest-hero-position: center;">
        <div class="guest-hero-inner">
            <p class="guest-kicker"><i class="fa fa-calendar-check"></i> DineMate for Old Canberra Inn</p>
            <h1 class="guest-display">Gather at Old Canberra Inn.</h1>
            <p class="guest-hero-copy">Book a table, browse the menu, and keep your visits in one place with DineMate.</p>
            <div class="guest-action-row">
                <a class="guest-button" href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa fa-calendar-plus"></i> Book a Table
                </a>
                <a class="guest-button-outline" href="<?= htmlspecialchars(appPath('public/menu.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa fa-utensils"></i> Explore Menu
                </a>
            </div>
        </div>
    </section>

    <section class="guest-quick-strip" aria-label="DineMate highlights">
        <div class="guest-quick-strip-grid">
            <div class="guest-quick-item">
                <strong>Book</strong>
                <span>Choose your date, time, and party size in a few quick steps.</span>
            </div>
            <div class="guest-quick-item">
                <strong>Gather</strong>
                <span>Add allergies, pram space, birthdays, or seating notes.</span>
            </div>
            <div class="guest-quick-item">
                <strong>Return</strong>
                <span>Sign in to view upcoming reservations and past visits.</span>
            </div>
            <div class="guest-quick-item">
                <strong>Share</strong>
                <span>Leave a note after your meal and help us keep improving.</span>
            </div>
        </div>
    </section>

    <section class="guest-section is-paper">
        <div class="guest-container guest-content-band">
            <div>
                <p class="guest-section-kicker"><i class="fa fa-table-cells-large"></i> Old Canberra Inn</p>
                <h2 class="guest-section-title">A warm table, a cold drink, and an easy way in.</h2>
                <p class="guest-section-copy">From casual lunches to bigger nights out, DineMate helps you choose a time and tell the venue what matters before you arrive.</p>
                <div class="guest-card-actions">
                    <a class="guest-link-button" href="<?= htmlspecialchars(appPath('auth/register.php'), ENT_QUOTES, 'UTF-8') ?>">Create a guest account</a>
                </div>
            </div>
            <div class="guest-proof-list">
                <article>
                    <span><i class="fa fa-clock"></i></span>
                    <div>
                        <h3>Pick a time</h3>
                        <p>Choose a date, arrival time, and party size without leaving the page.</p>
                    </div>
                </article>
                <article>
                    <span><i class="fa fa-note-sticky"></i></span>
                    <div>
                        <h3>Add the details</h3>
                        <p>Share allergies, accessibility needs, birthdays, or seating preferences.</p>
                    </div>
                </article>
                <article>
                    <span><i class="fa fa-repeat"></i></span>
                    <div>
                        <h3>Return easily</h3>
                        <p>Use your account to view reservations and keep preferences ready.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="guest-section">
        <div class="guest-container">
            <div class="guest-section-heading">
                <p class="guest-section-kicker">Plan Your Visit</p>
                <h2 class="guest-section-title">Everything you need before you walk in.</h2>
                <p class="guest-section-copy">Choose a table time, save your preferences, and keep your reservation details handy for the day you visit.</p>
            </div>
            <div class="guest-grid-3">
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-calendar-days"></i></div>
                    <h3>Simple booking</h3>
                    <p>Pick a date, time, party size, and any notes the team should know.</p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-user-check"></i></div>
                    <h3>Saved preferences</h3>
                    <p>Keep seating preferences, dietary notes, and contact details ready for next time.</p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-star"></i></div>
                    <h3>After your meal</h3>
                    <p>Leave a quick rating and share anything that made the visit memorable.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="guest-section is-green">
        <div class="guest-container guest-content-band">
            <div>
                <p class="guest-section-kicker">For Any Occasion</p>
                <h2 class="guest-section-title">Lunch, dinner, drinks, or the long Sunday catch-up.</h2>
                <p class="guest-section-copy">Tell us who is coming and what you need. The team will do their best to make the visit feel easy from the start.</p>
                <div class="guest-action-row">
                    <a class="guest-button" href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>">Start a booking</a>
                    <a class="guest-button-outline" href="<?= htmlspecialchars(appPath('public/about.php'), ENT_QUOTES, 'UTF-8') ?>">About the venue</a>
                </div>
            </div>
            <div class="guest-proof-list is-inverted">
                <article>
                    <span><i class="fa fa-users"></i></span>
                    <div>
                        <h3>Small tables</h3>
                        <p>Casual meals, quick plans, and everyday catch-ups stay simple.</p>
                    </div>
                </article>
                <article>
                    <span><i class="fa fa-champagne-glasses"></i></span>
                    <div>
                        <h3>Occasions</h3>
                        <p>Add a note when the table needs a little extra care.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="guest-section is-paper">
        <div class="guest-container guest-content-band">
            <div>
                <p class="guest-section-kicker">On The Table</p>
                <h2 class="guest-section-title">Comfort food, shared plates, and cold drinks.</h2>
                <p class="guest-section-copy">Browse the menu before you book, or head straight to a table request when you already know the plan.</p>
            </div>
            <div>
                <div class="guest-menu-list">
                    <div class="guest-menu-row">
                        <div>
                            <h3>Share plates and pub favourites</h3>
                            <p>Start with something to share, then settle in for the main event.</p>
                        </div>
                        <span class="guest-price">Menu</span>
                    </div>
                    <div class="guest-menu-row">
                        <div>
                            <h3>Functions and group dining</h3>
                            <p>Planning a larger table? Send the details and the team will help from there.</p>
                        </div>
                        <span class="guest-price">Events</span>
                    </div>
                    <div class="guest-menu-row">
                        <div>
                            <h3>Customer portal</h3>
                            <p>Keep upcoming bookings and past visits together when you sign in.</p>
                        </div>
                        <span class="guest-price">Account</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="guest-section">
        <div class="guest-container">
            <div class="guest-section-heading">
                <p class="guest-section-kicker">Easy From Anywhere</p>
                <h2 class="guest-section-title">Designed for the moment you decide to book.</h2>
            </div>
            <div class="guest-grid-4">
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-mobile-screen"></i></div>
                    <h3>Mobile first</h3>
                    <p>Book comfortably from your phone, whether you are planning ahead or already on the move.</p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-repeat"></i></div>
                    <h3>Easy return visits</h3>
                    <p>Use a past booking as a starting point when you want to come back again.</p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-bell"></i></div>
                    <h3>Reminders</h3>
                    <p>Choose reminder preferences from your profile and keep plans easier to remember.</p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-lock"></i></div>
                    <h3>Your details</h3>
                    <p>Share only what the venue needs to handle your booking and contact you if plans change.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="guest-section is-green">
        <div class="guest-container guest-cta-panel">
            <div>
                <p class="guest-section-kicker">Ready when guests are</p>
                <h2 class="guest-section-title">Book now. Manage later.</h2>
                <p class="guest-section-copy">Start with a table request, then use your customer account to keep upcoming plans and dining preferences organised.</p>
            </div>
            <div class="guest-inline-actions">
                <a class="guest-button" href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa fa-calendar-check"></i> Book a Table
                </a>
                <a class="guest-button-outline" href="<?= htmlspecialchars(appPath('auth/login.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="fa fa-user"></i> Customer Login
                </a>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
