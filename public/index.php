<?php
$pageTitle = 'DineMate | Old Canberra Inn';
$extraStylesheets = ['assets/css/pages/landing.css'];
$extraFooterScripts = ['assets/js/pages/landing.js'];
include __DIR__ . '/../includes/header.php';
?>

<!-- ============ HERO SECTION ============ -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-tag">
            <i class="fa fa-calendar-check"></i> Old Canberra Inn Reservations
        </div>
        
        <h1>
            Book <span class="hero-highlight">Old Canberra Inn</span>
            <br>
            Without the Wait
        </h1>
        
        <p>Choose your date, time, and party size online, then manage every reservation from your DineMate account.</p>
        
        <div class="hero-buttons">
            <a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-primary-hero">
                <i class="fa fa-calendar-check"></i> Reserve Now
            </a>
            <a href="<?= htmlspecialchars(appPath('public/menu.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-secondary-hero">
                <i class="fa fa-utensils"></i> View Menu
            </a>
        </div>

        <div class="hero-stats">
            <div class="stat">
                <div class="stat-number">50+</div>
                <div class="stat-label">Dining Spaces</div>
            </div>
            <div class="stat">
                <div class="stat-number">1000+</div>
                <div class="stat-label">Guest Records</div>
            </div>
            <div class="stat">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Online Requests</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ VIBE SECTION ============ -->
<section class="vibe-section">
    <div class="vibe-container">
        <div class="vibe-badge">
            <i class="fa fa-landmark"></i> Heritage Dining
        </div>
        <h2>Historic character, modern reservations</h2>
        <p>DineMate brings a cleaner booking experience to a Canberra landmark, helping guests reserve faster and staff manage service with confidence.</p>
        
        <div class="vibe-grid">
            <div class="vibe-item">
                <div class="vibe-icon"><i class="fa fa-crown"></i></div>
                <h3>Warm Atmosphere</h3>
                <p>Heritage interiors, relaxed service, and flexible spaces for casual meals, celebrations, and group dining.</p>
            </div>
            <div class="vibe-item">
                <div class="vibe-icon"><i class="fa fa-wine-glass"></i></div>
                <h3>Seasonal Menu</h3>
                <p>Browse favourites before you book, from share plates and burgers to hearty Old Canberra Inn classics.</p>
            </div>
            <div class="vibe-item">
                <div class="vibe-icon"><i class="fa fa-handshake"></i></div>
                <h3>Staff Visibility</h3>
                <p>Booking notes, dietary needs, and seating preferences are easier for the team to see before you arrive.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============ TRUST SECTION ============ -->
<section class="trust-section">
    <div class="trust-content">
        <div class="trust-item">
            <div class="trust-icon"><i class="fa fa-bolt"></i></div>
            <div class="trust-label">Fast Booking Requests</div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="fa fa-lock"></i></div>
            <div class="trust-label">Secure Guest Forms</div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="fa fa-headset"></i></div>
            <div class="trust-label">Staff-managed Updates</div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="fa fa-award"></i></div>
            <div class="trust-label">Review After Your Visit</div>
        </div>
    </div>
</section>

<!-- ============ FEATURES SECTION ============ -->
<section class="features-section">
    <div class="section-header">
        <div class="section-overline">Why DineMate</div>
        <h2 class="section-title">A smoother booking journey</h2>
        <p class="section-description">Everything guests need to reserve, update, review, and return to their favourite table.</p>
    </div>

    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-search"></i></div>
            <h3>Clear Availability</h3>
            <p>Pick from open dates, service times, and party sizes without calling during a busy shift.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-rocket"></i></div>
            <h3>Fast Booking Flow</h3>
            <p>Reserve in a few focused steps with a mobile-friendly calendar and simple guest details.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-bell"></i></div>
            <h3>Preference Notes</h3>
            <p>Save dietary notes, seating preferences, and reminder settings for future bookings.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-mobile-alt"></i></div>
            <h3>Mobile First</h3>
            <p>Book, reschedule, cancel, or review a visit comfortably from any screen size.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-users"></i></div>
            <h3>Group Friendly</h3>
            <p>Add party size and special requests so staff can prepare for celebrations and larger groups.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-star"></i></div>
            <h3>Visit History</h3>
            <p>Keep upcoming reservations and past visits in one place, with quick rebooking when available.</p>
        </div>
    </div>
</section>

<!-- ============ SHOWCASE SECTION ============ -->
<section class="showcase-section">
    <div class="showcase-wrapper">
        <div class="showcase-content">
            <h2>Dining made easier for guests and staff</h2>
            <p>DineMate keeps the reservation process focused: guests can submit clear requests, and the restaurant team can manage those requests from the admin dashboard.</p>

            <ul class="showcase-list">
                <li>Guided table reservation requests</li>
                <li>Booking history and quick rebooking</li>
                <li>Reschedule and cancellation actions</li>
                <li>Saved dietary and seating preferences</li>
                <li>Post-visit review flow</li>
                <li>Admin tools for booking follow-up</li>
            </ul>

            <a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-primary-hero">
                Book a Table
            </a>
        </div>

        <div class="showcase-image">
            <img src="<?= htmlspecialchars(appPath('assets/images/showcase/canberra-interior-1.webp'), ENT_QUOTES, 'UTF-8') ?>" alt="Canberra Inn Fine Dining Experience">
            <div class="showcase-badge">Guest-ready flow</div>
        </div>
    </div>
</section>

<!-- ============ TESTIMONIALS SECTION ============ -->
<section class="testimonials-section">
    <div class="section-header">
        <div class="section-overline">Review Flow</div>
        <h2 class="section-title">After the meal, feedback stays simple</h2>
    </div>

    <div class="testimonials-grid">
        <div class="testimonial-card">
            <div class="testimonial-header">
                <div class="testimonial-avatar">JS</div>
                <div>
                    <h4>Rate your visit</h4>
                    <div class="testimonial-role">Customer portal</div>
                </div>
            </div>
            <div class="testimonial-stars">★★★★★</div>
            <p class="testimonial-text">Guests can leave a star rating and short note after a completed booking, giving the restaurant useful context.</p>
        </div>

        <div class="testimonial-card">
            <div class="testimonial-header">
                <div class="testimonial-avatar avatar-dark">SA</div>
                <div>
                    <h4>Track service quality</h4>
                    <div class="testimonial-role">Admin reviews</div>
                </div>
            </div>
            <div class="testimonial-stars">★★★★★</div>
            <p class="testimonial-text">Staff can review recent feedback from the admin area and spot repeat issues or standout experiences.</p>
        </div>

        <div class="testimonial-card">
            <div class="testimonial-header">
                <div class="testimonial-avatar avatar-dark">MJ</div>
                <div>
                    <h4>Return with context</h4>
                    <div class="testimonial-role">Guest history</div>
                </div>
            </div>
            <div class="testimonial-stars">★★★★★</div>
            <p class="testimonial-text">Customer profiles keep preferences, notes, and past bookings connected for a more thoughtful next visit.</p>
        </div>
    </div>

    <div class="testimonial-actions">
        <a href="<?= htmlspecialchars(appPath('customer/my-bookings.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-secondary-hero">
            <i class="fa fa-star"></i> Review Your Visit
        </a>
    </div>
</section>

<!-- ============ PRICING SECTION ============ -->
<section class="pricing-section">
    <div class="section-header">
        <div class="section-overline">Booking Options</div>
        <h2 class="section-title">Choose how you want to book</h2>
        <p class="section-description">No payment is required in this demo. These options guide guests to the right next action.</p>
    </div>

    <div class="pricing-grid">
        <div class="pricing-card">
            <h3>Guest Booking</h3>
            <div class="pricing-price">Free</div>
            <div class="pricing-period">No account required</div>
            <ul class="pricing-features">
                <li>Submit a reservation request</li>
                <li>Add dietary or seating notes</li>
                <li>Mobile-friendly booking form</li>
                <li>Secure guest details</li>
                <li>Contact the restaurant if plans change</li>
            </ul>
            <button
                class="pricing-btn"
                type="button"
                data-plan-modal
                data-plan-title="Guest Booking"
                data-plan-price="Free booking request"
                data-plan-copy="Best for a quick reservation when you do not need booking history or saved preferences."
                data-plan-primary-label="Book a Table"
                data-plan-primary-url="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>"
                data-plan-secondary-label="Create Account"
                data-plan-secondary-url="<?= htmlspecialchars(appPath('auth/register.php'), ENT_QUOTES, 'UTF-8') ?>"
            >Get Started</button>
        </div>

        <div class="pricing-card featured">
            <div class="pricing-badge">Most Popular</div>
            <h3>Member Account</h3>
            <div class="pricing-price">Free</div>
            <div class="pricing-period">For returning guests</div>
            <ul class="pricing-features">
                <li>Booking history and rebooking</li>
                <li>Profile and preference management</li>
                <li>Modify or cancel eligible bookings</li>
                <li>Review completed visits</li>
                <li>Reminder preferences</li>
                <li>Saved contact details</li>
            </ul>
            <button
                class="pricing-btn"
                type="button"
                data-plan-modal
                data-plan-title="Member Account"
                data-plan-price="Free customer account"
                data-plan-copy="For returning guests who want saved details, booking history, rebooking, and post-visit reviews."
                data-plan-primary-label="Create Account"
                data-plan-primary-url="<?= htmlspecialchars(appPath('auth/register.php'), ENT_QUOTES, 'UTF-8') ?>"
                data-plan-secondary-label="Book First"
                data-plan-secondary-url="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>"
            >Create Account</button>
        </div>

        <div class="pricing-card">
            <h3>Group Dining</h3>
            <div class="pricing-price">Contact</div>
            <div class="pricing-period">For larger occasions</div>
            <ul class="pricing-features">
                <li>Celebrations and larger groups</li>
                <li>Special requests and notes</li>
                <li>Direct restaurant follow-up</li>
                <li>Flexible seating discussion</li>
                <li>Function enquiry support</li>
            </ul>
            <button
                class="pricing-btn"
                type="button"
                data-plan-modal
                data-plan-title="Group Dining"
                data-plan-price="Contact the team"
                data-plan-copy="For celebrations, teams, and larger groups that need direct staff follow-up before the visit."
                data-plan-primary-label="Contact Team"
                data-plan-primary-url="<?= htmlspecialchars(appPath('public/contact.php'), ENT_QUOTES, 'UTF-8') ?>"
                data-plan-secondary-label="View Function Options"
                data-plan-secondary-url="<?= htmlspecialchars(appPath('public/about.php'), ENT_QUOTES, 'UTF-8') ?>"
            >Contact Team</button>
        </div>
    </div>
</section>

<div class="plan-modal" data-plan-modal-root hidden>
    <div class="plan-modal-card" role="dialog" aria-modal="true" aria-labelledby="plan-modal-title">
        <button class="plan-modal-close" type="button" data-plan-modal-close aria-label="Close plan details">
            <i class="fa fa-xmark" aria-hidden="true"></i>
        </button>
        <div class="section-overline">Plan Details</div>
        <h2 id="plan-modal-title" data-plan-title>Plan</h2>
        <strong data-plan-price></strong>
        <p data-plan-copy></p>
        <div class="plan-modal-actions">
            <a class="btn-primary-hero" href="#" data-plan-primary></a>
            <a class="btn-secondary-hero" href="#" data-plan-secondary></a>
        </div>
    </div>
</div>

<!-- ============ CTA SECTION ============ -->
<section class="cta-section">
    <div class="cta-content">
        <h2>Ready to reserve your table?</h2>
        <p>Book online, save your preferences, and keep your plans easy to manage from the customer portal.</p>
        
        <div class="cta-buttons">
            <a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-cta-primary">
                <i class="fa fa-calendar-check"></i> Make Your Reservation
            </a>
            <a href="<?= htmlspecialchars(appPath('public/about.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-cta-primary btn-cta-secondary">
                <i class="fa fa-info-circle"></i> Learn More
            </a>
        </div>
    </div>
</section>

<!-- ============ FOOTER CTA ============ -->
<section class="footer-cta">
    <h2>Need help with a booking?</h2>
    <p>Send the team a message or browse the menu before choosing your table request.</p>
    
    <div class="footer-buttons">
        <a href="<?= htmlspecialchars(appPath('public/contact.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-footer btn-footer-primary">
            <i class="fa fa-envelope"></i> Get in Touch
        </a>
        <a href="<?= htmlspecialchars(appPath('public/menu.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-footer btn-footer-secondary">
            <i class="fa fa-book"></i> View Our Menu
        </a>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>


