import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../state/AuthProvider';

export function AdminShell({
  title,
  children,
  centerContent = null,
  topAction = null,
  notificationCount = 0,
  profileName = 'Admin',
  pageIcon = 'fa-compass',
}) {
  const location = useLocation();
  const { logout } = useAuth();
  const navItems = [
    { to: '/admin/pages/analytics', icon: 'fa-chart-line', label: 'Analytics' },
    { to: '/admin/pages/timeline', icon: 'fa-calendar-days', label: 'Timeline' },
    { to: '/admin/pages/bookings-management', icon: 'fa-clipboard-list', label: 'Bookings' },
    { to: '/admin/pages/tables-management', icon: 'fa-chair', label: 'Tables' },
    { to: '/admin/pages/menu-management', icon: 'fa-utensils', label: 'Menu' },
    { to: '/admin/pages/manage-users', icon: 'fa-users', label: 'Users' },
    { to: '/admin/pages/customer-history', icon: 'fa-address-book', label: 'Customers' },
    { to: '/admin/pages/ui-kit', icon: 'fa-palette', label: 'UI Kit' },
  ];
  return (
    <div className="admin-layout-shell">
      <div className="sidebar-shell">
        <div className="sidebar">
          <h4><i className="fa fa-utensils"></i><span className="brand-label">DineMate</span></h4>
          {navItems.map((item) => (
            <Link key={item.to} to={item.to} className={location.pathname.startsWith(item.to) ? 'active' : ''}>
              <i className={`fa ${item.icon}`}></i><span className="nav-label">{item.label}</span>
            </Link>
          ))}
          <button className="admin-logout-link" onClick={logout}><i className="fa fa-sign-out-alt"></i><span className="nav-label">Logout</span></button>
        </div>
      </div>
      <div className="main-content">
        <div className="topbar">
          <div className="topbar-left">
            <div className="topbar-page">
              <i className={`fa ${pageIcon}`}></i>
              <span className="topbar-page-title">{title}</span>
            </div>
          </div>
          <div className="topbar-center">
            {centerContent}
          </div>
          <div className="topbar-right">
            {topAction}
            <button type="button" className="topbar-icon-button" aria-label="Notifications">
              <i className="fa fa-bell"></i>
              {Number(notificationCount) > 0 && <span className="topbar-badge">{notificationCount > 99 ? '99+' : notificationCount}</span>}
            </button>
            <div className="topbar-profile" aria-label="Profile">
              <span className="topbar-profile-icon"><i className="fa fa-user-circle"></i></span>
              <span className="topbar-profile-name">{profileName}</span>
            </div>
          </div>
        </div>
        <div className="admin-page-shell">{children}</div>
      </div>
    </div>
  );
}
