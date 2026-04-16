import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { api } from '../../api/client';
import { Card } from '../../components/common/Card';

export function CustomerModifyBookingPage() {
  const { id } = useParams();
  const bookingId = Number(id);
  const navigate = useNavigate();
  const [form, setForm] = useState(null);
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    api.myBookings().then((res) => {
      const b = (res.bookings || []).find((x) => Number(x.booking_id) === bookingId);
      if (!b) {
        setError('Booking not found');
        return;
      }
      setForm({
        booking_date: b.booking_date,
        start_time: b.start_time?.slice(0, 5) || '12:00',
        end_time: b.end_time?.slice(0, 5) || '13:00',
        number_of_guests: b.number_of_guests || 2,
        special_request: b.special_request || '',
      });
    }).catch((err) => setError(err.message || 'Failed to load booking'));
  }, [bookingId]);

  if (!form) return <Card title="Modify Booking">{error ? <p className="error-text">{error}</p> : <p className="muted">Loading booking...</p>}</Card>;

  return (
    <div className="container modify-wrapper">
      <div className="modify-card">
        <h3 className="modify-title text-center"><i className="fa fa-pen-to-square text-warning"></i> Modify Booking</h3>
        {error && <div className="alert alert-danger">{error}</div>}
        {saved && <div className="alert alert-success">Booking request updated. Table assignment will be handled by staff.</div>}
        <form onSubmit={async (e) => {
          e.preventDefault();
          setSaved(false);
          try {
            await api.updateMyBooking(bookingId, form);
            setSaved(true);
          } catch (err) {
            setError(err.message || 'Update failed');
          }
        }}>
          <div className="row">
            <div className="col-md-6 mb-4"><label className="form-label"><i className="fa fa-calendar"></i> Select Date</label><input type="date" className="form-control modern-input" value={form.booking_date} onChange={(e) => setForm((p) => ({ ...p, booking_date: e.target.value }))} required /></div>
            <div className="col-md-6 mb-4"><label className="form-label"><i className="fa fa-clock"></i> Start Time</label><input type="time" className="form-control modern-input" value={form.start_time} onChange={(e) => setForm((p) => ({ ...p, start_time: e.target.value }))} required /></div>
            <div className="col-md-6 mb-4"><label className="form-label"><i className="fa fa-hourglass-end"></i> End Time</label><input type="time" className="form-control modern-input" value={form.end_time} onChange={(e) => setForm((p) => ({ ...p, end_time: e.target.value }))} required /></div>
            <div className="col-md-6 mb-4"><label className="form-label"><i className="fa fa-users"></i> Number of Guests</label><input type="number" min="1" className="form-control modern-input" value={form.number_of_guests} onChange={(e) => setForm((p) => ({ ...p, number_of_guests: Number(e.target.value) }))} required /></div>
            <div className="col-12 mb-4"><div className="alert alert-info mb-0"><i className="fa fa-circle-info"></i> Any booking changes return the reservation to the unassigned queue so staff can place it back onto the timeline.</div></div>
            <div className="col-12 mb-4"><label className="form-label"><i className="fa fa-note-sticky"></i> Special Request</label><textarea className="form-control modern-input" rows="3" value={form.special_request} onChange={(e) => setForm((p) => ({ ...p, special_request: e.target.value }))}></textarea></div>
          </div>
          <button className="btn btn-update w-100" type="submit"><i className="fa fa-save"></i> Update Booking</button>
        </form>
        <div className="text-center mt-4"><button className="btn btn-secondary btn-back" onClick={() => navigate('/customer/my-bookings')}>Back to My Bookings</button></div>
      </div>
    </div>
  );
}
