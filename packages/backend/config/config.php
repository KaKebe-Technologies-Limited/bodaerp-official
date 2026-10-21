<?php
// ============================================================
// BodaERP — App-wide configuration
// ============================================================

date_default_timezone_set('Africa/Kampala');

// Live (bodaerp.site) runs at the domain root, so BASE_URL is empty there.
// Local XAMPP serves this folder at /bodaerp-official, so it needs that prefix.
$isLocalEnv = php_sapi_name() === 'cli'
    || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
define('BASE_URL', $isLocalEnv ? '/bodaerp-official' : '');
define('SESSION_NAME', 'bodaerp_session');
define('SESSION_TIMEOUT', 1800); // 30 minutes idle timeout
define('UPLOAD_DIR', __DIR__ . '/../uploads/riders/');
define('UPLOAD_URL', BASE_URL . '/uploads/riders/');
