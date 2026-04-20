import { useEffect, useMemo, useState } from 'react';
import { AdminShell } from '../../components/admin/AdminShell';
import { api } from '../../api/client';
import { resolveAssetPath } from '../../utils/path';

const DEFAULT_CATEGORIES = ['Small Plates', 'Large Plates', 'House Specials', 'Burgers', 'Sides', 'Kiddies', 'Desserts', 'Drinks'];

const emptyForm = {
  id: null,
  name: '',
  description: '',
  price: '',
  category: DEFAULT_CATEGORIES[0],
  image: '',
  dietary_info: '',
  is_available: true,
};

export function AdminMenuPage() {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await api.adminMenuItems();
      setItems(res.items || []);
    } catch (err) {
      setError(err.message || 'Failed to load menu items');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const categoryOptions = useMemo(() => {
    const unique = new Set(DEFAULT_CATEGORIES);
    items.forEach((item) => {
      const value = String(item.category || '').trim();
      if (value) unique.add(value);
    });
    return Array.from(unique.values());
  }, [items]);

  const groupedItems = useMemo(() => {
    const map = new Map();
    items.forEach((item) => {
      const category = String(item.category || 'Uncategorized');
      if (!map.has(category)) {
        map.set(category, []);
      }
      map.get(category).push(item);
    });
    return Array.from(map.entries()).sort((left, right) => left[0].localeCompare(right[0]));
  }, [items]);

  const resetForm = () => {
    setForm({
      ...emptyForm,
      category: categoryOptions[0] || DEFAULT_CATEGORIES[0],
    });
  };

  const onSubmit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        name: form.name.trim(),
        description: form.description.trim(),
        price: Number(form.price),
        category: form.category.trim(),
        image: form.image.trim(),
        dietary_info: form.dietary_info.trim(),
        is_available: !!form.is_available,
      };
      if (form.id) {
        await api.adminUpdateMenuItem(form.id, payload);
      } else {
        await api.adminCreateMenuItem(payload);
      }
      resetForm();
      await load();
    } catch (err) {
      setError(err.message || 'Could not save menu item');
    } finally {
      setSaving(false);
    }
  };

  const editItem = (item) => {
    setForm({
      id: Number(item.id),
      name: String(item.name || ''),
      description: String(item.description || ''),
      price: String(item.price ?? ''),
      category: String(item.category || categoryOptions[0] || DEFAULT_CATEGORIES[0]),
      image: String(item.image || ''),
      dietary_info: String(item.dietary_info || ''),
      is_available: Number(item.is_available) === 1 || item.is_available === true,
    });
  };

  const deleteItem = async (itemId) => {
    if (!window.confirm('Delete this menu item? This cannot be undone.')) return;
    setError('');
    try {
      await api.adminDeleteMenuItem(itemId);
      if (Number(form.id) === Number(itemId)) {
        resetForm();
      }
      await load();
    } catch (err) {
      setError(err.message || 'Could not delete menu item');
    }
  };

  const totalItems = items.length;
  const availableItems = items.filter((item) => Number(item.is_available) === 1 || item.is_available === true).length;
  const unavailableItems = totalItems - availableItems;

  return (
    <AdminShell title="Menu Management">
      <section className="admin-section">
        <div className="admin-section-head">
          <div>
            <h2>Menu Management</h2>
            <p>Create, update, and remove menu items shown to customers.</p>
          </div>
          <div className="admin-inline-actions">
            <button type="button" className="secondary" onClick={resetForm}>New Item</button>
            <button type="button" onClick={load}><i className="fa fa-rotate-right"></i> Refresh</button>
          </div>
        </div>

        {error && <div className="alert alert-danger">{error}</div>}

        <div className="admin-metric-grid">
          <article className="admin-metric-card"><div className="admin-metric-label"><i className="fa fa-utensils"></i> Total Items</div><div className="admin-metric-value">{totalItems}</div></article>
          <article className="admin-metric-card"><div className="admin-metric-label"><i className="fa fa-circle-check"></i> Available</div><div className="admin-metric-value">{availableItems}</div></article>
          <article className="admin-metric-card"><div className="admin-metric-label"><i className="fa fa-circle-xmark"></i> Unavailable</div><div className="admin-metric-value">{unavailableItems}</div></article>
          <article className="admin-metric-card"><div className="admin-metric-label"><i className="fa fa-layer-group"></i> Categories</div><div className="admin-metric-value">{groupedItems.length}</div></article>
        </div>

        <div className="admin-split-grid">
          <article className="admin-panel">
            <h3>{form.id ? 'Edit Menu Item' : 'Add Menu Item'}</h3>
            <form className="admin-form-grid" onSubmit={onSubmit}>
              <input
                value={form.name}
                onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))}
                placeholder="Item name"
                required
              />
              <input
                type="number"
                min="0.01"
                step="0.01"
                value={form.price}
                onChange={(event) => setForm((prev) => ({ ...prev, price: event.target.value }))}
                placeholder="Price"
                required
              />
              <select
                value={form.category}
                onChange={(event) => setForm((prev) => ({ ...prev, category: event.target.value }))}
                required
              >
                {categoryOptions.map((category) => (
                  <option key={`menu-category-${category}`} value={category}>{category}</option>
                ))}
              </select>
              <input
                value={form.dietary_info}
                onChange={(event) => setForm((prev) => ({ ...prev, dietary_info: event.target.value }))}
                placeholder="Dietary info (optional)"
              />
              <input
                value={form.image}
                onChange={(event) => setForm((prev) => ({ ...prev, image: event.target.value }))}
                placeholder="Image path or URL (optional)"
              />
              <label className="admin-checkbox">
                <input
                  type="checkbox"
                  checked={!!form.is_available}
                  onChange={(event) => setForm((prev) => ({ ...prev, is_available: event.target.checked }))}
                />
                Available to customers
              </label>
              <textarea
                style={{ gridColumn: '1 / -1' }}
                rows="4"
                value={form.description}
                onChange={(event) => setForm((prev) => ({ ...prev, description: event.target.value }))}
                placeholder="Description (optional)"
              ></textarea>
              <div className="admin-inline-actions" style={{ gridColumn: '1 / -1' }}>
                {form.id ? <button type="button" className="secondary" onClick={resetForm}>Cancel Edit</button> : null}
                <button type="submit" disabled={saving}>
                  {saving ? 'Saving...' : (form.id ? 'Save Changes' : 'Create Item')}
                </button>
              </div>
            </form>
          </article>

          <article className="admin-panel">
            <h3>Menu Items</h3>
            {loading ? <p className="muted">Loading menu items...</p> : null}
            {!loading && groupedItems.length === 0 ? <p className="muted">No menu items yet.</p> : null}
            {!loading && groupedItems.length > 0 ? (
              <div className="admin-stack">
                {groupedItems.map(([category, rows]) => (
                  <div key={`menu-group-${category}`} className="admin-table-wrap">
                    <table className="admin-table">
                      <thead>
                        <tr>
                          <th colSpan={6}>{category} ({rows.length})</th>
                        </tr>
                        <tr>
                          <th>Item</th>
                          <th>Price</th>
                          <th>Dietary</th>
                          <th>Status</th>
                          <th>Image</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {rows.map((item) => {
                          const available = Number(item.is_available) === 1 || item.is_available === true;
                          return (
                            <tr key={`menu-item-${item.id}`}>
                              <td>
                                <strong>{item.name}</strong>
                                {item.description ? <div className="muted">{item.description}</div> : null}
                              </td>
                              <td>${Number(item.price || 0).toFixed(2)}</td>
                              <td>{item.dietary_info || '-'}</td>
                              <td><span className={`status-tag ${available ? 'confirmed' : 'cancelled'}`}>{available ? 'available' : 'hidden'}</span></td>
                              <td>
                                {item.image ? (
                                  <a href={resolveAssetPath(item.image)} target="_blank" rel="noreferrer">Preview</a>
                                ) : '-'}
                              </td>
                              <td>
                                <div className="admin-inline-actions">
                                  <button type="button" onClick={() => editItem(item)}>Edit</button>
                                  <button type="button" className="secondary" onClick={() => deleteItem(item.id)}>Delete</button>
                                </div>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                ))}
              </div>
            ) : null}
          </article>
        </div>
      </section>
    </AdminShell>
  );
}
