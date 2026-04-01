<?php
?>

<?php include "includes/header.php"; ?>

<style>

body{
font-family:'Poppins',sans-serif;
background:#f8f9fa;
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
background:linear-gradient(120deg,rgba(0,0,0,0.7),rgba(0,0,0,0.3));
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
background:#f4b400;
border:none;
padding:16px 40px;
border-radius:40px;
font-weight:600;
font-size:18px;
transition:0.3s;
}

.btn-book:hover{
transform:scale(1.05);
background:#e0a800;
}

/* FLOATING ICONS */

.floating{
position:absolute;
font-size:40px;
animation:float 6s ease-in-out infinite;
}

.food1{top:20%;left:10%;}
.food2{top:70%;right:12%;animation-delay:1s;}
.food3{top:40%;right:25%;animation-delay:2s;}

@keyframes float{
0%{transform:translateY(0px)}
50%{transform:translateY(-20px)}
100%{transform:translateY(0px)}
}

/* SECTION */

.section{
padding:80px 0;
}

/* MENU CARDS */

.menu-card{
background:white;
border-radius:18px;
overflow:hidden;
box-shadow:0 20px 40px rgba(0,0,0,0.1);
transition:0.4s;
height:100%;
}

.menu-card:hover{
transform:translateY(-12px) scale(1.02);
box-shadow:0 25px 50px rgba(0,0,0,0.2);
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
color:#6b7280;
font-size:15px;
}

/* FEATURE CARDS */

.feature-card{
background:white;
padding:30px;
border-radius:14px;
box-shadow:0 10px 30px rgba(0,0,0,0.1);
text-align:center;
transition:0.3s;
}

.feature-card:hover{
transform:translateY(-6px);
}

.feature-icon{
font-size:40px;
color:#f4b400;
margin-bottom:15px;
}

/* ABOUT */

.about-box{
background:white;
padding:100px;
border-radius:14px;
box-shadow:0 10px 30px rgba(0,0,0,0.1);
}

/* TABLE AVAILABILITY */

.table-preview{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:20px;
}

.table-card{
background:white;
border-radius:14px;
padding:25px;
text-align:center;
box-shadow:0 15px 30px rgba(0,0,0,0.1);
transition:0.3s;
}

.table-card:hover{
transform:translateY(-5px);
}

.table-icon{
font-size:28px;
margin-bottom:10px;
}

.available{color:#22c55e;}
.booked{color:#ef4444;}

/* REVIEWS */

.review-card{
background:white;
border-radius:14px;
padding:30px;
text-align:center;
box-shadow:0 10px 25px rgba(0,0,0,0.1);
}

.review-img{
width:70px;
height:70px;
border-radius:50%;
margin-bottom:15px;
}

</style>

</head>

<body>


<!-- HERO -->

<section class="hero">

<div class="hero-overlay"></div>

<div class="hero-content">

<h1>Experience Dining <br><span class="typed-text"></span></h1>

<p>Reserve your table instantly at Old Canberra Inn.</p>

<a href="bookings/book-table.php" class="btn btn-book">
<i class="fa fa-calendar-check"></i> Book a Table
</a>

</div>

<div class="floating food1">🍽</div>
<div class="floating food2">🍷</div>
<div class="floating food3">🍔</div>

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


<?php include "includes/footer.php"; ?>

<script>

const words=["Like Never Before","Without Waiting","In Seconds"];
let wordIndex=0;
let charIndex=0;

const typedText=document.querySelector(".typed-text");

function typeEffect(){

if(charIndex < words[wordIndex].length){

typedText.textContent += words[wordIndex].charAt(charIndex);
charIndex++;

setTimeout(typeEffect,70);

}else{

setTimeout(eraseEffect,1500);

}

}

function eraseEffect(){

if(charIndex>0){

typedText.textContent=words[wordIndex].substring(0,charIndex-1);
charIndex--;

setTimeout(eraseEffect,40);

}else{

wordIndex++;

if(wordIndex>=words.length){wordIndex=0;}

setTimeout(typeEffect,200);

}

}

document.addEventListener("DOMContentLoaded",function(){

setTimeout(typeEffect,500);

});

</script>

</body>
</html>