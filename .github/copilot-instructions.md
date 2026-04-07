# Project Guidelines

## Architecture
- DineMate is a plain PHP + MySQL app intended to run under XAMPP at `htdocs/Dinemate`; there is no build step or package manager.
- Shared helpers live in `includes/functions.php`. Reuse existing auth, redirect, flash, sanitization, schema, and path helpers before adding new utilities.
- Customer-facing booking flows live in `bookings/` and typically return HTML with redirects and session flash/error messages.
- Admin JSON endpoints live in `admin/timeline/` and should return structured JSON responses with explicit HTTP status codes.

## Build And Test
- Use the browser-accessible setup and diagnostics utilities for local verification: `setup.php`, `auto-fix.php`, `diagnose.php`, and `test-db.php`.
- After changing booking, table, or timeline schema expectations, make sure the corresponding self-healing schema code still works and run `auto-fix.php` in a local XAMPP environment.
- Prefer targeted manual verification through the relevant entry points instead of inventing CLI build steps that do not exist in this repo.

## Conventions
- Use `appPath()` for application links and redirects so the app still works when installed in a subdirectory.
- Keep database access on PDO prepared statements and match the existing lightweight procedural style.
- Start or validate sessions before reading `$_SESSION`, and use `storeUserSession()`, `requireLogin()`, `requireAdmin()`, and `requireCustomer()` instead of duplicating role checks.
- For admin API endpoints, set `Content-Type: application/json`, use `requireAdmin(['json' => true])` when access must stay API-safe, and return payloads shaped like `['success' => bool, ...]`.
- Preserve the current schema-migration pattern: this codebase often adds or normalizes missing columns in PHP with idempotent checks instead of assuming all migrations were run manually.
- Customer booking creation currently writes pending bookings with `table_id = NULL`; admin assignment later confirms the booking and attaches the table. Do not assume a booking already has a table.
- When querying bookings for customer views, handle nullable `table_id` safely and show a fallback such as `Table assignment pending`.
- Keep the current validation approach: sanitize input, validate email and phone explicitly, and reject invalid or missing request data early.

## Key Files
- `includes/functions.php`: shared helpers and schema guards.
- `bookings/process-booking.php`: booking request validation and pending-booking creation flow.
- `admin/timeline/update-table.php`: example of the expected admin JSON endpoint pattern.
- `config/db.php`: canonical local database connection defaults.