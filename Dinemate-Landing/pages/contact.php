<?php 
$pageTitle = "Contact Us";
$pageDescription = "Get in touch with the DineMate team. We're here to help with questions, feedback, or business inquiries.";
include __DIR__ . '/../includes/header.php'; 
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-envelope"></i> Get In Touch
        </div>
        
        <h1>
            We're Here to <span class="hero-highlight">Help</span>
        </h1>
        
        <p>Have questions, feedback, or just want to say hello? We'd love to hear from you. Our team is standing by.</p>
    </div>
</section>

<!-- Contact Section -->
<section class="section">
    <div class="container">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: start;">
            <!-- Contact Form -->
            <div>
                <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 30px;">Send us a Message</h2>
                
                <form style="display: grid; gap: 20px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Name</label>
                        <input type="text" placeholder="Your full name" required style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; transition: all 0.3s ease;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--border-color)'">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Email</label>
                        <input type="email" placeholder="your@email.com" required style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; transition: all 0.3s ease;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--border-color)'">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Subject</label>
                        <input type="text" placeholder="How can we help?" required style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; transition: all 0.3s ease;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--border-color)'">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">Message</label>
                        <textarea placeholder="Tell us more..." required rows="6" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; font-family: inherit; transition: all 0.3s ease; resize: vertical;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--border-color)'"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-large">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>

            <!-- Contact Info -->
            <div>
                <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 30px;">Other Ways to Reach Us</h2>
                
                <div style="display: grid; gap: 30px;">
                    <!-- Support -->
                    <div style="background: var(--bg-light); padding: 24px; border-radius: 12px; border-left: 4px solid var(--primary-color);">
                        <h4 style="font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-headset" style="color: var(--primary-color);"></i> Customer Support
                        </h4>
                        <p style="color: var(--text-light); font-size: 14px; margin-bottom: 12px;">Available 24/7 to help with any questions or issues.</p>
                        <p style="font-weight: 600; color: var(--text-primary);">
                            <a href="mailto:support@dinemate.com" style="color: var(--primary-color); text-decoration: none;">support@dinemate.com</a>
                        </p>
                        <p style="color: var(--text-light); font-size: 14px;">Response time: &lt; 2 hours</p>
                    </div>

                    <!-- Partnerships -->
                    <div style="background: var(--bg-light); padding: 24px; border-radius: 12px; border-left: 4px solid var(--primary-color);">
                        <h4 style="font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-handshake" style="color: var(--primary-color);"></i> Restaurant Partnerships
                        </h4>
                        <p style="color: var(--text-light); font-size: 14px; margin-bottom: 12px;">Interested in partnering with DineMate? Let's talk!</p>
                        <p style="font-weight: 600; color: var(--text-primary);">
                            <a href="mailto:partners@dinemate.com" style="color: var(--primary-color); text-decoration: none;">partners@dinemate.com</a>
                        </p>
                        <p style="color: var(--text-light); font-size: 14px;">Available: Mon-Fri, 9am-6pm EST</p>
                    </div>

                    <!-- Business -->
                    <div style="background: var(--bg-light); padding: 24px; border-radius: 12px; border-left: 4px solid var(--primary-color);">
                        <h4 style="font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-briefcase" style="color: var(--primary-color);"></i> Business Inquiries
                        </h4>
                        <p style="color: var(--text-light); font-size: 14px; margin-bottom: 12px;">Corporate inquiries, press, and media requests.</p>
                        <p style="font-weight: 600; color: var(--text-primary);">
                            <a href="mailto:business@dinemate.com" style="color: var(--primary-color); text-decoration: none;">business@dinemate.com</a>
                        </p>
                        <p style="color: var(--text-light); font-size: 14px;">Available: Mon-Fri, 9am-5pm EST</p>
                    </div>

                    <!-- Headquarters -->
                    <div style="background: var(--bg-light); padding: 24px; border-radius: 12px; border-left: 4px solid var(--primary-color);">
                        <h4 style="font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i> Headquarters
                        </h4>
                        <p style="color: var(--text-light); font-size: 14px; margin-bottom: 12px;">Our main office location:</p>
                        <p style="color: var(--text-primary); font-size: 14px;">
                            DineMate Global<br>
                            123 Innovation Street<br>
                            San Francisco, CA 94107<br>
                            USA
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="section" style="background: var(--bg-light);">
    <div class="container-sm">
        <div class="section-header">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-description">Quick answers to common questions</p>
        </div>

        <div style="display: grid; gap: 16px;">
            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer; background: white;" open>
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> What if I forgot my password?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Visit our login page and click "Forgot Password?" You'll receive an email with instructions to reset your password within seconds.</p>
            </details>

            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer; background: white;">
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> How do I cancel a reservation?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Log into your account, go to "My Bookings," find the reservation, and click "Cancel." Most cancellations are processed immediately.</p>
            </details>

            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer; background: white;">
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> Can I modify my reservation?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Yes! You can modify the date, time, or party size from your account up to 24 hours before your reservation.</p>
            </details>

            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer; background: white;">
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> Do you have a mobile app?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Yes! DineMate is available on iOS and Android. Download from the App Store or Google Play for all the same features.</p>
            </details>

            <details style="border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; cursor: pointer; background: white;">
                <summary style="font-weight: 700; color: var(--text-primary);">
                    <i class="fas fa-chevron-down" style="margin-right: 10px;"></i> Is my payment information secure?
                </summary>
                <p style="margin-top: 12px; color: var(--text-secondary); margin: 12px 0 0 30px;">Absolutely. We use industry-leading SSL encryption and PCI compliance to protect all payment data. Your information is never stored on our servers.</p>
            </details>
        </div>
    </div>
</section>

<!-- Response Time Info -->
<section class="section">
    <div class="container-sm">
        <div style="background: linear-gradient(135deg, rgba(46, 125, 50, 0.1), rgba(46, 125, 50, 0.05)); padding: 40px; border-radius: 12px; border: 1px solid rgba(46, 125, 50, 0.2); text-align: center;">
            <h3 style="font-weight: 700; margin-bottom: 16px; font-size: 20px;">Average Response Times</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 24px;">
                <div>
                    <p style="font-size: 24px; font-weight: 800; color: var(--primary-color); margin-bottom: 4px;">&lt; 2h</p>
                    <p style="font-size: 13px; color: var(--text-secondary);">Support Emails</p>
                </div>
                <div>
                    <p style="font-size: 24px; font-weight: 800; color: var(--primary-color); margin-bottom: 4px;">&lt; 30min</p>
                    <p style="font-size: 13px; color: var(--text-secondary);">Chat Support</p>
                </div>
                <div>
                    <p style="font-size: 24px; font-weight: 800; color: var(--primary-color); margin-bottom: 4px;">24/7</p>
                    <p style="font-size: 13px; color: var(--text-secondary);">Emergency Support</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section style="padding: 0 24px;">
    <div class="cta-section">
        <div class="cta-content">
            <h2>Thank You for Your Interest</h2>
            <p>We're excited to hear from you. Get in touch and let's discuss how we can help.</p>
            
            <div class="cta-buttons">
                <a href="#" class="btn btn-primary btn-large">
                    <i class="fas fa-search"></i> Explore DineMate
                </a>
                <button class="btn btn-secondary btn-large btn-cta-light" onclick="alert('Feedback form coming!')">
                    <i class="fas fa-star"></i> Share Feedback
                </button>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
