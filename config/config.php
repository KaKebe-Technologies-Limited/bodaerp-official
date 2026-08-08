<?php
// ============================================================
// BodaERP — App-wide configuration
// ============================================================

date_default_timezone_set('Africa/Kampala');

// BASE_URL is always empty — the app runs at the domain/vhost root.
// Live:  https://bodaerp.site  → root, no prefix needed
// Local: set up an XAMPP vhost pointing at this folder,
//        or access via http://localhost/bodaerp-official
//        and temporarily set BASE_URL = '/bodaerp-official' below if needed.
define('BASE_URL', '');
define('SESSION_NAME', 'bodaerp_session');
define('SESSION_TIMEOUT', 1800); // 30 minutes idle timeout
define('UPLOAD_DIR', __DIR__ . '/../uploads/riders/');
define('UPLOAD_URL', BASE_URL . '/uploads/riders/');
