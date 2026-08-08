-- ============================================================
-- BodaERP — MySQL / MariaDB schema
-- Import via phpMyAdmin, or:  mysql -u root < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS bodaerp
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bodaerp;

SET FOREIGN_KEY_CHECKS = 0;

-- ── cities (tenants) ────────────────────────────────────────
DROP TABLE IF EXISTS cities;
CREATE TABLE cities (
  id                 CHAR(3) PRIMARY KEY,                 -- LIR, GUL, KLA, MBA
  name               VARCHAR(120) NOT NULL,
  country            VARCHAR(80)  NOT NULL DEFAULT 'Uganda',
  currency           CHAR(3)      NOT NULL DEFAULT 'UGX',
  logo_path          VARCHAR(255),
  annual_fee         DECIMAL(12,2) NOT NULL DEFAULT 0,
  fiscal_year        VARCHAR(20)  NOT NULL DEFAULT '2026/2027',
  id_prefix          VARCHAR(20)  NOT NULL,
  status             ENUM('active','pending','suspended') NOT NULL DEFAULT 'pending',
  contact_email      VARCHAR(150),
  contact_phone      VARCHAR(30),
  address            VARCHAR(255),
  payment_gateway    VARCHAR(60),
  sms_gateway        VARCHAR(60),
  revenue_split_city        TINYINT UNSIGNED NOT NULL DEFAULT 60,
  revenue_split_association TINYINT UNSIGNED NOT NULL DEFAULT 26,
  revenue_split_platform    TINYINT UNSIGNED NOT NULL DEFAULT 14,
  compliance_target  TINYINT UNSIGNED NOT NULL DEFAULT 70,
  grace_period_days  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  reminder_days      SMALLINT UNSIGNED NOT NULL DEFAULT 14,
  created_by         INT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_revenue_split CHECK (revenue_split_city + revenue_split_association + revenue_split_platform = 100)
) ENGINE=InnoDB;

-- ── stages ──────────────────────────────────────────────────
DROP TABLE IF EXISTS stages;
CREATE TABLE stages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  city_id     CHAR(3) NOT NULL,
  code        VARCHAR(20) NOT NULL UNIQUE,          -- LIR-STG-001
  name        VARCHAR(120) NOT NULL,
  location    VARCHAR(150),
  route       VARCHAR(150),
  chairperson_name  VARCHAR(150),   -- lightweight contact info; may or may not have a linked login (see users.stage_id)
  chairperson_phone VARCHAR(30),
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE RESTRICT,
  INDEX idx_stage_city (city_id)
) ENGINE=InnoDB;

-- ── users (auth accounts, all roles) ───────────────────────
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  email          VARCHAR(150) NOT NULL UNIQUE,
  phone          VARCHAR(30),
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('super_admin','city_admin','chairperson','rider') NOT NULL,
  city_id        CHAR(3) NULL,
  stage_id       INT NULL,
  avatar_path    VARCHAR(255),
  status         ENUM('active','suspended') NOT NULL DEFAULT 'active',
  deleted_at     DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (city_id)  REFERENCES cities(id)  ON DELETE RESTRICT,
  FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE SET NULL,
  INDEX idx_users_role (role),
  INDEX idx_users_city (city_id)
) ENGINE=InnoDB;

ALTER TABLE cities
  ADD CONSTRAINT fk_cities_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- ── riders (business/compliance record, not necessarily a login) ─
DROP TABLE IF EXISTS riders;
CREATE TABLE riders (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  user_id              INT NULL,                     -- only set if this rider has portal access
  city_id              CHAR(3) NOT NULL,
  stage_id             INT NOT NULL,
  id_number            VARCHAR(30) NOT NULL UNIQUE,   -- BODA-LIR-004521
  full_name            VARCHAR(150) NOT NULL,
  nin                  VARCHAR(20) UNIQUE NULL,
  date_of_birth        DATE NULL,
  gender               ENUM('Male','Female','Other') NULL,
  marital_status       ENUM('Single','Married','Divorced','Widowed') NULL,
  phone                VARCHAR(30) NOT NULL,
  email                VARCHAR(150) NULL,
  physical_address     VARCHAR(255) NULL,
  next_of_kin_name     VARCHAR(150) NULL,
  next_of_kin_contact  VARCHAR(30) NULL,
  bike_plate           VARCHAR(20) NOT NULL,
  bike_model           VARCHAR(100) NULL,
  route                VARCHAR(150) NULL,
  photo_path           VARCHAR(255) NULL,
  status               ENUM('active','expired','pending') NOT NULL DEFAULT 'pending',
  member_since         DATE NOT NULL,
  expiry_date          DATE NULL,
  annual_tax           DECIMAL(12,2) NOT NULL,
  created_by           INT NULL,                      -- chairperson user who registered them
  deleted_at           DATETIME NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(id)  ON DELETE SET NULL,
  FOREIGN KEY (city_id)    REFERENCES cities(id) ON DELETE RESTRICT,
  FOREIGN KEY (stage_id)   REFERENCES stages(id) ON DELETE RESTRICT,
  FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL,
  INDEX idx_riders_stage (stage_id),
  INDEX idx_riders_city_status (city_id, status),
  INDEX idx_riders_expiry (expiry_date)
) ENGINE=InnoDB;

-- ── payments ────────────────────────────────────────────────
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  rider_id        INT NOT NULL,
  city_id         CHAR(3) NOT NULL,
  amount          DECIMAL(12,2) NOT NULL,
  payment_method  ENUM('Mobile Money','Cash','Bank Transfer') NOT NULL,
  receipt_number  VARCHAR(30) NOT NULL UNIQUE,
  status          ENUM('Confirmed','Pending','Failed') NOT NULL DEFAULT 'Confirmed',
  fiscal_year     VARCHAR(20) NOT NULL,
  paid_at         DATETIME NOT NULL,
  collected_by    INT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rider_id)     REFERENCES riders(id) ON DELETE RESTRICT,
  FOREIGN KEY (city_id)      REFERENCES cities(id) ON DELETE RESTRICT,
  FOREIGN KEY (collected_by) REFERENCES users(id)  ON DELETE SET NULL,
  INDEX idx_payments_rider (rider_id),
  INDEX idx_payments_city_status (city_id, status),
  INDEX idx_payments_paidat (paid_at)
) ENGINE=InnoDB;

-- ── enforcement_actions ─────────────────────────────────────
DROP TABLE IF EXISTS enforcement_actions;
CREATE TABLE enforcement_actions (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  action_code     VARCHAR(20) NOT NULL UNIQUE,        -- ENF-2026-001
  rider_id        INT NULL,
  city_id         CHAR(3) NOT NULL,
  stage_id        INT NULL,
  plate           VARCHAR(20),
  type            ENUM('warning','fine','suspension','impound') NOT NULL,
  amount          DECIMAL(12,2) NULL,
  description     VARCHAR(500),
  action_date     DATE NOT NULL,
  status          ENUM('pending','resolved','closed') NOT NULL DEFAULT 'pending',
  officer_user_id INT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (rider_id)        REFERENCES riders(id) ON DELETE SET NULL,
  FOREIGN KEY (city_id)         REFERENCES cities(id) ON DELETE RESTRICT,
  FOREIGN KEY (stage_id)        REFERENCES stages(id) ON DELETE SET NULL,
  FOREIGN KEY (officer_user_id) REFERENCES users(id)  ON DELETE SET NULL,
  INDEX idx_enforcement_city (city_id),
  INDEX idx_enforcement_status (status)
) ENGINE=InnoDB;

-- ── notifications ───────────────────────────────────────────
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  rider_id    INT NOT NULL,
  type        ENUM('Renewal Reminder','Payment Confirmed','Important Update','ID Card Issued','Registration Complete') NOT NULL,
  message     VARCHAR(255) NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
  INDEX idx_notifications_rider (rider_id, is_read)
) ENGINE=InnoDB;

-- ── audit_logs ──────────────────────────────────────────────
DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
  id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id            INT NULL,
  user_name_snapshot VARCHAR(150),
  role_snapshot      VARCHAR(20),
  city_id            CHAR(3) NULL,
  action             ENUM('LOGIN','LOGOUT','LOGIN_FAILED','CREATE','UPDATE','DELETE') NOT NULL,
  entity_type        VARCHAR(50) NULL,
  entity_id          VARCHAR(50) NULL,
  details            VARCHAR(500),
  ip_address         VARCHAR(45),
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_action (action)
) ENGINE=InnoDB;

-- ── login_sessions (audit trail of active/past sessions) ────
DROP TABLE IF EXISTS login_sessions;
CREATE TABLE login_sessions (
  id             VARCHAR(64) PRIMARY KEY,   -- PHP session_id()
  user_id        INT NOT NULL,
  ip_address     VARCHAR(45),
  user_agent     VARCHAR(255),
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_activity  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at     DATETIME NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_sessions_user (user_id)
) ENGINE=InnoDB;

-- ── platform_settings (single-row global config) ────────────
DROP TABLE IF EXISTS platform_settings;
CREATE TABLE platform_settings (
  id                          TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  platform_name               VARCHAR(100) NOT NULL DEFAULT 'BodaERP',
  operator_name                VARCHAR(150) NOT NULL DEFAULT 'Kakebe Technologies Limited',
  support_email                VARCHAR(150),
  support_phone                VARCHAR(30),
  default_split_city           TINYINT UNSIGNED NOT NULL DEFAULT 60,
  default_split_association    TINYINT UNSIGNED NOT NULL DEFAULT 26,
  default_split_platform       TINYINT UNSIGNED NOT NULL DEFAULT 14,
  require_2fa                  TINYINT(1) NOT NULL DEFAULT 1,
  maintenance_mode             TINYINT(1) NOT NULL DEFAULT 0,
  updated_at                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB;

INSERT INTO platform_settings (id, support_email, support_phone) VALUES (1, 'support@kakebe.tech', '+256 700 000 000');

SET FOREIGN_KEY_CHECKS = 1;

-- ── views: computed stats (avoid drift vs. storing counters) ─
-- Using CREATE OR REPLACE so this works on shared hosting (no SUPER/SET USER needed)
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
