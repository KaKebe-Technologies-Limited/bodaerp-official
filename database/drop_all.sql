-- ============================================================
-- BodaERP — Drop ALL tables and views
-- ⚠️  THIS DELETES EVERYTHING — run only on a fresh/test DB
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS v_city_stats;
DROP VIEW  IF EXISTS v_stage_stats;

DROP TABLE IF EXISTS login_sessions;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS enforcement_actions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS riders;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS stages;
DROP TABLE IF EXISTS platform_settings;
DROP TABLE IF EXISTS cities;

SET FOREIGN_KEY_CHECKS = 1;
