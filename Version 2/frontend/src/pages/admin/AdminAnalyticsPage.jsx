import { useEffect, useMemo, useState } from 'react';
import { api } from '../../api/client';
import { AdminShell } from '../../components/admin/AdminShell';

function formatHourLabel(hourSlot) {
  if (!hourSlot || typeof hourSlot !== 'string') {
    return '-';
  }
  const [hourRaw] = hourSlot.split(':');
  const hour = Number(hourRaw);
  if (Number.isNaN(hour)) {
    return hourSlot;
  }
  const period = hour >= 12 ? 'PM' : 'AM';
  const hour12 = hour % 12 || 12;
  return `${hour12}:00 ${period}`;
}

export function AdminAnalyticsPage() {
  const today = new Date().toISOString().slice(0, 10);
  const monthStart = `${today.slice(0, 8)}01`;
  const [dateFrom, setDateFrom] = useState(monthStart);
  const [dateTo, setDateTo] = useState(today);
  const [period, setPeriod] = useState('daily');
  const [areaId, setAreaId] = useState('all');
  const [areas, setAreas] = useState([]);
  const [data, setData] = useState({ summary: {}, top_tables: [], peak_hours: [] });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setError('');
    setLoading(true);
    try {
      const res = await api.adminAnalyticsOverview({ dateFrom, dateTo, areaId });
      setData({
        summary: res.summary || {},
        top_tables: res.top_tables || [],
        peak_hours: res.peak_hours || [],
      });
    } catch (err) {
      setError(err.message || 'Failed to load analytics');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const loadAreas = async () => {
      try {
        const res = await api.adminAreas();
        setAreas(Array.isArray(res.areas) ? res.areas : []);
      } catch {
        setAreas([]);
      }
    };
    loadAreas();
  }, []);

  useEffect(() => {
    load();
  }, [dateFrom, dateTo, areaId]);

  const summary = data.summary || {};
  const totalBookings = Number(summary.total_bookings || 0);
  const totalGuests = Number(summary.total_guests || 0);
  const pendingCount = Number(summary.pending_count || 0);
  const confirmedCount = Number(summary.confirmed_count || 0);
  const completedCount = Number(summary.completed_count || 0);
  const cancelledCount = Number(summary.cancelled_count || 0);
  const noShowCount = Number(summary.no_show_count || 0);
  const avgGuests = totalBookings > 0 ? (totalGuests / totalBookings).toFixed(1) : '0.0';
  const noShowRate = totalBookings > 0 ? ((noShowCount / totalBookings) * 100).toFixed(1) : '0.0';

  const topTable = useMemo(() => data.top_tables[0] || null, [data.top_tables]);
  const peakHour = useMemo(() => data.peak_hours[0] || null, [data.peak_hours]);
  const hasData = totalBookings > 0;

  const onPeriodChange = (nextPeriod) => {
    setPeriod(nextPeriod);
    const now = new Date();
    if (nextPeriod === 'daily') {
      const day = now.toISOString().slice(0, 10);
      setDateFrom(day);
      setDateTo(day);
      return;
    }
    if (nextPeriod === 'weekly') {
      const day = now.getDay();
      const diffToMonday = (day + 6) % 7;
      const monday = new Date(now);
      monday.setDate(now.getDate() - diffToMonday);
      const sunday = new Date(monday);
      sunday.setDate(monday.getDate() + 6);
      setDateFrom(monday.toISOString().slice(0, 10));
      setDateTo(sunday.toISOString().slice(0, 10));
      return;
    }
    if (nextPeriod === 'monthly') {
      const start = new Date(now.getFullYear(), now.getMonth(), 1);
      const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      setDateFrom(start.toISOString().slice(0, 10));
      setDateTo(end.toISOString().slice(0, 10));
      return;
    }
    const yearStart = `${now.getFullYear()}-01-01`;
    const yearEnd = `${now.getFullYear()}-12-31`;
    setDateFrom(yearStart);
    setDateTo(yearEnd);
  };

  return (
    <AdminShell
      title="Analytics"
      pageIcon="fa-chart-line"
      notificationCount={pendingCount}
      centerContent={(
        <div className="analytics-topbar-controls">
          <div className="analytics-range-group" role="group" aria-label="Period granularity selector">
            {['daily', 'weekly', 'monthly', 'yearly'].map((value) => (
              <button
                key={value}
                type="button"
                className={`analytics-range-chip ${period === value ? 'is-active' : ''}`}
                onClick={() => onPeriodChange(value)}
              >
                {value.charAt(0).toUpperCase() + value.slice(1)}
              </button>
            ))}
          </div>
          <div className="analytics-topbar-selects">
            <label className="analytics-topbar-select">
              <span className="analytics-topbar-select-icon"><i className="fa-solid fa-location-dot"></i></span>
              <select aria-label="Area filter" value={areaId} onChange={(e) => setAreaId(e.target.value)}>
                <option value="all">All areas</option>
                {areas.map((area) => (
                  <option key={area.area_id} value={String(area.area_id)}>
                    {area.name}
                  </option>
                ))}
              </select>
            </label>
            <div className="analytics-period-range">
              <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} aria-label="Start date" />
              <span>to</span>
              <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} aria-label="End date" />
            </div>
          </div>
        </div>
      )}
      profileName="Admin"
    >
      <main className="analytics-main">
        <div className="analytics-shell">
          <section className="analytics-hero">
            <div>
              <h1 className="hero-title">Analytics</h1>
              <p className="hero-subtitle">Booking, table, and customer performance.</p>
            </div>
            <div className="hero-overview">
              <div className="hero-mini-card">
                <div className="hero-mini-label">Live focus</div>
                <div className="hero-mini-value">
                  {peakHour ? formatHourLabel(peakHour.hour_slot) : 'No trend yet'}
                </div>
                <div className="hero-mini-note">
                  {peakHour ? `${peakHour.booking_count} bookings / ${peakHour.guests || 0} guests` : 'No booking trend in this period.'}
                </div>
              </div>
              <div className="hero-mini-card">
                <div className="hero-mini-label">Top table</div>
                <div className="hero-mini-value">
                  {topTable ? `Table ${topTable.table_number}` : 'No table data'}
                </div>
                <div className="hero-mini-note">
                  {topTable ? `${topTable.booking_count} bookings in selected range.` : 'No table activity in this period.'}
                </div>
              </div>
            </div>
          </section>

          {error && <div className="booking-alert"><i className="fa fa-triangle-exclamation"></i>{error}</div>}
          {loading && <p className="muted">Loading analytics...</p>}

          {!loading && !hasData && (
            <section className="empty-state-shell">
              <div>
                <div className="empty-icon"><i className="fa-solid fa-chart-column"></i></div>
                <h2>No analytics available</h2>
                <p>Analytics will appear when bookings exist in this filter range.</p>
              </div>
            </section>
          )}

          {!loading && (
            <div className="analytics-content">
              <section className="analytics-section">
                <div className="section-heading">
                  <div className="section-title-wrap">
                    <h2>KPI Summary</h2>
                    <p>Key booking and occupancy metrics.</p>
                  </div>
                  <div className="section-note">
                    {dateFrom} to {dateTo}
                  </div>
                </div>
                <div className="kpi-grid kpi-grid-seven">
                  <article className="kpi-card">
                    <div className="kpi-label">Total Bookings</div>
                    <div className="kpi-value">{totalBookings}</div>
                    <div className="kpi-meta">Bookings in the selected period.</div>
                  </article>
                  <article className="kpi-card">
                    <div className="kpi-label">Total Guests</div>
                    <div className="kpi-value">{totalGuests}</div>
                    <div className="kpi-meta">Total guests served by bookings.</div>
                  </article>
                  <article className="kpi-card">
                    <div className="kpi-label">Pending</div>
                    <div className="kpi-value">{pendingCount}</div>
                    <div className="kpi-meta">Awaiting admin confirmation.</div>
                  </article>
                  <article className="kpi-card">
                    <div className="kpi-label">Confirmed</div>
                    <div className="kpi-value">{confirmedCount}</div>
                    <div className="kpi-meta">Confirmed and upcoming reservations.</div>
                  </article>
                  <article className="kpi-card">
                    <div className="kpi-label">Completed</div>
                    <div className="kpi-value">{completedCount}</div>
                    <div className="kpi-meta">Successfully completed service.</div>
                  </article>
                  <article className="kpi-card">
                    <div className="kpi-label">Cancelled</div>
                    <div className="kpi-value">{cancelledCount}</div>
                    <div className="kpi-meta">Cancelled before service time.</div>
                  </article>
                  <article className="kpi-card">
                    <div className="kpi-label">No-show rate</div>
                    <div className="kpi-value">{noShowRate}%</div>
                    <div className="kpi-meta">{noShowCount} no-shows | Avg party {avgGuests}</div>
                  </article>
                </div>
              </section>

              <section className="analytics-section">
                <div className="section-heading">
                  <div className="section-title-wrap">
                    <h2>Booking Trends</h2>
                    <p>Busiest tables and peak demand hours.</p>
                  </div>
                  <button type="button" className="analytics-refresh-btn" onClick={load}>
                    <i className="fa fa-rotate-right"></i>
                    Refresh
                  </button>
                </div>
                <div className="panel-grid-half">
                  <article className="analytics-card">
                    <div className="card-header">
                      <div>
                        <h3 className="card-title">Top Tables</h3>
                        <p className="card-subtitle">Most booked tables in this period.</p>
                      </div>
                    </div>
                    {data.top_tables.length ? (
                      <div className="metrics-stack">
                        {data.top_tables.map((row) => (
                          <div className="metric-pill" key={`table-${row.table_id}`}>
                            <div className="metric-pill-label">Table {row.table_number}</div>
                            <div className="metric-pill-value">{row.booking_count} bookings</div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="muted">No table activity in this range.</p>
                    )}
                  </article>
                  <article className="analytics-card">
                    <div className="card-header">
                      <div>
                        <h3 className="card-title">Peak Hours</h3>
                        <p className="card-subtitle">Highest demand service times.</p>
                      </div>
                    </div>
                    {data.peak_hours.length ? (
                      <div className="metrics-stack">
                        {data.peak_hours.map((row, idx) => (
                          <div className="metric-pill" key={`hour-${row.hour_slot}-${idx}`}>
                            <div className="metric-pill-label">{formatHourLabel(row.hour_slot)}</div>
                            <div className="metric-pill-value">
                              {row.booking_count} bookings / {row.guests || 0} guests
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="muted">No peak-hour data for this range.</p>
                    )}
                  </article>
                </div>
              </section>
            </div>
          )}
        </div>
      </main>
    </AdminShell>
  );
}
