<?php
// ============================================================
// BodaERP — automatic schema sync  ("change it locally, the server follows")
//
//  1. On your own computer the app watches the local database. Whenever tables,
//     columns or indexes change — however they changed (phpMyAdmin, a
//     migration, Claude) — their structure (never data) is saved to
//     database/schema.snapshot.json. Commit that file with your code.
//  2. Any other copy of the app (the live server, a teammate's laptop) compares
//     the snapshot with its own database on the first request after the new
//     code arrives and ADDS whatever is missing: tables, columns, indexes,
//     foreign keys, plus safe widenings (longer varchar, bigger int, more enum
//     values, NULL allowed). It never drops, renames or shrinks anything and
//     never touches rows.
//  3. Anything riskier (different column types, differing indexes) is listed
//     as "Needs review" in Super Admin → Database; that kind of change belongs
//     in a migration file (database/migrations/), which also runs by itself.
//
// A sync problem never takes the site down: it is logged, shown to the Super
// Admin and retried later. Views, triggers and stored routines are not tracked
// here — use a migration for those.
// ============================================================

require_once __DIR__ . '/database/database.php';

const SCHEMA_SNAPSHOT_FORMAT = 1;
const SCHEMA_RETRY_SECONDS   = 300;    // back-off after a failed automatic run
const SCHEMA_FAILED_RETRY    = 3600;   // a failed state is retried automatically after this long
const SCHEMA_CAPTURE_EVERY   = 10;     // local auto-save looks at most every N seconds
const SCHEMA_STATE_KEY       = 'schema_sync_state';

function schema_snapshot_path(): string {
    $p = getenv('SCHEMA_SNAPSHOT_FILE');
    return ($p !== false && $p !== '') ? $p : __DIR__ . '/../../database/schema.snapshot.json';
}

function _sq(string $identifier): string { return '`' . str_replace('`', '``', $identifier) . '`'; }

/** Tables that are never part of the snapshot (internal, scratch or backup copies). */
function schema_ignored_table(string $t): bool {
    static $extra = null;
    if ($extra === null) $extra = array_filter(array_map('trim', explode(',', (string) getenv('SCHEMA_IGNORE_TABLES'))));
    if ($t === 'schema_migrations' || in_array($t, $extra, true)) return true;
    return (bool) preg_match('/^(tmp|temp|bak|backup|old|zz)_|_(bak|backup|old|tmp)$/i', $t);
}

// ------------------------------------------------------------
// Reading structure
// ------------------------------------------------------------

/** Splits the text of SHOW CREATE TABLE into columns / indexes / foreign keys / checks / options. */
function schema_parse_create(string $sql): array {
    $out = ['columns' => [], 'indexes' => [], 'fks' => [], 'checks' => [], 'engine' => null, 'charset' => null, 'collation' => null];
    $lines = preg_split('/\R/', trim($sql));
    $unq = fn(string $s): string => str_replace('``', '`', $s);
    for ($i = 1, $n = count($lines); $i < $n; $i++) {
        $line = trim($lines[$i]);
        if ($line === '') continue;
        if ($line[0] === ')') {
            if (preg_match('/ENGINE=(\w+)/i', $line, $m))          $out['engine']    = $m[1];
            if (preg_match('/DEFAULT CHARSET=(\w+)/i', $line, $m)) $out['charset']   = $m[1];
            if (preg_match('/COLLATE=(\w+)/i', $line, $m))         $out['collation'] = $m[1];
            break;
        }
        $line = rtrim($line, ',');
        if ($line[0] === '`') {
            if (preg_match('/^`((?:[^`]|``)+)`\s+(.+)$/s', $line, $m)) $out['columns'][$unq($m[1])] = $m[2];
        } elseif (preg_match('/^PRIMARY KEY\b/i', $line)) {
            $out['indexes']['PRIMARY'] = $line;
        } elseif (preg_match('/^(?:UNIQUE |FULLTEXT |SPATIAL )?KEY\s+`((?:[^`]|``)+)`/i', $line, $m)) {
            $out['indexes'][$unq($m[1])] = $line;
        } elseif (preg_match('/^CONSTRAINT\s+`((?:[^`]|``)+)`\s+FOREIGN KEY/i', $line, $m)) {
            $out['fks'][$unq($m[1])] = $line;
        } elseif (preg_match('/^CONSTRAINT\s+`((?:[^`]|``)+)`\s+CHECK/i', $line, $m)) {
            $out['checks'][$unq($m[1])] = $line;
        }
    }
    return $out;
}

/** @return array<string,array{parsed:array,types:array}> every non-ignored base table of the current database */
function schema_read(mysqli $db): array {
    $names = [];
    $res = $db->query("SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
    while ($r = $res->fetch_assoc()) if (!schema_ignored_table($r['t'])) $names[] = $r['t'];
    $res->free();

    $types = [];
    $res = $db->query("SELECT TABLE_NAME AS t, COLUMN_NAME AS c, COLUMN_TYPE AS ct, IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
    while ($r = $res->fetch_assoc()) $types[$r['t']][$r['c']] = ['type' => $r['ct'], 'null' => $r['n'] === 'YES'];
    $res->free();

    $out = [];
    foreach ($names as $t) {
        $row = $db->query('SHOW CREATE TABLE ' . _sq($t))->fetch_row();
        $out[$t] = ['parsed' => schema_parse_create((string) $row[1]), 'types' => $types[$t] ?? []];
    }
    return $out;
}

/** Counts of objects the sync does not handle (views, triggers, stored routines). */
function schema_unsupported_objects(?mysqli $db = null): array {
    $db = $db ?? db();
    $one = fn(string $sql): int => (int) $db->query($sql)->fetch_row()[0];
    return [
        'views'    => $one("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()"),
        'triggers' => $one("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()"),
        'routines' => $one("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()"),
    ];
}

// ------------------------------------------------------------
// Snapshot file
// ------------------------------------------------------------

function schema_snapshot_build(?mysqli $db = null): array {
    $db = $db ?? db();
    $tables = [];
    foreach (schema_read($db) as $t => $info) {
        $p = $info['parsed'];
        $cols = [];
        foreach ($p['columns'] as $name => $def) {
            $ty = $info['types'][$name] ?? ['type' => '', 'null' => true];
            $cols[] = ['name' => $name, 'type' => $ty['type'], 'null' => $ty['null'], 'def' => $def];
        }
        $tables[$t] = [
            'engine' => $p['engine'], 'charset' => $p['charset'], 'collation' => $p['collation'],
            'columns' => $cols, 'indexes' => $p['indexes'], 'foreign_keys' => $p['fks'], 'checks' => $p['checks'],
        ];
    }
    ksort($tables);
    return ['format' => SCHEMA_SNAPSHOT_FORMAT, 'tables' => $tables];
}

/** Canonical JSON. $meta = null gives the content-only form used to detect changes. */
function schema_snapshot_json(array $snap, ?array $meta): string {
    $doc = [];
    if ($meta !== null) {
        $doc['_note'] = 'Structure of the database (no data). Written automatically on the developer machine; applied automatically on every other copy. Commit it with the code. Safe to delete — it is rebuilt from the local database.';
        $doc['meta']  = $meta;
    }
    $doc['format'] = $snap['format'] ?? SCHEMA_SNAPSHOT_FORMAT;
    $doc['tables'] = $snap['tables'] ?? [];
    return json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

function schema_snapshot_load(): ?array {
    $path = schema_snapshot_path();
    if (!is_file($path)) return null;
    $data = json_decode((string) @file_get_contents($path), true);
    return (is_array($data) && isset($data['tables']) && is_array($data['tables'])) ? $data : null;
}

function schema_snapshot_md5(): ?string {
    $path = schema_snapshot_path();
    return is_file($path) ? md5_file($path) : null;
}

// ------------------------------------------------------------
// Comparing — what would change on this database
// ------------------------------------------------------------

function schema_norm_type(string $t): string {
    $t = strtolower(trim(preg_replace('/\s+/', ' ', $t)));
    $t = preg_replace('/\b(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/', '$1', $t);   // display width differs between servers
    return str_replace('integer', 'int', $t);
}

/** True when changing a column from type $from to $to can never lose or alter data. */
function schema_type_widens(string $from, string $to): bool {
    $ints = ['tinyint' => 1, 'smallint' => 2, 'mediumint' => 3, 'int' => 4, 'bigint' => 5];
    if (preg_match('/^(\w+?)( unsigned)?$/', $from, $a) && preg_match('/^(\w+?)( unsigned)?$/', $to, $b) && isset($ints[$a[1]], $ints[$b[1]])) {
        return ($a[2] ?? '') === ($b[2] ?? '') && $ints[$b[1]] > $ints[$a[1]];
    }
    foreach (['varchar', 'char'] as $f) {
        if (preg_match("/^$f\\((\\d+)\\)$/", $from, $a) && preg_match("/^$f\\((\\d+)\\)$/", $to, $b)) return (int) $b[1] > (int) $a[1];
    }
    $text = ['tinytext' => 1, 'text' => 2, 'mediumtext' => 3, 'longtext' => 4];
    $blob = ['tinyblob' => 1, 'blob' => 2, 'mediumblob' => 3, 'longblob' => 4];
    foreach ([$text, $blob] as $fam) {
        if (isset($fam[$from], $fam[$to])) return $fam[$to] > $fam[$from];
    }
    if (preg_match('/^varchar\(\d+\)$/', $from) && isset($text[$to]) && $to !== 'tinytext') return true;
    if (preg_match('/^decimal\((\d+),(\d+)\)( unsigned)?$/', $from, $a) && preg_match('/^decimal\((\d+),(\d+)\)( unsigned)?$/', $to, $b)) {
        return $a[2] === $b[2] && ($a[3] ?? '') === ($b[3] ?? '') && (int) $b[1] > (int) $a[1];
    }
    if (preg_match('/^(enum|set)\((.*)\)$/', $from, $a) && preg_match('/^(enum|set)\((.*)\)$/', $to, $b) && $a[1] === $b[1]) {
        $vals = function (string $s): array { preg_match_all("/'((?:[^']|'')*)'/", $s, $m); return $m[1]; };
        $x = $vals($a[2]); $y = $vals($b[2]);
        return count($y) > count($x) && array_slice($y, 0, count($x)) === $x;     // only new values at the end
    }
    return false;
}

/** @return string 'same' | 'widen' | 'drift' */
function schema_compare_column(array $want, array $have): string {
    $w = schema_norm_type($want['type']);
    $h = schema_norm_type($have['type']);
    $wantNull = (bool) $want['null']; $haveNull = (bool) $have['null'];
    $tightens = !$wantNull && $haveNull;           // snapshot says NOT NULL, this database allows NULL → can't enforce blindly
    if ($w === $h) {
        if ($tightens) return 'drift';
        return $wantNull !== $haveNull ? 'widen' : 'same';     // NULL newly allowed is safe
    }
    return (!$tightens && schema_type_widens($h, $w)) ? 'widen' : 'drift';
}

function schema_index_sig(string $def): string {
    $kind = 'KEY';
    if (preg_match('/^PRIMARY KEY/i', $def))   $kind = 'PRIMARY';
    elseif (preg_match('/^UNIQUE/i', $def))    $kind = 'UNIQUE';
    elseif (preg_match('/^FULLTEXT/i', $def))  $kind = 'FULLTEXT';
    elseif (preg_match('/^SPATIAL/i', $def))  $kind = 'SPATIAL';
    $cols = preg_match('/\((.+)\)/', $def, $m) ? preg_replace('/\s+/', '', strtolower($m[1])) : '';
    return $kind . '|' . $cols;
}

function schema_fk_sig(string $def): string {
    return strtolower(preg_replace('/\s+/', ' ', preg_replace('/^CONSTRAINT\s+`(?:[^`]|``)+`\s+/i', '', $def)));
}

function schema_create_sql(string $t, array $want): string {
    $parts = [];
    foreach ($want['columns'] as $c) $parts[] = '  ' . _sq($c['name']) . ' ' . $c['def'];
    foreach ($want['indexes'] ?? [] as $d) $parts[] = '  ' . $d;
    foreach ($want['checks'] ?? [] as $d)  $parts[] = '  ' . $d;
    $opts = ' ENGINE=' . ($want['engine'] ?: 'InnoDB') . ' DEFAULT CHARSET=' . ($want['charset'] ?: 'utf8mb4')
          . (!empty($want['collation']) ? ' COLLATE=' . $want['collation'] : '');
    return 'CREATE TABLE IF NOT EXISTS ' . _sq($t) . " (\n" . implode(",\n", $parts) . "\n)" . $opts;
}

/**
 * What the snapshot would change on $db.
 * @return array{actions:array,drift:array,extras:array}
 *   actions: ordered, safe to apply   [type, table, name, detail, sql]
 *   drift:   differences that are NOT auto-applied   [table, name, kind, snapshot, current]
 *   extras:  tables that exist here but are not in the snapshot
 */
function schema_plan(mysqli $db, array $snap): array {
    $cur = schema_read($db);
    $views = [];
    $res = $db->query("SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'VIEW'");
    while ($r = $res->fetch_assoc()) $views[$r['t']] = true;
    $res->free();
    $phase = ['create_table' => [], 'add_column' => [], 'widen_column' => [], 'add_index' => [], 'add_fk' => []];
    $drift = [];

    foreach ($snap['tables'] ?? [] as $t => $want) {
        if (schema_ignored_table($t) || empty($want['columns'])) continue;

        if (!isset($cur[$t])) {
            if (isset($views[$t])) {      // a view of that name exists here: never create a table over it
                $drift[] = ['table' => $t, 'name' => $t, 'kind' => 'object', 'snapshot' => 'table', 'current' => 'view'];
                continue;
            }
            $phase['create_table'][] = ['type' => 'create_table', 'table' => $t, 'name' => $t,
                'detail' => count($want['columns']) . ' columns', 'sql' => schema_create_sql($t, $want)];
            foreach ($want['foreign_keys'] ?? [] as $fn => $def) {
                $phase['add_fk'][] = ['type' => 'add_fk', 'table' => $t, 'name' => $fn, 'detail' => $def,
                    'sql' => 'ALTER TABLE ' . _sq($t) . ' ADD ' . $def];
            }
            continue;
        }

        $have = $cur[$t]['parsed'];
        $haveTypes = $cur[$t]['types'];
        $prev = null;
        foreach ($want['columns'] as $col) {
            $name = $col['name'];
            if (!isset($have['columns'][$name])) {
                $phase['add_column'][] = ['type' => 'add_column', 'table' => $t, 'name' => $name, 'detail' => $col['type'] . ($col['null'] ? '' : ' NOT NULL'),
                    'sql' => 'ALTER TABLE ' . _sq($t) . ' ADD COLUMN ' . _sq($name) . ' ' . $col['def'] . ($prev !== null ? ' AFTER ' . _sq($prev) : ' FIRST')];
            } elseif (isset($haveTypes[$name])) {
                $cmp = schema_compare_column($col, $haveTypes[$name]);
                if ($cmp === 'widen') {
                    $phase['widen_column'][] = ['type' => 'widen_column', 'table' => $t, 'name' => $name,
                        'detail' => schema_norm_type($haveTypes[$name]['type']) === schema_norm_type($col['type'])
                            ? $col['type'] . ': NOT NULL → allows NULL' : $haveTypes[$name]['type'] . ' → ' . $col['type'],
                        'sql' => 'ALTER TABLE ' . _sq($t) . ' MODIFY COLUMN ' . _sq($name) . ' ' . $col['def']];
                } elseif ($cmp === 'drift') {
                    $drift[] = ['table' => $t, 'name' => $name, 'kind' => 'column',
                        'snapshot' => $col['type'] . ($col['null'] ? '' : ' NOT NULL'),
                        'current'  => $haveTypes[$name]['type'] . ($haveTypes[$name]['null'] ? '' : ' NOT NULL')];
                }
            }
            $prev = $name;
        }

        $haveSigs = [];
        foreach ($have['indexes'] as $d) $haveSigs[schema_index_sig($d)] = true;
        foreach ($want['indexes'] ?? [] as $in => $def) {
            $sig = schema_index_sig($def);
            if (isset($have['indexes'][$in])) {
                if (schema_index_sig($have['indexes'][$in]) !== $sig) {
                    $drift[] = ['table' => $t, 'name' => $in, 'kind' => 'index', 'snapshot' => $def, 'current' => $have['indexes'][$in]];
                }
                continue;
            }
            if (isset($haveSigs[$sig])) continue;          // same index already exists under another name
            $phase['add_index'][] = ['type' => 'add_index', 'table' => $t, 'name' => $in, 'detail' => $def,
                'sql' => 'ALTER TABLE ' . _sq($t) . ' ADD ' . $def];
        }

        $haveFk = [];
        foreach ($have['fks'] as $d) $haveFk[schema_fk_sig($d)] = true;
        foreach ($want['foreign_keys'] ?? [] as $fn => $def) {
            if (isset($have['fks'][$fn]) || isset($haveFk[schema_fk_sig($def)])) continue;
            $phase['add_fk'][] = ['type' => 'add_fk', 'table' => $t, 'name' => $fn, 'detail' => $def,
                'sql' => 'ALTER TABLE ' . _sq($t) . ' ADD ' . $def];
        }
    }

    $extras = [];
    foreach (array_keys($cur) as $t) if (!isset($snap['tables'][$t])) $extras[] = $t;

    return ['actions' => array_merge(...array_values($phase)), 'drift' => $drift, 'extras' => $extras];
}

/** Runs each action on its own; one failure never stops the others. */
function schema_apply(mysqli $db, array $plan): array {
    $done = []; $failed = [];
    foreach ($plan['actions'] as $a) {
        try {
            $db->query($a['sql']);
            $done[] = $a;
        } catch (Throwable $e) {
            $failed[] = $a + ['error' => $e->getMessage()];
            error_log("BodaERP schema sync: {$a['type']} {$a['table']}.{$a['name']} failed: " . $e->getMessage());
        }
    }
    return ['done' => $done, 'failed' => $failed];
}

// ------------------------------------------------------------
// State (stored in system_settings) and the run itself
// ------------------------------------------------------------

function schema_state_load(?mysqli $db = null): ?array {
    try {
        $db = $db ?? db();
        $row = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = '" . SCHEMA_STATE_KEY . "'")->fetch_row();
        $data = $row ? json_decode((string) $row[0], true) : null;
        return is_array($data) ? $data : null;
    } catch (Throwable $e) {
        return null;       // system_settings does not exist yet
    }
}

function schema_state_save(mysqli $db, array $state): void {
    try {
        $key = SCHEMA_STATE_KEY; $json = json_encode($state, JSON_UNESCAPED_UNICODE);
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param('ss', $key, $json);
        $stmt->execute(); $stmt->close();
    } catch (Throwable $e) {
        error_log('BodaERP schema sync: could not save state: ' . $e->getMessage());
    }
}

function schema_sync_needed(mysqli $db): bool {
    $md5 = schema_snapshot_md5();
    if ($md5 === null) return false;
    try { $db->query('SELECT 1 FROM system_settings LIMIT 1')->free(); }
    catch (Throwable $e) { return false; }           // created by a migration; the sync waits for it
    $st = schema_state_load($db);
    if (!$st || ($st['md5'] ?? '') !== $md5) return true;
    return !empty($st['failed']) && time() - (int) strtotime($st['at'] ?? '2000-01-01') > SCHEMA_FAILED_RETRY;
}

/** Applies the snapshot to this database (under a lock) and records the outcome. */
function schema_sync_run(string $by = 'auto'): array {
    $res = ['applied' => [], 'failed' => [], 'drift' => [], 'skipped' => false, 'error' => null, 'no_snapshot' => false];
    $snap = schema_snapshot_load();
    if (!$snap) { $res['no_snapshot'] = true; return $res; }

    $db = db();
    $lock = $db->query("SELECT GET_LOCK('bodaerp_schema_sync', 0)");
    $got = (int) $lock->fetch_row()[0]; $lock->free();
    if (!$got) { $res['skipped'] = true; return $res; }

    try {
        $plan = schema_plan($db, $snap);
        $r = schema_apply($db, $plan);
        $res['applied'] = $r['done']; $res['failed'] = $r['failed']; $res['drift'] = $plan['drift'];
        schema_state_save($db, [
            'md5' => schema_snapshot_md5(), 'at' => date('Y-m-d H:i:s'), 'by' => substr($by, 0, 40),
            'applied' => count($r['done']), 'drift' => count($plan['drift']),
            'failed' => array_map(fn($f) => ['table' => $f['table'], 'name' => $f['name'], 'type' => $f['type'],
                                              'error' => mb_substr($f['error'], 0, 300)], array_slice($r['failed'], 0, 20)),
        ]);
    } catch (Throwable $e) {
        $res['error'] = $e->getMessage();
        error_log('BodaERP schema sync: ' . $e->getMessage());
    } finally {
        $db->query("SELECT RELEASE_LOCK('bodaerp_schema_sync')")->free();
    }
    return $res;
}

function schema_fail_file(): string {
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bodaerp_schema_fail_' . md5(DB_HOST . DB_NAME) . '.json';
}

/** Same as schema_sync_run(), plus the back-off marker the automatic hook uses. */
function schema_sync_run_tracked(string $by): array {
    $r = schema_sync_run($by);
    if ($r['failed'] || ($r['error'] && !$r['skipped'])) {
        @file_put_contents(schema_fail_file(), json_encode(['at' => date('Y-m-d H:i:s'), 'md5' => schema_snapshot_md5()]));
    } elseif (!$r['skipped']) {
        @unlink(schema_fail_file());
    }
    return $r;
}

/** True while the automatic hook should leave a recently failed snapshot alone (a changed snapshot is always tried at once). */
function schema_backoff_active(): bool {
    $f = schema_fail_file();
    if (!is_file($f) || time() - (int) @filemtime($f) >= SCHEMA_RETRY_SECONDS) return false;
    $d = json_decode((string) @file_get_contents($f), true);
    return is_array($d) && ($d['md5'] ?? null) === schema_snapshot_md5();
}

/** Problems from the most recent sync run on this database, or null. */
function schema_last_failure(): ?array {
    $st = schema_state_load();
    return ($st && !empty($st['failed'])) ? $st : null;
}

// ------------------------------------------------------------
// Local auto-save of the snapshot
// ------------------------------------------------------------

/** Auto-save is only ever active on a developer machine, from the machine itself. */
function schema_capture_possible(): bool {
    return PHP_SAPI !== 'cli' && db_is_local()
        && in_array(DB_HOST, ['127.0.0.1', 'localhost', '::1'], true)
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        && is_writable(dirname(schema_snapshot_path()));
}

function schema_autocapture_enabled(): bool {
    $v = strtolower((string) getenv('SCHEMA_AUTO_CAPTURE'));
    return !in_array($v, ['0', 'off', 'false', 'no'], true) && schema_capture_possible();
}

/**
 * Writes the snapshot from this database. Content-only comparison, so the file
 * (and git) only change when the structure really changed.
 * @return array{changed:bool,tables:int}
 */
function schema_capture(string $by = 'manual', bool $force = false): array {
    $db = db();
    $snap = schema_snapshot_build($db);
    $new = schema_snapshot_json($snap, null);
    $cur = schema_snapshot_load();
    if (!$force && $cur !== null && schema_snapshot_json($cur, null) === $new) {
        return ['changed' => false, 'tables' => count($snap['tables'])];
    }
    $path = schema_snapshot_path();
    $json = schema_snapshot_json($snap, [
        'saved_at' => date('Y-m-d H:i:s'), 'saved_by' => substr($by, 0, 40),
        'database_server' => $db->server_info, 'tables' => count($snap['tables']),
    ]);
    $tmp = $path . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Could not write ' . $path . ' (is the folder writable?)');
    }
    // This database is the source of the snapshot, so it is already "applied" here.
    schema_state_save($db, ['md5' => md5_file($path), 'at' => date('Y-m-d H:i:s'), 'by' => 'capture:' . substr($by, 0, 30),
                            'applied' => 0, 'drift' => 0, 'failed' => []]);
    return ['changed' => true, 'tables' => count($snap['tables'])];
}

/** Throttled hook for local development: keeps the snapshot file in step with the local database. */
function schema_autocapture_maybe(): void {
    if (!schema_autocapture_enabled()) return;
    $st = schema_state_load();
    if ($st && !empty($st['failed'])) return;       // don't overwrite the snapshot while part of it could not be applied here
    $marker = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bodaerp_schema_capture_' . md5(DB_NAME);
    if (is_file($marker) && time() - (int) @filemtime($marker) < SCHEMA_CAPTURE_EVERY) return;
    @touch($marker);
    try { schema_capture('auto'); }
    catch (Throwable $e) { error_log('BodaERP schema auto-save: ' . $e->getMessage()); }
}
