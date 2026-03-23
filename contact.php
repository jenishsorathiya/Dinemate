<?php include "includes/header.php"; ?>

<style>
    .contact-hero {
    background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), 
                url("https://images.unsplash.com/photo-1559339352-11d035aa65de");
    background-size: cover;
    background-position: center;
    height: 260px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: white;
    margin-top: 90px;
}
.section {
    padding: 70px 0;
}
.contact-card {
    background: white;
    padding: 35px;
    border-radius: 14px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.08);
}
.form-control {
    border-radius: 10px;
    padding: 12px;
}
.contact-icon {
    font-size: 28px;
    color: #f4b400;
    margin-right: 10px;
}
.btn-contact {
    background: #f4b400;
    border: none;
    padding: 12px 25px;
    border-radius: 30px;
    font-weight: 600;
}
</style>

<section class="contact-hero">
    <div>
        <h1>Contact Us</h1>
        <p>We Would lovw to hear from your</p>
    </div>
</selection>

<section class="selection">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <div class="contact-info">
                    <<h4 class="mb-4">Restaurant Information</h4>
                    <p><i class="fa fa-location-dot contact-icon"></i> Old Canberra Inn, 195 Mouat St, Lyneham ACT 2602, Australia</p>
                    <p><i class="fa fa-phone contact-icon"></i> +61 2 6248 7424</p>
                    <p><i class="fa fa-envelope contact-icon"></i> info@oldcanberrainn.com</p>
                    <p><i class="fa fa-clock contact-icon"></i> Mon – Sun : 11:00 AM – 11:00 PM</p>
                </div>
            </div>
             <div class="col-md-7">
                <div class="contact-card">
                    <h4 class="mb-4">Reservation Help</h4>
                    <form>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Your Name</label>
                                <input type="text" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email</label>
                                <input type="email" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Phone</label>
                                <input type="text" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Subject</label>
                                <input type="text" class="form-control">
                            </div>
                            <div class="col-12 mb-3">
                                <label>Message</label>
                                <textarea class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                        <button class="btn btn-contact"><i class="fa fa-paper-plane"></i> Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section bg-light">
    <div class="container">
        <h3 class="text-center mb-5">Find Us Here</h3>
        <div class="map">
            <iframe src="https://www.google.com/maps?q=Old%20Canberra%20Inn&output=embed" width="100%" height="400" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
</section>

<?php include "includes/footer.php"; ?>
