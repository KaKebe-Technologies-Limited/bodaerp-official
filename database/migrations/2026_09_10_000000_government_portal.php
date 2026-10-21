<?php
return [
    'description' => 'Users: add the government role and agency name',
    'up' => function (mysqli $db) {
        $type = mig_column_type($db, 'users', 'role');
        if ($type !== null && stripos($type, "'government'") === false) {
            mig_exec($db, "ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','city_admin','chairperson','rider','government') NOT NULL");
        }
        mig_add_column($db, 'users', 'agency_name', 'VARCHAR(150) NULL AFTER `stage_id`');
    },
];
