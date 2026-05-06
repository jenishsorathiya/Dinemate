<?php 
$pageTitle = "About DineMate";
$pageDescription = "Learn about DineMate's mission to transform how people discover and experience dining. Founded by food lovers, built for everyone.";
include __DIR__ . '/../includes/header.php'; 
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-heart"></i> Our Story
        </div>
        
        <h1>
            Transforming <span class="hero-highlight">How People Dine</span>
        </h1>
        
        <p>At DineMate, we believe everyone deserves access to exceptional dining experiences. We're on a mission to make restaurant discovery and reservations effortless.</p>
    </div>
</section>

<!-- Story Section -->
<section class="section">
    <div class="container">
        <div class="feature-row">
            <div class="feature-content">
                <h2>Our Journey Begins</h2>
                <p>DineMate was founded by a group of passionate food lovers who were frustrated with the dining reservation experience. Making a reservation felt like a chore—calling restaurants during business hours, dealing with phone trees, or navigating clunky booking websites.</p>
                <p style="margin-top: 16px;">We knew there had to be a better way. In 2020, we launched DineMate with a simple mission: make finding and booking amazing restaurants as easy as ordering food delivery. Today, we're proud to serve millions of diners and partner with thousands of restaurants worldwide.</p>
            </div>
            <div class="feature-image">
                <div style="background: linear-gradient(135deg, #e1bee7 0%, #ce93d8 100%); height: 400px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 80px;">🚀</div>
            </div>
        </div>
    </div>
</section>

<!-- Values Section -->
<section class="section" style="background: var(--bg-light);">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Our Core Values</h2>
            <p class="section-description">Guiding every decision we make</p>
        </div>

        <div class="card-grid">
            <div class="card">
                <div class="card-icon"><i class="fas fa-handshake"></i></div>
                <h3>Customer First</h3>
                <p>Everything we do is designed with our users in mind—whether they're diners seeking their next favorite restaurant or restaurant partners looking to grow.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-lightbulb"></i></div>
                <h3>Innovation</h3>
                <p>We constantly explore new technologies and ideas to improve the dining experience. We're not satisfied with "good enough"—we're always looking for better.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Trust & Transparency</h3>
                <p>We believe in transparent communication, fair pricing, and protecting our users' privacy. Trust is everything.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-globe"></i></div>
                <h3>Inclusivity</h3>
                <p>We're building a platform that welcomes everyone—diners of all backgrounds and restaurants of all sizes and cuisines.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-star"></i></div>
                <h3>Passion for Food</h3>
                <p>At our core, we're food lovers. We celebrate culinary diversity and support the chefs and restaurants that make dining special.</p>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fas fa-rocket"></i></div>
                <h3>Excellence</h3>
                <p>We strive for excellence in everything we do—from our product features to our customer service and workplace culture.</p>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Meet Our Leadership</h2>
            <p class="section-description">Passionate leaders dedicated to transforming dining</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 40px;">
            <div style="text-align: center;">
                <div style="width: 200px; height: 200px; background: linear-gradient(135deg, var(--primary-color), var(--primary-light)); border-radius: 12px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 80px;">👨‍💼</div>
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">Michael Chen</h3>
                <p style="color: var(--primary-color); font-weight: 600; margin-bottom: 12px;">CEO & Co-Founder</p>
                <p style="color: var(--text-light); font-size: 14px;">Former product leader at leading food platforms. Passionate about UX and restaurant technology.</p>
            </div>

            <div style="text-align: center;">
                <div style="width: 200px; height: 200px; background: linear-gradient(135deg, #ff9800, #f57c00); border-radius: 12px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 80px;">👩‍💼</div>
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">Sarah Williams</h3>
                <p style="color: var(--primary-color); font-weight: 600; margin-bottom: 12px;">CTO & Co-Founder</p>
                <p style="color: var(--text-light); font-size: 14px;">Tech visionary with 15+ years in SaaS. Expert in scalable platforms and data engineering.</p>
            </div>

            <div style="text-align: center;">
                <div style="width: 200px; height: 200px; background: linear-gradient(135deg, #2196f3, #1976d2); border-radius: 12px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 80px;">👨‍🍳</div>
                <h3 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">James Rivera</h3>
                <p style="color: var(--primary-color); font-weight: 600; margin-bottom: 12px;">VP Partnerships</p>
                <p style="color: var(--text-light); font-size: 14px;">Hospitality veteran with deep relationships in the restaurant industry worldwide.</p>
            </div>
        </div>
    </div>
</section>

<!-- By The Numbers -->
<section class="section" style="background: var(--bg-light);">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Our Impact</h2>
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">50K+</div>
                <div class="stat-label">Restaurants Worldwide</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">5M+</div>
                <div class="stat-label">Active Diners</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">1M+</div>
                <div class="stat-label">Monthly Reservations</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">50+</div>
                <div class="stat-label">Countries Served</div>
            </div>
        </div>
    </div>
</section>

<!-- Timeline Section -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Our Milestones</h2>
        </div>

        <div style="position: relative; padding: 40px 0;">
            <div style="display: grid; gap: 40px;">
                <div style="display: grid; grid-template-columns: 200px 1fr; gap: 40px; align-items: start;">
                    <div style="text-align: right;">
                        <h4 style="font-size: 18px; font-weight: 700; margin-bottom: 4px;">2020</h4>
                        <p style="color: var(--text-light); font-size: 13px;">FOUNDED</p>
                    </div>
                    <div style="border-left: 3px solid var(--primary-color); padding-left: 30px; position: relative;">
                        <div style="position: absolute; left: -9px; top: 0; width: 12px; height: 12px; background: var(--primary-color); border-radius: 50%;"></div>
                        <h4 style="font-weight: 700; margin-bottom: 8px;">DineMate Launches</h4>
                        <p style="color: var(--text-light); font-size: 14px;">We officially launched DineMate with a vision to transform restaurant reservations.</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 200px 1fr; gap: 40px; align-items: start;">
                    <div style="text-align: right;">
                        <h4 style="font-size: 18px; font-weight: 700; margin-bottom: 4px;">2021</h4>
                        <p style="color: var(--text-light); font-size: 13px;">EXPANSION</p>
                    </div>
                    <div style="border-left: 3px solid var(--primary-color); padding-left: 30px; position: relative;">
                        <div style="position: absolute; left: -9px; top: 0; width: 12px; height: 12px; background: var(--primary-color); border-radius: 50%;"></div>
                        <h4 style="font-weight: 700; margin-bottom: 8px;">1M Reservations Milestone</h4>
                        <p style="color: var(--text-light); font-size: 14px;">Reached 1 million reservations and expanded to 15 countries.</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 200px 1fr; gap: 40px; align-items: start;">
                    <div style="text-align: right;">
                        <h4 style="font-size: 18px; font-weight: 700; margin-bottom: 4px;">2022</h4>
                        <p style="color: var(--text-light); font-size: 13px;">GROWTH</p>
                    </div>
                    <div style="border-left: 3px solid var(--primary-color); padding-left: 30px; position: relative;">
                        <div style="position: absolute; left: -9px; top: 0; width: 12px; height: 12px; background: var(--primary-color); border-radius: 50%;"></div>
                        <h4 style="font-weight: 700; margin-bottom: 8px;">Mobile App Launch</h4>
                        <p style="color: var(--text-light); font-size: 14px;">Launched iOS and Android apps with advanced features for reservations on-the-go.</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 200px 1fr; gap: 40px; align-items: start;">
                    <div style="text-align: right;">
                        <h4 style="font-size: 18px; font-weight: 700; margin-bottom: 4px;">2023</h4>
                        <p style="color: var(--text-light); font-size: 13px;">SCALE</p>
                    </div>
                    <div style="border-left: 3px solid var(--primary-color); padding-left: 30px; position: relative;">
                        <div style="position: absolute; left: -9px; top: 0; width: 12px; height: 12px; background: var(--primary-color); border-radius: 50%;"></div>
                        <h4 style="font-weight: 700; margin-bottom: 8px;">50K Restaurants Onboarded</h4>
                        <p style="color: var(--text-light); font-size: 14px;">Partnered with 50,000 restaurants worldwide, reaching 5 million diners.</p>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 200px 1fr; gap: 40px; align-items: start;">
                    <div style="text-align: right;">
                        <h4 style="font-size: 18px; font-weight: 700; margin-bottom: 4px;">2024</h4>
                        <p style="color: var(--text-light); font-size: 13px;">INNOVATION</p>
                    </div>
                    <div style="border-left: 3px solid var(--primary-color); padding-left: 30px; position: relative;">
                        <div style="position: absolute; left: -9px; top: 0; width: 12px; height: 12px; background: var(--primary-color); border-radius: 50%;"></div>
                        <h4 style="font-weight: 700; margin-bottom: 8px;">AI-Powered Recommendations</h4>
                        <p style="color: var(--text-light); font-size: 14px;">Introduced intelligent dining recommendations and personalized restaurant discovery.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Careers Section -->
<section class="section" style="background: var(--bg-light);">
    <div class="container-sm">
        <div class="section-header">
            <h2 class="section-title">Join Our Team</h2>
            <p class="section-description">We're hiring passionate people who love food and technology</p>
        </div>

        <div style="background: white; border-radius: 12px; padding: 40px; text-align: center; border: 1px solid var(--border-color);">
            <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 16px;">We're Hiring!</h3>
            <p style="color: var(--text-light); margin-bottom: 30px; line-height: 1.7;">
                Join our growing team and help us transform how people experience dining. We're looking for talented engineers, designers, product managers, and more.
            </p>
            <a href="#" class="btn btn-primary btn-large">
                <i class="fas fa-briefcase"></i> View Open Positions
            </a>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 0 24px;">
    <div class="cta-section">
        <div class="cta-content">
            <h2>Ready to Experience the DineMate Difference?</h2>
            <p>Join millions of diners who trust us to make their dining experiences unforgettable.</p>
            
            <div class="cta-buttons">
                <a href="#" class="btn btn-primary btn-large">
                    <i class="fas fa-search"></i> Start Exploring
                </a>
                <button class="btn btn-secondary btn-large btn-cta-light" onclick="alert('Contact coming!')">
                    <i class="fas fa-envelope"></i> Get in Touch
                </button>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
