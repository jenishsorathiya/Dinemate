import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../state/AuthProvider';
import { api } from '../../api/client';
import { Card } from '../../components/common/Card';

export function BookingConfirmationPage() {
  const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
  const bookingId = Number(params.get('id') || 0);
  const token = params.get('token') || '';
  const [booking, setBooking] = useState(null);
  const [error, setError] = useState('');
  const { user } = useAuth();

  useEffect(() => {
    if (!bookingId) {
      setError('Booking confirmation not found.');
      return;
    }
    api.bookingConfirmation(bookingId, token).then((res) => setBooking(res.booking || null)).catch((err) => setError(err.message || 'Failed to load booking confirmation'));
  }, [bookingId, token]);

  if (error) return <Card title="Booking Confirmation"><p className="error-text">{error}</p></Card>;
  if (!booking) return <Card title="Booking Confirmation"><p className="muted">Loading booking confirmation...</p></Card>;

  const isLoggedInCustomer = user?.role === 'customer';
  const rebookQuery = new URLSearchParams({
    rebook: String(booking.booking_id || ''),
    date: String(booking.booking_date || ''),
    time: String((booking.start_time || '12:00').slice(0, 5)),
    guests: String(booking.number_of_guests || 2),
    special: String(booking.special_request || ''),
  }).toString();

  return (
    <div className="container confirm-wrapper">
      <div className="confirm-card">
        <div className="success-icon"><i className="fa fa-circle-check"></i></div>
        <h3 className="text-success mt-2">Reservation Request Submitted</h3>
        <p className="text-muted">Your request has been saved. A table will be assigned by the admin team.</p>

        <div className="confirm-grid">
          <div className="ticket">
            <div className="ticket-info">
              <p><strong>Table:</strong> {booking.table_summary || 'To be assigned by staff'}</p>
              <p><strong>Status:</strong> {booking.status_label || booking.status}</p>
              <p><strong>Source:</strong> {booking.booking_source_label || booking.booking_source || 'Unknown'}</p>
              <p><strong>Placed:</strong> {booking.reservation_card_status_label || 'Not placed'}</p>
              <p><strong>Name:</strong> {booking.customer_name || ''}</p>
              <p><strong>Email:</strong> {booking.customer_email || ''}</p>
              <p><strong>Phone:</strong> {booking.customer_phone || ''}</p>
              <p><strong>Date:</strong> {booking.booking_date || ''}</p>
              <p><strong>Time:</strong> {booking.start_time?.slice(0, 5)} - {booking.end_time?.slice(0, 5)}</p>
              <p><strong>Guests:</strong> {booking.number_of_guests || 0}</p>
              {booking.special_request && <p><strong>Note:</strong> {booking.special_request}</p>}
            </div>
          </div>

          <aside className="confirm-side">
            <h4>Booking Status</h4>
            <p>Your booking is available in your account for future updates.</p>
            <p>Table assignment and reservation-card placement are managed by staff.</p>
            <div className="confirm-links">
              {isLoggedInCustomer ? (
                <>
                  <Link to="/customer/dashboard" className="btn btn-outline-dark">Customer Dashboard</Link>
                  <Link to="/customer/my-bookings" className="btn btn-outline-secondary">Booking History</Link>
                </>
              ) : (
                <Link to="/auth/register" className="btn btn-outline-dark">Create Account</Link>
              )}
            </div>
          </aside>
        </div>

        {isLoggedInCustomer ? (
          <div className="confirm-links">
            <Link to="/customer/my-bookings" className="btn btn-bookings flex-fill">View My Bookings</Link>
            <Link to={`/customer/book-table?${rebookQuery}`} className="btn btn-outline-secondary flex-fill">Book Similar Again</Link>
          </div>
        ) : (
          <Link to="/customer/book-table" className="btn btn-bookings w-100">Book Another Reservation</Link>
        )}
      </div>
    </div>
  );
}
