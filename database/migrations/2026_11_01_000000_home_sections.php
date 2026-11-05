<?php
// Home page sections "How it works", "Who uses it", participating cities, FAQ preview and permit check.
// Text comes from database/seed_data/site_content.php; INSERT IGNORE keeps anything an admin already edited.
return [
    'description' => 'Home page content: steps, roles, cities, FAQ preview and permit check band',
    'up' => function (mysqli $db) {
        $c = require __DIR__ . '/../seed_data/site_content.php';
        $keys = ['home_steps_title', 'home_steps_text', 'home_roles_title', 'home_roles_text', 'home_cities_title', 'home_cities_text',
            'home_show_cities', 'home_verify_title', 'home_verify_text', 'home_faq_title'];
        $s = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,?,?,?,?)");
        foreach ($c['settings'] as [$key, $value, $type, $cat, $desc, $flag, $sort]) {
            if (!in_array($key, $keys, true)) continue;
            $s->bind_param('sssssii', $key, $value, $type, $cat, $desc, $flag, $sort);
            $s->execute();
        }
        $s->close();
        $s = $db->prepare("INSERT IGNORE INTO `content_items` (`group_code`,`icon`,`title`,`body`,`sort_order`) VALUES (?,?,?,?,?)");
        foreach ($c['items'] as [$group, $icon, $title, $body, $sort]) {
            if (!in_array($group, ['home_step', 'home_role'], true)) continue;
            $s->bind_param('ssssi', $group, $icon, $title, $body, $sort);
            $s->execute();
        }
        $s->close();
    },
];
