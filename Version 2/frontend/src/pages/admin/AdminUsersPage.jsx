import { useEffect, useState } from 'react';
import { useAuth } from '../../state/AuthProvider';
import { api } from '../../api/client';
import { AdminShell } from '../../components/admin/AdminShell';

export function AdminUsersPage() {
  const { user } = useAuth();
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const [newUser, setNewUser] = useState({ name: '', email: '', phone: '', role: 'customer', password: '' });

  const load = async () => {
    try {
      setError('');
      const res = await api.adminUsers();
      setRows((res.users || []).map((row) => ({ ...row, edit_password: '' })));
    } catch (err) {
      setError(err.message || 'Failed to load users');
    }
  };

  useEffect(() => { load(); }, []);

  return (
    <AdminShell title="Manage Users">
      <section className="admin-section">
        <div className="admin-section-head">
          <div>
            <h2>User Accounts</h2>
            <p>Create, update, disable, promote, or remove registered users.</p>
          </div>
          <button type="button" onClick={load}><i className="fa fa-rotate-right"></i> Refresh</button>
        </div>
        {error && <div className="alert alert-danger">{error}</div>}

        <article className="admin-panel">
          <h3>Add User</h3>
          <form className="admin-form-grid" onSubmit={async (e) => {
            e.preventDefault();
            try {
              await api.adminCreateUser(newUser);
              setNewUser({ name: '', email: '', phone: '', role: 'customer', password: '' });
              await load();
            } catch (err) {
              setError(err.message || 'Could not create user');
            }
          }}>
            <input value={newUser.name} onChange={(e) => setNewUser((prev) => ({ ...prev, name: e.target.value }))} placeholder="Name" required />
            <input type="email" value={newUser.email} onChange={(e) => setNewUser((prev) => ({ ...prev, email: e.target.value }))} placeholder="Email" required />
            <input value={newUser.phone} onChange={(e) => setNewUser((prev) => ({ ...prev, phone: e.target.value }))} placeholder="Phone" />
            <select value={newUser.role} onChange={(e) => setNewUser((prev) => ({ ...prev, role: e.target.value }))}>
              <option value="customer">Customer</option>
              <option value="admin">Admin</option>
            </select>
            <input type="password" value={newUser.password} onChange={(e) => setNewUser((prev) => ({ ...prev, password: e.target.value }))} placeholder="Password (min 6 chars)" required />
            <button type="submit">Create User</button>
          </form>
        </article>

        <article className="admin-panel" style={{ marginTop: 16 }}>
          <h3>Manage Existing Users</h3>
          <div className="admin-table-wrap">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Role</th>
                  <th>Disabled</th>
                  <th>New Password</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((entry) => (
                  <tr key={`user-${entry.user_id}`}>
                    <td><input value={entry.name || ''} onChange={(e) => setRows((prev) => prev.map((row) => row.user_id === entry.user_id ? { ...row, name: e.target.value } : row))} /></td>
                    <td><input value={entry.email || ''} onChange={(e) => setRows((prev) => prev.map((row) => row.user_id === entry.user_id ? { ...row, email: e.target.value } : row))} /></td>
                    <td><input value={entry.phone || ''} onChange={(e) => setRows((prev) => prev.map((row) => row.user_id === entry.user_id ? { ...row, phone: e.target.value } : row))} /></td>
                    <td>
                      <select value={entry.role || 'customer'} onChange={(e) => setRows((prev) => prev.map((row) => row.user_id === entry.user_id ? { ...row, role: e.target.value } : row))}>
                        <option value="customer">customer</option>
                        <option value="admin">admin</option>
                      </select>
                    </td>
                    <td><input type="checkbox" checked={Number(entry.is_disabled || 0) === 1} onChange={(e) => setRows((prev) => prev.map((row) => row.user_id === entry.user_id ? { ...row, is_disabled: e.target.checked ? 1 : 0 } : row))} /></td>
                    <td><input type="password" placeholder="Optional" value={entry.edit_password || ''} onChange={(e) => setRows((prev) => prev.map((row) => row.user_id === entry.user_id ? { ...row, edit_password: e.target.value } : row))} /></td>
                    <td>
                      <div className="admin-inline-actions">
                        <button type="button" onClick={async () => {
                          try {
                            await api.adminUpdateUser(entry.user_id, {
                              name: entry.name,
                              email: entry.email,
                              phone: entry.phone,
                              role: entry.role,
                              is_disabled: Number(entry.is_disabled || 0) === 1 ? 1 : 0,
                              ...(entry.edit_password ? { password: entry.edit_password } : {}),
                            });
                            await load();
                          } catch (err) {
                            setError(err.message || 'Could not update user');
                          }
                        }}>Save</button>
                        <button type="button" className="secondary" disabled={Number(user?.user_id) === Number(entry.user_id)} onClick={async () => {
                          try {
                            await api.adminDeleteUser(entry.user_id);
                            await load();
                          } catch (err) {
                            setError(err.message || 'Could not delete user');
                          }
                        }}>Delete</button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </article>
      </section>
    </AdminShell>
  );
}
