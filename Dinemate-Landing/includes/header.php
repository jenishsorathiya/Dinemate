<?php
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$landingBaseUrl = preg_replace('#/pages$#', '', $scriptDir);
$landingBaseUrl = $landingBaseUrl === '/' ? '' : rtrim($landingBaseUrl, '/');
$appBaseUrl = rtrim(str_replace('\\', '/', dirname($landingBaseUrl)), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - DineMate' : 'DineMate - Restaurant Reservation Platform'; ?></title>
    <meta name="description" content="<?php echo isset($pageDescription) ? htmlspecialchars($pageDescription) : 'DineMate: The world\'s leading restaurant reservation platform. Discover, book, and manage dining reservations effortlessly.'; ?>">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($landingBaseUrl . '/assets/css/global.css', ENT_QUOTES); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='75' font-size='75' fill='%234A7C59'%3E🍽️%3C/text%3E%3C/svg%3E">
    
    <style>
        body, html {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-logo">
                <a href="<?php echo htmlspecialchars($landingBaseUrl . '/index.php', ENT_QUOTES); ?>">
                    <span class="logo-icon">🍽️</span>
                    <span class="logo-text">DineMate</span>
                </a>
            </div>
            
            <div class="nav-toggle" id="navToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
            
            <ul class="nav-menu" id="navMenu">
                <li class="nav-item"><a href="<?php echo htmlspecialchars($landingBaseUrl . '/index.php', ENT_QUOTES); ?>" class="nav-link">Home</a></li>
                <li class="nav-item"><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/for-diners.php', ENT_QUOTES); ?>" class="nav-link">For Diners</a></li>
                <li class="nav-item"><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/for-restaurants.php', ENT_QUOTES); ?>" class="nav-link">For Restaurants</a></li>
                <li class="nav-item"><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/pricing.php', ENT_QUOTES); ?>" class="nav-link">Pricing</a></li>
                <li class="nav-item"><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/about.php', ENT_QUOTES); ?>" class="nav-link">About</a></li>
                <li class="nav-item"><a href="<?php echo htmlspecialchars($landingBaseUrl . '/pages/contact.php', ENT_QUOTES); ?>" class="nav-link">Contact</a></li>
                <li class="nav-item nav-item-auth">
                    <a href="<?php echo htmlspecialchars($appBaseUrl . '/auth/login.php', ENT_QUOTES); ?>" class="nav-link nav-link-login">Sign In</a>
                </li>
                <li class="nav-item nav-item-auth">
                    <a href="<?php echo htmlspecialchars($appBaseUrl . '/auth/register.php', ENT_QUOTES); ?>" class="nav-link nav-link-signup">Get Started</a>
                </li>
            </ul>
        </div>
    </nav>
