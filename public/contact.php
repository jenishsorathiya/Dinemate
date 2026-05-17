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

                $contactFlash = ['type' => 'success', 'message' => 'Thanks, your message has been sent to the team.'];
                $contactForm = array_fill_keys(array_keys($contactForm), '');
            } catch (Throwable $contactError) {
                error_log('Contact form error: ' . $contactError->getMessage());
                $contactFlash = ['type' => 'danger', 'message' => 'We could not send your message right now. Please try again shortly.'];
            }
        }
    }
}

$pageTitle = 'Contact | DineMate';
include __DIR__ . '/../includes/header.php';
?>

<main class="guest-main">
    <section class="guest-page-hero" style="--guest-hero-image: url('<?= htmlspecialchars(appPath('assets/images/editorial/contact-hero.jpg'), ENT_QUOTES, 'UTF-8') ?>'); --guest-hero-position: center;">
        <div class="guest-hero-inner">
            <p class="guest-kicker">Talk to the Team</p>
            <h1 class="guest-page-title">Questions, group plans, special requests.</h1>
            <p class="guest-page-copy">Send a message to the restaurant team or use DineMate to start a table request now.</p>
            <div class="guest-action-row">
                <a class="guest-button" href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>">Book a Table</a>
                <a class="guest-button-outline" href="tel:+61421108735">Call Venue</a>
            </div>
        </div>
    </section>

    <section class="guest-section is-paper">
        <div class="guest-container">
            <div class="guest-grid-3">
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-location-dot"></i></div>
                    <h3>Visit</h3>
                    <p>195 Mouat Street<br>Lyneham ACT 2602<br>Australia</p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-phone"></i></div>
                    <h3>Call</h3>
                    <p><a class="contact-inline-link" href="tel:+61421108735">+61 421 108 735</a></p>
                </article>
                <article class="guest-card">
                    <div class="guest-card-icon"><i class="fa fa-envelope"></i></div>
                    <h3>Email</h3>
                    <p><a class="contact-inline-link" href="mailto:info@oldcanberrainn.com">info@oldcanberrainn.com</a></p>
                </article>
            </div>
        </div>
    </section>

    <section class="guest-section contact-message-section">
        <div class="guest-container guest-split">
            <div class="contact-form-card">
                <p class="guest-section-kicker">Message</p>
                <h2>Send Us a Message</h2>
                <p class="guest-section-copy">Use this for group dining, booking help, accessibility details, or anything the online form does not cover.</p>

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

            <aside class="hours-card">
                <p class="guest-section-kicker">Hours</p>
                <h3 class="hours-title"><i class="fa fa-clock" aria-hidden="true"></i>Opening Hours</h3>
                <div class="hours-grid">
                    <div class="hour-item"><strong>Mon - Thu</strong><span>11:00 - 23:00</span></div>
                    <div class="hour-item"><strong>Fri - Sat</strong><span>11:00 - 00:00</span></div>
                    <div class="hour-item"><strong>Sunday</strong><span>11:00 - 22:00</span></div>
                    <div class="hour-item"><strong>Online</strong><span>Booking requests anytime</span></div>
                </div>
                <p class="hours-note">For large groups, send a message with your preferred date, time, party size, and occasion.</p>
            </aside>
        </div>
    </section>

    <section class="guest-section is-paper">
        <div class="guest-container">
            <div class="section-header">
                <p class="guest-section-kicker">Find Us</p>
                <h2 class="section-title">Old Canberra Inn, Lyneham</h2>
            </div>
            <div class="guest-location-panel">
                <div>
                    <h3>195 Mouat Street, Lyneham ACT 2602</h3>
                    <p>Open the venue location in your preferred map app, then use DineMate when you are ready to request a table.</p>
                </div>
                <div>
                    <div class="guest-action-row">
                        <a class="guest-button" href="https://www.google.com/maps/search/?api=1&query=Old+Canberra+Inn+Lyneham" target="_blank" rel="noopener">Open Maps</a>
                        <a class="guest-button-outline" href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>">Book a Table</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . "/../includes/footer.php"; ?>
