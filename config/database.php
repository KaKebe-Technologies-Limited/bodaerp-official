<?php
// ============================================================
// BodaERP — Database connection (mysqli)
// Auto-detects local vs live server environment
// ============================================================

// ----- DETECT ENVIRONMENT -----
$isLocal = (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
    strpos($_SERVER['SERVER_NAME'], '192.168.') === 0 ||
    strpos($_SERVER['SERVER_NAME'], '10.') === 0
);

// ----- DATABASE CONSTANTS -----
if ($isLocal) {
    // ===== LOCAL ENVIRONMENT (XAMPP / WAMP / MAMP) =====
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'bodaerp');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
    define('DB_ENV', 'local');
} else {
    // ===== LIVE SERVER ENVIRONMENT (Hostinger) =====
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'u850523537_VVbodaERP');
    define('DB_USER', 'u850523537_VVBodaUser');
    define('DB_PASS', 'bodaerp=1A');
    define('DB_CHARSET', 'utf8mb4');
    define('DB_ENV', 'live');
}

// ============================================================
// DATABASE CONNECTION FUNCTION
// ============================================================
function db(): mysqli {
    static $link = null;
    
    if ($link === null) {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $link = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
            $link->set_charset(DB_CHARSET);
        } catch (mysqli_sql_exception $e) {
            error_log('BodaERP DB Connection Error: ' . $e->getMessage());
            die('Database connection failed. Please try again later or contact support.');
        }
    }
    
    return $link;
}

// ============================================================
// HELPER: Get current environment (for debugging)
// ============================================================
function getDbEnvironment(): string {
    return DB_ENV;
}

// ============================================================
// HELPER: Check if connected to local environment
// ============================================================
function isLocalEnvironment(): bool {
    return DB_ENV === 'local';
}

// ============================================================
// HELPER: Test database connection
// ============================================================
function testDbConnection(): array {
    try {
        $db = db();
        $result = $db->query("SELECT 1");
        return [
            'success' => true,
            'message' => 'Connected successfully to ' . DB_NAME,
            'environment' => DB_ENV,
            'host' => DB_HOST
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Connection failed: ' . $e->getMessage(),
            'environment' => DB_ENV
        ];
    }
}