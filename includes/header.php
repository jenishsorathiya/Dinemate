<?php
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

$isLoggedInUser = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html>
    <title>DineMate | Old Canberra Inn</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-modern {
            background: rgba(255, 255, 255, 0.98);
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 999;
            transition: 0.3s;
            position: relative;
        }
        .navbar-modern.scrolled {
            background: #0f172a;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        .logo {
            font-family: 'Pacifico', cursive;
            font-size: 28px;
            color: #f4b400;
            text-decoration: none;
            font-weight: bold;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 0;
        }
        .nav-links a {
            color: #333;
            margin-left: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
            display: inline-block;
        }
        .nav-links a:hover {
            color: #f4b400;
        }
        .nav-links a.is-active {
            color: #f4b400;
        }
        .btn-book {
            background: #f4b400;
            color: black;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-book:hover {
            background: #e0a800;
            transform: scale(1.05);
            color: black;
        }
        .btn-logout {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-logout:hover {
            background: #c82333;
            transform: scale(1.05);
            color: white;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-modern navbar-expand-lg">
    <div class="container-fluid">
        <a class="logo" href="<?php echo htmlspecialchars($navUrl('index.php'), ENT_QUOTES, 'UTF-8'); ?>">DineMate</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navMenu">
            <div class="nav-links">
                <a href="<?php echo htmlspecialchars($navUrl('index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['', 'index.php']) ? 'is-active' : ''; ?>">Home</a>
                <a href="<?php echo htmlspecialchars($navUrl('about.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['about.php']) ? 'is-active' : ''; ?>">About</a>
                <a href="<?php echo htmlspecialchars($navUrl('menu.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['menu.php']) ? 'is-active' : ''; ?>">Menu</a>
                <a href="<?php echo htmlspecialchars($navUrl('contact.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['contact.php']) ? 'is-active' : ''; ?>">Contact</a>
                <?php if($isLoggedInUser): ?>
                    <!-- Logged In User Links -->
                    <a href="<?php echo htmlspecialchars($navUrl('bookings/my-bookings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['bookings/my-bookings.php', 'bookings/modify-booking.php']) ? 'is-active' : ''; ?>">My Bookings</a>
                    <a href="<?php echo htmlspecialchars($navUrl('bookings/book-table.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-book <?php echo $isActivePath(['bookings/book-table.php', 'bookings/booking-confirmation.php']) ? 'is-active' : ''; ?>">
                        <i class="fa fa-calendar-check"></i> Book Table
                    </a>
                    <a href="<?php echo htmlspecialchars($navUrl('auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-logout">
                        Logout
                    </a>
                <?php else: ?>
                    <!-- Guest Links -->
                    <a href="<?php echo htmlspecialchars($navUrl('bookings/book-table.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-book <?php echo $isActivePath(['bookings/book-table.php', 'bookings/booking-confirmation.php']) ? 'is-active' : ''; ?>">
                        <i class="fa fa-calendar-check"></i> Book Table
                    </a>
                    <a href="<?php echo htmlspecialchars($navUrl('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $isActivePath(['auth/login.php']) ? 'is-active' : ''; ?>">Login</a>
                    <a href="<?php echo htmlspecialchars($navUrl('auth/register.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-book <?php echo $isActivePath(['auth/register.php']) ? 'is-active' : ''; ?>">
                        Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>