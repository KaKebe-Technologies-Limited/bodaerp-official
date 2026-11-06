<?php
// Riders can object to how their data is used (a request type of its own), and can appeal an enforcement action
// from their portal; the city answers the appeal on the Enforcement page. Privacy text pointed at "My Profile",
// but the requests live under My account → Privacy and your data: only that phrase is replaced, edits are kept.
return [
    'description' => 'Objection requests, enforcement appeals, privacy text points to the right page',
    'up' => function (mysqli $db) {
        $type = (string) mig_column_type($db, 'data_requests', 'type');
        if (!str_contains($type, "'objection'")) {
            mig_exec($db, "ALTER TABLE `data_requests` MODIFY `type` ENUM('export','correction','deletion','objection') NOT NULL");
        }
        mig_add_column($db, 'enforcement_actions', 'appeal_text', "text DEFAULT NULL AFTER `status`");
        mig_add_column($db, 'enforcement_actions', 'appeal_at', "datetime DEFAULT NULL AFTER `appeal_text`");
        mig_add_column($db, 'enforcement_actions', 'appeal_status', "enum('open','upheld','rejected') DEFAULT NULL AFTER `appeal_at`");
        mig_add_column($db, 'enforcement_actions', 'appeal_response', "varchar(1000) DEFAULT NULL AFTER `appeal_status`");
        mig_add_column($db, 'enforcement_actions', 'appeal_by', "int(11) DEFAULT NULL AFTER `appeal_response`");
        mig_add_index($db, 'enforcement_actions', 'idx_enforcement_rider', '`rider_id`, `type`, `status`');

        $swaps = [
            ['(My Profile → Privacy)', '(My account → Privacy and your data)'],
            ['or send a correction request from My Profile in your portal.', 'or send a correction request from My account → Privacy and your data in your portal.'],
            ['Riders can download their profile and payment history from My Profile.', 'Riders can download their profile and payment history from My account → Privacy and your data, and each receipt from Payment history.'],
        ];
        $pages = $db->prepare("UPDATE `pages` SET `body` = REPLACE(`body`, ?, ?)");
        $items = $db->prepare("UPDATE `content_items` SET `body` = REPLACE(`body`, ?, ?)");
        foreach ($swaps as [$old, $new]) {
            $pages->bind_param('ss', $old, $new); $pages->execute();
            $items->bind_param('ss', $old, $new); $items->execute();
        }
        $pages->close(); $items->close();
    },
];
