<?php 
$pageTitle = "For Diners";
$pageDescription = "Discover the ultimate dining experience with DineMate. Access 50,000+ restaurants, make instant reservations, and enjoy exclusive dining benefits.";
include __DIR__ . '/../includes/header.php'; 
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-utensils"></i> For Diners
        </div>
        
        <h1>
            Your Gateway to <span class="hero-highlight">Exceptional Dining</span>
        </h1>
        
        <p>Discover, book, and experience the world's best restaurants with seamless reservations and exclusive perks.</p>
        
        <div class="hero-buttons">
            <a href="#" class="btn btn-primary btn-large">
                <i class="fas fa-search"></i> Start Exploring
            </a>
            <button class="btn btn-secondary btn-large" onclick="alert('App coming soon!')">
                <i class="fas fa-apple"></i> Download App
            </button>
        </div>
    </div>
</section>

<!-- Key Features Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-badge"><i class="fas fa-sparkles"></i> Your Benefits</div>
            <h2 class="section-title">Everything for the Perfect Dining Experience</h2>
        </div>

        <div class="feature-row">
            <div class="feature-content">
                <h2>Instant Reservations at Your Fingertips</h2>
                <p>No more waiting on hold or checking availability online. Book your table in seconds with real-time updates and instant confirmations.</p>
                <ul class="feature-list">
                    <li>Real-time table availability</li>
                    <li>Instant booking confirmation</li>
                    <li>Flexible modification anytime</li>
                    <li>No hidden fees or surprises</li>
                </ul>
                <a href="#" class="btn btn-primary">Start Booking</a>
            </div>
            <div class="feature-image">
                <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); height: 400px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 80px;">📱</div>
            </div>
        </div>

        <div class="feature-row">
            <div class="feature-content">
                <h2>Discover Restaurants That Match Your Taste</h2>
                <p>Browse curated collections, read honest reviews from real diners, and find your next favorite restaurant with confidence.</p>
                <ul class="feature-list">
                    <li>50,000+ restaurants worldwide</li>
                    <li>Authentic diner reviews and ratings</li>
                    <li>Filter by cuisine, price, and ambiance</li>
                    <li>Photos of dishes and dining spaces</li>
                </ul>
                <a href="#" class="btn btn-primary">Explore Now</a>
            </div>
            <div class="feature-image">
                <div style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); height: 400px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 80px;">🍽️</div>
            </div>
        </div>

        <div class="feature-row">
            <div class="feature-content">
                <h2>Exclusive Member Benefits & Rewards</h2>
                <p>Earn points on every reservation, get access to special menus, and unlock exclusive dining experiences.</p>
                <ul class="feature-list">
                    <li>Points on every booking</li>
                    <li>Redeem for discounts or free meals</li>
                    <li>Exclusive member-only events</li>
                    <li>Special dining packages</li>
                </ul>
                <a href="#" class="btn btn-primary">Join Premium</a>
            </div>
            <div class="feature-image">
                <div style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); height: 400px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 80px;">👑</div>
            </div>
        </div>

        <div class="feature-row">
            <div class="feature-content">
                <h2>Never Miss a Reservation</h2>
                <p>Smart reminders keep you on track. Get notifications before your reservation, and easily manage changes from anywhere.</p>
                <ul class="feature-list">
                    <li>Pre-reservation reminders</li>
                    <li>Multi-channel notifications</li>
                    <li>Easy cancellation or rescheduling</li>
                    <li>Reservation history and favorites</li>
                </ul>
                <a href="#" class="btn btn-primary">Enable Notifications</a>
            </div>
            <div class="feature-image">
                <div style="background: linear-gradient(135deg, #e0f2f1 0%, #b2dfdb 100%); height: 400px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 80px;">🔔</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Grid -->
<section class="section" style="background: var(--bg-light);">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">More Ways to Enjoy DineMate</h2>
        </div>

        <div class="card-grid">
            <div class="card">
                <div class="card-icon"><i class="fas fa-heart"></i></div>
                <h3>Save Your Favorites</h3>
                <p>Build a personalized list of restaurants you want to visit. Never lose a restaurant recommendation again.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-users"></i></div>
                <h3>Perfect for Groups</h3>
                <p>Book tables for any party size. DineMate handles everything, from reservations to dietary preferences.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-globe"></i></div>
                <h3>Dine Worldwide</h3>
                <p>Travel with confidence. Reserve at restaurants in major cities around the world with one account.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-clock"></i></div>
                <h3>24/7 Access</h3>
                <p>Book anytime, anywhere. Our 24/7 customer support is always ready to help with any questions.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-gift"></i></div>
                <h3>Gift Cards</h3>
                <p>Share the gift of great dining. DineMate gift cards are perfect for any occasion.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-shield"></i></div>
                <h3>100% Secure</h3>
                <p>Your data and payments are protected with industry-leading security standards.</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <div class="section-badge"><i class="fas fa-star"></i> Success Stories</div>
            <h2 class="section-title">Diners Love DineMate</h2>
        </div>

        <div class="testimonial-grid">
            <div class="testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar">AB</div>
                    <div>
                        <h4>Aisha Brown</h4>
                        <div class="testimonial-role">Frequent Diner</div>
                    </div>
                </div>
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"I use DineMate weekly. It's saved me so much time and I've discovered restaurants I would never have found otherwise."</p>
            </div>

            <div class="testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar">JL</div>
                    <div>
                        <h4>James Lee</h4>
                        <div class="testimonial-role">Business Owner</div>
                    </div>
                </div>
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"The rewards program is fantastic. I've earned enough points for several free meals. Highly recommended!"</p>
            </div>

            <div class="testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar">NP</div>
                    <div>
                        <h4>Nicole Press</h4>
                        <div class="testimonial-role">Food Blogger</div>
                    </div>
                </div>
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"The reviews and photos help me make the perfect choice every time. It's like having trusted friends guide my dining choices."</p>
            </div>

            <div class="testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar">DK</div>
                    <div>
                        <h4>David Kim</h4>
                        <div class="testimonial-role">Traveler</div>
                    </div>
                </div>
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"When I travel, DineMate is the first app I use to find restaurants. It never disappoints!"</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 0 24px;">
    <div class="cta-section">
        <div class="cta-content">
            <h2>Ready to Transform Your Dining?</h2>
            <p>Join millions of diners discovering exceptional restaurants and making memorable reservations.</p>
            
            <div class="cta-buttons">
                <a href="#" class="btn btn-primary btn-large">
                    <i class="fas fa-search"></i> Find Your Next Favorite
                </a>
                <button class="btn btn-secondary btn-large btn-cta-light" onclick="alert('Coming soon!')">
                    <i class="fas fa-envelope"></i> Get Restaurant Recommendations
                </button>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
