import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../../api/client';

export function CustomerBookingsPage() {
  const navigate = useNavigate();
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const [view, setView] = useState('upcoming');
  const [statusFilter, setStatusFilter] = useState('all');
  const [search, setSearch] = useState('');

  useEffect(() => {
    api.myBookings().then((res) => setRows(res.bookings || [])).catch((err) => setError(err.message || 'Failed to load bookings'));
  }, []);

  const counts = useMemo(() => {
    const result = { upcoming: 0, past: 0, cancelled: 0, no_show: 0, all: rows.length };
    const now = Date.now();
    rows.forEach((b) => {
      const status = String(b.status || '').toLowerCase();
      const bookingTs = new Date(`${b.booking_date}T${b.start_time || '00:00:00'}`).getTime();
      const isUpcoming = ['pending', 'confirmed'].includes(status) && bookingTs >= now;
      if (isUpcoming) result.upcoming += 1;
      else if (status === 'cancelled') result.cancelled += 1;
      else if (status === 'no_show') result.no_show += 1;
      else result.past += 1;
    });
    return result;
  }, [rows]);

  const filtered = useMemo(() => {
    const now = Date.now();
    return rows.filter((b) => {
      const status = String(b.status || '').toLowerCase();
      const bookingTs = new Date(`${b.booking_date}T${b.start_time || '00:00:00'}`).getTime();
      const isUpcoming = ['pending', 'confirmed'].includes(status) && bookingTs >= now;
      if (view === 'upcoming' && !isUpcoming) return false;
      if (view === 'past' && (isUpcoming || ['cancelled', 'no_show'].includes(status))) return false;
      if (view === 'cancelled' && status !== 'cancelled') return false;
      if (view === 'no_show' && status !== 'no_show') return false;
      if (statusFilter !== 'all' && status !== statusFilter) return false;
      if (search.trim() !== '') {
        const haystack = `${b.booking_date} ${b.table_number || ''} ${b.special_request || ''} ${b.status_label || ''}`.toLowerCase();
        if (!haystack.includes(search.toLowerCase())) return false;
      }
      return true;
    });
  }, [rows, view, statusFilter, search]);

  return (
    <div className="container bookings-wrapper">
      <div className="bookings-shell">
        {error && <div className="alert alert-danger">{error}</div>}
        <div className="bookings-hero">
          <div><h2><i className="fa fa-calendar-check text-warning"></i> My Reservations</h2><p>View, filter, and manage your reservations.</p></div>
          <div className="hero-actions"><Link to="/customer/dashboard" className="btn-surface"><i className="fa fa-gauge"></i> Dashboard</Link><Link to="/customer/book-table" className="btn-primary-solid"><i className="fa fa-calendar-plus"></i> New Booking</Link></div>
        </div>
        <div className="view-tabs">
          {[
            ['upcoming', 'Upcoming'],
            ['past', 'Past'],
            ['cancelled', 'Cancelled'],
            ['no_show', 'No-show'],
            ['all', 'All'],
          ].map(([key, label]) => (
            <button type="button" key={key} className={`view-tab ${view === key ? 'is-active' : ''}`} onClick={() => setView(key)}>
              <span>{label}</span><span>{counts[key]}</span>
            </button>
          ))}
        </div>
        <div className="filter-row">
          <input className="filter-input" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by date, table, note, or source..." />
          <select className="filter-select" value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
            <option value="all">All statuses</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No-show</option>
          </select>
          <div></div>
        </div>
        <div className="hint-card">Active bookings can be rescheduled or cancelled.</div>
        {filtered.length ? (
          <div className="booking-grid">
            {filtered.map((booking) => {
              const editable = ['pending', 'confirmed'].includes(String(booking.status || '').toLowerCase());
              const rebookQuery = new URLSearchParams({
                rebook: String(booking.booking_id),
                date: String(booking.booking_date || ''),
                time: String((booking.start_time || '12:00').slice(0, 5)),
                guests: String(booking.number_of_guests || 2),
                special: String(booking.special_request || ''),
              }).toString();
              return (
                <article className="booking-card" key={booking.booking_id}>
                  <div className="booking-card-top">
                    <div><div className="booking-card-title">{booking.booking_date}</div><div className="booking-card-subtitle">{booking.start_time?.slice(0, 5)} to {booking.end_time?.slice(0, 5)}</div></div>
                    <span className={`status-tag ${booking.status || 'pending'}`}>{booking.status_label || booking.status}</span>
                  </div>
                  <div className="booking-chip-list">
                    <span className="booking-chip"><i className="fa fa-users"></i> {booking.number_of_guests} guests</span>
                    <span className="booking-chip"><i className="fa fa-table-cells"></i> {booking.table_number ? `Table ${booking.table_number}` : 'Pending assignment'}</span>
                  </div>
                  {booking.special_request && <div className="booking-detail"><strong>Saved note:</strong> {booking.special_request}</div>}
                  <div className="booking-actions">
                    {editable && <button type="button" className="btn-surface" onClick={() => navigate(`/customer/modify-booking/${booking.booking_id}`)}><i className="fa fa-pen"></i> Reschedule</button>}
                    {editable && <button type="button" className="btn-surface" onClick={async () => { await api.cancelMyBooking(booking.booking_id); const res = await api.myBookings(); setRows(res.bookings || []); }}><i className="fa fa-ban"></i> Cancel</button>}
                    <Link to={`/customer/book-table?${rebookQuery}`} className="btn-primary-solid"><i className="fa fa-repeat"></i> Rebook</Link>
                  </div>
                </article>
              );
            })}
          </div>
        ) : (
          <div className="empty-state"><p>No reservations found.</p><Link to="/customer/book-table" className="btn-primary-solid" style={{ marginTop: 10 }}><i className="fa fa-calendar-plus"></i> Book Your Next Table</Link></div>
        )}
      </div>
    </div>
  );
}
