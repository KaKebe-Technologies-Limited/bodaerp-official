<?php
// BodaERP - application bootstrap (loaded by config/config.php) Environment helper, storage/upload paths,
// error handling and HTTP security headers. Works the same on XAMPP, plain Apache hosting and the optional
// Docker dev stack: every machine-specific value comes from real environment variables first, then .env,
// then a default that suits a stock XAMPP install.

require_once __DIR__ . '/database/database.php';   // db_is_local() decides debug output

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string {
        $v = getenv($key);
        if ($v === false) $v = $_ENV[$key] ?? null;
        if ($v === null || trim((string) $v) === '') return $default;
        return trim((string) $v);
    }
}

function app_root(): string { return dirname(__DIR__, 2); }

/** Writable folder for logs, the mail log and rate-limit data. Outside git, blocked by .htaccess. */
function storage_path(string $sub = ''): string {
    $base = rtrim(str_replace('\\', '/', env('STORAGE_PATH', app_root() . '/storage')), '/');
    return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
}

/** Folder that holds user uploads (rider photos, media library). Served only through serve_upload.php. */
function upload_path(string $sub = ''): string {
    $base = rtrim(str_replace('\\', '/', env('UPLOAD_PATH', app_root() . '/uploads')), '/');
    return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
}

function ensure_dir(string $dir): bool {
    if (is_dir($dir)) return is_writable($dir);
    return @mkdir($dir, 0775, true) || is_dir($dir);
}

/** Absolute public URL of this site, for links in emails, QR codes and the sitemap. */
function app_url(string $path = ''): string {
    $configured = env('APP_URL');
    if ($configured !== null) {
        $root = rtrim($configured, '/');
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        // Fallback only. With Apache's default UseCanonicalName Off, SERVER_NAME is the client's Host
        // header, so production must set APP_URL or reset links can point at a forged host.
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
        if (!empty($_SERVER['HTTP_HOST']) && preg_match('/^' . preg_quote($host, '/') . '(:\d+)?$/i', $_SERVER['HTTP_HOST'])) {
            $hostPart = $_SERVER['HTTP_HOST'];
        } else {
            $hostPart = $host . (in_array($port, [80, 443], true) ? '' : ':' . $port);
        }
        $root = ($https ? 'https' : 'http') . '://' . $hostPart . BASE_URL;
    }
    return $root . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function app_is_debug(): bool {
    $flag = env('APP_DEBUG');
    if ($flag !== null) return in_array(strtolower($flag), ['1', 'true', 'on', 'yes'], true);
    return function_exists('db_is_local') ? db_is_local() : PHP_SAPI === 'cli';
}

/**
 * Per-request CSP nonce. Inline <script> blocks must carry nonce="<?= csp_nonce() ?>"; anything injected
 * into a page without it (stored or reflected XSS) is refused by the browser.
 */
function csp_nonce(): string {
    static $nonce = null;
    return $nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
}

function app_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function app_log(string $channel, string $message, array $context = []): void {
    $dir = storage_path('logs');
    $line = '[' . date('Y-m-d H:i:s') . "] $channel: $message" . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '') . PHP_EOL;
    if (ensure_dir($dir) && @file_put_contents($dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $channel) . '.log', $line, FILE_APPEND | LOCK_EX) !== false) return;
    error_log($line);
}

/** Renders one of the public error pages (text editable in Super Admin → Content) and stops. */
function app_error_page(int $status): never {
    if (!headers_sent()) http_response_code($status === 419 ? 400 : $status);
    $file = app_root() . '/packages/frontend/pages/errors/error.php';
    if (PHP_SAPI !== 'cli' && is_file($file)) {
        $errorStatus = $status;
        try { require $file; exit; } catch (Throwable $e) { error_log('BodaERP error page failed: ' . $e->getMessage()); }
    }
    echo $status === 404 ? 'Page not found.' : 'Something went wrong. Please try again in a moment.';
    exit;
}

(function () {
    $debug = app_is_debug();
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');
    if (ensure_dir(storage_path('logs'))) ini_set('error_log', storage_path('logs/php-errors.log'));
    error_reporting(E_ALL);

    set_exception_handler(function (Throwable $e) use ($debug) {
        app_log('exceptions', get_class($e) . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        if (PHP_SAPI === 'cli') { fwrite(STDERR, (string) $e . PHP_EOL); exit(1); }
        while (ob_get_level() > 0) ob_end_clean();
        if ($debug) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<pre style="padding:20px;white-space:pre-wrap">' . htmlspecialchars((string) $e) . '</pre>';
            exit;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
            exit;
        }
        app_error_page(500);
    });

    if (PHP_SAPI === 'cli' || headers_sent()) return;
    ob_start();   // lets scheduled jobs run after the page has been delivered (automation_finish_response)

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(self), payment=()');   // geolocation: optional location on a roadside permit check
    header('Cross-Origin-Opener-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; "
        . "style-src 'self' 'unsafe-inline'; font-src 'self' data:; "
        . "img-src 'self' data: blob: https:; connect-src 'self'; media-src 'self' https:; "
        . "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://www.google.com; "
        . "frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");
    if (app_is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header_remove('X-Powered-By');
})();
