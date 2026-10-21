-- ============================================================
--  BodaERP — Add "Government" as a 4th revenue-split party
-- ============================================================
--  Safe to run once on an EXISTING database (local `bodaerp` or
--  the live u850523537_BodaERP26 database) that already has data.
--  It adds the new columns, then carves a "Government" share out
--  of the existing Platform share for every row, so every city
--  keeps summing to exactly 100% — no manual fix-up needed.
--
--  Run:  mysql -u USER -p DBNAME < database/add_government_revenue_split.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----- cities -----
ALTER TABLE `cities`
    ADD COLUMN IF NOT EXISTS `revenue_split_government` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `revenue_split_platform`;

-- Carve the Government share out of the existing Platform share (roughly half),
-- so City Council and Boda Association allocations are left untouched and the
-- four columns still total 100 for every city.
UPDATE `cities`
SET `revenue_split_government` = FLOOR(`revenue_split_platform` / 2),
    `revenue_split_platform`   = `revenue_split_platform` - FLOOR(`revenue_split_platform` / 2)
WHERE `revenue_split_government` = 0;

-- ----- platform_settings -----
ALTER TABLE `platform_settings`
    ADD COLUMN IF NOT EXISTS `default_split_government` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `default_split_platform`;

UPDATE `platform_settings`
SET `default_split_government` = FLOOR(`default_split_platform` / 2),
    `default_split_platform`   = `default_split_platform` - FLOOR(`default_split_platform` / 2)
WHERE `default_split_government` = 0;

SET FOREIGN_KEY_CHECKS = 1;

-- Sanity check — every row below should show total = 100
-- SELECT id, revenue_split_city + revenue_split_association + revenue_split_platform + revenue_split_government AS total FROM cities;
