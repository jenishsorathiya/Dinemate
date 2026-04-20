export function AboutPage() {
  return (
    <div>
      <section className="about-hero">
        <div>
          <h1>About Old Canberra Inn</h1>
          <p>Experience heritage dining with modern reservations</p>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="row align-items-center">
            <div className="col-md-6">
              <div className="story-card">
                <h3 className="text-warning mb-3">Our Story</h3>
                <p>Established in <strong>1857</strong>, Old Canberra Inn is one of Canberra&apos;s oldest and most iconic pubs. Known for its heritage charm, welcoming atmosphere, and exceptional food and beverages, the Inn has been a favorite gathering place for locals and visitors alike.</p>
                <p>From craft beers and delicious meals to community events and live entertainment, Old Canberra Inn blends tradition with a vibrant modern experience.</p>
                <p>With <strong>DineMate</strong>, we bring that same hospitality into the digital world, offering a seamless reservation experience for our guests.</p>
              </div>
            </div>
            <div className="col-md-6">
              <img src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0" className="img-fluid about-img" alt="Old Canberra Inn" />
            </div>
          </div>
        </div>
      </section>

      <section className="section bg-light">
        <div className="container">
          <h2 className="text-center mb-5">Why Choose Us</h2>
          <div className="row g-4">
            <div className="col-md-4"><div className="feature-card"><div className="feature-icon"><i className="fa fa-landmark"></i></div><h5>Heritage Experience</h5><p>One of Canberra&apos;s oldest pubs with authentic charm and history.</p></div></div>
            <div className="col-md-4"><div className="feature-card"><div className="feature-icon"><i className="fa fa-utensils"></i></div><h5>Exceptional Dining</h5><p>Enjoy premium meals, craft beverages, and seasonal menus.</p></div></div>
            <div className="col-md-4"><div className="feature-card"><div className="feature-icon"><i className="fa fa-calendar-check"></i></div><h5>Easy Reservations</h5><p>Book your table instantly with our DineMate reservation system.</p></div></div>
          </div>
        </div>
      </section>
    </div>
  );
}
