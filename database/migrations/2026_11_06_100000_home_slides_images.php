<?php
// Home page hero slider and photo backgrounds: content_items gets an image and a button (label + link), the
// "home_slide" group gets four slides, three settings hold the band background photos, and a "Photo credits"
// page names the photographers (the Wikimedia Commons licences require it). INSERT IGNORE and "only when empty"
// updates keep anything an admin already changed.
return [
    'description' => 'Home hero slides with photos, band background photos, photo credits page',
    'up' => function (mysqli $db) {
        mig_add_column($db, 'content_items', 'image', "VARCHAR(255) NULL DEFAULT NULL AFTER `body`");
        mig_add_column($db, 'content_items', 'link_label', "VARCHAR(80) NULL DEFAULT NULL AFTER `image`");
        mig_add_column($db, 'content_items', 'link_url', "VARCHAR(255) NULL DEFAULT NULL AFTER `link_label`");
        $c = require __DIR__ . '/../seed_data/site_content.php';

        $add = $db->prepare("INSERT IGNORE INTO `content_items` (`group_code`,`icon`,`title`,`body`,`sort_order`) VALUES (?,?,?,?,?)");
        $fill = $db->prepare("UPDATE `content_items` SET `image` = ?, `link_label` = ?, `link_url` = ? WHERE `group_code` = ? AND `title` = ? AND `image` IS NULL");
        foreach ($c['items'] as $item) {
            [$group, $icon, $title, $body, $sort] = $item;
            if ($group !== 'home_slide') continue;
            [$image, $linkLabel, $linkUrl] = [$item[5] ?? null, $item[6] ?? null, $item[7] ?? null];
            $add->bind_param('ssssi', $group, $icon, $title, $body, $sort);
            $add->execute();
            $fill->bind_param('sssss', $image, $linkLabel, $linkUrl, $group, $title);
            $fill->execute();
        }
        $add->close();
        $fill->close();

        $keys = ['home_slider_interval', 'home_stats_image', 'home_verify_image', 'home_cta_image', 'home_roles_image', 'footer_photo_credit'];
        $s = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,?,?,?,?)");
        foreach ($c['settings'] as [$key, $value, $type, $cat, $desc, $flag, $sort]) {
            if (!in_array($key, $keys, true)) continue;
            $s->bind_param('sssssii', $key, $value, $type, $cat, $desc, $flag, $sort);
            $s->execute();
        }
        $s->close();

        $p = $db->prepare("INSERT IGNORE INTO `pages` (`slug`,`title`,`summary`,`body`,`meta_title`,`meta_description`,`is_system`) VALUES (?,?,?,?,?,?,?)");
        foreach ($c['pages'] as [$slug, $title, $summary, $body, $mt, $md, $sys]) {
            if ($slug !== 'photo-credits') continue;
            $p->bind_param('ssssssi', $slug, $title, $summary, $body, $mt, $md, $sys);
            $p->execute();
        }
        $p->close();

        $m = $db->prepare("INSERT IGNORE INTO `menu_items` (`menu`,`label`,`url`,`sort_order`) VALUES (?,?,?,?)");
        foreach ($c['menus'] as [$menu, $label, $url, $sort]) {
            if ($url !== '/p/photo-credits') continue;
            $m->bind_param('sssi', $menu, $label, $url, $sort);
            $m->execute();
        }
        $m->close();
    },
];
