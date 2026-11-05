<?php
// Brand colours and logo files now come from the real BodaERP logo (navy #001740, blue #056CFE, red #E2262F).
// Only values still at the old purple defaults are changed, so an admin's own choice is kept.
return [
    'description' => 'Logo palette, square logo icon and favicon set as the branding defaults',
    'up' => function (mysqli $db) {
        mig_exec($db, "UPDATE `settings` SET `value` = '#056CFE' WHERE `key` = 'brand_color_primary' AND LOWER(`value`) = '#5b4bd5'");
        mig_exec($db, "UPDATE `settings` SET `value` = '#E2262F' WHERE `key` = 'brand_color_accent' AND LOWER(`value`) = '#dc3545'");
        mig_exec($db, "UPDATE `settings` SET `value` = '/assets/images/logo-mark.png' WHERE `key` = 'logo_path' AND `value` IN ('/assets/images/logo.png', '/assets/images/logo1.png', '')");
        mig_exec($db, "UPDATE `settings` SET `value` = '/assets/images/favicon-32.png' WHERE `key` = 'favicon_path' AND `value` IN ('/assets/images/logo.png', '')");
        mig_exec($db, "UPDATE `settings` SET `value` = '/assets/images/favicon-512.png' WHERE `key` = 'seo_og_image' AND `value` = '/assets/images/logo.png'");
        mig_exec($db, "INSERT IGNORE INTO `settings` (`key`, `value`, `value_type`, `category`, `description`, `is_feature_flag`, `sort_order`)
            VALUES ('brand_color_dark', '#001740', 'color', 'branding', 'Dark brand colour (sidebar, footer, the \"Boda\" in the logo)', 0, 5)");
        mig_exec($db, "INSERT IGNORE INTO `settings` (`key`, `value`, `value_type`, `category`, `description`, `is_feature_flag`, `sort_order`)
            VALUES ('site_tagline_short', 'Boda-boda permits and compliance', 'text', 'general', 'Very short line beside the logo in the site header (hidden on phones)', 0, 8)");
    },
];
