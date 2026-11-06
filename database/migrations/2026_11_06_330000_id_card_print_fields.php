<?php
// What staff may adjust on a card before it is printed (name as printed, photo framing, issue date, signatory,
// remark, which personal details show) is kept per card in id_cards.print_data as JSON. Plate, stage and expiry
// always come from the rider record, so the printed card and the QR check agree. Adds the card's country line and
// the signatory title used when a card has none of its own.
return [
    'description' => 'Per-card print fields and card heading settings',
    'up' => function (mysqli $db) {
        mig_add_column($db, 'id_cards', 'print_data', "text DEFAULT NULL AFTER `note`");
        mig_add_column($db, 'id_cards', 'edited_by', "int(11) DEFAULT NULL AFTER `print_data`");
        mig_add_column($db, 'id_cards', 'edited_at', "datetime DEFAULT NULL AFTER `edited_by`");
        $settings = [
            ['id_card_country_line', 'The Republic of Uganda', 'text', 'Top line of the card, above the city name. Leave empty to leave it out.', 0],
            ['id_card_signatory_title', 'Authorised officer', 'text', 'Title printed under the issuing officer\'s name on the back of the card', 8],
        ];
        $s = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,'id_card',?,0,?)");
        foreach ($settings as [$key, $value, $type, $desc, $sort]) { $s->bind_param('ssssi', $key, $value, $type, $desc, $sort); $s->execute(); }
        $s->close();
        mig_exec($db, "UPDATE `settings` SET `value` = 'Boda boda operator permit' WHERE `key` = 'id_card_title' AND `value` = 'Boda boda operator licence'");
        mig_exec($db, "UPDATE `settings` SET `value` = 'This permit is the property of {city} and must be shown to an authorised officer on request.' WHERE `key` = 'id_card_back_statement' AND `value` = 'This card is the property of {city} and must be surrendered on request.'");
    },
];
