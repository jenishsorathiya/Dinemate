<?php
?>

<?php include __DIR__ . "/../includes/header.php"; ?>

<style>

body{
font-family:'Inter',sans-serif;
background:var(--dm-bg);
margin:0;
}

/* HERO */

.hero{
position:relative;
height:100vh;
background:url("https://images.unsplash.com/photo-1414235077428-338989a2e8c0")
center/cover no-repeat;
display:flex;
align-items:center;
justify-content:center;
text-align:center;
color:white;
overflow:hidden;
}

.hero-overlay{
position:absolute;
top:0;
left:0;
width:100%;
height:100%;
background:linear-gradient(120deg,rgba(18,32,51,0.76),rgba(18,32,51,0.42));
}

.hero-content{
position:relative;
z-index:2;
}

.hero h1{
font-size:60px;
font-weight:700;
}

.hero p{
font-size:20px;
margin:20px 0 30px;
}

.btn-book{
background:var(--dm-accent-dark);
border:none;
padding:13px 32px;
border-radius:var(--dm-radius-sm);
font-weight:600;
font-size:16px;
color:var(--dm-surface);
transition:opacity 0.15s;
}

.btn-book:hover{
opacity:0.86;
}

/* SECTION */

.section{
padding:80px 0;
}

/* MENU CARDS */

.menu-card{
background:var(--dm-surface);
border-radius:var(--dm-radius-md);
overflow:hidden;
border:1px solid var(--dm-border);
box-shadow:0 4px 16px rgba(0,0,0,0.06);
transition:box-shadow 0.2s;
height:100%;
}

.menu-card:hover{
box-shadow:0 8px 24px rgba(0,0,0,0.10);
}

.menu-card img{
width:100%;
height:260px;
object-fit:cover;
}

.menu-body{
padding:20px;
text-align:center;
}

.menu-title{
font-size:22px;
font-weight:600;
margin-bottom:8px;
}

.menu-desc{
color:var(--dm-text-muted);
font-size:15px;
}

/* FEATURE CARDS */

.feature-card{
background:var(--dm-surface);
padding:24px;
border:1px solid var(--dm-border);
border-radius:var(--dm-radius-md);
box-shadow:0 4px 16px rgba(15,23,42,0.05);
text-align:center;
}

.feature-icon{
font-size:40px;
color:#f4b400;
margin-bottom:15px;
}

/* ABOUT */

.about-box{
background:var(--dm-surface);
padding:48px;
border:1px solid var(--dm-border);
border-radius:var(--dm-radius-lg);
box-shadow:0 4px 16px rgba(15,23,42,0.05);
}

/* TABLE AVAILABILITY */

.table-preview{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:20px;
}

.table-card{
background:var(--dm-surface);
border:1px solid var(--dm-border);
border-radius:var(--dm-radius-md);
padding:20px;
text-align:center;
box-shadow:0 4px 16px rgba(15,23,42,0.05);
}

.table-icon{
font-size:28px;
margin-bottom:10px;
}

.available{color:var(--dm-success-strong);}
.booked{color:var(--dm-danger-strong);}

/* REVIEWS */

.review-card{
background:var(--dm-surface);
border:1px solid var(--dm-border);
border-radius:var(--dm-radius-md);
padding:24px;
text-align:center;
box-shadow:0 4px 16px rgba(15,23,42,0.05);
}

.review-img{
width:70px;
height:70px;
border-radius:50%;
margin-bottom:15px;
}

@media (max-width: 767px) {
    .hero {
        height: auto;
        min-height: 72vh;
        padding: 80px 18px 60px;
    }

    .hero h1 {
        font-size: 32px;
        line-height: 1.1;
    }

    .hero p {
        font-size: 16px;
        margin-bottom: 24px;
    }

    .btn-book {
        width: 100%;
        max-width: 320px;
        padding: 14px 20px;
    }

    .feature-card,
    .menu-card,
    .review-card,
    .table-card,
    .about-box {
        padding: 18px;
    }

    .section {
        padding: 50px 0;
    }
}

</style>


<!-- HERO -->

<section class="hero">

<div class="hero-overlay"></div>

<div class="hero-content">

<h1>Experience Dining<br>Like Never Before</h1>

<p>Reserve your table instantly at Old Canberra Inn.</p>

<a href="<?= htmlspecialchars(appPath('customer/book-table.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-book">
<i class="fa fa-calendar-check"></i> Book a Table
</a>

</div>

</section>

<!-- FEATURES -->

<section class="section">

<div class="container">

<h2 class="text-center mb-5">Why Choose DineMate</h2>

<div class="row g-4">

<div class="col-md-3">
<div class="feature-card">
<div class="feature-icon"><i class="fa fa-chair"></i></div>
<h5>Real-Time Availability</h5>
<p>See available tables instantly without waiting.</p>
</div>
</div>

<div class="col-md-3">
<div class="feature-card">
<div class="feature-icon"><i class="fa fa-clock"></i></div>
<h5>Instant Booking</h5>
<p>Reserve your table within seconds online.</p>
</div>
</div>

<div class="col-md-3">
<div class="feature-card">
<div class="feature-icon"><i class="fa fa-user-shield"></i></div>
<h5>Secure Login</h5>
<p>Your reservations are protected and secure.</p>
</div>
</div>

<div class="col-md-3">
<div class="feature-card">
<div class="feature-icon"><i class="fa fa-chart-line"></i></div>
<h5>Smart Management</h5>
<p>Admins manage tables and bookings easily.</p>
</div>
</div>

</div>

</div>

</section>


<!-- ABOUT -->

<section class="section bg-light">

<div class="container">

<div class="row align-items-center">

<div class="col-md-6">

<div class="about-box">

<h3>About DineMate</h3>

<p>DineMate is a modern reservation platform designed for Old Canberra Inn.</p>

<p>Book tables online, avoid waiting times, and enjoy seamless dining experiences.</p>

</div>

</div>

<div class="col-md-6">

<img src="https://images.unsplash.com/photo-1559339352-11d035aa65de"
class="img-fluid rounded">

</div>

</div>

</div>

</section>


<!-- POPULAR DISHES -->

<section class="section">

<div class="container">

<h2 class="text-center mb-5">Popular Dishes</h2>

<div class="row g-4">

<div class="col-lg-4">

<div class="menu-card">

<img src="https://images.unsplash.com/photo-1600891964599-f61ba0e24092">

<div class="menu-body">

<div class="menu-title">Grilled Steak</div>

<p class="menu-desc">
Premium grilled steak with herb butter.
</p>

</div>

</div>

</div>


<div class="col-lg-4">

<div class="menu-card">

<img src="https://images.unsplash.com/photo-1540189549336-e6e99c3679fe">

<div class="menu-body">

<div class="menu-title">Italian Pasta</div>

<p class="menu-desc">
Authentic pasta with creamy parmesan sauce.
</p>

</div>

</div>

</div>


<div class="col-lg-4">

<div class="menu-card">

<img src="https://images.unsplash.com/photo-1600891964092-4316c288032e">

<div class="menu-body">

<div class="menu-title">Signature Burger</div>

<p class="menu-desc">
Chef special burger with smoked cheddar.
</p>

</div>

</div>

</div>

</div>

</div>

</section>


<!-- TABLE AVAILABILITY -->

<section class="section bg-light">

<div class="container">

<h2 class="text-center mb-5">Today's Table Availability</h2>

<div class="table-preview">

<div class="table-card">
<div class="table-icon available"><i class="fa fa-check-circle"></i></div>
<h5>Table 1</h5>
<p class="available">Available</p>
</div>

<div class="table-card">
<div class="table-icon booked"><i class="fa fa-times-circle"></i></div>
<h5>Table 2</h5>
<p class="booked">Booked</p>
</div>

<div class="table-card">
<div class="table-icon available"><i class="fa fa-check-circle"></i></div>
<h5>Table 3</h5>
<p class="available">Available</p>
</div>

<div class="table-card">
<div class="table-icon available"><i class="fa fa-check-circle"></i></div>
<h5>Table 4</h5>
<p class="available">Available</p>
</div>

<div class="table-card">
<div class="table-icon booked"><i class="fa fa-times-circle"></i></div>
<h5>Table 5</h5>
<p class="booked">Booked</p>
</div>

<div class="table-card">
<div class="table-icon available"><i class="fa fa-check-circle"></i></div>
<h5>Table 6</h5>
<p class="available">Available</p>
</div>

</div>

</div>

</section>


<!-- CUSTOMER REVIEWS -->

<section class="section">

<div class="container">

<h2 class="text-center mb-5">Customer Reviews</h2>

<div class="row g-4">

<div class="col-md-4">

<div class="review-card">

<img src="https://randomuser.me/api/portraits/men/73.jpg" class="review-img">

<p>"Absolutely amazing dining experience!"</p>

<h6>jenish sorathiya</h6>

</div>

</div>


<div class="col-md-4">

<div class="review-card">

<img src="https://randomuser.me/api/portraits/men/32.jpg" class="review-img">

<p>"Reservation system is smooth and very easy."</p>

<h6>James Walker</h6>

</div>

</div>


<div class="col-md-4">

<div class="review-card">

<img src="https://randomuser.me/api/portraits/men/45.jpg" class="review-img">

<p>"Best online restaurant booking experience."</p>

<h6>Olivia Brown</h6>

</div>

</div>

</div>

</div>

</section>


<?php include __DIR__ . "/../includes/footer.php"; ?>

</body>
</html>