<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

startAppSession();

$contactFlash = null;
$contactForm = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'subject' => '',
    'message' => '',
];
$contactCsrfToken = csrfToken('contact');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken('contact')) {
        $contactFlash = ['type' => 'danger', 'message' => 'Security check failed. Please refresh and try again.'];
    } else {
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
}

$pageTitle = 'Contact Old Canberra Inn | DineMate';
$extraStylesheets = ['assets/css/pages/contact.css'];
include __DIR__ . '/../includes/header.php';
?>


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
                <p><a class="contact-inline-link" href="tel:+61421108735">+61 421 108 735</a></p>
            </div>
            
            <div class="contact-info-card">
                <div class="contact-info-icon"><i class="fa fa-envelope"></i></div>
                <h3>Email</h3>
                <p><a class="contact-inline-link" href="mailto:info@oldcanberrainn.com">info@oldcanberrainn.com</a></p>
            </div>
        </div>
    </div>
</section>

<!-- CONTACT FORM & HOURS SECTION -->
<section class="section section-muted">
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
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($contactCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
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
                <div class="hours-card">
                    <h3 class="hours-title">
                        <i class="fa fa-clock" aria-hidden="true"></i>Opening Hours
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
                    
                    <p class="hours-note">
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
<section class="page-cta">
    <div class="container">
        <h2 class="page-cta-title">Ready to Dine With Us?</h2>
        <p class="page-cta-copy">Book your table now and reserve your perfect dining experience</p>
        <a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" class="page-cta-button">
            <i class="fa fa-calendar-check"></i> Reserve Your Table
        </a>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
