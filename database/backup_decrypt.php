<?php
// Turns an encrypted automatic backup back into a .sql.gz you can restore.
//   php database/backup_decrypt.php storage/backups/bodaerp_YYYYMMDD_HHMMSS_xxxx.sql.gz.enc [output.sql.gz]
// Reads BACKUP_KEY from the environment or .env. Without that key the backup cannot be recovered by anyone.
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../packages/backend/backup.php';

$from = $argv[1] ?? '';
if ($from === '' || !is_file($from)) { fwrite(STDERR, "Usage: php database/backup_decrypt.php <backup.sql.gz.enc> [output.sql.gz]\n"); exit(2); }
$to = $argv[2] ?? preg_replace('/\.enc$/', '', basename($from));
if (file_exists($to)) { fwrite(STDERR, "$to already exists; choose another output name.\n"); exit(2); }

try {
    $key = backup_key();
    if ($key === null) throw new RuntimeException('BACKUP_KEY is not set.');
    backup_decrypt_file($from, $to, $key);
} catch (Throwable $e) {
    @unlink($to);
    fwrite(STDERR, 'Could not decrypt: ' . $e->getMessage() . "\n");
    exit(1);
}
if (!backup_is_complete($to)) { fwrite(STDERR, "Decrypted, but the backup inside is incomplete: $to\n"); exit(1); }
echo "Wrote $to. Unpack it with gunzip and import the .sql in phpMyAdmin.\n";
