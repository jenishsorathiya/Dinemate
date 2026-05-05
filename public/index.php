<?php include __DIR__ . "/../includes/header.php"; ?>

<style>
/* ============ GLOBAL STYLES ============ */
.container {
    max-width: 1200px;
}

/* ============ HERO SECTION ============ */
.hero {
    position: relative;
    min-height: 100vh;
    background: linear-gradient(135deg, #0a8b5a 0%, #013222 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-top: 60px;
    padding: 60px 20px;
}

.hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 60%;
    height: 150%;
    background: radial-gradient(circle, rgba(19,231,150,0.15) 0%, rgba(19,231,150,0.05) 70%);
    border-radius: 50%;
}

.hero::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 50%;
    height: 120%;
    background: radial-gradient(circle, rgba(19,231,150,0.1) 0%, rgba(19,231,150,0.02) 70%);
    border-radius: 50%;
}

.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    color: white;
    max-width: 900px;
    animation: fadeInUp 1s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero-tag {
    display: inline-block;
    background: rgba(19, 231, 150, 0.1);
    color: #6b7280;
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 20px;
    border: 1px solid rgba(111, 114, 119, 0.3);
}

.hero h1 {
    font-size: clamp(36px, 8vw, 72px);
    font-weight: 800;
    margin-bottom: 20px;
    line-height: 1.15;
    letter-spacing: -0.02em;
}

.hero-highlight {
    background: linear-gradient(90deg, #111827, #374151);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero p {
    font-size: clamp(16px, 3vw, 20px);
    margin-bottom: 40px;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.7;
}

.hero-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 40px;
}

.btn-primary-hero {
    background: linear-gradient(135deg, #0a8b5a, #087a4d);
    color: #ffffff;
    border: none;
    padding: 18px 48px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: button;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 12px 40px rgba(19, 231, 150, 0.3);
}

.btn-primary-hero:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 50px rgba(19, 231, 150, 0.4);
}

.btn-secondary-hero {
    background: transparent;
    color: #0a8b5a;
    border: 2px solid #0a8b5a;
    padding: 16px 46px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-secondary-hero:hover {
    background: rgba(19, 231, 150, 0.1);
    transform: translateY(-4px);
}

.hero-stats {
    display: flex;
    gap: 40px;
    justify-content: center;
    flex-wrap: wrap;
    padding-top: 20px;
}

.stat {
    text-align: center;
}

.stat-number {
    font-size: 28px;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* ============ TRUST SECTION ============ */
.trust-section {
    background: var(--dm-surface);
    padding: 50px 20px;
    border-bottom: 1px solid var(--dm-border);
}

.trust-content {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 40px;
    text-align: center;
}

.trust-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.trust-icon {
    font-size: 32px;
    color: #6b7280;
}

.trust-label {
    font-size: 14px;
    color: var(--dm-text-muted);
    font-weight: 500;
}

/* ============ FEATURES GRID ============ */
.features-section {
    padding: 100px 20px;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
}

.section-header {
    text-align: center;
    margin-bottom: 60px;
}

.section-overline {
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 15px;
}

.section-title {
    font-size: clamp(28px, 6vw, 48px);
    font-weight: 800;
    color: var(--dm-text);
    margin-bottom: 15px;
    line-height: 1.2;
}

.section-description {
    font-size: 18px;
    color: var(--dm-text-muted);
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.feature-card {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 16px;
    padding: 40px 30px;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

.feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #111827, #374151);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.feature-card:hover {
    border-color: #d1d5db;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.08);
    transform: translateY(-8px);
}

.feature-card:hover::before {
    opacity: 1;
}

.feature-icon {
    font-size: 48px;
    color: #374151;
    margin-bottom: 20px;
    display: inline-block;
}

.feature-card h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--dm-text);
    margin-bottom: 12px;
}

.feature-card p {
    color: var(--dm-text-muted);
    font-size: 15px;
    line-height: 1.6;
    margin: 0;
}

/* ============ SHOWCASE SECTION ============ */
.showcase-section {
    padding: 100px 20px;
    background: var(--dm-surface);
}

.showcase-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
}

.showcase-content h2 {
    font-size: clamp(28px, 5vw, 44px);
    font-weight: 800;
    color: var(--dm-text);
    margin-bottom: 20px;
    line-height: 1.2;
}

.showcase-content p {
    font-size: 16px;
    color: var(--dm-text-muted);
    line-height: 1.7;
    margin-bottom: 20px;
}

.showcase-list {
    list-style: none;
    margin: 30px 0;
}

.showcase-list li {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    font-size: 15px;
    color: var(--dm-text-muted);
}

.showcase-list li::before {
    content: '✓';
    color: #111827;
    font-weight: 800;
    font-size: 18px;
    flex-shrink: 0;
}

.showcase-image {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.showcase-image img {
    width: 100%;
    height: auto;
    display: block;
}

.showcase-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(19, 231, 150, 0.95);
    color: #013222;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 14px;
    backdrop-filter: blur(10px);
}

/* ============ CTA SECTION ============ */
.cta-section {
    padding: 80px 20px;
    background: linear-gradient(135deg, #0a8b5a 0%, #013222 100%);
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 50%;
    height: 150%;
    background: radial-gradient(circle, rgba(19,231,150,0.1) 0%, rgba(19,231,150,0.02) 70%);
    border-radius: 50%;
}

.cta-content {
    position: relative;
    z-index: 2;
    max-width: 800px;
    margin: 0 auto;
}

.cta-section h2 {
    font-size: clamp(28px, 6vw, 48px);
    font-weight: 800;
    margin-bottom: 20px;
    line-height: 1.2;
}

.cta-section p {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.85);
    margin-bottom: 40px;
    line-height: 1.6;
}

.cta-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-cta-primary {
    background: #0a8b5a;
    color: #ffffff;
    border: none;
    padding: 16px 40px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.btn-cta-primary:hover {
    background: #087a4d;
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(10, 139, 90, 0.3);
}

/* ============ TESTIMONIALS ============ */
.testimonials-section {
    padding: 100px 20px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.testimonials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.testimonial-card {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
}

.testimonial-card:hover {
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
    border-color: #d1d5db;
}

.testimonial-header {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    align-items: flex-start;
}

.testimonial-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #374151, #1f2937);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
}

.testimonial-info h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--dm-text);
    margin-bottom: 4px;
}

.testimonial-role {
    font-size: 12px;
    color: var(--dm-text-muted);
}

.testimonial-stars {
    font-size: 14px;
    color: #f59e0b;
    margin-bottom: 15px;
}

.testimonial-text {
    color: var(--dm-text-muted);
    line-height: 1.6;
    font-style: italic;
    margin: 0;
}

/* ============ PRICING CARDS ============ */
.pricing-section {
    padding: 100px 20px;
    background: var(--dm-surface);
}

.pricing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.pricing-card {
    border: 2px solid var(--dm-border);
    border-radius: 16px;
    padding: 40px 30px;
    text-align: center;
    transition: all 0.3s ease;
    position: relative;
}

.pricing-card.featured {
    border-color: #0a8b5a;
    background: linear-gradient(180deg, rgba(10, 139, 90, 0.05), transparent);
    transform: scale(1.05);
}

.pricing-badge {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: #374151;
    color: #ffffff;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

.pricing-card h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--dm-text);
    margin-bottom: 15px;
}

.pricing-price {
    font-size: 36px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 8px;
}

.pricing-period {
    color: var(--dm-text-muted);
    font-size: 14px;
    margin-bottom: 25px;
}

.pricing-features {
    list-style: none;
    text-align: left;
    margin-bottom: 30px;
}

.pricing-features li {
    padding: 10px 0;
    border-bottom: 1px solid var(--dm-border);
    color: var(--dm-text-muted);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pricing-features li::before {
    content: '✓';
    color: #374151;
    font-weight: 800;
    font-size: 16px;
}

.pricing-btn {
    width: 100%;
    padding: 12px 20px;
    border: 2px solid var(--dm-border);
    background: transparent;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
}

.pricing-card.featured .pricing-btn {
    background: #0a8b5a;
    color: #ffffff;
    border-color: #0a8b5a;
}

.pricing-btn:hover {
    border-color: #0a8b5a;
    color: #0a8b5a;
}

.pricing-card.featured .pricing-btn:hover {
    background: #087a4d;
}

/* ============ FOOTER CTA ============ */
.footer-cta {
    padding: 100px 20px;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    text-align: center;
}

.footer-cta h2 {
    font-size: clamp(28px, 6vw, 44px);
    font-weight: 800;
    color: var(--dm-text);
    margin-bottom: 15px;
}

.footer-cta p {
    font-size: 18px;
    color: var(--dm-text-muted);
    margin-bottom: 40px;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.footer-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-footer {
    padding: 16px 40px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.btn-footer-primary {
    background: linear-gradient(135deg, #0a8b5a, #087a4d);
    color: #ffffff;
    border: none;
}

.btn-footer-primary:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(10, 139, 90, 0.3);
}

.btn-footer-secondary {
    background: transparent;
    color: var(--dm-text);
    border: 2px solid var(--dm-border);
}

.btn-footer-secondary:hover {
    border-color: #0a8b5a;
    color: #0a8b5a;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 768px) {
    .hero {
        margin-top: 60px;
        padding: 40px 20px;
    }

    .hero-stats {
        gap: 20px;
    }

    .stat-number {
        font-size: 24px;
    }

    .showcase-wrapper {
        grid-template-columns: 1fr;
        gap: 40px;
    }

    .features-section,
    .showcase-section,
    .testimonials-section,
    .pricing-section,
    .footer-cta {
        padding: 60px 20px;
    }

    .hero-buttons,
    .cta-buttons,
    .footer-buttons {
        flex-direction: column;
    }

    .btn-primary-hero,
    .btn-secondary-hero,
    .btn-cta-primary,
    .btn-footer {
        width: 100%;
        justify-content: center;
    }

    .pricing-card.featured {
        transform: scale(1);
    }

    .trust-content {
        gap: 20px;
    }
}

</style>

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
                <div class="testimonial-avatar" style="background: linear-gradient(135deg, #4b5563, #2d3748);">SA</div>
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
                <div class="testimonial-avatar" style="background: linear-gradient(135deg, #4b5563, #2d3748);">MJ</div>
                <div>
                    <h4>Michael Johnson</h4>
                    <div class="testimonial-role">Food Enthusiast</div>
                </div>
            </div>
            <div class="testimonial-stars">★★★★★</div>
            <p class="testimonial-text">"Finally, a reservation app that actually works! No more awkward phone calls. Love the reminder notifications."</p>
        </div>
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
            <button class="pricing-btn">Get Started</button>
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
            <button class="pricing-btn">Try Premium</button>
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
            <button class="pricing-btn">Contact Sales</button>
        </div>
    </div>
</section>

<!-- ============ CTA SECTION ============ -->
<section class="cta-section">
    <div class="cta-content">
        <h2>Ready to Transform Your Dining Experience?</h2>
        <p>Join thousands of diners who've already discovered the DineMate difference. Book your first table today and save time for what really matters.</p>
        
        <div class="cta-buttons">
            <a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-cta-primary">
                <i class="fa fa-calendar-check"></i> Make Your Reservation
            </a>
            <a href="<?= htmlspecialchars(appPath('public/about.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-cta-primary" style="background: transparent; border: 2px solid #0a8b5a; color: #0a8b5a;">
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

</body>
</html>
