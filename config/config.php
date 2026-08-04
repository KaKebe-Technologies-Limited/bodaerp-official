<?php
// ============================================================
// BodaERP — App-wide configuration
// ============================================================

date_default_timezone_set('Africa/Kampala');

$isLocalEnv = php_sapi_name() === 'cli'
    || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);

// Local XAMPP serves the app from /bodariders; the live server serves it from /boda.
define('BASE_URL', $isLocalEnv ? '/bodariders' : '/boda');
define('SESSION_NAME', 'bodaerp_session');
define('SESSION_TIMEOUT', 1800); // 30 minutes idle timeout
define('UPLOAD_DIR', __DIR__ . '/../uploads/riders/');
define('UPLOAD_URL', BASE_URL . '/uploads/riders/');
