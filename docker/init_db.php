<?php
// ============================================================
// BodaERP — container start-up database init (CLI only)
//   1. waits for the database to accept connections
//   2. applies pending database/migrations
//   3. if SEED_SAMPLE_DATA=1 and the users table is empty, loads
//      database/sample_data.sql (demo logins are listed at its top)
// Exits non-zero on failure so the container restarts and retries.
// ============================================================
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

define('BODAERP_SKIP_MIGRATIONS', true);   // run explicitly below
require_once __DIR__ . '/../packages/backend/db_helpers.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$deadline = time() + (int) (getenv('DB_WAIT_SECONDS') ?: 90);
while (true) {
    try {
        (new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT))->close();
        break;
    } catch (mysqli_sql_exception $e) {
        if (time() >= $deadline) { fwrite(STDERR, "init_db: database unreachable: {$e->getMessage()}\n"); exit(1); }
        echo "init_db: waiting for database at " . DB_HOST . ':' . DB_PORT . "...\n";
        sleep(2);
    }
}

$r = migrator_run_tracked('docker');
foreach ($r['applied'] as $name) echo "init_db: applied migration $name\n";
if ($r['failed'] || $r['error']) { fwrite(STDERR, "init_db: migration " . ($r['failed'] ?? '') . " failed: {$r['error']}\n"); exit(1); }

if (getenv('SEED_SAMPLE_DATA') === '1') {
    if ((int) fetchValue('SELECT COUNT(*) FROM users') === 0) {
        $db = db();
        $db->multi_query(file_get_contents(__DIR__ . '/../database/sample_data.sql'));
        do { if ($res = $db->store_result()) $res->free(); } while ($db->more_results() && $db->next_result());
        echo "init_db: loaded database/sample_data.sql\n";
    } else {
        echo "init_db: users already exist, skipping sample data\n";
    }
}
echo "init_db: database ready\n";
