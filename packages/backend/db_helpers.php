<?php
require_once __DIR__ . '/database/database.php';

// ── Core query helpers (mysqli) ───────────────────────────────
// All page code, auth, automation, anomaly engine and datapacks
// call these four functions. They accept positional (?) params.

function runQuery(string $sql, array $params = []): mysqli_result|bool {
    $db   = db();
    $stmt = $db->prepare($sql);
    if ($params) {
        $types = implode('', array_map(fn($v) => is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'), $params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result() ?: true;
    // Expose affected_rows and insert_id on the statement so callers can use them
    // via the returned object when they call ->affected_rows / ->close()
    return new class($stmt, $result) {
        public int  $affected_rows;
        public int  $insert_id;
        private     $stmt;
        private     $result;
        public function __construct(mysqli_stmt $stmt, $result) {
            $this->stmt          = $stmt;
            $this->result        = $result;
            $this->affected_rows = $stmt->affected_rows;
            $this->insert_id     = $stmt->insert_id;
        }
        public function fetch_assoc(): ?array { return is_object($this->result) ? $this->result->fetch_assoc() : null; }
        public function fetch_row(): ?array   { return is_object($this->result) ? $this->result->fetch_row()   : null; }
        public function fetchAll(): array     { return is_object($this->result) ? $this->result->fetch_all(MYSQLI_ASSOC) : []; }
        public function free(): void          { if (is_object($this->result)) $this->result->free(); }
        public function close(): void         { $this->stmt->close(); }
        public function store_result()        { return $this->result; }
        public function more_results(): bool  { return false; }
        public function next_result(): bool   { return false; }
    };
}

function fetchAll(string $sql, array $params = []): array {
    $r = runQuery($sql, $params);
    return $r->fetchAll();
}

function fetchOne(string $sql, array $params = []): ?array {
    $r = runQuery($sql, $params);
    return $r->fetch_assoc() ?: null;
}

function fetchValue(string $sql, array $params = []) {
    $r   = runQuery($sql, $params);
    $row = $r->fetch_row();
    return $row ? $row[0] : null;
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
