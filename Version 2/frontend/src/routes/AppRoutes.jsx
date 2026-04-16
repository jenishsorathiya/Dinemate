import { Navigate, Route, Routes } from 'react-router-dom';
import { RequireAuth } from '../components/layout/AppLayout';
import { HomePage } from '../pages/public/HomePage';
import { AboutPage } from '../pages/public/AboutPage';
import { ContactPage } from '../pages/public/ContactPage';
import { MenuPage } from '../pages/public/MenuPage';
import { LoginPage } from '../pages/auth/LoginPage';
import { RegisterPage } from '../pages/auth/RegisterPage';
import { CustomerBookTablePage } from '../pages/customer/CustomerBookTablePage';
import { BookingConfirmationPage } from '../pages/customer/BookingConfirmationPage';
import { CustomerDashboardPage } from '../pages/customer/CustomerDashboardPage';
import { CustomerBookingsPage } from '../pages/customer/CustomerBookingsPage';
import { CustomerProfilePage } from '../pages/customer/CustomerProfilePage';
import { CustomerModifyBookingPage } from '../pages/customer/CustomerModifyBookingPage';
import { AdminAnalyticsPage } from '../pages/admin/AdminAnalyticsPage';
import { AdminTimelinePage } from '../pages/admin/AdminTimelinePage';
import { AdminBookingsPage } from '../pages/admin/AdminBookingsPage';
import { AdminMenuPage } from '../pages/admin/AdminMenuPage';
import { AdminTablesPage } from '../pages/admin/AdminTablesPage';
import { AdminUsersPage } from '../pages/admin/AdminUsersPage';
import { AdminCustomersPage } from '../pages/admin/AdminCustomersPage';
import { AdminUIKitPage } from '../pages/admin/AdminUIKitPage';

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<HomePage />} />
      <Route path="/public/index.php" element={<Navigate to="/" replace />} />
      <Route path="/about" element={<AboutPage />} />
      <Route path="/public/about.php" element={<Navigate to="/about" replace />} />
      <Route path="/contact" element={<ContactPage />} />
      <Route path="/public/contact.php" element={<Navigate to="/contact" replace />} />
      <Route path="/menu" element={<MenuPage />} />
      <Route path="/public/menu.php" element={<Navigate to="/menu" replace />} />

      <Route path="/book" element={<CustomerBookTablePage />} />
      <Route path="/book-table" element={<Navigate to="/customer/book-table" replace />} />
      <Route path="/customer/book-table" element={<CustomerBookTablePage />} />
      <Route path="/customer/booking-confirmation" element={<BookingConfirmationPage />} />

      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/auth/login" element={<LoginPage />} />
      <Route path="/auth/register" element={<RegisterPage />} />
      <Route path="/auth/social-login" element={<Navigate to="/auth/login" replace />} />

      <Route path="/dashboard" element={<RequireAuth role="customer"><CustomerDashboardPage /></RequireAuth>} />
      <Route path="/customer/dashboard" element={<RequireAuth role="customer"><CustomerDashboardPage /></RequireAuth>} />
      <Route path="/customer/my-bookings" element={<RequireAuth role="customer"><CustomerBookingsPage /></RequireAuth>} />
      <Route path="/customer/profile" element={<RequireAuth role="customer"><CustomerProfilePage /></RequireAuth>} />
      <Route path="/customer/modify-booking/:id" element={<RequireAuth role="customer"><CustomerModifyBookingPage /></RequireAuth>} />

      <Route path="/admin" element={<RequireAuth role="admin"><Navigate to="/admin/pages/analytics" replace /></RequireAuth>} />
      <Route path="/admin/overview" element={<RequireAuth role="admin"><AdminAnalyticsPage /></RequireAuth>} />
      <Route path="/admin/timeline" element={<RequireAuth role="admin"><AdminTimelinePage /></RequireAuth>} />
      <Route path="/admin/timeline/timeline" element={<RequireAuth role="admin"><AdminTimelinePage /></RequireAuth>} />
      <Route path="/admin/pages/timeline" element={<RequireAuth role="admin"><AdminTimelinePage /></RequireAuth>} />
      <Route path="/admin/pages/analytics" element={<RequireAuth role="admin"><AdminAnalyticsPage /></RequireAuth>} />
      <Route path="/admin/pages/bookings-management" element={<RequireAuth role="admin"><AdminBookingsPage /></RequireAuth>} />
      <Route path="/admin/pages/menu-management" element={<RequireAuth role="admin"><AdminMenuPage /></RequireAuth>} />
      <Route path="/admin/pages/tables-management" element={<RequireAuth role="admin"><AdminTablesPage /></RequireAuth>} />
      <Route path="/admin/pages/manage-users" element={<RequireAuth role="admin"><AdminUsersPage /></RequireAuth>} />
      <Route path="/admin/pages/customer-history" element={<RequireAuth role="admin"><AdminCustomersPage /></RequireAuth>} />
      <Route path="/admin/pages/ui-kit" element={<RequireAuth role="admin"><AdminUIKitPage /></RequireAuth>} />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
