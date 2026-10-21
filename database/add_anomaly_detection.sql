-- ============================================================
--  BodaERP — Anomaly & Data-Quality Detection
-- ============================================================
--  Safe to run once on an EXISTING database (local `bodaerp` or the
--  live u850523537_BodaERP26). Creates the two tables the detection
--  engine writes to. No existing table is modified.
--
--  Run:  mysql -u USER -p DBNAME < database/add_anomaly_detection.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `anomaly_flags` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fingerprint`       VARCHAR(100) NOT NULL,
  `rule_code`         VARCHAR(40)  NOT NULL,
  `category`          VARCHAR(30)  NOT NULL,
  `severity`          ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `entity_type`       VARCHAR(20)  NOT NULL,
  `entity_id`         VARCHAR(80)  NOT NULL,
  `rider_id`          INT NULL,
  `city_id`           CHAR(3) NULL,
  `title`             VARCHAR(200) NOT NULL,
  `details`           VARCHAR(600) NULL,
  `status`            ENUM('open','confirmed','dismissed','resolved') NOT NULL DEFAULT 'open',
  `first_detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`       DATETIME NULL,
  `reviewed_by`       INT NULL,
  `reviewed_at`       DATETIME NULL,
  `review_note`       VARCHAR(300) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_anomaly_fingerprint` (`fingerprint`),
  KEY `idx_anomaly_status_sev` (`status`,`severity`),
  KEY `idx_anomaly_rule` (`rule_code`),
  KEY `idx_anomaly_city` (`city_id`),
  KEY `idx_anomaly_rider` (`rider_id`),
  KEY `idx_anomaly_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `anomaly_scan_runs` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `started_at`     DATETIME NOT NULL,
  `finished_at`    DATETIME NULL,
  `triggered_by`   VARCHAR(40) NOT NULL DEFAULT 'manual',
  `new_flags`      INT NOT NULL DEFAULT 0,
  `resolved_flags` INT NOT NULL DEFAULT 0,
  `open_flags`     INT NOT NULL DEFAULT 0,
  `duration_ms`    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_scan_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
