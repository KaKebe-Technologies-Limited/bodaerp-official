<?php
return [
    'description' => 'Payments: ioTec mobile-money reference columns',
    'up' => function (mysqli $db) {
        mig_add_column($db, 'payments', 'transaction_reference', 'VARCHAR(100) NULL DEFAULT NULL AFTER `receipt_number`');
        mig_add_column($db, 'payments', 'iotec_transaction_id',  'VARCHAR(100) NULL DEFAULT NULL AFTER `transaction_reference`');
        mig_add_column($db, 'payments', 'payer_phone',           'VARCHAR(30) NULL DEFAULT NULL AFTER `iotec_transaction_id`');
        mig_add_index($db, 'payments', 'idx_payments_iotec_tx', '`iotec_transaction_id`');
    },
];
