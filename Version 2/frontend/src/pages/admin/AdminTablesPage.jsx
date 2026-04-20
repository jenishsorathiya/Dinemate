import { useEffect, useState } from 'react';
import { api } from '../../api/client';
import { AdminShell } from '../../components/admin/AdminShell';

export function AdminTablesPage() {
  const [areas, setAreas] = useState([]);
  const [tables, setTables] = useState([]);
  const [error, setError] = useState('');
  const [newArea, setNewArea] = useState({ name: '', table_number_start: '', table_number_end: '' });
  const [newTable, setNewTable] = useState({ area_id: '', table_number: '', capacity: 4, sort_order: 10, reservable: true });

  const load = async () => {
    try {
      setError('');
      const [areasRes, tablesRes] = await Promise.all([api.adminAreas(), api.adminTables()]);
      setAreas((areasRes.areas || []).map((row) => ({ ...row })));
      setTables((tablesRes.tables || []).map((row) => ({ ...row })));
      if (!newTable.area_id && (areasRes.areas || []).length) {
        setNewTable((prev) => ({ ...prev, area_id: String(areasRes.areas[0].area_id) }));
      }
    } catch (err) {
      setError(err.message || 'Failed to load table data');
    }
  };

  useEffect(() => { load(); }, []);

  const createArea = async (e) => {
    e.preventDefault();
    try {
      await api.adminCreateArea({
        name: newArea.name,
        table_number_start: newArea.table_number_start || null,
        table_number_end: newArea.table_number_end || null,
      });
      setNewArea({ name: '', table_number_start: '', table_number_end: '' });
      await load();
    } catch (err) {
      setError(err.message || 'Could not create area');
    }
  };

  const updateArea = async (area) => {
    try {
      await api.adminUpdateArea(area.area_id, {
        name: area.name,
        table_number_start: area.table_number_start || null,
        table_number_end: area.table_number_end || null,
      });
      await load();
    } catch (err) {
      setError(err.message || 'Could not update area');
    }
  };

  const createTable = async (e) => {
    e.preventDefault();
    try {
      await api.adminCreateTable({
        area_id: Number(newTable.area_id),
        table_number: newTable.table_number,
        capacity: Number(newTable.capacity),
        sort_order: Number(newTable.sort_order),
        reservable: !!newTable.reservable,
      });
      setNewTable((prev) => ({ ...prev, table_number: '', capacity: 4, sort_order: 10 }));
      await load();
    } catch (err) {
      setError(err.message || 'Could not create table');
    }
  };

  const updateTable = async (table) => {
    try {
      await api.adminUpdateTable(table.table_id, {
        table_number: table.table_number,
        capacity: Number(table.capacity),
        area_id: Number(table.area_id),
        sort_order: Number(table.sort_order || 10),
        reservable: Number(table.reservable) === 1 || table.reservable === true,
      });
      await load();
    } catch (err) {
      setError(err.message || 'Could not update table');
    }
  };

  return (
    <AdminShell title="Tables Management">
      <section className="admin-section">
        <div className="admin-section-head">
          <div>
            <h2>Areas and Table Layout</h2>
            <p>Manage table areas, capacities, and reservation availability.</p>
          </div>
          <button type="button" onClick={load}><i className="fa fa-rotate-right"></i> Refresh</button>
        </div>
        {error && <div className="alert alert-danger">{error}</div>}

        <div className="admin-split-grid">
          <article className="admin-panel">
            <h3>Create Area</h3>
            <form className="admin-form-grid" onSubmit={createArea}>
              <input value={newArea.name} onChange={(e) => setNewArea((prev) => ({ ...prev, name: e.target.value }))} placeholder="Area name (e.g. Main Bar)" required />
              <input type="number" value={newArea.table_number_start} onChange={(e) => setNewArea((prev) => ({ ...prev, table_number_start: e.target.value }))} placeholder="Start #" />
              <input type="number" value={newArea.table_number_end} onChange={(e) => setNewArea((prev) => ({ ...prev, table_number_end: e.target.value }))} placeholder="End #" />
              <button type="submit">Add Area</button>
            </form>
            <h3 style={{ marginTop: 20 }}>Edit Areas</h3>
            <div className="admin-stack">
              {areas.map((area) => (
                <div className="admin-card-row" key={`area-edit-${area.area_id}`}>
                  <div className="admin-form-grid two-col">
                    <input value={area.name || ''} onChange={(e) => setAreas((prev) => prev.map((row) => row.area_id === area.area_id ? { ...row, name: e.target.value } : row))} />
                    <input type="number" value={area.table_number_start || ''} onChange={(e) => setAreas((prev) => prev.map((row) => row.area_id === area.area_id ? { ...row, table_number_start: e.target.value } : row))} placeholder="Start #" />
                    <input type="number" value={area.table_number_end || ''} onChange={(e) => setAreas((prev) => prev.map((row) => row.area_id === area.area_id ? { ...row, table_number_end: e.target.value } : row))} placeholder="End #" />
                  </div>
                  <div className="admin-inline-actions">
                    <button type="button" onClick={() => updateArea(area)}>Save</button>
                    <button type="button" className="secondary" onClick={async () => { await api.adminDeleteArea(area.area_id); await load(); }}>Delete</button>
                  </div>
                </div>
              ))}
            </div>
          </article>

          <article className="admin-panel">
            <h3>Create Table</h3>
            <form className="admin-form-grid" onSubmit={createTable}>
              <select value={newTable.area_id} onChange={(e) => setNewTable((prev) => ({ ...prev, area_id: e.target.value }))} required>
                {areas.map((area) => <option key={`new-table-area-${area.area_id}`} value={area.area_id}>{area.name}</option>)}
              </select>
              <input value={newTable.table_number} onChange={(e) => setNewTable((prev) => ({ ...prev, table_number: e.target.value }))} placeholder="Table number" required />
              <input type="number" min="1" value={newTable.capacity} onChange={(e) => setNewTable((prev) => ({ ...prev, capacity: e.target.value }))} placeholder="Capacity" required />
              <input type="number" min="1" value={newTable.sort_order} onChange={(e) => setNewTable((prev) => ({ ...prev, sort_order: e.target.value }))} placeholder="Sort order" />
              <label className="admin-checkbox"><input type="checkbox" checked={!!newTable.reservable} onChange={(e) => setNewTable((prev) => ({ ...prev, reservable: e.target.checked }))} /> Reservable</label>
              <button type="submit">Add Table</button>
            </form>

            <h3 style={{ marginTop: 20 }}>Current Tables</h3>
            <div className="admin-table-wrap">
              <table className="admin-table">
                <thead>
                  <tr>
                    <th>Table</th>
                    <th>Area</th>
                    <th>Capacity</th>
                    <th>Order</th>
                    <th>Reservable</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {tables.map((table) => (
                    <tr key={`table-edit-${table.table_id}`}>
                      <td><input value={table.table_number || ''} onChange={(e) => setTables((prev) => prev.map((row) => row.table_id === table.table_id ? { ...row, table_number: e.target.value } : row))} /></td>
                      <td>
                        <select value={table.area_id || ''} onChange={(e) => setTables((prev) => prev.map((row) => row.table_id === table.table_id ? { ...row, area_id: Number(e.target.value) } : row))}>
                          {areas.filter((area) => Number(area.is_active) === 1).map((area) => <option key={`area-select-${table.table_id}-${area.area_id}`} value={area.area_id}>{area.name}</option>)}
                        </select>
                      </td>
                      <td><input type="number" min="1" value={table.capacity || 1} onChange={(e) => setTables((prev) => prev.map((row) => row.table_id === table.table_id ? { ...row, capacity: Number(e.target.value) } : row))} /></td>
                      <td><input type="number" min="1" value={table.sort_order || 10} onChange={(e) => setTables((prev) => prev.map((row) => row.table_id === table.table_id ? { ...row, sort_order: Number(e.target.value) } : row))} /></td>
                      <td><input type="checkbox" checked={Number(table.reservable) === 1 || table.reservable === true} onChange={(e) => setTables((prev) => prev.map((row) => row.table_id === table.table_id ? { ...row, reservable: e.target.checked ? 1 : 0 } : row))} /></td>
                      <td>
                        <div className="admin-inline-actions">
                          <button type="button" onClick={() => updateTable(table)}>Save</button>
                          <button type="button" className="secondary" onClick={async () => { await api.adminDeleteTable(table.table_id); await load(); }}>Delete</button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </article>
        </div>
      </section>
    </AdminShell>
  );
}
