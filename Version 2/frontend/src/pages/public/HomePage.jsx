import { Link } from 'react-router-dom';

export function HomePage() {
  return (
    <div className="legacy-home">
      <section className="hero">
        <div className="hero-overlay"></div>
        <div className="hero-content">
          <h1>Experience Dining<br />Like Never Before</h1>
          <p>Reserve your table instantly at Old Canberra Inn.</p>
          <Link to="/customer/book-table" className="btn btn-book">
            <i className="fa fa-calendar-check"></i> Book a Table
          </Link>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <h2 className="text-center mb-5">Why Choose DineMate</h2>
          <div className="row g-4">
            <div className="col-md-3"><div className="feature-card"><div className="feature-icon"><i className="fa fa-chair"></i></div><h5>Real-Time Availability</h5><p>See available tables instantly without waiting.</p></div></div>
            <div className="col-md-3"><div className="feature-card"><div className="feature-icon"><i className="fa fa-clock"></i></div><h5>Instant Booking</h5><p>Reserve your table within seconds online.</p></div></div>
            <div className="col-md-3"><div className="feature-card"><div className="feature-icon"><i className="fa fa-user-shield"></i></div><h5>Secure Login</h5><p>Your reservations are protected and secure.</p></div></div>
            <div className="col-md-3"><div className="feature-card"><div className="feature-icon"><i className="fa fa-chart-line"></i></div><h5>Smart Management</h5><p>Admins manage tables and bookings easily.</p></div></div>
          </div>
        </div>
      </section>

      <section className="section bg-light">
        <div className="container">
          <div className="row align-items-center">
            <div className="col-md-6">
              <div className="about-box">
                <h3>About DineMate</h3>
                <p>DineMate is a modern reservation platform designed for Old Canberra Inn.</p>
                <p>Book tables online, avoid waiting times, and enjoy seamless dining experiences.</p>
              </div>
            </div>
            <div className="col-md-6">
              <img src="https://images.unsplash.com/photo-1559339352-11d035aa65de" className="img-fluid rounded" alt="About DineMate" />
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <h2 className="text-center mb-5">Popular Dishes</h2>
          <div className="row g-4">
            <div className="col-lg-4"><div className="menu-card"><img src="https://images.unsplash.com/photo-1600891964599-f61ba0e24092" alt="Grilled Steak" /><div className="menu-body"><div className="menu-title">Grilled Steak</div><p className="menu-desc">Premium grilled steak with herb butter.</p></div></div></div>
            <div className="col-lg-4"><div className="menu-card"><img src="https://images.unsplash.com/photo-1540189549336-e6e99c3679fe" alt="Italian Pasta" /><div className="menu-body"><div className="menu-title">Italian Pasta</div><p className="menu-desc">Authentic pasta with creamy parmesan sauce.</p></div></div></div>
            <div className="col-lg-4"><div className="menu-card"><img src="https://images.unsplash.com/photo-1600891964092-4316c288032e" alt="Signature Burger" /><div className="menu-body"><div className="menu-title">Signature Burger</div><p className="menu-desc">Chef special burger with smoked cheddar.</p></div></div></div>
          </div>
        </div>
      </section>

      <section className="section bg-light">
        <div className="container">
          <h2 className="text-center mb-5">Today's Table Availability</h2>
          <div className="table-preview">
            <div className="table-card"><div className="table-icon available"><i className="fa fa-check-circle"></i></div><h5>Table 1</h5><p className="available">Available</p></div>
            <div className="table-card"><div className="table-icon booked"><i className="fa fa-times-circle"></i></div><h5>Table 2</h5><p className="booked">Booked</p></div>
            <div className="table-card"><div className="table-icon available"><i className="fa fa-check-circle"></i></div><h5>Table 3</h5><p className="available">Available</p></div>
            <div className="table-card"><div className="table-icon available"><i className="fa fa-check-circle"></i></div><h5>Table 4</h5><p className="available">Available</p></div>
            <div className="table-card"><div className="table-icon booked"><i className="fa fa-times-circle"></i></div><h5>Table 5</h5><p className="booked">Booked</p></div>
            <div className="table-card"><div className="table-icon available"><i className="fa fa-check-circle"></i></div><h5>Table 6</h5><p className="available">Available</p></div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <h2 className="text-center mb-5">Customer Reviews</h2>
          <div className="row g-4">
            <div className="col-md-4"><div className="review-card"><img src="https://randomuser.me/api/portraits/men/73.jpg" className="review-img" alt="Reviewer 1" /><p>"Absolutely amazing dining experience!"</p><h6>jenish sorathiya</h6></div></div>
            <div className="col-md-4"><div className="review-card"><img src="https://randomuser.me/api/portraits/men/32.jpg" className="review-img" alt="Reviewer 2" /><p>"Reservation system is smooth and very easy."</p><h6>James Walker</h6></div></div>
            <div className="col-md-4"><div className="review-card"><img src="https://randomuser.me/api/portraits/men/45.jpg" className="review-img" alt="Reviewer 3" /><p>"Best online restaurant booking experience."</p><h6>Olivia Brown</h6></div></div>
          </div>
        </div>
      </section>
    </div>
  );
}
