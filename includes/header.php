<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
        <a class="logo" href="index.php">DineMate</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navMenu">
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="about.php">About</a>
                <a href="menu.php">Menu</a>
                <a href="contact.php">Contact</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- Logged In User Links -->
                    <a href="bookings/my-bookings.php">My Bookings</a>
                    <a href="bookings/book-table.php" class="btn btn-book">
                        <i class="fa fa-calendar-check"></i> Book Table
                    </a>
                    <a href="auth/logout.php" class="btn btn-logout">
                        Logout
                    </a>
                <?php else: ?>
                    <!-- Guest Links -->
                    <a href="bookings/book-table.php" class="btn btn-book">
                        <i class="fa fa-calendar-check"></i> Book Table
                    </a>
                    <a href="auth/login.php">Login</a>
                    <a href="auth/register.php" class="btn btn-book">
                        Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>