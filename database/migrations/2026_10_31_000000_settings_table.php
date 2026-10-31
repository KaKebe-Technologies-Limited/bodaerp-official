<?php
// Key-value store for page titles, labels, navigation text and feature flags
// (read by get_setting() / get_setting_value() / is_feature_enabled() in db_helpers.php).
// Defaults use INSERT IGNORE so values an admin has edited are never overwritten.
return [
    'description' => 'Settings table (configurable labels, titles and feature flags) with default content',
    'up' => function (mysqli $db) {
        mig_exec($db, "CREATE TABLE IF NOT EXISTS `settings` (
            `id`              INT NOT NULL AUTO_INCREMENT,
            `key`             VARCHAR(100) NOT NULL,
            `value`           TEXT NOT NULL,
            `value_type`      VARCHAR(20)  NOT NULL DEFAULT 'text',
            `category`        VARCHAR(50)  NOT NULL DEFAULT 'general',
            `description`     VARCHAR(255) NULL,
            `is_feature_flag` TINYINT(1)   NOT NULL DEFAULT 0,
            `sort_order`      SMALLINT     NOT NULL DEFAULT 0,
            `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_settings_key` (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // key, value, type, category, description, is_feature_flag, sort_order
        $rows = [
            ['site_name', 'BodaERP', 'text', 'general', 'Site name / title', 0, 1],
            ['operator_name', 'Kakebe Technologies Limited', 'text', 'general', 'Operator/company name', 0, 2],
            ['support_email', 'support@kakebe.tech', 'text', 'general', 'Support email address', 0, 3],
            ['support_phone', '+256 700 000 000', 'text', 'general', 'Support phone number', 0, 4],
            ['footer_copyright', '&copy; 2026 BodaERP. All rights reserved.', 'text', 'general', 'Footer copyright text', 0, 5],
            ['footer_contact', 'support@kakebe.tech', 'text', 'general', 'Footer contact email', 0, 6],

            ['nav_home', 'Home', 'text', 'navigation', 'Home menu label', 0, 1],
            ['nav_dashboard', 'Dashboard', 'text', 'navigation', 'Dashboard menu label', 0, 2],
            ['nav_riders', 'Riders', 'text', 'navigation', 'Riders menu label', 0, 3],
            ['nav_stages', 'Stages', 'text', 'navigation', 'Stages menu label', 0, 4],
            ['nav_payments', 'Payments', 'text', 'navigation', 'Payments menu label', 0, 5],
            ['nav_settings', 'Settings', 'text', 'navigation', 'Settings menu label', 0, 6],
            ['nav_login', 'Login', 'text', 'navigation', 'Login menu label', 0, 7],
            ['nav_logout', 'Logout', 'text', 'navigation', 'Logout menu label', 0, 8],

            ['chairperson_dashboard_title', 'Stage Dashboard', 'text', 'labels', 'Chairperson dashboard page title', 0, 1],
            ['chairperson_welcome_text', 'Welcome to your stage dashboard', 'textarea', 'labels', 'Chairperson dashboard welcome message', 0, 2],
            ['chairperson_payment_initiated', 'Payment initiated successfully', 'text', 'labels', 'Payment initiation success message', 0, 3],

            ['citycouncil_dashboard_title', 'City Council Dashboard', 'text', 'labels', 'City council dashboard page title', 0, 1],
            ['citycouncil_welcome_text', 'Welcome to your city dashboard', 'textarea', 'labels', 'City council dashboard welcome message', 0, 2],

            ['rider_dashboard_title', 'Rider Dashboard', 'text', 'labels', 'Rider dashboard page title', 0, 1],
            ['rider_welcome_text', 'Welcome to your rider dashboard', 'textarea', 'labels', 'Rider dashboard welcome message', 0, 2],
            ['rider_payment_history', 'Payment History', 'text', 'labels', 'Payment history section label', 0, 3],
            ['rider_id_card', 'ID Card', 'text', 'labels', 'ID Card section label', 0, 4],

            ['superadmin_dashboard_title', 'Super Admin Dashboard', 'text', 'labels', 'Super admin dashboard page title', 0, 1],
            ['superadmin_users_title', 'User Management', 'text', 'labels', 'Users page title', 0, 2],
            ['superadmin_cities_title', 'City Management', 'text', 'labels', 'Cities page title', 0, 3],

            ['feature_ckeditor5', 'true', 'boolean', 'features', 'Enable CKEditor 5 rich text editor', 1, 1],
            ['feature_online_payments', 'true', 'boolean', 'features', 'Enable online payment initiation', 1, 2],
            ['feature_ipn_notifications', 'true', 'boolean', 'features', 'Enable ioTec IPN notifications', 1, 3],

            ['appearance_primary_color', '#0066CC', 'text', 'appearance', 'Primary color for UI', 0, 1],
            ['appearance_logo_url', '', 'text', 'appearance', 'Logo URL for header', 0, 2],
        ];

        $stmt = $db->prepare("INSERT IGNORE INTO `settings`
            (`key`, `value`, `value_type`, `category`, `description`, `is_feature_flag`, `sort_order`)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as [$key, $value, $type, $category, $description, $flag, $sort]) {
            $stmt->bind_param('sssssii', $key, $value, $type, $category, $description, $flag, $sort);
            $stmt->execute();
        }
        $stmt->close();
    },
];
