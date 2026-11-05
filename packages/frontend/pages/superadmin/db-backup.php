<?php
// Streams a full database backup (.sql) to the Super Admin. POST + CSRF only.
require_once __DIR__ . '/../../../backend/auth.php';
require_role(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/pages/superadmin/system.php?tab=backup'); }
csrf_verify();

@set_time_limit(0);

$name = 'bodaerp_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: no-store');
audit_log('CREATE', 'backup', null, 'Database backup downloaded');
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // don't hold the session lock while streaming

backup_write_sql(function (string $sql) { echo $sql; flush(); });
