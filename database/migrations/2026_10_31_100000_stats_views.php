<?php
// v_stage_stats / v_city_stats must be live VIEWS. Some databases (built by importing a
// phpMyAdmin dump) ended up with an empty physical TABLE called v_stage_stats instead, so
// every stage report showed blanks, and v_city_stats was missing altogether.
// An existing table of that name is renamed to bak_<name>_<date> (kept, never deleted).
return [
    'description' => 'Stage and city statistics are live views (replaces stale table copies left by SQL imports)',
    'up' => function (mysqli $db) {
        $views = [
            'v_stage_stats' => <<<'SQL'
SELECT
  s.id     AS stage_id,
  s.city_id,
  s.code,
  s.name,
  COUNT(r.id)                                                                  AS rider_count,
  COALESCE(SUM(r.status = 'active'),  0)                                       AS active_count,
  COALESCE(SUM(r.status = 'expired'), 0)                                       AS expired_count,
  COALESCE(SUM(r.status = 'pending'), 0)                                       AS pending_count,
  ROUND(100 * COALESCE(SUM(r.status = 'active'), 0) / NULLIF(COUNT(r.id), 0), 1) AS compliance_pct
FROM stages s
LEFT JOIN riders r ON r.stage_id = s.id AND r.deleted_at IS NULL
GROUP BY s.id, s.city_id, s.code, s.name
SQL,
            'v_city_stats' => <<<'SQL'
SELECT
  c.id   AS city_id,
  c.name,
  COUNT(DISTINCT s.id)                                                          AS stage_count,
  COUNT(r.id)                                                                   AS rider_count,
  COALESCE(SUM(r.status = 'active'),  0)                                        AS active_count,
  COALESCE(SUM(r.status = 'expired'), 0)                                        AS expired_count,
  COALESCE(SUM(r.status = 'pending'), 0)                                        AS pending_count,
  ROUND(100 * COALESCE(SUM(r.status = 'active'), 0) / NULLIF(COUNT(r.id), 0), 1) AS compliance_pct
FROM cities c
LEFT JOIN stages s  ON s.city_id  = c.id
LEFT JOIN riders r  ON r.stage_id = s.id AND r.deleted_at IS NULL
GROUP BY c.id, c.name
SQL,
        ];
        foreach ($views as $name => $select) {
            $type = mig_value($db, "SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$name]);
            if ($type === 'BASE TABLE') {
                $bak = 'bak_' . $name . '_' . date('Ymd');
                if (mig_table_exists($db, $bak)) $bak .= '_' . date('His');
                mig_exec($db, "RENAME TABLE `$name` TO `$bak`");
            }
            mig_exec($db, "CREATE OR REPLACE VIEW `$name` AS " . $select);
        }
    },
];
