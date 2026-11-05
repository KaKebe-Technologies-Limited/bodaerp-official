<?php
// Label settings: nine were never read by any page (they only cluttered Text & branding), and the three that are
// used held generic "Welcome to your dashboard" text. Unused rows are removed; used rows change only if they still
// hold the original default, so text an admin has edited is kept.
return [
    'description' => 'Remove unused label settings, replace generic dashboard texts that were never edited',
    'up' => function (mysqli $db) {
        $unused = ['chairperson_payment_initiated', 'citycouncil_welcome_text', 'rider_dashboard_title', 'rider_welcome_text',
            'rider_payment_history', 'rider_id_card', 'superadmin_dashboard_title', 'superadmin_users_title', 'superadmin_cities_title'];
        $delete = $db->prepare("DELETE FROM `settings` WHERE `key` = ? AND `category` = 'labels'");
        foreach ($unused as $key) { $delete->bind_param('s', $key); $delete->execute(); }
        $delete->close();

        $replace = [
            ['chairperson_dashboard_title', 'Stage Dashboard', 'Stage dashboard'],
            ['chairperson_welcome_text', 'Welcome to your stage dashboard', 'Riders, cash collected and renewals due at your stage.'],
            ['citycouncil_dashboard_title', 'City Council Dashboard', 'City council dashboard'],
        ];
        $update = $db->prepare("UPDATE `settings` SET `value` = ? WHERE `key` = ? AND `value` = ?");
        foreach ($replace as [$key, $old, $new]) { $update->bind_param('sss', $new, $key, $old); $update->execute(); }
        $update->close();
    },
];
