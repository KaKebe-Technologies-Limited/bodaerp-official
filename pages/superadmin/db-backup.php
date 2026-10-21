<?php
// Streams a full database backup (.sql) to the Super Admin. POST + CSRF only.
require_once __DIR__ . '/../../includes/auth.php';
require_role(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/pages/superadmin/system.php?tab=backup'); }
csrf_verify();

@set_time_limit(0);
$db = db();
$skipData = ['login_sessions'];   // structure only — live sign-in sessions don't belong in a backup

$name = 'bodaerp_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: no-store');
audit_log('CREATE', 'backup', null, 'Database backup downloaded');
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // don't hold the session lock while streaming

echo "-- BodaERP database backup\n-- Database: " . DB_NAME . "\n-- Created: " . date('Y-m-d H:i:s') . "\n-- Restore: phpMyAdmin → select the database → Import\n\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

$tables = []; $views = [];
$res = $db->query("SHOW FULL TABLES");
while ($row = $res->fetch_row()) { if ($row[1] === 'VIEW') $views[] = $row[0]; else $tables[] = $row[0]; }
$res->free();

foreach ($tables as $t) {
    $create = $db->query("SHOW CREATE TABLE `{$t}`")->fetch_row()[1];
    echo "-- ----------------------------\n-- Table `{$t}`\n-- ----------------------------\nDROP TABLE IF EXISTS `{$t}`;\n{$create};\n\n";
    if (in_array($t, $skipData, true)) continue;

    $cols = []; $cr = $db->query("SHOW COLUMNS FROM `{$t}`");
    while ($c = $cr->fetch_assoc()) $cols[] = '`' . $c['Field'] . '`';
    $cr->free();
    $colList = implode(',', $cols);

    $rows = $db->query("SELECT * FROM `{$t}`", MYSQLI_USE_RESULT);   // unbuffered: constant memory
    $batch = []; $n = 0;
    while ($r = $rows->fetch_row()) {
        $vals = [];
        foreach ($r as $v) $vals[] = $v === null ? 'NULL' : "'" . $db->real_escape_string((string) $v) . "'";
        $batch[] = '(' . implode(',', $vals) . ')';
        if (++$n % 200 === 0) { echo "INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n"; $batch = []; flush(); }
    }
    $rows->free();
    if ($batch) echo "INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
    echo "\n";
}

foreach ($views as $v) {
    $create = $db->query("SHOW CREATE VIEW `{$v}`")->fetch_row()[1];
    $create = preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/', '', $create);   // a definer from this server would break the restore elsewhere
    echo "DROP VIEW IF EXISTS `{$v}`;\n{$create};\n\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n-- End of backup\n";
