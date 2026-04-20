import { useAuth } from '../../state/AuthProvider';
import { useNavigate } from 'react-router-dom';
import { BookingForm } from '../../components/booking/BookingForm';

export function CustomerBookTablePage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
  const initialValues = {
    booking_date: params.get('date') || '',
    start_time: params.get('time') || '',
    number_of_guests: Number(params.get('guests') || 0) || 2,
    special_request: params.get('special') || '',
  };

  return (
    <div className="container booking-shell">
      <BookingForm
        initialValues={initialValues}
        showAccountPrompt={user?.role !== 'customer'}
        showRebookAlert={params.has('rebook')}
        onSuccess={(booking) => navigate(`/customer/booking-confirmation?id=${booking.booking_id}${booking.guest_access_token ? `&token=${encodeURIComponent(booking.guest_access_token)}` : ''}`)}
      />
    </div>
  );
}
