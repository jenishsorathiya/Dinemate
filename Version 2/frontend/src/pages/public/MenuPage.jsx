import { useEffect, useMemo, useState } from 'react';
import { api } from '../../api/client';
import { resolveAssetPath } from '../../utils/path';
import { Card } from '../../components/common/Card';

export function MenuPage() {
  const [items, setItems] = useState([]);
  const [error, setError] = useState('');
  useEffect(() => { api.menu().then((res) => setItems(res.items || [])).catch((err) => setError(err.message || 'Failed to load menu')); }, []);
  const grouped = useMemo(() => {
    const map = new Map();
    items.forEach((item) => { if (!map.has(item.category)) map.set(item.category, []); map.get(item.category).push(item); });
    return Array.from(map.entries());
  }, [items]);
  if (error) return <p className="error-text">{error}</p>;
  return (
    <Card title="Menu">
      {grouped.map(([category, rows]) => (
        <section key={category} className="section-block">
          <h3>{category}</h3>
          <div className="booking-list">
            {rows.map((item) => (
              <article key={item.id} className="booking-item menu-item-card">
                {item.image && <img className="menu-item-image" src={resolveAssetPath(item.image)} alt={item.name} />}
                <h4>{item.name}</h4><p className="muted">${Number(item.price).toFixed(2)}</p><p>{item.description || 'No description'}</p>
              </article>
            ))}
          </div>
        </section>
      ))}
    </Card>
  );
}
