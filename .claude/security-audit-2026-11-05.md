# BodaERP Security Audit — 2026-11-05

Auditor: security-auditor subagent (Opus 4.6)
Scope: uncommitted working tree at /Data/Projects/bodaerp-official
Constraint: does not re-report fixed gaps #1-28 from CLAUDE.md unless the fix is broken

## Scope checked
- [x] 1. Authorization / IDOR
- [x] 2. CSRF
- [x] 3. XSS
- [x] 4. SQL injection
- [x] 5. Auth flows
- [x] 6. Payments / webhooks
- [x] 7. Uploads / path traversal / .htaccess
- [x] 8. Secrets / config / debug / headers

---

## Findings

### SA-01 [High] IPN webhook secret verification checks all header values instead of a specific header name

**File:** `packages/backend/iotec_functions.php:294-302`

```php
function verifyIotecIpnSecret(): bool {
    if (IOTEC_IPN_SECRET === '') return false;
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($headers as $value) {                              // <-- iterates VALUES, ignores keys
        $value = trim((string) $value);
        if (str_starts_with($value, 'Bearer ')) $value = substr($value, 7);
        if ($value !== '' && hash_equals(IOTEC_IPN_SECRET, $value)) return true;
    }
    return false;
}
```

**Exploit scenario:** `getallheaders()` returns an associative array `['Header-Name' => 'value', ...]`. The `foreach ($headers as $value)` iterates over *values* only, comparing every header's value to the secret. If a legitimate upstream proxy or CDN sets a header whose value happens to match the IPN secret (unlikely but possible), it would pass. More importantly, the function does not verify *which* header carries the secret. The ioTec Pay documentation specifies a particular header for the shared secret (typically `Authorization` or a custom header like `X-Callback-Secret`). This code would accept the secret from ANY header, including `User-Agent`, `Referer`, or `Content-Type`.

**Fix:** Replace the loop with a check on the specific header ioTec uses. Example:

```php
function verifyIotecIpnSecret(): bool {
    if (IOTEC_IPN_SECRET === '') return false;
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = '';
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) { $auth = trim($value); break; }
    }
    if (str_starts_with($auth, 'Bearer ')) $auth = substr($auth, 7);
    return $auth !== '' && hash_equals(IOTEC_IPN_SECRET, $auth);
}
```

---

### SA-02 [High] Host header injection enables debug mode on a production server without APP_DEBUG or APP_ENV

**Files:** `packages/backend/database/database.php:26-32`, `packages/backend/app.php:59-62`

**Chain:**
1. `db_is_local()` (database.php:26) reads `$_SERVER['SERVER_NAME']`. Under Apache with `UseCanonicalName Off` (the default), `SERVER_NAME` is taken from the `Host` request header.
2. If the host is `localhost`, `127.0.0.1`, ends in `.test`, or matches a private-LAN pattern, `db_is_local()` returns `true`.
3. `app_is_debug()` (app.php:59-62): when neither `APP_DEBUG` nor `APP_ENV` is set in `.env`, it falls back to `db_is_local()`.
4. When debug is on, unhandled exceptions dump full PHP stack traces to the browser (app.php:100-104), exposing file paths, database credentials in connection errors, and internal logic.

**Exploit scenario:** An attacker sends `Host: localhost` to the production server. If the production `.env` has `APP_DEBUG=` (empty, as in `.env.example`) and `APP_ENV=` (empty), `db_is_local()` returns true, `app_is_debug()` returns true, and any 500 error now leaks a stack trace. This is especially dangerous during database outages when connection errors would reveal credentials.

**Fix (two layers):**
1. Change `.env.example` defaults so production deployments are safe by default:
   ```
   APP_ENV=live
   APP_DEBUG=0
   ```
2. Harden `app_is_debug()` to require an explicit opt-in rather than inheriting from `db_is_local()`:
   ```php
   function app_is_debug(): bool {
       $flag = env('APP_DEBUG');
       if ($flag !== null) return in_array(strtolower($flag), ['1','true','on','yes'], true);
       return PHP_SAPI === 'cli';   // never auto-enable for web requests
   }
   ```

---

### SA-03 [Medium] Stored DOM XSS via city name in innerHTML assignment

**File:** `packages/frontend/pages/superadmin/cities.php:332`

```js
document.getElementById('cityModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit City: ' + t.name;
```

**Chain:** City data is encoded via `js_json()` (which uses `JSON_HEX_TAG` to prevent `<script>`-breakout from the `<script>` block), embedded in the page as `const citiesData = <?= js_json($citiesById) ?>;`. When JavaScript's JSON parser decodes the string, `<` and `>` become literal `<` and `>`. Assigning the result to `innerHTML` causes the browser to parse it as HTML.

**Exploit scenario:** A super_admin creates a city named `<img src=x onerror=alert(document.cookie)>`. The next time any super_admin opens the cities page and clicks "Edit" on that city, the XSS fires. Practical impact is limited because only super_admins can create cities and only super_admins can view this page, but in a multi-admin deployment one compromised or malicious admin could escalate against another.

**Fix:** Use `textContent` instead of `innerHTML`, or build the DOM properly:

```js
function openEditCity(id) {
    const t = citiesData[id];
    if (!t) return;
    const title = document.getElementById('cityModalTitle');
    title.textContent = '';
    const icon = document.createElement('i');
    icon.className = 'fas fa-edit me-2';
    title.appendChild(icon);
    title.appendChild(document.createTextNode('Edit City: ' + t.name));
    // ... rest unchanged
}
```

Also applies to `openAddCity()` at line 314 (static HTML, so less risky, but should be consistent).

---

### SA-04 [Medium] No rate limiting on password change current-password verification

**File:** `packages/frontend/pages/account/settings.php:16-31`

The password change handler verifies the current password with `password_verify()` but does not call `rate_limit()`. Every other sensitive auth action is rate-limited (login: lockout + IP throttle; forgot-password: 10/hour per IP + 3/hour per email; email verification: 3/hour; contact form: 5/hour).

**Exploit scenario:** An attacker who has hijacked a user's session (e.g., via session fixation on a shared device, or a stolen remember-me cookie) can POST to `settings.php` with `action=password` and brute-force the current password at server speed. Once they find it, they change the password and lock out the real user. The lack of rate limiting means hundreds of attempts per minute are possible.

**Fix:** Add a rate limit keyed to the user ID:

```php
if ($action === 'password') {
    if (!rate_limit('pw-change:' . $uid, 5, 900)) {
        flash('error', 'Too many password change attempts. Wait 15 minutes.');
    } else {
        // existing logic
    }
}
```

---

### SA-05 [Medium] CDN scripts loaded without Subresource Integrity (SRI) hashes

**File:** `packages/frontend/partials/scripts-footer.php:13-15`

```php
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
```

Three JavaScript files are loaded from public CDNs without `integrity` or `crossorigin` attributes. If a CDN is compromised (jsdelivr and cdnjs have both had incidents), an attacker's script would execute in every portal page session with full access to cookies, session tokens, CSRF tokens, and all page data.

**Exploit scenario:** A CDN compromise or DNS hijack serves a modified bootstrap.bundle.min.js that exfiltrates `document.cookie` or injects a keylogger. Every logged-in user of every role is affected.

**Fix:** Add SRI hashes. Generate them with:
```bash
curl -s https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js | openssl dgst -sha384 -binary | openssl base64 -A
```
Then:
```html
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-<hash>" crossorigin="anonymous"></script>
```

The CLAUDE.md already notes self-hosting these as a future goal (item 4 under "NOT BLOCKING BUT NEEDED SOON"). Until then, SRI provides protection.

---

### SA-06 [Medium] php://input double-read in IPN flow (benign but fragile)

**Files:** `ipn_handler.php:14`, `packages/backend/iotec_functions.php:315`

`ipn_handler.php` reads `php://input` at line 14 for logging. Then `processIotecIpn()` reads it again at line 315. On PHP 8.x, `php://input` is re-readable (unlike PHP 5.x), so this works. However, it relies on an implementation detail and the two reads could diverge if a future PHP version changes this behavior, or if a SAPI (like certain FastCGI configurations) does not buffer the input.

The payment outcome is re-verified via the ioTec API before any state change (the code fetches transaction status from ioTec and only confirms if the API says "SUCCESSFUL"), so even if the second read failed, the payment flow itself is safe. The risk is that logging and processing could silently disagree about what was received.

**Fix:** Pass the raw body from `ipn_handler.php` into `processIotecIpn()` as a parameter instead of reading `php://input` twice:

```php
// ipn_handler.php
$raw = (string) file_get_contents('php://input', false, null, 0, 65536);
$result = processIotecIpn($raw);

// iotec_functions.php
function processIotecIpn(string $raw = ''): array {
    if (!verifyIotecIpnSecret()) return ['error' => 'Invalid or missing IPN secret'];
    if ($raw === '') $raw = file_get_contents('php://input');
    $payload = json_decode($raw);
    // ...
}
```

---

### SA-07 [Low] IOTEC_PUBLIC_BASE_URL derived from client-controlled HTTP_HOST

**File:** `packages/backend/iotec_config.php:49`

```php
$host = $_SERVER['HTTP_HOST'] ?? 'bodaerp.site';
define('IOTEC_PUBLIC_BASE_URL', $scheme . '://' . $host);
```

`HTTP_HOST` is client-controlled. This value is used only to define `IOTEC_IPN_URL` (line 52), which is a reference constant for the developer when configuring the ioTec merchant portal callback URL. It is never sent to ioTec in API calls and is not used in any security-sensitive path. If `APP_URL` is set (as recommended for production in `.env.example`), the app uses that for canonical URLs instead.

**Fix:** Use `app_url()` or the `APP_URL` env var instead:

```php
if (!defined('IOTEC_PUBLIC_BASE_URL')) {
    $url = getenv('APP_URL') ?: null;
    if (!$url) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'bodaerp.site');
    }
    define('IOTEC_PUBLIC_BASE_URL', $url);
}
```

---

### SA-08 [Low] Chairperson can set arbitrary rider status value via crafted POST

**File:** `packages/frontend/pages/chairperson/edit-rider.php:20`

```php
$status = $_POST['status'] ?? $rider['status'];
```

The HTML `<select>` offers three valid values: `active`, `expired`, `pending`. But the server does not validate the POST value against this allowlist. A chairperson can submit `status=suspended` or any arbitrary string. Compare with `packages/frontend/pages/citycouncil/city-riders.php:14`, which correctly validates:

```php
$status = in_array($_POST['status'] ?? '', ['active', 'pending', 'expired'], true) ? $_POST['status'] : 'pending';
```

**Impact:** A chairperson already manages their own stage's riders. The risk is data integrity: an unexpected status value could bypass business logic that checks for `active`/`expired`/`pending` elsewhere, or cause display issues.

**Fix:** Add the same allowlist validation:

```php
$status = in_array($_POST['status'] ?? '', ['active', 'pending', 'expired'], true)
    ? $_POST['status'] : $rider['status'];
```

---

### SA-09 [Low] Integer LIMIT/OFFSET values interpolated in SQL rather than parameterized

**Files:** `packages/frontend/pages/citycouncil/payment-transactions.php:53`, `packages/frontend/pages/citycouncil/city-riders.php:71`

```php
// payment-transactions.php:53
$where ORDER BY p.paid_at DESC LIMIT $perPage OFFSET $offset

// city-riders.php:71
$where ORDER BY $orderBy LIMIT $perPage OFFSET $offset
```

Both `$perPage` and `$offset` are derived from integer arithmetic on int-cast values, so they are not injectable. However, this breaks the project's otherwise consistent discipline of parameterizing all values. If a future change introduces a code path where these values are not properly cast, the interpolation pattern becomes a real injection vector.

**Fix:** Use placeholders for consistency:

```php
"... LIMIT ? OFFSET ?", array_merge($params, [$perPage, $offset])
```

Note: mysqli with prepared statements binds integers correctly.

---

## Areas reviewed with no new findings

**1. Authorization / IDOR:**
Every protected page calls `require_role()` with an explicit role list. Data is scoped by session variables (`$_SESSION['city_id']`, `$_SESSION['stage_id']`, `$_SESSION['rider_id']`). UPDATE and DELETE queries include the scoping column in the WHERE clause. Government portal is read-only (no POST handlers). The previously reported gap #9 (city_admin moving riders across cities) remains fixed. No new IDOR vectors found.

**2. CSRF:**
All state-changing POST handlers call `csrf_verify()` (which uses `hash_equals()` for timing-safe comparison). The token is per-session, regenerated on login. CSRF field is included in all forms. No state changes on GET requests (exports use GET but only read data scoped to the session).

**3. XSS (beyond SA-03):**
All PHP output uses `h()` (htmlspecialchars with ENT_QUOTES, UTF-8). Rich text goes through `safe_html()` (DOMDocument-based sanitizer that strips scripts, event handlers, non-allowlisted tags, and dangerous URIs). `js_json()` with JSON_HEX_TAG prevents script-tag breakout. Flash messages are h()-escaped. The `showToast()` function in main.js uses innerHTML with `${message}` but all callers pass static string literals.

**4. SQL injection:**
All queries use prepared statements through `_dbPrepare()`. `sort_sql()` uses an allowlist for ORDER BY. The `match()` expressions in my-riders.php and city-riders.php map to safe static strings. No raw user input in SQL beyond SA-09 (safe int interpolation).

**5. Auth flows:**
Session fixation prevented (session_regenerate_id on login and 2FA). Login lockout enforced. IP-based rate limiting on login. Hashed one-time tokens for password reset. Remember-me uses selector.validator with rotation. 2FA codes hashed with password_hash, 5-try limit, 10-minute expiry. safe_next_path() restricts redirects to /pages/ paths. Demo logins gated by db_is_dev_machine() (requires BOTH local DB AND local REMOTE_ADDR).

**6. Payments:**
payment_confirm() uses atomic UPDATE WHERE status='Pending' in a transaction. Payment amounts from DB (rider.annual_tax), never user input. Cash collection validates against city's annual_fee. ioTec API verification re-checks payment status server-side before confirming.

**7. Uploads / path traversal:**
upload_resolve() uses realpath() to prevent directory traversal, blocks `..` and null bytes. Rider photos served through PHP with role-based access (rider_photo_allowed). Content-type sniffing via finfo. Random filenames. .htaccess blocks direct access to uploads directory. Media upload requires super_admin + POST + CSRF.

**8. Secrets / config:**
No hardcoded credentials in tracked files. .htaccess blocks .env, .git, config/, database/, storage/. Cron endpoint requires a secret token (hash_equals). Health endpoint reveals only ok/degraded status. Database backup requires super_admin + POST + CSRF. Debug info on the database page (super_admin only) shows connection details but not the password.

---

## Summary Table

| ID | Severity | Title | File |
|---|---|---|---|
| SA-01 | High | IPN secret checks all header values, not a specific header | iotec_functions.php:294 |
| SA-02 | High | Host header injection enables debug mode in production | database.php:26 + app.php:62 |
| SA-03 | Medium | Stored DOM XSS via city name in innerHTML | superadmin/cities.php:332 |
| SA-04 | Medium | No rate limiting on password change attempts | account/settings.php:16 |
| SA-05 | Medium | CDN scripts without SRI hashes | partials/scripts-footer.php:13-15 |
| SA-06 | Medium | php://input double-read in IPN flow | ipn_handler.php:14 + iotec_functions.php:315 |
| SA-07 | Low | IOTEC_PUBLIC_BASE_URL from HTTP_HOST | iotec_config.php:49 |
| SA-08 | Low | Rider status not validated against allowlist | chairperson/edit-rider.php:20 |
| SA-09 | Low | Integer LIMIT/OFFSET interpolated in SQL | payment-transactions.php:53 |

**Totals:** 2 High, 4 Medium, 3 Low. No Critical findings (the previously reported critical issues in the gap register appear to remain properly fixed).

---

## Resolution (2026-11-05, verified)

Tests after the fixes: smoke 123/123, flows 85/85 (new: password-change throttle), access 334/334, portability clean.
Browser (Playwright): public home, Super Admin cities + revenue splits, rider ID card load every CDN script with
SRI and log no console errors; a `<img onerror>` city name renders as text in the Edit City modal.

| ID | Outcome |
|---|---|
| SA-01 | Severity disputed: an attacker still has to know the secret, so "any header" gives nothing away, and the payment outcome is re-read from ioTec's API anyway. The reporter's fix (Authorization only) could break the real callback, because ioTec's header name for this wallet is not known. Added optional `IOTEC_IPN_HEADER`: once set, only that header is checked. |
| SA-02 | Fixed at the root in `db_is_local()`: a local-looking host name counts only when `REMOTE_ADDR` is loopback/private. This also covers DB credential selection, schema writing and DB auto-create, not only debug output. Verified: `Host: localhost` from 41.210.1.1 / 8.8.8.8 resolves to live; 127.0.0.1 / 192.168.x stays local. |
| SA-03 | Fixed: the title icon is set as static markup and the city name is appended as text. |
| SA-04 | Fixed: `rate_limit('pw-change:<uid>', 5, 900)`. Covered by tests/flows.php. |
| SA-05 | Fixed: SHA-384 SRI + `crossorigin` on every CDN tag (Bootstrap CSS/JS, Font Awesome, ApexCharts, jsPDF, autotable, qrcodejs); Bootstrap hashes match the official ones. Removed a second ApexCharts 3.45 load on revenue-splits.php (3.54 already comes from head-assets). |
| SA-06 | Fixed: `ipn_handler.php` passes its body to `processIotecIpn($raw)`. |
| SA-07 | Fixed: `IOTEC_PUBLIC_BASE_URL` comes from `app_url()` (APP_URL first). |
| SA-08 | Fixed: status is allowlisted (`active`/`expired`/`pending`); the column is an ENUM anyway. |
| SA-09 | Not exploitable (both values are computed ints); made explicit with `(int)` casts, the same pattern security.php uses. Not bound as params: `runQuery` may bind them as strings, which MariaDB rejects in LIMIT. |
| SA-10 (new, found while fixing SA-02/07) | Low/config: when `APP_URL` is unset, `app_url()` builds email links from `SERVER_NAME`, which Apache takes from the client's Host header by default (the code comment claimed otherwise). On a server where this site is the default vhost, a forged Host header could poison password-reset links. DEPLOYMENT.md already sets APP_URL; it is now also a security-checklist item. |
