<?php
// Public-site content (pages, menus, FAQ/feature items), email templates and outbox, contact inbox,
// media library, login throttling, hashed password-reset and remember-me tokens, optional email 2FA,
// rider data requests, and the default text for all of it (database/seed_data/site_content.php).
// Defaults use INSERT IGNORE, so anything an administrator has edited is never overwritten.
return [
    'description' => 'Content pages, menus, email templates, contact inbox, media library and login security tables',
    'up' => function (mysqli $db) {
        $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `pages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(120) NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `summary` VARCHAR(500) NULL,
            `body` MEDIUMTEXT NOT NULL,
            `meta_title` VARCHAR(200) NULL,
            `meta_description` VARCHAR(300) NULL,
            `og_image` VARCHAR(255) NULL,
            `status` ENUM('published','draft') NOT NULL DEFAULT 'published',
            `is_system` TINYINT(1) NOT NULL DEFAULT 0,
            `updated_by` INT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_pages_slug` (`slug`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `menu_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `menu` ENUM('header','footer_product','footer_company','footer_legal') NOT NULL,
            `label` VARCHAR(80) NOT NULL,
            `url` VARCHAR(255) NOT NULL,
            `sort_order` SMALLINT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `new_tab` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_menu_label` (`menu`, `label`),
            KEY `idx_menu_sort` (`menu`, `sort_order`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `content_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `group_code` VARCHAR(40) NOT NULL,
            `icon` VARCHAR(60) NULL,
            `title` VARCHAR(200) NOT NULL,
            `body` TEXT NOT NULL,
            `sort_order` SMALLINT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_content_item` (`group_code`, `title`),
            KEY `idx_content_group` (`group_code`, `sort_order`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `email_templates` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(60) NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `description` VARCHAR(300) NULL,
            `subject` VARCHAR(200) NOT NULL,
            `body_html` MEDIUMTEXT NOT NULL,
            `variables` VARCHAR(500) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `updated_by` INT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_email_template_code` (`code`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `email_log` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `to_email` VARCHAR(190) NOT NULL,
            `subject` VARCHAR(200) NOT NULL,
            `template_code` VARCHAR(60) NULL,
            `transport` VARCHAR(20) NOT NULL,
            `status` ENUM('sent','logged','failed') NOT NULL,
            `error` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_email_log_created` (`created_at`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `contact_messages` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(120) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `phone` VARCHAR(30) NULL,
            `organisation` VARCHAR(150) NULL,
            `subject` VARCHAR(200) NOT NULL,
            `message` TEXT NOT NULL,
            `status` ENUM('new','read','replied','archived','spam') NOT NULL DEFAULT 'new',
            `reply_body` TEXT NULL,
            `replied_by` INT NULL,
            `replied_at` DATETIME NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_contact_status` (`status`, `created_at`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `media` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `path` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `mime` VARCHAR(100) NOT NULL,
            `size_bytes` INT UNSIGNED NOT NULL,
            `width` INT UNSIGNED NULL,
            `height` INT UNSIGNED NULL,
            `alt_text` VARCHAR(255) NULL,
            `uploaded_by` INT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_media_path` (`path`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(190) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `success` TINYINT(1) NOT NULL DEFAULT 0,
            `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_attempt_email` (`email`, `attempted_at`),
            KEY `idx_attempt_ip` (`ip_address`, `attempted_at`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `rate_limits` (
            `bucket` VARCHAR(190) NOT NULL,
            `hits` INT UNSIGNED NOT NULL DEFAULT 0,
            `window_start` DATETIME NOT NULL,
            PRIMARY KEY (`bucket`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `user_tokens` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `purpose` ENUM('password_reset','email_verify','remember') NOT NULL,
            `selector` CHAR(24) NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_token_selector` (`selector`),
            KEY `idx_token_user` (`user_id`, `purpose`)
        ) $t");

        mig_exec($db, "CREATE TABLE IF NOT EXISTS `data_requests` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `rider_id` INT NULL,
            `type` ENUM('export','correction','deletion') NOT NULL,
            `details` TEXT NULL,
            `status` ENUM('open','completed','rejected') NOT NULL DEFAULT 'open',
            `response` TEXT NULL,
            `handled_by` INT NULL,
            `handled_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_data_requests_status` (`status`, `created_at`)
        ) $t");

        mig_add_column($db, 'users', 'email_verified_at', 'DATETIME NULL AFTER `email`');
        mig_add_column($db, 'users', 'two_factor_enabled', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_hash`');
        mig_add_column($db, 'users', 'notify_email', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `two_factor_enabled`');
        mig_add_column($db, 'users', 'notify_in_app', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_email`');
        mig_add_column($db, 'users', 'last_login_at', 'DATETIME NULL AFTER `status`');
        mig_add_column($db, 'users', 'password_changed_at', 'DATETIME NULL AFTER `last_login_at`');

        // Plain-text reset tokens are no longer used (user_tokens stores only a hash). Clear any left over.
        if (mig_column_exists($db, 'users', 'reset_token')) {
            mig_exec($db, "UPDATE `users` SET `reset_token` = NULL, `reset_expires` = NULL WHERE `reset_token` IS NOT NULL");
        }

        $c = require __DIR__ . '/../seed_data/site_content.php';

        $s = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,?,?,?,?)");
        foreach ($c['settings'] as [$k, $v, $type, $cat, $desc, $flag, $sort]) {
            $s->bind_param('sssssii', $k, $v, $type, $cat, $desc, $flag, $sort);
            $s->execute();
        }
        $s->close();

        // Placeholder values left by the first settings migration are not real contact details.
        mig_exec($db, "UPDATE `settings` SET `value` = '' WHERE `key` = 'support_phone' AND `value` = '+256 700 000 000'");
        mig_exec($db, "UPDATE `settings` SET `value` = '&copy; {year} Kakebe Technologies Limited. All rights reserved.' WHERE `key` = 'footer_copyright' AND `value` = '&copy; 2026 BodaERP. All rights reserved.'");

        $s = $db->prepare("INSERT IGNORE INTO `pages` (`slug`,`title`,`summary`,`body`,`meta_title`,`meta_description`,`is_system`) VALUES (?,?,?,?,?,?,?)");
        foreach ($c['pages'] as [$slug, $title, $summary, $body, $metaTitle, $metaDesc, $system]) {
            $s->bind_param('ssssssi', $slug, $title, $summary, $body, $metaTitle, $metaDesc, $system);
            $s->execute();
        }
        $s->close();

        $s = $db->prepare("INSERT IGNORE INTO `menu_items` (`menu`,`label`,`url`,`sort_order`) VALUES (?,?,?,?)");
        foreach ($c['menus'] as [$menu, $label, $url, $sort]) {
            $s->bind_param('sssi', $menu, $label, $url, $sort);
            $s->execute();
        }
        $s->close();

        $s = $db->prepare("INSERT IGNORE INTO `content_items` (`group_code`,`icon`,`title`,`body`,`sort_order`) VALUES (?,?,?,?,?)");
        foreach ($c['items'] as [$group, $icon, $title, $body, $sort]) {
            $s->bind_param('ssssi', $group, $icon, $title, $body, $sort);
            $s->execute();
        }
        $s->close();

        $s = $db->prepare("INSERT IGNORE INTO `email_templates` (`code`,`name`,`description`,`subject`,`body_html`,`variables`) VALUES (?,?,?,?,?,?)");
        foreach ($c['emails'] as [$code, $name, $desc, $subject, $body, $vars]) {
            $s->bind_param('ssssss', $code, $name, $desc, $subject, $body, $vars);
            $s->execute();
        }
        $s->close();
    },
];
