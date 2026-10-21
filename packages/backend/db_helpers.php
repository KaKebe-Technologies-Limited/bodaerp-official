<?php
require_once __DIR__ . '/database/database.php';

// ── PDO parameterized query helper ────────────────────────────
// Accepts either positional ("WHERE id = ?", [$id]) or named
// ("WHERE id = :id", ['id' => $params]) placeholders.
// All values are bound via PDO::execute() — no string interpolation.

function _dbPrepare(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);

    if ($params !== []) {
        $types = '';
        $values = [];

        foreach ($params as $name => $value) {
            $key = ltrim($name, ':');
            if (is_int($value))          $types .= 'i';
            elseif (is_float($value))    $types .= 'd';
            elseif (is_bool($value))     $types .= 'b';
            else                         $types .= 's';

            $values[$key] = $value;
        }

        $stmt->execute($values);
    } else {
        $stmt->execute();
    }

    return $stmt;
}

// ── Query executors ───────────────────────────────────────────

function dbFetchAll(string $sql, array $params = []): array {
    $stmt = _dbPrepare($sql, $params);
    return $stmt->fetchAll();
}

function dbFetchOne(string $sql, array $params = []): ?array {
    $stmt = _dbPrepare($sql, $params);
    return $stmt->fetch() ?? null;
}

function dbFetchValue(string $sql, array $params = []): mixed {
    $stmt = _dbPrepare($sql, $params);
    $row = $stmt->fetch();
    return $row ? $row[0] : null;
}

// ── Settings helper ───────────────────────────────────────────

/**
 * Get a setting value from the database by key.
 *
 * @param string $key The settings key (e.g., 'site_name', 'footer_contact')
 * @param string $default Default value if setting not found
 * @return string The setting value, or $default if not found
 */
function get_setting(string $key, string $default = ''): string {
    $row = dbFetchOne(
        "SELECT value FROM settings WHERE \"key\" = :key",
        ["key" => $key]
    );

    if ($row && isset($row['value'])) {
        return $row['value'];
    }

    return $default;
}

/**
 * Get a setting value with type coercion.
 *
 * @param string $key The settings key
 * @param string $default Default value if setting not found
 * @return mixed The setting value, appropriately typed
 */
function get_setting_value(string $key, $default = null) {
    $row = dbFetchOne(
        "SELECT value, value_type FROM settings WHERE \"key\" = :key",
        ["key" => $key]
    );

    if ($row && isset($row['value'])) {
        $value = $row['value'];
        $type = $row['value_type'] ?? 'text';

        match ($type) {
            'boolean' => return filter_var($value, FILTER_VALIDATE_BOOLEAN) ?? $default;
            'json'    => return json_decode($value, true) ?? $default;
            'select'  => return $value;
            default   => return $value;
        }
    }

    return $default ?? $default;
}

/**
 * Check if a feature flag is enabled.
 *
 * @param string $key The feature flag key (e.g., 'feature_ckeditor5')
 * @return bool True if the feature flag is set to true, false otherwise
 */
function is_feature_enabled(string $key): bool {
    $row = dbFetchOne(
        "SELECT is_feature_flag FROM settings WHERE \"key\" = :key",
        ["key" => $key]
    );

    if ($row && isset($row['is_feature_flag'])) {
        return (bool) $row['is_feature_flag'];
    }

    return false;
}