# BodaERP: continuation file (read this first)

OVERALL: 79% complete. 21% remaining until public launch
(counted only from items below that were built AND verified; unchecked = not complete)

| Area | Done | % |
|---|---|---|
| Features & Functionality | 17 of 21 | 81% |
| Public Pages & Content | 12 of 14 | 86% |
| Shared Login & Auth Flows | 11 of 13 | 85% |
| Admin Portal | 11 of 14 | 79% |
| Customer (Rider) Portal | 8 of 10 | 80% |
| UI / UX & Responsiveness | 9 of 11 | 82% |
| Backend (PHP) | 9 of 11 | 82% |
| Database, Migrations & Seeds | 7 of 8 | 88% |
| Emails & Notifications | 10 of 12 | 83% |
| Popups, Errors & Edge States | 6 of 8 | 75% |
| Security | 16 of 16 | 100% |
| Dev Environments (Docker + XAMPP) | 6 of 7 | 86% |
| Content & Settings in DB | 7 of 9 | 78% |
| CKEditor Integration | 5 of 6 | 83% |
| SEO & Performance | 7 of 8 | 88% |
| Testing & QA | 8 of 10 | 80% |
| Documentation | 5 of 6 | 83% |
| **Total** | **154 of 185** | **83% raw; 79% weighted by launch risk (what is left is mostly owner actions, see BLOCKING)** |

### NEXT (owner, 2026-11-07)
1. [x] (2026-11-07) bodaerp.site live on the Kakebe VPS behind a shared Traefik (Let's Encrypt, auto renewal; Roru's
   expired certificate fixed the same way). Demo data cleared: one account per role left (Lira city, Railway Stage, one rider);
   each has a random password (`/root/bodaerp-passwords.txt` on the server); sign-in codes off until email works. Same day: SEO
   titles/descriptions per page, schema.org JSON-LD (Organization, WebSite, WebPage/FAQPage/ContactPage/AboutPage,
   BreadcrumbList, Service), 1200x630 share card, footer photo-credit line removed, homepage light sections
   alternate (`$tone()` in index.php) so no two neighbours share a colour. Details: docs/DEPLOYMENT.md "Kakebe VPS".
2. Owner: email for bodaerp.site (a mailbox or SMTP service + MX/SPF/DKIM), then SMTP_* in `/opt/bodaerp/.env`,
   real addresses on staff accounts, sign-in codes back on. Google Search Console: add the property (DNS TXT can be
   added on request) and submit https://bodaerp.site/sitemap.xml.
3. LATER LIST (owner, 2026-11-07, "everything else we do later"):
   a. Icons: removed from every text button and button-styled link (137 in 54 files, icon-only buttons kept). Icons
      in headings, card titles, badges, tables and the sidebar remain; ask the owner whether those go too.
   b. Live accounts are still the demo people (Lira, Ocen, Akello, works@); Super Admin name "Kakebe Admin" and phone
      +256 700 000 001 are placeholders. Replace with real people when they arrive.
   c. Walk every page of every role in the browser on the live site after the button change (tests pass; no full
      Playwright pass was run on the live site for this change).
   d. Email + sign-in codes (item 2), Search Console, then round 6.
4. Round 6, the full plan in docs/ROUND6_PLAN.md (notifications for every role, notices, stages and chairpersons by
   city admins, chairperson search/add/move without delete, transfers, removal and restore, renewal requests with a
   view-only rider card, complaints for everyone including the public, pop-up permit check by number or plate,
   reports, monitoring, README with every role). Update its status table as phases finish.

### BLOCKING PUBLIC LAUNCH
0. Owner review of rounds 4 and 5 (2026-11-06, committed and pushed at the owner's request; visual sign-off still open): homepage slider and copy, portal redesign, own form controls (ROUND 4); ID cards, photo columns, phone portal accounts, 23 copy-promised features, upgrades (ROUND 5).
0b. ApexCharts licence: kept on 4.7.0, the last MIT release. 5.x and later need a paid licence for a platform used by other organisations; upgrade only if the owner buys one.
1. Owner review of round 2 (built and verified 2026-11-01, committed 2026-11-05 in 03a0cb8 at the owner's request): login, theme, portal shell, sidebars, government data fixes, home sections, AI-tell sweep. Still needs the owner's visual sign-off.
2. SMTP not configured on the live server (Kakebe VPS): password reset, invites, receipts and sign-in codes need it; codes are switched off until then. bodaerp.site has no MX record, so the `@bodaerp.site` account addresses receive nothing (docs/DEPLOYMENT.md → Email).
3. Mobile-money (ioTec) never tested end to end, even in test mode. `IOTEC_MODE=test` until then; IPN callback URL must point at this site.
4. Rotate the secrets that are in git history (old config/database.php DB password, ioTec IPN secret).
5. Legal review of Privacy Policy, Terms, Cookie Policy (Super Admin → Pages & Content).
6. Real contact phone / WhatsApp / social links (Super Admin → Site Text & Branding → Contact details; empty = hidden).
7. Reprint ID cards made before 2026-10-31: their QR holds the permit number (now shows a masked name, no photo). New cards use a random token.
8. Security audit: DONE 2026-11-05 (`.claude/security-audit-2026-11-05.md`, 9 findings + 1 found while fixing, all fixed and verified; gap register #29-38). `APP_ENV=live` and `APP_URL` are set on the VPS (2026-11-07); left: `IOTEC_IPN_HEADER` once ioTec's header name is known.
9. [x] (2026-11-07) Live server on MariaDB 11.8.9 with every migration applied (Kakebe VPS).
10. EgoSMS account + sender ID, then `EGOSMS_USERNAME/PASSWORD/SENDER` in the server `.env` (sandbox first, `EGOSMS_MODE=live` after a test). Until then SMS goes to `storage/sms/`.
11. USSD renewal needs a short code from a USSD provider (EgoSMS has none), `USSD_TOKEN` in `.env`, then `ussd_code` in Site Text & Branding. Built and walked end to end locally; the pay step needs ioTec (blocker 3).
12. Native-speaker review before switching on Luganda / Lango / Acholi / Runyankole (Super Admin → Languages + SMS translations, then `languages_enabled`).

### NOT BLOCKING BUT NEEDED SOON
1. [x] (2026-11-05, found already built + tested) Renewal reminder emails: `job_renewal_reminders()` in automation_notices.php, three per permit, honours notify_email.
2. [x] (2026-11-05; flows 87) `rider_notify()` in functions.php is the only way to add a portal notification; it skips riders whose account has "Show notifications in my portal" off and trims to the 255-char column (data-request replies used to overflow it). Notifications page explains when they are off.
3. [x] (2026-11-05, script half) CSP `script-src 'self' 'nonce-…'`: no `'unsafe-inline'`. Every inline `<script>` carries `nonce="<?= csp_nonce() ?>"` (enforced by check_portability). Verified in browser: injected script refused; 48 portal pages + public pages + CKEditor run with no violations. `style-src` keeps `'unsafe-inline'` on purpose (148 style attributes + ApexCharts' injected styles; low XSS value).
4. [x] (2026-11-05) All libraries self-hosted in `packages/frontend/assets/vendor/` with `VERSIONS.json`; no page loads anything from another site (enforced by check_portability; CSP has no CDN hosts). jsPDF 2.5.1 (12 advisories) upgraded to 4.2.1 + AutoTable 5.0.9, PDF export verified. Inter font self-hosted (public site and login asked for it but never loaded it). `tests/check_dependencies.php` reads VERSIONS.json: 7 libraries, 0 advisories.
5. [x] (2026-11-05, already done) Every portal page uses `topheader.php` or `portal_start()`; `db-backup.php` is a download endpoint, not a page.
6. PHPUnit is not used (no Composer by decision); tests are plain PHP scripts in tests/.
7. [x] (2026-11-05) Accessibility pass, automated in Playwright over every page of all 5 roles + public pages: icon-only buttons named (aria-label + title), ~150 fields tied to labels (`for`/`id`, filter selects `aria-label`), alt text on all images, contrast to WCAG AA (warning/info/success text shades, `--brand-ink` for links, orange/teal/purple leftovers replaced). Found and fixed `var(--primary-blue-gradient)` used 6 times with no definition (active tabs, step numbers, buttons had no background, white text invisible). Remaining: logo wordmark red on navy (logotype, exempt). Not covered by the script: keyboard focus order, screen-reader walkthrough.
8. [x] (2026-11-06) SMS through EgoSMS: receipts, failed payments, renewal reminders (2 per permit), overdue notices, chairperson/city reminders (1 per rider per day), handover results, sign-in codes. `sms.php`, Super Admin → SMS.

---

## ROUND 5 (2026-11-06 owner request, built and verified, committed and pushed 2026-11-06 at the owner's request)

Owner: sidebar dropdowns stay open independently; rider photo as the first column in every rider list; build every feature the public copy promises; full ID card pages in all portals, cleaner and shaped like a real government ID, staff can edit print fields, riders cannot; upgrade everything; then deploy bodaerp.site to the VPS (`ssh vps`), today on Kakebe's Hostinger shared hosting.

Built and verified (smoke 156, flows 139, access 440, portability clean, 0 advisories; Playwright all 5 roles; card rebuild checked in Playwright: card page, signature pad, enlarge, A4 sheet, rider without photo):
1. Sidebar groups open independently; `person_cell()`/`person_avatar()` photo column; staff profile photos (`uploads/avatars`, private); city logo upload.
2. ID cards: `id_cards` table + `packages/backend/id_cards.php`, partials `id-card*.php`, `id-card.css/js`; staff print-field editor with live preview (`id_cards.print_data`, name limited to registered words, audited diff); riders read-only; chairperson + city pages (single, list, print sheet). Rebuilt the same day on the owner's photos of the new national ID (migration `350000_id_card_layout`): front = emblem + heading + national-colour rule, photo with the city name (LIRA, GULU, KAMPALA: `id_card_city_word()`, also in the corner badge; the code stays in the permit number) and permit digits under it, surname / given / other names beside sex, birth, plate, NIN, issue and expiry, the holder's signature (the only one), ghost photo (only when there is a photo; otherwise the signature line widens), card number on the edge; back = tinted panel (city, stage, route, home, make, next of kin, kin's phone, rider since), QR (decoded with zxing from a print-resolution render), property statement and conditions one sentence per line (never wraps; `id-card.js` shrinks a long line), MRZ band. No barcode (owner). Security print is an inline SVG (`id_card_pattern_svg()`). Signature: drawn in a pad on the staff card page or uploaded (`riders.signature_path`, private like photos, `store_generated_image()`); no officer signatory any more.
   Document pages (staff card page for chairperson + city, rider card, 3 receipts, A4 sheet) use `.doc-layout` (portal-shell.css): `action_rail()` (admin_ui.php) as a small menu down the left instead of a button row, forms in the middle, the card pinned on the right (sticky, both sides stacked at real size); below 1280px the menu is a strip above, below 768px it all stacks.
3. All 23 copy-promised gaps built: receipts page (`partials/receipt.php`), portal by phone number (SMS invite + reset, migration `340000_phone_portal`, `users.email` nullable), full rider edit for city/super admin with audit diffs (`rider_records.php`), anonymise from data requests, objection requests, enforcement suspension affects status/verify/USSD + rider appeals, revenue fiscal-year filter, stage report export, audit purge after 12 months, unused settings removed, contact channels.
4. Upgrades: Docker dev stack PHP 8.5 + MariaDB 11.8 by default (`PHP_VERSION=8.2` / `MARIADB_VERSION=10.4` in `.env` reproduce XAMPP), Mailpit 1.33; PHP 8.5 deprecations removed (`curl_close`, `finfo_close`, `fputcsv` escape via `csv_put()`); Bootstrap 5.3.8, Font Awesome 7.3.1 (every icon in code and DB checked), CKEditor 49.1.0 (all 53 plugins present), ApexCharts 4.7.0 (see blocker 0b). jsPDF, AutoTable, Outfit, qrcodejs already latest.

Found on the way: gap register #49-51.

## ROUND 4 (2026-11-06 owner request, built and verified, committed and pushed 2026-11-06 at the owner's request)

Owner, in order: "all the browser defaults confirm dialogs dropdowns etc fix all to use system style ... work more on the homepage starting with full standard size hero section with multiple hero slide download real image ... very neat and clean footer ... use image for bg in some sections"; then "remove ... the boda boda permit and compliance badge and anything similar, ... in the footer remove the check permit button, align the copyright text in the center ... footer 4 column ... with social links ... the admin portals and rider portals too need redesigning esp the sidebar items, every single thing under the main sidebar item should be a subitem WITH DROPDOWN ... benchmark from goodname finance project ... center the nav bar items but leave sign in or the avatar in the right corner and logo in the left. remove the pause button and the slide numbering ... abolish anything that mentions where this system was built from, rewrite every wording ... importance, why, how it works ... for who and why them".

Built and verified (tests + Playwright, one window, 66 pages of all roles clean):
1. Own controls for every browser-drawn surface: `assets/js/controls.js` + `assets/css/controls.css` (loaded by public, sign-in and portal shells). Dialog service `BodaUI.confirm/showText` (no `confirm()`/`prompt()` anywhere; a missing controls.js refuses instead of proceeding), listbox for every `<select>` (filter above 10 options, 200-row cap), calendar date field (dd/mm/yyyy), slider, colour swatches, numeric text fields, file drop zone (`data-max-mb`), suggestion box for `list=`, search clear button, tooltips for `title=`/`data-tip`, FAQ accordion (`render_faq_item()`), inline validation on every form (`noValidate`), styled scrollbars/checkboxes/autofill. Confirm copy names the record; deleting a city needs the name typed (`data-confirm-typed`); bulk forms use `{count}`/`{action}`. Proof: `audit/PROOF_NATIVE.md`.
2. Homepage: full-height hero slider from `content_items` group `home_slide` (photo, title, text, button; Super Admin, Pages & content, "Home: hero slides"), arrows only, autoplay every `home_slider_interval` s that holds on hover/focus and stops for good once the visitor uses arrows/keys/swipe, never on reduced motion. "Why it matters" photo band (`home_why`, new tab), steps, who it is for (photo on one third), features, permit check band and closing band on photos (`home_*_image` settings). 9 real photos from Wikimedia Commons (CC BY-SA 4.0 / CC0) in `assets/images/home/` as WebP with 960px phone versions; credits on `/p/photo-credits` (footer link) as the licences require.
3. Public header: logo left, menu centred, account right; no tagline badge anywhere (`site_tagline_short` removed). Footer: 4 columns (brand + social, Product, Company, Contact), legal links, copyright and photo credit centred.
4. Copy rewritten (migration `2026_11_06_200000_public_copy`, only where text is still the old default): why it matters, how it works, who it is for and why them; nothing about where the system is made. Legal pages, the copyright line and the email footer still name Kakebe Technologies Limited as the legal operator (left on purpose). Portal chart labels "Kakebe Tech" became "Platform".
5. Portals redesigned on the Goodname Finance staff portal: Outfit font (self-hosted `vendor/outfit`, replaces Inter), navy sidebar + navy top bar as one frame, groups as dropdowns (one open at a time, current group open, single-page groups are links, badges roll up to a dot), fold to an icon rail (remembered), top bar shows scope / group / page, page header with red accent bar, floating cards that lift, uppercase table headers with an accent edge on row hover, 10px buttons that press in, links dark with a red underline instead of browser blue.

Found on the way: gap register #45-48.

## ROUND 3 (2026-11-05 owner request, done 2026-11-06)

Owner: "sms use egosms and dont put authenticator app anywhere, instead optional otp, and then also implement all other [suggestions]. then make the structure better and beautify all the raw text, not too packed together, rules of thirds in the content area"; then "run anti AI tells in the entire platform ... verify everything e2e and finally push".

Built and verified (tests + Playwright, one window): 1 SMS (EgoSMS JSON API, sandbox default, log transport without credentials). 2 Optional sign-in codes by SMS or email per user (`users.two_factor_channel`, phone confirmed by code, `phone_verified_at`); platform `require_2fa` still forces staff; no authenticator app anywhere. 3 USSD renewal `ussd.php` (token + optional IP allowlist, masked names, rate limits, `ussd_sessions`). 4 Cash handover (chairperson `cash-handover.php`, city `cash-handovers.php`, SMS result). 5 Late fees per city (flat or %, after the grace period) via `permit_amount_due()` on every payment path; anomaly rule compares the fee part only. 6 Duplicate check at registration (live, `check-duplicate.php`; NIN/plate block, shared phone needs a tick). 7 Rider import CSV/.xlsx for city admins (`rider_import.php`, `import-riders.php`). 8 Roadside checks on `verify.php` for signed-in staff (outcome, note, optional location; `permit-checks.php`; national counts on the government enforcement page). 9 Off-server backup email (`backup_email`, encrypted only). 10 Languages mechanism (`t()`, `translations`, Super Admin → Languages; drafts stay off). 11 Design pass: spacing scale, `.layout-thirds` (2/3 + 1/3), `.section-title`, sentence case for ~400 headings/labels/buttons, table headers and sidebar groups no longer ALL CAPS, rider pages rebuilt on `portal_start()` with `t()`.

Anti-AI-tell sweep (2026-11-06): baseline SQL comments (emoji, dividers, "import in phpMyAdmin"), `.env.example`/`.htaccess` dividers, section-label comments, silent DB probes now logged (`db_probe_failed()`), generic names (`qsMerge2`, `$items`, `$data`), dead `statusBadgeClass()`, 9 unused label settings removed by migration, 41 page titles now use the `site_name` setting, `.claude/` untracked and git-ignored, `.htaccess` blocks every dot-path except `.well-known`. CMS text in the DB scanned: clean.

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
12. [x] (2026-11-05) Committed on main as 03a0cb8 when the owner asked. Still: no commits unless requested.

---

## TEAM & ENVIRONMENTS

| | Developer A | Developer B |
|---|---|---|
| OS | Linux | Windows |
| Runs the app with | Docker (optional dev convenience): `docker compose up -d --build` → http://localhost:7080/bodaerp/ , phpMyAdmin :7081, Mailpit :7082 | XAMPP 8.2.12 (PHP 8.2.12, MariaDB 10.4.32): clone into `C:\xampp\htdocs`, open http://localhost/bodaerp-official/ |
| PHP CLI | `docker compose exec app php …` | `C:\xampp\php\php.exe …` |
| Email | Mailpit (SMTP) | written to `storage/mail/*.eml` + Sent log |

- XAMPP versions are an assumption (latest XAMPP stable known: 8.2.12). Production since 2026-11-07: Docker on the Kakebe VPS (PHP 8.5, MariaDB 11.8) behind a shared Traefik; shared hosting (Apache + PHP 8.2 + MariaDB, no Docker) must keep working.
- The app never depends on Docker: config comes from real env vars → `.env` → XAMPP defaults (127.0.0.1, root, no password, DB `bodaerp` auto-created).
- Verified 2026-10-31: Docker stack from scratch, and a "stock XAMPP" simulation (no env, no .env, empty root password, folder `bodaerp-official`, DB auto-built on first request, CLI migrate + seed): smoke 118/118, flows 56/56 (Docker), portability check clean.
- Port block for this project is 7000-7999; another project's `rooibok-*` containers currently occupy 7000-7002 and 7020.

## Decisions (do not reverse without asking the owner)

- **Stack: PHP 8.1+ · MySQL/MariaDB (mysqli) · plain JavaScript.** No PDO (owner, 2026-10-31: keep the mysqli helpers), no Node/npm, no Composer, no build step.
- **Docker: dev stack for Developer A and the live stack on the Kakebe VPS** (owner, 2026-10-31 dev-only; production moved onto it 2026-11-06/07). XAMPP and shared hosting still run the app without it, so the app must never depend on Docker. Server differences live in `deploy/docker-compose.traefik.yml`, chosen by `COMPOSE_FILE` in the server `.env`.
- **CKEditor 5, GPL licence, free plugins only** (owner, 2026-10-31). v49.1.0 self-hosted browser build committed in `packages/frontend/assets/vendor/ckeditor5/` (licenseKey `'GPL'`). Note: GPL obligations apply if BodaERP is distributed.
- **No public sign-up** (owner, 2026-10-31). Riders get an account when their chairperson registers them with an email (invite link); staff accounts from Super Admin → All Users.
- **Nobody imports SQL by hand.** Migrations + schema snapshot apply themselves.
- **Secrets only in `.env`.** Environment values (DB, SMTP, ioTec, paths) in `.env`; everything editable (text, menus, emails, branding, flags) in the database.
- **JavaScript stays shared and plain:** `controls.js` (form controls, dialogs, tooltips, validation; every page), `main.js` (portals), `public.js` (public site), `editor.js` (CKEditor), `table-tools.js`. Never call `alert()`, `confirm()` or `prompt()`: use `BodaUI.confirm()` or `data-confirm`.
- **Portal look follows the Goodname Finance staff portal** (owner, 2026-11-06): Outfit, navy sidebar + top bar, dropdown groups, accent page header. No badges/eyebrow labels on the public site, no slide numbers or pause button on the hero, no text about where the system is made. Use `data-*` attributes (`data-confirm`, `data-autosubmit`, `data-print`, `data-copy`, `data-copy-from`, `data-check-all`, `data-preview-target`, `data-fallback-src`, `data-hide-broken`), never inline handlers. To call a page function use `data-call` / `data-call-input` / `data-call-submit` with `data-call-args` (JSON via `h(js_json([...]))`), and add its name to `PAGE_ACTIONS` in `main.js`.
- Two-step sign-in is enforced only when SMTP is configured (otherwise admins would be locked out).
- Roles are fixed in code (`ROLE_LABELS` in auth.php); there is no role/permission editor.
- **Super Admin reads the national (government) portal** (owner, 2026-11-07): government pages allow `super_admin`; `portal_nav_current()` gives them the government menu plus an Admin link back. Every other cross-role page stays refused (tests/access.php).
- **Sidebar labels are one word each; the browser tab title is the sidebar label** (owner, 2026-11-07), set in head-assets.php through `portal_page_label()`. Pages outside the menu keep their own `$pageTitle`.
- **Public header:** "Sign in" button when nobody is signed in or the sign-in has expired or been revoked (`session_is_current()`); the initials avatar only for a live sign-in.
- **Public section tones:** neighbours never share a colour. Homepage light sections rotate grey / white / blue tint / white / dot grid / white (`$tone()` in index.php); inner pages: dotted blue-tint header, white body, navy footer.
- Views `v_stage_stats` / `v_city_stats` must stay views.

## Commands

```bash
php database/migrate.php [--status|--capture]   # migrations + schema snapshot (also automatic on first request)
php database/seed_run.php [--fresh|--if-empty]  # demo data (never on real data)
php database/create_admin.php --name "N" --email admin@bodaerp.site
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
| 39 | City admin sidebar cast the city code ('LIR') to int 0, so the Defaulters and Data checks badges counted every city | Medium | fixed + verified |
| 40 | Branded error page crashed (BRAND_DEFAULT_DARK undefined) when auth stopped a request while site.php was still loading (maintenance 503, expired form) | Medium | fixed (constants before the auth require) |
| 41 | main.js double-submit lock read `form.method`; Collect payments has a field named "method", so the lock threw and a double tap could post twice | High | fixed + verified in browser |
| 42 | Enforcement codes from COUNT(*)+1: repeated after a deletion (UNIQUE column, save failed) and raced | Medium | fixed + test in flows.php |
| 43 | Collect payments showed the city base fee as the amount, not what the rider owes | Low | fixed (amount follows the selected rider, late fee included) |
| 44 | `get_setting(...) ?? 'default'` fallbacks never applied (get_setting returns a string) | Low | fixed |
| 45 | Enforcement form saved any `type`/`status` string and read `amount` without a guard (warning when the field is absent) | Low | fixed + test in flows.php |
| 46 | `.gitignore` line `vendor/` (meant for Composer) also matched `packages/frontend/assets/vendor/`: Bootstrap, Font Awesome, CKEditor, ApexCharts, jsPDF, QR code and fonts were never in git or on origin, so a fresh clone (XAMPP, live server) had no styles, editor or charts | Critical | fixed (`/vendor/`); the folder goes in with the next commit |
| 47 | `pattern="[A-Za-z0-9-]{4,40}"` (permit number) and `[a-z0-9-]*` (page slug) are invalid in the v-flag regex mode browsers now use, so the check was silently skipped | Low | fixed (`\-`) |
| 48 | main.js fell back to `window.confirm()` without Bootstrap and to `window.prompt()` when the clipboard was refused | Low | fixed (dialog service, refuses when missing) |
| 49 | On PHP 8.5 `finfo_close()` printed a deprecation into the media-upload JSON, so uploads failed | Medium | fixed + test in flows.php (`new finfo()`) |
| 50 | Round 4 portal heading/link colours made dashboard banner titles and the "items need your attention" link dark on navy (5 pages) | Medium | fixed + verified in browser, all 5 roles |
| 51 | Revenue-split legends drew two of four dots as badges holding a "●" character | Low | fixed (`.legend-dot`, 3 dashboards) |

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
- `style-src` still allows `'unsafe-inline'` (25 `<style>` blocks, 148 `style=` attributes, ApexCharts). Scripts are nonce-only.
- Upgrading a vendor library: replace the files in `assets/vendor/<lib>/`, change `VERSIONS.json`, run `tests/check_dependencies.php`.

## HOW TO CONTINUE

1. `docker compose up -d --build` (Linux) or XAMPP; run the three test scripts; all must pass before and after changes.
2. Work through PENDING TASKS in order; mark each `[x]` here only after running it.
3. After any schema change: `docker compose exec app php database/migrate.php --capture` then
   `docker compose exec app chown $(id -u):$(id -g) database/schema.snapshot.json`.
4. Browser checks: Playwright, one window; screenshots go to `.playwright-mcp/` (git-ignored). Admin sign-in in Docker
   needs the emailed code: `curl -s "http://localhost:7082/api/v1/search?query=to:admin@bodaerp.site&limit=1"`.
5. Do not commit unless the owner asks.

## Rules when changing code

- **Database changes:** plain additions → change local DB, commit the regenerated snapshot. Anything else → idempotent migration with `mig_*` helpers; never edit a released migration.
- **SQL:** always parameters; **Output:** `h()` (rich text: `render_rich_text()`); **Forms:** `csrf_field()` + `csrf_verify()`; **Scope:** filter by `$_SESSION['stage_id']` / `['city_id']`.
- **Paths:** `__DIR__`-relative requires; URLs via `BASE_URL` / `app_url()`; files via `upload_path()` / `storage_path()`.
- **`db_helpers.php`:** keep `_dbPrepare()` and `runQuery()` returning `mysqli_stmt`.
- **Never on a real server:** demo passwords or on-screen reset links (gated by `db_is_dev_machine()` / `SHOW_DEMO_LOGINS`).
- **Emails:** `send_template_email('<code>', $to, [...])`; add new templates to `site_content.php` + a migration INSERT IGNORE.
