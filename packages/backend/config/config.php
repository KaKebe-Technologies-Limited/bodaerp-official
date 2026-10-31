<?php
// ============================================================
// BodaERP — App-wide configuration
// ============================================================

date_default_timezone_set('Africa/Kampala');

// BASE_URL is the URL path this app is served from: '' at a domain root (the live site),
// '/bodaerp-official' when the folder sits inside XAMPP's htdocs. It is worked out from where
// this folder is under the web server's document root, so the folder can have any name.
// Override with APP_BASE_URL in .env if a proxy or alias makes the automatic value wrong
// (use APP_BASE_URL=/ for "domain root").
require_once __DIR__ . '/../env_loader.php';
loadEnvFile(__DIR__ . '/../../../.env');

function app_base_url(): string {
    $override = getenv('APP_BASE_URL');
    if ($override !== false && trim($override) !== '') return rtrim(trim($override), '/');

    $root = str_replace(chr(92), '/', (string) realpath(__DIR__ . '/../../..'));
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($docRoot !== false) {
        $doc = rtrim(str_replace(chr(92), '/', $docRoot), '/');
        if ($doc === '') return '';
        if (strncasecmp($root, $doc, strlen($doc)) === 0) return substr($root, strlen($doc));
    }
    // Command line (cron, seeding): no document root, so go by the usual web folder names.
    if (preg_match('#/(?:htdocs|www|public_html|html)((?:/[^/]+)*)$#i', $root, $m)) return $m[1];
    return '';
}
define('BASE_URL', app_base_url());
define('SESSION_NAME', 'bodaerp_session');
define('SESSION_TIMEOUT', 1800); // 30 minutes idle timeout
define('UPLOAD_DIR', __DIR__ . '/../../../uploads/riders/');
define('UPLOAD_URL', BASE_URL . '/uploads/riders/');
