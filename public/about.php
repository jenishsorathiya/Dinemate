<?php include __DIR__ . "/../includes/header.php"; ?>

<style>
.about-hero {
    background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), 
                url("https://images.unsplash.com/photo-1559339352-11d035aa65de");
    background-size: cover;
    background-position: center;
    height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: white;
    margin-top: 90px;
}
.about-hero h1{
    font-weight: 700;
}  

.section {
    padding: 70px 0;
}

.feature-card{
    background: white;
    border: 1px solid var(--dm-border);
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);
}

.feature-card:hover {
    box-shadow: 0 8px 24px rgba(15,23,42,0.10);
}

.feature-icon {
    font-size: 40px;
    color: #f4b400;
    margin-bottom: 15px;
}

.story-card {
    background: white;
    border: 1px solid var(--dm-border);
    border-radius: 10px;
    padding: 40px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);

}

.about-img {
    border-radius: 10px;
    box-shadow: 0 4px 16px rgba(15,23,42,0.06);
}

@media (max-width: 767px) {
    .about-hero,
    .contact-hero,
    .hero {
        height: auto;
        min-height: 60vh;
        padding: 70px 18px;
        margin-top: 80px;
    }

    .about-hero h1,
    .contact-hero h1,
    .hero h1 {
        font-size: 32px;
    }

    .about-hero p,
    .contact-hero p,
    .hero p {
        font-size: 16px;
    }

    .section {
        padding: 50px 0;
    }

    .feature-card,
    .story-card,
    .contact-card {
        padding: 20px;
    }
}

</style>

<section class="about-hero">
    <div>
        <h1>About Old Canberra Inn</h1>
        <p>Experience heritage dining with modern reservations</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="story-card">
                    <h3 class="text-warning mb-3">Our Story</h3>
                    <p>Established in <strong>1857</strong>, Old Canberra Inn is one of Canberra's oldest and most iconic pubs. Known for its heritage charm, welcoming atmosphere, and exceptional food and beverages, the Inn has been a favorite gathering place for locals and visitors alike.</p>
                    <p>From craft beers and delicious meals to community events and live entertainment, Old Canberra Inn blends tradition with a vibrant modern experience.</p>
                    <p>With <strong>DineMate</strong>, we bring that same hospitality into the digital world, offering a seamless reservation experience for our guests.</p>
                </div>
            </div>
            <div class="col-md-6">
                <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0" class="img-fluid about-img">
            </div>
        </div>
    </div>
</section>

<section class="section bg-light">
    <div class="container">
        <h2 class="text-center mb-5">Why Choose Us</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa fa-landmark"></i></div>
                    <h5>Heritage Experience</h5>
                    <p>One of Canberra's oldest pubs with authentic charm and history.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa fa-utensils"></i></div>
                    <h5>Exceptional Dining</h5>
                    <p>Enjoy premium meals, craft beverages, and seasonal menus.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fa fa-calendar-check"></i></div>
                    <h5>Easy Reservations</h5>
                    <p>Book your table instantly with our DineMate reservation system.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . "/../includes/footer.php"; ?>
