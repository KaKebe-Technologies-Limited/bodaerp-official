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

// ── Settings helpers ──────────────────────────────────────────

function get_setting(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $v = fetchValue("SELECT value FROM settings WHERE `key` = ?", [$key]);
        $cache[$key] = $v !== null ? (string) $v : $default;
    } catch (Throwable $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

function get_setting_value(string $key, $default = null) {
    try {
        $row = fetchOne("SELECT value, value_type FROM settings WHERE `key` = ?", [$key]);
        if (!$row) return $default;
        return match ($row['value_type'] ?? 'text') {
            'boolean' => filter_var($row['value'], FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($row['value'], true) ?? $default,
            default   => $row['value'],
        };
    } catch (Throwable $e) { return $default; }
}

function is_feature_enabled(string $key): bool {
    try {
        $v = fetchValue("SELECT is_feature_flag FROM settings WHERE `key` = ?", [$key]);
        return (bool) $v;
    } catch (Throwable $e) { return false; }
}
