<?php
// SMS through EgoSMS (templates per language + a send log), the channel for one-time sign-in codes,
// per-person SMS and language choices, and the translations table for the rider portal.
return [
    'description' => 'SMS templates and log, sign-in code channel, SMS and language preferences, translations',
    'up' => function (mysqli $db) {
        mig_exec($db, "CREATE TABLE IF NOT EXISTS `sms_templates` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `code` varchar(60) NOT NULL,
            `language` varchar(5) NOT NULL DEFAULT 'en',
            `name` varchar(120) NOT NULL,
            `description` varchar(300) DEFAULT NULL,
            `body` varchar(480) NOT NULL,
            `variables` varchar(500) DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `needs_review` tinyint(1) NOT NULL DEFAULT 0,
            `updated_by` int(11) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_sms_templates_code_lang` (`code`, `language`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `sms_log` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `to_phone` varchar(20) NOT NULL,
            `template_code` varchar(60) DEFAULT NULL,
            `language` varchar(5) DEFAULT NULL,
            `transport` varchar(20) NOT NULL,
            `status` enum('sent','failed','logged') NOT NULL,
            `segments` tinyint(3) unsigned NOT NULL DEFAULT 1,
            `cost` decimal(10,2) DEFAULT NULL,
            `provider_ref` varchar(100) DEFAULT NULL,
            `error` varchar(500) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_sms_log_created` (`created_at`),
            KEY `idx_sms_log_phone` (`to_phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `translations` (
            `language` varchar(5) NOT NULL,
            `msg_key` varchar(120) NOT NULL,
            `text` varchar(1000) NOT NULL,
            `needs_review` tinyint(1) NOT NULL DEFAULT 1,
            `updated_by` int(11) DEFAULT NULL,
            `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`language`, `msg_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        mig_add_column($db, 'users', 'two_factor_channel', "enum('email','sms') NOT NULL DEFAULT 'email' AFTER `two_factor_enabled`");
        mig_add_column($db, 'users', 'phone_verified_at', "datetime DEFAULT NULL AFTER `phone`");
        mig_add_column($db, 'users', 'notify_sms', "tinyint(1) NOT NULL DEFAULT 1 AFTER `notify_in_app`");
        mig_add_column($db, 'users', 'language', "varchar(5) NOT NULL DEFAULT 'en' AFTER `notify_sms`");
        mig_add_column($db, 'riders', 'language', "varchar(5) NOT NULL DEFAULT 'en' AFTER `email`");
        mig_add_column($db, 'riders', 'sms_opt_out', "tinyint(1) NOT NULL DEFAULT 0 AFTER `language`");

        $content = require __DIR__ . '/../seed_data/site_content.php';
        $insert = $db->prepare("INSERT IGNORE INTO `sms_templates` (`code`,`language`,`name`,`description`,`body`,`variables`,`is_active`,`needs_review`) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($content['sms'] as [$code, $lang, $name, $desc, $body, $vars, $active, $review]) {
            $insert->bind_param('ssssssii', $code, $lang, $name, $desc, $body, $vars, $active, $review);
            $insert->execute();
        }
        $insert->close();

        $settings = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,?,?,?,?)");
        foreach ($content['settings'] as [$key, $value, $type, $category, $description, $flag, $sort]) {
            if (!in_array($key, ['languages_enabled', 'ussd_code', 'feature_sms'], true)) continue;
            $settings->bind_param('sssssii', $key, $value, $type, $category, $description, $flag, $sort);
            $settings->execute();
        }
        $settings->close();
        mig_exec($db, "UPDATE `settings` SET `description` = 'Let users turn on sign-in codes by SMS or email (two-step sign-in)' WHERE `key` = 'feature_two_factor'");
    },
];
