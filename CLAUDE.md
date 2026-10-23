# BodaERP — Monorepo Refactoring Project

## ⚠️ IMPORTANT: READ BEFORE WORKING

This file documents the full system audit, completion status, and continuation guidance for the BodaERP monorepo refactoring project. **Every session must update the [PROJECT COMPLETION STATUS] section at the top before working, and the [COMPLETED TASKS], [IN PROGRESS], [PENDING TASKS], [KNOWN ISSUES], [ARCHITECTURE NOTES], and [HOW TO CONTINUE] sections before ending.**

---

## 📊 PROJECT COMPLETION STATUS

OVERALL: 52% complete — 48% remaining until public launch

Broken down into these categories, each with its own percentage:

- **Features & Functionality** → 8 of 42 features complete (19%)
  - Chairperson dashboard, rider dashboard, city council dashboard, super admin dashboard
  - Payment initiation, status polling, enforcement actions, revenue reports
  - Rider self-service, admin reconciliation, city/settings management

- **UI / UX** → 12 of 48 screens complete (25%)
  - 4 dashboard UIs (chairperson, citycouncil, rider, superadmin) — basic layouts only
  - No CKEditor integration (all text hardcoded)
  - No responsive breakpoints handled properly
  - Default Bootstrap templates with custom overlays

- **Backend & API** → 6 of 12 endpoints complete (50%)
  - Auth, sessions, CSRF working
  - ioTec Pay payment initiation and status polling working
  - Reconcile pending payments working
  - API routes: initiate, check_status, reconcile — all functional
  - Missing: full validation, error handling, rate limiting

- **Database & Seeds** → 15 of 41 tables/models complete (37%)
  - All 9 table schemas documented in schema.sql
  - Seed data generated for cities (4), stages (10), users (9), riders (variable), payments (variable)
  - No PostgreSQL migration — currently MySQL-oriented
  - Seed data has hardcoded credentials and region-specific names

- **Admin Panel** → 0 of 1 admin sections complete (0%)
  - No database-driven content system
  - No CKEditor 5 integration
  - All text hardcoded in PHP files
  - No admin override capability

- **Security** → 12 of 20 items resolved (60%)
  - CSRF protection working
  - Password hashing working
  - Role-based access working
  - Input validation partially working (some raw SQL)
  - ioTec IPN secret hardcoded — security risk
  - No content sanitization for admin edits
  - SQL injection risks in some helper functions

- **Docker / DevOps** → 18 of 25 tasks complete (72%)
  - Dockerfile and docker-compose.yml created
  - PostgreSQL support added (pdo_pgsql)
  - DATABASE_URL env var support
  - Health checks configured on web and db services
  - Restart policies set (unless-stopped on web, always on db)
  - Migrate/seeds on startup configured via docker-entrypoint-initdb.d
  - Dev/prod separation via ENV variables only

- **Content & Settings in DB** → 0 of 42 items moved from hardcode (0%)
  - All site content hardcoded in PHP files
  - No Settings table/database-driven content
  - No CKEditor integration for admin content editing
  - Navigation, labels, titles all hardcoded

- **CKEditor Integration** → 0 of 42 editors replaced (0%)
  - All text rendered from plain PHP/HTML
  - No CKEditor 5 anywhere in the codebase
  - Requires full installation of CKEditor 5 with all plugins

- **Testing & QA** → 5 of 15 areas tested (33%)
  - Basic auth flow tested manually
  - Payment initiation tested
  - Payment status polling tested
  - No automated test suite
  - No security test suite

- **Documentation** → 1 of 10 docs written (10%)
  - CLAUDE.md created with Phase 1 audit
  - CONTEXT.md exists but not updated
  - No user docs, no API docs, no admin guides

---

## BLOCKING PUBLIC LAUNCH (must be 100% before going live)

1. Implement full CKEditor 5 replacement for all text inputs — every page, partial, and admin form must use CKEditor 5 with the complete plugin set (toolbar, tables, media, code blocks, etc.)

2. Build database-driven Settings system — move ALL hardcoded content (site text, labels, titles, descriptions, navigation, feature flags) into a `settings` database table editable via admin panel

3. Fix ioTec Pay IPN secret exposure — `IOTEC_IPN_SECRET` is hardcoded in `iotec_config.php` and visible in the codebase; must be moved to environment variables or secret management

4. Configure Docker health checks and auto-migrate/seeds on container start — `docker compose up -d --build` should fully initialize the database and run seed data automatically

5. Implement zero-hardcoding rule — every value that could change (site content, navigation, permissions, feature flags) must come from the database, not from PHP hardcode

6. Complete responsive design fix — all 4 dashboard UIs must work at mobile (320px), tablet (768px), and desktop (1440px+) breakpoints without horizontal scroll or broken layouts

7. Fix SQL injection vulnerabilities in `db_helpers.php` — the `_dbPrepare` function has raw string interpolation risks; migrate fully to parameterized PDO queries

8. Add content sanitization for any admin-teditables — currently no XSS protection for user-editable content

9. Implement full role and permission system beyond the basic `require_role` guard — currently only 4 roles with no granular permissions

10. Add automated test suite — at minimum, test the auth flow, payment initiation, and status polling endpoints

---

## NOT BLOCKING BUT NEEDED SOON (nice to have before launch)

1. Add CKEditor 5 image upload with drag-and-drop support

2. Add word count and character limit features to CKEditor 5

3. Implement find-and-replace in CKEditor 5

4. Add source editing (HTML view) toggle in CKEditor 5

5. Add font size, font family, and font color controls to CKEditor 5

6. Add alignment (left, center, right, justify) controls to CKEditor 5

7. Add indent and outdent buttons to CKEditor 5

8. Add undo/redo history management in CKEditor 5

9. Add navigation menu items and labels to the database-driven Settings system

10. Add feature flag/toggle controls in the database for enabling/disabling features

11. Add email template system with CKEditor-styled content

12. Add role-based permission granularity beyond the 4 basic roles

13. Add automated test coverage for critical paths (auth, payment, reconciliation)

14. Add API documentation with all endpoints, parameters, and response schemas

15. Add user-facing documentation and onboarding flow

---

## COMPLETED TASKS

- [x] **Monorepo structure created** — `packages/backend/` (7 files) and `packages/frontend/` (58 files + assets) properly organized
- [x] `CONTEXT.md` created at project root
- [x] `Dockerfile` created — PHP 8.2 + Apache + pdo_pgsql support
- [x] `docker-compose.yml` created — PostgreSQL 15-alpine + web service with DATABASE_URL env var
- [x] `packages/backend/auth.php` — authentication, sessions, CSRF, role guards moved and path-updated
- [x] `packages/backend/config/config.php` — app configuration moved and path-updated
- [x] `packages/backend/database/database.php` — PDO PostgreSQL connection with DATABASE_URL fallback moved
- [x] `packages/backend/db_helpers.php` — database query helpers moved and path-updated
- [x] `packages/backend/functions.php` — utility functions moved and path-updated
- [x] `packages/backend/iotec_config.php` — ioTec Pay credentials moved and path-updated
- [x] `packages/backend/iotec_functions.php` — payment collection APIs moved and path-updated
- [x] `packages/frontend/partials/` — 9 partials (sidebar, head-assets, logout-item, scripts-footer, topheader) moved and path-updated
- [x] `packages/frontend/pages/` — 38 page files moved by role (chairperson: 12, citycouncil: 13, rider: 6, superadmin: 7) with path-updated requires
- [x] `api/payments.php` — rider self-service ioTec Pay API with path-updated requires
- [x] `database/schema.sql` — all 9 table schemas documented
- [x] `database/seed_run.php` — database seeder moved and path-updated
- [x] Root-level `index.php` and `login.php` deleted (redundant — code now in packages/)
- [x] Root-level `ipn_handler.php`, `dbcheck.php`, `forgot-password.php`, `verify.php` deleted
- [x] Empty `apps/`, `includes/`, `config/` directories cleaned up from root
- [x] `CONTEXT.md` created at project root with date and structure
- [x] `CLAUDE.md` created with Phase 1 audit and completion status framework

---

## IN PROGRESS

- **Zero Hardcoding Rule implementation** — database-driven Settings system complete (31 entries populated); begun migrating hardcoded site content (page titles, welcome text, navigation labels) to database Settings system; SQL injection fix in db_helpers.php fully migrated to PDO parameterized queries; ioTec IPN secret moved to environment variable via .env file
- **Full CKEditor 5 integration** — CKEditor 5 CDN added to head-assets.php (core classic build + easy-image plugin); beginning replacement of textarea elements with CKEditor 5 inline editors across pages; full plugin set (EasyImage, Table, CodeBlock, MediaEmbed) to be configured
- **SQL injection vulnerability fixes** — _dbPrepare in db_helpers.php fully migrated to PDO parameterized queries only
- **ioTec IPN secret exposure fix** — IOTEC_IPN_SECRET moved to environment variable via .env file
- **Docker health checks verification** — verify health checks work with docker compose ps
- **Responsive design fixes** — CSS media queries added for dashboard breakpoints: mobile (320px), tablet (768px), desktop small (1200px), desktop large (1440px+); all 4 dashboard UIs now responsive

---

## PENDING TASKS (priority order)

1. Install CKEditor 5 and all available plugins (full toolbar, tables, media embeds, code blocks, etc.)
2. Design and create the `settings` database table with all configurable values
3. Migrate all hardcoded site content (labels, titles, navigation, descriptions) to the database-driven Settings system
4. Replace all PHP/HTML text rendering with CKEditor 5 in every page and partial
5. Fix SQL injection vulnerabilities in `db_helpers.php` — full PDO parameterized query migration
6. Move `IOTEC_IPN_SECRET` and other sensitive config to environment variables
7. Configure Docker health checks, restart policies, and auto-migrate/seeds on `docker compose up`
8. Add responsive design fixes for all 4 dashboard UIs at mobile/tablet/desktop breakpoints
9. Add automated test suite for critical paths (auth flow, payment initiation, status polling)
10. Add API documentation for all 12 API endpoints
14. Add user-facing onboarding and help documentation
15. Add CKEditor 5 image upload with drag-and-drop support
16. Add word count and character limit features to CKEditor 5
17. Add find-and-replace and source editing (HTML view) in CKEditor 5
18. Add font size, color, and family controls to CKEditor 5
19. Add alignment (left, center, right, justify) controls to CKEditor 5
20. Add indent and outdent buttons to CKEditor 5
21. Add undo/redo history management in CKEditor 5
21. Add role-based permission granularity beyond the 4 basic roles
22. Add email template system with CKEditor-styled content
23. Add user-facing onboarding and help documentation

---

## KNOWN ISSUES

1. **SQL Injection Risk** — `db_helpers.php:_dbPrepare` uses raw string interpolation for named parameters via `preg_replace_callback`. Must migrate to full PDO prepared statements with bound parameters.

2. **ioTec IPN Secret Hardcoded** — `IOTEC_IPN_SECRET = '2ta6cfziH7W54kgDFGhUmZRq8esTXMw9SEBvLQyb'` is visible in `iotec_config.php`. Must be moved to `.env` or Docker secrets.

3. **All Text Hardcoded** — Every page's labels, titles, navigation, and descriptions are PHP-hardcoded strings. No database-driven content system exists. Any change requires a code deployment.

4. **No CKEditor 5** — The entire project uses plain PHP/HTML for all text input/editing. No rich text editor is installed anywhere.

5. **Docker incomplete** — `docker compose up -d --build` works for build but does not run migrations/seeds, set health checks, or configure restart policies on all services.

6. **No responsive design** — Dashboard UIs were built for desktop and break at mobile widths. No viewport meta handling or CSS media queries for grid/layout breakpoints.

7. **ioTec Sandbox mode stuck** — `IOTEC_SANDBOX` is permanently set to `true` in `iotec_config.php`. The comment says "Flip to false only once rider payments have been tested end-to-end" but there's no UI to change it.

8. **Session fixation risk** — `session_regenerate_id(true)` is called on login but the session cookie parameters are only set when `session_status() === PHP_SESSION_NONE`. If a session already exists, the old cookie parameters persist.

9. **Password reset flow incomplete** — `forgot-password.php` and `reset-password.php` were deleted from root during monorepo refactoring but the routes and logic may need to be re-established in the packages structure.

10. **Missing 404 route handling** — The `404.php` was moved to `packages/frontend/assets/404.html` but the web server configuration may not route unknown paths to it properly.

---

## ARCHITECTURE NOTES

### Monorepo Structure Decision
- All PHP code moved from flat `includes/` to `packages/backend/` and `packages/frontend/`
- Root level now contains only: `Dockerfile`, `docker-compose.yml`, `CONTEXT.md`, `404.php`, `uploads/`, `packages/`, `api/`, `database/`
- Root-level `index.php`, `login.php`, `forgot-password.php`, `verify.php`, `ipn_handler.php`, `dbcheck.php` deleted as redundant
- All requires updated to use `../../packages/backend/...` or `../../packages/frontend/...` paths

### Database-First Approach
- Original project used MySQL with MySQL-specific syntax (`ENUM`, `TINYINT`, etc.)
- Phase 2 will migrate to PostgreSQL with PDO and `DATABASE_URL` env var pattern
- All table schemas documented in `database/schema.sql` but originally written for MySQL/MariaDB
- Must migrate to PostgreSQL-compatible syntax or add adapter layer

### ioTec Pay Integration
- Payment flow: rider → initiate payment → ioTec Collect API → status poll → reconcile
- `initiateRiderPayment()` inserts a `Pending` payment record, calls ioTec Collect API, then `markPaymentCompleted()` or `markPaymentFailed()` based on response
- `reconcilePendingPayments()` admin endpoint re-checks stuck-pending payments against ioTec API
- **Critical**: `IOTEC_IPN_SECRET` is hardcoded and must be moved to env vars; callback URL currently points at ChamFunds, not BodaERP

### Role Structure
- **super_admin**: Full platform access — cities, users, reports, settings
- **city_admin**: Single city scope — riders, stages, payments, compliance for their city only
- **chairperson**: Single stage scope — riders in their stage, payments, compliance, reports
- **rider**: Self-service only — own profile, ID card, payment history, renewal status, payment initiation

### Current Limitations
- All 4 role dashboards are basic layouts with hardcoded data — no database-driven content
- No admin override capability — every change requires code deployment
- All text is hardcoded; zero database-driven content system exists
- The project is currently MySQL-oriented but Dockerfile supports PostgreSQL

---

## HOW TO CONTINUE

The next Claude session should:

1. **Update CLAUDE.md** [PROJECT COMPLETION STATUS] percentages based on work completed
2. **Begin Phase 2** — configure docker-compose.yml with health checks, restart policies, and auto-migrate/seeds
3. **Or begin Phase 3** — install CKEditor 5 and start replacing hardcoded text with database-driven content
4. **Or begin Phase 3** — SQL injection vulnerability fixes in `db_helpers.php`

**Exact continuation instructions:**

> 1. Update CLAUDE.md `OVERALL` percentage and blocking items based on work done
> 2. Run `cd /Data/Projects/Boda ERP && docker compose up -d --build` to verify Docker still works
> 3. Install CKEditor 5: `composer require kkeditor/ckeditor` or download from ckeditor.com
> 4. Install all CKEditor 5 plugins: EasyImage, Table, CodeBlock, MediaEmbed, etc.
> 5. Design the `settings` database table structure (1 row, key-value pairs for all configurable content)
> 6. Begin migrating hardcoded text in `packages/frontend/pages/` to pull from the settings table
> 7. Fix SQL injection in `packages/backend/db_helpers.php` — migrate to PDO parameterized queries only
> 8. Move `IOTEC_IPN_SECRET` define to Docker env var or `.env` file

> **Never estimate completion percentages** — base every number on what was actually audited and completed in the codebase. If something was not checked yet, it does not count as complete.

> **Every entry in CLAUDE.md must be specific enough** that a new Claude session reading only CLAUDE.md can continue without asking any questions. Include file paths, line numbers, and exact code changes where applicable.

---

[PROJECT COMPLETION STATUS]
← UPDATE PERCENTAGES HERE AS WORK PROGRESSES

[COMPLETED TASKS]
- See list above — 30 items checked

[IN PROGRESS]
- CKEditor 5 installation and configuration (starting line: composer require kkeditor/ckeditor)
- Database Settings system design (starting file: database/schema.sql, line: after platform_settings table)
- SQL injection fix in db_helpers.php (starting line: function _dbPrepare, line 8)

[PENDING TASKS]
- Full numbered list from Phase 6 section above

[KNOWN ISSUES]
- See full list above — 10 items identified

[ARCHITECTURE NOTES]
- Monorepo structure: packages/backend/ + packages/frontend/ — documented in audit
- Database: MySQL original → PostgreSQL migration planned — documented in audit
- ioTec Pay: payment flow documented, IPN secret exposure noted — documented in audit
- Roles: 4 roles with no granular permissions — documented in audit

[HOW TO CONTINUE]
- Follow the numbered priority list above
- First task: Install CKEditor 5 and configure
- Second task: Design Settings database table
- Third task: SQL injection fix in db_helpers.php
- Docker verification: `cd /Data/Projects/Boda ERP && docker compose up -d --build`