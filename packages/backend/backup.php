<?php
// Database backups. db-backup.php streams one to the Super Admin on request; the backup_database job
// keeps dated copies in storage/backups. A copy on the same server does not survive losing that server,
// so these complement the hosting panel's backups and an off-site copy rather than replace them.
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/db_helpers.php';

const BACKUP_END_MARKER = '-- End of backup';
const BACKUP_MAGIC = "BODABK1\n";
const BACKUP_CHUNK_BYTES = 1048576;

/** Writes the whole database as SQL through $write, seen as one consistent moment in time. */
function backup_write_sql(callable $write): void {
    $db = db();
    $skipData = ['login_sessions'];   // live sign-in sessions don't belong in a backup

    $write("-- BodaERP database backup\n-- Database: " . DB_NAME . "\n-- Created: " . date('Y-m-d H:i:s') . "\n-- Restore: phpMyAdmin → select the database → Import\n\n");
    $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

    $tables = []; $views = [];
    $res = $db->query("SHOW FULL TABLES");
    while ($row = $res->fetch_row()) { if ($row[1] === 'VIEW') $views[] = $row[0]; else $tables[] = $row[0]; }
    $res->free();

    // Without this, a payment confirmed mid-backup could appear in `payments` but not in `riders`.
    $db->query("START TRANSACTION WITH CONSISTENT SNAPSHOT");
    try {
        foreach ($tables as $table) {
            $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch_row()[1];
            $write("DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");
            if (in_array($table, $skipData, true)) continue;

            $columns = [];
            $columnResult = $db->query("SHOW COLUMNS FROM `{$table}`");
            while ($column = $columnResult->fetch_assoc()) $columns[] = '`' . $column['Field'] . '`';
            $columnResult->free();
            $columnList = implode(',', $columns);

            $rows = $db->query("SELECT * FROM `{$table}`", MYSQLI_USE_RESULT);   // unbuffered: constant memory
            $batch = []; $rowCount = 0;
            while ($row = $rows->fetch_row()) {
                $values = [];
                foreach ($row as $value) $values[] = $value === null ? 'NULL' : "'" . $db->real_escape_string((string) $value) . "'";
                $batch[] = '(' . implode(',', $values) . ')';
                if (++$rowCount % 200 === 0) { $write("INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $batch) . ";\n"); $batch = []; }
            }
            $rows->free();
            if ($batch) $write("INSERT INTO `{$table}` ({$columnList}) VALUES\n" . implode(",\n", $batch) . ";\n");
            $write("\n");
        }
    } finally {
        $db->query("COMMIT");
    }

    foreach ($views as $view) {
        $create = $db->query("SHOW CREATE VIEW `{$view}`")->fetch_row()[1];
        $create = preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/', '', $create);   // a definer from this server would break the restore elsewhere
        $write("DROP VIEW IF EXISTS `{$view}`;\n{$create};\n\n");
    }
    $write("SET FOREIGN_KEY_CHECKS = 1;\n" . BACKUP_END_MARKER . "\n");
}

/** The 32-byte key from BACKUP_KEY (64 hex characters), or null when backups are not encrypted. */
function backup_key(): ?string {
    $hex = trim((string) env('BACKUP_KEY', ''));
    if ($hex === '') return null;
    if (!preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
        // Falling back to plain text here would write rider data unencrypted while the owner believes otherwise.
        throw new RuntimeException('BACKUP_KEY must be 64 hexadecimal characters.');
    }
    return (string) hex2bin($hex);
}

function backup_dir(): string { return storage_path('backups'); }

/** A backup counts only if the compressed file reads to the end and carries the closing marker. */
function backup_is_complete(string $gzPath): bool {
    $gz = @gzopen($gzPath, 'rb');
    if (!$gz) return false;
    $tail = '';
    while (!gzeof($gz)) {
        $chunk = gzread($gz, 65536);
        if ($chunk === false) { gzclose($gz); return false; }
        $tail = substr($tail . $chunk, -64);
    }
    gzclose($gz);
    return str_contains($tail, BACKUP_END_MARKER);
}

/**
 * AES-256-GCM in numbered chunks: [4-byte length][12-byte IV][16-byte tag][ciphertext]. Each chunk's number and
 * last-chunk flag are authenticated, so a file cut short or reordered fails to decrypt instead of restoring partly.
 */
function backup_encrypt_file(string $from, string $to, string $key): void {
    $in = fopen($from, 'rb'); $out = fopen($to, 'wb');
    if (!$in || !$out) throw new RuntimeException('Could not open the backup files for encryption.');
    try {
        fwrite($out, BACKUP_MAGIC);
        $index = 0;
        $current = fread($in, BACKUP_CHUNK_BYTES);
        do {
            $next = fread($in, BACKUP_CHUNK_BYTES);
            $isLast = $next === '' || $next === false;
            $iv = random_bytes(12); $tag = '';
            $cipher = openssl_encrypt((string) $current, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, pack('NC', $index, $isLast ? 1 : 0));
            if ($cipher === false) throw new RuntimeException('Backup encryption failed.');
            fwrite($out, pack('N', strlen($cipher)) . $iv . $tag . $cipher);
            $current = $next; $index++;
        } while (!$isLast);
    } finally {
        fclose($in); fclose($out);
    }
}

function backup_decrypt_file(string $from, string $to, string $key): void {
    $total = filesize($from);
    $in = fopen($from, 'rb');
    if (!$in) throw new RuntimeException('Could not open the encrypted backup.');
    $out = null;
    try {
        if (fread($in, strlen(BACKUP_MAGIC)) !== BACKUP_MAGIC) throw new RuntimeException('This is not an encrypted BodaERP backup.');
        $out = fopen($to, 'wb');
        if (!$out) throw new RuntimeException('Could not create the output file.');
        $index = 0; $sawLast = false;
        while (ftell($in) < $total) {
            $header = fread($in, 32);
            if ($header === false || strlen($header) !== 32) throw new RuntimeException('The backup file is cut short.');
            $length = unpack('N', substr($header, 0, 4))[1];
            $cipher = $length > 0 ? fread($in, $length) : '';
            if ($cipher === false || strlen($cipher) !== $length) throw new RuntimeException('The backup file is cut short.');
            $sawLast = ftell($in) >= $total;
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($header, 4, 12), substr($header, 16, 16), pack('NC', $index, $sawLast ? 1 : 0));
            if ($plain === false) throw new RuntimeException('Wrong key, or the backup file has been changed or cut short.');
            fwrite($out, $plain);
            $index++;
        }
        if (!$sawLast) throw new RuntimeException('The backup file is empty.');
    } finally {
        fclose($in);
        if ($out) fclose($out);
    }
}

/** Writes one dated, verified backup and returns its details. Throws when it cannot be trusted. */
function backup_create(): array {
    $key = backup_key();
    $dir = backup_dir();
    if (!ensure_dir($dir)) throw new RuntimeException('Cannot write to the backup folder.');
    if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "Require all denied\n");

    $name = 'bodaerp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql.gz';
    $path = "$dir/$name";
    $gz = gzopen($path, 'wb6');
    if (!$gz) throw new RuntimeException('Cannot create the backup file.');
    try {
        backup_write_sql(function (string $sql) use ($gz) {
            if (gzwrite($gz, $sql) === false) throw new RuntimeException('Writing the backup failed; check free disk space.');
        });
    } catch (Throwable $e) {
        gzclose($gz); @unlink($path);
        throw $e;
    }
    gzclose($gz);
    if (!backup_is_complete($path)) { @unlink($path); throw new RuntimeException('The backup failed its completeness check and was discarded.'); }

    if ($key !== null) {
        try {
            backup_encrypt_file($path, "$path.enc", $key);
        } catch (Throwable $e) {
            @unlink("$path.enc"); @unlink($path);
            throw $e;
        }
        @unlink($path);
        $path .= '.enc'; $name .= '.enc';
    }
    @chmod($path, 0600);
    return ['name' => $name, 'path' => $path, 'bytes' => (int) filesize($path), 'encrypted' => $key !== null];
}

/** Newest first. @return list<array{name:string,path:string,bytes:int,time:int,encrypted:bool}> */
function backup_list(): array {
    $found = [];
    foreach (glob(backup_dir() . '/bodaerp_*') ?: [] as $path) {
        $found[] = ['name' => basename($path), 'path' => $path, 'bytes' => (int) filesize($path), 'time' => (int) filemtime($path), 'encrypted' => str_ends_with($path, '.enc')];
    }
    usort($found, fn($a, $b) => $b['time'] <=> $a['time']);
    return $found;
}

/** Deletes backups older than $keepDays, never leaving fewer than the newest three. */
function backup_prune(int $keepDays): int {
    $removed = 0;
    foreach (array_slice(backup_list(), 3) as $backup) {
        if ($backup['time'] < time() - $keepDays * 86400 && @unlink($backup['path'])) $removed++;
    }
    return $removed;
}
