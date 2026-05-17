<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$contactFlash = null;
$contactForm = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'subject' => '',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($contactForm as $field => $value) {
        $contactForm[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($contactForm['name'] === '' || $contactForm['email'] === '' || $contactForm['subject'] === '' || $contactForm['message'] === '') {
        $contactFlash = ['type' => 'danger', 'message' => 'Please complete the required fields.'];
    } elseif (!filter_var($contactForm['email'], FILTER_VALIDATE_EMAIL)) {
        $contactFlash = ['type' => 'danger', 'message' => 'Please enter a valid email address.'];
    } else {
        try {
            ensureInboxMessagesTable($pdo);
            $insertMessage = $pdo->prepare("
                INSERT INTO inbox_messages
                    (type, folder, status, guest_name, guest_email, guest_phone, subject, preview, message, received_at)
                VALUES
                    ('guest_message', 'requests', 'open', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $preview = substr($contactForm['message'], 0, 220);
            $insertMessage->execute([
                $contactForm['name'],
                $contactForm['email'],
                $contactForm['phone'] !== '' ? $contactForm['phone'] : null,
                $contactForm['subject'],
                $preview,
                $contactForm['message'],
            ]);

            $contactFlash = ['type' => 'success', 'message' => 'Thanks, your message has been sent to our team.'];
            $contactForm = array_fill_keys(array_keys($contactForm), '');
        } catch (Throwable $contactError) {
            error_log('Contact form error: ' . $contactError->getMessage());
            $contactFlash = ['type' => 'danger', 'message' => 'We could not send your message right now. Please try again shortly.'];
        }
    }
}

include __DIR__ . "/../includes/header.php";
?>

<style>
/* Page Header Hero */
.page-hero {
    background: linear-gradient(135deg, #2C3E50 0%, #1f2d3a 100%);
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

/* Contact Info Cards */
.contact-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
    margin-bottom: 60px;
}

.contact-info-card {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 12px;
    padding: 40px 30px;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.contact-info-card:hover {
    border-color: #4A7C59;
    box-shadow: 0 12px 32px rgba(107, 190, 141, 0.15);
    transform: translateY(-8px);
}

.contact-info-icon {
    font-size: 36px;
    color: #4A7C59;
    margin-bottom: 15px;
}

.contact-info-card h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--dm-text);
}

.contact-info-card p {
    color: var(--dm-text-muted);
    font-size: 15px;
    margin: 0;
}

/* Contact Form */
.contact-form-card {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 12px;
    padding: 50px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.contact-form-card h2 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 30px;
    color: var(--dm-text);
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--dm-text);
    font-size: 14px;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--dm-border);
    border-radius: 8px;
    font-size: 14px;
    font-family: var(--dm-font-sans);
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #4A7C59;
    box-shadow: 0 0 0 3px rgba(107, 190, 141, 0.12);
}

.form-group textarea {
    resize: vertical;
    min-height: 120px;
}

.btn-submit {
    background: #2C3E50;
    color: white;
    border: none;
    padding: 14px 40px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    margin-top: 10px;
}

.btn-submit:hover {
    background: #1f2d3a;
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(44, 62, 80, 0.24);
}

/* Map Section */
.map-container {
    background: var(--dm-surface);
    border: 1px solid var(--dm-border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.map-container iframe {
    width: 100%;
    height: 450px;
    border: none;
}

/* Opening Hours */
.hours-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.hour-item {
    background: var(--dm-surface-muted);
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #4A7C59;
}

.hour-item strong {
    display: block;
    color: var(--dm-text);
    margin-bottom: 5px;
}

.hour-item span {
    color: var(--dm-text-muted);
    font-size: 14px;
}

@media (max-width: 767px) {
    .page-hero {
        padding: 80px 20px 60px;
    }

    .contact-form-card {
        padding: 30px;
    }

    .map-container iframe {
        height: 300px;
    }

    .section {
        padding: 50px 0;
    }
}
</style>

<!-- PAGE HERO -->
<section class="page-hero">
    <h1>Get in Touch</h1>
    <p>We'd love to hear from you. Send us a message or visit us at our location</p>
</section>

<!-- CONTACT INFO SECTION -->
<section class="section">
    <div class="container">
        <div class="contact-info-grid">
            <div class="contact-info-card">
                <div class="contact-info-icon"><i class="fa fa-map-marker-alt"></i></div>
                <h3>Location</h3>
                <p>195 Mouat Street<br>Lyneham ACT 2602<br>Australia</p>
            </div>
            
            <div class="contact-info-card">
                <div class="contact-info-icon"><i class="fa fa-phone"></i></div>
                <h3>Phone</h3>
                <p><a href="tel:+61421108735" style="color: var(--dm-link); text-decoration: none;">+61 421 108 735</a></p>
            </div>
            
            <div class="contact-info-card">
                <div class="contact-info-icon"><i class="fa fa-envelope"></i></div>
                <h3>Email</h3>
                <p><a href="mailto:info@oldcanberrainn.com" style="color: var(--dm-link); text-decoration: none;">info@oldcanberrainn.com</a></p>
            </div>
        </div>
    </div>
</section>

<!-- CONTACT FORM & HOURS SECTION -->
<section class="section" style="background: var(--dm-surface-muted);">
    <div class="container">
        <div class="row g-5">
            <!-- Contact Form -->
            <div class="col-lg-7">
                <div class="contact-form-card">
                    <h2>Send us a Message</h2>
                    <?php if ($contactFlash): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($contactFlash['type'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($contactFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars(appPath('public/contact.php'), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Full Name *</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($contactForm['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($contactForm['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($contactForm['phone'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subject">Subject *</label>
                                    <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($contactForm['subject'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" required><?php echo htmlspecialchars($contactForm['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fa fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Opening Hours -->
            <div class="col-lg-5">
                <div style="background: var(--dm-surface); border: 1px solid var(--dm-border); border-radius: 12px; padding: 40px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);">
                    <h3 style="font-size: 22px; font-weight: 700; margin-bottom: 30px; color: var(--dm-text);">
                        <i class="fa fa-clock" style="color: #4A7C59; margin-right: 10px;"></i>Opening Hours
                    </h3>
                    
                    <div class="hours-grid">
                        <div class="hour-item">
                            <strong>Monday</strong>
                            <span>11:00 - 23:00</span>
                        </div>
                        <div class="hour-item">
                            <strong>Tuesday</strong>
                            <span>11:00 - 23:00</span>
                        </div>
                        <div class="hour-item">
                            <strong>Wednesday</strong>
                            <span>11:00 - 23:00</span>
                        </div>
                        <div class="hour-item">
                            <strong>Thursday</strong>
                            <span>11:00 - 23:00</span>
                        </div>
                        <div class="hour-item">
                            <strong>Friday</strong>
                            <span>11:00 - 00:00</span>
                        </div>
                        <div class="hour-item">
                            <strong>Saturday</strong>
                            <span>11:00 - 00:00</span>
                        </div>
                    </div>
                    
                    <p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--dm-border); color: var(--dm-text-muted); font-size: 14px;">
                        <strong>Sunday</strong><br>
                        11:00 - 22:00
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MAP SECTION -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Find Us on the Map</h2>
        </div>
        
        <div class="map-container">
            <iframe src="https://www.google.com/maps?q=Old+Canberra+Inn,+Lyneham&output=embed" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section style="background: linear-gradient(135deg, #2C3E50 0%, #1f2d3a 100%); color: white; text-align: center; padding: 80px 20px; border-radius: 16px; margin: 80px 0;">
    <div class="container">
        <h2 style="font-size: 36px; font-weight: 700; margin-bottom: 20px; color: white;">Ready to Dine With Us?</h2>
        <p style="font-size: 18px; margin-bottom: 40px; color: rgba(255, 255, 255, 0.9);">Book your table now and reserve your perfect dining experience</p>
        <a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" style="background: #2C3E50; color: white; border: none; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block;">
            <i class="fa fa-calendar-check"></i> Reserve Your Table
        </a>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
