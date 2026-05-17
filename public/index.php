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
            <i class="fa fa-star"></i> Perfect Reservations, Every Time
        </div>
        
        <h1>
            Discover <span class="hero-highlight">Exceptional Dining</span>
            <br>
            On Your Terms
        </h1>
        
        <p>Book your ideal table in seconds. Skip the wait. Experience the dining you deserve.</p>
        
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
                <div class="stat-label">Premium Tables</div>
            </div>
            <div class="stat">
                <div class="stat-number">1000+</div>
                <div class="stat-label">Happy Diners</div>
            </div>
            <div class="stat">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Booking Available</div>
            </div>
        </div>
    </div>
</section>

<!-- ============ VIBE SECTION ============ -->
<section class="vibe-section">
    <div class="vibe-container">
        <div class="vibe-badge">
            <i class="fa fa-gem"></i> Vibe
        </div>
        <h2>Premium Restaurant / Fine Dining</h2>
        <p>Experience elegance, sophistication, and impeccable service in an atmosphere designed for unforgettable moments.</p>
        
        <div class="vibe-grid">
            <div class="vibe-item">
                <div class="vibe-icon"><i class="fa fa-crown"></i></div>
                <h3>Luxury Ambiance</h3>
                <p>Carefully curated interiors and warm lighting create the perfect setting for any occasion.</p>
            </div>
            <div class="vibe-item">
                <div class="vibe-icon"><i class="fa fa-wine-glass"></i></div>
                <h3>Culinary Excellence</h3>
                <p>Expertly crafted dishes prepared by our award-winning chefs using premium ingredients.</p>
            </div>
            <div class="vibe-item">
                <div class="vibe-icon"><i class="fa fa-handshake"></i></div>
                <h3>Attentive Service</h3>
                <p>Our trained staff ensures every detail is perfect, from greeting to farewell.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============ TRUST SECTION ============ -->
<section class="trust-section">
    <div class="trust-content">
        <div class="trust-item">
            <div class="trust-icon"><i class="fa fa-bolt"></i></div>
            <div class="trust-label">Instant Confirmation</div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="fa fa-lock"></i></div>
            <div class="trust-label">100% Secure</div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="fa fa-headset"></i></div>
            <div class="trust-label">24/7 Support</div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="fa fa-award"></i></div>
            <div class="trust-label">Premium Quality</div>
        </div>
    </div>
</section>

<!-- ============ FEATURES SECTION ============ -->
<section class="features-section">
    <div class="section-header">
        <div class="section-overline">Why DineMate</div>
        <h2 class="section-title">The Smarter Way to Dine Out</h2>
        <p class="section-description">Experience the convenience of modern restaurant reservations with features designed for you.</p>
    </div>

    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-search"></i></div>
            <h3>Real-Time Availability</h3>
            <p>See exactly which tables are available right now. No surprises, no waiting.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-rocket"></i></div>
            <h3>Lightning Fast Booking</h3>
            <p>Reserve your perfect table in under 60 seconds from anywhere.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-bell"></i></div>
            <h3>Smart Reminders</h3>
            <p>Get timely notifications so you never miss or forget your reservation.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-mobile-alt"></i></div>
            <h3>Mobile First</h3>
            <p>Manage bookings on the go with our fully responsive platform.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-users"></i></div>
            <h3>Group Friendly</h3>
            <p>Easily book tables for any party size, from intimate dinners to celebrations.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon"><i class="fa fa-star"></i></div>
            <h3>Loyalty Rewards</h3>
            <p>Earn points with every booking and unlock exclusive dining benefits.</p>
        </div>
    </div>
</section>

<!-- ============ SHOWCASE SECTION ============ -->
<section class="showcase-section">
    <div class="showcase-wrapper">
        <div class="showcase-content">
            <h2>Dining Made Simple</h2>
            <p>DineMate transforms how you experience fine dining. Our intelligent reservation system puts you in control, offering instant bookings, real-time updates, and personalized recommendations.</p>

            <ul class="showcase-list">
                <li>One-click table reservations</li>
                <li>Real-time availability updates</li>
                <li>Instant booking confirmations</li>
                <li>Flexible modification options</li>
                <li>24/7 customer support</li>
                <li>Member exclusive deals</li>
            </ul>

            <a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-primary-hero">
                Start Booking Today
            </a>
        </div>

        <div class="showcase-image">
            <img src="<?= htmlspecialchars(appPath('assets/images/showcase/canberra-interior-1.webp'), ENT_QUOTES, 'UTF-8') ?>" alt="Canberra Inn Fine Dining Experience">
            <div class="showcase-badge">⭐ Top Rated</div>
        </div>
    </div>
</section>

<!-- ============ TESTIMONIALS SECTION ============ -->
<section class="testimonials-section">
    <div class="section-header">
        <div class="section-overline">Customer Love</div>
        <h2 class="section-title">What Our Diners Say</h2>
    </div>

    <div class="testimonials-grid">
        <div class="testimonial-card">
            <div class="testimonial-header">
                <div class="testimonial-avatar">JS</div>
                <div>
                    <h4>Jenish Sorathiya</h4>
                    <div class="testimonial-role">Regular Guest</div>
                </div>
            </div>
            <div class="testimonial-stars">★★★★★</div>
            <p class="testimonial-text">"DineMate made booking so easy! I used to spend 20 minutes on the phone, now it takes 30 seconds. Highly recommend!"</p>
        </div>

        <div class="testimonial-card">
            <div class="testimonial-header">
                <div class="testimonial-avatar avatar-dark">SA</div>
                <div>
                    <h4>Sarah Anderson</h4>
                    <div class="testimonial-role">Event Planner</div>
                </div>
            </div>
            <div class="testimonial-stars">★★★★★</div>
            <p class="testimonial-text">"Perfect for managing group bookings. The platform is intuitive and their support team is fantastic!"</p>
        </div>

        <div class="testimonial-card">
            <div class="testimonial-header">
                <div class="testimonial-avatar avatar-dark">MJ</div>
                <div>
                    <h4>Michael Johnson</h4>
                    <div class="testimonial-role">Food Enthusiast</div>
                </div>
            </div>
            <div class="testimonial-stars">★★★★★</div>
            <p class="testimonial-text">"Finally, a reservation app that actually works! No more awkward phone calls. Love the reminder notifications."</p>
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
        <div class="section-overline">Pricing</div>
        <h2 class="section-title">Simple, Transparent Plans</h2>
        <p class="section-description">Choose the plan that works best for your dining lifestyle.</p>
    </div>

    <div class="pricing-grid">
        <div class="pricing-card">
            <h3>Basic</h3>
            <div class="pricing-price">Free</div>
            <div class="pricing-period">Forever</div>
            <ul class="pricing-features">
                <li>Unlimited reservations</li>
                <li>Real-time availability</li>
                <li>Email confirmations</li>
                <li>Basic support</li>
                <li>Mobile app access</li>
            </ul>
            <button
                class="pricing-btn"
                type="button"
                data-plan-modal
                data-plan-title="Basic"
                data-plan-price="Free forever"
                data-plan-copy="Best for guests who want fast reservations, booking changes, confirmations, and reminders."
                data-plan-primary-label="Book a Table"
                data-plan-primary-url="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>"
                data-plan-secondary-label="Create Account"
                data-plan-secondary-url="<?= htmlspecialchars(appPath('auth/register.php'), ENT_QUOTES, 'UTF-8') ?>"
            >Get Started</button>
        </div>

        <div class="pricing-card featured">
            <div class="pricing-badge">Most Popular</div>
            <h3>Premium</h3>
            <div class="pricing-price">$9.99</div>
            <div class="pricing-period">per month</div>
            <ul class="pricing-features">
                <li>Everything in Basic</li>
                <li>Priority booking access</li>
                <li>Exclusive member deals</li>
                <li>Points & rewards program</li>
                <li>Dedicated support</li>
                <li>Monthly newsletter</li>
            </ul>
            <button
                class="pricing-btn"
                type="button"
                data-plan-modal
                data-plan-title="Premium"
                data-plan-price="$9.99 per month"
                data-plan-copy="For regular diners who want priority access, rewards, member offers, and dedicated support."
                data-plan-primary-label="Create Account"
                data-plan-primary-url="<?= htmlspecialchars(appPath('auth/register.php'), ENT_QUOTES, 'UTF-8') ?>"
                data-plan-secondary-label="Book First"
                data-plan-secondary-url="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>"
            >Try Premium</button>
        </div>

        <div class="pricing-card">
            <h3>Corporate</h3>
            <div class="pricing-price">Custom</div>
            <div class="pricing-period">Contact us</div>
            <ul class="pricing-features">
                <li>Everything in Premium</li>
                <li>Bulk group bookings</li>
                <li>Corporate branding</li>
                <li>Advanced analytics</li>
                <li>Custom integration</li>
            </ul>
            <button
                class="pricing-btn"
                type="button"
                data-plan-modal
                data-plan-title="Corporate"
                data-plan-price="Custom quote"
                data-plan-copy="For teams, events, and group dining programs that need coordinated bookings and direct staff support."
                data-plan-primary-label="Contact Sales"
                data-plan-primary-url="<?= htmlspecialchars(appPath('public/contact.php'), ENT_QUOTES, 'UTF-8') ?>"
                data-plan-secondary-label="View Function Options"
                data-plan-secondary-url="<?= htmlspecialchars(appPath('public/about.php'), ENT_QUOTES, 'UTF-8') ?>"
            >Contact Sales</button>
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
        <h2>Ready to Transform Your Dining Experience?</h2>
        <p>Join thousands of diners who've already discovered the DineMate difference. Book your first table today and save time for what really matters.</p>
        
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
    <h2>Still Have Questions?</h2>
    <p>Our team is here to help. Reach out to us anytime—we're available 24/7 to assist you.</p>
    
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


