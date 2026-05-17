<?php
require_once __DIR__ . '/functions.php';

startAppSession();

$footerRole = getCurrentUserRole();
$footerLinks = [
    ['label' => 'Home', 'path' => 'public/index.php'],
    ['label' => 'Book a Table', 'path' => 'customer/book-table.php'],
    ['label' => 'Login', 'path' => 'auth/login.php'],
    ['label' => 'Register', 'path' => 'auth/register.php'],
    ['label' => 'Contact Us', 'path' => 'public/contact.php'],
];

if (isLoggedIn() && $footerRole === 'customer') {
    $footerLinks = [
        ['label' => 'Dashboard', 'path' => 'customer/dashboard.php'],
        ['label' => 'Home', 'path' => 'public/index.php'],
        ['label' => 'Book', 'path' => 'customer/book-table.php'],
        ['label' => 'My Bookings', 'path' => 'customer/my-bookings.php'],
        ['label' => 'Profile', 'path' => 'customer/profile.php'],
        ['label' => 'Logout', 'path' => 'auth/logout.php'],
    ];
} elseif (isLoggedIn() && $footerRole === 'admin') {
    $footerLinks = [
        ['label' => 'Admin Home', 'path' => 'admin/pages/admin_home.php'],
        ['label' => 'Bookings', 'path' => 'admin/pages/admin_bookings.php'],
        ['label' => 'Inbox', 'path' => 'admin/pages/admin_inbox.php'],
        ['label' => 'Tables Management', 'path' => 'admin/pages/tables-management.php'],
        ['label' => 'Menu Management', 'path' => 'admin/pages/menu-management.php'],
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
                <button type="button" class="back-top" data-scroll-top>↑ Back to Top</button>
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
                    <li><a href="<?php echo htmlspecialchars(appPath('public/privacy.php'), ENT_QUOTES, 'UTF-8'); ?>">Privacy Policy</a></li>
                    <li><a href="<?php echo htmlspecialchars(appPath('public/terms.php'), ENT_QUOTES, 'UTF-8'); ?>">Terms of Service</a></li>
                    <li><a href="<?php echo htmlspecialchars(appPath('public/policies.php'), ENT_QUOTES, 'UTF-8'); ?>">Restaurant Policies</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        © 2026 Old Canberra Inn - Powered by DineMate
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php
$extraFooterScripts = $extraFooterScripts ?? [];
if (is_array($extraFooterScripts)) {
    foreach ($extraFooterScripts as $script) {
        $scriptPath = is_array($script) ? (string) ($script['src'] ?? '') : (string) $script;
        $scriptType = is_array($script) ? trim((string) ($script['type'] ?? '')) : '';
        if ($scriptPath === '') {
            continue;
        }
        $scriptSrc = preg_match('#^(?:https?:)?//#i', $scriptPath) ? $scriptPath : assetUrl($scriptPath);
        echo '<script src="' . htmlspecialchars($scriptSrc, ENT_QUOTES, 'UTF-8') . '"' .
            ($scriptType !== '' ? ' type="' . htmlspecialchars($scriptType, ENT_QUOTES, 'UTF-8') . '"' : '') .
            '></script>' . PHP_EOL;
    }
}
?>
</body>
</html>
        


    

