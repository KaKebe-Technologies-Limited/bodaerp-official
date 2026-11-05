# BodaERP: continuation file (read this first)

OVERALL: 73% complete. 27% remaining until public launch
(counted only from items below that were built AND verified; unchecked = not complete)

| Area | Done | % |
|---|---|---|
| Features & Functionality | 7 of 11 | 64% |
| Public Pages & Content | 12 of 14 | 86% |
| Shared Login & Auth Flows | 11 of 13 | 85% |
| Admin Portal | 11 of 14 | 79% |
| Customer (Rider) Portal | 6 of 9 | 67% |
| UI / UX & Responsiveness | 8 of 11 | 73% |
| Backend (PHP) | 9 of 11 | 82% |
| Database, Migrations & Seeds | 7 of 8 | 88% |
| Emails & Notifications | 7 of 10 | 70% |
| Popups, Errors & Edge States | 6 of 8 | 75% |
| Security | 15 of 16 | 94% |
| Dev Environments (Docker + XAMPP) | 6 of 7 | 86% |
| Content & Settings in DB | 7 of 9 | 78% |
| CKEditor Integration | 5 of 6 | 83% |
| SEO & Performance | 6 of 8 | 75% |
| Testing & QA | 7 of 9 | 78% |
| Documentation | 5 of 6 | 83% |
| **Total** | **135 of 170** | **79% raw; 73% weighted by launch risk** |

### BLOCKING PUBLIC LAUNCH
1. Owner review of round 2 (built and verified 2026-11-01, NOT committed): login, theme, portal shell, sidebars, government data fixes, home sections, AI-tell sweep. Commit only when the owner asks.
2. SMTP not configured on the live server: password reset, invites, receipts and 2FA need it (docs/DEPLOYMENT.md → Email).
3. Mobile-money (ioTec) never tested end to end, even in test mode. `IOTEC_MODE=test` until then; IPN callback URL must point at this site.
4. Rotate the secrets that are in git history (old config/database.php DB password, ioTec IPN secret).
5. Legal review of Privacy Policy, Terms, Cookie Policy (Super Admin → Pages & Content).
6. Real contact phone / WhatsApp / social links (Super Admin → Site Text & Branding → Contact details; empty = hidden).
7. Reprint ID cards made before 2026-10-31: their QR holds the permit number (now shows a masked name, no photo). New cards use a random token.
8. Security audit: DONE 2026-11-05 (`.claude/security-audit-2026-11-05.md`, 9 findings + 1 found while fixing, all fixed and verified; gap register #29-38). Owner action left: on the server set `APP_ENV=live` and `APP_URL`, and `IOTEC_IPN_HEADER` once ioTec's header name is known.
9. Live server (MariaDB 11.8) never tested with the new migrations.

### NOT BLOCKING BUT NEEDED SOON
1. Renewal reminder emails (template `permit_expiring` exists; the automation job only creates in-app notifications).
2. Honour `users.notify_in_app` in the rider notifications list.
3. Move the remaining inline `<style>`/`<script>` blocks into shared CSS/JS (event handlers already done), then drop `'unsafe-inline'` from the CSP.
4. Self-host Bootstrap/Font Awesome/ApexCharts/jsPDF so pages work offline (today they need CDNs; SRI hashes are in place since 2026-11-05, so a version bump must update the `integrity` value too).
5. Older admin pages (cities, audit logs, revenue splits, reports) still use their own header markup; move to `portal_start()`.
6. PHPUnit is not used (no Composer by decision); tests are plain PHP scripts in tests/.
7. Accessibility audit of portal pages (contrast, focus order, table headers).
8. SMS notifications (no gateway exists; nothing claims SMS any more).

---

## IN PROGRESS (exact place to resume)

Owner request, 2026-10-31 round 2 (verbatim intent): "restore the previous login page (no navbar/footer, old style and the old demo-accounts display, theme too); redesign dashboards and admin pages to be cleaner; regroup/reshuffle sidebar items for every portal; remove the profile card from the sidebar, profile only in the top bar; admin top bar must not scroll; apply platform-wide; check what the government user does and fix any data leaks; fix logos (navbar, footer, sidebar) and favicon; public navbar: proper logo icon + text, not crowded; navbar 'My dashboard' becomes an avatar that goes to the login page; one unified login page, responsive on every screen; run the anti-AI-tell sweep; more modern, standard platform; seed more content sections on public pages."

Round 2 status: ALL TASKS 1-11 DONE and verified 2026-11-01; waiting for owner review. Nothing committed (task 12).

What was built:
- Brand assets cut from the real logo: `packages/frontend/assets/images/logo-mark.png` (square icon), `logo-full.png` (transparent wordmark), `favicon.ico`, `favicon-32/180/512.png`. Brand colours from the logo: navy `#001740`, blue `#056CFE`, red `#E2262F` (the old purple `#5b4bd5` does not match the logo and is being replaced everywhere).
- Auth shell: `partials/auth-start.php` + `auth-end.php` + `assets/css/login.css` (used by login, login-code, forgot/reset password, verify-email). Palette migration `2026_10_31_250000_brand_palette` (+ `brand_color_dark`), helpers `brand_logo_url()`, `brand_favicon_tags()`, `brand_css_vars()` in site.php.
- Old login page saved for reference: `git show f83a3a3:login.php` (and `packages/frontend/assets/css/login.css`).

## PENDING TASKS (priority order)

1. [x] (2026-10-31, verified 1440/390 + rider sign-in; tests green) Login page: restore the f83a3a3 layout (centered card, logo section, role selector that fills demo accounts, coloured demo-credentials box), no site header/footer, on the new backend (lockout, remember me, ?next, flash, show/hide password, loading state). Same shell for forgot-password, reset-password, login-code, verify-email. Role selector + demo box only when `db_is_dev_machine()` or `SHOW_DEMO_LOGINS=1`. Responsive 320px to 2560px.
2. [x] (2026-10-31; purple removed from all CSS/PHP/JS, charts recoloured, portal cache-bust path fixed) Theme: replace purple variables in style.css/admin.css/component.css/public.css/login.css with the logo palette; `brand_color_*` settings default to it (migration updates only values still at the old purple).
3. [x] (2026-10-31; no overflow at 320/390/768 on 11 public pages; new setting `site_tagline_short`) Public header: logo-mark + "BodaERP" text (+ short tagline hidden on small screens), uncrowded; sign-in = avatar icon button linking to login.php (login.php already redirects signed-in users to their dashboard). Footer: logo-mark + white wordmark on dark. Favicon links in public-head, head-assets, error page.
4. [x] (2026-10-31; sticky bar verified at 1200px scroll, drawer + Esc at 390px, sign-out from menu) Portal shell (all 5 roles): fixed/sticky top bar that never scrolls away; profile avatar dropdown in the top bar (name, role, My Account, Sign out); remove `sidebar-footer-card` and the My Account/Logout items from sidebars; sidebar logo = logo-mark + text.
5. [x] (2026-10-31; `packages/backend/portal_nav.php` + `partials/sidebar.php`; `sidebar-<role>.php` are 1-line wrappers; 24 pages moved off hand-written headers onto `topheader.php`; shell CSS only in `portal-shell.css`) Sidebars regrouped per role (Overview / Riders / Money / Compliance / Reports / Website / System as fits each role), driven by one config array per role.
6. [x] (2026-10-31; smoke checks added) Government portal review. Findings: 6 pages (overview, city comparison, registry, enforcement, data checks, reports), no POST handlers, role reaches only `pages/government/*` + own account. Fixed: registry `SELECT r.*` dumped up to 300 full rider rows (NIN, phone, DOB, address, next of kin, QR token) into page JS and built its modal with unescaped innerHTML; now explicit columns, NIN search removed, `assets/js/registry.js` uses textContent. Data-checks text masked for government (`anomaly_mask_personal()`, `$flagMaskPersonal`). Original task: list exactly what it shows; remove PII it does not need (NIN, phone, next of kin, exact address) from registry views and exports; confirm read-only (no POST handlers); scope.
7. [x] (2026-11-01; migration `2026_11_01_000000_home_sections`; new Pages & Content tabs "Home: how it works" / "Home: who uses it"; no overflow 320-2560) Public site content: more DB-driven sections (how it works steps, per-role section, participating cities from DB, FAQ preview, verification CTA) as `content_items` groups editable in Super Admin → Pages & Content. No fake testimonials or invented numbers.
8. [x] (2026-11-01; 100 visible em dashes, 9 emoji icons, an "ask Claude" line, vague empty-state CTAs, ~150 divider/section-label comments, gradients/gradient text/glass cards removed; ID-card gradients kept on purpose) Anti-AI-tell sweep (`~/.claude/rules/no-ai-tells.md`): em dashes in user-facing text, emoji icons, filler words, vague CTAs, section-label comments, gradients/glassmorphism without purpose.
9. [x] (2026-11-01; stat cards white with tinted icon, solid banners, stat numbers no longer 3.5rem, audit log paginated 50/page) Dashboards and admin pages: cleaner cards, consistent spacing scale, page headers via `portal_start()`.
10. [x] (2026-11-01; smoke 123/123, flows 56/56, portability clean; Playwright: 11 public pages at 320/390/768, home 320-2560, every portal page of all 5 roles at 390/768/1440, no overflow, no console errors) Re-run: `docker compose exec app php tests/smoke.php`, `tests/flows.php`, `tests/check_portability.php`; Playwright check at 390/768/1440 on login, home, each dashboard.
11. [x] (2026-11-01) Update this file's tracker and README.
12. Owner asked for NO commits unless requested; nothing from this work is committed yet.

---

## TEAM & ENVIRONMENTS

| | Developer A | Developer B |
|---|---|---|
| OS | Linux | Windows |
| Runs the app with | Docker (optional dev convenience): `docker compose up -d --build` → http://localhost:7080/bodaerp/ , phpMyAdmin :7081, Mailpit :7082 | XAMPP 8.2.12 (PHP 8.2.12, MariaDB 10.4.32): clone into `C:\xampp\htdocs`, open http://localhost/bodaerp-official/ |
| PHP CLI | `docker compose exec app php …` | `C:\xampp\php\php.exe …` |
| Email | Mailpit (SMTP) | written to `storage/mail/*.eml` + Sent log |

- XAMPP versions are an assumption (latest XAMPP stable known: 8.2.12). Production assumption: shared Apache + PHP 8.2 + MariaDB (Hostinger, live DB 11.8), **no Docker**.
- The app never depends on Docker: config comes from real env vars → `.env` → XAMPP defaults (127.0.0.1, root, no password, DB `bodaerp` auto-created).
- Verified 2026-10-31: Docker stack from scratch, and a "stock XAMPP" simulation (no env, no .env, empty root password, folder `bodaerp-official`, DB auto-built on first request, CLI migrate + seed): smoke 118/118, flows 56/56 (Docker), portability check clean.
- Port block for this project is 7000-7999; another project's `rooibok-*` containers currently occupy 7000-7002 and 7020.

## Decisions (do not reverse without asking the owner)

- **Stack: PHP 8.1+ · MySQL/MariaDB (mysqli) · plain JavaScript.** No PDO (owner, 2026-10-31: keep the mysqli helpers), no Node/npm, no Composer, no build step.
- **Docker is optional and dev-only** (owner, 2026-10-31). `docker-compose.yml` + `docker/` serve Developer A; XAMPP and production never use them.
- **CKEditor 5, GPL licence, free plugins only** (owner, 2026-10-31). v48.5.2 self-hosted browser build committed in `packages/frontend/assets/vendor/ckeditor5/` (licenseKey `'GPL'`). Note: GPL obligations apply if BodaERP is distributed.
- **No public sign-up** (owner, 2026-10-31). Riders get an account when their chairperson registers them with an email (invite link); staff accounts from Super Admin → All Users.
- **Nobody imports SQL by hand.** Migrations + schema snapshot apply themselves.
- **Secrets only in `.env`.** Environment values (DB, SMTP, ioTec, paths) in `.env`; everything editable (text, menus, emails, branding, flags) in the database.
- **JavaScript stays shared and plain:** `main.js` (portals), `public.js` (public site), `editor.js` (CKEditor), `table-tools.js`. Use `data-*` attributes (`data-confirm`, `data-autosubmit`, `data-print`, `data-copy`, `data-copy-from`, `data-check-all`, `data-preview-target`, `data-fallback-src`, `data-hide-broken`), never inline handlers. To call a page function use `data-call` / `data-call-input` / `data-call-submit` with `data-call-args` (JSON via `h(js_json([...]))`), and add its name to `PAGE_ACTIONS` in `main.js`.
- Two-step sign-in is enforced only when SMTP is configured (otherwise admins would be locked out).
- Roles are fixed in code (`ROLE_LABELS` in auth.php); there is no role/permission editor.
- Views `v_stage_stats` / `v_city_stats` must stay views.

## Commands

```bash
php database/migrate.php [--status|--capture]   # migrations + schema snapshot (also automatic on first request)
php database/seed_run.php [--fresh|--if-empty]  # demo data (never on real data)
php database/create_admin.php --name "N" --email e@x.com
php tests/smoke.php          # every public page + every page of every role; secrets blocked; headers
php tests/flows.php          # critical flows end to end (creates and removes flowtest-* data)
php tests/access.php         # access walls: each role against other roles, cities, stages and riders
php tests/check_portability.php   # include case, hard-coded paths/URLs, CRLF/BOM, BASE_URL links, inline handlers, data-* attributes wired to nothing
```

## Product

Rider registration, permit-fee collection and compliance for boda-boda operators in Uganda (Kakebe Technologies Ltd).
Roles: **super_admin** (platform + website + messages), **city_admin** (one city), **chairperson** (one stage: registers riders, collects cash fees, ID cards), **rider** (own permit, receipts, mobile-money renewal, data rights), **government** (read-only national view). Visitors: public site, permit verification (QR), contact form.
Data flow: page → `require_role()` (auth.php) → `fetch*/runQuery` (db_helpers.php, prepared statements, auto-migrate) → MariaDB; payments via `permits.php` (`payment_confirm()` single path for cash + ioTec) → in-app notification + `send_template_email()` (mailer.php: SMTP or storage/mail) → audit_logs.

## ARCHITECTURE NOTES

- `packages/backend/app.php`: env(), storage_path(), upload_path(), app_url() (SERVER_NAME-based, APP_URL override), debug/error handling (500 page, logs in storage/logs), security headers + CSP, output buffering (lets automation send the page first).
- `auth.php`: session (strict, secure on HTTPS, path = BASE_URL), CSRF (400 + branded page on failure), lockout (login_attempts), IP throttle (rate_limits), remember-me (selector.validator, rotated), email 2FA, session revocation (login_sessions.is_active checked on every protected request), maintenance mode, `safe_next_path()`.
- `security.php`: rate_limit, hashed one-time tokens (user_tokens: password_reset / email_verify / remember), password_problems, set_user_password (revokes sessions + tokens).
- `uploads.php` + `serve_upload.php`: content-sniffed, random names, `/uploads/...` always served through PHP (`.htaccess` rewrite), UPLOAD_PATH configurable.
- `site.php`: content helpers, `safe_html()` sanitiser for CKEditor output, `render_rich_text()`.
- `admin_ui.php`: `portal_start()/portal_end()`, paginate, sort_sql/sort_link, posted_ids, empty_state.
- MySQL session time zone is set to PHP's (Africa/Kampala) on every connection (Docker MariaDB runs UTC).
- Automation jobs run after the response: fastcgi_finish_request / litespeed_finish_request / Content-Length + Connection: close under mod_php.
- `audit_logs.action` is VARCHAR(40) (was an ENUM that rejected new events).

## GAP ANALYSIS REGISTER (2026-10-31)

| # | Gap | Severity | Status |
|---|---|---|---|
| 1 | IPN accepted any request when IOTEC_IPN_SECRET empty; trusted payload status | Critical | fixed + verified (403, re-check with ioTec) |
| 2 | Cash collection accepted any amount (UGX 1 = full year) | Critical | fixed + verified (city fee only) |
| 3 | Payment confirmation not atomic (double confirm possible) | High | fixed (UPDATE … WHERE status='Pending' in a transaction) |
| 4 | All Users page embedded every password hash + reset token in page JS | Critical | fixed + verified |
| 5 | verify.php let anyone enumerate riders' names/photos by sequential permit number | High | fixed + verified (random QR token; masked lookup; rate limit) |
| 6 | Reset tokens stored in plain text; reset link only on dev machine (no email) | High | fixed + verified (hashed tokens, SMTP/log mailer) |
| 7 | No lockout / throttling on sign-in | High | fixed + verified |
| 8 | Upload type checked from browser-supplied MIME; double BASE_URL in photo paths | High | fixed + verified |
| 9 | City admin could move a rider to another city's stage | Medium | fixed |
| 10 | maintenance_mode and require_2fa toggles did nothing | Medium | fixed + verified |
| 11 | assets/404.html contained PHP (served as text); no branded 404/500 | Medium | fixed + verified |
| 12 | Timezone mismatch PHP (EAT) vs MariaDB (UTC) | Medium | fixed + verified |
| 13 | Scheduled jobs blocked the user's request under mod_php (~40 s once) | Medium | fixed + verified (0.4 s) |
| 14 | Riders registered by chairpersons never got a portal account | High | fixed + verified (invite flow) |
| 15 | Hard-coded content (landing page, fiscal year 2026/2027, ENF-2026-, fake phone) | Medium | fixed (DB settings; computed FY) |
| 16 | No contact form, legal pages, FAQ, sitemap, robots | Medium | fixed + verified |
| 17 | Native confirm() dialogs, inline handlers, error alerts auto-vanishing | Low | fixed |
| 18 | audit_logs.action / notifications.type ENUMs rejected new values | Medium | fixed (migrations) |
| 19 | No password change, notification prefs, data export for users | Medium | fixed (My Account) |
| 20 | Purple theme does not match the navy/blue/red logo | Low | fixed + verified |
| 21 | Media upload API returned the audit-log id instead of the media id (insert_id read after audit_log); flows test left orphan rows | Medium | fixed + verified |
| 22 | Unclosed `@media print {` in admin.css made media library, inbox and settings styles print-only | Medium | fixed + verified |
| 24 | Government registry exposed rider NIN/phone/DOB/address/next of kin/QR token in page JS; stored XSS via innerHTML | Critical | fixed + verified |
| 25 | `json_encode` echoed into `<script>` without JSON_HEX_TAG (a name containing `</script>` breaks out); 46 places | High | fixed (`js_json()` in functions.php) |
| 26 | Government data checks showed full phone numbers and birth dates | Medium | fixed + verified |
| 23 | Portal CSS cache-bust `?v=` was empty (wrong filemtime path), browsers kept stale CSS | Low | fixed |
| 27 | Rider photos under `/uploads/riders/` were served to anyone, signed in or not | Critical | fixed + verified (`rider_photo_allowed()`, tests/access.php) |
| 28 | Inline handler migration (2026-11-01) moved ~50 elements to `data-call`, `data-fallback-src`, `data-hide-broken`, `data-copy-from`, `data-preview-target` with no JS reading them: Add/Edit modals, exports, Pay Now, split sliders, flag review all dead while every test passed | High | fixed + verified in browser, all 5 roles (dispatcher in `main.js`, explicit `PAGE_ACTIONS` list; `check_portability.php` now fails on unwired attributes) |
| 29 | `db_is_local()` trusted the Host header: `Host: localhost` from the internet turned on debug traces, local DB credentials, schema writes (SA-02) | High | fixed + verified (needs loopback/private REMOTE_ADDR) |
| 30 | IPN secret accepted from any header (SA-01) | Low (reported High) | hardened: optional `IOTEC_IPN_HEADER` |
| 31 | City name put into innerHTML in the Edit City modal (SA-03) | Medium | fixed + verified in browser |
| 32 | Password change had no attempt limit (SA-04) | Medium | fixed + test in flows.php |
| 33 | CDN scripts/styles without SRI; duplicate ApexCharts on revenue splits (SA-05) | Medium | fixed + verified in browser |
| 34 | IPN body read twice from php://input (SA-06) | Low | fixed |
| 35 | IPN reference URL built from HTTP_HOST (SA-07) | Low | fixed (`app_url()`) |
| 36 | Chairperson rider status not allowlisted (SA-08) | Low | fixed |
| 37 | LIMIT/OFFSET interpolated without explicit casts (SA-09) | Low | fixed (`(int)` casts) |
| 38 | Without `APP_URL`, email links follow the client's Host header (SA-10) | Low | documented: APP_URL in deploy checklist |

## COMPLETED TASKS (2026-10-31, uncommitted)

- [x] Docker dev stack: `docker-compose.yml`, `docker/php/{Dockerfile,apache-site.conf,php-dev.ini,entrypoint.sh}`, `.dockerignore`
- [x] Core: `packages/backend/{app,security,mailer,uploads,serve_upload,site,permits,accounts,admin_ui,media_upload}.php`; `auth.php` rewritten
- [x] Migrations: `2026_10_31_200000_content_auth_email`, `210000_rider_verify_token`, `220000_audit_action_varchar`, `230000_backfill_password_changed`, `240000_notification_types`; snapshot recaptured; defaults in `database/seed_data/site_content.php`
- [x] Public: `index.php`, `page.php`, `faq.php`, `contact.php`, `verify.php`, `sitemap.php`, `robots.php`, `pages/errors/error.php`, partials `public-{head,header,footer}.php`, `assets/css/public.css`, `assets/js/public.js`
- [x] Auth pages: `login.php`, `login-code.php`, `forgot-password.php`, `reset-password.php`, `verify-email.php` (login layout to be restored, task 1)
- [x] Super Admin: `content.php`, `content-edit.php`, `site-settings.php`, `email-templates.php`, `media.php`, `inbox.php`, `data-requests.php`; `users.php` reworked; sidebar grouped
- [x] All roles: `pages/account/settings.php`
- [x] CKEditor: `assets/vendor/ckeditor5/*`, `assets/js/editor.js`, `partials/editor-assets.php` (verified in browser: load, image upload, save)
- [x] Payments: `permits.php`; `collect-payments.php`, `register-rider.php`, `iotec_functions.php`, `payments.php`, `ipn_handler.php`
- [x] Tests: `tests/lib.php`, `tests/smoke.php` (123), `tests/flows.php` (85), `tests/access.php` (334), `tests/check_portability.php`
- [x] Docs: README.md (both setups), docs/DEPLOYMENT.md (email, ioTec mode, checklist), `.env.example`, `.gitattributes`, `.gitignore`

## KNOWN ISSUES

- `storage/` in the repo folder holds test mail files owned by root (git-ignored; delete with sudo if wanted).
- The test scripts do not run JavaScript. Button behaviour is only proven by a Playwright pass; `check_portability.php` catches unwired attributes statically, not broken functions.
- 25 PHP files still hold inline `<script>` blocks and 25 hold `<style>` blocks; these block dropping `'unsafe-inline'` from the CSP. Inline event handlers are gone (enforced by `check_portability.php`).

## HOW TO CONTINUE

1. `docker compose up -d --build` (Linux) or XAMPP; run the three test scripts; all must pass before and after changes.
2. Work through PENDING TASKS in order; mark each `[x]` here only after running it.
3. After any schema change: `docker compose exec app php database/migrate.php --capture` then
   `docker compose exec app chown $(id -u):$(id -g) database/schema.snapshot.json`.
4. Browser checks: Playwright, one window; screenshots go to `.playwright-mcp/` (git-ignored). Admin sign-in in Docker
   needs the emailed code: `curl -s "http://localhost:7082/api/v1/search?query=to:admin@bodaerp.com&limit=1"`.
5. Do not commit unless the owner asks.

## Rules when changing code

- **Database changes:** plain additions → change local DB, commit the regenerated snapshot. Anything else → idempotent migration with `mig_*` helpers; never edit a released migration.
- **SQL:** always parameters; **Output:** `h()` (rich text: `render_rich_text()`); **Forms:** `csrf_field()` + `csrf_verify()`; **Scope:** filter by `$_SESSION['stage_id']` / `['city_id']`.
- **Paths:** `__DIR__`-relative requires; URLs via `BASE_URL` / `app_url()`; files via `upload_path()` / `storage_path()`.
- **`db_helpers.php`:** keep `_dbPrepare()` and `runQuery()` returning `mysqli_stmt`.
- **Never on a real server:** demo passwords or on-screen reset links (gated by `db_is_dev_machine()` / `SHOW_DEMO_LOGINS`).
- **Emails:** `send_template_email('<code>', $to, [...])`; add new templates to `site_content.php` + a migration INSERT IGNORE.
