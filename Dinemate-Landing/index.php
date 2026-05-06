<?php 
$pageTitle = "Discover & Book Restaurants";
$pageDescription = "Find and reserve tables at top restaurants. DineMate makes dining discoveries effortless with real-time availability and instant confirmations.";
include __DIR__ . '/includes/header.php'; 
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-sparkles"></i> The World's #1 Reservation Platform
        </div>
        
        <h1>
            Discover. Book. <span class="hero-highlight">Dine.</span>
        </h1>
        
        <p>Find the perfect table in seconds. Access 50,000+ restaurants worldwide and make reservations instantly.</p>
        
        <div class="hero-buttons">
            <button class="btn btn-primary btn-large" onclick="document.getElementById('search-section').scrollIntoView({behavior: 'smooth'})">
                <i class="fas fa-search"></i> Find a Restaurant
            </button>
            <a href="#" class="btn btn-secondary btn-large">
                <i class="fas fa-play-circle"></i> Watch Demo
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">50K+</div>
                <div class="stat-label">Restaurants</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">5M+</div>
                <div class="stat-label">Diners</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">1M+</div>
                <div class="stat-label">Monthly Reservations</div>
            </div>
        </div>
    </div>
</section>

<!-- Search Section -->
<section class="section" id="search-section" style="background: linear-gradient(135deg, #f0f9ff 0%, #f9fafb 100%); padding: 80px 24px;">
    <div class="container">
        <h2 style="text-align: center; font-size: 32px; font-weight: 800; margin-bottom: 40px;">Search Restaurants Near You</h2>
        
        <div style="max-width: 700px; margin: 0 auto; background: white; border-radius: 12px; padding: 24px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);">
            <form style="display: grid; gap: 16px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <input type="text" placeholder="Cuisine, Restaurant, or Dish" style="padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                    <input type="text" placeholder="Location or City" style="padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 16px; align-items: end;">
                    <div>
                        <label style="display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; font-weight: 600;">Date</label>
                        <input type="date" style="padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; font-weight: 600;">Time</label>
                        <input type="time" style="padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; width: 100%;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; font-weight: 600;">Guests</label>
                        <select style="padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; width: 100%;">
                            <option>1 Guest</option>
                            <option>2 Guests</option>
                            <option>3 Guests</option>
                            <option>4 Guests</option>
                            <option>5 Guests</option>
                            <option>6 Guests</option>
                            <option>7+ Guests</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin: 0;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-badge"><i class="fas fa-star"></i> Why DineMate</div>
            <h2 class="section-title">Everything You Need for Perfect Dining</h2>
            <p class="section-description">Experience the platform built by diners, for diners.</p>
        </div>

        <div class="card-grid">
            <div class="card">
                <div class="card-icon"><i class="fas fa-bolt"></i></div>
                <h3>Instant Reservations</h3>
                <p>Book tables in seconds with real-time availability across thousands of restaurants.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-map-location-dot"></i></div>
                <h3>Location Discovery</h3>
                <p>Find the best restaurants near you with detailed information and customer reviews.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-bell"></i></div>
                <h3>Smart Notifications</h3>
                <p>Get timely reminders and updates about your reservations and special offers.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-mobile-alt"></i></div>
                <h3>Mobile First</h3>
                <p>Manage all your bookings on the go with our intuitive mobile app and website.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-star-half"></i></div>
                <h3>Ratings & Reviews</h3>
                <p>Read authentic reviews and see ratings from fellow diners to make informed choices.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-crown"></i></div>
                <h3>Exclusive Deals</h3>
                <p>Access member-only discounts, special menus, and dining packages at partner restaurants.</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="section" style="background: var(--bg-light);">
    <div class="container">
        <div class="section-header">
            <div class="section-badge"><i class="fas fa-check-circle"></i> Simple Process</div>
            <h2 class="section-title">How DineMate Works</h2>
        </div>

        <div class="card-grid">
            <div class="card" style="text-align: left; padding-top: 40px; padding-bottom: 40px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); color: white; border-radius: 50%; font-weight: 800; margin-bottom: 20px;">1</div>
                <h3>Search & Explore</h3>
                <p>Browse thousands of restaurants by cuisine, location, and availability. Read reviews from real diners and check out photos of dishes.</p>
            </div>

            <div class="card" style="text-align: left; padding-top: 40px; padding-bottom: 40px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); color: white; border-radius: 50%; font-weight: 800; margin-bottom: 20px;">2</div>
                <h3>Select & Reserve</h3>
                <p>Pick your date, time, and party size. Choose your preferred table location and confirm your reservation instantly.</p>
            </div>

            <div class="card" style="text-align: left; padding-top: 40px; padding-bottom: 40px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); color: white; border-radius: 50%; font-weight: 800; margin-bottom: 20px;">3</div>
                <h3>Get Confirmation</h3>
                <p>Receive instant confirmation via email and SMS. Manage your reservation anytime from your account.</p>
            </div>

            <div class="card" style="text-align: left; padding-top: 40px; padding-bottom: 40px;">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); color: white; border-radius: 50%; font-weight: 800; margin-bottom: 20px;">4</div>
                <h3>Enjoy Your Meal</h3>
                <p>Arrive at the restaurant at your reserved time and enjoy an unforgettable dining experience.</p>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header">
            <div class="section-badge"><i class="fas fa-heart"></i> Loved by Diners</div>
            <h2 class="section-title">What Our Community Says</h2>
        </div>

        <div class="testimonial-grid">
            <div class="testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar">RJ</div>
                    <div>
                        <h4>Raj Patel</h4>
                        <div class="testimonial-role">Food Enthusiast</div>
                    </div>
                </div>
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"DineMate has completely changed how I discover and book restaurants. It's so easy and the reservations are always confirmed. Best app ever!"</p>
            </div>

            <div class="testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar">SC</div>
                    <div>
                        <h4>Sarah Chen</h4>
                        <div class="testimonial-role">Event Organizer</div>
                    </div>
                </div>
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"Managing reservations for large groups used to be a nightmare. DineMate makes it seamless. Highly recommended for anyone who loves dining out."</p>
            </div>

            <div class="testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar">MM</div>
                    <div>
                        <h4>Marco Martinez</h4>
                        <div class="testimonial-role">Business Professional</div>
                    </div>
                </div>
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"I use DineMate for all my business dinners. The variety of restaurants and the reliability of the platform is unmatched."</p>
            </div>

            <div class="testimonial-card">
                <div class="testimonial-header">
                    <div class="testimonial-avatar">EK</div>
                    <div>
                        <h4>Emily Kim</h4>
                        <div class="testimonial-role">Couple</div>
                    </div>
                </div>
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"Finding a romantic restaurant for our anniversary was so simple. DineMate's recommendations were perfect!"</p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section style="padding: 0 24px;">
    <div class="cta-section">
        <div class="cta-content">
            <h2>Ready to Discover Your Next Favorite Restaurant?</h2>
            <p>Join millions of diners who trust DineMate. Start exploring exceptional dining experiences today.</p>
            
            <div class="cta-buttons">
                <a href="#" class="btn btn-primary btn-large">
                    <i class="fas fa-search"></i> Explore Restaurants
                </a>
                <button class="btn btn-secondary btn-large btn-cta-light" onclick="alert('Get the app!')">
                    <i class="fas fa-download"></i> Download App
                </button>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
