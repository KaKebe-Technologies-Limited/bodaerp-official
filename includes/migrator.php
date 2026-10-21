<?php
// ============================================================
// BodaERP — automatic, idempotent database migrations
//
// Every schema change lives in database/migrations/<timestamp>_<name>.php.
// Pending migrations are applied automatically (once, under a lock) the first
// time any request touches the database, so deploying new code is enough —
// nobody has to import SQL by hand. Applied migrations are recorded in the
// `schema_migrations` table.
//
// A migration file returns:  ['description' => '...', 'up' => function (mysqli $db) { ... }]
// and MUST be idempotent (use the mig_* helpers below, which check
// information_schema first). A failure never takes the site down: it is
// logged, shown to the Super Admin, and retried at most every 5 minutes.
// ============================================================

require_once __DIR__ . '/../config/database.php';

const MIGRATOR_RETRY_SECONDS = 300;

function migrator_dir(): string { return __DIR__ . '/../database/migrations'; }

/** @return array<string,string> migration name => file path, in run order */
function migrator_files(?string $dir = null): array {
    $files = glob(($dir ?? migrator_dir()) . '/*.php') ?: [];
    sort($files, SORT_STRING);
    $out = [];
    foreach ($files as $f) $out[basename($f, '.php')] = $f;
    return $out;
}

function migrator_ensure_table(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `migration`   VARCHAR(190) NOT NULL,
        `applied_at`  DATETIME NOT NULL,
        `applied_by`  VARCHAR(40) NOT NULL DEFAULT 'auto',
        `duration_ms` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** @return array<string,array> migration name => row */
function migrator_applied(mysqli $db): array {
    $out = [];
    $res = $db->query("SELECT migration, applied_at, applied_by, duration_ms FROM schema_migrations ORDER BY migration");
    while ($row = $res->fetch_assoc()) $out[$row['migration']] = $row;
    $res->free();
    return $out;
}

/** Status for the admin UI / CLI: every migration with its description and state. */
function migrator_status(?mysqli $db = null, ?string $dir = null): array {
    $db = $db ?? db();
    migrator_ensure_table($db);
    $applied = migrator_applied($db);
    $list = [];
    foreach (migrator_files($dir) as $name => $path) {
        $def = @include $path;
        $list[] = [
            'name'        => $name,
            'description' => is_array($def) ? ($def['description'] ?? '') : '',
            'applied_at'  => $applied[$name]['applied_at'] ?? null,
            'applied_by'  => $applied[$name]['applied_by'] ?? null,
        ];
    }
    return ['migrations' => $list, 'pending' => count(array_filter($list, fn($m) => $m['applied_at'] === null))];
}

/** Applies every pending migration, in order, stopping at the first failure. */
function migrator_run(?mysqli $db = null, string $by = 'auto', ?string $dir = null): array {
    $db = $db ?? db();
    $result = ['applied' => [], 'failed' => null, 'error' => null, 'skipped' => false];

    $lock = $db->query("SELECT GET_LOCK('bodaerp_migrate', 0)");
    $got = (int) $lock->fetch_row()[0]; $lock->free();
    if (!$got) { $result['skipped'] = true; return $result; }   // another request is already migrating

    try {
        migrator_ensure_table($db);
        $applied = migrator_applied($db);
        foreach (migrator_files($dir) as $name => $path) {
            if (isset($applied[$name])) continue;
            $t0 = microtime(true);
            try {
                $def = require $path;
                if (!is_array($def) || !isset($def['up']) || !is_callable($def['up'])) {
                    throw new RuntimeException("Migration file must return ['up' => callable].");
                }
                $def['up']($db);
                $ms = (int) round((microtime(true) - $t0) * 1000);
                $stmt = $db->prepare("INSERT INTO schema_migrations (migration, applied_at, applied_by, duration_ms) VALUES (?, NOW(), ?, ?)");
                $by40 = substr($by, 0, 40);
                $stmt->bind_param('ssi', $name, $by40, $ms);
                $stmt->execute(); $stmt->close();
                $result['applied'][] = $name;
            } catch (Throwable $e) {
                $result['failed'] = $name;
                $result['error']  = $e->getMessage();
                error_log("BodaERP migration {$name} failed: " . $e->getMessage());
                break;   // later migrations may depend on this one
            }
        }
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
        error_log('BodaERP migrator: ' . $e->getMessage());
    } finally {
        $db->query("SELECT RELEASE_LOCK('bodaerp_migrate')")->free();
    }
    return $result;
}

function migrator_fail_file(): string {
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bodaerp_migrate_fail_' . md5((defined('DB_HOST') ? DB_HOST : '') . (defined('DB_NAME') ? DB_NAME : '')) . '.json';
}

function migrator_last_failure(): ?array {
    $f = migrator_fail_file();
    if (!is_file($f)) return null;
    $data = json_decode((string) @file_get_contents($f), true);
    return is_array($data) ? $data : null;
}

/** Runs pending migrations and records/clears the failure marker. */
function migrator_run_tracked(string $by): array {
    $r = migrator_run(null, $by);
    if ($r['failed'] || ($r['error'] && !$r['skipped'])) {
        @file_put_contents(migrator_fail_file(), json_encode(['at' => date('Y-m-d H:i:s'), 'migration' => $r['failed'], 'error' => $r['error']]));
    } elseif (!$r['skipped']) {
        @unlink(migrator_fail_file());
    }
    return $r;
}

/** Cheap per-request hook: one COUNT query when nothing is pending. */
function migrator_auto(): void {
    static $ran = false;
    if ($ran) return;
    $ran = true;
    if (defined('BODAERP_SKIP_MIGRATIONS')) return;
    try {
        $db = db();
        $files = migrator_files();
        if (!$files) return;
        try { $done = (int) $db->query("SELECT COUNT(*) FROM schema_migrations")->fetch_row()[0]; }
        catch (Throwable $e) { $done = -1; }          // table doesn't exist yet
        if ($done >= count($files)) return;            // fast path: up to date

        $fail = migrator_fail_file();
        if (is_file($fail) && time() - (int) @filemtime($fail) < MIGRATOR_RETRY_SECONDS) return;   // back off after a failure
        migrator_run_tracked(PHP_SAPI === 'cli' ? 'cli' : 'auto');
    } catch (Throwable $e) {
        error_log('BodaERP migrator: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Helpers for writing idempotent migrations
// ------------------------------------------------------------

function mig_value(mysqli $db, string $sql, array $params = []) {
    $stmt = $db->prepare($sql);
    if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row[0] ?? null;
}

function mig_table_exists(mysqli $db, string $table): bool {
    return (int) mig_value($db, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]) > 0;
}

function mig_column_exists(mysqli $db, string $table, string $column): bool {
    return (int) mig_value($db, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]) > 0;
}

function mig_column_type(mysqli $db, string $table, string $column): ?string {
    return mig_value($db, "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
}

function mig_index_exists(mysqli $db, string $table, string $index): bool {
    return (int) mig_value($db, "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?", [$table, $index]) > 0;
}

function mig_check_clause(mysqli $db, string $table, string $name): ?string {
    return mig_value($db, "SELECT cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc
        JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
        WHERE tc.TABLE_SCHEMA = DATABASE() AND tc.TABLE_NAME = ? AND tc.CONSTRAINT_NAME = ? AND tc.CONSTRAINT_TYPE = 'CHECK'", [$table, $name]);
}

function mig_exec(mysqli $db, string $sql): void { $db->query($sql); }

/** Adds the column only if missing. $definition e.g. "VARCHAR(150) NULL AFTER stage_id". Returns true if it was added. */
function mig_add_column(mysqli $db, string $table, string $column, string $definition): bool {
    if (mig_column_exists($db, $table, $column)) return false;
    $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    return true;
}

/** Adds the index only if missing. $columns e.g. "`iotec_transaction_id`". */
function mig_add_index(mysqli $db, string $table, string $index, string $columns, bool $unique = false): bool {
    if (mig_index_exists($db, $table, $index)) return false;
    $db->query("ALTER TABLE `{$table}` ADD " . ($unique ? 'UNIQUE ' : '') . "INDEX `{$index}` ({$columns})");
    return true;
}

/** Executes a multi-statement .sql file (statements separated by ';'). */
function mig_run_sql_file(mysqli $db, string $path): void {
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException("Cannot read SQL file: {$path}");
    $db->multi_query($sql);
    do {
        if ($res = $db->store_result()) $res->free();
    } while ($db->more_results() && $db->next_result());
}
