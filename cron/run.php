<?php
// ============================================================
// BodaERP — scheduler entry point (runs every enabled automation job that is due)
//
// The app already runs due jobs by itself while people use it. Use cron only if you want
// exact timing even on quiet days. Either:
//   Command line (Hostinger → Advanced → Cron Jobs, every 5 minutes is plenty):
//     */5 * * * *  /usr/bin/php /home/<user>/domains/<domain>/public_html/cron/run.php
//   or a URL pinger (cron-job.org etc.) — the secret token is shown in
//     Super Admin → System & Updates → Scheduler:
//     https://<domain>/cron/run.php?token=<secret>
//   Add --all (CLI) or &all=1 (URL) to force every enabled job to run now.
// ============================================================
define('BODAERP_SKIP_AUTOMATION', true);
require_once __DIR__ . '/../includes/automation.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $given = (string) ($_GET['token'] ?? '');
    if (!automation_tables_ready() || $given === '' || !hash_equals(automation_cron_token(), $given)) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}
$force = $isCli ? in_array('--all', $argv, true) : !empty($_GET['all']);
$res = automation_run_due($isCli ? 'cron' : 'cron-url', $force);

if ($res['skipped']) { echo "Another run is in progress.\n"; exit(0); }
if (!$res['ran'])    { echo date('[Y-m-d H:i:s]') . " nothing due.\n"; exit(0); }
foreach ($res['ran'] as $code => $r) echo date('[Y-m-d H:i:s]') . " {$code}: " . ($r['ok'] ? 'ok' : 'ERROR') . " — {$r['message']}\n";
