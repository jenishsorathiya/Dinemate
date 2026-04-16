import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import { AdminShell } from '../../components/admin/AdminShell';

function localDateKey(value = new Date()) {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function AdminBookingsPage() {
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const load = async () => {
    try {
      setError('');
      const res = await api.adminBookings({});
      setRows(res.bookings || []);
    } catch (err) {
      setError(err.message || 'Failed to load bookings');
    }
  };

  useEffect(() => { load(); }, []);

  const filtered = rows.filter((row) => {
    const status = String(row.status || '').toLowerCase();
    if (statusFilter !== 'all' && status !== statusFilter) return false;
    if (dateFrom && String(row.booking_date || '') < dateFrom) return false;
    if (dateTo && String(row.booking_date || '') > dateTo) return false;
    if (search.trim()) {
      const hay = `${row.customer_name || ''} ${row.booking_date || ''} ${row.booking_source || ''} ${row.special_request || ''}`.toLowerCase();
      if (!hay.includes(search.toLowerCase())) return false;
    }
    return true;
  });

  const pendingQueue = filtered.filter((row) => String(row.status || '').toLowerCase() === 'pending');
  const today = localDateKey();
  const todayActive = filtered.filter((row) => row.booking_date === today && ['pending', 'confirmed'].includes(String(row.status || '').toLowerCase()));
  const serviceOutcomes = filtered.filter((row) => row.booking_date === today && ['completed', 'cancelled', 'no_show'].includes(String(row.status || '').toLowerCase()));

  const handlePending = async (bookingId, approve) => {
    try {
      if (approve) await api.adminConfirmPendingBooking(bookingId);
      else await api.adminCancelBooking(bookingId);
      await load();
    } catch (err) {
      setError(err.message || 'Could not update pending booking');
    }
  };

  const handleStatus = async (bookingId, status) => {
    try {
      await api.adminSetBookingStatus(bookingId, { status });
      await load();
    } catch (err) {
      setError(err.message || 'Could not update booking status');
    }
  };

  return (
    <AdminShell title="Bookings Management">
      <section className="admin-section">
        <div className="admin-section-head">
          <div>
            <h2>Booking Operations</h2>
            <p>Approve requests, monitor today&apos;s service, and review outcomes.</p>
          </div>
          <div className="admin-filters">
            <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
              <option value="no_show">No-show</option>
            </select>
            <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
            <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
            <input type="text" placeholder="Search name or source..." value={search} onChange={(e) => setSearch(e.target.value)} />
            <button type="button" onClick={load}><i className="fa fa-rotate-right"></i> Refresh</button>
          </div>
        </div>
        {error && <div className="alert alert-danger">{error}</div>}

        <div className="admin-metric-grid">
          <article className="admin-metric-card"><div className="admin-metric-label"><i className="fa fa-hourglass-half"></i> Pending Queue</div><div className="admin-metric-value">{pendingQueue.length}</div></article>
          <article className="admin-metric-card"><div className="admin-metric-label"><i className="fa fa-calendar-day"></i> Today Active</div><div className="admin-metric-value">{todayActive.length}</div></article>
          <article className="admin-metric-card"><div className="admin-metric-label"><i className="fa fa-check-double"></i> Today Outcomes</div><div className="admin-metric-value">{serviceOutcomes.length}</div></article>
          <article className="admin-metric-card"><div className="admin-metric-label"><i className="fa fa-list"></i> Filtered Total</div><div className="admin-metric-value">{filtered.length}</div></article>
        </div>

        <div className="admin-split-grid">
          <article className="admin-panel">
            <h3>Pending Requests</h3>
            {pendingQueue.length ? pendingQueue.slice(0, 12).map((row) => (
              <div className="admin-card-row" key={`pending-manage-${row.booking_id}`}>
                <div>
                  <strong>{row.customer_name || 'Guest'}</strong>
                  <p>{row.booking_date} • {String(row.start_time || '').slice(0, 5)}-{String(row.end_time || '').slice(0, 5)} • {row.number_of_guests} guests</p>
                </div>
                <div className="admin-inline-actions">
                  <button type="button" onClick={() => handlePending(row.booking_id, true)}>Approve</button>
                  <button type="button" className="secondary" onClick={() => handlePending(row.booking_id, false)}>Reject</button>
                </div>
              </div>
            )) : <p className="muted">No pending requests.</p>}
          </article>
          <article className="admin-panel">
            <h3>Today&apos;s Service Outcomes</h3>
            {serviceOutcomes.length ? serviceOutcomes.map((row) => (
              <div className="admin-list-item" key={`outcome-${row.booking_id}`}>
                <span>{row.customer_name || 'Guest'} ({String(row.start_time || '').slice(0, 5)})</span>
                <strong>{String(row.status || '').replace('_', ' ')}</strong>
              </div>
            )) : <p className="muted">No completed/cancelled/no-show outcomes yet.</p>}
          </article>
        </div>

        <div className="admin-table-wrap">
          <table className="admin-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Customer</th>
                <th>Guests</th>
                <th>Assigned Tables</th>
                <th>Status</th>
                <th>Source</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((row) => {
                const status = String(row.status || '').toLowerCase();
                const assigned = Array.isArray(row.assigned_table_numbers) && row.assigned_table_numbers.length ? row.assigned_table_numbers.join(', ') : 'Unassigned';
                return (
                  <tr key={`booking-${row.booking_id}`}>
                    <td>{row.booking_date}</td>
                    <td>{String(row.start_time || '').slice(0, 5)}-{String(row.end_time || '').slice(0, 5)}</td>
                    <td>{row.customer_name || 'Guest'}</td>
                    <td>{row.number_of_guests}</td>
                    <td>{assigned}</td>
                    <td><span className={`status-tag ${status || 'pending'}`}>{status}</span></td>
                    <td>{row.booking_source || 'customer_account'}</td>
                    <td>
                      <div className="admin-inline-actions">
                        {status === 'pending' && <button type="button" onClick={() => handlePending(row.booking_id, true)}>Confirm</button>}
                        {status === 'pending' && <button type="button" className="secondary" onClick={() => handlePending(row.booking_id, false)}>Reject</button>}
                        {status === 'confirmed' && <button type="button" onClick={() => handleStatus(row.booking_id, 'completed')}>Complete</button>}
                        {status === 'confirmed' && <button type="button" className="secondary" onClick={() => handleStatus(row.booking_id, 'no_show')}>No-show</button>}
                        {status === 'confirmed' && <button type="button" className="secondary" onClick={() => handleStatus(row.booking_id, 'cancelled')}>Cancel</button>}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>
    </AdminShell>
  );
}
