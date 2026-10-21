-- ============================================================
--  BodaERP — Add the "Government" role & National Portal
-- ============================================================
--  Safe to run once on an EXISTING database (local `bodaerp` or
--  the live u850523537_BodaERP26 database) that already has data.
--
--  Run:  mysql -u USER -p DBNAME < database/add_government_portal.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('super_admin','city_admin','chairperson','rider','government') NOT NULL;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `agency_name` VARCHAR(150) NULL AFTER `stage_id`;

SET FOREIGN_KEY_CHECKS = 1;

-- Example national/ministry account (edit email + password before creating in production):
-- INSERT INTO users (name, email, phone, password_hash, role, agency_name, status)
-- VALUES ('Ministry of Works & Transport', 'works@gov.ug', NULL, '<bcrypt-hash>', 'government', 'Ministry of Works & Transport', 'active');
