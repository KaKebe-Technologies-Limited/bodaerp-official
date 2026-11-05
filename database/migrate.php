<?php
// BodaERP: update / inspect the database from the command line php database/migrate.php
// apply pending migrations, then sync the schema snapshot php database/migrate.php --status
// what is applied / pending / would change php database/migrate.php --capture
// save this database's structure to database/schema.snapshot.json (All of this also happens automatically
// on the first request after a deploy.)
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Run from the command line: php database/migrate.php'); }
define('BODAERP_SKIP_MIGRATIONS', true);   // we run them explicitly below
require_once __DIR__ . '/../packages/backend/db_helpers.php';

if (in_array('--capture', $argv, true)) {
    $r = schema_capture('cli', in_array('--force', $argv, true));
    echo $r['changed'] ? "Saved {$r['tables']} tables to database/schema.snapshot.json: commit it with your code.\n"
                       : "Snapshot already matches this database ({$r['tables']} tables).\n";
    exit(0);
}

if (in_array('--status', $argv, true)) {
    $s = migrator_status();
    foreach ($s['migrations'] as $m) printf("%-9s %s  %s\n", $m['applied_at'] ? 'applied' : 'PENDING', $m['name'], $m['description']);
    printf("%d migration(s) pending\n", $s['pending']);
    $snap = schema_snapshot_load();
    if (!$snap) { echo "No schema snapshot yet (php database/migrate.php --capture).\n"; exit(0); }
    $plan = schema_plan(db(), $snap);
    foreach ($plan['actions'] as $a) printf("WOULD %-13s %s.%s  %s\n", $a['type'], $a['table'], $a['name'], $a['detail']);
    foreach ($plan['drift'] as $d)   printf("REVIEW %-6s %s.%s  snapshot: %s | here: %s\n", $d['kind'], $d['table'], $d['name'], $d['snapshot'], $d['current']);
    printf("Schema: %d change(s) pending, %d need review\n", count($plan['actions']), count($plan['drift']));
    exit(0);
}

$exit = 0;
$r = migrator_run_tracked('cli');
foreach ($r['applied'] as $name) echo "applied  $name\n";
if ($r['skipped']) { echo "Another migration run is in progress.\n"; exit(0); }
if ($r['failed'] || $r['error']) { fwrite(STDERR, "FAILED " . ($r['failed'] ?? '') . ": " . $r['error'] . "\n"); exit(1); }
echo $r['applied'] ? "Migrations done.\n" : "Migrations: already up to date.\n";

$sr = schema_sync_run_tracked('cli');
if ($sr['no_snapshot']) { echo "Schema: no snapshot file yet: nothing to sync.\n"; exit(0); }
if ($sr['skipped'])     { echo "Another schema sync is in progress.\n"; exit(0); }
foreach ($sr['applied'] as $a) printf("synced   %-13s %s.%s\n", $a['type'], $a['table'], $a['name']);
foreach ($sr['failed']  as $f) { fwrite(STDERR, sprintf("FAILED   %s %s.%s: %s\n", $f['type'], $f['table'], $f['name'], $f['error'])); $exit = 1; }
if ($sr['error'])              { fwrite(STDERR, "Schema sync error: {$sr['error']}\n"); $exit = 1; }
if ($sr['drift']) printf("%d difference(s) need review (not applied): see Super Admin → Database.\n", count($sr['drift']));
echo $sr['applied'] ? "Schema synced.\n" : "Schema: already up to date.\n";
exit($exit);
