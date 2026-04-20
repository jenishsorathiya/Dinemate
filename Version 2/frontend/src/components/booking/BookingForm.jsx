import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../state/AuthProvider';
import { api } from '../../api/client';

function toLocalDateKey(value = new Date()) {
  const date = value instanceof Date ? value : new Date(value);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function parseLocalDateKey(value, fallback = new Date()) {
  const [yearRaw, monthRaw, dayRaw] = String(value || '').split('-');
  const year = Number(yearRaw);
  const month = Number(monthRaw);
  const day = Number(dayRaw);
  if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) {
    return new Date(fallback.getFullYear(), fallback.getMonth(), fallback.getDate());
  }
  return new Date(year, month - 1, day);
}

export function BookingForm({ onSuccess, initialValues, showAccountPrompt = false, showRebookAlert = false }) {
  const { user } = useAuth();
  const todayKey = toLocalDateKey(new Date());
  const weekdayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  const timeOptions = useMemo(() => {
    const options = [];
    const start = new Date(2000, 0, 1, 10, 0, 0);
    const end = new Date(2000, 0, 1, 21, 0, 0);
    const cursor = new Date(start);
    while (cursor <= end) {
      options.push(`${String(cursor.getHours()).padStart(2, '0')}:${String(cursor.getMinutes()).padStart(2, '0')}`);
      cursor.setMinutes(cursor.getMinutes() + 30);
    }
    return options;
  }, []);
  const [step, setStep] = useState('booking');
  const [form, setForm] = useState({
    customer_name: initialValues.customer_name || user?.name || '',
    customer_email: initialValues.customer_email || user?.email || '',
    customer_phone: initialValues.customer_phone || user?.phone || '',
    booking_date: initialValues.booking_date || todayKey,
    start_time: initialValues.start_time || '12:00',
    number_of_guests: initialValues.number_of_guests || 2,
    special_request: initialValues.special_request || '',
  });
  const [phoneCountry, setPhoneCountry] = useState('+61');
  const [phoneLocal, setPhoneLocal] = useState('');
  const [error, setError] = useState('');
  const [availability, setAvailability] = useState([]);
  const [checkingAvailability, setCheckingAvailability] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [calendarMonth, setCalendarMonth] = useState(() => {
    const selected = parseLocalDateKey(initialValues.booking_date || todayKey);
    return new Date(selected.getFullYear(), selected.getMonth(), 1);
  });

  useEffect(() => {
    const raw = String(form.customer_phone || '').trim();
    const match = raw.match(/^(\+\d{1,3})\s*(.*)$/);
    if (match) {
      setPhoneCountry(match[1]);
      setPhoneLocal(match[2] || '');
    } else {
      setPhoneLocal(raw);
    }
  }, []);

  useEffect(() => {
    const selected = parseLocalDateKey(form.booking_date);
    if (!Number.isNaN(selected.getTime())) {
      setCalendarMonth(new Date(selected.getFullYear(), selected.getMonth(), 1));
    }
  }, [form.booking_date]);

  useEffect(() => {
    setAvailability([]);
  }, [form.booking_date, form.start_time, form.number_of_guests]);

  const summaryTimeEnd = useMemo(() => {
    const [h, m] = String(form.start_time || '12:00').split(':').map(Number);
    const d = new Date(2000, 0, 1, h, m || 0, 0);
    d.setMinutes(d.getMinutes() + 60);
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  }, [form.start_time]);

  const calendarDays = useMemo(() => {
    const firstOfMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1);
    const start = new Date(firstOfMonth);
    const mondayOffset = (firstOfMonth.getDay() + 6) % 7;
    start.setDate(1 - mondayOffset);
    const days = [];
    for (let i = 0; i < 42; i += 1) {
      const date = new Date(start);
      date.setDate(start.getDate() + i);
      const key = toLocalDateKey(date);
      days.push({
        key,
        label: date.getDate(),
        inCurrentMonth: date.getMonth() === calendarMonth.getMonth(),
        isToday: key === todayKey,
        isSelected: key === form.booking_date,
        isPast: key < todayKey,
      });
    }
    return days;
  }, [calendarMonth, form.booking_date, todayKey]);

  const selectedDate = useMemo(() => {
    const parsed = parseLocalDateKey(form.booking_date);
    if (Number.isNaN(parsed.getTime())) return new Date();
    return parsed;
  }, [form.booking_date]);

  const checkAvailability = async () => {
    setError('');
    setCheckingAvailability(true);
    try {
      const response = await api.tableAvailability({
        date: form.booking_date,
        startTime: `${form.start_time}:00`,
        guests: Number(form.number_of_guests || 1),
      });
      setAvailability(response.tables || []);
      if (!Array.isArray(response.tables) || response.tables.length === 0) {
        setError('No tables are currently available for this time.');
      }
    } catch (err) {
      setAvailability([]);
      setError(err.message || 'Could not check availability');
    } finally {
      setCheckingAvailability(false);
    }
  };

  return (
    <div className="booking-stage">
      <div className="booking-topbar">
        <div className="booking-heading">
          <h2>Make A Booking</h2>
        </div>
        <div className="booking-topbar-aside">
          <div className="booking-progress">
            <div className={`booking-step ${step === 'booking' ? 'is-active' : 'is-complete'}`}><span className="booking-step-dot">1</span><span>Booking</span></div>
            <div className={`booking-step ${step === 'details' ? 'is-active' : ''}`}><span className="booking-step-dot">2</span><span>Your Details</span></div>
          </div>
          {showAccountPrompt && (
            <div className="booking-account-card">
              <div className="booking-account-row is-inline">
                <p className="booking-account-title is-inline">Have an account? <Link className="booking-mini-link" to="/auth/login">Log in</Link> to continue.</p>
              </div>
            </div>
          )}
        </div>
      </div>

      {error && <div className="booking-alert"><i className="fa fa-circle-exclamation"></i> {error}</div>}
      {showRebookAlert && <div className="booking-alert booking-alert-rebook"><i className="fa fa-repeat"></i> Previous booking details have been prefilled for review.</div>}

      {step === 'booking' && (
        <div className="booking-layout">
          <div className="booking-left-stack">
            <section className="booking-calendar-panel" aria-label="Booking calendar">
              <div className="booking-calendar-top">
                <div className="booking-selected-date">
                  <div>
                    <div className="booking-selected-year">{selectedDate.getFullYear()}</div>
                    <div className="booking-selected-day">{selectedDate.toLocaleString('en-US', { weekday: 'short' })} {selectedDate.getDate()}</div>
                  </div>
                  <p>{selectedDate.toLocaleString('en-US', { month: 'long' })} {selectedDate.getDate()}</p>
                </div>
                <div className="booking-calendar-grid">
                  <div className="booking-calendar-header">
                    <button type="button" className="booking-calendar-nav" onClick={() => setCalendarMonth((prev) => new Date(prev.getFullYear(), prev.getMonth() - 1, 1))}>
                      <i className="fa fa-chevron-left"></i>
                    </button>
                    <div className="booking-calendar-title">{calendarMonth.toLocaleString('en-US', { month: 'long', year: 'numeric' })}</div>
                    <button type="button" className="booking-calendar-nav" onClick={() => setCalendarMonth((prev) => new Date(prev.getFullYear(), prev.getMonth() + 1, 1))}>
                      <i className="fa fa-chevron-right"></i>
                    </button>
                  </div>
                  <div className="booking-weekdays">
                    {weekdayNames.map((name) => <span key={name}>{name}</span>)}
                  </div>
                  <div className="booking-days">
                    {calendarDays.map((day) => (
                      <button
                        key={day.key}
                        type="button"
                        disabled={day.isPast}
                        className={`booking-day ${!day.inCurrentMonth ? 'is-muted' : ''} ${day.isToday ? 'is-today' : ''} ${day.isSelected ? 'is-selected' : ''}`}
                        onClick={() => setForm((p) => ({ ...p, booking_date: day.key }))}
                      >
                        {day.label}
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            </section>

            {showAccountPrompt && (
              <aside className="booking-account-card is-benefits" aria-label="Account benefits">
                <div className="booking-benefits-top">
                  <span className="booking-benefits-icon"><i className="fa fa-user"></i></span>
                  <div className="booking-benefits-copy">
                    <p className="booking-account-title">Unlock perks with an account.</p>
                  </div>
                </div>
                <ul className="booking-benefits-list">
                  <li><i className="fa fa-circle"></i><span>Easily manage and update your bookings</span></li>
                  <li><i className="fa fa-circle"></i><span>View your booking history.</span></li>
                  <li><i className="fa fa-circle"></i><span>Receive exclusive offers and gift vouchers</span></li>
                </ul>
                <div className="booking-account-links">
                  <Link className="booking-mini-btn" to="/auth/login">Log In</Link>
                  <Link className="booking-mini-btn is-primary" to="/auth/register">Register</Link>
                </div>
              </aside>
            )}
          </div>

          <div className="booking-card-stack">
            <section className="booking-step-card">
              <div className="booking-step-header">
                <div><h3>Booking</h3><p>Pick your party size and a preferred arrival time.</p></div>
                <span className="booking-card-pill"><i className="fa fa-clock"></i>60-minute request</span>
              </div>
              <div className="booking-field-grid">
                <div className="booking-field full-width">
                  <label>How many people are coming?</label>
                  <div className="booking-guests-control">
                    <button type="button" className="booking-guest-btn" onClick={() => setForm((p) => ({ ...p, number_of_guests: Math.max(1, Number(p.number_of_guests || 1) - 1) }))}>-</button>
                    <input type="number" className="booking-guests-display" min="1" value={form.number_of_guests} onChange={(e) => setForm((p) => ({ ...p, number_of_guests: Math.max(1, Number(e.target.value || 1)) }))} />
                    <button type="button" className="booking-guest-btn is-primary" onClick={() => setForm((p) => ({ ...p, number_of_guests: Number(p.number_of_guests || 1) + 1 }))}>+</button>
                  </div>
                </div>
                <div className="booking-field full-width">
                  <label>Preferred Time</label>
                  <select className="booking-select" value={form.start_time} onChange={(e) => setForm((p) => ({ ...p, start_time: e.target.value }))}>
                    {timeOptions.map((option) => (
                      <option key={option} value={option}>{new Date(`2000-01-01T${option}:00`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}</option>
                    ))}
                  </select>
                  <div className="booking-hint">We are open from 10:00 AM to 10:00 PM.</div>
                </div>
              </div>
              <div className="booking-summary">
                <div className="booking-summary-item"><span>Date</span><span>{selectedDate.toLocaleDateString('en-AU', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</span></div>
                <div className="booking-summary-item"><span>Time</span><span>{new Date(`2000-01-01T${form.start_time}:00`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }).toLowerCase()} to {new Date(`2000-01-01T${summaryTimeEnd}:00`).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }).toLowerCase()}</span></div>
                <div className="booking-summary-item"><span>Party</span><span>{form.number_of_guests} {Number(form.number_of_guests) === 1 ? 'guest' : 'guests'}</span></div>
              </div>
              {availability.length > 0 && <div className="booking-hint">Available tables: {availability.map((t) => t.table_number).join(', ')}</div>}
              <div className="booking-actions">
                <button type="button" className="booking-btn booking-btn-secondary" onClick={checkAvailability} disabled={checkingAvailability}>
                  {checkingAvailability ? 'Checking...' : 'Check Availability'}
                </button>
                <button type="button" className="booking-btn booking-btn-primary" onClick={() => setStep('details')}>Next</button>
              </div>
            </section>
          </div>
        </div>
      )}

      {step === 'details' && (
        <section className="booking-step-card">
          <div className="booking-step-header">
            <div><h3>Your Details</h3><p>We will use these details to confirm your request and contact you if needed.</p></div>
            <span className="booking-card-pill"><i className="fa fa-circle-info"></i>Table assigned by staff</span>
          </div>
          <form onSubmit={async (e) => {
            e.preventDefault();
            setError('');
            if (!phoneLocal.trim()) {
              setError('Please enter your phone number.');
              return;
            }
            const payload = {
              ...form,
              customer_phone: `${phoneCountry} ${phoneLocal.trim()}`.trim(),
            };
            setSubmitting(true);
            try {
              const res = await api.createBooking(payload);
              onSuccess?.(res.booking);
            } catch (err) {
              setError(err.message || 'Failed to create booking');
            } finally {
              setSubmitting(false);
            }
          }}>
            <div className="booking-field-grid">
              <div className="booking-field full-width"><label>Name</label><input className="booking-input" value={form.customer_name} onChange={(e) => setForm((p) => ({ ...p, customer_name: e.target.value }))} required /></div>
              <div className="booking-field"><label>Email</label><input type="email" className="booking-input" value={form.customer_email} onChange={(e) => setForm((p) => ({ ...p, customer_email: e.target.value }))} required /></div>
              <div className="booking-field">
                <label>Phone Number</label>
                <div className="booking-phone-row">
                  <select className="booking-select booking-phone-country" value={phoneCountry} onChange={(e) => setPhoneCountry(e.target.value)}>
                    <option value="+61">AU +61</option>
                    <option value="+64">NZ +64</option>
                    <option value="+1">US +1</option>
                    <option value="+44">UK +44</option>
                    <option value="+65">SG +65</option>
                    <option value="+91">IN +91</option>
                    <option value="+971">AE +971</option>
                  </select>
                  <input className="booking-input" value={phoneLocal} onChange={(e) => setPhoneLocal(e.target.value)} required />
                </div>
              </div>
              <div className="booking-field full-width"><label>Add Note or Special Requirements</label><textarea className="booking-textarea" value={form.special_request} onChange={(e) => setForm((p) => ({ ...p, special_request: e.target.value }))}></textarea></div>
            </div>
            <div className="booking-actions">
              <button type="button" className="booking-btn booking-btn-secondary" onClick={() => setStep('booking')}>Back</button>
              <button type="submit" className="booking-btn booking-btn-primary" disabled={submitting}>{submitting ? 'Submitting...' : 'Confirm Booking'}</button>
            </div>
          </form>
        </section>
      )}
    </div>
  );
}
