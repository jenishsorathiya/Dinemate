import { AdminShell } from '../../components/admin/AdminShell';

export function AdminUIKitPage() {
  return (
    <AdminShell title="UI Kit">
      <section className="admin-section">
        <h2>Component Library</h2>
        <p className="muted">Reference primitives used across admin and customer modules.</p>
        <div className="admin-split-grid">
          <article className="admin-panel">
            <h3>Buttons</h3>
            <div className="admin-inline-actions">
              <button type="button">Primary</button>
              <button type="button" className="secondary">Secondary</button>
            </div>
          </article>
          <article className="admin-panel">
            <h3>Status Tags</h3>
            <div className="admin-inline-actions">
              <span className="status-tag pending">pending</span>
              <span className="status-tag confirmed">confirmed</span>
              <span className="status-tag completed">completed</span>
              <span className="status-tag cancelled">cancelled</span>
              <span className="status-tag no_show">no_show</span>
            </div>
          </article>
        </div>
      </section>
    </AdminShell>
  );
}
