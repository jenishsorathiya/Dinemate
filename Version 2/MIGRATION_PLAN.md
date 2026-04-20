# DineMate Migration Plan (React Frontend + PHP API Backend)

## What was added now
- `api/` front controller with versioned routes under `/api/v1/*`.
- Modular API route files for:
  - auth (`/auth/*`)
  - public catalog + booking intake (`/menu`, `/bookings`, availability)
  - customer profile + bookings (`/customer/*`, `/bookings/my`)
  - admin timeline/areas/tables (`/admin/*`)
- `frontend/` React SPA (Vite + React Router):
  - public routes: home/menu/booking
  - auth routes: login/register
  - protected customer dashboard
  - protected admin timeline view

## Recommended architecture target
- Frontend: React SPA (eventually route-based: public, customer, admin areas).
- Backend: PHP API only (JSON), no server-rendered pages.
- Auth: secure cookie session now, optional later move to JWT/refresh-token strategy.
- Data access: separate service/repository layer over time, away from page scripts.

## Phased migration path
1. Freeze new feature work in legacy PHP views when possible.
2. Extract shared domain logic from page scripts into reusable PHP services.
3. Add API endpoints module-by-module:
   - auth/profile
   - customer bookings
   - admin timeline
   - table management
   - menu management
   - analytics
4. Point React pages to API endpoints as each module is completed.
5. Keep old pages available behind a temporary fallback route until parity is reached.
6. Remove legacy view layer after full parity and QA sign-off.

## Production hardening checklist
- Add CSRF protection for cookie-authenticated state-changing endpoints.
- Add centralized API error handler + request/trace IDs.
- Add input validation layer for all endpoints.
- Add rate limiting and login brute-force protection.
- Move DB credentials to environment variables.
- Add database migrations runner and deployment pipeline.
- Add tests:
  - API integration tests for each route
  - frontend component and e2e tests
- Add logging/monitoring and backup strategy.

## Local run instructions
1. Ensure XAMPP Apache + MySQL are running.
2. Use the Version 2 project root at `http://localhost/Dinemate/Version%202`.
3. API base URL:
   - `http://localhost/Dinemate/Version%202/api/v1`
4. Frontend dev setup:
   - `cd "Version 2\frontend"`
   - `copy .env.example .env` (Windows)
   - `npm install`
   - `npm run dev`
5. Open React app at `http://localhost:5173`.
6. Frontend production build served by Apache from Version 2:
   - `cd "Version 2\frontend"`
   - `npm run build`
   - open `http://localhost/Dinemate/Version%202/`

## Immediate next migration candidates
- Convert `customer/dashboard.php` + `customer/my-bookings.php` to React routes.
- Convert `admin/timeline/*.php` AJAX endpoints into `/api/v1/admin/timeline/*`.
- Build a single API auth middleware strategy for admin/customer route groups.
