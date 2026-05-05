<?php include __DIR__ . "/../includes/header.php"; ?>

<style>
/* Page Header Hero */
.page-hero {
    background: linear-gradient(135deg, #0a8b5a 0%, #013222 100%);
    color: white;
    padding: 120px 20px 80px;
    text-align: center;
    margin-top: 60px;
}

.page-hero h1 {
    font-size: clamp(32px, 6vw, 56px);
    font-weight: 700;
    margin-bottom: 15px;
}

.page-hero p {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.9);
}

/* Section Styles */
.section {
    padding: 80px 0;
}

.section-header {
    text-align: center;
    margin-bottom: 60px;
}

.section-title {
    font-size: clamp(28px, 6vw, 48px);
    font-weight: 700;
    color: var(--dm-text);
    margin-bottom: 20px;
}

.section-subtitle {
    font-size: 18px;
    color: var(--dm-text-muted);
    max-width: 600px;
    margin: 0 auto;
}

/* Story Card */
.story-card {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 12px;
    padding: 50px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.story-card h3 {
    font-size: 24px;
    font-weight: 700;
    color: #13e796;
    margin-bottom: 20px;
}

.story-card p {
    font-size: 16px;
    color: var(--dm-text-muted);
    line-height: 1.8;
    margin-bottom: 15px;
}

.story-card strong {
    color: var(--dm-text);
}

/* Value Cards Grid */
.value-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-top: 50px;
}

.value-card {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 12px;
    padding: 40px 30px;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.value-card:hover {
    border-color: #13e796;
    box-shadow: 0 12px 32px rgba(19, 231, 150, 0.15);
    transform: translateY(-8px);
}

.value-card-icon {
    font-size: 48px;
    color: #13e796;
    margin-bottom: 20px;
}

.value-card h3 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--dm-text);
}

.value-card p {
    color: var(--dm-text-muted);
    font-size: 16px;
    line-height: 1.6;
}

/* Timeline/History Section */
.timeline-item {
    display: flex;
    gap: 30px;
    margin-bottom: 50px;
}

.timeline-date {
    min-width: 120px;
    font-size: 24px;
    font-weight: 700;
    color: #13e796;
}

.timeline-content h4 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--dm-text);
}

.timeline-content p {
    color: var(--dm-text-muted);
    line-height: 1.6;
}

/* Image styling */
.img-fluid-custom {
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
    width: 100%;
}

/* Stats Section */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
    margin-top: 50px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 36px;
    font-weight: 700;
    color: #13e796;
    margin-bottom: 10px;
}

.stat-label {
    font-size: 16px;
    color: var(--dm-text-muted);
}

@media (max-width: 767px) {
    .page-hero {
        padding: 80px 20px 60px;
    }

    .story-card {
        padding: 30px;
    }

    .section {
        padding: 50px 0;
    }

    .timeline-item {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>About Old Canberra Inn</h1>
    <p>A Heritage of Excellence, Modern Convenience</p>
</section>

<!-- OUR STORY SECTION -->
<section class="section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="story-card">
                    <h3><i class="fa fa-history"></i> Our Story</h3>
                    <p>Established in <strong>1857</strong>, Old Canberra Inn stands as one of Canberra's most iconic heritage establishments. With over 160 years of history, we've been the heart of the community, serving locals and visitors with exceptional hospitality.</p>
                    <p>From its early days as a gathering place for settlers and travelers, Old Canberra Inn has evolved into a beloved destination known for its <strong>authentic charm, premium dining, and vibrant atmosphere</strong>.</p>
                    <p>Today, we blend our rich heritage with modern innovation. With DineMate, we've made reservations easier than ever—so you can spend less time booking and more time enjoying the experience.</p>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="<?= htmlspecialchars(appPath('assets/images/showcase/canberra-interior-2.webp'), ENT_QUOTES, 'UTF-8') ?>" class="img-fluid-custom">
            </div>
        </div>
    </div>
</section>

<!-- OUR VALUES SECTION -->
<section class="section" style="background: var(--dm-surface-muted);">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Why Choose Old Canberra Inn</h2>
            <p class="section-subtitle">Experience the perfect blend of heritage, quality, and modern convenience</p>
        </div>
        
        <div class="value-cards">
            <div class="value-card">
                <div class="value-card-icon"><i class="fa fa-landmark"></i></div>
                <h3>Authentic Heritage</h3>
                <p>Over 160 years of history and tradition. A true Canberra landmark with genuine character.</p>
            </div>
            
            <div class="value-card">
                <div class="value-card-icon"><i class="fa fa-star"></i></div>
                <h3>Premium Quality</h3>
                <p>Carefully sourced ingredients, expert chefs, and craft beverages you'll love.</p>
            </div>
            
            <div class="value-card">
                <div class="value-card-icon"><i class="fa fa-handshake"></i></div>
                <h3>Warm Hospitality</h3>
                <p>Friendly staff, welcoming atmosphere, and service that makes you feel at home.</p>
            </div>
            
            <div class="value-card">
                <div class="value-card-icon"><i class="fa fa-calendar-check"></i></div>
                <h3>Easy Booking</h3>
                <p>Reserve your perfect table instantly with DineMate's seamless reservation system.</p>
            </div>
            
            <div class="value-card">
                <div class="value-card-icon"><i class="fa fa-music"></i></div>
                <h3>Vibrant Events</h3>
                <p>Live entertainment, special events, and community gatherings year-round.</p>
            </div>
            
            <div class="value-card">
                <div class="value-card-icon"><i class="fa fa-utensils"></i></div>
                <h3>Diverse Menu</h3>
                <p>Classic comfort food, innovative dishes, and something for every palate.</p>
            </div>
        </div>
    </div>
</section>

<!-- BY THE NUMBERS SECTION -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Our Numbers</h2>
        </div>
        
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">160+</div>
                <div class="stat-label">Years of Heritage</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">1000+</div>
                <div class="stat-label">Happy Diners Monthly</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">50+</div>
                <div class="stat-label">Premium Tables</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Online Reservations</div>
            </div>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section style="background: linear-gradient(135deg, #0a8b5a 0%, #013222 100%); color: white; text-align: center; padding: 80px 20px; border-radius: 16px; margin: 80px 0;">
    <div class="container">
        <h2 style="font-size: 36px; font-weight: 700; margin-bottom: 20px; color: white;">Ready to Join Us?</h2>
        <p style="font-size: 18px; margin-bottom: 40px; color: rgba(255, 255, 255, 0.9);">Reserve your table now and experience the Old Canberra Inn difference</p>
        <a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" style="background: #13e796; color: #013222; border: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block;">
            <i class="fa fa-calendar-check"></i> Reserve Your Table
        </a>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>

</body>
</html>
