<?php
// ============================================================
// BodaERP — Authentication, session, CSRF, and role-guard core.
// Include this (via functions.php) at the very top of every
// protected page, before any HTML output.
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
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
];

function dashboard_url_for(string $role): string {
    return ROLE_DASHBOARDS[$role] ?? '/login.php';
}

// ── CSRF ──────────────────────────────────────────────────────
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
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

// ── Audit logging ──────────────────────────────────────────────
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
            'etype' => $entityType,
            'eid' => $entityId,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]
    );
}

// ── Login / logout ───────────────────────────────────────────
function attempt_login(string $email, string $password): array|string {
    $user = fetchOne("SELECT * FROM users WHERE email = ? AND status = 'active' AND deleted_at IS NULL", [$email]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        runQuery(
            "INSERT INTO audit_logs (user_name_snapshot, action, details, ip_address) VALUES (?, 'LOGIN_FAILED', ?, ?)",
            [$email, 'Invalid email or password', $_SERVER['REMOTE_ADDR'] ?? '']
        );
        return 'Invalid email or password.';
    }

    session_regenerate_id(true);

    $riderId = null;
    if ($user['role'] === 'rider') {
        $riderId = fetchValue("SELECT id FROM riders WHERE user_id = ?", [$user['id']]);
    }

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
    $_SESSION['rider_id']      = $riderId;
    $_SESSION['last_activity'] = time();
    csrf_token();

    $sid = session_id();
    runQuery(
        "INSERT INTO login_sessions (id,user_id,ip_address,user_agent,expires_at,is_active)
         VALUES (:id,:uid,:ip,:ua,:exp,1)
         ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), ip_address=VALUES(ip_address), user_agent=VALUES(user_agent),
         last_activity=CURRENT_TIMESTAMP, expires_at=VALUES(expires_at), is_active=1",
        [
            'id' => $sid, 'uid' => $user['id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'exp' => date('Y-m-d H:i:s', time() + SESSION_TIMEOUT),
        ]
    );

    audit_log('LOGIN', 'user', (string) $user['id'], 'Successful login');

    return $user;
}

function logout_user(): void {
    if (is_logged_in()) {
        audit_log('LOGOUT', 'user', (string) $_SESSION['user_id'], 'User logged out');
        runQuery("UPDATE login_sessions SET is_active = 0 WHERE id = ?", [session_id()]);
    }
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

// ── Route guard: call as the first line of every protected page ──
function require_role(array $allowedRoles): array {
    if (!is_logged_in()) {
        redirect('/login.php');
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logout_user();
        redirect('/login.php?timeout=1');
    }
    $_SESSION['last_activity'] = time();

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        redirect(dashboard_url_for($_SESSION['role']));
    }

    return $_SESSION;
}
