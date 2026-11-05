# BodaERP

Rider registration, permit-fee collection and compliance platform for boda-boda (motorcycle taxi)
operators in Uganda, by Kakebe Technologies Limited. Stage chairpersons register riders and collect
fees, city councils see their revenue and compliance, riders manage their permit in their own portal,
the national government gets a read-only portal, and the platform owner runs everything, including the
public website's text, emails and media, from one Super Admin area.

**Stack: PHP + MySQL/MariaDB + plain JavaScript.** No Node, no Composer, no build step. Copy the folder into a
web server and it runs. Docker is an optional convenience for Linux developers only; nothing depends on it.

| | |
|---|---|
| PHP | 8.1 or newer; developed on **8.2** (XAMPP 8.2.12 ships PHP 8.2.12). Extensions: `mysqli`, `mbstring`, `curl`, `json`, `fileinfo`, `openssl` (all on in XAMPP); `dom` for page sanitising (built in) |
| Database | MariaDB 10.4+ (XAMPP 8.2.12 ships MariaDB 10.4.32; the Docker stack uses 10.4; the live server runs 11.8) |
| Web server | Apache with `mod_rewrite` and `.htaccess` allowed (default in XAMPP) |
| Front end | Bootstrap 5, Font Awesome, ApexCharts, jsPDF, qrcodejs, the Inter font and CKEditor 5, all committed in `packages/frontend/assets/vendor/` (no internet needed) |

---

## Option A: Windows + XAMPP (about 5 minutes, no Docker)

1. **Install [XAMPP](https://www.apachefriends.org/) 8.2** and start **Apache** and **MySQL** in its control panel.
2. **Get the code** into XAMPP's web folder (any folder name works):
   ```bat
   cd C:\xampp\htdocs
   git clone https://github.com/KaKebe-Technologies-Limited/bodaerp-official.git
   ```
3. **Open <http://localhost/bodaerp-official/>.** The first visit creates the database `bodaerp` and builds every
   table. There is nothing to import in phpMyAdmin and nothing to configure: the defaults are XAMPP's
   (`127.0.0.1`, user `root`, no password). Only if your MySQL differs, copy `.env.example` to `.env` and set `DB_*`.
4. **Load demo data** (Command Prompt in the project folder):
   ```bat
   C:\xampp\php\php.exe database\seed_run.php
   ```
   Adds 4 cities, 10 stages, about 1,200 riders and their payments. (Add `C:\xampp\php` to PATH to type just `php`.)
5. **Check everything works:**
   ```bat
   C:\xampp\php\php.exe tests\smoke.php
   C:\xampp\php\php.exe tests\flows.php
   C:\xampp\php\php.exe tests\check_portability.php
   ```
6. **Sign in** at <http://localhost/bodaerp-official/login.php>. The demo accounts are listed on the sign-in page
   (on your own computer only); click one to fill the form.

**Email on XAMPP:** there is no mail server, so every email (password reset, invites, receipts, contact form) is
saved as an `.eml` file in `storage\mail\` and listed in Super Admin → Email Templates → Sent log. Open the
`.eml` file to click the link. To send real email, fill in the `SMTP_*` lines of `.env`.

## Option B: Linux + Docker (development only)

```bash
docker compose up -d --build
```

| | |
|---|---|
| App | <http://localhost:7080/bodaerp/> (served from a sub-folder, exactly like XAMPP) |
| phpMyAdmin | <http://localhost:7081/> (server `db`, user `root`, password `bodaerp-dev`) |
| Mailpit | <http://localhost:7082/>: every email the app sends lands here |

The container waits for MariaDB, runs `php database/migrate.php`, seeds demo data once, then starts Apache.
The code is mounted, so edits show on refresh. Configuration comes from `docker-compose.yml` environment
variables (no committed PHP file is edited). Tests run inside the container:

```bash
docker compose exec app php tests/smoke.php
docker compose exec app php tests/flows.php
docker compose exec app php tests/check_portability.php
```

Start over: `docker compose down -v && docker compose up -d --build`. Ports stay in BodaERP's 7000-7999 block
and bind to 127.0.0.1. The Docker files are never used by XAMPP or production.

---

## Demo logins (created by `database/seed_run.php`; never on a live server)

| Role | Email | Password |
|---|---|---|
| Super Admin | `admin@bodaerp.com` | `admin123` |
| City council (Lira) | `council@liracityuganda.go.ug` | `lira2025` |
| Stage chairperson (Lira) | `ocen@railway.lira.ug` | `chair123` |
| Rider (Lira) | `akello@bodaerp.com` | `rider123` |
| Government (Works & Transport) | `works@gov.bodaerp.com` | `Gov@2026` |

In the Docker stack, administrators and government users get a 6-digit sign-in code by email (Mailpit), because
Platform Settings → *Require 2FA* is on and SMTP works there. On XAMPP (no SMTP) two-step sign-in stays off.

---

## Who can do what

| Role | Lands on | Purpose |
|---|---|---|
| **Super Admin** | Platform Overview | Cities, users, revenue splits, reports, data-quality flags, audit logs, database, system updates, platform settings, **plus** the website: pages, menus, FAQ, site text and branding, email templates and sent log, media library, contact inbox, rider data requests |
| **City council** | City dashboard | One city: riders, rider import (CSV / Excel), stages, payments, cash handovers from stages, late fees, compliance, defaulters, enforcement, roadside checks, revenue reports |
| **Stage chairperson** | Stage dashboard | One stage: register riders (with a live duplicate check), collect fees, hand cash over to the city, expiry alerts, defaulters, reminders by portal and SMS, ID cards |
| **Rider** | My permit | Own profile, ID card, payment history and receipts, renewal and mobile-money payment, notifications, download my data, correction/deletion requests |
| **Government** | National overview | Read-only national view: registry (name, permit, plate, status only; no NIN, phone or address), city comparison, enforcement, masked data checks, exports |

Everyone has **My account** (account menu, top right of every portal page): change password, notifications (email, SMS, portal), optional two-step sign-in with a code by SMS or email (there is no authenticator app), confirm email and mobile number,
sign out other devices. There is no public sign-up: riders get an account when their chairperson registers
them with an email address; staff accounts are created in Super Admin → All Users (an invite link is emailed).

Public pages: home, `/p/about`, `/p/privacy-policy`, `/p/terms-of-service`, `/p/cookie-policy`, `faq.php`,
`contact.php`, `verify.php` (permit check; the ID-card QR code opens it; signed-in staff can record a roadside check there), `ussd.php` (USSD renewal, for the USSD provider only), sign-in, forgot/reset password,
`sitemap.xml`, `robots.txt`, and branded 404 / 403 / 500 / maintenance pages. All their text is in the database.

---

## Project layout

```
index.php page.php faq.php contact.php verify.php     public pages (text from the database)
login.php login-code.php forgot-password.php reset-password.php verify-email.php    sign-in flows
ipn_handler.php sitemap.php robots.php                 ioTec callback, SEO
.htaccess                    URL mapping (/pages, /assets, /uploads, /p/<slug>), blocks secrets, 404 fallback
.env.example                 every setting the app reads (copy to .env)
cron/run.php                 scheduler entry point (optional)
tests/                       smoke.php (every page, every role), flows.php (critical flows), check_portability.php
docker-compose.yml docker/   optional Linux dev stack (PHP 8.2 + Apache, MariaDB 10.4, phpMyAdmin, Mailpit)
uploads/ storage/            user files and runtime files (git-ignored; paths configurable)
packages/
  backend/                   PHP: app (bootstrap, errors, headers), auth, security, mailer, uploads, site (content),
                             permits (payments), accounts, admin_ui, db_helpers, migrator, schema_sync, automation,
                             anomaly_engine, ioTec payments, media_upload, serve_upload, portal_nav (sidebar menus)
  frontend/
    pages/<role>/*.php         superadmin, citycouncil, chairperson, rider, government, account, errors
    partials/                  sidebar.php + topheader.php (portal shell), auth-start/auth-end (sign-in pages),
                               public head/header/footer, editor assets
    assets/css, assets/js      style/admin/component CSS, portal-shell.css, public.css, login.css;
                               main.js, table-tools.js, public.js, editor.js, registry.js
    assets/vendor/ckeditor5    CKEditor 5 (GPL) browser build, committed
database/
  migrations/                  database changes that apply themselves
  seed_data/site_content.php   default website text, menus, FAQ, email templates and settings
  schema.snapshot.json         the shape of the database (commit it)
  seed_run.php sample_data.sql demo data
docs/                          DATABASE.md, DEPLOYMENT.md, ARCHITECTURE.md
```

---

## Everyday work

- **Change a page:** edit `packages/frontend/pages/<role>/…`, refresh. New admin pages use `portal_start()` /
  `portal_end()` from `packages/backend/admin_ui.php`.
- **Add or move a sidebar item:** one array per role in `packages/backend/portal_nav.php`; the page sets `$active`
  to the item's key.
- **Colours and logo:** Super Admin → Text & branding (`brand_color_primary/accent/dark`, logo, favicon). Defaults
  come from the logo: navy `#001740`, blue `#056CFE`, red `#E2262F`.
- **Change website text, menus, FAQ, emails or branding:** Super Admin, no code needed.
- **Change the database:** change it locally, then commit the regenerated `database/schema.snapshot.json`
  (or write a migration for anything beyond adding). Details: [docs/DATABASE.md](docs/DATABASE.md).
- **Run database updates by hand:** `php database/migrate.php` (`--status` to preview), or Super Admin → Database.
- **Reset the demo data:** `php database/seed_run.php --fresh`.
- **After every change:** run the three test scripts.
- **Deploy:** [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). **How the code fits together:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

---

## Troubleshooting

| You see | Cause and fix |
|---|---|
| **"BodaERP can't reach its database"** | MySQL is not running (start it in XAMPP), or on a live server `.env` is missing or wrong. |
| Pages without styling, or links give 404 | `mod_rewrite` / `.htaccess` is not active. In XAMPP it is on by default; elsewhere enable `mod_rewrite` and `AllowOverride All`. |
| Everything is unstyled but links work | `packages/frontend/assets/vendor/` is missing or incomplete (check the clone or upload). |
| Sign-in says the email or password is wrong | Run `php database/seed_run.php` first. After 5 wrong tries an account pauses for 15 minutes. |
| No reset email arrives on XAMPP | Expected: it is in `storage\mail\` and the Sent log. Configure `SMTP_*` to send real email. |
| `php` is not recognised | Use `C:\xampp\php\php.exe`, or add `C:\xampp\php` to PATH. |
| Tests say `curl` is missing | Enable `extension=curl` in `C:\xampp\php\php.ini` and restart Apache. |
| A photo or file will not upload | `uploads/` must be writable by the web server; files are checked by content (JPG/PNG/WebP, 2 MB for rider photos). |
| Mobile-money payment fails | Payments run in ioTec test mode until `IOTEC_MODE=live` (see docs/DEPLOYMENT.md). |

---

*Kakebe Technologies Limited, support@kakebe.tech*
