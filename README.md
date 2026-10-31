# BodaERP

Rider registration, permit-fee collection and compliance platform for boda-boda (motorcycle taxi)
operators in Uganda, by Kakebe Technologies Limited. Stage chairpersons register riders and collect
fees, city councils see their revenue and compliance, the national government gets a read-only
portal, and the platform owner runs everything from one Super Admin area.

**Stack: PHP + MySQL + plain JavaScript.** No Docker, no Node, no Composer, no build step —
copy the folder into a web server and it runs.

| | |
|---|---|
| PHP | 8.1 or newer (developed on 8.2) with the `mysqli`, `mbstring`, `curl` and `json` extensions — all on by default in XAMPP |
| Database | MariaDB 10.4+ (what XAMPP ships; tested on 10.4, and the live server runs 11.8). Oracle MySQL 5.7/8 should work but has not been tested |
| Web server | Apache with `mod_rewrite` and `.htaccess` allowed (default in XAMPP) |
| Front end | Bootstrap 5, Font Awesome, ApexCharts and jsPDF loaded from CDNs — **the browser needs internet access** |

---

## Quick start (Windows + XAMPP, about 5 minutes)

1. **Install [XAMPP](https://www.apachefriends.org/)** (PHP 8.1+) and start **Apache** and **MySQL** in its control panel.
2. **Get the code** into XAMPP's web folder — any folder name works:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/KaKebe-Technologies-Limited/bodaerp-official.git
   ```
3. **Open <http://localhost/bodaerp-official/>.** The first visit creates the database `bodaerp`
   and builds every table by itself — there is nothing to import and nothing to configure.
4. **Load demo data** (a command prompt in the project folder):
   ```bash
   C:\xampp\php\php.exe database\seed_run.php
   ```
   This adds 4 cities, 10 stages, about 1,200 riders and their payments. (Or put `C:\xampp\php` on your PATH and type `php`.)
5. **Check everything works:**
   ```bash
   C:\xampp\php\php.exe tests\smoke.php
   ```
   It signs in as every role and opens every page; you should see `55 checks passed, 0 failed`.
6. **Sign in** at <http://localhost/bodaerp-official/login.php> with the demo logins below.

macOS / Linux: the same steps with MAMP / LAMP — put the folder where Apache serves files
(`htdocs`, `/var/www/html`, `~/Sites`), make sure `mod_rewrite` is enabled and `AllowOverride All` is set for it.

### Demo logins (created by `database/seed_run.php`, shown on the login page on localhost)

| Role | Email | Password |
|---|---|---|
| Super Admin | `admin@bodaerp.com` | `admin123` |
| City council (Lira) | `council@liracityuganda.go.ug` | `lira2025` |
| Stage chairperson (Lira) | `ocen@railway.lira.ug` | `chair123` |
| Rider (Lira) | `akello@bodaerp.com` | `rider123` |
| Government (Works & Transport) | `works@gov.bodaerp.com` | `Gov@2026` |

Gulu, Kampala and Mbarara have their own council, chairperson and rider logins — the full list is
in the box on the login page. A second, smaller data set with different logins is in
`database/sample_data.sql` (instructions at the top of that file).
**Never create these accounts on a live server.**

### Only if you need it: the `.env` file

Locally the app connects to MySQL on `127.0.0.1` as `root` with no password. If yours differs, or you
want a different database name, or you are testing mobile-money payments, copy `.env.example` to
`.env` and fill in only the lines you need. `.env` is never committed.

---

## Who can do what

| Role | Lands on | Purpose |
|---|---|---|
| **Super Admin** | Platform Overview | All cities and users, revenue splits, reports, data-quality flags, audit logs, **Database**, System & Updates, platform settings |
| **City council** | City dashboard | One city: riders, stages, payments, compliance, defaulters, enforcement, revenue reports |
| **Stage chairperson** | Stage dashboard | One stage: register riders, collect payments, expiry alerts, defaulters, ID cards |
| **Rider** | My permit | Own profile, ID card, payment history, renewal status, notifications |
| **Government** | National dashboard | Read-only national view: registry, city league tables, enforcement, data flags, exports |

Public pages: landing page (`index.php`), `login.php`, `forgot-password.php`, and `verify.php?code=…`
(what the QR code on a rider's ID card opens).

---

## Project layout

```
index.php  login.php  forgot-password.php  reset-password.php  verify.php  ipn_handler.php   public entry points
.htaccess                    maps /pages and /assets to packages/frontend, blocks .env, database/, tests/
.env.example                 every setting the app reads (copy to .env)
cron/run.php                 scheduler entry point (optional)
tests/smoke.php              opens every page for every role
uploads/                     rider photos (created on demand, not in git)
packages/
  backend/                   PHP logic: auth, db_helpers, migrator, schema_sync, automation, anomaly_engine, ioTec payments
    config/config.php          timezone, BASE_URL, session settings
    database/database.php      the MySQL connection (the only place that knows credentials)
  frontend/
    pages/<role>/*.php         one folder per role: superadmin, citycouncil, chairperson, rider, government
    partials/                  sidebars, header, shared <head> and scripts
    assets/css, assets/js      plain CSS and JavaScript (main.js, table-tools.js)
database/
  migrations/                  one-off database changes that run by themselves
  schema.snapshot.json         the shape of the database, saved automatically (commit it)
  datapacks/                   ready-made reference data, e.g. a new city
  seed_run.php  sample_data.sql   demo data
  bodaerp_full.sql             the original base schema (used once, on an empty database)
docs/                          DATABASE.md, DEPLOYMENT.md, ARCHITECTURE.md
```

---

## Everyday work

- **Change a page:** edit the file under `packages/frontend/pages/<role>/`, refresh the browser.
- **Change the database:** change it locally any way you like (phpMyAdmin, SQL). The app saves its
  shape to `database/schema.snapshot.json`; **commit that file** and the live server updates itself
  after the next deploy. Details and exceptions: [docs/DATABASE.md](docs/DATABASE.md).
- **See / run database updates by hand:** Super Admin → **Database** → *Update database now*,
  or `php database/migrate.php` (`--status` to preview).
- **Add a city with starter data:** Super Admin → System & Updates → Data packs.
- **Reset the demo data:** `php database/seed_run.php --fresh` (empties the data tables first).
- **Start completely over:** drop the `bodaerp` database in phpMyAdmin and open the site again.
- **Back up:** Super Admin → Database → *Backup (.sql)*.
- **Deploy to the live server:** [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).
- **How the code fits together, and how to add a page:** [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

Rules we follow: no Docker/Node/Composer; JavaScript stays plain and shared (one `main.js`, no copy-pasted
inline handlers); every database change reaches the server automatically — nobody imports SQL by hand;
secrets live only in `.env`.

---

## Troubleshooting

| You see | Cause and fix |
|---|---|
| **"BodaERP can't reach its database"** | MySQL is not running — start it in XAMPP. On a live server the `.env` is missing or wrong ([docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)). |
| Pages without styling, or a link goes to `404` | `mod_rewrite` / `.htaccess` is not active. In XAMPP it is on by default; elsewhere enable `mod_rewrite` and set `AllowOverride All`. |
| Everything is unstyled but links work | The browser has no internet (Bootstrap and icons load from CDNs). |
| Login says the email or password is wrong | Run `php database/seed_run.php` first, then use the logins above. |
| Empty dashboards | No data yet — run the seeder. |
| `php` is not recognised | Use the full path `C:\xampp\php\php.exe`, or add `C:\xampp\php` to your PATH. |
| `tests/smoke.php` says `curl` is missing | Enable `extension=curl` in `C:\xampp\php\php.ini` and restart Apache. |
| A rider photo will not upload | The `uploads/` folder must be writable by the web server. |
| Forgot-password shows no link | Correct on a live server: there is no mail server, so an administrator sets the new password (Super Admin → All Users). The link is only shown on a developer's own machine. |
| Mobile-money payment fails | Payments run in ioTec **test mode** until you switch them on — see [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md#mobile-money-payments-iotec). |

---

*Kakebe Technologies Limited — support@kakebe.tech*
