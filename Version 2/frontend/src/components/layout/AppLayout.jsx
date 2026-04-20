import { Link, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../state/AuthProvider';

export function RequireAuth({ role, children }) {
  const { loading, authenticated, user } = useAuth();
  if (loading) return <p className="muted">Loading session...</p>;
  if (!authenticated) return <Navigate to="/auth/login" replace />;
  if (role && user?.role !== role) return <Navigate to="/" replace />;
  return children;
}

export function Layout({ children }) {
  const { user, authenticated, logout } = useAuth();
  const location = useLocation();
  const isAdminRoute = location.pathname === '/admin' || location.pathname.startsWith('/admin/');
  const isCustomer = authenticated && user?.role === 'customer';
  const marketingFullBleedPaths = ['/', '/about', '/contact', '/public/index.php', '/public/about.php', '/public/contact.php'];
  const isMarketingFullBleed = marketingFullBleedPaths.includes(location.pathname);
  const footerLinks = isCustomer
    ? [
        { label: 'Dashboard', to: '/customer/dashboard' },
        { label: 'Home', to: '/' },
        { label: 'Book', to: '/customer/book-table' },
        { label: 'My Bookings', to: '/customer/my-bookings' },
        { label: 'Profile', to: '/customer/profile' },
      ]
    : [
        { label: 'Home', to: '/' },
        { label: 'Book a Table', to: '/customer/book-table' },
        { label: 'Login', to: '/auth/login' },
        { label: 'Register', to: '/auth/register' },
        { label: 'Contact Us', to: '/contact' },
      ];
  return (
    <div className="app-shell">
      {!isAdminRoute && (
        <nav className="navbar navbar-modern navbar-expand-lg">
          <div className="container-fluid">
            <Link className="logo" to="/">DineMate</Link>
            <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
              <span className="navbar-toggler-icon"></span>
            </button>
            <div className="collapse navbar-collapse justify-content-end" id="navMenu">
              <div className="nav-links">
                {authenticated && user?.role === 'customer' ? (
                  <>
                    <Link to="/customer/dashboard">Dashboard</Link>
                    <Link to="/">Home</Link>
                    <Link to="/menu">Menu</Link>
                    <Link className="btn btn-book" to="/customer/book-table"><i className="fa fa-calendar-check"></i> Book</Link>
                    <Link to="/customer/my-bookings">My Bookings</Link>
                    <Link to="/customer/profile">Profile</Link>
                    <button className="btn btn-logout" onClick={logout}>Logout</button>
                  </>
                ) : (
                  <>
                    <Link to="/">Home</Link>
                    <Link to="/menu">Menu</Link>
                    <Link to="/about">About</Link>
                    <Link to="/contact">Contact</Link>
                    <Link className="btn btn-book" to="/customer/book-table"><i className="fa fa-calendar-check"></i> Book a Table</Link>
                    {!authenticated && <Link to="/auth/login">Login</Link>}
                    {authenticated && user?.role === 'admin' && <Link to="/admin/pages/analytics">Admin</Link>}
                    {authenticated && <button className="btn btn-logout" onClick={logout}>Logout</button>}
                  </>
                )}
              </div>
            </div>
          </div>
        </nav>
      )}

      <main className={isAdminRoute ? 'admin-host-shell' : (isMarketingFullBleed ? 'page-shell page-shell-marketing' : 'page-shell')}>
        {children}
      </main>

      {!isAdminRoute && (
        <footer className="footer">
          <div className="container">
            <div className="row">
              <div className="col-md-4 footer-brand">
                <h4>DineMate</h4>
                <p>Modern reservation support for Old Canberra Inn. Book faster, manage visits clearly, and keep dining service running smoothly.</p>
                <div className="social-icons">
                  <a href="#"><i className="fab fa-facebook"></i></a>
                  <a href="#"><i className="fab fa-twitter"></i></a>
                  <a href="#"><i className="fab fa-instagram"></i></a>
                  <a href="#"><i className="fab fa-linkedin"></i></a>
                </div>
                <button onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })} className="back-top">↑ Back to Top</button>
              </div>
              <div className="col-md-4">
                <h5>Site Map</h5>
                <ul>
                  {footerLinks.map((item) => (
                    <li key={`${item.label}-${item.to}`}><Link to={item.to}>{item.label}</Link></li>
                  ))}
                  {authenticated && <li><button className="btn btn-link p-0 footer-logout-link" onClick={logout}>Logout</button></li>}
                </ul>
              </div>
              <div className="col-md-4">
                <h5>Legal</h5>
                <ul>
                  <li><a href="#">Privacy Policy</a></li>
                  <li><a href="#">Terms of Service</a></li>
                  <li><a href="#">Restaurant Policies</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div className="footer-bottom">© 2026 Old Canberra Inn - Powered by DineMate</div>
        </footer>
      )}
    </div>
  );
}

