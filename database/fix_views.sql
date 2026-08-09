-- ============================================================
-- BodaERP — Fix Views for Shared Hosting (Hostinger)
-- Upload and run this in phpMyAdmin → Import
-- ============================================================

CREATE OR REPLACE VIEW v_stage_stats AS
SELECT
  s.id AS stage_id,
  s.city_id,
  s.code,
  s.name,
  COUNT(r.id) AS rider_count,
  COALESCE(SUM(r.status = 'active'), 0)  AS active_count,
  COALESCE(SUM(r.status = 'expired'), 0) AS expired_count,
  COALESCE(SUM(r.status = 'pending'), 0) AS pending_count,
  ROUND(100 * COALESCE(SUM(r.status = 'active'), 0) / NULLIF(COUNT(r.id), 0), 1) AS compliance_pct
FROM stages s
LEFT JOIN riders r ON r.stage_id = s.id AND r.deleted_at IS NULL
GROUP BY s.id, s.city_id, s.code, s.name;

CREATE OR REPLACE VIEW v_city_stats AS
SELECT
  c.id AS city_id,
  c.name,
  COUNT(DISTINCT s.id) AS stage_count,
  COUNT(r.id) AS rider_count,
  COALESCE(SUM(r.status = 'active'), 0)  AS active_count,
  COALESCE(SUM(r.status = 'expired'), 0) AS expired_count,
  COALESCE(SUM(r.status = 'pending'), 0) AS pending_count,
  ROUND(100 * COALESCE(SUM(r.status = 'active'), 0) / NULLIF(COUNT(r.id), 0), 1) AS compliance_pct
FROM cities c
LEFT JOIN stages s ON s.city_id = c.id
LEFT JOIN riders r ON r.stage_id = s.id AND r.deleted_at IS NULL
GROUP BY c.id, c.name;
