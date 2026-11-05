<?php
// Scheduled jobs that reach people or look after the platform. automation.php runs them; the on/off switches are
// in Super Admin → System & Updates. Decisions about money or a rider's status stay with people: these jobs remind,
// report, verify and tidy, and never suspend, fine, refund or delete a record that carries legal weight.
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/backup.php';

const AUTOMATION_EMAIL_BATCH = 100;

/** How long housekeeping keeps each kind of operational record. Audit logs, payments and riders are never touched. */
const AUTOMATION_RETENTION = [
    'login_attempts_days' => 90, 'email_log_days' => 365, 'read_notifications_days' => 365, 'scan_runs_days' => 180,
    'used_tokens_days' => 30, 'spam_messages_days' => 90, 'mail_files_days' => 30, 'log_files_days' => 90, 'send_records_days' => 730,
];

/** Without SMTP, mail is written to disk for testing; bulk notices would only pile rider data up there. */
function automation_email_ready(): bool { return mail_transport_name() === 'smtp'; }

/** True the first time this message is claimed, so a re-run or a Run Now click can never send it twice. */
function automation_claim(string $kind, string $target, string $period): bool {
    $insert = runQuery("INSERT IGNORE INTO automation_sends (kind, target, period) VALUES (?,?,?)", [$kind, $target, $period]);
    $claimed = $insert->affected_rows === 1;
    $insert->close();
    return $claimed;
}

/**
 * Sends each message at most once and stops at the first failure, so a dead mail server cannot hold the job for
 * the full timeout per recipient. A failed claim is released for the next run; the job then shows as failed.
 * @param list<array{target:string,period:string,to:string,template:string,vars:array}> $messages
 */
function automation_send_batch(string $kind, array $messages): int {
    $sent = 0;
    foreach ($messages as $message) {
        if ($sent >= AUTOMATION_EMAIL_BATCH) break;
        if (!automation_claim($kind, $message['target'], $message['period'])) continue;
        if (!send_template_email($message['template'], $message['to'], $message['vars'])) {
            runQuery("DELETE FROM automation_sends WHERE kind = ? AND target = ? AND period = ?", [$kind, $message['target'], $message['period']])->close();
            throw new RuntimeException("Stopped after {$sent} email(s): the mail server refused {$message['template']}.");
        }
        $sent++;
    }
    return $sent;
}

function automation_email_note(int $sent): string {
    if (!automation_email_ready()) return 'Email is not configured, so nothing was emailed.';
    return "Emailed {$sent}.";
}

function job_renewal_reminders(): string {
    $inApp = runQuery("INSERT INTO notifications (rider_id, type, message)
        SELECT r.id, 'Renewal Reminder', CONCAT('Your permit expires on ', DATE_FORMAT(r.expiry_date, '%b %d, %Y'), '. Renew now to stay compliant.')
        FROM riders r JOIN cities c ON c.id = r.city_id JOIN users u ON u.id = r.user_id
        WHERE r.status = 'active' AND r.deleted_at IS NULL AND u.notify_in_app = 1
          AND r.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL c.reminder_days DAY)
          AND NOT EXISTS (SELECT 1 FROM notifications n WHERE n.rider_id = r.id AND n.type = 'Renewal Reminder'
                          AND n.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY))");
    $inAppCount = max(0, $inApp->affected_rows);
    $inApp->close();
    if (!automation_email_ready()) return "{$inAppCount} in-app reminder(s). " . automation_email_note(0);

    $riders = fetchAll("SELECT r.id, r.full_name, r.id_number, r.expiry_date, DATEDIFF(r.expiry_date, CURDATE()) AS days_left,
            COALESCE(NULLIF(u.email, ''), NULLIF(r.email, '')) AS to_email
        FROM riders r JOIN cities c ON c.id = r.city_id LEFT JOIN users u ON u.id = r.user_id
        WHERE r.status = 'active' AND r.deleted_at IS NULL AND c.status = 'active'
          AND r.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL c.reminder_days DAY)
          AND COALESCE(u.notify_email, 1) = 1 AND (u.id IS NULL OR u.status = 'active')
          AND COALESCE(NULLIF(u.email, ''), NULLIF(r.email, '')) IS NOT NULL
        ORDER BY r.expiry_date");
    $messages = [];
    foreach ($riders as $rider) {
        $daysLeft = (int) $rider['days_left'];
        // Three reminders per permit: on entering the window, a week out and three days out.
        $stage = $daysLeft <= 3 ? 'd3' : ($daysLeft <= 7 ? 'd7' : 'window');
        $messages[] = ['target' => (string) $rider['id'], 'period' => $rider['expiry_date'] . '|' . $stage, 'to' => $rider['to_email'], 'template' => 'permit_expiring', 'vars' => [
            'name' => $rider['full_name'], 'id_number' => $rider['id_number'], 'expiry_date' => formatDate($rider['expiry_date']),
            'days_left' => $daysLeft, 'portal_url' => app_url('pages/rider/renewal-status.php'),
        ]];
    }
    return "{$inAppCount} in-app reminder(s). " . automation_email_note(automation_send_batch('permit_expiring', $messages));
}

function job_overdue_notices(): string {
    $riders = fetchAll("SELECT r.id, r.user_id, r.full_name, r.id_number, r.expiry_date, DATEDIFF(CURDATE(), r.expiry_date) AS days_overdue,
            COALESCE(NULLIF(u.email, ''), NULLIF(r.email, '')) AS to_email, COALESCE(u.notify_email, 1) AS wants_email, COALESCE(u.notify_in_app, 0) AS wants_in_app
        FROM riders r JOIN cities c ON c.id = r.city_id LEFT JOIN users u ON u.id = r.user_id
        WHERE r.status IN ('active', 'expired') AND r.deleted_at IS NULL AND c.status = 'active'
          AND r.expiry_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND (u.id IS NULL OR u.status = 'active')
        ORDER BY r.expiry_date DESC");
    $inAppCount = 0;
    $messages = [];
    foreach ($riders as $rider) {
        $overdue = (int) $rider['days_overdue'];
        $milestone = $overdue >= 60 ? 'd60' : ($overdue >= 30 ? 'd30' : 'd7');
        $period = $rider['expiry_date'] . '|' . $milestone;
        if ((int) $rider['wants_in_app'] === 1 && automation_claim('overdue_in_app', (string) $rider['id'], $period)) {
            runQuery("INSERT INTO notifications (rider_id, type, message) VALUES (?, 'Renewal Reminder', ?)",
                [$rider['id'], 'Your permit expired on ' . formatDate($rider['expiry_date']) . ". Renew it to keep riding legally."])->close();
            $inAppCount++;
        }
        if ($rider['to_email'] && (int) $rider['wants_email'] === 1) {
            $messages[] = ['target' => (string) $rider['id'], 'period' => $period, 'to' => $rider['to_email'], 'template' => 'permit_overdue', 'vars' => [
                'name' => $rider['full_name'], 'id_number' => $rider['id_number'], 'expiry_date' => formatDate($rider['expiry_date']),
                'days_overdue' => $overdue, 'portal_url' => app_url('pages/rider/renewal-status.php'),
            ]];
        }
    }
    $sent = automation_email_ready() ? automation_send_batch('permit_overdue', $messages) : 0;
    return "{$inAppCount} in-app notice(s). " . automation_email_note($sent);
}

function job_digest_chairpersons(): string {
    if (!automation_email_ready()) return automation_email_note(0);
    $week = date('o-\WW');
    $chairpersons = fetchAll("SELECT u.id, u.name, u.email, u.stage_id, s.name AS stage_name FROM users u JOIN stages s ON s.id = u.stage_id
        WHERE u.role = 'chairperson' AND u.status = 'active' AND u.deleted_at IS NULL AND u.notify_email = 1 AND s.status = 'active'");
    $messages = [];
    foreach ($chairpersons as $chair) {
        $stageId = (int) $chair['stage_id'];
        $money = fetchOne("SELECT COUNT(*) AS payment_count, COALESCE(SUM(p.amount), 0) AS collected FROM payments p JOIN riders r ON r.id = p.rider_id
            WHERE r.stage_id = ? AND p.status = 'Confirmed' AND p.paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$stageId]);
        $active = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND deleted_at IS NULL AND status = 'active' AND expiry_date >= CURDATE()", [$stageId]);
        $overdue = (int) fetchValue("SELECT COUNT(*) FROM riders WHERE stage_id = ? AND deleted_at IS NULL AND status IN ('active','expired') AND expiry_date < CURDATE()", [$stageId]);
        $expiring = fetchAll("SELECT full_name, id_number, expiry_date FROM riders WHERE stage_id = ? AND deleted_at IS NULL AND status = 'active'
            AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) ORDER BY expiry_date", [$stageId]);
        $rows = '';
        foreach (array_slice($expiring, 0, 15) as $rider) {
            $rows .= '<tr><td>' . h($rider['full_name']) . '</td><td>' . h($rider['id_number']) . '</td><td>' . h(formatDate($rider['expiry_date'])) . '</td></tr>';
        }
        $messages[] = ['target' => (string) $chair['id'], 'period' => $week, 'to' => $chair['email'], 'template' => 'digest_chairperson', 'vars' => [
            'name' => $chair['name'], 'stage_name' => $chair['stage_name'], 'collected' => formatCurrency($money['collected']), 'payment_count' => (int) $money['payment_count'],
            'active_count' => $active, 'expiring_count' => count($expiring), 'overdue_count' => $overdue,
            'expiring_html' => $rows === '' ? '' : '<p><strong>Remind these riders first:</strong></p><table class="details">' . $rows . '</table>',
            'portal_url' => app_url('pages/chairperson/expiry-alerts.php'),
        ]];
    }
    return automation_email_note(automation_send_batch('digest_chairperson', $messages));
}

function job_digest_city_admins(): string {
    if (!automation_email_ready()) return automation_email_note(0);
    $week = date('o-\WW');
    $admins = fetchAll("SELECT u.id, u.name, u.email, u.city_id, c.name AS city_name FROM users u JOIN cities c ON c.id = u.city_id
        WHERE u.role = 'city_admin' AND u.status = 'active' AND u.deleted_at IS NULL AND u.notify_email = 1 AND c.status = 'active'");
    $messages = [];
    foreach ($admins as $admin) {
        $cityId = $admin['city_id'];
        $money = fetchOne("SELECT COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS collected FROM payments
            WHERE city_id = ? AND status = 'Confirmed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$cityId]);
        $stages = fetchAll("SELECT s.name, COALESCE(SUM(p.amount), 0) AS collected FROM stages s
            LEFT JOIN riders r ON r.stage_id = s.id LEFT JOIN payments p ON p.rider_id = r.id AND p.status = 'Confirmed' AND p.paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            WHERE s.city_id = ? GROUP BY s.id, s.name ORDER BY collected DESC LIMIT 10", [$cityId]);
        $rows = '';
        foreach ($stages as $stage) $rows .= '<tr><td>' . h($stage['name']) . '</td><td>' . h(formatCurrency($stage['collected'])) . '</td></tr>';
        $messages[] = ['target' => (string) $admin['id'], 'period' => $week, 'to' => $admin['email'], 'template' => 'digest_city', 'vars' => [
            'name' => $admin['name'], 'city_name' => $admin['city_name'], 'collected' => formatCurrency($money['collected']), 'payment_count' => (int) $money['payment_count'],
            'new_riders' => (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$cityId]),
            'active_count' => (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND deleted_at IS NULL AND status = 'active' AND expiry_date >= CURDATE()", [$cityId]),
            'overdue_count' => (int) fetchValue("SELECT COUNT(*) FROM riders WHERE city_id = ? AND deleted_at IS NULL AND status IN ('active','expired') AND expiry_date < CURDATE()", [$cityId]),
            'high_flags' => (int) fetchValue("SELECT COUNT(*) FROM anomaly_flags WHERE city_id = ? AND status = 'open' AND severity = 'high'", [$cityId]),
            'stages_html' => $rows === '' ? '' : '<p><strong>Collected by stage:</strong></p><table class="details">' . $rows . '</table>',
            'portal_url' => app_url('pages/citycouncil/dashboard.php'),
        ]];
    }
    return automation_email_note(automation_send_batch('digest_city', $messages));
}

function job_reconcile_payments(): string {
    $pending = (int) fetchValue("SELECT COUNT(*) FROM payments WHERE status = 'Pending' AND iotec_transaction_id IS NOT NULL AND iotec_transaction_id != ''");
    if ($pending === 0) return 'No mobile-money payments waiting.';
    require_once __DIR__ . '/iotec_functions.php';
    if (IOTEC_CLIENT_ID === '') return "{$pending} waiting, but mobile money is not configured.";
    $summary = reconcilePendingPayments(20);
    return "Checked {$summary['checked']}: {$summary['confirmed']} confirmed, {$summary['failed']} failed, {$summary['still_pending']} still waiting.";
}

function job_backup_database(): string {
    @set_time_limit(0);
    $backup = backup_create();
    $removed = backup_prune(max(1, (int) env('BACKUP_KEEP_DAYS', '14')));
    automation_audit("Automatic backup {$backup['name']} (" . number_format($backup['bytes'] / 1048576, 1) . ' MB)', 'backup');
    return number_format($backup['bytes'] / 1048576, 1) . ' MB, verified, ' . ($backup['encrypted'] ? 'encrypted' : 'NOT encrypted') . ". Removed {$removed} old backup(s).";
}

function job_retention_cleanup(): string {
    $days = AUTOMATION_RETENTION;
    $statements = [
        'sign-in attempts' => "DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL {$days['login_attempts_days']} DAY)",
        'rate-limit counters' => "DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 DAY)",
        'expired one-time links' => "DELETE FROM user_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL {$days['used_tokens_days']} DAY)",
        'email log rows' => "DELETE FROM email_log WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days['email_log_days']} DAY)",
        'read notifications' => "DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL {$days['read_notifications_days']} DAY)",
        'scan records' => "DELETE FROM anomaly_scan_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL {$days['scan_runs_days']} DAY)",
        'spam messages' => "DELETE FROM contact_messages WHERE status = 'spam' AND created_at < DATE_SUB(NOW(), INTERVAL {$days['spam_messages_days']} DAY)",
        'send records' => "DELETE FROM automation_sends WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days['send_records_days']} DAY)",
    ];
    $removed = [];
    foreach ($statements as $label => $sql) {
        $delete = runQuery($sql);
        if ($delete->affected_rows > 0) $removed[] = "{$delete->affected_rows} {$label}";
        $delete->close();
    }
    // Test emails hold personal details and exist only for development, so they must not sit on disk for ever.
    foreach ([['mail/*.eml', $days['mail_files_days'], 'mail files'], ['logs/*', $days['log_files_days'], 'log files']] as [$pattern, $keepDays, $label]) {
        $count = 0;
        foreach (glob(storage_path($pattern)) ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - $keepDays * 86400 && @unlink($file)) $count++;
        }
        if ($count > 0) $removed[] = "{$count} {$label}";
    }
    return $removed === [] ? 'Nothing was old enough to remove.' : 'Removed ' . implode(', ', $removed) . '.';
}

/**
 * What a Super Admin should look at now, from live checks. Each problem has a stable key so a changing number
 * does not count as a new problem and trigger another email.
 * @return list<array{key:string,text:string,url:string}>
 */
function automation_health_problems(): array {
    $problems = [];
    $add = function (string $key, string $text, string $path) use (&$problems) { $problems[] = ['key' => $key, 'text' => $text, 'url' => $path]; };

    $jobs = automation_tables_ready() ? automation_state() : [];
    foreach ($jobs as $code => $job) {
        if (!$job['enabled'] || !$job['last_run_at']) continue;
        if ($job['last_status'] === 'error') {
            $add("job_failed:$code", "The job \"{$job['title']}\" failed: {$job['last_message']}", 'pages/superadmin/system.php?tab=automation');
        } elseif (strtotime($job['last_run_at']) + ($job['every'] * 180) + 3600 < time()) {
            $add("job_late:$code", "The job \"{$job['title']}\" last ran on " . date('M d, H:i', strtotime($job['last_run_at'])) . ". The scheduler may have stopped.", 'pages/superadmin/system.php?tab=automation');
        }
    }

    if (($jobs['backup_database']['enabled'] ?? 0) && $jobs['backup_database']['last_run_at']) {
        $latest = backup_list()[0] ?? null;
        if (!$latest || $latest['time'] < time() - 2 * 86400) $add('backup_stale', 'No database backup in the last two days.', 'pages/superadmin/system.php?tab=backup');
    }
    $keyProblem = false;
    try { $hasKey = backup_key() !== null; } catch (Throwable $e) { $hasKey = false; $keyProblem = true; }
    if ($keyProblem) $add('backup_key_invalid', 'BACKUP_KEY in .env is not 64 hexadecimal characters, so backups are failing.', 'pages/superadmin/system.php?tab=backup');
    elseif (!$hasKey && !db_is_dev_machine()) $add('backup_unencrypted', 'Backups hold rider data but are not encrypted. Set BACKUP_KEY in .env.', 'pages/superadmin/system.php?tab=backup');

    $failedEmails = (int) fetchValue("SELECT COUNT(*) FROM email_log WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
    if ($failedEmails > 0) $add('email_failing', "{$failedEmails} email(s) failed to send in the last 24 hours.", 'pages/superadmin/email-templates.php');

    $stalePayments = (int) fetchValue("SELECT COUNT(*) FROM payments WHERE status = 'Pending' AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    if ($stalePayments > 0) $add('payments_stale', "{$stalePayments} payment(s) have been pending for over two hours.", 'pages/superadmin/data-quality.php');

    $openRequests = (int) fetchValue("SELECT COUNT(*) FROM data_requests WHERE status = 'open' AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)");
    if ($openRequests > 0) $add('data_requests_late', "{$openRequests} rider data request(s) have waited over 14 days for an answer.", 'pages/superadmin/data-requests.php');

    $unread = (int) fetchValue("SELECT COUNT(*) FROM contact_messages WHERE status = 'new' AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
    if ($unread > 0) $add('messages_unread', "{$unread} contact message(s) have been unread for over three days.", 'pages/superadmin/inbox.php');

    $newHigh = (int) fetchValue("SELECT COUNT(*) FROM anomaly_flags WHERE status = 'open' AND severity = 'high' AND first_detected_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
    if ($newHigh > 0) $add('flags_high', "{$newHigh} new high-severity data flag(s) in the last 24 hours.", 'pages/superadmin/data-quality.php');

    $free = @disk_free_space(storage_path()); $total = @disk_total_space(storage_path());
    if ($free !== false && $total && $free / $total < 0.10) $add('disk_low', 'Less than 10% of disk space is free on the server.', 'pages/superadmin/system.php?tab=backup');

    return $problems;
}

function job_ops_alerts(): string {
    $problems = automation_health_problems();
    if ($problems === []) return 'No problems found.';
    $count = count($problems);
    if (!automation_email_ready()) return "{$count} problem(s), listed on this page. " . automation_email_note(0);

    $keys = array_column($problems, 'key');
    sort($keys);
    $period = date('Y-m-d') . '|' . substr(md5(implode(',', $keys)), 0, 12);
    $list = '';
    foreach ($problems as $problem) $list .= '<li>' . h($problem['text']) . '</li>';
    $messages = [];
    foreach (fetchAll("SELECT id, email FROM users WHERE role = 'super_admin' AND status = 'active' AND deleted_at IS NULL AND notify_email = 1") as $admin) {
        $messages[] = ['target' => (string) $admin['id'], 'period' => $period, 'to' => $admin['email'], 'template' => 'ops_alert', 'vars' => [
            'problem_count' => $count, 'problems_html' => '<ul>' . $list . '</ul>', 'portal_url' => app_url('pages/superadmin/system.php?tab=automation'),
        ]];
    }
    $sent = automation_send_batch('ops_alert', $messages);
    return "{$count} problem(s). " . automation_email_note($sent);
}

/** Added to the job list in automation.php. 'off_by_default' marks jobs that email people outside the operator's team. */
function automation_notice_jobs(): array {
    return [
        'reconcile_payments' => ['title' => 'Re-check mobile-money payments', 'every' => 15, 'run' => 'job_reconcile_payments',
            'desc' => 'Asks ioTec about payments still waiting, so a rider who closed the page still gets their permit. Never guesses: anything unclear stays pending.'],
        'overdue_notices' => ['title' => 'Send overdue notices', 'every' => 60, 'run' => 'job_overdue_notices', 'off_by_default' => true,
            'desc' => 'Tells riders 7, 30 and 60 days after their permit expired, in the portal and by email. Off until you switch it on, because it contacts real riders.'],
        'digest_chairpersons' => ['title' => 'Weekly stage summary', 'every' => 10080, 'run' => 'job_digest_chairpersons', 'off_by_default' => true,
            'desc' => 'Emails each chairperson the fees recorded, permits expiring and riders overdue at their stage. Off until you switch it on.'],
        'digest_city_admins' => ['title' => 'Weekly council summary', 'every' => 10080, 'run' => 'job_digest_city_admins', 'off_by_default' => true,
            'desc' => 'Emails each city administrator revenue, new riders, overdue riders and open data flags. Off until you switch it on.'],
        'backup_database' => ['title' => 'Back up the database', 'every' => 1440, 'run' => 'job_backup_database',
            'desc' => 'Writes a verified, compressed copy to the storage folder and keeps two weeks of them. Encrypted when BACKUP_KEY is set.'],
        'retention_cleanup' => ['title' => 'Remove old operational records', 'every' => 1440, 'run' => 'job_retention_cleanup',
            'desc' => 'Removes old sign-in attempts, expired links, read notifications and test mail. Never touches riders, payments or audit logs.'],
        'ops_alerts' => ['title' => 'Check for problems', 'every' => 60, 'run' => 'job_ops_alerts',
            'desc' => 'Looks for failed jobs, stale backups, failing email, stuck payments and overdue requests, and emails Super Admins at most once a day for the same problems.'],
    ];
}
