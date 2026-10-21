<?php
// Creates the whole base schema on a brand-new, empty database. Existing installs are left untouched.
return [
    'description' => 'Create the base schema on an empty database',
    'up' => function (mysqli $db) {
        $core = ['users', 'cities', 'riders', 'stages', 'payments'];
        $present = array_values(array_filter($core, fn($t) => mig_table_exists($db, $t)));
        if (count($present) === count($core)) return;      // existing install: nothing to do
        if ($present) {
            throw new RuntimeException('Partial schema found (' . implode(', ', $present) . ' exist). Refusing to auto-create the base schema over it.');
        }
        mig_run_sql_file($db, __DIR__ . '/../bodaerp_full.sql');   // empty database: safe to build everything
    },
];
