<?php
// BodaERP - authentication, session, CSRF and role guard. Include at the very top of every page, before any
// HTML output.

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/automation.php';
require_once __DIR__ . '/uploads.php';

const REMEMBER_COOKIE = 'bodaerp_remember';

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli' && !defined('BODAERP_BACKGROUND')) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'secure'   => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name(SESSION_NAME);
    session_start();
}

const ROLE_DASHBOARDS = [
    'super_admin' => '/pages/superadmin/dashboard.php',
    'city_admin'  => '/pages/citycouncil/dashboard.php',
    'chairperson' => '/pages/chairperson/dashboard.php',
    'rider'       => '/pages/rider/dashboard.php',
    'government'  => '/pages/government/dashboard.php',
];

const ROLE_LABELS = [
    'super_admin' => 'Super Admin',
    'city_admin'  => 'City Council',
    'chairperson' => 'Stage Chairperson',
    'rider'       => 'Rider',
    'government'  => 'Government',
];

function dashboard_url_for(string $role): string {
    return ROLE_DASHBOARDS[$role] ?? '/login.php';
}

function role_label(string $role): string { return ROLE_LABELS[$role] ?? ucwords(str_replace('_', ' ', $role)); }

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (is_string($token) && $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token)) return;

    $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])
        || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/backend/');
    http_response_code(400);
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => get_setting('msg_form_expired', 'This form expired. Reload the page and try again.')]);
        exit;
    }
    app_error_page(419);
}

// One-time messages shown after a redirect (POST → redirect → GET)
function flash(string $type, string $message): void { $_SESSION['flash'][] = [$type, $message]; }

function flash_take(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function audit_log(string $action, ?string $entityType = null, ?string $entityId = null, string $details = ''): void {
    $u = current_user();
    runQuery(
        "INSERT INTO audit_logs (user_id,user_name_snapshot,role_snapshot,city_id,action,entity_type,entity_id,details,ip_address)
         VALUES (:uid,:name,:role,:city,:action,:etype,:eid,:details,:ip)",
        [
            'uid' => $u['user_id'] ?? null,
            'name' => $u['name'] ?? 'Unknown',
            'role' => $u['role'] ?? null,
            'city' => $u['city_id'] ?? null,
            'action' => $action,
            'etype' => $entityType !== null ? mb_substr($entityType, 0, 50) : null,
            'eid' => $entityId !== null ? mb_substr($entityId, 0, 50) : null,
            'details' => mb_substr($details, 0, 500),
            'ip' => client_ip(),
        ]
    );
}

/**
 * Checks the password, lockout and throttling.
 * @return array{ok:bool, user?:array, error?:string, needs_code?:bool}
 */
function attempt_login(string $email, string $password): array {
    $email = mb_strtolower(trim($email));
    if (!rate_limit('login-ip:' . client_ip(), 30, 900)) {
        return ['ok' => false, 'error' => 'Too many sign-in attempts from this network. Wait 15 minutes and try again.'];
    }
    if ($minutes = login_lockout_minutes($email)) {
        return ['ok' => false, 'error' => str_replace('{minutes}', (string) $minutes, get_setting('msg_login_locked', 'Too many failed attempts. Try again in {minutes} minutes.'))];
    }

    $user = fetchOne("SELECT * FROM users WHERE email = ? AND status = 'active' AND deleted_at IS NULL", [$email]);
    // Verify against a dummy hash when the user does not exist, so response time does not reveal which emails are registered.
    $hash = $user['password_hash'] ?? '$2y$10$Oqr3vafUgGeAMH1ZIYlTvu1L8cMOmql2Ce1PzJd/6umQixuQeL5rW';
    if (!password_verify($password, $hash) || !$user) {
        login_record_attempt($email, false);
        runQuery("INSERT INTO audit_logs (user_name_snapshot, action, details, ip_address) VALUES (?, 'LOGIN_FAILED', ?, ?)",
            [mb_substr($email, 0, 150), 'Invalid email or password', client_ip()]);
        if ($minutes = login_lockout_minutes($email)) {
            return ['ok' => false, 'error' => str_replace('{minutes}', (string) $minutes, get_setting('msg_login_locked', 'Too many failed attempts. Try again in {minutes} minutes.'))];
        }
        return ['ok' => false, 'error' => get_setting('msg_login_failed', 'That email and password do not match an active account.')];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        runQuery("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    login_record_attempt($email, true);

    if (two_factor_required($user)) {
        session_regenerate_id(true);
        $_SESSION['pending_2fa'] = ['user_id' => (int) $user['id']];
        two_factor_send_code($user);
        return ['ok' => true, 'needs_code' => true, 'user' => $user];
    }

    login_complete($user);
    return ['ok' => true, 'user' => $user];
}

/**
 * Where this person's sign-in codes can go right now: 'sms' (their confirmed mobile number, EgoSMS set up) and/or
 * 'email' (SMTP set up). Their chosen channel comes first. There is no authenticator-app option by decision.
 * @return list<'sms'|'email'>
 */
function two_factor_channels(array $user): array {
    $channels = [];
    if (sms_ready() && sms_normalize_phone($user['phone'] ?? '') && !empty($user['phone_verified_at'])) $channels[] = 'sms';
    if (!empty($user['email']) && mail_transport_name() === 'smtp') $channels[] = 'email';
    if (($user['two_factor_channel'] ?? 'email') === 'email') $channels = array_reverse($channels);
    return $channels;
}

function two_factor_required(array $user): bool {
    // With no way to deliver a code (no SMTP and no SMS), two-step sign-in stays off rather than locking everyone out.
    if (!two_factor_channels($user)) return false;
    if ((int) ($user['two_factor_enabled'] ?? 0) === 1 && setting_bool('feature_two_factor', true)) return true;
    $platform = fetchOne("SELECT require_2fa FROM platform_settings WHERE id = 1");
    return $platform && (int) $platform['require_2fa'] === 1 && in_array($user['role'], ['super_admin', 'city_admin', 'government'], true);
}

/**
 * Sends a fresh 6-digit code for the sign-in waiting in $_SESSION['pending_2fa'], by the person's chosen channel,
 * falling back to the other one when the first fails. Records where it went, for the code page.
 */
function two_factor_send_code(array $user): bool {
    $code = (string) random_int(100000, 999999);
    $_SESSION['pending_2fa'] = array_merge($_SESSION['pending_2fa'] ?? [], ['user_id' => (int) $user['id'],
        'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'expires' => time() + 600, 'tries' => 0]);
    foreach (two_factor_channels($user) as $channel) {
        $sent = $channel === 'sms'
            ? send_template_sms('two_factor_code', $user['phone'], ['code' => $code])
            : send_template_email('two_factor_code', $user['email'], ['name' => $user['name'], 'code' => $code]);
        if ($sent) {
            $_SESSION['pending_2fa']['channel'] = $channel;
            $_SESSION['pending_2fa']['hint'] = $channel === 'sms' ? sms_phone_hint((string) $user['phone']) : preg_replace('/(?<=.).(?=[^@]*@)/', '•', (string) $user['email']);
            return true;
        }
    }
    $_SESSION['pending_2fa']['channel'] = 'none';
    return false;
}

/** Second step of two-step sign-in. */
function verify_login_code(string $code): array {
    $p = $_SESSION['pending_2fa'] ?? null;
    if (!$p || $p['expires'] < time()) { unset($_SESSION['pending_2fa']); return ['ok' => false, 'error' => 'The code expired. Sign in again to get a new one.', 'restart' => true]; }
    if ($p['tries'] >= 5) { unset($_SESSION['pending_2fa']); return ['ok' => false, 'error' => 'Too many wrong codes. Sign in again.', 'restart' => true]; }
    $_SESSION['pending_2fa']['tries']++;
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $p['code_hash'])) return ['ok' => false, 'error' => 'That code is not right. Check the latest ' . (($p['channel'] ?? '') === 'sms' ? 'text message' : 'email') . ' and try again.'];
    $user = fetchOne("SELECT * FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$p['user_id']]);
    unset($_SESSION['pending_2fa']);
    if (!$user) return ['ok' => false, 'error' => 'This account is no longer active.', 'restart' => true];
    login_complete($user);
    return ['ok' => true, 'user' => $user];
}

/** Puts the user into the session and records the sign-in. */
function login_complete(array $user, string $how = 'password'): void {
    session_regenerate_id(true);
    $riderId = $user['role'] === 'rider' ? fetchValue("SELECT id FROM riders WHERE user_id = ? AND deleted_at IS NULL", [$user['id']]) : null;
    $city = $user['city_id'] ? fetchOne("SELECT name, logo_path, id_prefix FROM cities WHERE id = ?", [$user['city_id']]) : null;

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['name']          = $user['name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['city_id']       = $user['city_id'];
    $_SESSION['city_name']     = $city['name'] ?? null;
    $_SESSION['city_logo']     = $city['logo_path'] ?? null;
    $_SESSION['id_prefix']     = $city['id_prefix'] ?? null;
    $_SESSION['stage_id']      = $user['stage_id'];
    $_SESSION['rider_id']      = $riderId !== null ? (int) $riderId : null;
    $_SESSION['agency_name']   = $user['agency_name'] ?? null;
    $_SESSION['avatar_path']   = $user['avatar_path'] ?? null;
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
    csrf_token();

    auth_session_register();
    runQuery("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
    audit_log('LOGIN', 'user', (string) $user['id'], $how === 'remember' ? 'Signed in with "Keep me signed in"' : 'Successful login');
}

/** Records the current session so it can be listed and revoked (Super Admin, password change). */
function auth_session_register(): void {
    runQuery(
        "INSERT INTO login_sessions (id,user_id,ip_address,user_agent,expires_at,is_active)
         VALUES (:id,:uid,:ip,:ua,:exp,1)
         ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), ip_address=VALUES(ip_address), user_agent=VALUES(user_agent),
         last_activity=CURRENT_TIMESTAMP, expires_at=VALUES(expires_at), is_active=1",
        [
            'id' => session_id(), 'uid' => $_SESSION['user_id'],
            'ip' => client_ip(), 'ua' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'exp' => date('Y-m-d H:i:s', time() + SESSION_TIMEOUT),
        ]
    );
}

function remember_me_issue(int $userId): void {
    $days = max(1, min(90, setting_int('security_remember_days', 30)));
    $token = user_token_create($userId, 'remember', $days * 86400, false);
    setcookie(REMEMBER_COOKIE, $token, [
        'expires' => time() + $days * 86400, 'path' => BASE_URL === '' ? '/' : BASE_URL . '/',
        'secure' => app_is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function remember_me_clear(): void {
    $token = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (is_string($token) && preg_match('/^([a-f0-9]{24})\./', $token, $m)) {
        runQuery("DELETE FROM user_tokens WHERE selector = ? AND purpose = 'remember'", [$m[1]]);
    }
    setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => BASE_URL === '' ? '/' : BASE_URL . '/',
        'secure' => app_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

/** Signs the visitor in from a valid remember-me cookie (the token is rotated on every use). */
function remember_me_login(): bool {
    $token = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!is_string($token) || $token === '' || !setting_bool('feature_remember_me', true)) return false;
    $row = user_token_check($token, 'remember');
    if (!$row) { remember_me_clear(); return false; }
    $user = fetchOne("SELECT * FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$row['user_id']]);
    runQuery("DELETE FROM user_tokens WHERE id = ?", [$row['id']]);
    if (!$user || two_factor_required($user)) { remember_me_clear(); return false; }
    login_complete($user, 'remember');
    remember_me_issue((int) $user['id']);
    return true;
}

function logout_user(): void {
    if (is_logged_in()) {
        audit_log('LOGOUT', 'user', (string) $_SESSION['user_id'], 'User logged out');
        runQuery("UPDATE login_sessions SET is_active = 0 WHERE id = ?", [session_id()]);
    }
    remember_me_clear();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_unset();
    session_destroy();
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    return is_logged_in() ? $_SESSION : null;
}

/** Maintenance mode (Super Admin → Platform Settings): everyone except Super Admins sees the maintenance page. */
function maintenance_guard(): void {
    if (PHP_SAPI === 'cli') return;
    try { $on = (int) fetchValue("SELECT maintenance_mode FROM platform_settings WHERE id = 1"); }
    catch (Throwable $e) { error_log('BodaERP maintenance flag unreadable, site stays open: ' . $e->getMessage()); $on = 0; }
    if (!$on || (($_SESSION['role'] ?? '') === 'super_admin')) return;
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($script, ['login.php', 'logout.php', 'login-code.php', 'ipn_handler.php', 'run.php'], true)) return;
    header('Retry-After: 1800');
    app_error_page(503);
}

// Route guard: call as the first line of every protected page
function require_role(array $allowedRoles): array {
    if (!is_logged_in() && !remember_me_login()) {
        $back = $_SERVER['REQUEST_URI'] ?? '';
        redirect('/login.php' . ($back !== '' && $_SERVER['REQUEST_METHOD'] === 'GET' ? '?next=' . urlencode($back) : ''));
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logout_user();
        redirect('/login.php?timeout=1');
    }
    $active = fetchValue("SELECT is_active FROM login_sessions WHERE id = ? AND user_id = ?", [session_id(), $_SESSION['user_id']]);
    if ($active !== null && (int) $active === 0) {        // signed out elsewhere (password change, admin action)
        logout_user();
        redirect('/login.php?revoked=1');
    }
    $_SESSION['last_activity'] = time();
    automation_tick();   // runs due scheduled jobs after the response is sent (throttled to once a minute)

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        audit_log('ACCESS_DENIED', 'page', mb_substr($_SERVER['SCRIPT_NAME'] ?? '', 0, 100), 'Role ' . $_SESSION['role'] . ' tried to open a page for ' . implode(', ', $allowedRoles));
        app_error_page(403);
    }

    return $_SESSION;
}

/** Only allows redirects back into this site (prevents ?next=https://evil.example). */
function safe_next_path(?string $next): ?string {
    if (!is_string($next) || $next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '\\')) return null;
    if (BASE_URL !== '') {
        if (!str_starts_with($next, BASE_URL . '/')) return null;
        $next = substr($next, strlen(BASE_URL));
    }
    return str_starts_with($next, '/pages/') ? $next : null;
}

if (PHP_SAPI !== 'cli' && !defined('BODAERP_BACKGROUND')) {
    if (!is_logged_in() && !empty($_COOKIE[REMEMBER_COOKIE])) remember_me_login();
    maintenance_guard();
}
