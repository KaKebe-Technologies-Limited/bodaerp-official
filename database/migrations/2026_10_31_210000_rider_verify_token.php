<?php
// Random, unguessable code printed in each ID card's QR. Permit numbers are sequential, so looking a rider
// up by permit number now shows only status and a masked name; the photo and full name need the QR token.
return [
    'description' => 'Random verification token for rider ID-card QR codes',
    'up' => function (mysqli $db) {
        mig_add_column($db, 'riders', 'verify_token', 'CHAR(20) NULL AFTER `id_number`');
        mig_add_index($db, 'riders', 'uq_riders_verify_token', '`verify_token`', true);
        $res = $db->query("SELECT id FROM riders WHERE verify_token IS NULL");
        $ids = array_column($res->fetch_all(MYSQLI_ASSOC), 'id');
        $res->free();
        $stmt = $db->prepare("UPDATE riders SET verify_token = ? WHERE id = ?");
        foreach ($ids as $id) {
            $token = bin2hex(random_bytes(10));
            $id = (int) $id;
            $stmt->bind_param('si', $token, $id);
            $stmt->execute();
        }
        $stmt->close();
    },
];
