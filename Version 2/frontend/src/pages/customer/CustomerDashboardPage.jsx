import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api/client';

export function CustomerDashboardPage() {
  const [bookings, setBookings] = useState([]);
  const [profile, setProfile] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.all([api.myBookings(), api.customerProfile()])
      .then(([bookingsRes, profileRes]) => {
        setBookings(bookingsRes.bookings || []);
        setProfile(profileRes.profile || {});
      })
      .catch((err) => setError(err.message || 'Failed to load dashboard'));
  }, []);

  const active = bookings.filter((b) => ['pending', 'confirmed'].includes(String(b.status || '').toLowerCase()));
  const completed = bookings.filter((b) => String(b.status || '').toLowerCase() === 'completed');
  const nextBooking = active[0] || null;
  const recentHistory = bookings.filter((b) => !['pending', 'confirmed'].includes(String(b.status || '').toLowerCase())).slice(0, 5);
  const avgParty = completed.length ? (completed.reduce((sum, row) => sum + Number(row.number_of_guests || 0), 0) / completed.length) : 0;

  return (
    <div className="container customer-dashboard">
      {error && <div className="alert alert-danger">{error}</div>}
      <div className="dashboard-shell">
        <section className="dashboard-hero">
          <div className="hero-copy">
            <h1>Welcome back, {profile?.name || 'Customer'}</h1>
            <p>Manage your bookings, profile, and dining preferences.</p>
            <div className="hero-actions">
              <Link className="btn-portal" to="/customer/book-table"><i className="fa fa-calendar-plus"></i> New Booking</Link>
              <Link className="btn-portal-secondary" to="/customer/my-bookings"><i className="fa fa-clock-rotate-left"></i> View Booking History</Link>
              <Link className="btn-portal-secondary" to="/customer/profile"><i className="fa fa-user-gear"></i> Update Profile</Link>
            </div>
          </div>
          <aside className="hero-focus">
            <div className="hero-focus-label">Next Up</div>
            {nextBooking ? (
              <>
                <div className="hero-focus-title">{nextBooking.booking_date} at {nextBooking.start_time?.slice(0, 5)}</div>
                <div className="hero-focus-meta">
                  <span><i className="fa fa-users"></i> {nextBooking.number_of_guests} guests</span>
                  <span><i className="fa fa-table-cells-large"></i> {nextBooking.table_number ? `Table ${nextBooking.table_number}` : 'Pending assignment'}</span>
                  <span><i className="fa fa-circle-info"></i> {nextBooking.status_label || nextBooking.status}</span>
                </div>
              </>
            ) : <div className="hero-focus-title">No upcoming bookings</div>}
          </aside>
        </section>

        <section className="metric-grid">
          <article className="metric-card"><div className="metric-label">Completed Visits</div><div className="metric-value">{completed.length}</div><div className="metric-meta">Completed reservations in your history.</div></article>
          <article className="metric-card"><div className="metric-label">Upcoming</div><div className="metric-value">{active.length}</div><div className="metric-meta">Pending and confirmed reservations.</div></article>
          <article className="metric-card"><div className="metric-label">Average Party</div><div className="metric-value">{avgParty ? avgParty.toFixed(avgParty % 1 === 0 ? 0 : 1) : '0'}</div><div className="metric-meta">Average guests per booking.</div></article>
          <article className="metric-card"><div className="metric-label">Shared History</div><div className="metric-value">{bookings.length}</div><div className="metric-meta">Bookings linked to your customer profile.</div></article>
        </section>

        <section className="dashboard-lower">
          <article className="dashboard-panel">
            <div className="panel-heading"><div><h3>Recent Visit History</h3><p>Recent bookings and rebooking access.</p></div><Link className="btn-portal-secondary" to="/customer/my-bookings">See Full History</Link></div>
            {recentHistory.length ? (
              <div className="history-list">
                {recentHistory.map((booking) => (
                  <div className="history-item" key={booking.booking_id}>
                    <div className="history-row">
                      <div><div className="timeline-card-title" style={{ fontSize: 17 }}>{booking.booking_date}</div><div className="history-meta">{booking.start_time?.slice(0, 5)}, {booking.number_of_guests} guests</div></div>
                      <span className={`status-tag ${booking.status || 'pending'}`}>{booking.status_label || booking.status}</span>
                    </div>
                  </div>
                ))}
              </div>
            ) : <div className="empty-state">Completed visits will appear here.</div>}
          </article>
          <article className="dashboard-panel">
            <div className="panel-heading"><div><h3>Loyalty Snapshot</h3><p>Summary of your booking activity.</p></div></div>
            <div className="mini-stat-list">
              <div className="profile-chip"><strong>Reminder Preferences</strong><span>{Number(profile?.email_reminders_enabled ?? 1) ? 'Email reminders on' : 'Email reminders off'}, {Number(profile?.sms_reminders_enabled ?? 0) ? 'SMS reminders on' : 'SMS reminders off'}</span></div>
            </div>
          </article>
        </section>
      </div>
    </div>
  );
}
