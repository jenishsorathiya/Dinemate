# DineMate UI/UX Audit

Last updated: 2026-05-17

## Goal

Polish DineMate into a modern product demo for Old Canberra Inn: clear page content, consistent visual language, responsive layouts, modal-first interactions where appropriate, and no leftover timeline-first user experience.

## Priority Checklist

- [x] Remove visible timeline-first navigation and replace admin booking creation with same-screen modal flow.
- [x] Extract page-level inline CSS and JavaScript into `assets/css/pages` and `assets/js/pages`.
- [x] Restore auth page imagery and fix admin sidebar/logout layout.
- [x] Consolidate duplicate global design tokens in `assets/css/app.css`.
- [x] Normalize landing page section hierarchy, copy, button contrast, and responsive hero imagery.
- [x] Replace unsupported pricing/payment language with demo-safe plan actions.
- [x] Improve customer dashboard labels, empty states, and booking workflow copy.
- [ ] Replace remaining admin inline `onclick` handlers with delegated JavaScript.
- [ ] Review admin table density, modal spacing, and responsive behavior page by page.
- [x] Re-run syntax, smoke, and visual checks after the first public/customer page group.

## High-Impact Findings

1. `assets/css/app.css` contains two `:root` token blocks. The second block overrides the intended Old Canberra Inn palette with a blue product palette, which makes pages drift visually.
2. `assets/css/pages/landing.css` uses dark-section heading colors globally. On light landing sections this can make section titles and cards feel inconsistent or low-contrast.
3. The landing page still uses generic SaaS language in places, including paid plan wording that is not backed by a payment feature. It should present demo-safe guest/member/group actions.
4. Some customer-facing labels are grammatically rough, for example "Upcoming And Rebook" and "Make A Booking".
5. Product pages now mostly use external assets, but admin pages still contain some inline click handlers. That is acceptable for the current UI pass, but should be cleaned in the next structure/security pass.

## Current Page Groups

- Public: `public/index.php`, `public/about.php`, `public/menu.php`, `public/contact.php`
- Auth: `auth/login.php`, `auth/register.php`, `admin/admin-login.php`
- Customer: `customer/dashboard.php`, `customer/book-table.php`, `customer/my-bookings.php`, `customer/profile.php`, `customer/modify-booking.php`, `customer/rate-booking.php`, `customer/confirmation.php`
- Admin: `admin/pages/*`, `admin/partials/*`, `admin/actions/*`

## Visual QA Checklist

- Desktop: 1440px wide, no hero/content overlap, CTAs visible, nav stable.
- Tablet: 768-991px, grids collapse cleanly, admin sidebar/header do not crowd content.
- Mobile: 390px wide, buttons wrap without clipped text, modals fit, forms remain easy to complete.
- Dark sections: headings, body text, secondary buttons, and muted labels meet readable contrast.
- Light sections: no inherited white headings or muted text that becomes invisible.
