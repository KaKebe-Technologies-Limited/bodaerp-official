<?php
// Records which scheduled messages went out, so no rider or officer gets the same one twice.
// Adds the templates for those messages and retires the home page badge setting.
return [
    'description' => 'Automation send records, overdue and digest email templates, home badge setting removed',
    'up' => function (mysqli $db) {
        mig_exec($db, "CREATE TABLE IF NOT EXISTS `automation_sends` (
            `kind` varchar(40) NOT NULL,
            `target` varchar(40) NOT NULL,
            `period` varchar(40) NOT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`kind`, `target`, `period`),
            KEY `idx_automation_sends_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $content = require __DIR__ . '/../seed_data/site_content.php';
        $insert = $db->prepare("INSERT IGNORE INTO `email_templates` (`code`,`name`,`description`,`subject`,`body_html`,`variables`) VALUES (?,?,?,?,?,?)");
        foreach ($content['emails'] as [$code, $name, $desc, $subject, $body, $vars]) {
            if (!in_array($code, ['permit_overdue', 'digest_chairperson', 'digest_city', 'ops_alert'], true)) continue;
            $insert->bind_param('ssssss', $code, $name, $desc, $subject, $body, $vars);
            $insert->execute();
        }
        $insert->close();

        mig_exec($db, "DELETE FROM `settings` WHERE `key` = 'home_hero_badge'");
    },
];
