<?php
// Two dark photo bands never sit next to each other on the homepage (owner, 2026-11-07): "why it matters" (after
// the hero) and the permit check (before the closing band) are light sections now. The why photo moves beside the
// reasons; the permit check band has no photo, so its setting goes.
return [
    'description' => 'Homepage: why-it-matters and permit check become light sections',
    'up' => function (mysqli $db) {
        $db->query("DELETE FROM `settings` WHERE `key` = 'home_verify_image'");
        $db->query("UPDATE `settings` SET `description` = 'Photo beside the \"why it matters\" reasons (wide screens)' WHERE `key` = 'home_why_image'");
    },
];
