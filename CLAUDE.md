# BodaERP — notes for developers and AI assistants

Read `README.md` first (setup, logins, layout), then `docs/DATABASE.md`, `docs/ARCHITECTURE.md` and
`docs/DEPLOYMENT.md` as needed. This file is the short version of the rules.

## Decisions (do not reverse without asking the owner)

- **Stack: PHP 8.1+ · MySQL/MariaDB (mysqli) · plain JavaScript.** No Docker, no PostgreSQL/PDO, no Node/npm,
  no Composer, no build step, no CKEditor. Do not add them back or generate files for them.
- **Nobody imports SQL by hand.** Database changes reach every machine automatically (see below).
- **Secrets live only in `.env`** (git-ignored). Never put a credential in code, docs or this file.
- **JavaScript stays shared and plain:** behaviour used on many pages goes in `packages/frontend/assets/js/main.js`
  or `table-tools.js`; never paste the same inline handler into several pages (a duplicated sidebar handler once
  cancelled itself out and broke the mobile menu).

## Commands

```bash
php database/migrate.php              # apply pending migrations + schema snapshot (also automatic on first request)
php database/migrate.php --status     # preview, changes nothing
php database/migrate.php --capture    # save database/schema.snapshot.json from the local database
php database/seed_run.php [--fresh]   # large demo data set (never on real data)
php database/create_admin.php --name "N" --email e@x.com    # first Super Admin on a new database
php tests/smoke.php                   # sign in as every role and open every page — run after every change
```

Local PHP on this project's Windows setup: `C:\xampp\php\php.exe`. Local database: `bodaerp` (created automatically).

## Rules when changing code

- **Database changes:** plain structure (table, column, index, longer varchar, enum value at the end, allow NULL) =
  change the local database, then commit the regenerated `database/schema.snapshot.json`. Anything else (data fixes,
  renames, drops, shrinking, views) = an idempotent migration in `database/migrations/` using the `mig_*` helpers;
  never edit a released migration. Test on a scratch database (`DB_NAME=scratch php database/migrate.php`).
- **SQL:** always parameters (`fetchOne($sql, [$id])`); never interpolate request data. **Output:** `h()`.
  **Forms:** `csrf_field()` + `csrf_verify()`. **Scope:** filter by `$_SESSION['stage_id']` / `['city_id']`, not just the role.
- **Paths:** `__DIR__`-relative requires only (never `/var/www/html/...`); URLs go through `BASE_URL`.
- **`db_helpers.php`:** keep `_dbPrepare()` (named `:param` support + the `migrator_auto()` hook) and `runQuery()`
  returning `mysqli_stmt`. Rewriting it to another return type breaks every query.
- **Never show on a real server:** demo passwords or an on-screen password-reset link. They are gated by
  `db_is_dev_machine()` (local AND request from the same machine) and `SHOW_DEMO_LOGINS` — keep it that way.
- Views `v_stage_stats` / `v_city_stats` must stay **views** (the schema sync only tracks base tables).

## Verified status (2026-10-31, local XAMPP, MariaDB 10.4, PHP 8.2)

- `php tests/smoke.php`: 55 checks pass — every page for super admin, government, city council, chairperson and
  rider, plus public pages and the lock-down of `.env` / `database/`.
- A fresh checkout in a differently named folder with a brand-new empty database builds itself
  (migrations + schema sync), seeds, and passes the same test. Forgot-password → reset → sign-in works there.
- Schema sync tested on scratch databases: new table/column/index/foreign key, safe widenings, risky changes only
  reported, failures reported without crashing, live mode never writes the snapshot.
- **Not tested:** the real live server (MariaDB 11.8), Oracle MySQL, real ioTec payments (the code is in test mode:
  `IOTEC_SANDBOX` in `packages/backend/iotec_config.php`), email (there is no mail server).

## Open items

- Rotate secrets that were committed in the past: the old `config/database.php` (live DB user/password) and the
  ioTec IPN secret are in git history.
- Password reset has no email: on a live server an administrator sets the new password (Super Admin → All Users).
- `IOTEC_SANDBOX` is hard-coded `true`; flip it only after an end-to-end test with ioTec.
- `.claude/` holds assistant tooling files from an earlier workflow; they are not part of the application.
