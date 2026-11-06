<?php
// Sign-in page trimmed to the form (owner, 2026-11-07: "too much word"): heading "Sign in", no subheading and no help
// paragraph. Only text still at the previous default changes; anything edited in Super Admin is kept.
return [
    'description' => 'Shorter sign-in page text',
    'up' => function (mysqli $db) {
        $db->query("UPDATE `settings` SET `value` = 'Sign in' WHERE `key` = 'login_heading' AND `value` = 'Sign in to BodaERP'");
        $db->query("UPDATE `settings` SET `value` = '', `description` = 'Text under the sign-in heading (empty = hidden)' WHERE `key` = 'login_subheading'
            AND `value` = 'One sign-in for council staff, stage chairpersons, riders and government officers.'");
        $db->query("UPDATE `settings` SET `value` = '', `description` = 'Help text under the sign-in form (empty = hidden)' WHERE `key` = 'login_help_text'
            AND `value` = 'Riders get their account from their stage chairperson. Council staff get theirs from the BodaERP administrator.'");
    },
];
