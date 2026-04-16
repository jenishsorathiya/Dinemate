import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import { AdminShell } from '../../components/admin/AdminShell';

export function AdminCustomersPage() {
  const [search, setSearch] = useState('');
  const [rows, setRows] = useState([]);
  const [selectedProfileId, setSelectedProfileId] = useState(0);
  const [detail, setDetail] = useState({ profile: null, bookings: [] });
  const [linkableUsers, setLinkableUsers] = useState([]);
  const [linkUserId, setLinkUserId] = useState('');
  const [mergeTargetId, setMergeTargetId] = useState('');
  const [notes, setNotes] = useState('');
  const [error, setError] = useState('');

  const loadList = async (q = search) => {
    try {
      setError('');
      const res = await api.adminCustomerHistory(q);
      const customers = res.customers || [];
      setRows(customers);
      if (!selectedProfileId && customers.length) setSelectedProfileId(Number(customers[0].customer_profile_id));
    } catch (err) {
      setError(err.message || 'Failed to load customer history');
    }
  };

  const loadDetail = async (profileId) => {
    if (!profileId) return;
    try {
      const res = await api.adminCustomerProfileDetail(profileId);
      const profile = res.profile || null;
      setDetail({ profile, bookings: res.bookings || [] });
      setNotes(profile?.notes || '');
      setLinkUserId(profile?.linked_user_id ? String(profile.linked_user_id) : '');
    } catch (err) {
      setError(err.message || 'Failed to load customer detail');
    }
  };

  useEffect(() => {
    Promise.all([loadList(''), api.adminCustomerLinkableUsers().then((res) => setLinkableUsers(res.users || []))]).catch(() => {});
  }, []);

  useEffect(() => { loadDetail(selectedProfileId); }, [selectedProfileId]);

  return (
    <AdminShell title="Customer History">
      <section className="admin-section">
        <div className="admin-section-head">
          <div>
            <h2>Customer Profiles</h2>
            <p>Review linked accounts, booking history, notes, and profile merge actions.</p>
          </div>
          <div className="admin-filters">
            <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search customers" />
            <button type="button" onClick={() => loadList(search)}>Search</button>
            <button type="button" className="secondary" onClick={() => { setSearch(''); loadList(''); }}>Reset</button>
          </div>
        </div>
        {error && <div className="alert alert-danger">{error}</div>}

        <div className="admin-customer-layout">
          <article className="admin-panel">
            <h3>Profiles ({rows.length})</h3>
            <div className="admin-stack">
              {rows.map((profile) => (
                <button type="button" key={`profile-${profile.customer_profile_id}`} className={`admin-profile-pill ${Number(selectedProfileId) === Number(profile.customer_profile_id) ? 'is-active' : ''}`} onClick={() => setSelectedProfileId(Number(profile.customer_profile_id))}>
                  <span>{profile.name || 'Guest'} {profile.email ? `• ${profile.email}` : ''}</span>
                  <strong>{profile.total_bookings || 0} bookings</strong>
                </button>
              ))}
            </div>
          </article>

          <article className="admin-panel">
            <h3>Profile Detail</h3>
            {detail.profile ? (
              <>
                <div className="admin-list-item"><span>Name</span><strong>{detail.profile.name || 'Guest'}</strong></div>
                <div className="admin-list-item"><span>Email</span><strong>{detail.profile.email || 'None'}</strong></div>
                <div className="admin-list-item"><span>Phone</span><strong>{detail.profile.phone || 'None'}</strong></div>
                <div className="admin-list-item"><span>Linked Account</span><strong>{detail.profile.linked_user_name ? `${detail.profile.linked_user_name} (${detail.profile.linked_user_email})` : 'Not linked'}</strong></div>

                <h4 style={{ marginTop: 14 }}>Link or Unlink Account</h4>
                <div className="admin-inline-actions">
                  <select value={linkUserId} onChange={(e) => setLinkUserId(e.target.value)}>
                    <option value="">Select customer account</option>
                    {linkableUsers.map((u) => <option key={`linkable-${u.user_id}`} value={u.user_id}>{u.name} ({u.email})</option>)}
                  </select>
                  <button type="button" onClick={async () => {
                    if (!linkUserId) return;
                    await api.adminLinkCustomerProfile(detail.profile.customer_profile_id, { link_user_id: Number(linkUserId) });
                    await loadDetail(detail.profile.customer_profile_id);
                    await loadList(search);
                  }}>Link</button>
                  <button type="button" className="secondary" onClick={async () => {
                    await api.adminUnlinkCustomerProfile(detail.profile.customer_profile_id);
                    await loadDetail(detail.profile.customer_profile_id);
                    await loadList(search);
                  }}>Unlink</button>
                </div>

                <h4 style={{ marginTop: 14 }}>Notes</h4>
                <textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows="4"></textarea>
                <div style={{ marginTop: 8 }}>
                  <button type="button" onClick={async () => {
                    await api.adminUpdateCustomerProfileNotes(detail.profile.customer_profile_id, { notes });
                    await loadDetail(detail.profile.customer_profile_id);
                  }}>Save Notes</button>
                </div>

                <h4 style={{ marginTop: 14 }}>Merge Profile</h4>
                <div className="admin-inline-actions">
                  <select value={mergeTargetId} onChange={(e) => setMergeTargetId(e.target.value)}>
                    <option value="">Select target profile</option>
                    {rows.filter((row) => Number(row.customer_profile_id) !== Number(detail.profile.customer_profile_id)).map((row) => (
                      <option key={`merge-target-${row.customer_profile_id}`} value={row.customer_profile_id}>{row.name} #{row.customer_profile_id}</option>
                    ))}
                  </select>
                  <button type="button" onClick={async () => {
                    if (!mergeTargetId) return;
                    await api.adminMergeCustomerProfile(detail.profile.customer_profile_id, { target_profile_id: Number(mergeTargetId) });
                    const targetId = Number(mergeTargetId);
                    setMergeTargetId('');
                    await loadList(search);
                    setSelectedProfileId(targetId);
                  }}>Merge Into Target</button>
                </div>

                <h4 style={{ marginTop: 14 }}>Booking History ({detail.bookings.length})</h4>
                <div className="admin-table-wrap">
                  <table className="admin-table">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Guests</th>
                        <th>Source</th>
                        <th>Tables</th>
                      </tr>
                    </thead>
                    <tbody>
                      {detail.bookings.map((row) => (
                        <tr key={`profile-booking-${row.booking_id}`}>
                          <td>{row.booking_date}</td>
                          <td>{String(row.start_time || '').slice(0, 5)}-{String(row.end_time || '').slice(0, 5)}</td>
                          <td>{row.status}</td>
                          <td>{row.number_of_guests}</td>
                          <td>{row.booking_source || 'customer_account'}</td>
                          <td>{row.assigned_table_numbers || 'Unassigned'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </>
            ) : <p className="muted">Select a customer profile to view details.</p>}
          </article>
        </div>
      </section>
    </AdminShell>
  );
}
