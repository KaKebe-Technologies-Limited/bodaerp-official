<?php
// Audit log actions were a fixed ENUM of six values, so security events (ACCESS_DENIED, PASSWORD_CHANGED,
// PAYMENT_CONFIRMED, EXPORT ...) could not be recorded. Widen to VARCHAR; existing rows keep their values.
return [
    'description' => 'Audit log action becomes free text (records security and export events)',
    'up' => function (mysqli $db) {
        if (str_starts_with((string) mig_column_type($db, 'audit_logs', 'action'), 'enum')) {
            mig_exec($db, "ALTER TABLE `audit_logs` MODIFY `action` VARCHAR(40) NOT NULL");
        }
        mig_add_index($db, 'audit_logs', 'idx_audit_action_created', '`action`, `created_at`');
    },
];
