<?php
// The card now follows the layout of the national ID: one signature, the holder's, kept on the rider so every later
// card prints it (riders.signature_path, drawn or uploaded by staff). The issuing officer's name and title are no
// longer printed, so their setting goes. Next of kin can be printed on the back for emergencies.
return [
    'description' => 'Holder signature on riders, next of kin on the card, no officer signatory',
    'up' => function (mysqli $db) {
        mig_add_column($db, 'riders', 'signature_path', "varchar(255) DEFAULT NULL AFTER `photo_path`");
        $key = 'id_card_show_kin'; $value = 'true'; $type = 'boolean'; $sort = 7;
        $desc = 'Print the next of kin and their phone number on the back of the card, for emergencies';
        $s = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,'id_card',?,0,?)");
        $s->bind_param('ssssi', $key, $value, $type, $desc, $sort);
        $s->execute();
        $s->close();
        mig_exec($db, "DELETE FROM `settings` WHERE `key` = 'id_card_signatory_title'");
        mig_exec($db, "UPDATE `settings` SET `sort_order` = 8 WHERE `key` = 'id_card_colour'");
    },
];
