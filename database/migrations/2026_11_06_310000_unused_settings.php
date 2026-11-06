<?php
// Settings rows that no page reads any more, so editing them in Text & branding changed nothing: menu labels
// replaced by the menu editor, the old appearance and footer fields, the home hero text and button replaced by
// the hero slides, and the short tagline the header no longer shows.
return [
    'description' => 'Remove settings that nothing reads',
    'up' => function (mysqli $db) {
        mig_exec($db, "DELETE FROM `settings` WHERE `key` IN ('nav_home','nav_dashboard','nav_riders','nav_stages','nav_settings','nav_login','nav_logout',
            'footer_contact','feature_ipn_notifications','appearance_primary_color','appearance_logo_url','home_hero_text','home_cta_primary','msg_saved','site_tagline_short')");
    },
];
