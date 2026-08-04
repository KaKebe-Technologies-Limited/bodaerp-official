<?php
// ============================================================
// BodaERP — App-wide configuration
// ============================================================

date_default_timezone_set('Africa/Kampala');

define('BASE_URL', '/bodariders');
define('SESSION_NAME', 'bodaerp_session');
define('SESSION_TIMEOUT', 1800); // 30 minutes idle timeout
define('UPLOAD_DIR', __DIR__ . '/../uploads/riders/');
define('UPLOAD_URL', BASE_URL . '/uploads/riders/');
