export function ContactPage() {
  return (
    <div>
      <section className="contact-hero">
        <div>
          <h1>Contact Us</h1>
          <p>We would love to hear from you</p>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="row g-4">
            <div className="col-md-5">
              <div className="contact-info">
                <h4 className="mb-4">Restaurant Information</h4>
                <p><i className="fa fa-location-dot contact-icon"></i> Old Canberra Inn, 195 Mouat St, Lyneham ACT 2602, Australia</p>
                <p><i className="fa fa-phone contact-icon"></i> +61 421108735</p>
                <p><i className="fa fa-envelope contact-icon"></i> info@oldcanberrainn.com</p>
                <p><i className="fa fa-clock contact-icon"></i> Mon – Sun : 11:00 AM – 11:00 PM</p>
              </div>
            </div>
            <div className="col-md-7">
              <div className="contact-card">
                <h4 className="mb-4">Reservation Help</h4>
                <form onSubmit={(e) => e.preventDefault()}>
                  <div className="row">
                    <div className="col-md-6 mb-3"><label>Your Name</label><input type="text" className="form-control" required /></div>
                    <div className="col-md-6 mb-3"><label>Email</label><input type="email" className="form-control" required /></div>
                    <div className="col-md-6 mb-3"><label>Phone</label><input type="text" className="form-control" /></div>
                    <div className="col-md-6 mb-3"><label>Subject</label><input type="text" className="form-control" /></div>
                    <div className="col-12 mb-3"><label>Message</label><textarea className="form-control" rows="4"></textarea></div>
                  </div>
                  <button className="btn btn-contact"><i className="fa fa-paper-plane"></i> Send Message</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="section bg-light">
        <div className="container">
          <h3 className="text-center mb-5">Find Us Here</h3>
          <div className="map">
            <iframe src="https://www.google.com/maps?q=Old%20Canberra%20Inn&output=embed" width="100%" height="400" style={{ border: 0 }} loading="lazy" title="Old Canberra Inn Map"></iframe>
          </div>
        </div>
      </section>
    </div>
  );
}
