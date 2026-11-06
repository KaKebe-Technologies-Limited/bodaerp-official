<?php
// Search and sharing text rewritten page by page (owner, 2026-11-07): titles under 60 characters, descriptions
// about 150, each naming who the page is for. The share image becomes a 1200x630 card. The footer photo-credit
// line is gone (the Photo credits page stays linked in the legal links, as the photo licences require).
// Only values still at the previous default change, so text edited in Super Admin is kept.
return [
    'description' => 'SEO titles, descriptions and share image; footer photo-credit line removed',
    'up' => function (mysqli $db) {
        $settings = [
            'seo_default_title' => ['BodaERP: register boda-boda riders, collect permit fees, check permits at the roadside', 'Boda-boda rider registration and permits in Uganda | BodaERP'],
            'seo_default_description' => ['BodaERP registers boda-boda riders at their stage, collects permit fees by cash or mobile money with a receipt for every payment, issues ID cards with a QR code and lets anyone check a permit in seconds.', 'Ugandan city councils use BodaERP to register boda-boda riders by stage, collect permit fees by cash or mobile money and check any permit by QR code.']
        ];
        $setting = $db->prepare("UPDATE `settings` SET `value` = ? WHERE `key` = ? AND `value` = ?");
        foreach ($settings as $key => [$old, $new]) {
            $setting->bind_param('sss', $new, $key, $old);
            $setting->execute();
        }
        $setting->close();
        $db->query("UPDATE `settings` SET `value` = '/assets/images/og-image.jpg' WHERE `key` = 'seo_og_image'
            AND `value` IN ('', '/assets/images/favicon-512.png', '/assets/images/logo.png')");
        $db->query("DELETE FROM `settings` WHERE `key` = 'footer_photo_credit'");

        $pages = [
            'about' => [['About BodaERP', 'Why boda-boda riders need to be registered, how BodaERP registers riders and collects permit fees, and who uses it.'],
                ['About BodaERP | Boda-boda permits for Ugandan cities', 'Why boda-boda riders need to be registered, how BodaERP registers them at their stage and collects permit fees, and the councils and riders it serves.']],
            'privacy-policy' => [['Privacy Policy | BodaERP', 'How BodaERP collects, uses, shares and protects the personal data of riders and staff.'],
                ['Privacy policy | BodaERP', 'How BodaERP collects, uses, shares and protects the personal data of boda-boda riders, stage chairpersons and council staff, and the rights each one has.']],
            'terms-of-service' => [['Terms of Service | BodaERP', 'The terms that apply to riders, stage chairpersons, city councils and government users of BodaERP.'],
                ['Terms of service | BodaERP', 'The terms for riders, stage chairpersons, city councils and government officers who use BodaERP to register riders, pay permit fees and check permits.']],
            'cookie-policy' => [['Cookie Policy | BodaERP', 'BodaERP uses only the cookies needed to keep you signed in. No tracking or advertising cookies.'],
                ['Cookie policy | BodaERP', 'BodaERP sets only the cookies that keep you signed in and remember your cookie choice. This site has no tracking or advertising cookies.']],
            'photo-credits' => [['Photo credits | BodaERP', 'Photographers and licences of the photos used on the BodaERP website.'],
                ['Photo credits | BodaERP', 'The photographers and Creative Commons licences of the boda-boda and city photos used on the BodaERP website, with links to each original.']]
        ];
        $page = $db->prepare("UPDATE `pages` SET `meta_title` = ?, `meta_description` = ? WHERE `slug` = ? AND `meta_title` = ? AND `meta_description` = ?");
        foreach ($pages as $slug => [[$oldTitle, $oldDesc], [$newTitle, $newDesc]]) {
            $page->bind_param('sssss', $newTitle, $newDesc, $slug, $oldTitle, $oldDesc);
            $page->execute();
        }
        $page->close();
    },
];
