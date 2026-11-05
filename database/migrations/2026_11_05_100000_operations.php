<?php
// Late fees per city, cash handovers from chairpersons to the city, USSD renewal sessions,
// logged permit checks by officers, and rider import batches.
return [
    'description' => 'Late fees, cash handovers, USSD sessions, permit checks, rider imports',
    'up' => function (mysqli $db) {
        mig_add_column($db, 'cities', 'late_fee', "decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `grace_period_days`");
        mig_add_column($db, 'cities', 'late_fee_type', "enum('flat','percent') NOT NULL DEFAULT 'flat' AFTER `late_fee`");
        mig_add_column($db, 'payments', 'late_fee', "decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `amount`");
        mig_add_column($db, 'payments', 'channel', "varchar(20) DEFAULT NULL AFTER `payment_method`");
        mig_add_column($db, 'payments', 'handover_id', "int(10) unsigned DEFAULT NULL AFTER `collected_by`");
        mig_add_index($db, 'payments', 'idx_payments_handover', '`handover_id`');

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `cash_handovers` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `reference` varchar(30) NOT NULL,
            `city_id` char(3) NOT NULL,
            `stage_id` int(11) NOT NULL,
            `submitted_by` int(11) NOT NULL,
            `payments_count` int(10) unsigned NOT NULL DEFAULT 0,
            `expected_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
            `handed_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
            `note` varchar(500) DEFAULT NULL,
            `status` enum('submitted','confirmed','queried') NOT NULL DEFAULT 'submitted',
            `received_amount` decimal(14,2) DEFAULT NULL,
            `reviewed_by` int(11) DEFAULT NULL,
            `reviewed_at` datetime DEFAULT NULL,
            `review_note` varchar(500) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_cash_handovers_reference` (`reference`),
            KEY `idx_cash_handovers_city` (`city_id`, `status`),
            KEY `idx_cash_handovers_stage` (`stage_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `ussd_sessions` (
            `session_id` varchar(100) NOT NULL,
            `phone` varchar(20) NOT NULL,
            `rider_id` int(11) DEFAULT NULL,
            `payment_id` int(11) DEFAULT NULL,
            `steps` smallint(5) unsigned NOT NULL DEFAULT 0,
            `last_input` varchar(200) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`session_id`),
            KEY `idx_ussd_sessions_phone` (`phone`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `permit_checks` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `rider_id` int(11) NOT NULL,
            `city_id` char(3) NOT NULL,
            `checked_by` int(11) NOT NULL,
            `status_seen` varchar(20) NOT NULL,
            `outcome` enum('ok','warned','impounded','other') NOT NULL DEFAULT 'ok',
            `note` varchar(300) DEFAULT NULL,
            `latitude` decimal(9,6) DEFAULT NULL,
            `longitude` decimal(9,6) DEFAULT NULL,
            `accuracy_m` int(10) unsigned DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_permit_checks_city` (`city_id`, `created_at`),
            KEY `idx_permit_checks_rider` (`rider_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `rider_imports` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `city_id` char(3) NOT NULL,
            `imported_by` int(11) NOT NULL,
            `file_name` varchar(200) NOT NULL,
            `rows_total` int(10) unsigned NOT NULL DEFAULT 0,
            `rows_created` int(10) unsigned NOT NULL DEFAULT 0,
            `rows_skipped` int(10) unsigned NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_rider_imports_city` (`city_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        mig_add_column($db, 'riders', 'import_id', "int(10) unsigned DEFAULT NULL AFTER `created_by`");

        $settings = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,?,?,?,?)");
        $content = require __DIR__ . '/../seed_data/site_content.php';
        foreach ($content['settings'] as [$key, $value, $type, $category, $description, $flag, $sort]) {
            if (!in_array($key, ['backup_email', 'feature_ussd'], true)) continue;
            $settings->bind_param('sssssii', $key, $value, $type, $category, $description, $flag, $sort);
            $settings->execute();
        }
        $settings->close();
    },
];
