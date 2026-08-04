<?php
require_once __DIR__ . '/../config/database.php';

// Accepts either positional ("WHERE id = ?", [$id]) or named
// ("WHERE id = :id", ['id' => $id]) placeholders — mirrors how the
// app used to call these helpers under PDO — and prepares/binds
// them as a mysqli_stmt.
function _dbPrepare(string $sql, array $params = []): mysqli_stmt {
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
