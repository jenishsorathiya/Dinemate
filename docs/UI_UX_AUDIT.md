# DineMate UI/UX Audit

Last updated: 2026-05-18

## Goal

Polish DineMate into a modern product demo for Old Canberra Inn: clear page content, consistent visual language, responsive layouts, modal-first interactions where appropriate, and no leftover timeline-first user experience.

## Priority Checklist

- [x] Remove visible timeline-first navigation and replace admin booking creation with same-screen modal flow.
- [x] Extract page-level inline CSS and JavaScript into `assets/css/pages` and `assets/js/pages`.
- [x] Restore auth page imagery and fix admin sidebar/logout layout.
- [x] Consolidate duplicate global design tokens in `assets/css/app.css`.
- [x] Remake public, auth, and customer-facing pages with the product-first guest experience system.
- [x] Remove unsupported pricing/payment language and old timeline-first public copy.
- [x] Improve customer dashboard labels, empty states, and booking workflow copy.
- [ ] Replace remaining admin inline `onclick` handlers with delegated JavaScript.
- [ ] Review admin table density, modal spacing, and responsive behavior page by page.
- [x] Re-run syntax, smoke, and visual checks after the first public/customer page group.

## High-Impact Findings

1. `assets/css/app.css` contains two `:root` token blocks. The second block overrides the intended Old Canberra Inn palette with a blue product palette, which makes pages drift visually.
2. The old landing/page-specific public CSS has been replaced by `assets/css/pages/guest-experience.css` for the new guest-facing redesign.
3. Public pages now use product-first DineMate copy, same-page CTAs, and restaurant imagery instead of unsupported SaaS/pricing language.
4. Customer-facing labels now use reservation language consistently across dashboard, booking, profile, confirmation, modify, and review flows.
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
