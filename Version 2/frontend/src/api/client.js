function detectApiBaseUrl() {
  const fromEnv = import.meta.env.VITE_API_BASE_URL;
  if (fromEnv && fromEnv.trim() !== '') {
    return fromEnv.trim();
  }

  if (typeof window === 'undefined') {
    return 'http://localhost/Dinemate/Version%202/api/v1';
  }

  if (window.location.port === '5173' || window.location.port === '4173') {
    return 'http://localhost/Dinemate/Version%202/api/v1';
  }

  const pathname = window.location.pathname;
  const markers = ['/Version%202', '/Version 2'];
  let rootPath = '';
  for (const marker of markers) {
    const index = pathname.indexOf(marker);
    if (index >= 0) {
      rootPath = pathname.slice(0, index + marker.length);
      break;
    }
  }

  return `${window.location.origin}${rootPath}/api/v1`;
}

const API_BASE_URL = detectApiBaseUrl();

async function request(path, options = {}) {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    const message = data?.error || `Request failed (${response.status})`;
    throw new Error(message);
  }

  return data;
}

export const api = {
  health: () => request('/health'),
  menu: () => request('/menu'),
  tableAvailability: ({ date, startTime, guests }) =>
    request(`/public/table-availability?date=${encodeURIComponent(date)}&start_time=${encodeURIComponent(startTime)}&guests=${encodeURIComponent(String(guests))}`),
  authMe: () => request('/auth/me'),
  login: (payload) => request('/auth/login', { method: 'POST', body: JSON.stringify(payload) }),
  socialLogin: (provider) => request('/auth/social-login', { method: 'POST', body: JSON.stringify({ provider }) }),
  register: (payload) => request('/auth/register', { method: 'POST', body: JSON.stringify(payload) }),
  logout: () => request('/auth/logout', { method: 'POST' }),
  createBooking: (payload) => request('/bookings', { method: 'POST', body: JSON.stringify(payload) }),
  bookingConfirmation: (bookingId, token = '') =>
    request(`/bookings/${encodeURIComponent(String(bookingId))}/confirmation${token ? `?token=${encodeURIComponent(token)}` : ''}`),
  customerProfile: () => request('/customer/profile'),
  updateCustomerProfile: (payload) => request('/customer/profile', { method: 'PATCH', body: JSON.stringify(payload) }),
  updateCustomerPassword: (payload) => request('/customer/profile/password', { method: 'POST', body: JSON.stringify(payload) }),
  myBookings: () => request('/bookings/my'),
  updateMyBooking: (bookingId, payload) => request(`/customer/bookings/${bookingId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  cancelMyBooking: (bookingId) => request(`/customer/bookings/${bookingId}/cancel`, { method: 'POST' }),
  adminTimeline: (date) => request(`/admin/timeline?date=${encodeURIComponent(date)}`),
  adminPendingBookings: () => request('/admin/pending-bookings'),
  adminCreateBooking: (payload) => request('/admin/bookings', { method: 'POST', body: JSON.stringify(payload) }),
  adminScheduleBooking: (bookingId, payload) =>
    request(`/admin/bookings/${bookingId}/schedule`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminSetBookingStatus: (bookingId, payload) =>
    request(`/admin/bookings/${bookingId}/status`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminConfirmPendingBooking: (bookingId) => request(`/admin/bookings/${bookingId}/confirm-pending`, { method: 'POST' }),
  adminCancelBooking: (bookingId) => request(`/admin/bookings/${bookingId}/cancel`, { method: 'POST' }),
  adminUpdateBookingDetails: (bookingId, payload) =>
    request(`/admin/bookings/${bookingId}/details`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminUpdateBookingPlacement: (bookingId, payload) =>
    request(`/admin/bookings/${bookingId}/placement`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminAreas: () => request('/admin/areas'),
  adminCreateArea: (payload) => request('/admin/areas', { method: 'POST', body: JSON.stringify(payload) }),
  adminUpdateArea: (areaId, payload) => request(`/admin/areas/${areaId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminDeleteArea: (areaId) => request(`/admin/areas/${areaId}`, { method: 'DELETE' }),
  adminReorderAreas: (payload) => request('/admin/areas/order', { method: 'PATCH', body: JSON.stringify(payload) }),
  adminTables: (areaId) => request(`/admin/tables${areaId ? `?area_id=${encodeURIComponent(String(areaId))}` : ''}`),
  adminCreateTable: (payload) => request('/admin/tables', { method: 'POST', body: JSON.stringify(payload) }),
  adminUpdateTable: (tableId, payload) => request(`/admin/tables/${tableId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminDeleteTable: (tableId) => request(`/admin/tables/${tableId}`, { method: 'DELETE' }),
  adminMenuItems: () => request('/admin/menu-items'),
  adminCreateMenuItem: (payload) => request('/admin/menu-items', { method: 'POST', body: JSON.stringify(payload) }),
  adminUpdateMenuItem: (itemId, payload) => request(`/admin/menu-items/${itemId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminDeleteMenuItem: (itemId) => request(`/admin/menu-items/${itemId}`, { method: 'DELETE' }),
  adminUsers: () => request('/admin/users'),
  adminCreateUser: (payload) => request('/admin/users', { method: 'POST', body: JSON.stringify(payload) }),
  adminUpdateUser: (userId, payload) => request(`/admin/users/${userId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminDeleteUser: (userId) => request(`/admin/users/${userId}`, { method: 'DELETE' }),
  adminAnalyticsOverview: ({ dateFrom, dateTo, areaId = 'all' }) =>
    request(`/admin/analytics/overview?date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&area_id=${encodeURIComponent(String(areaId))}`),
  adminCustomerHistory: (search = '') =>
    request(`/admin/customer-history${search ? `?search=${encodeURIComponent(search)}` : ''}`),
  adminCustomerProfileDetail: (profileId) => request(`/admin/customer-history/${profileId}`),
  adminCustomerLinkableUsers: () => request('/admin/customer-history/linkable-users'),
  adminLinkCustomerProfile: (profileId, payload) => request(`/admin/customer-history/${profileId}/link-account`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminUnlinkCustomerProfile: (profileId) => request(`/admin/customer-history/${profileId}/unlink-account`, { method: 'POST' }),
  adminUpdateCustomerProfileNotes: (profileId, payload) => request(`/admin/customer-history/${profileId}/notes`, { method: 'PATCH', body: JSON.stringify(payload) }),
  adminMergeCustomerProfile: (profileId, payload) => request(`/admin/customer-history/${profileId}/merge`, { method: 'POST', body: JSON.stringify(payload) }),
  adminBookings: (filters = {}) => {
    const query = new URLSearchParams();
    if (filters.status) query.set('status', filters.status);
    if (filters.date) query.set('date', filters.date);
    const queryString = query.toString();
    return request(`/admin/bookings${queryString ? `?${queryString}` : ''}`);
  },
};
