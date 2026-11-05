<?php
require_once __DIR__ . '/database/database.php';
require_once __DIR__ . '/migrator.php';

// Accepts either positional ("WHERE id = ?", [$id]) or named
// ("WHERE id = :id", ['id' => $id]) placeholders and prepares/binds
// them as a mysqli_stmt (always a prepared statement, never string-built SQL).
function _dbPrepare(string $sql, array $params = []): mysqli_stmt {
    migrator_auto();   // applies pending database/migrations once, before the first query of the request
    $values = $params;

    if ($params !== [] && array_keys($params) !== range(0, count($params) - 1)) {
        $values = [];
        $sql = preg_replace_callback('/:([a-zA-Z_][a-zA-Z0-9_]*)/', function ($m) use ($params, &$values) {
            $values[] = $params[$m[1]] ?? null;
            return '?';
        }, $sql);
    }

    $stmt = db()->prepare($sql);

    if ($values !== []) {
        $types = '';
        foreach ($values as $v) {
            if (is_int($v))       $types .= 'i';
            elseif (is_float($v)) $types .= 'd';
            elseif (is_bool($v))  { $types .= 'i'; }
            else                   $types .= 's';
        }
        $bound = [];
        foreach ($values as $k => $v) {
            $bound[$k] = is_bool($v) ? (int) $v : $v;
        }
        $stmt->bind_param($types, ...$bound);
    }

    return $stmt;
}

function fetchAll(string $sql, array $params = []): array {
    $stmt = _dbPrepare($sql, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function fetchOne(string $sql, array $params = []): ?array {
    $stmt = _dbPrepare($sql, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?? null;
}

function fetchValue(string $sql, array $params = []) {
    $stmt = _dbPrepare($sql, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return $row === null ? null : $row[0];
}

function runQuery(string $sql, array $params = []): mysqli_stmt {
    $stmt = _dbPrepare($sql, $params);
    $stmt->execute();
    return $stmt;
}


/** All settings, loaded with one query per request. Super Admin → Content → Settings edits them. */
function settings_all(bool $reload = false): array {
    static $all = null;
    if ($all !== null && !$reload) return $all;
    $all = [];
    try {
        foreach (fetchAll("SELECT `key`, `value` FROM settings") as $row) $all[$row['key']] = (string) $row['value'];
    } catch (Throwable $e) {
        error_log('BodaERP settings unavailable: ' . $e->getMessage());
    }
    return $all;
}

/**
 * For probes that fall back to a default when a table is missing (first install, a migration not yet run):
 * the fallback keeps the page working, and this records why, so a real database fault is still visible.
 */
function db_probe_failed(string $what, Throwable $e): void {
    $message = "$what unavailable, using the default: " . $e->getMessage();
    if (function_exists('app_log')) app_log('db', $message); else error_log('BodaERP ' . $message);
}

function get_setting(string $key, string $default = ''): string {
    $all = settings_all();
    return array_key_exists($key, $all) && $all[$key] !== '' ? $all[$key] : $default;
}

function setting_bool(string $key, bool $default = false): bool {
    $all = settings_all();
    return array_key_exists($key, $all) ? filter_var($all[$key], FILTER_VALIDATE_BOOLEAN) : $default;
}

function setting_int(string $key, int $default): int {
    $v = get_setting($key, '');
    return ctype_digit($v) ? (int) $v : $default;
}
