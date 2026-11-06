<?php
// Every address the platform itself uses is on bodaerp.site (owner, 2026-11-06): the demo and staff accounts that
// were on bodaerp.com, gov.bodaerp.com or made-up council domains, and the support and contact settings.
// Riders' own addresses are left alone. An account is skipped when its new address is already taken.
return [
    'description' => 'Platform email addresses moved to @bodaerp.site',
    'up' => function (mysqli $db) {
        $exact = [
            'council@liracityuganda.go.ug' => 'lira@bodaerp.site', 'council@kcca.go.ug' => 'kampala@bodaerp.site',
            'council@gulucity.go.ug' => 'gulu@bodaerp.site', 'council@mbararacity.go.ug' => 'mbarara@bodaerp.site',
            'council@soroticity.go.ug' => 'soroti@bodaerp.site', 'ocen@railway.lira.ug' => 'ocen@bodaerp.site',
            'opio@market.lira.ug' => 'opio@bodaerp.site', 'tumwine@mbararamain.mba.ug' => 'tumwine@bodaerp.site',
            'nakato@nakasero.kla.ug' => 'nakato@bodaerp.site', 'komakech@gulumain.gulu.ug' => 'komakech@bodaerp.site',
        ];
        $move = $db->prepare("UPDATE `users` SET `email` = ? WHERE `email` = ? AND NOT EXISTS (SELECT 1 FROM (SELECT `email` FROM `users`) taken WHERE taken.`email` = ?)");
        foreach ($exact as $old => $new) {
            $move->bind_param('sss', $new, $old, $new);
            $move->execute();
        }
        $move->close();
        foreach (['@gov.bodaerp.com', '@bodaerp.com'] as $suffix) {
            $like = '%' . $suffix;
            $rows = $db->prepare("SELECT `id`, `email` FROM `users` WHERE `email` LIKE ?");
            $rows->bind_param('s', $like);
            $rows->execute();
            $found = $rows->get_result()->fetch_all(MYSQLI_ASSOC);
            $rows->close();
            foreach ($found as $row) {
                $new = substr($row['email'], 0, -strlen($suffix)) . '@bodaerp.site';
                $update = $db->prepare("UPDATE `users` SET `email` = ? WHERE `id` = ? AND NOT EXISTS (SELECT 1 FROM (SELECT `email` FROM `users`) taken WHERE taken.`email` = ?)");
                $update->bind_param('sis', $new, $row['id'], $new);
                $update->execute();
                $update->close();
            }
        }
        foreach (['support_email', 'contact_email', 'contact_inbox_email'] as $key) {
            $setting = $db->prepare("UPDATE `settings` SET `value` = 'support@bodaerp.site' WHERE `key` = ? AND `value` IN ('support@kakebe.tech', '')");
            $setting->bind_param('s', $key);
            $setting->execute();
            $setting->close();
        }
    },
];
