import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api/client';
import { Card } from '../../components/common/Card';

export function CustomerProfilePage() {
  const [profile, setProfile] = useState(null);
  const [bookings, setBookings] = useState([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [passwordForm, setPasswordForm] = useState({ current_password: '', new_password: '', confirm_password: '' });
  useEffect(() => {
    Promise.all([api.customerProfile(), api.myBookings()])
      .then(([profileRes, bookingsRes]) => {
        setProfile(profileRes.profile || {});
        setBookings(bookingsRes.bookings || []);
      })
      .catch((err) => setError(err.message || 'Failed to load profile'));
  }, []);
  if (!profile) return <Card title="My Profile"><p className="muted">Loading profile...</p></Card>;

  const completedCount = bookings.filter((b) => String(b.status || '').toLowerCase() === 'completed').length;
  const activeCount = bookings.filter((b) => ['pending', 'confirmed'].includes(String(b.status || '').toLowerCase())).length;
  const lastBookingDate = bookings[0]?.booking_date || '';

  return (
    <div className="container profile-wrapper">
      <div className="profile-layout">
        <div className="profile-card">
          <div className="profile-header"><h2><i className="fa fa-user text-warning"></i> My Profile And Preferences</h2><p>Manage your profile, preferences, and password.</p></div>
          {error && <div className="alert alert-danger">{error}</div>}
          {success && <div className="alert alert-success">{success}</div>}

          <form onSubmit={async (e) => {
            e.preventDefault();
            setError('');
            setSuccess('');
            try {
              const res = await api.updateCustomerProfile(profile);
              setProfile(res.profile || profile);
              setSuccess('Profile and preferences updated.');
            } catch (err) {
              setError(err.message || 'Update failed');
            }
          }}>
            <div className="profile-grid">
              <div className="profile-field"><label>Name</label><input className="profile-input" value={profile.name || ''} onChange={(e) => setProfile((p) => ({ ...p, name: e.target.value }))} required /></div>
              <div className="profile-field"><label>Phone</label><input className="profile-input" value={profile.phone || ''} onChange={(e) => setProfile((p) => ({ ...p, phone: e.target.value }))} required /></div>
              <div className="profile-field full-width"><label>Email</label><input className="profile-input" type="email" value={profile.email || ''} onChange={(e) => setProfile((p) => ({ ...p, email: e.target.value }))} required /></div>
              <div className="profile-field"><label>Seating Preference</label><select className="profile-select" value={profile.seating_preference || ''} onChange={(e) => setProfile((p) => ({ ...p, seating_preference: e.target.value }))}><option value="">No preference</option><option value="window">Window</option><option value="quiet_corner">Quiet corner</option><option value="outdoor">Outdoor</option><option value="bar">Bar seating</option><option value="family_friendly">Family-friendly area</option></select></div>
              <div className="profile-field"><label>Preferred Booking Time</label><select className="profile-select" value={profile.preferred_booking_time || ''} onChange={(e) => setProfile((p) => ({ ...p, preferred_booking_time: e.target.value }))}><option value="">No preference</option><option value="breakfast">Breakfast</option><option value="lunch">Lunch</option><option value="afternoon">Afternoon</option><option value="dinner">Dinner</option></select></div>
              <div className="profile-field full-width"><label>Dietary Or Allergy Notes</label><textarea className="profile-textarea" value={profile.dietary_notes || ''} onChange={(e) => setProfile((p) => ({ ...p, dietary_notes: e.target.value }))}></textarea></div>
              <div className="profile-field full-width"><label>Additional Booking Notes</label><textarea className="profile-textarea" value={profile.notes || ''} onChange={(e) => setProfile((p) => ({ ...p, notes: e.target.value, customer_notes: e.target.value }))}></textarea></div>
            </div>
            <div className="profile-section">
              <h3>Reminder Preferences</h3>
              <div className="toggle-row" style={{ marginTop: 16 }}>
                <label className="toggle-item"><div className="toggle-copy"><strong>Email reminders</strong><span>Use my saved email address for reminders.</span></div><input type="checkbox" checked={Number(profile.email_reminders_enabled ?? 1) === 1} onChange={(e) => setProfile((p) => ({ ...p, email_reminders_enabled: e.target.checked ? 1 : 0 }))} /></label>
                <label className="toggle-item"><div className="toggle-copy"><strong>SMS reminders</strong><span>Store my preference for text reminders.</span></div><input type="checkbox" checked={Number(profile.sms_reminders_enabled ?? 0) === 1} onChange={(e) => setProfile((p) => ({ ...p, sms_reminders_enabled: e.target.checked ? 1 : 0 }))} /></label>
              </div>
            </div>
            <div className="profile-actions"><Link className="profile-btn profile-btn-secondary" to="/customer/dashboard" style={{ textDecoration: 'none' }}>Back To Dashboard</Link><button type="submit" className="profile-btn profile-btn-primary">Save Profile</button></div>
          </form>

          <div className="profile-section">
            <h3>Account Security</h3>
            <p style={{ marginTop: 8, color: 'var(--dm-text-muted)' }}>Change your password without leaving the customer portal.</p>
            <form style={{ marginTop: 18 }} onSubmit={async (e) => {
              e.preventDefault();
              setError('');
              setSuccess('');
              try {
                await api.updateCustomerPassword(passwordForm);
                setSuccess('Password updated successfully.');
                setPasswordForm({ current_password: '', new_password: '', confirm_password: '' });
              } catch (err) {
                setError(err.message || 'Failed to update password');
              }
            }}>
              <div className="profile-grid">
                <div className="profile-field full-width"><label>Current Password</label><input type="password" className="profile-input" value={passwordForm.current_password} onChange={(e) => setPasswordForm((p) => ({ ...p, current_password: e.target.value }))} /></div>
                <div className="profile-field"><label>New Password</label><input type="password" className="profile-input" value={passwordForm.new_password} onChange={(e) => setPasswordForm((p) => ({ ...p, new_password: e.target.value }))} /></div>
                <div className="profile-field"><label>Confirm New Password</label><input type="password" className="profile-input" value={passwordForm.confirm_password} onChange={(e) => setPasswordForm((p) => ({ ...p, confirm_password: e.target.value }))} /></div>
              </div>
              <div className="profile-actions"><button type="submit" className="profile-btn profile-btn-primary">Update Password</button></div>
            </form>
          </div>
        </div>

        <aside className="profile-side-card">
          <h3>Profile Snapshot</h3>
          <p>Profile and booking summary.</p>
          <div className="stats-list" style={{ marginTop: 18 }}>
            <div className="stat-item"><strong>Completed Visits</strong><span>{completedCount} completed bookings in your customer history.</span></div>
            <div className="stat-item"><strong>Active Bookings</strong><span>{activeCount} pending or confirmed reservations.</span></div>
            <div className="stat-item"><strong>Last Booking</strong><span>{lastBookingDate || 'None'}</span></div>
            <div className="stat-item"><strong>Customer Profile ID</strong><span>{profile.customer_profile_id ? `#${profile.customer_profile_id}` : 'Pending'}</span></div>
          </div>
        </aside>
      </div>
    </div>
  );
}
