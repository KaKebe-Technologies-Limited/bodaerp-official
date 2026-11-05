<?php
// BodaERP: Automation (scheduled jobs) + system settings
// Jobs are defined here, their on/off switch and last result live in `automation_jobs`. They run
// automatically: 1. lazily: a cheap throttled check on normal page traffic runs whatever is due, after the
// response has been sent (no cron needed); 2. from cron/run.php (CLI or secret-token URL) for exact timing.
// A GET_LOCK guarantees two requests never run the same batch twice.

require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/anomaly_engine.php';
require_once __DIR__ . '/automation_notices.php';

function automation_tables_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { fetchValue("SELECT 1 FROM automation_jobs LIMIT 1"); $ready = true; }
    catch (\Throwable $e) { db_probe_failed('automation_jobs', $e); $ready = false; }
    return $ready;
}

function system_setting(string $key, ?string $default = null): ?string {
    try {
        $v = fetchValue("SELECT setting_value FROM system_settings WHERE setting_key = ?", [$key]);
        return $v === null ? $default : (string) $v;
    } catch (\Throwable $e) { db_probe_failed("system setting $key", $e); return $default; }
}

function system_setting_set(string $key, ?string $value): void {
    runQuery("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$key, $value])->close();
}

/** Secret used by cron/run.php?token=... (generated once, regenerable from the portal). */
function automation_cron_token(bool $regenerate = false): string {
    $t = $regenerate ? null : system_setting('cron_token');
    if (!$t) { $t = bin2hex(random_bytes(16)); system_setting_set('cron_token', $t); }
    return $t;
}

function automation_audit(string $details, string $entity = 'automation'): void {
    runQuery("INSERT INTO audit_logs (user_id, user_name_snapshot, role_snapshot, action, entity_type, details, ip_address)
              VALUES (NULL, 'System (automation)', NULL, 'UPDATE', ?, ?, 'cli')", [$entity, mb_substr($details, 0, 500)])->close();
}

/** code => [title, description, every N minutes, callable returning a result message]: in run order. */
function automation_jobs(): array {
    return [
        'expire_riders' => [
            'title' => 'Expire overdue riders',
            'desc'  => 'Marks riders as Expired once their permit date has passed, so compliance and defaulter lists are always correct.',
            'every' => 60,
            'run'   => function () {
                $r = runQuery("UPDATE riders SET status = 'expired'
                               WHERE status = 'active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE() AND deleted_at IS NULL");
                $n = max(0, $r->affected_rows); $r->close();
                if ($n > 0) automation_audit("Automatically marked {$n} rider(s) as expired (permit date passed)", 'rider');
                return $n === 0 ? 'No riders needed expiring.' : "Marked {$n} rider(s) as expired.";
            },
        ],
        'renewal_reminders' => [
            'title' => 'Send renewal reminders',
            'desc'  => 'Reminds riders in the portal and by email when their permit enters their city\'s reminder window, a week out and three days out. Honours each rider\'s notification choices.',
            'every' => 60,
            'run'   => 'job_renewal_reminders',
        ],
        'anomaly_scan' => [
            'title' => 'Data integrity scan',
            'desc'  => 'Runs the 15 duplicate / fraud / consistency checks and refreshes the Data Quality flags.',
            'every' => 1440,
            'run'   => function () {
                $res = run_anomaly_scan('automation');
                if (isset($res['error'])) throw new RuntimeException($res['error']);
                if (!empty($res['skipped'])) return 'Another scan was already running.';
                return "{$res['new']} new, {$res['resolved']} auto-resolved, {$res['open']} open.";
            },
        ],
        'cleanup_sessions' => [
            'title' => 'Clean up old sessions',
            'desc'  => 'Deletes expired and long-idle sign-in sessions to keep the database small and tidy.',
            'every' => 1440,
            'run'   => function () {
                $r = runQuery("DELETE FROM login_sessions
                    WHERE (expires_at IS NOT NULL AND expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
                       OR last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $n = max(0, $r->affected_rows); $r->close();
                return $n === 0 ? 'Nothing to clean.' : "Removed {$n} old session(s).";
            },
        ],
    ] + automation_notice_jobs();
}

/** Registers a job the first time it is seen; jobs that email people outside the team start switched off. */
function automation_register_job(string $code): void {
    $job = automation_jobs()[$code] ?? [];
    runQuery("INSERT IGNORE INTO automation_jobs (job_code, enabled) VALUES (?, ?)", [$code, empty($job['off_by_default']) ? 1 : 0])->close();
}

/** Jobs merged with their stored state; creates missing rows. */
function automation_state(): array {
    $jobs = automation_jobs();
    foreach ($jobs as $code => $_) automation_register_job($code);
    $rows = array_column(fetchAll("SELECT * FROM automation_jobs"), null, 'job_code');
    $out = [];
    foreach ($jobs as $code => $j) {
        $row = $rows[$code];
        $last = $row['last_run_at'] ? strtotime($row['last_run_at']) : null;
        $out[$code] = $j + [
            'code' => $code, 'enabled' => (int) $row['enabled'], 'last_run_at' => $row['last_run_at'],
            'last_status' => $row['last_status'], 'last_message' => $row['last_message'], 'last_ms' => (int) $row['last_duration_ms'],
            'run_count' => (int) $row['run_count'], 'by' => $row['last_triggered_by'],
            'due' => (int) $row['enabled'] === 1 && ($last === null || $last + $j['every'] * 60 <= time()),
            'next_due' => $last ? date('Y-m-d H:i:s', $last + $j['every'] * 60) : null,
        ];
    }
    return $out;
}

function automation_every_label(int $minutes): string {
    if ($minutes % 1440 === 0) return $minutes === 1440 ? 'Daily' : 'Every ' . ($minutes / 1440) . ' days';
    if ($minutes % 60 === 0)   return $minutes === 60 ? 'Hourly' : 'Every ' . ($minutes / 60) . ' hours';
    return "Every {$minutes} min";
}

function automation_run_job(string $code, string $by = 'manual'): array {
    $jobs = automation_jobs();
    if (!isset($jobs[$code])) return ['ok' => false, 'message' => 'Unknown job.'];
    automation_register_job($code);
    $t0 = microtime(true);
    try {
        $msg = (string) ($jobs[$code]['run'])();
        $ok = true;
    } catch (\Throwable $e) {
        $msg = $e->getMessage(); $ok = false;
        error_log("BodaERP automation {$code} failed: " . $e->getMessage());
    }
    $ms = (int) round((microtime(true) - $t0) * 1000);
    runQuery("UPDATE automation_jobs SET last_run_at = NOW(), last_status = ?, last_message = ?, last_duration_ms = ?,
              last_triggered_by = ?, run_count = run_count + 1 WHERE job_code = ?",
        [$ok ? 'ok' : 'error', mb_substr($msg, 0, 400), $ms, mb_substr($by, 0, 40), $code])->close();
    return ['ok' => $ok, 'message' => $msg, 'ms' => $ms];
}

/** Runs every enabled job that is due (or all enabled jobs when $force). */
function automation_run_due(string $by = 'auto', bool $force = false): array {
    if (!automation_tables_ready()) return ['ran' => [], 'skipped' => false];
    $db = db();
    $lock = $db->query("SELECT GET_LOCK('bodaerp_automation', 0)"); $got = (int) $lock->fetch_row()[0]; $lock->free();
    if (!$got) return ['ran' => [], 'skipped' => true];
    $ran = [];
    try {
        foreach (automation_state() as $code => $j) {
            if (!$j['enabled'] || (!$force && !$j['due'])) continue;
            $ran[$code] = automation_run_job($code, $by);
        }
    } finally {
        $db->query("SELECT RELEASE_LOCK('bodaerp_automation')")->free();
    }
    return ['ran' => $ran, 'skipped' => false];
}

/**
 * Sends the finished page to the browser before slow jobs run. PHP-FPM and LiteSpeed have a function for it;
 * under Apache mod_php (XAMPP, the Docker dev stack) the buffered page is sent with Content-Length and
 * Connection: close, so the browser stops waiting while PHP carries on.
 */
function automation_finish_response(): void {
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); return; }
    if (function_exists('litespeed_finish_request')) { litespeed_finish_request(); return; }
    ignore_user_abort(true);
    if (!headers_sent()) {
        $body = '';
        while (ob_get_level() > 0) $body = ob_get_clean() . $body;
        header('Connection: close');
        header('Content-Encoding: none');
        header('Content-Length: ' . strlen($body));
        echo $body;
    }
    while (ob_get_level() > 0) @ob_end_flush();
    flush();
}

/** Lazy trigger: at most one due-check per minute across all traffic; work runs after the response is sent. */
function automation_tick(): void {
    static $done = false;
    if ($done || PHP_SAPI === 'cli' || defined('BODAERP_SKIP_AUTOMATION')) return;
    $done = true;
    $marker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bodaerp_automation_tick_' . md5((defined('DB_HOST') ? DB_HOST : '') . (defined('DB_NAME') ? DB_NAME : ''));
    if (is_file($marker) && time() - (int) @filemtime($marker) < 60) return;
    @touch($marker);
    register_shutdown_function(function () {
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // don't hold the user's session lock
        // Jobs run under whoever's page view triggered them; without this a payment the job confirms would be
        // audited as that person's action.
        $_SESSION = [];
        automation_finish_response();
        try { automation_run_due('auto'); } catch (\Throwable $e) { error_log('BodaERP automation tick: ' . $e->getMessage()); }
    });
}
