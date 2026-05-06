<?php
if (!isset($landingBaseUrl)) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $landingBaseUrl = preg_replace('#/pages$#', '', $scriptDir);
    $landingBaseUrl = $landingBaseUrl === '/' ? '' : rtrim($landingBaseUrl, '/');
}
?>
<!-- Footer -->
<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h4>DineMate</h4>
            <p>The world's leading restaurant reservation platform, making dining discoveries effortless.</p>
            <div class="social-links">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        
        <div class="footer-section">
            <h5>For Diners</h5>
            <ul>
                <li><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/for-diners.php', ENT_QUOTES); ?>">Browse Restaurants</a></li>
                <li><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/for-diners.php', ENT_QUOTES); ?>">How It Works</a></li>
                <li><a href="#">Gift Cards</a></li>
                <li><a href="#">Mobile App</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h5>For Restaurants</h5>
            <ul>
                <li><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/for-restaurants.php', ENT_QUOTES); ?>">Partner With Us</a></li>
                <li><a href="#">Pricing</a></li>
                <li><a href="#">Restaurant Admin</a></li>
                <li><a href="#">Support</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h5>Company</h5>
            <ul>
                <li><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/about.php', ENT_QUOTES); ?>">About Us</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Press</a></li>
                <li><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/contact.php', ENT_QUOTES); ?>">Contact</a></li>
            </ul>
        </div>
        
        <div class="footer-section">
            <h5>Legal</h5>
            <ul>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
                <li><a href="#">Cookie Policy</a></li>
                <li><a href="#">Accessibility</a></li>
            </ul>
        </div>
    </div>
    
    <div class="footer-bottom">
        <p>&copy; 2026 DineMate. All rights reserved. | Made with <i class="fas fa-heart" style="color: #e74c3c;"></i> for food lovers.</p>
    </div>
</footer>

<script src="<?php echo htmlspecialchars($landingBaseUrl . '/assets/js/main.js', ENT_QUOTES); ?>"></script>
</body>
</html>
