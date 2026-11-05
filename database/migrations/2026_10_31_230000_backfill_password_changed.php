<?php
// Accounts that existed before password_changed_at was added already have a real password; record that,
// so only people who have not yet used their invite link show as "invite not accepted" in All Users.
return [
    'description' => 'Mark existing accounts as having a password',
    'up' => function (mysqli $db) {
        mig_exec($db, "UPDATE `users` SET `password_changed_at` = COALESCE(`updated_at`, `created_at`) WHERE `password_changed_at` IS NULL");
    },
];
