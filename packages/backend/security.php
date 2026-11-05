<?php
// BodaERP - rate limiting, sign-in lockout, one-time tokens and password rules
require_once __DIR__ . '/db_helpers.php';

function client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Fixed-window counter. Returns true while the caller is within $max hits per $windowSeconds.
 * Used for the contact form, password-reset requests and sign-in attempts per IP.
 */
function rate_limit(string $bucket, int $max, int $windowSeconds): bool {
    $bucket = substr($bucket, 0, 190);
    $row = fetchOne("SELECT hits, window_start FROM rate_limits WHERE bucket = ?", [$bucket]);
    $now = time();
    if (!$row || strtotime($row['window_start']) + $windowSeconds <= $now) {
        runQuery("INSERT INTO rate_limits (bucket, hits, window_start) VALUES (?, 1, NOW())
                  ON DUPLICATE KEY UPDATE hits = 1, window_start = NOW()", [$bucket]);
        return true;
    }
    if ((int) $row['hits'] >= $max) return false;
    runQuery("UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?", [$bucket]);
    return true;
}

/** Minutes left on a lockout for this email, or 0. Counts failures since the last success. */
function login_lockout_minutes(string $email): int {
    $max = max(3, setting_int('security_login_max_attempts', 5));
    $minutes = max(1, setting_int('security_lockout_minutes', 15));
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $lastSuccess = fetchValue("SELECT MAX(attempted_at) FROM login_attempts WHERE email = ? AND success = 1", [$email]);
    $from = ($lastSuccess && $lastSuccess > $since) ? $lastSuccess : $since;
    $failures = fetchAll("SELECT attempted_at FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > ? ORDER BY attempted_at DESC LIMIT " . (int) $max,
        [$email, $from]);
    if (count($failures) < $max) return 0;
    $unlock = strtotime($failures[0]['attempted_at']) + $minutes * 60;
    return max(1, (int) ceil(($unlock - time()) / 60));
}

function login_record_attempt(string $email, bool $success): void {
    runQuery("INSERT INTO login_attempts (email, ip_address, success) VALUES (?,?,?)", [mb_substr($email, 0, 190), client_ip(), $success ? 1 : 0]);
    if (random_int(1, 50) === 1) {
        runQuery("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        runQuery("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        runQuery("DELETE FROM user_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
}

/**
 * Creates a one-time token. Only a SHA-256 hash is stored, so a database leak does not leak working links.
 * Returns "selector.validator" for the URL or cookie.
 */
function user_token_create(int $userId, string $purpose, int $ttlSeconds, bool $replaceExisting = true): string {
    if ($replaceExisting) {
        runQuery("DELETE FROM user_tokens WHERE user_id = ? AND purpose = ? AND used_at IS NULL", [$userId, $purpose]);
    }
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    runQuery("INSERT INTO user_tokens (user_id, purpose, selector, token_hash, expires_at, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)", [
        $userId, $purpose, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', time() + $ttlSeconds),
        client_ip(), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
    return $selector . '.' . $validator;
}

/** Returns the token row (with user_id) when valid and unused; does not consume it. */
function user_token_check(string $token, string $purpose): ?array {
    if (!preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $token, $m)) return null;
    $row = fetchOne("SELECT * FROM user_tokens WHERE selector = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()", [$m[1], $purpose]);
    if (!$row || !hash_equals($row['token_hash'], hash('sha256', $m[2]))) return null;
    return $row;
}

function user_token_consume(int $tokenId): bool {
    return runQuery("UPDATE user_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL", [$tokenId])->affected_rows === 1;
}

/** @return string[] problems with the password (empty = acceptable) */
function password_problems(string $password, string $email = '', string $name = ''): array {
    $min = max(8, setting_int('security_password_min_length', 8));
    $problems = [];
    if (mb_strlen($password) < $min) $problems[] = "Use at least $min characters.";
    if (mb_strlen($password) > 200) $problems[] = 'Use at most 200 characters.';
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) $problems[] = 'Use both letters and numbers.';
    $lower = mb_strtolower($password);
    $common = ['password', 'password1', '12345678', '123456789', 'qwerty123', 'bodaerp', 'bodaerp1', 'admin123', 'uganda123', 'kampala1'];
    if (in_array($lower, $common, true)) $problems[] = 'That password is too common.';
    $local = mb_strtolower(strtok($email, '@') ?: '');
    if ($local !== '' && mb_strlen($local) >= 4 && str_contains($lower, $local)) $problems[] = 'Do not use your email address in the password.';
    return $problems;
}

function set_user_password(int $userId, string $password): void {
    runQuery("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $userId]);
    runQuery("DELETE FROM user_tokens WHERE user_id = ? AND purpose IN ('password_reset','remember')", [$userId]);
    runQuery("UPDATE login_sessions SET is_active = 0 WHERE user_id = ?", [$userId]);
}
