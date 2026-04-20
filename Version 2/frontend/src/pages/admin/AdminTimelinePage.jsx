import { useEffect, useMemo, useRef, useState } from 'react';
import { api } from '../../api/client';
import { AdminShell } from '../../components/admin/AdminShell';

const START_HOUR = 10;
const END_HOUR = 23;
const STEP = 30;
const CELL_W = 80;
const ROW_H = 40;

const p2 = (v) => String(v).padStart(2, '0');
const toHHMM = (v) => `${p2(String(v || '12:00').split(':')[0])}:${p2(String(v || '12:00').split(':')[1] || 0)}`;
const toHHMMSS = (v) => `${toHHMM(v)}:00`;
const minOf = (v) => { const [h = '0', m = '0'] = String(v || '00:00:00').split(':'); return Number(h) * 60 + Number(m); };
const hhmmssOf = (mins) => `${p2(Math.floor(mins / 60))}:${p2(mins % 60)}:00`;
const fmt = (v) => { const [hRaw = '0', mRaw = '0'] = String(v || '00:00:00').split(':'); const h = Number(hRaw); return `${h % 12 || 12}:${p2(mRaw)} ${h >= 12 ? 'PM' : 'AM'}`; };
const norm = (b) => ({ ...b, assigned_table_ids: (b.assigned_table_ids || []).map(Number), assigned_table_numbers: (b.assigned_table_numbers || []).map(String) });
const hasAssigned = (b) => Array.isArray(b.assigned_table_ids) && b.assigned_table_ids.length > 0;
const statusClass = (s) => (s === 'confirmed' ? 'success' : s === 'completed' ? 'completed' : s === 'no_show' ? 'no-show' : s === 'cancelled' ? 'info' : 'pending');
const overlap = (startA, endA, startB, endB) => startA < endB && endA > startB;
const placementOf = (b) => {
  const placement = String(b?.reservation_card_status || '').toLowerCase();
  return placement === 'placed' || placement === 'not_placed' ? placement : null;
};
const localDateKey = (value = new Date()) => `${value.getFullYear()}-${p2(value.getMonth() + 1)}-${p2(value.getDate())}`;

export function AdminTimelinePage() {
  const [date, setDate] = useState(localDateKey());
  const [tab, setTab] = useState('pending');
  const [areaFilter, setAreaFilter] = useState('all');
  const [timeline, setTimeline] = useState({ bookings: [], tables: [], areas: [] });
  const [pending, setPending] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [createOpen, setCreateOpen] = useState(false);
  const [detailsOpen, setDetailsOpen] = useState(false);
  const [tableOpen, setTableOpen] = useState(false);
  const [areaOpen, setAreaOpen] = useState(false);
  const [createErr, setCreateErr] = useState('');
  const [detailsErr, setDetailsErr] = useState('');
  const [tableErr, setTableErr] = useState('');
  const [areaErr, setAreaErr] = useState('');
  const [draggingBookingId, setDraggingBookingId] = useState(null);
  const [draggingAreaId, setDraggingAreaId] = useState(null);
  const [areaDropState, setAreaDropState] = useState({ targetId: null, position: null });
  const [nowTick, setNowTick] = useState(Date.now());
  const [resizePreview, setResizePreview] = useState({});

  const [showEmail, setShowEmail] = useState(false);
  const [showPhone, setShowPhone] = useState(false);
  const [fCreate, setFCreate] = useState({ name: '', guests: 2, booking_date: date, start: '12:00', email: '', phone: '', note: '' });
  const [fDetails, setFDetails] = useState({ id: '', name: '', guests: 2, reqS: '12:00', reqE: '13:00', asS: '12:00', asE: '13:00', tableId: '', note: '', place: null, status: 'pending' });
  const [fTable, setFTable] = useState({ mode: 'create', id: '', num: '', cap: 2, areaId: '', sort: 10 });
  const [fArea, setFArea] = useState({ mode: 'create', id: '', name: '', start: '', end: '' });

  const dragRef = useRef(null);
  const resizeRef = useRef(null);
  const resizePreviewRef = useRef({});
  const timelineDateInputRef = useRef(null);
  const suppressAreaChipClickRef = useRef(false);
  const slots = useMemo(() => {
    const values = [];
    for (let h = START_HOUR; h <= END_HOUR; h += 1) {
      for (let m = 0; m < 60; m += STEP) {
        if (h === END_HOUR && m > 0) break;
        values.push(`${p2(h)}:${p2(m)}`);
      }
    }
    return values;
  }, []);

  const load = async () => {
    setLoading(true); setError('');
    try {
      const [t, p] = await Promise.all([api.adminTimeline(date), api.adminPendingBookings()]);
      setTimeline({ bookings: (t.bookings || []).map(norm), tables: t.tables || [], areas: t.areas || [] });
      setPending((p.bookings || []).map(norm));
    } catch (e) { setError(e.message || 'Failed to load timeline'); } finally { setLoading(false); }
  };
  useEffect(() => { load(); }, [date]);
  useEffect(() => { setFCreate((x) => ({ ...x, booking_date: date })); }, [date]);
  useEffect(() => {
    const timer = setInterval(() => setNowTick(Date.now()), 30000);
    return () => clearInterval(timer);
  }, []);
  useEffect(() => {
    return () => {
      window.removeEventListener('mousemove', onResizeMove);
      window.removeEventListener('mouseup', onResizeEnd);
    };
  }, []);

  const byId = useMemo(() => Object.fromEntries([...timeline.bookings, ...pending].map((b) => [b.booking_id, b])), [timeline.bookings, pending]);
  const sortedAreas = useMemo(() => [...timeline.areas].sort((a, b) => Number(a.display_order || 0) - Number(b.display_order || 0) || String(a.name || '').localeCompare(String(b.name || ''))), [timeline.areas]);
  const sortedTables = useMemo(() => [...timeline.tables].sort((a, b) => Number(a.area_display_order || 0) - Number(b.area_display_order || 0) || Number(a.sort_order || 0) - Number(b.sort_order || 0) || String(a.table_number || '').localeCompare(String(b.table_number || ''), undefined, { numeric: true })), [timeline.tables]);
  const tableById = useMemo(() => Object.fromEntries(sortedTables.map((t) => [Number(t.table_id), t])), [sortedTables]);
  const visibleTables = useMemo(() => areaFilter === 'all' ? sortedTables : sortedTables.filter((t) => String(t.area_id) === String(areaFilter)), [sortedTables, areaFilter]);
  const visibleTableOrder = useMemo(() => visibleTables.map((t) => Number(t.table_id)), [visibleTables]);
  const tableIndexMap = useMemo(() => {
    const map = {};
    visibleTableOrder.forEach((id, idx) => { map[id] = idx; });
    return map;
  }, [visibleTableOrder]);
  const rows = useMemo(() => { const out = []; const ars = areaFilter === 'all' ? sortedAreas : sortedAreas.filter((a) => String(a.area_id) === String(areaFilter)); ars.forEach((a) => { out.push({ type: 'area', a }); visibleTables.filter((t) => String(t.area_id) === String(a.area_id)).forEach((t) => out.push({ type: 'table', a, t })); }); return out; }, [sortedAreas, visibleTables, areaFilter]);
  const byTable = useMemo(() => {
    const m = {};
    timeline.bookings.forEach((b) => (b.assigned_table_ids || []).forEach((id) => {
      const n = Number(id);
      if (!m[n]) m[n] = [];
      m[n].push(b);
    }));
    return m;
  }, [timeline.bookings]);
  const bookingInActiveArea = (booking) => {
    if (areaFilter === 'all') return true;
    const ids = Array.isArray(booking.assigned_table_ids) ? booking.assigned_table_ids : [];
    if (!ids.length) return true;
    return ids.some((tableId) => {
      const table = sortedTables.find((t) => Number(t.table_id) === Number(tableId));
      return table && String(table.area_id) === String(areaFilter);
    });
  };

  const pendingForDate = useMemo(
    () => timeline.bookings.filter((b) => String(b.status || '').toLowerCase() === 'pending').filter(bookingInActiveArea),
    [timeline.bookings, areaFilter, sortedTables],
  );
  useEffect(() => {
    if (tab === 'pending' && pendingForDate.length === 0) {
      setTab('standby');
    }
  }, [tab, pendingForDate.length]);
  const list = useMemo(() => {
    if (tab === 'pending') return pendingForDate;
    if (tab === 'standby') {
      return timeline.bookings.filter((b) => String(b.status || '').toLowerCase() !== 'pending' && !hasAssigned(b));
    }
    return timeline.bookings.filter((b) => hasAssigned(b) && bookingInActiveArea(b));
  }, [tab, pendingForDate, timeline.bookings, areaFilter, sortedTables]);
  const live = timeline.bookings.filter((b) => ['pending', 'confirmed'].includes(String(b.status || '').toLowerCase()));
  const lunch = live.filter((b) => String(b.start_time || '') < '17:00:00');
  const dinner = live.filter((b) => String(b.start_time || '') >= '17:00:00');
  const totalGuests = live.reduce((s, b) => s + Number(b.number_of_guests || 0), 0);
  const bookedPeopleCountForArea = (areaId) => timeline.bookings.reduce((sum, booking) => {
    const guestCount = Number(booking.number_of_guests || 0);
    const assignedTableIds = (booking.assigned_table_ids || []).map(Number);
    if (!assignedTableIds.length) {
      return areaId === 'all' ? sum + guestCount : sum;
    }
    if (areaId === 'all') return sum + guestCount;
    const hasArea = assignedTableIds.some((tableId) => String(tableById[tableId]?.area_id || '') === String(areaId));
    return hasArea ? sum + guestCount : sum;
  }, 0);
  const availableDetailTables = useMemo(() => {
    const bookingId = Number(fDetails.id || 0);
    const start = toHHMMSS(fDetails.asS || '12:00');
    const end = toHHMMSS(fDetails.asE || '13:00');
    const blocked = new Set();
    timeline.bookings.forEach((booking) => {
      if (Number(booking.booking_id) === bookingId) return;
      const status = String(booking.status || '').toLowerCase();
      if (!['pending', 'confirmed'].includes(status)) return;
      if (!overlap(start, end, booking.start_time, booking.end_time)) return;
      (booking.assigned_table_ids || []).forEach((tableId) => blocked.add(Number(tableId)));
    });
    return sortedTables.filter((table) => !blocked.has(Number(table.table_id)));
  }, [fDetails.id, fDetails.asS, fDetails.asE, timeline.bookings, sortedTables]);

  const confirmScheduledTimeChange = (booking, nextStartTime, nextEndTime) => {
    const currentStart = toHHMM(booking.start_time);
    const nextStart = toHHMM(nextStartTime);
    const currentEnd = toHHMM(booking.end_time);
    const nextEnd = toHHMM(nextEndTime);
    if (currentStart === nextStart && currentEnd === nextEnd) return true;
    const requestedStart = toHHMM(booking.requested_start_time || booking.start_time);
    const requestedEnd = toHHMM(booking.requested_end_time || booking.end_time);
    const bookingName = booking.customer_name || 'this booking';
    return window.confirm(
      `${bookingName}\n\nCurrent: ${fmt(booking.start_time)} - ${fmt(booking.end_time)}\nNew: ${fmt(nextStartTime)} - ${fmt(nextEndTime)}\nRequested: ${fmt(`${requestedStart}:00`)} - ${fmt(`${requestedEnd}:00`)}\n\nApply this time change?`,
    );
  };

  const schedule = async (bookingId, tableId, slotHHMM) => {
    const b = byId[bookingId]; if (!b) return;
    const dur = Math.max(30, minOf(b.end_time) - minOf(b.start_time) || 60);
    const s = minOf(`${slotHHMM}:00`); const e = s + dur;
    const currentAssigned = (b.assigned_table_ids || []).map(Number).filter((id) => Number.isFinite(tableIndexMap[id]));
    const span = Math.max(1, currentAssigned.length || 1);
    const targetIdx = tableIndexMap[Number(tableId)];
    if (targetIdx === undefined) {
      throw new Error('Target table is not available in the current view.');
    }
    const targetTableIds = visibleTableOrder.slice(targetIdx, targetIdx + span);
    if (targetTableIds.length < span) {
      throw new Error('Not enough adjacent tables to keep this booking span.');
    }
    const targetArea = sortedTables.find((t) => Number(t.table_id) === Number(targetTableIds[0]))?.area_id;
    if (targetArea !== undefined) {
      const sameAreaIds = targetTableIds.every((id) => {
        const table = sortedTables.find((t) => Number(t.table_id) === Number(id));
        return table && String(table.area_id) === String(targetArea);
      });
      if (!sameAreaIds) {
        throw new Error('Cannot span tables across different areas.');
      }
    }
    const nextStartTime = hhmmssOf(s);
    const nextEndTime = hhmmssOf(e);
    if (!confirmScheduledTimeChange(b, nextStartTime, nextEndTime)) return;
    await api.adminScheduleBooking(bookingId, { table_ids: targetTableIds, start_time: nextStartTime, end_time: nextEndTime });
  };

  const onDrop = async (e, tableId, slot) => {
    e.preventDefault(); const id = dragRef.current; dragRef.current = null; if (!id) return;
    try { await schedule(id, tableId, slot); await load(); } catch (err) { setError(err.message || 'Could not schedule booking.'); }
  };

  const startResize = (event, booking, tableIds, direction) => {
    event.preventDefault();
    event.stopPropagation();
    const start = minOf(booking.start_time);
    const end = minOf(booking.end_time);
    const initialTableIds = tableIds && tableIds.length ? tableIds.map(Number) : (booking.assigned_table_ids || []).map(Number);
    resizeRef.current = {
      bookingId: booking.booking_id,
      tableIds: initialTableIds,
      direction,
      startX: event.clientX,
      startY: event.clientY,
      originalStart: start,
      originalEnd: end,
    };
    resizePreviewRef.current[booking.booking_id] = { start, end, tableIds: initialTableIds };
    setResizePreview((prev) => ({ ...prev, [booking.booking_id]: { start, end, tableIds: initialTableIds } }));
    window.addEventListener('mousemove', onResizeMove);
    window.addEventListener('mouseup', onResizeEnd);
  };

  const onResizeMove = (event) => {
    const state = resizeRef.current;
    if (!state) return;
    const deltaX = event.clientX - state.startX;
    const deltaY = event.clientY - state.startY;
    const slotShift = Math.round(deltaX / CELL_W);
    const rowShift = Math.round(deltaY / ROW_H);
    const minuteShift = slotShift * STEP;
    const minStart = START_HOUR * 60;
    const maxEnd = END_HOUR * 60;
    let nextStart = state.originalStart;
    let nextEnd = state.originalEnd;
    let nextTableIds = [...state.tableIds];

    if (state.direction === 'left') {
      nextStart = Math.max(minStart, state.originalStart + minuteShift);
      if (nextEnd - nextStart < STEP) nextStart = nextEnd - STEP;
    } else if (state.direction === 'right') {
      nextEnd = Math.min(maxEnd, state.originalEnd + minuteShift);
      if (nextEnd - nextStart < STEP) nextEnd = nextStart + STEP;
    } else if (state.direction === 'top' || state.direction === 'bottom') {
      const currentTopIndex = visibleTableOrder.indexOf(Number(state.tableIds[0]));
      const currentBottomIndex = currentTopIndex + state.tableIds.length - 1;

      if (currentTopIndex >= 0) {
        let nextTopIndex = currentTopIndex;
        let nextBottomIndex = currentBottomIndex;
        if (state.direction === 'top') {
          nextTopIndex = Math.max(0, Math.min(currentBottomIndex, currentTopIndex + rowShift));
        } else {
          nextBottomIndex = Math.min(visibleTableOrder.length - 1, Math.max(currentTopIndex, currentBottomIndex + rowShift));
        }

        if (nextBottomIndex >= nextTopIndex) {
          const candidateIds = visibleTableOrder.slice(nextTopIndex, nextBottomIndex + 1);
          const areaId = tableById[candidateIds[0]]?.area_id;
          const allSameArea = candidateIds.every((tableId) => String(tableById[tableId]?.area_id || '') === String(areaId || ''));
          if (allSameArea) {
            nextTableIds = candidateIds;
          }
        }
      }
    }

    resizePreviewRef.current[state.bookingId] = { start: nextStart, end: nextEnd, tableIds: nextTableIds };
    setResizePreview((prev) => ({ ...prev, [state.bookingId]: { start: nextStart, end: nextEnd, tableIds: nextTableIds } }));
  };

  const onResizeEnd = async () => {
    window.removeEventListener('mousemove', onResizeMove);
    window.removeEventListener('mouseup', onResizeEnd);
    const state = resizeRef.current;
    resizeRef.current = null;
    if (!state) return;

    const preview = resizePreviewRef.current[state.bookingId];
    const next = preview || { start: state.originalStart, end: state.originalEnd, tableIds: state.tableIds };
    delete resizePreviewRef.current[state.bookingId];
    setResizePreview((prev) => {
      const copy = { ...prev };
      delete copy[state.bookingId];
      return copy;
    });

    const currentIds = (state.tableIds || []).map(Number).join(',');
    const nextIds = (next.tableIds || []).map(Number).join(',');
    if (next.start === state.originalStart && next.end === state.originalEnd && nextIds === currentIds) return;
    const booking = byId[state.bookingId];
    if (booking && !confirmScheduledTimeChange(booking, hhmmssOf(next.start), hhmmssOf(next.end))) return;

    try {
      await api.adminScheduleBooking(state.bookingId, {
        table_ids: (next.tableIds && next.tableIds.length ? next.tableIds : []).map(Number),
        start_time: hhmmssOf(next.start),
        end_time: hhmmssOf(next.end),
      });
      await load();
    } catch (err) {
      setError(err.message || 'Could not resize booking.');
    }
  };

  const openDetails = (id) => {
    const b = byId[id]; if (!b) return;
    setFDetails({ id: b.booking_id, name: b.customer_name || '', guests: Number(b.number_of_guests || 1), reqS: toHHMM(b.requested_start_time || b.start_time), reqE: toHHMM(b.requested_end_time || b.end_time), asS: toHHMM(b.start_time), asE: toHHMM(b.end_time), tableId: b.assigned_table_ids?.[0] ? String(b.assigned_table_ids[0]) : '', note: b.special_request || '', place: b.reservation_card_status || null, status: b.status || 'pending' });
    setDetailsErr(''); setDetailsOpen(true);
  };

  const submitCreate = async (e) => {
    e.preventDefault(); setCreateErr('');
    try {
      await api.adminCreateBooking({ customer_name: fCreate.name, number_of_guests: Number(fCreate.guests), booking_date: fCreate.booking_date, start_time: toHHMMSS(fCreate.start), customer_email: showEmail && fCreate.email ? fCreate.email : undefined, customer_phone: showPhone && fCreate.phone ? fCreate.phone : undefined, special_request: fCreate.note || undefined });
      setCreateOpen(false); await load();
    } catch (err) { setCreateErr(err.message || 'Could not create booking.'); }
  };

  const submitDetails = async (e) => {
    e.preventDefault(); setDetailsErr('');
    try {
      await api.adminUpdateBookingDetails(fDetails.id, {
        customer_name: fDetails.name,
        number_of_guests: Number(fDetails.guests),
        requested_start_time: toHHMMSS(fDetails.reqS),
        requested_end_time: toHHMMSS(fDetails.reqE),
        start_time: toHHMMSS(fDetails.asS),
        end_time: toHHMMSS(fDetails.asE),
        table_id: fDetails.tableId ? Number(fDetails.tableId) : null,
        special_request: fDetails.note || '',
        confirm_booking: String(fDetails.status || '').toLowerCase() === 'pending',
      });
      setDetailsOpen(false); await load();
    } catch (err) { setDetailsErr(err.message || 'Could not save details.'); }
  };

  const actPending = async (id, approve) => {
    try {
      if (approve) {
        const res = await api.adminConfirmPendingBooking(id);
        const assigned = Array.isArray(res?.assigned_table_ids) ? res.assigned_table_ids : [];
        setTab(assigned.length > 0 ? 'bookings' : 'standby');
      } else {
        await api.adminCancelBooking(id);
      }
      await load();
    } catch (e) {
      setError(e.message || 'Action failed.');
    }
  };
  const markStatus = async (s) => { try { await api.adminSetBookingStatus(fDetails.id, { status: s }); setDetailsOpen(false); await load(); } catch (e) { setDetailsErr(e.message || 'Status update failed.'); } };
  const cancelFromDetails = async () => { try { await api.adminCancelBooking(fDetails.id); setDetailsOpen(false); await load(); } catch (e) { setDetailsErr(e.message || 'Cancel failed.'); } };
  const togglePlacement = async () => { try { const n = fDetails.place === 'placed' ? 'not_placed' : 'placed'; await api.adminUpdateBookingPlacement(fDetails.id, { reservation_card_status: n }); setFDetails((x) => ({ ...x, place: n })); await load(); } catch (e) { setDetailsErr(e.message || 'Placement update failed.'); } };

  const openTable = (t = null, areaId = '') => {
    setTableErr('');
    setFTable(t ? { mode: 'edit', id: String(t.table_id), num: String(t.table_number), cap: Number(t.capacity || 2), areaId: String(t.area_id || ''), sort: Number(t.sort_order || 10) } : { mode: 'create', id: '', num: '', cap: 2, areaId: areaId || String(sortedAreas[0]?.area_id || ''), sort: 10 });
    setTableOpen(true);
  };
  const submitTable = async (e) => {
    e.preventDefault(); setTableErr('');
    try {
      const payload = { table_number: fTable.num, capacity: Number(fTable.cap), area_id: Number(fTable.areaId), sort_order: Number(fTable.sort) };
      if (fTable.mode === 'create') await api.adminCreateTable(payload); else await api.adminUpdateTable(Number(fTable.id), payload);
      setTableOpen(false); await load();
    } catch (err) { setTableErr(err.message || 'Could not save table.'); }
  };
  const delTable = async () => { try { await api.adminDeleteTable(Number(fTable.id)); setTableOpen(false); await load(); } catch (e) { setTableErr(e.message || 'Could not delete table.'); } };

  const openArea = (a = null) => { setAreaErr(''); setFArea(a ? { mode: 'edit', id: String(a.area_id), name: String(a.name || ''), start: a.table_number_start ?? '', end: a.table_number_end ?? '' } : { mode: 'create', id: '', name: '', start: '', end: '' }); setAreaOpen(true); };
  const submitArea = async (e) => {
    e.preventDefault(); setAreaErr('');
    try {
      const payload = { name: fArea.name, table_number_start: fArea.start === '' ? null : Number(fArea.start), table_number_end: fArea.end === '' ? null : Number(fArea.end) };
      if (fArea.mode === 'create') await api.adminCreateArea(payload); else await api.adminUpdateArea(Number(fArea.id), payload);
      setAreaOpen(false); await load();
    } catch (err) { setAreaErr(err.message || 'Could not save area.'); }
  };
  const delArea = async () => { try { await api.adminDeleteArea(Number(fArea.id)); if (String(areaFilter) === String(fArea.id)) setAreaFilter('all'); setAreaOpen(false); await load(); } catch (e) { setAreaErr(e.message || 'Could not delete area.'); } };

  const reorderAreas = async (draggedId, targetId, position) => {
    if (!draggedId || !targetId || String(draggedId) === String(targetId)) return;
    const order = sortedAreas.map((a) => Number(a.area_id));
    const from = order.indexOf(Number(draggedId));
    const to = order.indexOf(Number(targetId));
    if (from < 0 || to < 0) return;

    const next = [...order];
    const [moved] = next.splice(from, 1);
    let insertIndex = to;
    if (position === 'after') insertIndex = to + (from < to ? 0 : 1);
    if (position === 'before') insertIndex = to + (from < to ? -1 : 0);
    if (insertIndex < 0) insertIndex = 0;
    if (insertIndex > next.length) insertIndex = next.length;
    next.splice(insertIndex, 0, moved);

    try {
      await api.adminReorderAreas({ area_ids: next });
      await load();
    } catch (err) {
      setError(err.message || 'Could not reorder areas.');
    }
  };

  const dObj = new Date(`${date}T00:00:00`);
  const dYear = dObj.getFullYear();
  const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const dPrimary = `${weekdayNames[dObj.getDay()]}, ${monthNames[dObj.getMonth()]} ${p2(dObj.getDate())}`;
  const isToday = date === localDateKey();
  const prevDay = () => { const d = new Date(`${date}T00:00:00`); d.setDate(d.getDate() - 1); setDate(localDateKey(d)); };
  const nextDay = () => { const d = new Date(`${date}T00:00:00`); d.setDate(d.getDate() + 1); setDate(localDateKey(d)); };
  const openDatePicker = () => {
    const input = timelineDateInputRef.current;
    if (!input) return;
    if (typeof input.showPicker === 'function') {
      input.showPicker();
      return;
    }
    input.focus();
    input.click();
  };

  return (
    <AdminShell
      title="Timeline"
      pageIcon="fa-calendar-days"
      notificationCount={pending.length}
      topAction={pendingForDate.length > 0 ? (
        <button type="button" className="topbar-new-bookings" onClick={() => setTab('pending')}>
          New Bookings <span>{pendingForDate.length}</span>
        </button>
      ) : null}
    >
      <main className="timeline-main">
        <div className="content">
          <div className="left-panel">
            <div className="tables-section">
              <div className="timeline-panel-tools">
                <div className="timeline-date-card">
                  <div className="timeline-date-year">{dYear}</div><div className="timeline-date-primary">{dPrimary}</div>
                  <button type="button" className={`today-button ${isToday ? 'is-hidden' : ''}`} onClick={() => setDate(localDateKey())}>View Today</button>
                  <div className="timeline-date-nav-row">
                    <button type="button" className="timeline-date-nav" onClick={prevDay}><i className="fa fa-chevron-left"></i></button>
                    <input ref={timelineDateInputRef} type="date" className="timeline-date-input" value={date} onChange={(e) => setDate(e.target.value)} />
                    <button type="button" className="timeline-date-picker-trigger" onClick={openDatePicker}><i className="fa fa-calendar-alt"></i></button>
                    <button type="button" className="timeline-date-nav" onClick={nextDay}><i className="fa fa-chevron-right"></i></button>
                  </div>
                </div>
              </div>
              <div className="booking-list-tabs">
                <button
                  type="button"
                  className={`booking-list-tab pending-span ${tab === 'pending' ? 'active' : ''} ${pendingForDate.length > 0 ? 'has-pending' : ''}`}
                  onClick={() => setTab('pending')}
                  style={{ display: pendingForDate.length > 0 ? 'inline-flex' : 'none' }}
                >
                  Pending
                </button>
                <div className="booking-list-tabs-row">
                  <button type="button" className={`booking-list-tab ${tab === 'standby' ? 'active' : ''}`} onClick={() => setTab('standby')}>Standby</button>
                  <button type="button" className={`booking-list-tab ${tab === 'bookings' ? 'active' : ''}`} onClick={() => setTab('bookings')}>Bookings</button>
                </div>
              </div>
              <div className="booking-list">
                {error && <div className="booking-alert"><i className="fa fa-triangle-exclamation"></i>{error}</div>}
                {loading && <p className="booking-list-empty">Loading timeline...</p>}
                {!loading && !list.length && (
                  <p className="booking-list-empty">
                    {tab === 'pending' ? 'No pending bookings for this date.' : tab === 'standby' ? 'No unassigned bookings for this date.' : 'No assigned bookings for this date.'}
                  </p>
                )}
                {!loading && list.map((b) => {
                  const normalizedStatus = String(b.status || '').toLowerCase();
                  const isPendingTab = tab === 'pending';
                  const isStandbyTab = tab === 'standby';
                  const canDragFromList = (isPendingTab || isStandbyTab) && ['pending', 'confirmed'].includes(normalizedStatus);
                  const assignmentSummary = (b.assigned_table_numbers || []).length ? `T${(b.assigned_table_numbers || []).join(', T')}` : 'Unassigned';
                  const placement = placementOf(b);
                  const hasNote = String(b.special_request || '').trim() !== '';

                  return (
                    <div
                      key={`l-${b.booking_id}`}
                      className={`booking-item${canDragFromList ? ' draggable-booking' : ''} ${draggingBookingId === b.booking_id ? 'dragging' : ''}`}
                      draggable={canDragFromList}
                      onDragStart={() => { if (!canDragFromList) return; dragRef.current = b.booking_id; setDraggingBookingId(b.booking_id); }}
                      onDragEnd={() => setDraggingBookingId(null)}
                      onClick={() => openDetails(b.booking_id)}
                    >
                      <div className="booking-item-top">
                        <span className="booking-item-top-left">
                          <span className="booking-item-time">{fmt(b.start_time)}</span>
                          {!isPendingTab && !isStandbyTab && placement ? <span className={`booking-placement-dot ${placement === 'placed' ? 'placed' : 'not-placed'}`}></span> : null}
                          {hasNote ? <span className="booking-item-note-icon" title={b.special_request || ''}><i className="fa-solid fa-note-sticky"></i></span> : null}
                        </span>
                        <span className="booking-item-top-right">
                          {isPendingTab ? <span className="status-tag pending">Pending</span> : null}
                          {isStandbyTab ? <span className="booking-item-meta">P{b.number_of_guests}</span> : null}
                          {!isPendingTab && !isStandbyTab ? <span className="booking-item-table">{assignmentSummary}</span> : null}
                        </span>
                      </div>
                      <div className="booking-item-bottom">
                        <span className="booking-item-name">{b.customer_name || 'Guest'}</span>
                        {isPendingTab ? (
                          <span className="booking-item-actions-inline">
                            <button type="button" className="booking-item-action-btn" onClick={(e) => { e.stopPropagation(); actPending(b.booking_id, true); }}>Confirm</button>
                            <button type="button" className="booking-item-action-btn secondary" onClick={(e) => { e.stopPropagation(); actPending(b.booking_id, false); }}>Reject</button>
                          </span>
                        ) : null}
                        {!isPendingTab && !isStandbyTab ? <span className="booking-item-bottom-right">P{b.number_of_guests}</span> : null}
                      </div>
                    </div>
                  );
                })}
              </div>
              <div className="left-panel-footer">
                <div className="stats-card"><div className="stats-list">
                  <div className="stats-item"><span className="stats-item-label">Total</span><span className="stats-item-value"><span className="stats-item-bookings">{live.length} Bookings</span><span className="stats-item-people">P{totalGuests}</span></span></div>
                  <div className="stats-item"><span className="stats-item-label">Lunch</span><span className="stats-item-value"><span className="stats-item-bookings">{lunch.length} Bookings</span><span className="stats-item-people">P{lunch.reduce((s, r) => s + Number(r.number_of_guests || 0), 0)}</span></span></div>
                  <div className="stats-item"><span className="stats-item-label">Dinner</span><span className="stats-item-value"><span className="stats-item-bookings">{dinner.length} Bookings</span><span className="stats-item-people">P{dinner.reduce((s, r) => s + Number(r.number_of_guests || 0), 0)}</span></span></div>
                </div></div>
                <button type="button" className="add-booking-button" onClick={() => { setCreateErr(''); setShowEmail(false); setShowPhone(false); setFCreate({ name: '', guests: 2, booking_date: date, start: '12:00', email: '', phone: '', note: '' }); setCreateOpen(true); }}><i className="fa fa-plus"></i>Add a Booking</button>
              </div>
            </div>
          </div>

          <div className="timeline-area">
            <div className="timeline-toolbar">
              <div className="area-filter-bar">
                <button type="button" className={`area-filter-chip ${areaFilter === 'all' ? 'active' : ''}`} onClick={() => setAreaFilter('all')}>Full View <span className="area-filter-chip-count">{bookedPeopleCountForArea('all')}</span></button>
                {sortedAreas.map((a) => (
                  <button
                    key={`a-${a.area_id}`}
                    type="button"
                    className={`area-filter-chip ${String(areaFilter) === String(a.area_id) ? 'active' : ''} ${draggingAreaId === a.area_id ? 'dragging' : ''} ${areaDropState.targetId === a.area_id && areaDropState.position === 'before' ? 'drop-before' : ''} ${areaDropState.targetId === a.area_id && areaDropState.position === 'after' ? 'drop-after' : ''}`}
                    onClick={() => {
                      if (suppressAreaChipClickRef.current) {
                        suppressAreaChipClickRef.current = false;
                        return;
                      }
                      if (String(areaFilter) === String(a.area_id)) {
                        openArea(a);
                        return;
                      }
                      setAreaFilter(String(a.area_id));
                    }}
                    onDoubleClick={() => openArea(a)}
                    title="Double-click to edit area"
                    draggable
                    onDragStart={() => { setDraggingAreaId(a.area_id); }}
                    onDragOver={(e) => {
                      e.preventDefault();
                      const rect = e.currentTarget.getBoundingClientRect();
                      const before = e.clientX < rect.left + rect.width / 2;
                      setAreaDropState({ targetId: a.area_id, position: before ? 'before' : 'after' });
                    }}
                    onDragLeave={() => setAreaDropState((prev) => (prev.targetId === a.area_id ? { targetId: null, position: null } : prev))}
                    onDrop={async (e) => {
                      e.preventDefault();
                      suppressAreaChipClickRef.current = true;
                      await reorderAreas(draggingAreaId, a.area_id, areaDropState.position || 'after');
                      setDraggingAreaId(null);
                      setAreaDropState({ targetId: null, position: null });
                    }}
                    onDragEnd={() => {
                      suppressAreaChipClickRef.current = true;
                      setDraggingAreaId(null);
                      setAreaDropState({ targetId: null, position: null });
                    }}
                  >
                    {a.name}<span className="area-filter-chip-count">{bookedPeopleCountForArea(a.area_id)}</span>
                  </button>
                ))}
                <button type="button" className="area-filter-chip secondary area-filter-add-btn" onClick={() => openArea()}>+</button>
              </div>
            </div>
            <div className="timeline-scroll-wrapper">
              <div className="time-header"><div className="time-header-spacer">Tables</div><div className="time-slots">{slots.map((s) => <div className="time-slot" key={`t-${s}`}>{fmt(`${s}:00`)}</div>)}</div></div>
              <div className="timeline-content">
                <div className="table-labels">
                  {rows.map((r, i) => r.type === 'area' ? <div key={`al-${r.a.area_id}-${i}`} className="table-label area-label-row">{r.a.name}</div> : <div key={`tl-${r.t.table_id}`} className="table-label clickable" onClick={() => openTable(r.t)}><span className="table-label-inner"><span>T{r.t.table_number}</span><span className="table-label-area-pill">P{r.t.capacity}</span></span></div>)}
                  {sortedAreas.length > 0 && <div className="table-label add-table-row"><button type="button" className="add-table-inline-btn" onClick={() => openTable(null, areaFilter === 'all' ? sortedAreas[0].area_id : areaFilter)}>+</button></div>}
                </div>
                <div className="timeline-grid">
                  {rows.map((r, i) => r.type === 'area' ? <div key={`ad-${r.a.area_id}-${i}`} className="area-divider-row" data-area-name={r.a.name}>{slots.map((s) => <div key={`ac-${r.a.area_id}-${s}`} className="area-divider-cell"></div>)}</div> : (
                    <div key={`row-${r.t.table_id}`} className="table-row">
                      {slots.map((s) => <div key={`c-${r.t.table_id}-${s}`} className="time-cell" onDragOver={(e) => e.preventDefault()} onDrop={(e) => onDrop(e, r.t.table_id, s)}></div>)}
                      {(byTable[r.t.table_id] || []).map((b) => {
                        const preview = resizePreview[b.booking_id];
                        const idsFromState = (b.assigned_table_ids || []).map(Number);
                        const idsFromPreview = preview?.tableIds || idsFromState;
                        const assignedVisibleIds = idsFromPreview
                          .map(Number)
                          .filter((id) => Number.isFinite(tableIndexMap[id]))
                          .sort((left, right) => tableIndexMap[left] - tableIndexMap[right]);
                        if (!assignedVisibleIds.length) return null;

                        const currentIdx = tableIndexMap[Number(r.t.table_id)];
                        if (currentIdx === undefined) return null;
                        const assignedSet = new Set(assignedVisibleIds.map((id) => tableIndexMap[id]));
                        if (!assignedSet.has(currentIdx)) return null;

                        if (assignedSet.has(currentIdx - 1)) return null;

                        let rowSpan = 1;
                        while (assignedSet.has(currentIdx + rowSpan)) rowSpan += 1;
                        const segmentTableIds = visibleTableOrder.slice(currentIdx, currentIdx + rowSpan);
                        const startMin = preview ? preview.start : minOf(b.start_time);
                        const endMin = preview ? preview.end : minOf(b.end_time);
                        const left = ((startMin - START_HOUR * 60) / STEP) * CELL_W;
                        const width = Math.max(CELL_W, ((Math.max(endMin, startMin + STEP) - startMin) / STEP) * CELL_W);
                        const height = Math.max(32, rowSpan * ROW_H - 8);
                        const requestedStart = toHHMM(b.requested_start_time || b.start_time);
                        const scheduledStart = toHHMM(b.start_time);
                        const rescheduled = requestedStart !== scheduledStart;
                        const assignedCapacity = segmentTableIds.reduce((sum, tableId) => sum + Number(tableById[Number(tableId)]?.capacity || 0), 0);
                        const overCapacity = assignedCapacity > 0 && Number(b.number_of_guests || 0) > assignedCapacity;
                        const showTime = width >= 88;
                        const showGuests = width >= 132;
                        const showNote = Boolean(String(b.special_request || '').trim()) && width >= 156;
                        const placement = placementOf(b);

                        return (
                          <div
                            key={`bb-${b.booking_id}-${r.t.table_id}`}
                            className={`booking-block ${statusClass(String(b.status || 'pending').toLowerCase())} ${overCapacity ? 'over-capacity' : ''} ${rescheduled ? 'rescheduled' : ''} ${draggingBookingId === b.booking_id ? 'dragging' : ''}`}
                            style={{ left: `${left}px`, width: `${width}px`, height: `${height}px` }}
                            draggable={['pending', 'confirmed'].includes(String(b.status || '').toLowerCase())}
                            onDragStart={() => { dragRef.current = b.booking_id; setDraggingBookingId(b.booking_id); }}
                            onDragEnd={() => setDraggingBookingId(null)}
                            onClick={() => openDetails(b.booking_id)}
                          >
                            <div className="resize-handle top-handle" onMouseDown={(e) => startResize(e, b, segmentTableIds, 'top')}></div>
                            <div className="resize-handle left-handle" onMouseDown={(e) => startResize(e, b, segmentTableIds, 'left')}></div>
                            <div className="resize-handle right-handle" onMouseDown={(e) => startResize(e, b, segmentTableIds, 'right')}></div>
                            <div className="resize-handle bottom-handle" onMouseDown={(e) => startResize(e, b, segmentTableIds, 'bottom')}></div>
                            <div className="booking-content">
                              <div className="booking-top">
                                {showTime ? <span className="booking-time-text">{fmt(hhmmssOf(startMin))}</span> : <span></span>}
                                <span className="booking-meta-inline">
                                  {placement ? <span className={`booking-placement-dot booking-placement-inline ${placement === 'placed' ? 'placed' : 'not-placed'}`}></span> : null}
                                  {showGuests ? `P${b.number_of_guests}` : ''}
                                  {showNote ? (
                                    <button type="button" className="booking-note-btn" title={b.special_request || ''} onClick={(e) => { e.stopPropagation(); openDetails(b.booking_id); }}>
                                      <i className="fa-solid fa-note-sticky"></i>
                                    </button>
                                  ) : null}
                                </span>
                              </div>
                              <div className="booking-name-row">
                                <span className="booking-name-text">{b.customer_name || 'Guest'}</span>
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  ))}
                  {isToday && (
                    (() => {
                      void nowTick;
                      const now = new Date();
                      const mins = now.getHours() * 60 + now.getMinutes();
                      const start = START_HOUR * 60;
                      const end = END_HOUR * 60;
                      if (mins < start || mins > end) return null;
                      const left = ((mins - start) / STEP) * CELL_W;
                      return <div className="current-time-line" style={{ left: `${left}px` }}></div>;
                    })()
                  )}
                  {sortedAreas.length > 0 && <div className="table-row add-table-row">{slots.map((s) => <div key={`add-${s}`} className="time-cell"></div>)}</div>}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className={`modal-backdrop-custom ${createOpen ? 'open' : ''}`} onClick={() => setCreateOpen(false)}><div className="booking-modal-card booking-details-card booking-create-card" onClick={(e) => e.stopPropagation()}>
          <div className="booking-modal-header"><h5><i className="fa fa-calendar-plus"></i> Add a Booking</h5><button type="button" className="booking-modal-close" onClick={() => setCreateOpen(false)}>&times;</button></div>
          {createErr && <div className="modal-error" style={{ display: 'block' }}>{createErr}</div>}
          <form onSubmit={submitCreate}><div className="booking-detail-grid">
            <div className="modal-form-group"><label>Name</label><input type="text" value={fCreate.name} onChange={(e) => setFCreate((x) => ({ ...x, name: e.target.value }))} required /></div>
            <div className="modal-form-group"><label>Number of People</label><input type="number" min="1" value={fCreate.guests} onChange={(e) => setFCreate((x) => ({ ...x, guests: Number(e.target.value) }))} required /></div>
            <div className="modal-form-group"><label>Date</label><input type="date" value={fCreate.booking_date} onChange={(e) => setFCreate((x) => ({ ...x, booking_date: e.target.value }))} required /></div>
            <div className="modal-form-group"><label>Time</label><input type="time" min="10:00" max="21:00" step="1800" value={fCreate.start} onChange={(e) => setFCreate((x) => ({ ...x, start: e.target.value }))} required /><div className="modal-helper-text">Default duration: 60 minutes.</div></div>
            <div className="modal-form-group full-width"><button type="button" className={`booking-inline-trigger ${showEmail ? 'is-active' : ''}`} onClick={() => setShowEmail((v) => !v)}><i className="fa fa-envelope"></i><span>{showEmail ? 'Remove Email' : 'Add Email'}</span></button></div>
            {showEmail && <div className="modal-form-group full-width"><label>Email</label><input type="email" value={fCreate.email} onChange={(e) => setFCreate((x) => ({ ...x, email: e.target.value }))} /></div>}
            <div className="modal-form-group full-width"><button type="button" className={`booking-inline-trigger ${showPhone ? 'is-active' : ''}`} onClick={() => setShowPhone((v) => !v)}><i className="fa fa-phone"></i><span>{showPhone ? 'Remove Phone Number' : 'Add Phone Number'}</span></button></div>
            {showPhone && <div className="modal-form-group full-width"><label>Phone</label><input type="text" value={fCreate.phone} onChange={(e) => setFCreate((x) => ({ ...x, phone: e.target.value }))} /></div>}
            <div className="modal-form-group full-width"><label>Notes</label><textarea value={fCreate.note} onChange={(e) => setFCreate((x) => ({ ...x, note: e.target.value }))}></textarea></div>
          </div><div className="booking-modal-actions"><button type="button" className="booking-modal-cancel" onClick={() => setCreateOpen(false)}>Cancel</button><button type="submit" className="booking-modal-submit">Create Booking</button></div></form>
        </div></div>

        <div className={`modal-backdrop-custom ${detailsOpen ? 'open' : ''}`} onClick={() => setDetailsOpen(false)}><div className="booking-modal-card booking-details-card" onClick={(e) => e.stopPropagation()}>
          <div className="booking-modal-header"><div><h5><i className="fa fa-clipboard-list"></i> Booking Details</h5><div className="booking-meta-chip">{fDetails.place && <span className={`booking-placement-dot ${fDetails.place === 'placed' ? 'placed' : 'not-placed'}`}></span>}<span>{String(fDetails.status || 'pending').replace('_', ' ')}</span></div></div><button type="button" className="booking-modal-close" onClick={() => setDetailsOpen(false)}>&times;</button></div>
          {detailsErr && <div className="modal-error" style={{ display: 'block' }}>{detailsErr}</div>}
          <form onSubmit={submitDetails}><div className="booking-detail-topbar">
            {['pending', 'confirmed'].includes(String(fDetails.status || '').toLowerCase()) && <button type="button" className="booking-modal-danger-small" onClick={cancelFromDetails}>Cancel Booking</button>}
            {['pending', 'confirmed'].includes(String(fDetails.status || '').toLowerCase()) && fDetails.tableId && <button type="button" className="booking-modal-success-small" onClick={togglePlacement}>{fDetails.place === 'placed' ? 'Mark Not Placed' : 'Mark Placed'}</button>}
            {String(fDetails.status || '').toLowerCase() === 'confirmed' && <button type="button" className="booking-modal-secondary-small" onClick={() => markStatus('completed')}>Mark Completed</button>}
            {String(fDetails.status || '').toLowerCase() === 'confirmed' && <button type="button" className="booking-modal-secondary-small" onClick={() => markStatus('no_show')}>Mark No-show</button>}
          </div>
            <div className="booking-detail-grid">
              <div className="modal-form-group"><label>Name</label><input type="text" value={fDetails.name} onChange={(e) => setFDetails((x) => ({ ...x, name: e.target.value }))} required /></div>
              <div className="modal-form-group"><label>Party Size</label><input type="number" min="1" value={fDetails.guests} onChange={(e) => setFDetails((x) => ({ ...x, guests: Number(e.target.value) }))} required /></div>
              <div className="modal-form-group full-width"><label>Requested Time</label><div className="booking-time-pair"><input type="time" min="10:00" max="21:30" step="1800" value={fDetails.reqS} onChange={(e) => setFDetails((x) => ({ ...x, reqS: e.target.value }))} required /><span className="booking-time-pair-separator">to</span><input type="time" min="10:30" max="22:00" step="1800" value={fDetails.reqE} onChange={(e) => setFDetails((x) => ({ ...x, reqE: e.target.value }))} required /></div></div>
              <div className="modal-form-group full-width"><label>Assigned Time</label><div className="booking-time-pair"><input type="time" min="10:00" max="21:30" step="1800" value={fDetails.asS} onChange={(e) => setFDetails((x) => ({ ...x, asS: e.target.value }))} required /><span className="booking-time-pair-separator">to</span><input type="time" min="10:30" max="22:00" step="1800" value={fDetails.asE} onChange={(e) => setFDetails((x) => ({ ...x, asE: e.target.value }))} required /></div></div>
              <div className="modal-form-group full-width">
                <label>Table</label>
                <select value={fDetails.tableId} onChange={(e) => setFDetails((x) => ({ ...x, tableId: e.target.value }))}>
                  <option value="">Unassigned</option>
                  {availableDetailTables.map((t) => <option key={`opt-${t.table_id}`} value={t.table_id}>T{t.table_number} | {t.area_name} | Capacity {t.capacity}</option>)}
                  {fDetails.tableId && !availableDetailTables.some((t) => String(t.table_id) === String(fDetails.tableId)) && (() => {
                    const selectedTable = sortedTables.find((t) => String(t.table_id) === String(fDetails.tableId));
                    if (!selectedTable) return null;
                    return <option value={selectedTable.table_id}>T{selectedTable.table_number} | {selectedTable.area_name} | Capacity {selectedTable.capacity}</option>;
                  })()}
                </select>
              </div>
              <div className="modal-form-group full-width"><label>Notes</label><textarea value={fDetails.note} onChange={(e) => setFDetails((x) => ({ ...x, note: e.target.value }))}></textarea></div>
            </div><div className="booking-modal-actions"><button type="button" className="booking-modal-cancel" onClick={() => setDetailsOpen(false)}>Close</button><button type="submit" className="booking-modal-submit">{String(fDetails.status || '').toLowerCase() === 'pending' ? 'Confirm Booking' : 'Save Changes'}</button></div>
          </form>
        </div></div>

        <div className={`modal-backdrop-custom ${tableOpen ? 'open' : ''}`} onClick={() => setTableOpen(false)}><div className="booking-modal-card" onClick={(e) => e.stopPropagation()}>
          <div className="booking-modal-header"><h5><i className="fa fa-chair"></i> {fTable.mode === 'create' ? 'Add Table' : 'Table Details'}</h5><button type="button" className="booking-modal-close" onClick={() => setTableOpen(false)}>&times;</button></div>
          {tableErr && <div className="modal-error" style={{ display: 'block' }}>{tableErr}</div>}
          <form onSubmit={submitTable}><div className="modal-form-group"><label>Table</label><input type="text" value={fTable.num} onChange={(e) => setFTable((x) => ({ ...x, num: e.target.value }))} required /></div><div className="modal-form-group"><label>Capacity</label><input type="number" min="1" value={fTable.cap} onChange={(e) => setFTable((x) => ({ ...x, cap: Number(e.target.value) }))} required /></div><div className="modal-form-group"><label>Area</label><select value={fTable.areaId} onChange={(e) => setFTable((x) => ({ ...x, areaId: e.target.value }))} required><option value="">Select area</option>{sortedAreas.map((a) => <option key={`ta-${a.area_id}`} value={a.area_id}>{a.name}</option>)}</select></div><div className="modal-form-group"><label>Order In Area</label><input type="number" min="1" value={fTable.sort} onChange={(e) => setFTable((x) => ({ ...x, sort: Number(e.target.value) }))} required /></div><div className="booking-modal-actions">{fTable.mode === 'edit' && <button type="button" className="booking-modal-danger-small" onClick={delTable}>Delete Table</button>}<button type="button" className="booking-modal-cancel" onClick={() => setTableOpen(false)}>Close</button><button type="submit" className="booking-modal-submit">Save Table</button></div></form>
        </div></div>

        <div className={`modal-backdrop-custom ${areaOpen ? 'open' : ''}`} onClick={() => setAreaOpen(false)}><div className="booking-modal-card" onClick={(e) => e.stopPropagation()}>
          <div className="booking-modal-header"><h5><i className="fa fa-layer-group"></i> {fArea.mode === 'create' ? 'Add Area' : 'Area Details'}</h5><button type="button" className="booking-modal-close" onClick={() => setAreaOpen(false)}>&times;</button></div>
          {areaErr && <div className="modal-error" style={{ display: 'block' }}>{areaErr}</div>}
          <form onSubmit={submitArea}><div className="modal-form-group"><label>Area Name</label><input type="text" value={fArea.name} onChange={(e) => setFArea((x) => ({ ...x, name: e.target.value }))} required /></div><div className="modal-form-group"><label>Table Number Starts At</label><input type="number" min="1" value={fArea.start} onChange={(e) => setFArea((x) => ({ ...x, start: e.target.value === '' ? '' : Number(e.target.value) }))} /></div><div className="modal-form-group"><label>Table Number Ends At</label><input type="number" min="1" value={fArea.end} onChange={(e) => setFArea((x) => ({ ...x, end: e.target.value === '' ? '' : Number(e.target.value) }))} /></div><div className="booking-modal-actions">{fArea.mode === 'edit' && <button type="button" className="booking-modal-danger-small" onClick={delArea}>Delete Area</button>}<button type="button" className="booking-modal-cancel" onClick={() => setAreaOpen(false)}>Close</button><button type="submit" className="booking-modal-submit">Save Area</button></div></form>
        </div></div>
      </main>
    </AdminShell>
  );
}
