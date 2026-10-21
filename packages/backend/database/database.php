<?php
// ============================================================
// BodaERP — Database connection (PDO PostgreSQL)
// Auto-detects local vs live server environment
// Uses DATABASE_URL env var when available (Docker/Heroku/Platform)
// Falls back to individual env vars for traditional setups
// ============================================================

// ----- PREFER: DATABASE_URL (12-factor app pattern) -----
$dsnFromUrl = $_ENV['DATABASE_URL'] ?? null;

// ----- FALLBACK: Individual environment variables -----
$dbHost = $_ENV['DB_HOST'] ?? '';
$dbName = $_ENV['DB_NAME'] ?? '';
$dbUser = $_ENV['DB_USER'] ?? '';
$dbPass = $_ENV['DB_PASS'] ?? '';

// ----- Determine DSN and environment -----
$useUrl = !empty($dsnFromUrl) && filter_var($dsnFromUrl, FILTER_VALIDATE_URL);
$hasAllVars = !empty($dbHost) && !empty($dbName) && !empty($dbUser);

if ($useUrl) {
    // Use DATABASE_URL (supports: postgresql://user:pass@host:port/dbname)
    define('DB_DSN', $dsnFromUrl);
    define('DB_ENV', 'docker-or-url');
    // Parse host from URL for debugging
    $parsed = parse_url($dsnFromUrl);
    define('DB_HOST', $parsed['host'] ?? 'unknown');
    define('DB_PORT', $parsed['port'] ?? '5432');
    define('DB_USER', $parsed['user'] ?? 'postgres');
    define('DB_PASS', $parsed['pass'] ?? '');
} elseif ($hasAllVars) {
    // Use individual env vars
    define('DB_DSN', "pgsql:host={$dbHost} port=5432 dbname={$dbName} user={$dbUser} password={$dbPass}");
    define('DB_ENV', 'env-vars');
    define('DB_HOST', $dbHost);
    define('DB_PORT', '5432');
    define('DB_USER', $dbUser);
    define('DB_PASS', $dbPass);
} else {
    // No configuration available
    die('BodaERP Database not configured. Set DATABASE_URL or DB_HOST/DB_NAME/DB_USER/DB_PASS environment variables.');
}

// ----- PDO CONNECTION FUNCTION -----
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(DB_DSN, DB_USER ?? 'postgres', DB_PASS ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->setAttribute(PDO::ATTR_PERSISTENT, false);
        } catch (PDOException $e) {
            error_log('BodaERP DB Connection Error: ' . $e->getMessage());
            die('Database connection failed. Please try again later or contact support.');
        }
    }

    return $pdo;
}

// ----- HELPER: Get current environment (for debugging) -----
function getDbEnvironment(): string {
    return DB_ENV;
}

// ----- HELPER: Check if connected to local environment -----
function isLocalEnvironment(): bool {
    return DB_ENV === 'docker-or-url' || DB_ENV === 'env-vars';
}

// ----- HELPER: Test database connection -----
function testDbConnection(): array {
    try {
        $pdo = db();
        $result = $pdo->query("SELECT 1 AS test_val");
        $row = $result->fetch();
        return [
            'success' => true,
            'message' => 'Connected successfully to ' . DB_NAME,
            'environment' => DB_ENV,
            'host' => DB_HOST,
            'test_value' => $row['test_val'] ?? 'unknown'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Connection failed: ' . $e->getMessage(),
            'environment' => DB_ENV
        ];
    }
}