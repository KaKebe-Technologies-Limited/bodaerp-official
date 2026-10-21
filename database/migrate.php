<?php
// ============================================================
// BodaERP — run / inspect database migrations from the command line
//   php database/migrate.php            apply every pending migration
//   php database/migrate.php --status   list applied / pending
// (Migrations also apply automatically on the first request after a deploy.)
// ============================================================
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Run from the command line: php database/migrate.php'); }
define('BODAERP_SKIP_MIGRATIONS', true);   // we run them explicitly below
require_once __DIR__ . '/../includes/db_helpers.php';

if (in_array('--status', $argv, true)) {
    $s = migrator_status();
    foreach ($s['migrations'] as $m) printf("%-9s %s  %s\n", $m['applied_at'] ? 'applied' : 'PENDING', $m['name'], $m['description']);
    printf("%d pending\n", $s['pending']);
    exit(0);
}
$r = migrator_run_tracked('cli');
foreach ($r['applied'] as $name) echo "applied  $name\n";
if ($r['skipped']) { echo "Another migration run is in progress.\n"; exit(0); }
if ($r['failed'] || $r['error']) { fwrite(STDERR, "FAILED " . ($r['failed'] ?? '') . ": " . $r['error'] . "\n"); exit(1); }
echo $r['applied'] ? "Done.\n" : "Database is already up to date.\n";
