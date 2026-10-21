<?php
// ============================================================
// BodaERP — nightly anomaly scan (CLI only)
//
// Hostinger hPanel → Advanced → Cron Jobs, e.g. every night at 02:00:
//   0 2 * * *  /usr/bin/php /home/<user>/domains/<domain>/public_html/cron/anomaly_scan.php
// ============================================================
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('anomaly_scan.php can only be run from the command line.');
}
require_once __DIR__ . '/../includes/anomaly_engine.php';

$r = run_anomaly_scan('cron');
if (isset($r['error']))   { fwrite(STDERR, $r['error'] . "\n"); exit(1); }
if (!empty($r['skipped'])) { echo "Another scan is already running.\n"; exit(0); }
printf("[%s] scan done in %d ms: %d new, %d auto-resolved, %d open\n", date('Y-m-d H:i:s'), $r['ms'], $r['new'], $r['resolved'], $r['open']);
foreach ($r['errors'] as $code => $msg) fwrite(STDERR, "rule $code failed: $msg\n");
