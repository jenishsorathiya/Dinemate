<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$footerRole = getCurrentUserRole();
$footerLinks = [
    ['label' => 'Home', 'path' => 'index.php'],
    ['label' => 'Book a Table', 'path' => 'bookings/book-table.php'],
    ['label' => 'Login', 'path' => 'auth/login.php'],
    ['label' => 'Register', 'path' => 'auth/register.php'],
    ['label' => 'Contact Us', 'path' => 'contact.php'],
];

if (isLoggedIn() && $footerRole === 'customer') {
    $footerLinks = [
        ['label' => 'Dashboard', 'path' => 'bookings/dashboard.php'],
        ['label' => 'Home', 'path' => 'index.php'],
        ['label' => 'Book', 'path' => 'bookings/book-table.php'],
        ['label' => 'My Bookings', 'path' => 'bookings/my-bookings.php'],
        ['label' => 'Profile', 'path' => 'bookings/profile.php'],
        ['label' => 'Logout', 'path' => 'auth/logout.php'],
    ];
} elseif (isLoggedIn() && $footerRole === 'admin') {
    $footerLinks = [
        ['label' => 'Timeline', 'path' => 'admin/timeline/new-dashboard.php'],
        ['label' => 'Bookings Management', 'path' => 'admin/bookings-management.php'],
        ['label' => 'Tables Management', 'path' => 'admin/tables-management.php'],
        ['label' => 'Menu Management', 'path' => 'admin/menu-management.php'],
        ['label' => 'Logout', 'path' => 'auth/logout.php'],
    ];
}
?>
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 footer-brand">
                <h4>DineMate</h4>
                <p>Modern reservation support for Old Canberra Inn. Book faster, manage visits clearly, and keep dining service running smoothly.</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                </div>
                <button onclick="scrollTopPage()" class="back-top">↑ Back to Top</button>
            </div>
            <div class="col-md-4">
                <h5>Site Map</h5>
                <ul>
                    <?php foreach ($footerLinks as $footerLink): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(appPath($footerLink['path']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($footerLink['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Legal</h5>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Restaurant Policies</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        © 2026 Old Canberra Inn - Powered by DineMate
    </div>
</footer>

<style>
.footer {
.footer .col-md-4 {
    margin-bottom: 24px;
}
.footer-brand {
    max-width: 340px;
}
.footer ul {
.footer .container {
    max-width: 1320px;
}

.footer p {
    line-height: 1.7;
}

.back-top:hover {
    background: #f8fafc;
}
@media (max-width: 767px) {
    .footer {
        padding-top: 48px;
    }
}
</style>

<script>
function scrollTopPage() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        


    
