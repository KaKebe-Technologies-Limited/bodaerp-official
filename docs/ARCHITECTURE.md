# Architecture

A classic server-rendered PHP application: every page is a PHP file that checks who is signed in, runs
SQL through a few small helpers, and prints HTML. There is no framework, no router and no build step.

## How a request flows

```
browser ─► Apache + .htaccess ─► a PHP page ─► packages/backend/auth.php ─► MySQL
            /pages/x/y.php  →     packages/frontend/pages/x/y.php
```

1. **`.htaccess`** maps the public URLs `/pages/…` and `/assets/…` to `packages/frontend/…`, and `/logout.php`,
   `/api/payments.php` to `packages/backend/…`. It also blocks `.env`, `.git`, `*.sql`, `database/`, `tests/`.
2. **A page** starts with `require_once __DIR__ . '/../../../backend/auth.php'; require_role([...]);` which loads
   the configuration, starts the session, enforces the role and idle timeout (30 min), and: after the first
   database query of the request: applies pending database updates (`migrator_auto()`).
3. **The page** reads or writes data with the helpers below, then prints HTML using shared partials.

## Backend (`packages/backend/`)

| File | What it does |
|---|---|
| `config/config.php` | Timezone, `BASE_URL` (URL folder, detected automatically), session name and timeout, upload folder |
| `database/database.php` | The MySQL connection `db()`; environment detection (`db_is_local()`, `db_is_dev_machine()`); credentials come from `.env` |
| `db_helpers.php` | `fetchAll`, `fetchOne`, `fetchValue`, `runQuery`: **always** prepared statements; positional `?` or named `:name` placeholders; `runQuery` returns the `mysqli_stmt` (`->affected_rows`, `->insert_id`, `->close()`) |
| `auth.php` | Login, `require_role()`, CSRF (`csrf_field()`, `csrf_verify()`), `audit_log()`, `current_user()` |
| `functions.php` | `h()` (HTML-escape: use on every printed value), `redirect()`, `formatCurrency()`, `formatDate()`, ID/receipt number generators, `verifyUrl()` |
| `migrator.php` | Runs `database/migrations/*.php` and the `mig_*` helpers |
| `schema_sync.php` | Keeps the database shape in step with `database/schema.snapshot.json` ([DATABASE.md](DATABASE.md)) |
| `automation.php` | The four scheduled jobs, run lazily while the app is used or from `cron/run.php` |
| `anomaly_engine.php` | The 15 duplicate / fraud / consistency checks that feed *Data Quality & Flags* |
| `datapacks.php` | Ready-made reference data (a city with stages) applied from Super Admin |
| `iotec_config.php`, `iotec_functions.php`, `payments.php` | ioTec Pay mobile-money collection; `ipn_handler.php` (project root) receives payment notifications |
| `sms.php` | EgoSMS sender in the mailer's shape: `send_template_sms()`, `sms_log`, `storage/sms/` without credentials, `rider_sms_target()`, `rider_send_reminder()` |
| `i18n.php` | `t('key', 'English')` for rider-facing text; reviewed translations from the `translations` table (Super Admin → Languages) |
| `rider_import.php` | CSV / .xlsx rider import for city admins: read, check per row, then save in one transaction |
| `permits.php` | `payment_confirm()` (the single path for every payment), cash payments, renewal window |

### Rules for backend code

- **SQL:** never put a request value into a query string; pass it as a parameter:
  `fetchOne("SELECT … WHERE id = ? AND stage_id = ?", [$id, $stageId])`.
- **Forms:** every POST form contains `<?= csrf_field() ?>` and the page calls `csrf_verify()` first.
- **Output:** print values with `h($value)`. **Scope:** a chairperson page must filter by `$_SESSION['stage_id']`,
  a city page by `$_SESSION['city_id']`: the role check alone does not isolate data.
- **Changes worth tracing** call `audit_log('UPDATE', 'entity', $id, 'what happened')`.

## Front end (`packages/frontend/`)

- `pages/<role>/*.php`: one folder per role (`superadmin`, `citycouncil`, `chairperson`, `rider`, `government`).
- `partials/`: `head-assets.php` (CSS + chart library), `sidebar-<role>.php` (menu), `topheader.php`,
  `export-toolbar.php` (CSV / Excel / PDF / print for any table), `scripts-footer.php` (JavaScript).
- `assets/css/`: `style.css` (design tokens: colours, radii, shadows), `admin.css` (dashboard layout and cards),
  `component.css`, `login.css`.

### A page, in full

```php
<?php
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['chairperson']);
$active = 'my-report';                                   // highlights the sidebar entry
$rows = fetchAll("SELECT full_name, status FROM riders WHERE stage_id = ?", [$_SESSION['stage_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head><?php $pageTitle = 'My report - BodaERP'; require __DIR__ . '/../../partials/head-assets.php'; ?></head>
<body>
<?php require __DIR__ . '/../../partials/sidebar-chairperson.php'; ?>
<div class="main-content" id="mainContent">
    <?php $pageTitle = 'My report'; $pageSubtitle = 'One line about it'; require __DIR__ . '/../../partials/topheader.php'; ?>
    <div class="content-area">
        <div class="card"><div class="card-body">
            <?php foreach ($rows as $r): ?><p><?= h($r['full_name']) ?>: <?= h($r['status']) ?></p><?php endforeach; ?>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/../../partials/scripts-footer.php'; ?>
</body>
</html>
```

To add it to the menu, add one line to the `$nav` array in `partials/sidebar-chairperson.php`
(`'my-report' => ['my-report.php', 'fa-file-alt', 'My report', null]`). Then run `php tests/smoke.php`.

### JavaScript rules ("clean JS")

- **Plain JavaScript only.** No Node, npm, bundler or framework. Libraries are committed, unchanged, in
  `packages/frontend/assets/vendor/` (Bootstrap 5.3.8, Font Awesome 7.3.1, ApexCharts 4.7.0, jsPDF 4.2.1 +
  AutoTable 5.0.9, qrcodejs 1.0.0, Inter, CKEditor 5), so pages work with no internet. Versions are listed in
  `vendor/VERSIONS.json`; `tests/check_dependencies.php` checks them for advisories.
- **Content Security Policy:** scripts run only from this site or from inline blocks carrying
  `nonce="<?= csp_nonce() ?>"`; `tests/check_portability.php` fails on an inline script without it.
- **Shared behaviour lives in two files**, loaded by `scripts-footer.php` on every page:
  `assets/js/main.js` (mobile sidebar, alert auto-hide, tooltips) and `assets/js/table-tools.js` (table export).
  Do **not** paste the same handler into individual pages: a duplicated sidebar handler used to cancel itself
  out and kept the mobile menu from ever opening.
- Page-specific code (a chart, a form calculation) goes in a short `<script>` at the bottom of that page only.
- Add libraries only when something needs them; each one is downloaded on every page view.

## Roles and data scope

| Role (`users.role`) | Scoped by | Notes |
|---|---|---|
| `super_admin` | nothing: sees all | The only role that can change cities, users, splits and the database |
| `city_admin` | `$_SESSION['city_id']` | Called *City council* in the UI |
| `chairperson` | `$_SESSION['stage_id']` | One stage |
| `rider` | own `riders` row | Self-service |
| `government` | nothing, **read-only** | `users.agency_name` names the ministry/agency |

## Money

Each city has a revenue split (city council / boda association / government / platform) that must total 100 %,
edited at Super Admin → Revenue Splits. Payments are recorded as cash, mobile money or bank transfer; mobile-money collection
goes through ioTec Pay (`iotec_functions.php`) and is confirmed by `ipn_handler.php` or by polling.

What a rider owes always comes from `permit_amount_due()` (functions.php): the permit fee plus the city's late fee
once the permit has been expired longer than the grace period. Cash, the rider portal and USSD all use it, and each
payment stores its `late_fee` and `channel` (`stage`, `portal`, `ussd`). Cash taken at a stage waits in Cash handover
until the chairperson bundles it and the city confirms what it counted (`cash_handovers`); a queried handover
releases its payments to be handed over again.

## Scheduled and background work

`automation_tick()` runs any due jobs at the end of a request (checked at most once a minute, and after the page has
been sent where the server supports it). `cron/run.php` does the same on a timer. Job status is at Super Admin →
System & Updates.

## Where things are decided

| Question | Answer |
|---|---|
| How does the URL folder get its value? | `app_base_url()` in `config/config.php`; override with `APP_BASE_URL` in `.env` |
| Local or live? | `db_is_local()`: CLI, `localhost`, `*.test` or a private address; force with `APP_ENV` |
| Why can't I see demo logins / the reset link on the server? | They need `db_is_dev_machine()` (local **and** the request comes from the same machine) |
| Where are settings like page titles and labels? | The `settings` table, read with `get_setting('key')` |
