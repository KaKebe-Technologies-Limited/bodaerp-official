<?php
// ============================================================
// BodaERP — create the first Super Admin (command line only)
//
//   php database/create_admin.php --name "Jane Doe" --email jane@example.com
//        creates a Super Admin with a random password, printed once
//   php database/create_admin.php --name "Jane Doe" --email jane@example.com --password "a long password"
//   php database/create_admin.php --email jane@example.com --reset
//        sets a new random password on an existing account (its role is not changed)
//   php database/create_admin.php --hash "a long password"
//        prints only a password hash — for hosting without a command line: insert it
//        into users.password_hash with phpMyAdmin (see docs/DEPLOYMENT.md)
//
// Run it where the .env of the target database is (on the live server: over SSH).
// ============================================================
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Run from the command line: php database/create_admin.php'); }

$o = getopt('', ['name:', 'email:', 'password:', 'reset', 'hash:']);

if (isset($o['hash'])) {
    if (strlen($o['hash']) < 10) { fwrite(STDERR, "Use a password of at least 10 characters.\n"); exit(1); }
    echo password_hash($o['hash'], PASSWORD_DEFAULT), "\n";
    exit(0);
}

require_once __DIR__ . '/../packages/backend/db_helpers.php';

$email = trim((string) ($o['email'] ?? ''));
$name  = trim((string) ($o['name'] ?? ''));
$reset = isset($o['reset']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || (!$reset && $name === '')) {
    fwrite(STDERR, "Usage: php database/create_admin.php --name \"Full Name\" --email you@example.com [--password \"...\"]\n"
                 . "       php database/create_admin.php --email you@example.com --reset\n");
    exit(1);
}

$generated = false;
$password = (string) ($o['password'] ?? '');
if ($password === '') {
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';   // no look-alike characters
    for ($i = 0; $i < 16; $i++) $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $generated = true;
} elseif (strlen($password) < 10) {
    fwrite(STDERR, "Use a password of at least 10 characters.\n");
    exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);

$existing = fetchOne("SELECT id, role FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);
if ($existing && !$reset) {
    fwrite(STDERR, "An account with that email already exists (role: {$existing['role']}). Add --reset to give it a new password.\n");
    exit(1);
}
if ($reset) {
    if (!$existing) { fwrite(STDERR, "No account with that email.\n"); exit(1); }
    runQuery("UPDATE users SET password_hash = ?, status = 'active' WHERE id = ?", [$hash, $existing['id']])->close();
    echo "Password reset for $email ({$existing['role']}).\n";
} else {
    runQuery("INSERT INTO users (name, email, phone, password_hash, role, city_id, stage_id, status)
              VALUES (?, ?, NULL, ?, 'super_admin', NULL, NULL, 'active')", [$name, $email, $hash])->close();
    echo "Super Admin created: $name <$email>\n";
}
if ($generated) echo "Password (shown once, change it after signing in): $password\n";
