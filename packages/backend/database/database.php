<?php
// ============================================================
// BodaERP — Database connection (mysqli)
//
// Zero setup on your own computer (XAMPP / WAMP / MAMP): connects to MySQL on
// 127.0.0.1 as root and creates the database if it does not exist yet — the
// tables then build themselves (see migrator.php and schema_sync.php).
//
// On a live server put the connection in ONE place, the .env file next to
// login.php (copy .env.example):
//     DB_HOST=localhost
//     DB_NAME=your_database
//     DB_USER=your_user
//     DB_PASS=your_password
//
// The old config/database.php (one file holding a local and a live block) is
// still understood — it is only READ, never executed — so an existing server
// keeps working until you move those four values into .env.
//
// All backend code uses the mysqli API directly: db() must return mysqli.
// ============================================================

require_once __DIR__ . '/../env_loader.php';
loadEnvFile(__DIR__ . '/../../../.env');

/** True on a developer machine: CLI, localhost, *.test, private LAN addresses (APP_ENV=live|local overrides). */
function db_is_local(): bool {
    static $local = null;
    if ($local !== null) return $local;

    $forced = strtolower(trim((string) getenv('APP_ENV')));
    if (in_array($forced, ['live', 'production', 'prod'], true))  return $local = false;
    if (in_array($forced, ['local', 'dev', 'development'], true)) return $local = true;
    if (PHP_SAPI === 'cli') return $local = true;

    $host = strtolower((string) preg_replace('/:\d+$/', '', $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? ''));
    return $local = (
        $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $host === '[::1]'
        || str_contains($host, 'localhost') || str_ends_with($host, '.test')
        || str_starts_with($host, '192.168.') || str_starts_with($host, '10.')
        || (bool) preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $host)
    );
}

/**
 * True only on a developer's own machine: local environment AND the request really comes from the
 * machine itself (a forged "Host: localhost" header from the internet does not count).
 * Gates anything that must never be reachable on a real server (demo passwords, on-screen reset links).
 */
function db_is_dev_machine(): bool {
    return db_is_local()
        && (PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));
}

function _db_env(string $key): string {
    $v = getenv($key);
    if ($v === false) $v = $_ENV[$key] ?? '';
    return trim((string) $v);
}

/** Reads the DB_* values out of the legacy config/database.php without executing it. */
function _db_legacy_config(bool $local): ?array {
    $file = __DIR__ . '/../../../config/database.php';
    if (!is_file($file) || !is_readable($file)) return null;
    $src = (string) @file_get_contents($file);
    $cut = stripos($src, 'LIVE SERVER');                       // marker the old file used between its two blocks
    $block = $local ? substr($src, 0, $cut === false ? strlen($src) : $cut) : ($cut === false ? '' : substr($src, $cut));
    if (!preg_match_all("/define\(\s*'DB_(HOST|PORT|NAME|USER|PASS)'\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)/", $block, $m, PREG_SET_ORDER)) return null;
    $v = [];
    foreach ($m as $row) $v[strtolower($row[1])] = strtr($row[2], ['\\\\' => '\\', "\\'" => "'"]);   // PHP single-quote rules only
    if (empty($v['name'])) return null;
    return $v + ['host' => 'localhost', 'port' => '3306', 'user' => '', 'pass' => ''];
}

/** @return array{host:string,port:int,user:string,pass:string,name:string,source:string} */
function _db_resolve(): array {
    $local = db_is_local();

    if (_db_env('DB_HOST') !== '' || _db_env('DB_NAME') !== '' || _db_env('DB_USER') !== '' || _db_env('DB_PASS') !== '') {
        return [
            'host' => _db_env('DB_HOST') ?: ($local ? '127.0.0.1' : 'localhost'),
            'port' => (int) (_db_env('DB_PORT') ?: 3306),
            'user' => _db_env('DB_USER') ?: ($local ? 'root' : ''),
            'pass' => _db_env('DB_PASS'),
            'name' => _db_env('DB_NAME') ?: ($local ? 'bodaerp' : ''),
            'source' => '.env settings',
        ];
    }

    if ($local) {
        return ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => '', 'name' => 'bodaerp', 'source' => 'built-in local defaults'];
    }

    if ($legacy = _db_legacy_config(false)) {
        return [
            'host' => $legacy['host'], 'port' => (int) $legacy['port'], 'user' => $legacy['user'],
            'pass' => $legacy['pass'], 'name' => $legacy['name'], 'source' => 'legacy config/database.php',
        ];
    }

    return ['host' => 'localhost', 'port' => 3306, 'user' => '', 'pass' => '', 'name' => '', 'source' => 'not configured'];
}

$_dbCfg = _db_resolve();
define('DB_HOST', $_dbCfg['host']);
define('DB_PORT', $_dbCfg['port']);
define('DB_USER', $_dbCfg['user']);
define('DB_PASS', $_dbCfg['pass']);
define('DB_NAME', $_dbCfg['name']);
define('DB_SOURCE', $_dbCfg['source']);
define('DB_ENV', db_is_local() ? 'local' : 'live');
unset($_dbCfg);

function _db_open(string $dbName): mysqli {
    $c = new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName, DB_PORT);
    $c->set_charset('utf8mb4');
    return $c;
}

/** Local only: the database is missing, so create it (the tables then build themselves). */
function _db_create_local(): mysqli {
    if (!preg_match('/^[A-Za-z0-9_$]+$/', DB_NAME)) throw new RuntimeException('Unsafe database name.');
    $c = _db_open('');
    $c->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $c->select_db(DB_NAME);
    return $c;
}

function db(): mysqli {
    static $conn = null;
    if ($conn !== null) return $conn;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = _db_open(DB_NAME);
    } catch (mysqli_sql_exception $e) {
        if ((int) $e->getCode() === 1049 && db_is_local()) {          // unknown database
            try { return $conn = _db_create_local(); }
            catch (Throwable $e2) { $e = $e2; }
        }
        _db_fail($e);
    }
    return $conn;
}

/** Plain-language error page (never shows credentials or stack traces). */
function _db_fail(Throwable $e): never {
    $code = (int) $e->getCode();
    error_log('BodaERP DB connection error [' . $code . ']: ' . $e->getMessage());
    $local = db_is_local();

    $reason = match (true) {
        $code === 1045                                  => 'The database server refused the username or password.',
        $code === 1049                                  => 'The database does not exist on the server yet.',
        in_array($code, [2002, 2003, 2006, 2013], true) => 'The database server could not be reached.',
        DB_NAME === ''                                  => 'No database has been configured for this site yet.',
        default                                         => 'The connection to the database failed.',
    };
    $fix = $local
        ? 'Start <strong>MySQL</strong> in the XAMPP control panel and reload this page. A missing database is created automatically.'
        : 'Create a file named <code>.env</code> next to <code>login.php</code> (copy <code>.env.example</code>) with '
          . '<code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> and <code>DB_PASS</code> from your hosting panel. '
          . 'Tables are created automatically on the first visit.';

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database connection failed: $reason (error $code, settings from " . DB_SOURCE . ")\n"
            . strip_tags($fix) . "\n");
        exit(1);
    }

    http_response_code(503);
    header('Retry-After: 30');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Database unavailable - BodaERP</title><style>'
       . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f3f1fb;font-family:system-ui,Segoe UI,Arial,sans-serif;color:#25214a}'
       . '.c{max-width:520px;margin:24px;background:#fff;border-radius:20px;padding:34px;box-shadow:0 20px 50px rgba(91,75,213,.15)}'
       . 'h1{font-size:1.25rem;margin:0 0 6px}p{line-height:1.55;color:#5b5878;margin:10px 0}code{background:#f3f1fb;padding:2px 6px;border-radius:6px;font-size:.85em}'
       . 'small{color:#8b88a6}.b{display:inline-block;background:#fdeaea;color:#c0392b;border-radius:99px;padding:3px 12px;font-size:.78rem;font-weight:600}'
       . '</style></head><body><div class="c"><span class="b">Database unavailable</span><h1>BodaERP can\'t reach its database</h1>'
       . '<p>' . htmlspecialchars($reason) . '</p><p>' . $fix . '</p>'
       . '<small>Error ' . $code . ' &middot; settings read from: ' . htmlspecialchars(DB_SOURCE) . '</small></div></body></html>';
    exit;
}

function getDbEnvironment(): string { return DB_ENV; }

/** Details for Super Admin → Database (no password). */
function db_connection_info(): array {
    $db = db();
    $t0 = microtime(true);
    $db->query('SELECT 1')->free();
    $ms = round((microtime(true) - $t0) * 1000, 1);
    $coll = $db->query('SELECT @@collation_database')->fetch_row()[0] ?? '';
    return [
        'environment' => DB_ENV, 'host' => DB_HOST, 'port' => DB_PORT, 'database' => DB_NAME, 'user' => DB_USER,
        'source' => DB_SOURCE, 'server' => $db->server_info, 'collation' => $coll, 'latency_ms' => $ms,
    ];
}
