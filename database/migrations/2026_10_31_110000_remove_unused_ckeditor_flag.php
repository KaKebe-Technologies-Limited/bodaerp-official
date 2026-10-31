<?php
// The rich-text editor (CKEditor) was dropped: the project is plain PHP + MySQL + plain JavaScript.
// Remove its feature flag so the settings list only contains things that exist.
return [
    'description' => 'Remove the unused CKEditor feature flag from settings',
    'up' => function (mysqli $db) {
        if (mig_table_exists($db, 'settings')) {
            mig_exec($db, "DELETE FROM `settings` WHERE `key` = 'feature_ckeditor5'");
        }
    },
];
