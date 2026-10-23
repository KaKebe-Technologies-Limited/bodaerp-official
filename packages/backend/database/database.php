<?php
// ============================================================
// BodaERP — Database connection (mysqli)
// Uses DATABASE_URL env var when available (Docker/Heroku/Platform)
// Falls back to individual DB_* env vars for traditional setups.
// All backend code (migrator, automation, anomaly engine, datapacks)
// uses the mysqli API directly — this must return mysqli, not PDO.
// ============================================================

require_once __DIR__ . '/../env_loader.php';
loadEnvFile(__DIR__ . '/../../../.env');

function _db_parse_url(string $url): array {
    $p = parse_url($url);
    return [
        'host' => $p['host'] ?? '127.0.0.1',
        'port' => (int) ($p['port'] ?? 3306),
        'user' => urldecode($p['user'] ?? 'root'),
        'pass' => urldecode($p['pass'] ?? ''),
        'name' => ltrim($p['path'] ?? '', '/'),
    ];
}

$_dbUrl  = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: '';
$_dbHost = $_ENV['DB_HOST']      ?? getenv('DB_HOST')      ?: '127.0.0.1';
$_dbName = $_ENV['DB_NAME']      ?? getenv('DB_NAME')      ?: 'bodaerp';
$_dbUser = $_ENV['DB_USER']      ?? getenv('DB_USER')      ?: 'root';
$_dbPass = $_ENV['DB_PASS']      ?? getenv('DB_PASS')      ?: '';
$_dbPort = (int) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);

if ($_dbUrl !== '') {
    $c = _db_parse_url($_dbUrl);
    define('DB_HOST', $c['host']);
    define('DB_PORT', $c['port']);
    define('DB_USER', $c['user']);
    define('DB_PASS', $c['pass']);
    define('DB_NAME', $c['name']);
    define('DB_ENV',  'url');
} elseif ($_dbHost !== '') {
    define('DB_HOST', $_dbHost);
    define('DB_PORT', $_dbPort);
    define('DB_USER', $_dbUser);
    define('DB_PASS', $_dbPass);
    define('DB_NAME', $_dbName);
    define('DB_ENV',  'env-vars');
} else {
    die('BodaERP: set DATABASE_URL or DB_HOST/DB_NAME/DB_USER/DB_PASS environment variables.');
}

function db(): mysqli {
    static $conn = null;
    if ($conn !== null) return $conn;

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $conn->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('BodaERP DB connection error: ' . $e->getMessage());
        die('Database connection failed. Please try again later.');
    }
    return $conn;
}

function getDbEnvironment(): string { return DB_ENV; }
