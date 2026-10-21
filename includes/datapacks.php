<?php
// ============================================================
// BodaERP — Data packs
//
// A data pack is a one-click, re-runnable set of records (a city, its stages,
// users, riders…) defined in database/datapacks/<code>.php. Applied from
// Super Admin → System & Updates, so onboarding a new city never needs SQL or
// file uploads. Packs are idempotent: existing records are never overwritten.
//
// A pack file returns:
//   ['title' => '...', 'description' => '...', 'apply' => function (): array { ... }]
// where apply() returns ['summary' => '...', 'credentials' => [[role,name,email,password,new], ...]].
// ============================================================

require_once __DIR__ . '/db_helpers.php';

function datapacks_dir(): string { return __DIR__ . '/../database/datapacks'; }

function datapacks_tables_ready(): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try { fetchValue("SELECT 1 FROM data_pack_runs LIMIT 1"); $ready = true; }
    catch (\Throwable $e) { $ready = false; }
    return $ready;
}

/** @return array<string,array> code => title, description, file, applied (row or null) */
function datapacks_list(): array {
    $applied = datapacks_tables_ready() ? array_column(fetchAll("SELECT * FROM data_pack_runs"), null, 'pack_code') : [];
    $out = [];
    $files = glob(datapacks_dir() . '/*.php') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $file) {
        $code = basename($file, '.php');
        $def = include $file;
        if (!is_array($def) || empty($def['apply'])) continue;
        $out[$code] = ['code' => $code, 'title' => $def['title'] ?? $code, 'description' => $def['description'] ?? '',
                       'hint' => $def['hint'] ?? '', 'file' => $file, 'applied' => $applied[$code] ?? null];
    }
    return $out;
}

function datapack_apply(string $code, ?int $userId): array {
    $packs = datapacks_list();
    if (!isset($packs[$code])) return ['ok' => false, 'error' => 'Unknown data pack.'];
    $def = include $packs[$code]['file'];
    $db = db();
    try {
        $db->begin_transaction();
        $res = $def['apply']();
        $summary = mb_substr((string) ($res['summary'] ?? 'Applied.'), 0, 400);
        runQuery("INSERT INTO data_pack_runs (pack_code, title, applied_at, applied_by, summary)
                  VALUES (?, ?, NOW(), ?, ?)
                  ON DUPLICATE KEY UPDATE applied_at = NOW(), applied_by = VALUES(applied_by), summary = VALUES(summary), run_count = run_count + 1",
            [$code, mb_substr($packs[$code]['title'], 0, 150), $userId, $summary])->close();
        $db->commit();
        return ['ok' => true, 'summary' => $summary, 'credentials' => $res['credentials'] ?? []];
    } catch (\Throwable $e) {
        try { $db->rollback(); } catch (\Throwable $e2) {}
        error_log("BodaERP data pack {$code} failed: " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
