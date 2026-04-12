<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$documentRootPath = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$projectRootPath = realpath(__DIR__ . '/..') ?: '';
$basePath = '';

if ($documentRootPath !== '' && $projectRootPath !== '') {
    $normalizedDocumentRoot = str_replace('\\', '/', $documentRootPath);
    $normalizedProjectRoot = str_replace('\\', '/', $projectRootPath);
    if (strpos($normalizedProjectRoot, $normalizedDocumentRoot) === 0) {
        $basePath = str_replace('\\', '/', substr($normalizedProjectRoot, strlen($normalizedDocumentRoot)));
    }
}

$basePath = rtrim($basePath, '/');
$requestPath = str_replace('\\', '/', (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
$relativeRequestPath = ltrim($requestPath, '/');

if ($basePath !== '' && strpos($requestPath, $basePath . '/') === 0) {
    $relativeRequestPath = ltrim(substr($requestPath, strlen($basePath)), '/');
}

$navUrl = static function (string $path) use ($basePath): string {
    return ($basePath !== '' ? $basePath : '') . '/' . ltrim($path, '/');
};

$isActivePath = static function (array $paths) use ($relativeRequestPath): bool {
    foreach ($paths as $path) {
        if ($relativeRequestPath === ltrim($path, '/')) {
            return true;
        }
    }

    return false;
};

$isLoggedInUser = isLoggedIn();
$currentUserRole = getCurrentUserRole();
?>
<!DOCTYPE html>
<html>
    <title>DineMate | Old Canberra Inn</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($navUrl('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <style>
        body {
            font-family: var(--dm-font-sans);
        }

        .navbar-toggler {
            border-color: var(--dm-border-strong);
            border-radius: var(--dm-radius-sm);
            padding: 8px 10px;
        }

        .navbar-toggler:focus {
            box-shadow: var(--dm-focus-ring);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-modern navbar-expand-lg">
    <div class="container-fluid">
        <a class="logo" href="<?php echo htmlspecialchars($navUrl('public/index.php'), ENT_QUOTES, 'UTF-8'); ?>">DineMate</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navMenu">
            <div class="nav-links">
                <?php if($isLoggedInUser && $currentUserRole === 'admin'): ?>
                    <a href="<?php echo htmlspecialchars($navUrl('admin/timeline/timeline.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['admin/timeline/timeline.php']) ? 'is-active' : ''; ?>">Timeline</a>
                    <a href="<?php echo htmlspecialchars($navUrl('admin/pages/bookings-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['admin/pages/bookings-management.php']) ? 'is-active' : ''; ?>">Bookings Management</a>
                    <a href="<?php echo htmlspecialchars($navUrl('admin/pages/tables-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['admin/pages/tables-management.php']) ? 'is-active' : ''; ?>">Tables Management</a>
                    <a href="<?php echo htmlspecialchars($navUrl('admin/pages/menu-management.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['admin/pages/menu-management.php']) ? 'is-active' : ''; ?>">Menu Management</a>
                    <a href="<?php echo htmlspecialchars($navUrl('auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-logout">Logout</a>
                <?php elseif($isLoggedInUser && $currentUserRole === 'customer'): ?>
                    <a href="<?php echo htmlspecialchars($navUrl('customer/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['customer/dashboard.php']) ? 'is-active' : ''; ?>">Dashboard</a>
                    <a href="<?php echo htmlspecialchars($navUrl('public/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['public/index.php']) ? 'is-active' : ''; ?>">Home</a>
                    <a href="<?php echo htmlspecialchars($navUrl('public/menu.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['public/menu.php']) ? 'is-active' : ''; ?>">Menu</a>
                    <a href="<?php echo htmlspecialchars($navUrl('customer/book-table.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-book <?php echo $isActivePath(['customer/book-table.php', 'customer/booking-confirmation.php']) ? 'is-active' : ''; ?>">
                        <i class="fa fa-calendar-check"></i> Book
                    </a>
                    <a href="<?php echo htmlspecialchars($navUrl('customer/my-bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['customer/my-bookings.php', 'customer/modify-booking.php']) ? 'is-active' : ''; ?>">My Bookings</a>
                    <a href="<?php echo htmlspecialchars($navUrl('customer/profile.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['customer/profile.php']) ? 'is-active' : ''; ?>">Profile</a>
                    <a href="<?php echo htmlspecialchars($navUrl('auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-logout">Logout</a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($navUrl('public/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['public/index.php']) ? 'is-active' : ''; ?>">Home</a>
                    <a href="<?php echo htmlspecialchars($navUrl('public/menu.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['public/menu.php']) ? 'is-active' : ''; ?>">Menu</a>
                    <a href="<?php echo htmlspecialchars($navUrl('public/about.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['public/about.php']) ? 'is-active' : ''; ?>">About</a>
                    <a href="<?php echo htmlspecialchars($navUrl('public/contact.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['public/contact.php']) ? 'is-active' : ''; ?>">Contact</a>
                    <a href="<?php echo htmlspecialchars($navUrl('customer/book-table.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-book <?php echo $isActivePath(['customer/book-table.php', 'customer/booking-confirmation.php']) ? 'is-active' : ''; ?>">
                        <i class="fa fa-calendar-check"></i> Book a Table
                    </a>
                    <a href="<?php echo htmlspecialchars($navUrl('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['auth/login.php']) ? 'is-active' : ''; ?>">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

