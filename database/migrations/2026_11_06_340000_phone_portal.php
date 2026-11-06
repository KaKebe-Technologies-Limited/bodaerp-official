<?php
// Riders without an email address get a portal account too: they sign in with their phone number, and the
// set-password and reset links come by SMS. users.email becomes optional (still unique when present).
return [
    'description' => 'Rider portal accounts by phone number, SMS invite and reset links',
    'up' => function (mysqli $db) {
        if (str_contains((string) mig_value($db, "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email'"), 'NO')) {
            mig_exec($db, "ALTER TABLE `users` MODIFY `email` varchar(150) DEFAULT NULL");
        }
        mig_add_index($db, 'users', 'idx_users_phone', '`phone`');
        $content = require __DIR__ . '/../seed_data/site_content.php';
        $insert = $db->prepare("INSERT IGNORE INTO `sms_templates` (`code`,`language`,`name`,`description`,`body`,`variables`,`is_active`,`needs_review`) VALUES (?,?,?,?,?,?,?,?)");
        foreach ($content['sms'] as [$code, $lang, $name, $desc, $body, $vars, $active, $review]) {
            if (!in_array($code, ['portal_invite', 'password_reset'], true)) continue;
            $insert->bind_param('ssssssii', $code, $lang, $name, $desc, $body, $vars, $active, $review);
            $insert->execute();
        }
        $insert->close();
    },
];
