<?php 
$pageTitle = "Pricing";
$pageDescription = "Choose the DineMate plan that's right for you. Transparent pricing with no hidden fees. Free trial available.";
include __DIR__ . '/../includes/header.php'; 
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-tag"></i> Transparent Pricing
        </div>
        
        <h1>
            Simple, Fair <span class="hero-highlight">Pricing Plans</span>
        </h1>
        
        <p>No hidden fees. No surprise charges. Choose the plan that works for you and upgrade anytime.</p>
    </div>
</section>

<!-- Toggle Section -->
<section class="section">
    <div class="container-sm">
        <div style="text-align: center; margin-bottom: 60px;">
            <div style="display: inline-flex; background: var(--bg-light); padding: 4px; border-radius: 8px;">
                <button style="padding: 8px 24px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;" onclick="alert('Annual pricing coming!')">Monthly Billing</button>
                <button style="padding: 8px 24px; background: transparent; color: var(--text-primary); border: none; cursor: pointer; font-weight: 600;">Annual Billing <span style="color: var(--primary-color); font-weight: 700;">Save 20%</span></button>
            </div>
        </div>

        <!-- Diner Plans -->
        <div style="margin-bottom: 80px;">
            <h2 style="text-align: center; font-size: 28px; font-weight: 800; margin-bottom: 50px;">Plans for Diners</h2>
            
            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3>Explorer</h3>
                    <div class="pricing-price">Free</div>
                    <div class="pricing-period">Forever</div>
                    <ul class="pricing-features">
                        <li>Unlimited restaurant searches</li>
                        <li>Real-time availability</li>
                        <li>Instant booking confirmation</li>
                        <li>Basic email notifications</li>
                        <li>Mobile app access</li>
                        <li>1 saved favorite list</li>
                    </ul>
                    <button class="pricing-btn">Get Started</button>
                </div>

                <div class="pricing-card featured">
                    <div class="pricing-badge">Most Popular</div>
                    <h3>Premium</h3>
                    <div class="pricing-price">$9.99</div>
                    <div class="pricing-period">per month</div>
                    <ul class="pricing-features">
                        <li>Everything in Explorer</li>
                        <li>Priority reservation access</li>
                        <li>Exclusive member deals</li>
                        <li>Earn points on bookings</li>
                        <li>Special dining packages</li>
                        <li>Unlimited favorite lists</li>
                    </ul>
                    <button class="pricing-btn">Try Premium</button>
                </div>

                <div class="pricing-card">
                    <h3>VIP</h3>
                    <div class="pricing-price">$19.99</div>
                    <div class="pricing-period">per month</div>
                    <ul class="pricing-features">
                        <li>Everything in Premium</li>
                        <li>VIP table placement</li>
                        <li>Premium points multiplier</li>
                        <li>Concierge service</li>
                        <li>Exclusive event invitations</li>
                        <li>Free gift card value</li>
                    </ul>
                    <button class="pricing-btn">Upgrade to VIP</button>
                </div>
            </div>
        </div>

        <!-- Restaurant Plans -->
        <div style="border-top: 2px solid var(--border-color); padding-top: 80px;">
            <h2 style="text-align: center; font-size: 28px; font-weight: 800; margin-bottom: 50px;">Plans for Restaurants</h2>
            
            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3>Starter</h3>
                    <div class="pricing-price">$99</div>
                    <div class="pricing-period">per month</div>
                    <ul class="pricing-features">
                        <li>Up to 50 monthly reservations</li>
                        <li>Basic analytics dashboard</li>
                        <li>Email & SMS confirmations</li>
                        <li>Single location support</li>
                        <li>Standard customer support</li>
                        <li>Basic menu management</li>
                    </ul>
                    <button class="pricing-btn">Get Started</button>
                </div>

                <div class="pricing-card featured">
                    <div class="pricing-badge">Most Popular</div>
                    <h3>Growth</h3>
                    <div class="pricing-price">$299</div>
                    <div class="pricing-period">per month</div>
                    <ul class="pricing-features">
                        <li>Unlimited reservations</li>
                        <li>Advanced analytics & insights</li>
                        <li>Marketing automation</li>
                        <li>Multi-location support</li>
                        <li>Priority support</li>
                        <li>Custom branding options</li>
                    </ul>
                    <button class="pricing-btn">Start Free Trial</button>
                </div>

                <div class="pricing-card">
                    <h3>Enterprise</h3>
                    <div class="pricing-price">Custom</div>
                    <div class="pricing-period">Contact us</div>
                    <ul class="pricing-features">
                        <li>Everything in Growth</li>
                        <li>API access & integrations</li>
                        <li>Custom development</li>
                        <li>Dedicated account manager</li>
                        <li>Training & onboarding</li>
                    </ul>
                    <button class="pricing-btn">Contact Sales</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Feature Comparison -->
<section class="section" style="background: var(--bg-light);">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Detailed Feature Comparison</h2>
            <p class="section-description">See what's included in each plan</p>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-md);">
                <thead>
                    <tr style="background: var(--secondary-color); color: white;">
                        <th style="padding: 20px; text-align: left; font-weight: 700;">Feature</th>
                        <th style="padding: 20px; text-align: center; font-weight: 700;">Explorer</th>
                        <th style="padding: 20px; text-align: center; font-weight: 700; background: rgba(46, 125, 50, 0.1); color: var(--text-primary);">Premium</th>
                        <th style="padding: 20px; text-align: center; font-weight: 700;">VIP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 16px 20px;">Unlimited Searches</td>
                        <td style="padding: 16px 20px; text-align: center;">✓</td>
                        <td style="padding: 16px 20px; text-align: center; background: rgba(46, 125, 50, 0.05);">✓</td>
                        <td style="padding: 16px 20px; text-align: center;">✓</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 16px 20px;">Real-time Availability</td>
                        <td style="padding: 16px 20px; text-align: center;">✓</td>
                        <td style="padding: 16px 20px; text-align: center; background: rgba(46, 125, 50, 0.05);">✓</td>
                        <td style="padding: 16px 20px; text-align: center;">✓</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 16px 20px;">Priority Booking Access</td>
                        <td style="padding: 16px 20px; text-align: center;">✗</td>
                        <td style="padding: 16px 20px; text-align: center; background: rgba(46, 125, 50, 0.05);">✓</td>
                        <td style="padding: 16px 20px; text-align: center;">✓</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 16px 20px;">Exclusive Member Deals</td>
                        <td style="padding: 16px 20px; text-align: center;">✗</td>
                        <td style="padding: 16px 20px; text-align: center; background: rgba(46, 125, 50, 0.05);">✓</td>
                        <td style="padding: 16px 20px; text-align: center;">✓</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 16px 20px;">Earn Rewards Points</td>
                        <td style="padding: 16px 20px; text-align: center;">✗</td>
                        <td style="padding: 16px 20px; text-align: center; background: rgba(46, 125, 50, 0.05);">✓</td>
                        <td style="padding: 16px 20px; text-align: center;">✓ (2x Multiplier)</td>
                    </tr>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 16px 20px;">Concierge Service</td>
                        <td style="padding: 16px 20px; text-align: center;">✗</td>
                        <td style="padding: 16px 20px; text-align: center; background: rgba(46, 125, 50, 0.05);">✗</td>
                        <td style="padding: 16px 20px; text-align: center;">✓</td>
                    </tr>
                    <tr>
                        <td style="padding: 16px 20px;">VIP Event Access</td>
                        <td style="padding: 16px 20px; text-align: center;">✗</td>
                        <td style="padding: 16px 20px; text-align: center; background: rgba(46, 125, 50, 0.05);">✗</td>
                        <td style="padding: 16px 20px; text-align: center;">✓</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="section">
    <div class="container-sm">
        <div class="section-header">
            <h2 class="section-title">Frequently Asked Questions</h2>
        </div>

        <div style="display: grid; gap: 24px;">
            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer;" open>
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> Can I upgrade or downgrade anytime?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Yes! You can upgrade or downgrade your plan at any time. Changes take effect immediately, and we'll pro-rate your billing accordingly.</p>
            </details>

            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer;">
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> Is there a free trial?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Yes! Premium and Growth plans come with a 14-day free trial. No credit card required to start.</p>
            </details>

            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer;">
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> Do you offer discounts for annual billing?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Absolutely! Save 20% when you choose annual billing instead of monthly. Perfect for long-term commitment.</p>
            </details>

            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer;">
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> What payment methods do you accept?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">We accept all major credit cards, PayPal, and Apple Pay. Invoicing available for Enterprise customers.</p>
            </details>

            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer;">
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> Can I cancel anytime?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Yes! You can cancel your subscription anytime with no cancellation fees. Your data remains accessible for 30 days.</p>
            </details>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 0 24px;">
    <div class="cta-section">
        <div class="cta-content">
            <h2>Choose Your Plan Today</h2>
            <p>Start for free or upgrade to Premium. Experience the difference DineMate can make.</p>
            
            <div class="cta-buttons">
                <a href="#" class="btn btn-primary btn-large">
                    <i class="fas fa-search"></i> Get Started Free
                </a>
                <button class="btn btn-secondary btn-large btn-cta-light" onclick="alert('Support coming!')">
                    <i class="fas fa-headset"></i> Talk to Sales
                </button>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
