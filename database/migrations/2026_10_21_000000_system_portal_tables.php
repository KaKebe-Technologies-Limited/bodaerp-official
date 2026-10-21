<?php
return [
    'description' => 'System portal: automation jobs, system settings and data-pack tracking',
    'up' => function (mysqli $db) {
        mig_exec($db, "CREATE TABLE IF NOT EXISTS `automation_jobs` (
            `job_code`          VARCHAR(40)  NOT NULL,
            `enabled`           TINYINT(1)   NOT NULL DEFAULT 1,
            `last_run_at`       DATETIME NULL,
            `last_status`       VARCHAR(10)  NULL,
            `last_message`      VARCHAR(400) NULL,
            `last_duration_ms`  INT NOT NULL DEFAULT 0,
            `last_triggered_by` VARCHAR(40)  NULL,
            `run_count`         INT NOT NULL DEFAULT 0,
            PRIMARY KEY (`job_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        mig_exec($db, "CREATE TABLE IF NOT EXISTS `system_settings` (
            `setting_key`   VARCHAR(60) NOT NULL,
            `setting_value` TEXT NULL,
            `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        mig_exec($db, "CREATE TABLE IF NOT EXISTS `data_pack_runs` (
            `pack_code`   VARCHAR(60)  NOT NULL,
            `title`       VARCHAR(150) NOT NULL,
            `applied_at`  DATETIME NOT NULL,
            `applied_by`  INT NULL,
            `summary`     VARCHAR(400) NULL,
            `run_count`   INT NOT NULL DEFAULT 1,
            PRIMARY KEY (`pack_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
];
