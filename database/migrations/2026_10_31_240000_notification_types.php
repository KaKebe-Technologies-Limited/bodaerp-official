<?php
// Riders are told in their portal when a data request is answered and when a mobile-money payment fails.
return [
    'description' => 'Notification types for data requests and failed payments',
    'up' => function (mysqli $db) {
        $type = (string) mig_column_type($db, 'notifications', 'type');
        if (str_starts_with($type, 'enum') && !str_contains($type, "'Data Request'")) {
            mig_exec($db, "ALTER TABLE `notifications` MODIFY `type` ENUM('Renewal Reminder','Payment Confirmed','Important Update','ID Card Issued',"
                . "'Registration Complete','Data Request','Payment Failed') NOT NULL");
        }
    },
];
