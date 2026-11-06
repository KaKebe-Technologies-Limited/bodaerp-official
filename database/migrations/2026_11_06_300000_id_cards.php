<?php
// ID card register. Every printed card is a row with its own serial and the QR token it carries, so a card that is
// replaced, reported lost or cancelled stops verifying while the new one works. riders.verify_token stays the token
// of the current card. Existing riders get card 1 with the token they already have, so cards in circulation still scan.
// Card wording and which personal details are printed live in settings (category id_card, Super Admin → Text & branding).
return [
    'description' => 'ID card register with serials, print and hand-over tracking, card wording settings',
    'up' => function (mysqli $db) {
        mig_exec($db, "CREATE TABLE IF NOT EXISTS `id_cards` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `rider_id` int(11) NOT NULL,
            `city_id` char(3) NOT NULL,
            `stage_id` int(11) DEFAULT NULL,
            `version` smallint(5) unsigned NOT NULL DEFAULT 1,
            `serial` varchar(60) NOT NULL,
            `verify_token` char(20) NOT NULL,
            `reason` varchar(20) NOT NULL DEFAULT 'first',
            `status` enum('issued','printed','collected','replaced','lost','cancelled') NOT NULL DEFAULT 'issued',
            `valid_until` date DEFAULT NULL,
            `note` varchar(300) DEFAULT NULL,
            `issued_by` int(11) DEFAULT NULL,
            `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
            `printed_by` int(11) DEFAULT NULL,
            `printed_at` datetime DEFAULT NULL,
            `print_count` smallint(5) unsigned NOT NULL DEFAULT 0,
            `collected_by` int(11) DEFAULT NULL,
            `collected_at` datetime DEFAULT NULL,
            `ended_by` int(11) DEFAULT NULL,
            `ended_at` datetime DEFAULT NULL,
            `end_reason` varchar(300) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_id_cards_serial` (`serial`),
            KEY `idx_id_cards_rider` (`rider_id`, `version`),
            KEY `idx_id_cards_token` (`verify_token`),
            KEY `idx_id_cards_city` (`city_id`, `status`),
            KEY `idx_id_cards_stage` (`stage_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $missing = $db->query("SELECT id FROM riders WHERE verify_token IS NULL OR verify_token = ''");
        $fill = $db->prepare("UPDATE riders SET verify_token = ? WHERE id = ?");
        while ($row = $missing->fetch_assoc()) {
            $token = bin2hex(random_bytes(10)); $id = (int) $row['id'];
            $fill->bind_param('si', $token, $id); $fill->execute();
        }
        $fill->close();
        mig_exec($db, "INSERT INTO id_cards (rider_id, city_id, stage_id, version, serial, verify_token, reason, status, valid_until, issued_by, issued_at)
            SELECT r.id, r.city_id, r.stage_id, 1, CONCAT(r.id_number, '-01'), r.verify_token, 'first', 'issued', r.expiry_date, r.created_by, r.created_at
            FROM riders r WHERE r.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM id_cards c WHERE c.rider_id = r.id)");

        $settings = [
            ['id_card_title', 'Boda boda operator permit', 'text', 'Line under the city name on the front of the card', 1],
            ['id_card_back_statement', 'This permit is the property of {city} and must be shown to an authorised officer on request.', 'text', 'Statement on the back of the card. {city} becomes the city name.', 2],
            ['id_card_back_terms', 'Non-transferable. Renew every year.', 'text', 'Small print on the back of the card, before the card serial', 3],
            ['id_card_show_nin', 'true', 'boolean', 'Print the rider\'s National ID number (NIN) on the card', 4],
            ['id_card_show_dob', 'true', 'boolean', 'Print the date of birth on the card', 5],
            ['id_card_show_address', 'false', 'boolean', 'Print the home address on the card (anyone holding the card can read it)', 6],
            ['id_card_colour', '#b01c24', 'color', 'Colour of the card header band', 7],
        ];
        $s = $db->prepare("INSERT IGNORE INTO `settings` (`key`,`value`,`value_type`,`category`,`description`,`is_feature_flag`,`sort_order`) VALUES (?,?,?,'id_card',?,0,?)");
        foreach ($settings as [$key, $value, $type, $desc, $sort]) { $s->bind_param('ssssi', $key, $value, $type, $desc, $sort); $s->execute(); }
        $s->close();
    },
];
